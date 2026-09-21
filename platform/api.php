<?php
/**
 * REST API Backend for Universal Catalog Matcher & Invoice Platform.
 */

header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
header( 'Access-Control-Allow-Headers: Content-Type' );

if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
	exit;
}

require_once __DIR__ . '/classes/class-auth.php';
require_once __DIR__ . '/classes/class-crawler.php';
require_once __DIR__ . '/classes/class-matcher.php';
require_once __DIR__ . '/classes/class-pdf-builder.php';

$action = isset( $_GET['action'] ) ? sanitize_text( $_GET['action'] ) : '';

function sanitize_text( $val ) {
	return htmlspecialchars( trim( (string) $val ), ENT_QUOTES, 'UTF-8' );
}

function send_json( $data, $status = 200 ) {
	http_response_code( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	exit;
}

// 0. Action: Auth Login
if ( $action === 'login' ) {
	$input = json_decode( file_get_contents( 'php://input' ), true );
	if ( empty( $input ) ) {
		$input = $_POST;
	}

	$username = isset( $input['username'] ) ? $input['username'] : '';
	$password = isset( $input['password'] ) ? $input['password'] : '';

	if ( SoftProjects_Auth::attempt_login( $username, $password ) ) {
		send_json( array(
			'success'  => true,
			'message'  => 'Успешная авторизация',
			'username' => SoftProjects_Auth::USERNAME,
		) );
	} else {
		send_json( array(
			'success' => false,
			'error'   => 'Неверный логин или пароль.',
		), 401 );
	}
}

// 0. Action: Auth Logout
if ( $action === 'logout' ) {
	SoftProjects_Auth::logout();
	if ( isset( $_GET['redirect'] ) ) {
		header( 'Location: index.php' );
		exit;
	}
	send_json( array( 'success' => true, 'message' => 'Сессия завершена' ) );
}

// Check authentication for all protected actions
if ( ! SoftProjects_Auth::is_authenticated() ) {
	send_json( array(
		'success'      => false,
		'error'        => 'Требуется авторизация в системе SoftProjects.',
		'unauthorized' => true,
	), 401 );
}

// 1. Action: Get/Fetch Catalog
if ( $action === 'get_catalog' ) {
	$url           = isset( $_POST['url'] ) ? trim( $_POST['url'] ) : ( isset( $_GET['url'] ) ? trim( $_GET['url'] ) : '' );
	$force_refresh = ! empty( $_POST['force_refresh'] ) || ! empty( $_GET['force_refresh'] );

	if ( empty( $url ) ) {
		send_json( array( 'success' => false, 'error' => 'URL не указан.' ), 400 );
	}

	$catalog_data = Universal_Catalog_Crawler::get_catalog( $url, isset( $_POST['currency'] ) ? $_POST['currency'] : 'EUR', $force_refresh );
	send_json( $catalog_data );
}

// 2. Action: Match Products for Amount
if ( $action === 'match' ) {
	$input = json_decode( file_get_contents( 'php://input' ), true );
	if ( empty( $input ) ) {
		$input = $_POST;
	}

	$url           = ! empty( $input['url'] ) ? Universal_Catalog_Crawler::normalize_url( $input['url'] ) : '';
	$target_amount = ! empty( $input['amount'] ) ? (float) str_replace( array( ',', ' ' ), array( '.', '' ), $input['amount'] ) : 0.0;
	$currency      = ! empty( $input['currency'] ) ? strtoupper( trim( $input['currency'] ) ) : 'EUR';
	$force_refresh = ! empty( $input['force_refresh'] );
	$customer_data = ! empty( $input['customer'] ) ? $input['customer'] : array();

	if ( empty( $url ) || ! Universal_Catalog_Crawler::is_valid_url( $url ) ) {
		send_json( array( 'success' => false, 'error' => 'Укажите корректный URL магазина.' ), 400 );
	}

	if ( $target_amount <= 0 ) {
		send_json( array( 'success' => false, 'error' => 'Укажите корректную сумму заказа больше 0.' ), 400 );
	}

	// 1. Get or scrape catalog in selected currency
	$catalog_result = Universal_Catalog_Crawler::get_catalog( $url, $currency, $force_refresh );
	if ( ! $catalog_result['success'] || empty( $catalog_result['products'] ) ) {
		send_json( array(
			'success' => false,
			'error'   => ! empty( $catalog_result['error'] ) ? $catalog_result['error'] : 'Не удалось найти товары по указанному URL.',
		), 400 );
	}

	// Extract store brand name from domain
	$parsed_host = parse_url( $url, PHP_URL_HOST );
	$store_brand = preg_replace( '/^www\./i', '', $parsed_host );
	$store_name  = strtoupper( explode( '.', $store_brand )[0] );
	if ( empty( $store_name ) ) {
		$store_name = 'DREZZA';
	}

	// 2. Match products with custom shipping options
	$custom_options = array();
	if ( isset( $input['shipping_mode'] ) && $input['shipping_mode'] === 'manual' ) {
		$custom_options['shipping_cost'] = isset( $input['shipping_cost'] ) ? (float) str_replace( array( ',', ' ' ), array( '.', '' ), $input['shipping_cost'] ) : 0.0;
		if ( ! empty( $input['shipping_title'] ) ) {
			$custom_options['shipping_title'] = trim( $input['shipping_title'] );
		}
	} elseif ( isset( $input['shipping_cost'] ) && is_numeric( $input['shipping_cost'] ) ) {
		$custom_options['shipping_cost'] = (float) $input['shipping_cost'];
		if ( ! empty( $input['shipping_title'] ) ) {
			$custom_options['shipping_title'] = trim( $input['shipping_title'] );
		}
	}

	$match_result = Universal_Catalog_Matcher::match_amount( $catalog_result['products'], $target_amount, $currency, $custom_options );
	if ( ! $match_result['success'] ) {
		send_json( $match_result, 400 );
	}

	// 3. Generate formatted outputs
	$text_receipt = Universal_Catalog_Matcher::format_text_receipt( $match_result, $customer_data, $store_name );
	$compact_list = Universal_Catalog_Matcher::format_compact_list( $match_result, $currency );
	$tsv_data     = Universal_Catalog_Matcher::format_tsv( $match_result, $currency );

	send_json( array(
		'success'        => true,
		'store_name'     => $store_name,
		'url'            => $url,
		'catalog_source' => $catalog_result['source'],
		'catalog_count'  => count( $catalog_result['products'] ),
		'is_cached'      => ! empty( $catalog_result['cached'] ),
		'match'          => $match_result,
		'text_receipt'   => $text_receipt,
		'compact_list'   => $compact_list,
		'tsv_data'       => $tsv_data,
		'customer'       => $customer_data,
	) );
}

// 2.1 Action: Recalculate Modified Items & Formats
if ( $action === 'recalculate' ) {
	$input = json_decode( file_get_contents( 'php://input' ), true );
	if ( empty( $input ) ) {
		$input = $_POST;
	}

	$items         = isset( $input['items'] ) && is_array( $input['items'] ) ? $input['items'] : array();
	$currency      = ! empty( $input['currency'] ) ? strtoupper( trim( $input['currency'] ) ) : 'EUR';
	$customer_data = ! empty( $input['customer'] ) ? $input['customer'] : array();
	$store_name    = ! empty( $input['store_name'] ) ? trim( $input['store_name'] ) : 'STORE';
	$shipping_info = isset( $input['shipping'] ) ? $input['shipping'] : array();

	$recalc_result = Universal_Catalog_Matcher::recalculate_from_items( $items, $currency, $shipping_info );
	$text_receipt  = Universal_Catalog_Matcher::format_text_receipt( $recalc_result, $customer_data, $store_name );
	$compact_list  = Universal_Catalog_Matcher::format_compact_list( $recalc_result, $currency );
	$tsv_data      = Universal_Catalog_Matcher::format_tsv( $recalc_result, $currency );

	send_json( array(
		'success'      => true,
		'store_name'   => $store_name,
		'match'        => $recalc_result,
		'text_receipt' => $text_receipt,
		'compact_list' => $compact_list,
		'tsv_data'     => $tsv_data,
		'customer'     => $customer_data,
	) );
}

// 2.2 Action: Preview HTML Invoice (for Live Preview modal and printing)
if ( $action === 'preview_html' ) {
	$payload_raw = isset( $_POST['payload'] ) ? $_POST['payload'] : ( isset( $_GET['payload'] ) ? $_GET['payload'] : '' );
	if ( empty( $payload_raw ) ) {
		die( 'Missing invoice payload.' );
	}

	$data = json_decode( $payload_raw, true );
	if ( empty( $data ) || empty( $data['match'] ) ) {
		die( 'Invalid payload.' );
	}

	$match_result  = $data['match'];
	$customer_data = ! empty( $data['customer'] ) ? $data['customer'] : array();
	$store_name    = ! empty( $data['store_name'] ) ? $data['store_name'] : 'DREZZA';

	$html = Universal_PDF_Builder::render_html( $match_result, $customer_data, $store_name );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo $html;
	exit;
}

// 3. Action: Download PDF Invoice
if ( $action === 'download_pdf' ) {
	$payload_raw = isset( $_POST['payload'] ) ? $_POST['payload'] : ( isset( $_GET['payload'] ) ? $_GET['payload'] : '' );
	if ( empty( $payload_raw ) ) {
		die( 'Missing invoice payload.' );
	}

	$data = json_decode( $payload_raw, true );
	if ( empty( $data ) || empty( $data['match'] ) ) {
		die( 'Invalid payload.' );
	}

	$match_result  = $data['match'];
	$customer_data = ! empty( $data['customer'] ) ? $data['customer'] : array();
	$store_name    = ! empty( $data['store_name'] ) ? $data['store_name'] : 'DREZZA';

	$order_id = ! empty( $customer_data['order_id'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', $customer_data['order_id'] ) : 'DRZ-37708';
	$filename = sprintf( 'Invoice_%s_%s.pdf', $store_name, $order_id );

	$temp_dir = __DIR__ . '/storage/temp/';
	if ( ! file_exists( $temp_dir ) ) {
		@mkdir( $temp_dir, 0755, true );
	}

	$pdf_path = $temp_dir . uniqid( 'inv_', true ) . '.pdf';
	$pdf_res  = Universal_PDF_Builder::generate_pdf( $match_result, $customer_data, $pdf_path, $store_name );

	if ( true !== $pdf_res ) {
		die( 'PDF Generation error: ' . $pdf_res );
	}

	// Stream PDF
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}

	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . filesize( $pdf_path ) );
	header( 'Cache-Control: private, max-age=0, must-revalidate' );
	header( 'Pragma: public' );

	readfile( $pdf_path );
	@unlink( $pdf_path );
	exit;
}

// 4. Action: List Saved Catalogs
if ( $action === 'list_shops' ) {
	$catalogs_dir = __DIR__ . '/storage/catalogs/';
	$shops        = array();

	if ( file_exists( $catalogs_dir ) ) {
		$files = glob( $catalogs_dir . '*.json' );
		foreach ( $files as $f ) {
			$json = @json_decode( file_get_contents( $f ), true );
			if ( ! empty( $json ) && ! empty( $json['url'] ) ) {
				$parsed = parse_url( $json['url'], PHP_URL_HOST );
				$shops[] = array(
					'url'        => $json['url'],
					'host'       => $parsed,
					'name'       => strtoupper( explode( '.', preg_replace( '/^www\./i', '', $parsed ) )[0] ),
					'count'      => ! empty( $json['products'] ) ? count( $json['products'] ) : 0,
					'currency'   => ! empty( $json['currency'] ) ? $json['currency'] : 'EUR',
					'source'     => ! empty( $json['source'] ) ? $json['source'] : 'Unknown',
					'scraped_at' => ! empty( $json['scraped_at'] ) ? $json['scraped_at'] : '',
				);
			}
		}
	}

	send_json( array( 'success' => true, 'shops' => $shops ) );
}

send_json( array( 'error' => 'Неизвестное действие.' ), 404 );
