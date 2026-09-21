<?php
/**
 * ZIP Archive Handler for packaging and streaming generated PDF invoices.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WC_PIBG_TEST_RUN' ) ) {
	exit;
}

class WC_PIBG_Zip_Handler {

	/**
	 * Process records and generate a ZIP archive containing all PDFs.
	 *
	 * @param array $selected_records List of customer records from CSV
	 * @return string|WP_Error Path to created ZIP file
	 */
	public static function build_zip( $selected_records ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_missing', __( 'Расширение PHP ZipArchive не доступно.', 'wc-pdf-invoice-generator' ) );
		}

		$upload_dir = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array( 'basedir' => sys_get_temp_dir() );
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'wc-pibg-temp/' . uniqid( 'batch_', true );

		if ( ! file_exists( $temp_dir ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $temp_dir );
			} else {
				mkdir( $temp_dir, 0755, true );
			}
		}

		$zip_file_path = $temp_dir . '/invoices_' . date( 'Ymd_His' ) . '.zip';
		$zip           = new ZipArchive();

		if ( true !== $zip->open( $zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'zip_create_error', __( 'Не удалось создать ZIP архив.', 'wc-pdf-invoice-generator' ) );
		}

		$generated_pdf_files = array();

		foreach ( $selected_records as $record ) {
			// Find products for target amount in the specific currency and calculate shipping
			$target_cents = (int) $record['amount_cents'];
			$currency     = ! empty( $record['currency'] ) ? $record['currency'] : 'EUR';
			$match_data   = WC_PIBG_Subset_Sum::match_products( $target_cents, $currency );

			// Format file name: Invoice_{sanitized_name}_{row_id}.pdf
			$client_name = ! empty( $record['first_name'] ) ? $record['first_name'] : 'client';
			if ( function_exists( 'sanitize_title' ) ) {
				$slug = sanitize_title( $client_name );
			} else {
				$slug = strtolower( trim( preg_replace( '/[^A-Za-z0-9-]+/', '-', $client_name ) ) );
			}
			if ( empty( $slug ) ) {
				$slug = 'customer';
			}

			$pdf_filename = sprintf( 'Invoice_%s_%d.pdf', $slug, $record['row_id'] );
			$pdf_path     = $temp_dir . '/' . $pdf_filename;

			$gen_result = WC_PIBG_PDF_Generator::generate_pdf( $record, $match_data, $pdf_path );

			if ( is_wp_error( $gen_result ) ) {
				$zip->close();
				self::cleanup_dir( $temp_dir );
				return $gen_result;
			}

			if ( file_exists( $pdf_path ) ) {
				$zip->addFile( $pdf_path, $pdf_filename );
				$generated_pdf_files[] = $pdf_path;
			}
		}

		$zip->close();

		// Cleanup individual PDF files from disk, keeping only the ZIP
		foreach ( $generated_pdf_files as $file ) {
			if ( file_exists( $file ) ) {
				@unlink( $file );
			}
		}

		return $zip_file_path;
	}

	/**
	 * Stream ZIP file to client and exit.
	 *
	 * @param string $zip_path
	 * @param string $download_name
	 */
	public static function stream_and_exit( $zip_path, $download_name = '' ) {
		if ( ! file_exists( $zip_path ) ) {
			wp_die( esc_html__( 'Файл архива не найден.', 'wc-pdf-invoice-generator' ) );
		}

		if ( empty( $download_name ) ) {
			$download_name = 'Invoices_' . date( 'Y-m-d_H-i-s' ) . '.zip';
		}

		// Clear any prior output buffer
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Disable compression in PHP / Apache
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
		}
		@ini_set( 'zlib.output_compression', 'Off' );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $download_name ) . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $zip_path ) );

		readfile( $zip_path );

		// Cleanup ZIP and parent batch directory
		$parent_dir = dirname( $zip_path );
		@unlink( $zip_path );
		self::cleanup_dir( $parent_dir );

		exit;
	}

	/**
	 * Recursively delete a directory and its contents.
	 *
	 * @param string $dir
	 */
	public static function cleanup_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::cleanup_dir( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}
}
