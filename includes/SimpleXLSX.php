<?php
/**
 * WC_PIBG_SimpleXLSX - Lightweight Excel (.xlsx) Parser
 * Namespaced specifically for WooCommerce PDF Invoice Batch Generator to prevent conflicts with other plugins.
 */

if ( ! class_exists( 'WC_PIBG_SimpleXLSX' ) ) {

	class WC_PIBG_SimpleXLSX {

		public $workbook;
		public $sheets = array();
		public $sharedstrings = array();
		public $error = '';

		public static function parse( $filename, $is_data = false ) {
			$xlsx = new self();
			if ( $xlsx->_parse( $filename, $is_data ) ) {
				return $xlsx;
			}
			return false;
		}

		public static function parseError() {
			return 'WC_PIBG_SimpleXLSX parse error';
		}

		private function _parse( $filename, $is_data = false ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				$this->error = 'ZipArchive extension missing';
				return false;
			}

			$zip = new ZipArchive();
			$status = $is_data ? false : $zip->open( $filename );

			if ( true !== $status ) {
				$this->error = 'Cannot open XLSX file';
				return false;
			}

			$libxml_flags = ( defined( 'LIBXML_PARSEHUGE' ) ? LIBXML_PARSEHUGE : 0 ) | ( defined( 'LIBXML_NOERROR' ) ? LIBXML_NOERROR : 0 );

			// Read sharedStrings.xml
			$this->sharedstrings = array();
			$xml_data = $zip->getFromName( 'xl/sharedStrings.xml' );
			if ( false === $xml_data ) {
				// Case-insensitive search
				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$entry_name = $zip->getNameIndex( $i );
					if ( preg_match( '#sharedstrings\.xml$#i', $entry_name ) ) {
						$xml_data = $zip->getFromIndex( $i );
						break;
					}
				}
			}

			if ( false !== $xml_data && strlen( $xml_data ) > 0 ) {
				$sxml = @simplexml_load_string( $xml_data, 'SimpleXMLElement', $libxml_flags );
				if ( false !== $sxml ) {
					$si_nodes = $sxml->xpath( '//*[local-name()="si"]' );
					if ( ! empty( $si_nodes ) ) {
						foreach ( $si_nodes as $si ) {
							$t_nodes = $si->xpath( './/*[local-name()="t"]' );
							$str     = '';
							if ( ! empty( $t_nodes ) ) {
								foreach ( $t_nodes as $t ) {
									$str .= (string) $t;
								}
							}
							$this->sharedstrings[] = $str;
						}
					}
				}
			}

			// Read first worksheet (xl/worksheets/sheet1.xml)
			$sheet_data = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
			if ( false === $sheet_data ) {
				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$entry_name = $zip->getNameIndex( $i );
					if ( preg_match( '#worksheets/sheet\d*\.xml$#i', $entry_name ) ) {
						$sheet_data = $zip->getFromIndex( $i );
						break;
					}
				}
			}

			$zip->close();

			if ( false === $sheet_data || empty( $sheet_data ) ) {
				$this->error = 'No worksheet found';
				return false;
			}

			$this->sheets[0] = $this->_parseSheet( $sheet_data );
			return true;
		}

		private function _parseSheet( $xml_content ) {
			$rows = array();
			$libxml_flags = ( defined( 'LIBXML_PARSEHUGE' ) ? LIBXML_PARSEHUGE : 0 ) | ( defined( 'LIBXML_NOERROR' ) ? LIBXML_NOERROR : 0 );
			$sxml = @simplexml_load_string( $xml_content, 'SimpleXMLElement', $libxml_flags );

			if ( false === $sxml ) {
				return $rows;
			}

			$row_nodes = $sxml->xpath( '//*[local-name()="row"]' );
			if ( empty( $row_nodes ) ) {
				return $rows;
			}

			foreach ( $row_nodes as $row_node ) {
				$cells = $row_node->xpath( './*[local-name()="c"]' );
				$row_data = array();
				$last_col = -1;

				foreach ( $cells as $cell ) {
					$attrs = $cell->attributes();
					$ref   = isset( $attrs['r'] ) ? (string) $attrs['r'] : '';
					$type  = isset( $attrs['t'] ) ? (string) $attrs['t'] : '';

					if ( ! empty( $ref ) ) {
						$col_letter = preg_replace( '/[0-9]/', '', $ref );
						$col_idx    = self::col_letter_to_index( $col_letter );
					} else {
						$col_idx = $last_col + 1;
					}
					$last_col = $col_idx;

					$v_nodes = $cell->xpath( './*[local-name()="v"]' );
					$v_val   = ! empty( $v_nodes ) ? (string) $v_nodes[0] : '';
					$val     = '';

					if ( $type === 's' ) {
						$s_idx = (int) $v_val;
						$val   = isset( $this->sharedstrings[ $s_idx ] ) ? $this->sharedstrings[ $s_idx ] : '';
					} elseif ( $type === 'inlineStr' ) {
						$t_nodes = $cell->xpath( './/*[local-name()="t"]' );
						$val     = ! empty( $t_nodes ) ? (string) $t_nodes[0] : '';
					} else {
						$val = $v_val;
						if ( $val === '' ) {
							$t_nodes = $cell->xpath( './/*[local-name()="t"]' );
							if ( ! empty( $t_nodes ) ) {
								$val = (string) $t_nodes[0];
							}
						}
					}

					$row_data[ $col_idx ] = trim( $val );
				}

				if ( ! empty( $row_data ) ) {
					$max_col = max( array_keys( $row_data ) );
					$dense   = array();
					for ( $c = 0; $c <= $max_col; $c++ ) {
						$dense[ $c ] = isset( $row_data[ $c ] ) ? $row_data[ $c ] : '';
					}
					$rows[] = $dense;
				}
			}

			return $rows;
		}

		public function rows( $sheet_index = 0 ) {
			return isset( $this->sheets[ $sheet_index ] ) ? $this->sheets[ $sheet_index ] : array();
		}

		public static function col_letter_to_index( $col ) {
			$col   = strtoupper( trim( $col ) );
			$len   = strlen( $col );
			$index = 0;

			for ( $i = 0; $i < $len; $i++ ) {
				$index = $index * 26 + ( ord( $col[ $i ] ) - ord( 'A' ) + 1 );
			}

			return max( 0, $index - 1 );
		}
	}
}
