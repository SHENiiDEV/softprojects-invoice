<?php
/**
 * Ultra-Resilient CSV Parser for WooCommerce PDF Invoice Batch Generator.
 * Handles UTF-8, UTF-16LE, UTF-16BE, Windows-1251, CP1252, Mac line endings, dynamic delimiters, and fuzzy header detection.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WC_PIBG_TEST_RUN' ) ) {
	exit;
}

class WC_PIBG_CSV_Parser {

	/**
	 * Parse a CSV file and return structured records.
	 *
	 * @param string $file_path Path to the CSV file.
	 * @return array Array containing 'success', 'data', or 'error'.
	 */
	public static function parse( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'error'   => __( 'CSV файл не найден или недоступен для чтения.', 'wc-pdf-invoice-generator' ),
			);
		}

		// Enable auto-detection of line endings
		$previous_line_endings = ini_get( 'auto_detect_line_endings' );
		@ini_set( 'auto_detect_line_endings', true );

		$content = file_get_contents( $file_path );
		if ( false === $content || strlen( trim( $content ) ) === 0 ) {
			@ini_set( 'auto_detect_line_endings', $previous_line_endings );
			return array(
				'success' => false,
				'error'   => __( 'Файл пуст или содержит некорректные данные.', 'wc-pdf-invoice-generator' ),
			);
		}

		// Convert encodings (UTF-16LE, UTF-16BE, Windows-1251, Windows-1252 to UTF-8)
		$content = self::ensure_utf8( $content );

		// Strip UTF-8 BOM
		$bom = pack( 'H*', 'EFBBBF' );
		$content = preg_replace( "/^$bom/", '', $content );

		// Detect delimiter from first non-empty lines
		$lines = preg_split( '/\r\n|\r|\n/', trim( $content ) );
		if ( empty( $lines ) ) {
			@ini_set( 'auto_detect_line_endings', $previous_line_endings );
			return array(
				'success' => false,
				'error'   => __( 'Не удалось прочитать строки из файла.', 'wc-pdf-invoice-generator' ),
			);
		}

		$first_non_empty_line = '';
		foreach ( $lines as $l ) {
			if ( strlen( trim( $l ) ) > 0 ) {
				$first_non_empty_line = $l;
				break;
			}
		}

		$delimiter = self::detect_delimiter( $first_non_empty_line );

		// Parse all rows using fgetcsv
		$handle = fopen( 'php://temp', 'r+' );
		fwrite( $handle, $content );
		rewind( $handle );

		$raw_rows = array();
		while ( ( $row = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) !== false ) {
			if ( ! empty( array_filter( $row, 'strlen' ) ) ) {
				$raw_rows[] = $row;
			}
		}

		fclose( $handle );
		@ini_set( 'auto_detect_line_endings', $previous_line_endings );

		if ( empty( $raw_rows ) ) {
			return array(
				'success' => false,
				'error'   => __( 'В CSV файле не найдено строк с данными.', 'wc-pdf-invoice-generator' ),
			);
		}

		return self::process_raw_rows( $raw_rows );
	}

	/**
	 * Convert content to valid UTF-8.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function ensure_utf8( $content ) {
		// Check UTF-16LE BOM (\xFF\xFE)
		if ( substr( $content, 0, 2 ) === "\xFF\xFE" ) {
			return mb_convert_encoding( substr( $content, 2 ), 'UTF-8', 'UTF-16LE' );
		}
		// Check UTF-16BE BOM (\xFE\xFF)
		if ( substr( $content, 0, 2 ) === "\xFE\xFF" ) {
			return mb_convert_encoding( substr( $content, 2 ), 'UTF-8', 'UTF-16BE' );
		}

		// Check for null bytes indicating UTF-16 without BOM
		if ( substr_count( substr( $content, 0, 200 ), "\x00" ) > 5 ) {
			$converted = @mb_convert_encoding( $content, 'UTF-8', 'UTF-16LE' );
			if ( ! empty( $converted ) ) {
				return $converted;
			}
		}

		if ( function_exists( 'mb_detect_encoding' ) ) {
			$enc = mb_detect_encoding( $content, array( 'UTF-8', 'Windows-1251', 'Windows-1252', 'ISO-8859-1' ), true );
			if ( $enc && $enc !== 'UTF-8' ) {
				return mb_convert_encoding( $content, 'UTF-8', $enc );
			}
		}

		return $content;
	}

	/**
	 * Detect CSV delimiter by counting occurrences in line.
	 *
	 * @param string $line
	 * @return string
	 */
	public static function detect_delimiter( $line ) {
		$delimiters = array( ',', ';', "\t", '|' );
		$counts     = array();

		foreach ( $delimiters as $delim ) {
			$counts[ $delim ] = substr_count( $line, $delim );
		}

		arsort( $counts );
		$chosen = key( $counts );

		return $counts[ $chosen ] > 0 ? $chosen : ',';
	}

	/**
	 * Process raw extracted 2D table rows into structured data records.
	 * Finds header row dynamically in the first 10 rows.
	 *
	 * @param array $raw_rows
	 * @return array
	 */
	public static function process_raw_rows( $raw_rows ) {
		if ( empty( $raw_rows ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Таблица пуста.', 'wc-pdf-invoice-generator' ),
			);
		}

		// Find header row in first 10 rows
		$header_index = 0;
		$best_map     = array();
		$max_matches  = 0;

		$scan_limit = min( 10, count( $raw_rows ) );
		for ( $i = 0; $i < $scan_limit; $i++ ) {
			$candidate_map = self::normalize_headers( $raw_rows[ $i ] );
			if ( count( $candidate_map ) > $max_matches ) {
				$max_matches  = count( $candidate_map );
				$header_index = $i;
				$best_map     = $candidate_map;
			}
		}

		// If no recognized headers, fallback to positional or heuristic mapping
		if ( empty( $best_map ) || ! isset( $best_map['amount'] ) ) {
			$best_map = self::heuristic_column_mapping( $raw_rows );
		}

		$data_rows = array_slice( $raw_rows, $header_index + 1 );
		$records   = array();
		$row_id    = 1;

		foreach ( $data_rows as $row ) {
			if ( empty( array_filter( $row, 'strlen' ) ) ) {
				continue;
			}

			$record = self::map_row_to_record( $row, $best_map, $row_id );
			if ( $record ) {
				$records[] = $record;
				$row_id++;
			}
		}

		if ( empty( $records ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Не удалось извлечь записи из таблицы.', 'wc-pdf-invoice-generator' ),
			);
		}

		return array(
			'success' => true,
			'data'    => $records,
		);
	}

	/**
	 * Normalize header names to standardized keys.
	 *
	 * @param array $headers
	 * @return array
	 */
	public static function normalize_headers( $headers ) {
		$map = array();

		foreach ( $headers as $index => $raw_header ) {
			$raw_str = (string) $raw_header;
			// Clean special characters but keep letters, digits, and spaces
			$clean = mb_strtolower( trim( preg_replace( '/[^\p{L}\p{N}\s_-]/u', ' ', $raw_str ) ), 'UTF-8' );
			$clean = preg_replace( '/[\s_-]+/', ' ', $clean );

			if ( empty( $clean ) ) {
				continue;
			}

			// 1. First Name (Strict check: ensure it does NOT match "Last Name")
			if ( preg_match( '/\b(customer\s*first\s*name|first\s*name|first_name|имя\s*клиента)\b/u', $clean ) && ! isset( $map['first_name'] ) ) {
				$map['first_name'] = $index;
			}
			// 2. Last Name
			elseif ( preg_match( '/\b(customer\s*last\s*name|last\s*name|last_name|фамилия\s*клиента|фамилия)\b/u', $clean ) && ! isset( $map['last_name'] ) ) {
				$map['last_name'] = $index;
			}
			// 3. ID / Transaction ID
			elseif ( preg_match( '/\b(id|transaction\s*id|trans\s*id|номер\s*транзакции)\b/u', $clean ) && ! isset( $map['id'] ) ) {
				$map['id'] = $index;
			}
			// 4. Created Date
			elseif ( preg_match( '/\b(created\s*at|created|transaction\s*date|date|дата)\b/u', $clean ) && ! isset( $map['created'] ) ) {
				$map['created'] = $index;
			}
			// 5. State / Status
			elseif ( preg_match( '/\b(state|status|статус)\b/u', $clean ) && ! isset( $map['state'] ) ) {
				$map['state'] = $index;
			}
			// 6. Amount
			elseif ( preg_match( '/\b(amount|sum|total|цена|сумма|стоимость)\b/u', $clean ) && ! isset( $map['amount'] ) ) {
				$map['amount'] = $index;
			}
			// 7. Currency
			elseif ( preg_match( '/\b(currency|валюта)\b/u', $clean ) && ! isset( $map['currency'] ) ) {
				$map['currency'] = $index;
			}
			// 8. Account Number
			elseif ( preg_match( '/\b(customer\s*account\s*number|account\s*number|account|номер\s*счета|счет|аккаунт)\b/u', $clean ) && ! isset( $map['account_number'] ) ) {
				$map['account_number'] = $index;
			}
			// 9. Email
			elseif ( preg_match( '/\b(customer\s*email|email|e\s*mail|почта|электронная\s*почта)\b/u', $clean ) && ! isset( $map['email'] ) ) {
				$map['email'] = $index;
			}
			// 10. Phone
			elseif ( preg_match( '/\b(customer\s*phone|phone|телефон|тел)\b/u', $clean ) && ! isset( $map['phone'] ) ) {
				$map['phone'] = $index;
			}
			// 11. Date of birth
			elseif ( preg_match( '/\b(customer\s*date\s*of\s*birth|date\s*of\s*birth|dob|дата\s*рождения)\b/u', $clean ) && ! isset( $map['dob'] ) ) {
				$map['dob'] = $index;
			}
			// 12. Billing Address
			elseif ( preg_match( '/\b(billing\s*address|address\s*1|address|адрес)\b/u', $clean ) && ! isset( $map['address'] ) ) {
				$map['address'] = $index;
			}
			// 13. Billing City
			elseif ( preg_match( '/\b(billing\s*city|city|город)\b/u', $clean ) && ! isset( $map['city'] ) ) {
				$map['city'] = $index;
			}
			// 14. Billing Country
			elseif ( preg_match( '/\b(billing\s*country|country|страна)\b/u', $clean ) && ! isset( $map['country'] ) ) {
				$map['country'] = $index;
			}
			// 15. Billing Postal Code
			elseif ( preg_match( '/\b(billing\s*postal\s*code|billing\s*zip|postal\s*code|postcode|zip\s*code|zip|индекс|почтовый\s*индекс)\b/u', $clean ) && ! isset( $map['postal_code'] ) ) {
				$map['postal_code'] = $index;
			}
			// 16. Fallback General Name
			elseif ( preg_match( '/\b(name|клиент|имя)\b/u', $clean ) && ! isset( $map['first_name'] ) ) {
				$map['first_name'] = $index;
			}
		}

		return $map;
	}

	/**
	 * Heuristic column mapping when headers are not found by name.
	 * Analyzes cell contents (email addresses, currency codes, numeric amounts).
	 *
	 * @param array $rows
	 * @return array
	 */
	public static function heuristic_column_mapping( $rows ) {
		$map = array();
		if ( empty( $rows ) ) {
			return $map;
		}

		// Inspect first 5 data rows
		$sample_rows = array_slice( $rows, 0, 5 );

		foreach ( $sample_rows as $row ) {
			foreach ( $row as $col_idx => $val ) {
				$val = trim( (string) $val );

				// Check for email
				if ( ! isset( $map['email'] ) && filter_var( $val, FILTER_VALIDATE_EMAIL ) ) {
					$map['email'] = $col_idx;
				}
				// Check for currency code (EUR, USD, GBP, etc.)
				if ( ! isset( $map['currency'] ) && preg_match( '/^(EUR|USD|GBP|RUB|CAD|AUD|CHF|JPY)$/i', $val ) ) {
					$map['currency'] = $col_idx;
				}
				// Check for numeric amount
				if ( ! isset( $map['amount'] ) && is_numeric( str_replace( array( ',', ' ' ), array( '.', '' ), $val ) ) ) {
					$num = (float) str_replace( array( ',', ' ' ), array( '.', '' ), $val );
					if ( $num > 0 && $num < 10000000 && ! preg_match( '/^\d{4}$/', $val ) ) { // Not a year
						$map['amount'] = $col_idx;
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Map a raw row to a standardized data array.
	 *
	 * @param array $row
	 * @param array $map
	 * @param int   $row_index
	 * @return array
	 */
	public static function map_row_to_record( $row, $map, $row_index ) {
		$get_val = function( $key, $default = '' ) use ( $row, $map ) {
			if ( isset( $map[ $key ] ) && isset( $row[ $map[ $key ] ] ) ) {
				return trim( (string) $row[ $map[ $key ] ] );
			}
			return $default;
		};

		// Clean and parse Amount
		$raw_amount = $get_val( 'amount', '0' );
		$raw_amount = str_replace( array( ' ', "\xC2\xA0" ), '', $raw_amount );
		$raw_amount = str_replace( ',', '.', $raw_amount );
		$raw_amount = preg_replace( '/[^0-9.]/', '', $raw_amount );
		$amount     = (float) $raw_amount;

		$first_name = $get_val( 'first_name', '' );
		$last_name  = $get_val( 'last_name', '' );

		if ( empty( $first_name ) && empty( $last_name ) ) {
			$first_name = 'Customer ' . $row_index;
		}

		$full_name = trim( $first_name . ' ' . $last_name );

		$currency = strtoupper( $get_val( 'currency', function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR' ) );
		if ( empty( $currency ) ) {
			$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR';
		}

		$raw_email = $get_val( 'email', '' );
		$email     = '';
		if ( preg_match( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $raw_email, $matches ) ) {
			$email = $matches[0];
		} else {
			$email = $raw_email;
		}

		$trans_id       = $get_val( 'id', '' );
		$created_date   = $get_val( 'created', '' );
		$state          = $get_val( 'state', '' );
		$phone          = $get_val( 'phone', '' );
		$dob            = $get_val( 'dob', '' );
		$account_number = $get_val( 'account_number', 'ACC-' . str_pad( (string) $row_index, 5, '0', STR_PAD_LEFT ) );
		$address        = $get_val( 'address', '' );
		$city           = $get_val( 'city', '' );
		$country        = $get_val( 'country', '' );
		$postal_code    = $get_val( 'postal_code', '' );

		return array(
			'row_id'         => $row_index,
			'transaction_id' => $trans_id,
			'created_at'     => $created_date,
			'state'          => $state,
			'first_name'     => $first_name,
			'last_name'      => $last_name,
			'full_name'      => ! empty( $full_name ) ? $full_name : 'Customer ' . $row_index,
			'phone'          => $phone,
			'dob'            => $dob,
			'amount'         => $amount,
			'amount_cents'   => (int) round( $amount * 100 ),
			'currency'       => $currency,
			'account_number' => $account_number,
			'email'          => $email,
			'address'        => $address,
			'city'           => $city,
			'country'        => $country,
			'postal_code'    => $postal_code,
			'raw_row'        => $row,
		);
	}
}
