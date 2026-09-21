<?php
/**
 * Standalone PDF Builder using wkhtmltopdf for Universal Catalog Matcher.
 */

class Universal_PDF_Builder {

	/**
	 * Find wkhtmltopdf binary on host system.
	 *
	 * @return string|false
	 */
	public static function get_binary_path() {
		$common_paths = array(
			'/usr/local/bin/wkhtmltopdf',
			'/usr/bin/wkhtmltopdf',
			'/opt/homebrew/bin/wkhtmltopdf',
			'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
		);

		foreach ( $common_paths as $path ) {
			if ( file_exists( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}

		if ( function_exists( 'exec' ) ) {
			$which = trim( @shell_exec( 'which wkhtmltopdf 2>/dev/null' ) );
			if ( ! empty( $which ) && file_exists( $which ) && is_executable( $which ) ) {
				return $which;
			}
		}

		return false;
	}

	/**
	 * Render HTML string for invoice.
	 *
	 * @param array $match_result
	 * @param array $customer_data
	 * @param string $store_name
	 * @return string
	 */
	public static function render_html( $match_result, $customer_data, $store_name = 'DREZZA' ) {
		$record = array(
			'full_name'      => ! empty( $customer_data['name'] ) ? $customer_data['name'] : 'Customer',
			'first_name'     => ! empty( $customer_data['name'] ) ? explode( ' ', $customer_data['name'] )[0] : 'Customer',
			'email'          => ! empty( $customer_data['email'] ) ? $customer_data['email'] : '',
			'phone'          => ! empty( $customer_data['phone'] ) ? $customer_data['phone'] : '',
			'account_number' => ! empty( $customer_data['card_pan'] ) ? $customer_data['card_pan'] : '',
			'address'        => ! empty( $customer_data['address'] ) ? $customer_data['address'] : '',
			'city'           => ! empty( $customer_data['city'] ) ? $customer_data['city'] : '',
			'country'        => ! empty( $customer_data['country'] ) ? $customer_data['country'] : '',
			'postal_code'    => ! empty( $customer_data['postal_code'] ) ? $customer_data['postal_code'] : '',
			'amount'         => (float) $match_result['total'],
			'currency'       => ! empty( $match_result['currency'] ) ? $match_result['currency'] : 'EUR',
		);

		$items          = $match_result['items'];
		$shipping       = $match_result['shipping'];
		$subtotal       = $match_result['subtotal'];
		$invoice_number = ! empty( $customer_data['order_id'] ) ? $customer_data['order_id'] : 'DRZ-37708';
		$invoice_date   = ! empty( $customer_data['date'] ) ? $customer_data['date'] : date( 'd.m.Y' );
		$currency_symbol = ( $record['currency'] === 'GBP' ) ? '£' : ( ( $record['currency'] === 'USD' ) ? '$' : '€' );

		$template_file = __DIR__ . '/../templates/invoice-email-style.php';
		ob_start();
		include $template_file;
		return ob_get_clean();
	}

	/**
	 * Generate PDF file on disk.
	 *
	 * @param array  $match_result
	 * @param array  $customer_data
	 * @param string $output_pdf_path
	 * @param string $store_name
	 * @return bool|string True on success, or error string
	 */
	public static function generate_pdf( $match_result, $customer_data, $output_pdf_path, $store_name = 'DREZZA' ) {
		$binary = self::get_binary_path();
		if ( ! $binary ) {
			return 'Утилита wkhtmltopdf не найдена на сервере.';
		}

		$html = self::render_html( $match_result, $customer_data, $store_name );

		$temp_html = tempnam( sys_get_temp_dir(), 'inv_html_' ) . '.html';
		file_put_contents( $temp_html, $html );

		$cmd = sprintf(
			'%s --encoding utf-8 --page-size A4 --margin-top 8mm --margin-bottom 8mm --margin-left 8mm --margin-right 8mm --enable-local-file-access --quiet %s %s 2>&1',
			escapeshellcmd( $binary ),
			escapeshellarg( $temp_html ),
			escapeshellarg( $output_pdf_path )
		);

		$output   = array();
		$ret_code = 0;
		@exec( $cmd, $output, $ret_code );

		@unlink( $temp_html );

		if ( ! file_exists( $output_pdf_path ) || filesize( $output_pdf_path ) === 0 ) {
			return ! empty( $output ) ? implode( "\n", $output ) : 'Не удалось сформировать PDF документ.';
		}

		return true;
	}
}
