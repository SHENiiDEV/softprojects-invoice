<?php
/**
 * XLSX (Excel OpenXML) Parser using SimpleXLSX for WooCommerce PDF Invoice Batch Generator.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WC_PIBG_TEST_RUN' ) ) {
	exit;
}

require_once __DIR__ . '/SimpleXLSX.php';

class WC_PIBG_XLSX_Parser {

	/**
	 * Parse an Excel (.xlsx) file and return structured records.
	 *
	 * @param string $file_path
	 * @return array Array with 'success', 'data', or 'error'.
	 */
	public static function parse( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Файл .xlsx не найден или недоступен для чтения.', 'wc-pdf-invoice-generator' ),
			);
		}

		$xlsx = WC_PIBG_SimpleXLSX::parse( $file_path );
		if ( ! $xlsx ) {
			return array(
				'success' => false,
				'error'   => __( 'Не удалось прочитать файл .xlsx. Убедитесь, что файл не поврежден.', 'wc-pdf-invoice-generator' ),
			);
		}

		$rows = $xlsx->rows();
		if ( empty( $rows ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Файл Excel пуст.', 'wc-pdf-invoice-generator' ),
			);
		}

		// 1. Locate the header row (skipping title rows like "Payments (16)")
		$header_row_index = -1;
		foreach ( $rows as $idx => $row_data ) {
			$col0 = isset( $row_data[0] ) ? mb_strtolower( trim( (string) $row_data[0] ), 'UTF-8' ) : '';
			$col3 = isset( $row_data[3] ) ? mb_strtolower( trim( (string) $row_data[3] ), 'UTF-8' ) : '';

			// Check if row contains 'id' in col 0, or 'amount' in col 3, or matches common headers
			if ( $col0 === 'id' || $col3 === 'amount' || preg_match( '/\b(first\s*name|amount|created|customer)\b/i', implode( ' ', $row_data ) ) ) {
				$header_row_index = $idx;
				break;
			}
		}

		// If header row not found in top rows, default to row index 1 (Row 2 in Excel) or row index 0
		if ( $header_row_index === -1 ) {
			$header_row_index = count( $rows ) > 1 ? 1 : 0;
		}

		$headers   = isset( $rows[ $header_row_index ] ) ? $rows[ $header_row_index ] : array();
		$data_rows = array_slice( $rows, $header_row_index + 1 );

		if ( empty( $data_rows ) ) {
			return array(
				'success' => false,
				'error'   => __( 'В файле Excel не найдено строк с транзакциями.', 'wc-pdf-invoice-generator' ),
			);
		}

		// 2. Check if headers match standard 15-column schema
		$is_15_col_schema = (
			( isset( $headers[0] ) && stripos( $headers[0], 'id' ) !== false ) &&
			( isset( $headers[3] ) && stripos( $headers[3], 'amount' ) !== false )
		);

		$records = array();
		$row_id  = 1;

		if ( $is_15_col_schema ) {
			// Direct fast index mapping as specified by user
			foreach ( $data_rows as $data ) {
				if ( empty( array_filter( $data, 'strlen' ) ) ) {
					continue;
				}

				// 0 — Id
				$trans_id = isset( $data[0] ) ? trim( (string) $data[0] ) : '';
				// 1 — Created
				$created = isset( $data[1] ) ? trim( (string) $data[1] ) : '';
				// 2 — State
				$state = isset( $data[2] ) ? trim( (string) $data[2] ) : '';
				// 3 — Amount
				$raw_amount = isset( $data[3] ) ? trim( (string) $data[3] ) : '0';
				$raw_amount = str_replace( array( ' ', "\xC2\xA0" ), '', $raw_amount );
				$raw_amount = str_replace( ',', '.', $raw_amount );
				$raw_amount = preg_replace( '/[^0-9.]/', '', $raw_amount );
				$amount     = (float) $raw_amount;

				// 4 — Currency
				$currency = isset( $data[4] ) && ! empty( trim( $data[4] ) ) ? strtoupper( trim( (string) $data[4] ) ) : 'EUR';
				// 5 — Customer Account Number
				$account_number = isset( $data[5] ) ? trim( (string) $data[5] ) : 'ACC-' . str_pad( (string) $row_id, 5, '0', STR_PAD_LEFT );
				// 6 — Customer Email
				$raw_email = isset( $data[6] ) ? trim( (string) $data[6] ) : '';
				$email     = '';
				if ( preg_match( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $raw_email, $m ) ) {
					$email = $m[0];
				} else {
					$email = $raw_email;
				}

				// Dynamic header map resolution with positional fallback
				$header_map = WC_PIBG_CSV_Parser::normalize_headers( $headers );

				$first_name = isset( $header_map['first_name'] ) && isset( $data[ $header_map['first_name'] ] ) ? trim( (string) $data[ $header_map['first_name'] ] ) : ( isset( $data[7] ) ? trim( (string) $data[7] ) : '' );
				$last_name  = isset( $header_map['last_name'] ) && isset( $data[ $header_map['last_name'] ] ) ? trim( (string) $data[ $header_map['last_name'] ] ) : ( isset( $data[8] ) ? trim( (string) $data[8] ) : '' );

				if ( empty( $first_name ) && empty( $last_name ) ) {
					$first_name = 'Customer ' . $row_id;
				}
				$full_name = trim( $first_name . ' ' . $last_name );

				// Date of birth and Phone
				$dob   = isset( $header_map['dob'] ) && isset( $data[ $header_map['dob'] ] ) ? trim( (string) $data[ $header_map['dob'] ] ) : ( isset( $data[9] ) ? trim( (string) $data[9] ) : '' );
				$phone = isset( $header_map['phone'] ) && isset( $data[ $header_map['phone'] ] ) ? trim( (string) $data[ $header_map['phone'] ] ) : ( isset( $data[10] ) ? trim( (string) $data[10] ) : '' );

				// Auto-disambiguation if phone contains a date format (e.g. 6/26/1993)
				if ( preg_match( '#^\d{1,2}[/-]\d{1,2}[/-]\d{2,4}$#', $phone ) && ! preg_match( '#^\d{1,2}[/-]\d{1,2}[/-]\d{2,4}$#', $dob ) ) {
					$temp  = $phone;
					$phone = $dob;
					$dob   = $temp;
				}

				// Address fields
				$address     = isset( $header_map['address'] ) && isset( $data[ $header_map['address'] ] ) ? trim( (string) $data[ $header_map['address'] ] ) : ( isset( $data[11] ) ? trim( (string) $data[11] ) : '' );
				$city        = isset( $header_map['city'] ) && isset( $data[ $header_map['city'] ] ) ? trim( (string) $data[ $header_map['city'] ] ) : ( isset( $data[12] ) ? trim( (string) $data[12] ) : '' );
				$country     = isset( $header_map['country'] ) && isset( $data[ $header_map['country'] ] ) ? trim( (string) $data[ $header_map['country'] ] ) : ( isset( $data[13] ) ? trim( (string) $data[13] ) : '' );
				$postal_code = isset( $header_map['postal_code'] ) && isset( $data[ $header_map['postal_code'] ] ) ? trim( (string) $data[ $header_map['postal_code'] ] ) : ( isset( $data[14] ) ? trim( (string) $data[14] ) : '' );

				$records[] = array(
					'row_id'         => $row_id,
					'transaction_id' => $trans_id,
					'created_at'     => $created,
					'state'          => $state,
					'first_name'     => $first_name,
					'last_name'      => $last_name,
					'full_name'      => $full_name,
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
					'raw_row'        => $data,
				);

				$row_id++;
			}
		} else {
			// Dynamic header mapping fallback
			$header_map = WC_PIBG_CSV_Parser::normalize_headers( $headers );
			if ( empty( $header_map ) || ! isset( $header_map['amount'] ) ) {
				$header_map = WC_PIBG_CSV_Parser::heuristic_column_mapping( $data_rows );
			}

			foreach ( $data_rows as $row ) {
				if ( empty( array_filter( $row, 'strlen' ) ) ) {
					continue;
				}
				$record = WC_PIBG_CSV_Parser::map_row_to_record( $row, $header_map, $row_id );
				if ( $record ) {
					$records[] = $record;
					$row_id++;
				}
			}
		}

		if ( empty( $records ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Не удалось извлечь записи из файла Excel.', 'wc-pdf-invoice-generator' ),
			);
		}

		return array(
			'success' => true,
			'data'    => $records,
		);
	}
}
