<?php
/**
 * Standalone Test Script for WooCommerce PDF Invoice Batch Generator.
 * Run via: php tests/test-generator.php
 */

define( 'WC_PIBG_TEST_RUN', true );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
define( 'WC_PIBG_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

// Mock minimal WordPress functions if running standalone outside WP
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) { return htmlspecialchars( (string)$text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) { return htmlspecialchars( (string)$text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $text ) { return $text; }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = mb_strtolower( trim( $title ), 'UTF-8' );
		$title = preg_replace( '/[^a-z0-9_-]+/u', '-', $title );
		return trim( $title, '-' );
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $str ) { return rtrim( $str, '/\\' ) . '/'; }
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format ) { return date( 'd.m.Y' ); }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $opt, $default = '' ) { return $default; }
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) {
			$this->code = $code;
			$this->message = $message;
		}
		public function get_error_message() { return $this->message; }
	}
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}

require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-csv-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-numbers-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-xlsx-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-subset-sum.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-pdf-generator.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-zip-handler.php';

echo "=== 1. Testing CSV Parsing ===\n";

// Sample 1: Semicolon delimiter with comma decimals and UTF-8 BOM
$bom = pack( 'H*', 'EFBBBF' );
$sample_csv_1 = $bom . "First Name;Amount;Currency;Customer Account Number;Customer Email;Billing Address;City;Country;Postal Code\r\n" .
	"David Jurado;100,50;USD;ACC-98412;david.jurado@example.com;123 Main St;Madrid;Spain;28001\r\n" .
	"Anna Ivanova;45,00;EUR;ACC-54120;anna@example.com;Nevsky Prospekt 10;Saint Petersburg;Russia;191186\r\n" .
	"John Doe;250.75;USD;ACC-11002;john.doe@example.com;456 Elm St;New York;USA;10001\r\n";

$temp_csv_file = tempnam( sys_get_temp_dir(), 'test_csv_' ) . '.csv';
file_put_contents( $temp_csv_file, $sample_csv_1 );

$parsed_result = WC_PIBG_CSV_Parser::parse( $temp_csv_file );
unlink( $temp_csv_file );

if ( ! $parsed_result['success'] ) {
	die( "CSV Parser Failed: " . $parsed_result['error'] . "\n" );
}

echo "Parsed " . count( $parsed_result['data'] ) . " rows successfully.\n";
foreach ( $parsed_result['data'] as $row ) {
	echo sprintf(
		" -> Row %d: %s | Amount: %.2f (%d cents) | %s | Email: %s\n",
		$row['row_id'],
		$row['full_name'],
		$row['amount'],
		$row['amount_cents'],
		$row['currency'],
		$row['email']
	);
}

// Assertions for CSV Parser
assert( count( $parsed_result['data'] ) === 3, 'Expected 3 parsed rows' );
assert( $parsed_result['data'][0]['amount_cents'] === 10050, 'Row 1 should be 10050 cents (100,50)' );
assert( $parsed_result['data'][1]['amount_cents'] === 4500, 'Row 2 should be 4500 cents (45,00)' );
assert( $parsed_result['data'][2]['amount_cents'] === 25075, 'Row 3 should be 25075 cents (250.75)' );
echo "✓ CSV Parser validation passed.\n\n";

echo "=== 1b. Testing Apple Numbers (.numbers) Parser ===\n";

// Create a synthetic Apple Numbers package with index.xml
$temp_numbers_file = tempnam( sys_get_temp_dir(), 'test_doc_' ) . '.numbers';
$zip = new ZipArchive();
if ( $zip->open( $temp_numbers_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
	$xml_content = '<?xml version="1.0" encoding="UTF-8"?>
	<document>
		<grid-row>
			<cell>First Name</cell>
			<cell>Amount</cell>
			<cell>Currency</cell>
			<cell>Customer Account Number</cell>
			<cell>Customer Email</cell>
			<cell>Billing Address</cell>
			<cell>City</cell>
			<cell>Country</cell>
			<cell>Postal Code</cell>
		</grid-row>
		<grid-row>
			<cell>Mac User</cell>
			<cell>100,50</cell>
			<cell>USD</cell>
			<cell>ACC-MAC01</cell>
			<cell>mac.user@apple.com</cell>
			<cell>1 Infinite Loop</cell>
			<cell>Cupertino</cell>
			<cell>USA</cell>
			<cell>95014</cell>
		</grid-row>
	</document>';
	$zip->addFromString( 'index.xml', $xml_content );
	$zip->close();
}

$numbers_result = WC_PIBG_Numbers_Parser::parse( $temp_numbers_file );
unlink( $temp_numbers_file );

if ( ! $numbers_result['success'] ) {
	die( "Numbers Parser Failed: " . $numbers_result['error'] . "\n" );
}

echo "Parsed " . count( $numbers_result['data'] ) . " rows from .numbers file.\n";
echo " -> " . $numbers_result['data'][0]['full_name'] . " | " . $numbers_result['data'][0]['amount'] . " " . $numbers_result['data'][0]['currency'] . " (" . $numbers_result['data'][0]['amount_cents'] . " cents)\n";
assert( $numbers_result['data'][0]['amount_cents'] === 10050, 'Numbers parsed amount should be 10050 cents' );
assert( $numbers_result['data'][0]['first_name'] === 'Mac User', 'First name should be Mac User' );
echo "✓ Apple Numbers parser validation passed!\n\n";

echo "=== 1c. Testing Excel (.xlsx) Parser ===\n";

// Create a synthetic XLSX zip file
$temp_xlsx_file = tempnam( sys_get_temp_dir(), 'test_sheet_' ) . '.xlsx';
$zip = new ZipArchive();
if ( $zip->open( $temp_xlsx_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
	// Shared strings: 0 => First Name, 1 => Amount, 2 => Currency, 3 => Customer Email, 4 => Excel User, 5 => USD, 6 => excel.user@microsoft.com
	$shared_strings_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="7" uniqueCount="7">
		<si><t>First Name</t></si>
		<si><t>Amount</t></si>
		<si><t>Currency</t></si>
		<si><t>Customer Email</t></si>
		<si><t>Excel User</t></si>
		<si><t>USD</t></si>
		<si><t>excel.user@microsoft.com</t></si>
	</sst>';

	// Worksheet 1
	$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
		<sheetData>
			<row r="1">
				<c r="A1" t="s"><v>0</v></c>
				<c r="B1" t="s"><v>1</v></c>
				<c r="C1" t="s"><v>2</v></c>
				<c r="D1" t="s"><v>3</v></c>
			</row>
			<row r="2">
				<c r="A2" t="s"><v>4</v></c>
				<c r="B2"><v>100.50</v></c>
				<c r="C2" t="s"><v>5</v></c>
				<c r="D2" t="s"><v>6</v></c>
			</row>
		</sheetData>
	</worksheet>';

	$zip->addFromString( 'xl/sharedStrings.xml', $shared_strings_xml );
	$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
	$zip->close();
}

$xlsx_result = WC_PIBG_XLSX_Parser::parse( $temp_xlsx_file );
unlink( $temp_xlsx_file );

if ( ! $xlsx_result['success'] ) {
	die( "XLSX Parser Failed: " . $xlsx_result['error'] . "\n" );
}

echo "Parsed " . count( $xlsx_result['data'] ) . " rows from .xlsx file.\n";
echo " -> " . $xlsx_result['data'][0]['full_name'] . " | " . $xlsx_result['data'][0]['amount'] . " " . $xlsx_result['data'][0]['currency'] . " (" . $xlsx_result['data'][0]['amount_cents'] . " cents)\n";
assert( $xlsx_result['data'][0]['amount_cents'] === 10050, 'XLSX parsed amount should be 10050 cents' );
assert( $xlsx_result['data'][0]['first_name'] === 'Excel User', 'First name should be Excel User' );
assert( $xlsx_result['data'][0]['email'] === 'excel.user@microsoft.com', 'Email should match' );
echo "✓ Excel .xlsx parser validation passed!\n\n";

echo "=== 2. Testing Subset Sum DP Algorithm ===\n";

// Set a mock WooCommerce catalog
$mock_catalog = array(
	array( 'id' => 101, 'name' => 'Premium WordPress Theme', 'sku' => 'WP-TH-01', 'price' => 59.00, 'price_cents' => 5900 ),
	array( 'id' => 102, 'name' => 'WooCommerce Extension Pro', 'sku' => 'WC-EXT-PRO', 'price' => 29.50, 'price_cents' => 2950 ),
	array( 'id' => 103, 'name' => 'SEO Optimization Addon', 'sku' => 'SEO-ADD-01', 'price' => 12.00, 'price_cents' => 1200 ),
	array( 'id' => 104, 'name' => 'Monthly Support Pass', 'sku' => 'SUP-MONTH', 'price' => 10.00, 'price_cents' => 1000 ),
	array( 'id' => 105, 'name' => 'Micro Service Unit', 'sku' => 'SRV-050', 'price' => 0.50, 'price_cents' => 50 ),
);

WC_PIBG_Subset_Sum::set_products( $mock_catalog );

// Test exact target: $100.50 (10050 cents)
// Combination: 5900 (Theme) + 2950 (Extension) + 1200 (SEO) = 10050! Exact!
$target_1 = 10050;
$match_1 = WC_PIBG_Subset_Sum::match_products( $target_1 );

echo "Matching target $100.50 (10050 cents):\n";
$sum_1 = 0;
foreach ( $match_1['items'] as $item ) {
	$sum_1 += $item['total_cents'];
	echo sprintf( "   * [%dx] %s (SKU: %s) @ $%.2f = $%.2f\n", $item['qty'], $item['name'], $item['sku'], $item['unit_price'], $item['total'] );
}
echo "   Subtotal: " . ( $sum_1 / 100 ) . " ($sum_1 cents), Shipping: " . $match_1['shipping']['cost'] . ", Total: " . $match_1['total'] . "\n";
assert( $match_1['total_cents'] === 10050, 'Total should match exactly 10050 cents' );
echo "✓ Exact DP match passed!\n\n";

// Test target $45.00 (4500 cents)
// Combination: 1000 * 3 + 1200 + ... or 2950 + 1000 + 50*11
$target_2 = 4500;
$match_2 = WC_PIBG_Subset_Sum::match_products( $target_2 );
echo "Matching target $45.00 (4500 cents):\n";
$sum_2 = 0;
foreach ( $match_2['items'] as $item ) {
	$sum_2 += $item['total_cents'];
	echo sprintf( "   * [%dx] %s (SKU: %s) @ $%.2f = $%.2f\n", $item['qty'], $item['name'], $item['sku'], $item['unit_price'], $item['total'] );
}
echo "   Subtotal: " . ( $sum_2 / 100 ) . " ($sum_2 cents), Shipping: " . $match_2['shipping']['cost'] . ", Total: " . $match_2['total'] . "\n";
assert( $match_2['total_cents'] === 4500, 'Total should match exactly 4500 cents' );
echo "✓ DP match passed!\n\n";

echo "=== 3. Testing PDF Generation via wkhtmltopdf ===\n";
$test_record = $parsed_result['data'][0]; // David Jurado, $100.50
$test_pdf_path = __DIR__ . '/test_invoice_david_jurado.pdf';

$pdf_res = WC_PIBG_PDF_Generator::generate_pdf( $test_record, $match_1, $test_pdf_path );
if ( is_wp_error( $pdf_res ) ) {
	die( "PDF Generation Failed: " . $pdf_res->get_error_message() . "\n" );
}

echo "PDF generated successfully at: " . $test_pdf_path . " (Size: " . filesize( $test_pdf_path ) . " bytes)\n";
assert( file_exists( $test_pdf_path ) && filesize( $test_pdf_path ) > 0, 'PDF file should exist and not be empty' );
unlink( $test_pdf_path );
echo "✓ PDF generation verified!\n\n";

echo "=== 4. Testing ZIP Batch Archiving ===\n";
$zip_result = WC_PIBG_Zip_Handler::build_zip( $parsed_result['data'] );
if ( is_wp_error( $zip_result ) ) {
	die( "ZIP Archiving Failed: " . $zip_result->get_error_message() . "\n" );
}

echo "ZIP Archive created at: " . $zip_result . " (Size: " . filesize( $zip_result ) . " bytes)\n";

$zip_check = new ZipArchive();
if ( $zip_check->open( $zip_result ) === true ) {
	echo "ZIP contains " . $zip_check->numFiles . " files:\n";
	for ( $i = 0; $i < $zip_check->numFiles; $i++ ) {
		$stat = $zip_check->statIndex( $i );
		echo "   - " . $stat['name'] . " (" . $stat['size'] . " bytes)\n";
	}
	$zip_check->close();
}

assert( file_exists( $zip_result ) && filesize( $zip_result ) > 0, 'ZIP file should exist and not be empty' );
echo "✓ ZIP archiving verified!\n\n";

echo "========================================\n";
echo " ALL TESTS PASSED SUCCESSFULLY! (100%)\n";
echo "========================================\n";
