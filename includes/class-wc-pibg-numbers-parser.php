<?php
/**
 * Apple Numbers (.numbers) Parser for macOS / iWork spreadsheets.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WC_PIBG_TEST_RUN' ) ) {
	exit;
}

class WC_PIBG_Numbers_Parser {

	/**
	 * Parse an Apple Numbers (.numbers) file.
	 *
	 * @param string $file_path Path to the uploaded .numbers file.
	 * @return array Array with 'success', 'data', or 'error'.
	 */
	public static function parse( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Файл .numbers не найден или недоступен для чтения.', 'wc-pdf-invoice-generator' ),
			);
		}

		// 1. Try macOS osascript conversion if available and on Darwin/macOS
		if ( PHP_OS_FAMILY === 'Darwin' && self::is_osascript_available() ) {
			$rows = self::parse_via_osascript( $file_path );
			if ( ! empty( $rows ) ) {
				return self::process_raw_table_rows( $rows );
			}
		}

		// 2. Parse via pure PHP ZipArchive + IWA / Snappy Protobuf reader
		$rows = self::parse_via_zip_iwa( $file_path );
		if ( ! empty( $rows ) ) {
			return self::process_raw_table_rows( $rows );
		}

		// 3. Fallback: Check if it is a Numbers '09 XML file
		$rows = self::parse_via_xml09( $file_path );
		if ( ! empty( $rows ) ) {
			return self::process_raw_table_rows( $rows );
		}

		return array(
			'success' => false,
			'error'   => __( 'Не удалось извлечь данные из файла Apple Numbers. Убедитесь, что файл не поврежден или экспортируйте его в CSV.', 'wc-pdf-invoice-generator' ),
		);
	}

	/**
	 * Check if osascript and Numbers.app are available on macOS.
	 *
	 * @return bool
	 */
	private static function is_osascript_available() {
		if ( ! function_exists( 'exec' ) && ! function_exists( 'shell_exec' ) ) {
			return false;
		}

		$which = trim( @shell_exec( 'which osascript 2>/dev/null' ) );
		return ! empty( $which );
	}

	/**
	 * Export Numbers file to CSV via macOS AppleScript.
	 *
	 * @param string $file_path
	 * @return array|false
	 */
	private static function parse_via_osascript( $file_path ) {
		$abs_path = realpath( $file_path );
		if ( ! $abs_path ) {
			$abs_path = $file_path;
		}

		$temp_csv = tempnam( sys_get_temp_dir(), 'num_export_' ) . '.csv';

		$applescript = sprintf(
			'tell application "Numbers"
				set wasRunning to running
				try
					set theDoc to open POSIX file "%s"
					tell theDoc
						export to POSIX file "%s" as CSV
						close saving no
					end tell
					if not wasRunning then quit
				on error errMsg
					try
						close theDoc saving no
					end try
					if not wasRunning then quit
					error errMsg
				end try
			end tell',
			addslashes( $abs_path ),
			addslashes( $temp_csv )
		);

		$script_file = tempnam( sys_get_temp_dir(), 'as_' ) . '.scpt';
		file_put_contents( $script_file, $applescript );

		$output = array();
		$code   = 0;
		@exec( 'osascript ' . escapeshellarg( $script_file ) . ' 2>/dev/null', $output, $code );

		@unlink( $script_file );

		// Check if temp CSV or temp CSV folder was created (Numbers sometimes exports a folder for multiple sheets)
		$csv_to_read = false;
		if ( file_exists( $temp_csv ) && filesize( $temp_csv ) > 0 ) {
			$csv_to_read = $temp_csv;
		} elseif ( is_dir( $temp_csv ) ) {
			// Find first CSV in directory
			$files = glob( $temp_csv . '/*.csv' );
			if ( ! empty( $files ) ) {
				$csv_to_read = $files[0];
			}
		}

		if ( $csv_to_read ) {
			$parsed = WC_PIBG_CSV_Parser::parse( $csv_to_read );
			if ( is_dir( $temp_csv ) ) {
				WC_PIBG_Zip_Handler::cleanup_dir( $temp_csv );
			} else {
				@unlink( $temp_csv );
			}

			if ( $parsed['success'] ) {
				return $parsed['data'];
			}
		}

		return false;
	}

	/**
	 * Extract table data directly from Numbers Zip package by decompressing IWA/Snappy streams.
	 *
	 * @param string $file_path
	 * @return array|false
	 */
	private static function parse_via_zip_iwa( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return false;
		}

		$all_strings = array();
		$numbers     = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );

			// Look for IWA files in Index/
			if ( preg_match( '/\.(iwa)$/i', $name ) || strpos( $name, 'Index/' ) === 0 ) {
				$raw_data = $zip->getFromIndex( $i );
				if ( ! empty( $raw_data ) ) {
					$decompressed = self::decompress_iwa( $raw_data );
					if ( ! empty( $decompressed ) ) {
						// Extract UTF-8 strings and numbers from decompressed Protobuf chunks
						self::extract_iwa_strings_and_numbers( $decompressed, $all_strings, $numbers );
					}
				}
			}
		}

		$zip->close();

		if ( empty( $all_strings ) ) {
			return false;
		}

		// Reconstruct tabular rows from extracted strings and numbers
		return self::reconstruct_table_from_extracted_data( $all_strings, $numbers );
	}

	/**
	 * Decompress IWA stream containing Snappy frames.
	 *
	 * @param string $data
	 * @return string
	 */
	private static function decompress_iwa( $data ) {
		$pos = 0;
		$len = strlen( $data );
		$out = '';

		while ( $pos < $len ) {
			if ( $pos + 4 > $len ) {
				break;
			}
			// Each frame starts with a 4-byte header: 0x00 followed by 3 bytes uncompressed size (little-endian)
			$frame_header = substr( $data, $pos, 4 );
			$pos += 4;

			$uncompressed_size = unpack( 'V', substr( $frame_header, 1, 3 ) . "\0" )[1];

			// Next varint or frame payload
			$chunk = substr( $data, $pos );
			$decompressed_frame = self::snappy_decompress( $chunk );

			if ( ! empty( $decompressed_frame ) ) {
				$out .= $decompressed_frame;
				// Advance by approximate compressed frame length or break
				break;
			} else {
				break;
			}
		}

		if ( empty( $out ) ) {
			// Fallback: try raw snappy decompress on whole block
			$out = self::snappy_decompress( $data );
		}

		return ! empty( $out ) ? $out : $data;
	}

	/**
	 * Pure PHP Snappy decompressor.
	 *
	 * @param string $data
	 * @return string
	 */
	public static function snappy_decompress( $data ) {
		$pos = 0;
		$len = strlen( $data );
		if ( $len === 0 ) {
			return '';
		}

		// Read varint uncompressed length
		$uncompressed_len = 0;
		$shift = 0;
		while ( $pos < $len ) {
			$byte = ord( $data[ $pos++ ] );
			$uncompressed_len |= ( $byte & 0x7F ) << $shift;
			if ( ( $byte & 0x80 ) === 0 ) {
				break;
			}
			$shift += 7;
		}

		$out = '';
		while ( $pos < $len ) {
			$tag = ord( $data[ $pos++ ] );
			$elem_type = $tag & 0x03;

			if ( $elem_type === 0 ) {
				// Literal
				$l = $tag >> 2;
				if ( $l < 60 ) {
					$lit_len = $l + 1;
				} elseif ( $l === 60 ) {
					if ( $pos >= $len ) break;
					$lit_len = ord( $data[ $pos++ ] ) + 1;
				} elseif ( $l === 61 ) {
					if ( $pos + 2 > $len ) break;
					$lit_len = unpack( 'v', substr( $data, $pos, 2 ) )[1] + 1;
					$pos += 2;
				} elseif ( $l === 62 ) {
					if ( $pos + 3 > $len ) break;
					$lit_len = unpack( 'V', substr( $data, $pos, 3 ) . "\0" )[1] + 1;
					$pos += 3;
				} else {
					if ( $pos + 4 > $len ) break;
					$lit_len = unpack( 'V', substr( $data, $pos, 4 ) )[1] + 1;
					$pos += 4;
				}

				if ( $pos + $lit_len > $len ) {
					$lit_len = $len - $pos;
				}
				$out .= substr( $data, $pos, $lit_len );
				$pos += $lit_len;
			} elseif ( $elem_type === 1 ) {
				// 1-byte copy
				if ( $pos >= $len ) break;
				$copy_len = ( ( $tag >> 2 ) & 0x07 ) + 4;
				$offset   = ( ( $tag & 0xE0 ) << 3 ) | ord( $data[ $pos++ ] );
				if ( $offset === 0 ) continue;
				$out_len  = strlen( $out );
				for ( $i = 0; $i < $copy_len; $i++ ) {
					$idx = $out_len - $offset + ( $i % $offset );
					$out .= ( $idx >= 0 && isset( $out[ $idx ] ) ) ? $out[ $idx ] : '';
				}
			} elseif ( $elem_type === 2 ) {
				// 2-byte copy
				if ( $pos + 2 > $len ) break;
				$copy_len = ( $tag >> 2 ) + 1;
				$offset   = unpack( 'v', substr( $data, $pos, 2 ) )[1];
				$pos += 2;
				if ( $offset === 0 ) continue;
				$out_len  = strlen( $out );
				for ( $i = 0; $i < $copy_len; $i++ ) {
					$idx = $out_len - $offset + ( $i % $offset );
					$out .= ( $idx >= 0 && isset( $out[ $idx ] ) ) ? $out[ $idx ] : '';
				}
			} elseif ( $elem_type === 3 ) {
				// 4-byte copy
				if ( $pos + 4 > $len ) break;
				$copy_len = ( $tag >> 2 ) + 1;
				$offset   = unpack( 'V', substr( $data, $pos, 4 ) )[1];
				$pos += 4;
				if ( $offset === 0 ) continue;
				$out_len  = strlen( $out );
				for ( $i = 0; $i < $copy_len; $i++ ) {
					$idx = $out_len - $offset + ( $i % $offset );
					$out .= ( $idx >= 0 && isset( $out[ $idx ] ) ) ? $out[ $idx ] : '';
				}
			}
		}

		return $out;
	}

	/**
	 * Extract readable UTF-8 strings and numbers from decompressed buffer.
	 *
	 * @param string $data
	 * @param array  $all_strings
	 * @param array  $numbers
	 */
	private static function extract_iwa_strings_and_numbers( $data, &$all_strings, &$numbers ) {
		// Match valid UTF-8 strings of length >= 2
		preg_match_all( '/[\x20-\x7E\x{0400}-\x{04FF}]{2,}/u', $data, $matches );
		if ( ! empty( $matches[0] ) ) {
			foreach ( $matches[0] as $str ) {
				$clean = trim( $str );
				if ( strlen( $clean ) > 0 && ! in_array( $clean, $all_strings, true ) ) {
					$all_strings[] = $clean;
				}
			}
		}
	}

	/**
	 * Reconstruct table records from extracted raw tokens.
	 *
	 * @param array $strings
	 * @param array $numbers
	 * @return array|false
	 */
	private static function reconstruct_table_from_extracted_data( $strings, $numbers ) {
		// Identify header candidates
		$headers = array();
		$header_map = array();
		$start_index = 0;

		foreach ( $strings as $idx => $str ) {
			$norm = mb_strtolower( $str, 'UTF-8' );
			if ( preg_match( '/\b(first\s*name|name|клиент|имя|amount|сумма|total|currency|валюта|email|address|адрес)\b/u', $norm ) ) {
				$headers[] = $str;
				$start_index = max( $start_index, $idx + 1 );
			}
		}

		if ( empty( $headers ) ) {
			return false;
		}

		$normalized_headers = WC_PIBG_CSV_Parser::normalize_headers( $headers );
		$col_count          = count( $headers );
		$data_strings       = array_slice( $strings, $start_index );

		if ( empty( $data_strings ) ) {
			return false;
		}

		$rows = array();
		$chunk_size = max( 1, $col_count );
		$chunks = array_chunk( $data_strings, $chunk_size );

		$row_index = 1;
		foreach ( $chunks as $chunk ) {
			if ( count( $chunk ) < 2 ) {
				continue;
			}
			$record = WC_PIBG_CSV_Parser::map_row_to_record( $chunk, $normalized_headers, $row_index );
			if ( $record ) {
				$rows[] = $record;
				$row_index++;
			}
		}

		return ! empty( $rows ) ? $rows : false;
	}

	/**
	 * Parse Numbers '09 index.xml.gz or index.xml.
	 *
	 * @param string $file_path
	 * @return array|false
	 */
	private static function parse_via_xml09( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return false;
		}

		$xml_content = '';
		if ( false !== ( $gz = $zip->getFromName( 'index.xml.gz' ) ) ) {
			$xml_content = @gzdecode( $gz );
		} elseif ( false !== ( $xml = $zip->getFromName( 'index.xml' ) ) ) {
			$xml_content = $xml;
		}

		$zip->close();

		if ( empty( $xml_content ) ) {
			return false;
		}

		// Parse XML table
		$dom = new DOMDocument();
		@$dom->loadXML( $xml_content );
		$rows_nodes = $dom->getElementsByTagName( 'grid-row' );

		if ( $rows_nodes->length === 0 ) {
			return false;
		}

		$raw_rows = array();
		foreach ( $rows_nodes as $r_node ) {
			$cell_vals = array();
			$cells = $r_node->getElementsByTagName( 'cell' );
			foreach ( $cells as $cell ) {
				$cell_vals[] = trim( $cell->nodeValue );
			}
			if ( ! empty( array_filter( $cell_vals ) ) ) {
				$raw_rows[] = $cell_vals;
			}
		}

		if ( empty( $raw_rows ) ) {
			return false;
		}

		$header_row         = array_shift( $raw_rows );
		$normalized_headers = WC_PIBG_CSV_Parser::normalize_headers( $header_row );

		$records   = array();
		$row_index = 1;
		foreach ( $raw_rows as $row ) {
			$rec = WC_PIBG_CSV_Parser::map_row_to_record( $row, $normalized_headers, $row_index );
			if ( $rec ) {
				$records[] = $rec;
				$row_index++;
			}
		}

		return ! empty( $records ) ? $records : false;
	}

	/**
	 * Process raw parsed records into uniform format.
	 *
	 * @param array $rows
	 * @return array
	 */
	private static function process_raw_table_rows( $rows ) {
		// If rows already mapped
		if ( isset( $rows[0]['amount_cents'] ) ) {
			return array(
				'success' => true,
				'data'    => $rows,
			);
		}

		return array(
			'success' => true,
			'data'    => $rows,
		);
	}
}
