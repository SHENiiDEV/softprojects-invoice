<?php
/**
 * PDF Generator class using wkhtmltopdf for pixel-perfect WooCommerce email style invoices.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WC_PIBG_TEST_RUN' ) ) {
	exit;
}

class WC_PIBG_PDF_Generator {

	/**
	 * Path to wkhtmltopdf binary.
	 *
	 * @var string|null
	 */
	private static $binary_path = null;

	/**
	 * Locate the wkhtmltopdf binary on the system.
	 *
	 * @return string|false
	 */
	public static function get_binary_path() {
		if ( null !== self::$binary_path ) {
			return self::$binary_path;
		}

		$common_paths = array(
			'/usr/local/bin/wkhtmltopdf',
			'/usr/bin/wkhtmltopdf',
			'/opt/homebrew/bin/wkhtmltopdf',
			'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
		);

		foreach ( $common_paths as $path ) {
			if ( file_exists( $path ) && is_executable( $path ) ) {
				self::$binary_path = $path;
				return self::$binary_path;
			}
		}

		// Try 'which' on Unix / Mac
		if ( function_exists( 'exec' ) ) {
			$which = trim( @shell_exec( 'which wkhtmltopdf 2>/dev/null' ) );
			if ( ! empty( $which ) && file_exists( $which ) && is_executable( $which ) ) {
				self::$binary_path = $which;
				return self::$binary_path;
			}
		}

		return false;
	}

	/**
	 * Set custom binary path (e.g. from plugin settings).
	 *
	 * @param string $path
	 */
	public static function set_binary_path( $path ) {
		self::$binary_path = $path;
	}

	/**
	 * Generate or get a realistic WooCommerce-aligned order number (e.g. DRZ-37708).
	 *
	 * @param array $record
	 * @return string
	 */
	public static function generate_order_number( $record ) {
		static $base_order_id = null;

		if ( null === $base_order_id ) {
			if ( function_exists( 'wc_get_orders' ) ) {
				$orders = wc_get_orders( array(
					'limit'   => 1,
					'orderby' => 'ID',
					'order'   => 'DESC',
					'return'  => 'ids',
				) );
				if ( ! empty( $orders ) ) {
					$base_order_id = (int) reset( $orders );
				}
			}

			// Fallback if no existing orders in DB: realistic 5-6 digit WooCommerce order base (e.g. 37700)
			if ( empty( $base_order_id ) ) {
				$base_order_id = 37700;
			}
		}

		$row_id    = isset( $record['row_id'] ) ? (int) $record['row_id'] : 1;
		$order_num = $base_order_id + $row_id;

		return 'DRZ-' . $order_num;
	}

	/**
	 * Render HTML invoice string for a customer record.
	 *
	 * @param array $record     Customer record from CSV/XLSX
	 * @param array $match_data Matched products and shipping data from Subset Sum
	 * @return string
	 */
	public static function render_html( $record, $match_data ) {
		if ( ! empty( $record['order_id'] ) ) {
			$invoice_number = $record['order_id'];
		} elseif ( ! empty( $record['invoice_number'] ) ) {
			$invoice_number = $record['invoice_number'];
		} else {
			$invoice_number = self::generate_order_number( $record );
		}

		if ( ! empty( $record['created_at'] ) && strtotime( $record['created_at'] ) ) {
			$invoice_date = date_i18n( get_option( 'date_format', 'd.m.Y' ), strtotime( $record['created_at'] ) );
		} else {
			$invoice_date = date_i18n( get_option( 'date_format', 'd.m.Y' ) );
		}

		$store_name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'DREZZA';
		if ( empty( $store_name ) || 'WordPress' === $store_name ) {
			$store_name = 'DREZZA';
		}
		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $record['currency'] ) : '€';

		// Extract items, shipping, subtotal
		$items    = isset( $match_data['items'] ) ? $match_data['items'] : $match_data;
		$shipping = isset( $match_data['shipping'] ) ? $match_data['shipping'] : null;
		$subtotal = isset( $match_data['subtotal'] ) ? $match_data['subtotal'] : $record['amount'];

		$template_path = defined( 'WC_PIBG_PLUGIN_DIR' ) 
			? WC_PIBG_PLUGIN_DIR . 'templates/invoice-email-style.php'
			: dirname( __DIR__ ) . '/templates/invoice-email-style.php';

		ob_start();
		include $template_path;
		return ob_get_clean();
	}

	/**
	 * Generate PDF file for a customer record.
	 *
	 * @param array  $record
	 * @param array  $match_data
	 * @param string $output_pdf_path
	 * @return bool|WP_Error
	 */
	public static function generate_pdf( $record, $match_data, $output_pdf_path ) {
		$binary = self::get_binary_path();
		if ( ! $binary ) {
			return new WP_Error(
				'binary_not_found',
				__( 'Утилита wkhtmltopdf не найдена на сервере. Убедитесь, что она установлена и доступна для выполнения.', 'wc-pdf-invoice-generator' )
			);
		}

		$html = self::render_html( $record, $match_data );

		$temp_html_path = tempnam( sys_get_temp_dir(), 'wc_pibg_html_' ) . '.html';
		file_put_contents( $temp_html_path, $html );

		$cmd = sprintf(
			'%s --encoding utf-8 --page-size A4 --margin-top 8mm --margin-bottom 8mm --margin-left 8mm --margin-right 8mm --enable-local-file-access --quiet %s %s 2>&1',
			escapeshellcmd( $binary ),
			escapeshellarg( $temp_html_path ),
			escapeshellarg( $output_pdf_path )
		);

		$output   = array();
		$ret_code = 0;

		if ( function_exists( 'exec' ) ) {
			@exec( $cmd, $output, $ret_code );
		} else {
			@shell_exec( $cmd );
		}

		// Cleanup temp html
		if ( file_exists( $temp_html_path ) ) {
			@unlink( $temp_html_path );
		}

		if ( ! file_exists( $output_pdf_path ) || filesize( $output_pdf_path ) === 0 ) {
			$err_msg = ! empty( $output ) ? implode( "\n", $output ) : 'Неизвестная ошибка wkhtmltopdf.';
			return new WP_Error( 'pdf_generation_failed', $err_msg );
		}

		return true;
	}
}
