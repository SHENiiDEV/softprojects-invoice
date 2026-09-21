<?php
/**
 * Test specifically reproducing the user's spreadsheet schema with 15 columns.
 * Run via: php tests/test-user-dataset.php
 */

define( 'WC_PIBG_TEST_RUN', true );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
define( 'WC_PIBG_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

// Mock minimal WordPress functions
if ( ! function_exists( '__' ) ) { function __( $t, $d = 'default' ) { return $t; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $t, $d = 'default' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_html_e' ) ) { function esc_html_e( $t, $d = 'default' ) { echo htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string)$t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $t ) { return htmlspecialchars( (string)$t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $t ) { return $t; } }
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $t ) {
		$t = mb_strtolower( trim( $t ), 'UTF-8' );
		$t = preg_replace( '/[^a-z0-9_-]+/u', '-', $t );
		return trim( $t, '-' );
	}
}
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; } }
if ( ! function_exists( 'date_i18n' ) ) { function date_i18n( $f, $ts = null ) { return date( 'd.m.Y', $ts ?: time() ); } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $o, $d = '' ) { return $d; } }
if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) { function get_woocommerce_currency_symbol( $c ) { return $c === 'EUR' ? '€' : '$'; } }
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $c; private $m;
		public function __construct( $c, $m ) { $this->c = $c; $this->m = $m; }
		public function get_error_message() { return $this->m; }
	}
	function is_wp_error( $t ) { return $t instanceof WP_Error; }
}

require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-csv-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-numbers-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-xlsx-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-subset-sum.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-pdf-generator.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-zip-handler.php';

echo "=== Testing User's Exact 15-Column XLSX Format ===\n";

// 1. Build an XLSX file matching the user's exact schema
$temp_xlsx = tempnam( sys_get_temp_dir(), 'user_test_' ) . '.xlsx';
$zip = new ZipArchive();
if ( $zip->open( $temp_xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
	$strings = array(
		"Id", "Created", "State", "Amount", "Currency",
		"Customer Account Number", "Customer Email", "Customer First Name", "Customer Last Name",
		"Customer Date Of Birth", "Customer Phone", "Billing Address", "Billing City",
		"Billing Country", "Billing Postal Code",
		"a34bfd7eacfc42cea81f5ac762a805fd", "2026-06-29 22:26:51", "COMPLETED", "200", "EUR",
		"433467***4469", "info.imprentajurado@gmail.com", "David", "Jurado Giles",
		"8/24/1987", "34 626829761", "Carrer dels Espardenyers, 25, 7", "Valls",
		"Spain", "43800",
		"f8989e0386414c9d8fbe57166de99c7c", "2026-06-29 15:08:49", "300"
	);

	$sst_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $strings ) . '">';
	foreach ( $strings as $s ) {
		$sst_xml .= '<si><t>' . htmlspecialchars( $s ) . '</t></si>';
	}
	$sst_xml .= '</sst>';

	$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
		<sheetData>
			<!-- Row 1: Headers -->
			<row r="1">
				<c r="A1" t="s"><v>0</v></c>
				<c r="B1" t="s"><v>1</v></c>
				<c r="C1" t="s"><v>2</v></c>
				<c r="D1" t="s"><v>3</v></c>
				<c r="E1" t="s"><v>4</v></c>
				<c r="F1" t="s"><v>5</v></c>
				<c r="G1" t="s"><v>6</v></c>
				<c r="H1" t="s"><v>7</v></c>
				<c r="I1" t="s"><v>8</v></c>
				<c r="J1" t="s"><v>9</v></c>
				<c r="K1" t="s"><v>10</v></c>
				<c r="L1" t="s"><v>11</v></c>
				<c r="M1" t="s"><v>12</v></c>
				<c r="N1" t="s"><v>13</v></c>
				<c r="O1" t="s"><v>14</v></c>
			</row>
			<!-- Row 2: First Transaction (200 EUR) -->
			<row r="2">
				<c r="A2" t="s"><v>15</v></c>
				<c r="B2" t="s"><v>16</v></c>
				<c r="C2" t="s"><v>17</v></c>
				<c r="D2"><v>200</v></c>
				<c r="E2" t="s"><v>19</v></c>
				<c r="F2" t="s"><v>20</v></c>
				<c r="G2" t="s"><v>21</v></c>
				<c r="H2" t="s"><v>22</v></c>
				<c r="I2" t="s"><v>23</v></c>
				<c r="J2" t="s"><v>24</v></c>
				<c r="K2" t="s"><v>25</v></c>
				<c r="L2" t="s"><v>26</v></c>
				<c r="M2" t="s"><v>27</v></c>
				<c r="N2" t="s"><v>28</v></c>
				<c r="O2" t="s"><v>29</v></c>
			</row>
			<!-- Row 3: Second Transaction (300 EUR) -->
			<row r="3">
				<c r="A3" t="s"><v>30</v></c>
				<c r="B3" t="s"><v>31</v></c>
				<c r="C3" t="s"><v>17</v></c>
				<c r="D3"><v>300</v></c>
				<c r="E3" t="s"><v>19</v></c>
				<c r="F3" t="s"><v>20</v></c>
				<c r="G3" t="s"><v>21</v></c>
				<c r="H3" t="s"><v>22</v></c>
				<c r="I3" t="s"><v>23</v></c>
				<c r="J3" t="s"><v>24</v></c>
				<c r="K3" t="s"><v>25</v></c>
				<c r="L3" t="s"><v>26</v></c>
				<c r="M3" t="s"><v>27</v></c>
				<c r="N3" t="s"><v>28</v></c>
				<c r="O3" t="s"><v>29</v></c>
			</row>
		</sheetData>
	</worksheet>';

	$zip->addFromString( 'xl/sharedStrings.xml', $sst_xml );
	$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
	$zip->close();
}

$parsed = WC_PIBG_XLSX_Parser::parse( $temp_xlsx );
unlink( $temp_xlsx );

if ( ! $parsed['success'] ) {
	die( "Parsing Failed: " . $parsed['error'] . "\n" );
}

echo "Successfully parsed " . count( $parsed['data'] ) . " transactions:\n\n";

foreach ( $parsed['data'] as $idx => $row ) {
	echo "Transaction #" . ( $idx + 1 ) . ":\n";
	echo "  - Client: " . $row['full_name'] . " (First: " . $row['first_name'] . ", Last: " . $row['last_name'] . ")\n";
	echo "  - Amount: " . $row['amount'] . " " . $row['currency'] . " (" . $row['amount_cents'] . " cents)\n";
	echo "  - Account Number: " . $row['account_number'] . "\n";
	echo "  - Email: " . $row['email'] . "\n";
	echo "  - Phone: " . $row['phone'] . "\n";
	echo "  - Address: " . $row['address'] . ", " . $row['city'] . ", " . $row['postal_code'] . ", " . $row['country'] . "\n";
	echo "  - Date: " . $row['created_at'] . "\n";
	echo "  - State: " . $row['state'] . "\n\n";
}

assert( count( $parsed['data'] ) === 2, 'Must have 2 rows' );
assert( $parsed['data'][0]['full_name'] === 'David Jurado Giles', 'Full name should be David Jurado Giles' );
assert( $parsed['data'][0]['first_name'] === 'David', 'First name should be David' );
assert( $parsed['data'][0]['last_name'] === 'Jurado Giles', 'Last name should be Jurado Giles' );
assert( $parsed['data'][0]['amount_cents'] === 20000, 'Row 1 amount must be 20000 cents (200 EUR)' );
assert( $parsed['data'][1]['amount_cents'] === 30000, 'Row 2 amount must be 30000 cents (300 EUR)' );
assert( $parsed['data'][0]['currency'] === 'EUR', 'Currency must be EUR' );
assert( $parsed['data'][0]['email'] === 'info.imprentajurado@gmail.com', 'Email must match' );
assert( $parsed['data'][0]['account_number'] === '433467***4469', 'Account number must match' );

echo "=== Testing Product Matching for 200 EUR and 300 EUR ===\n";

// Mock catalog with realistic WooCommerce products
$mock_products = array(
	array( 'id' => 10, 'name' => 'Custom Print Job - 500 Copies', 'sku' => 'PRN-500', 'price' => 150.00, 'price_cents' => 15000 ),
	array( 'id' => 11, 'name' => 'Express Delivery Service', 'sku' => 'DLV-EXP', 'price' => 50.00, 'price_cents' => 5000 ),
	array( 'id' => 12, 'name' => 'Design & Prepress Package', 'sku' => 'DSG-PKG', 'price' => 100.00, 'price_cents' => 10000 ),
	array( 'id' => 13, 'name' => 'Lamination Gloss Finish', 'sku' => 'LAM-GLS', 'price' => 25.00, 'price_cents' => 2500 ),
);

WC_PIBG_Subset_Sum::set_products( $mock_products );

// Match Row 1 (200 EUR)
$match_1 = WC_PIBG_Subset_Sum::match_products( 20000, 'EUR' );
echo "Matched items for 200 EUR:\n";
$sum_1 = 0;
foreach ( $match_1['items'] as $item ) {
	$sum_1 += $item['total_cents'];
	echo sprintf( "   * [%dx] %s @ %.2f EUR = %.2f EUR\n", $item['qty'], $item['name'], $item['unit_price'], $item['total'] );
}
echo "   Subtotal: " . $match_1['subtotal'] . " EUR, Shipping: " . $match_1['shipping']['cost'] . " EUR, Total: " . $match_1['total'] . " EUR\n";
assert( $match_1['total_cents'] === 20000, 'Matched total must be exactly 20000 cents' );

// Match Row 2 (300 EUR)
$match_2 = WC_PIBG_Subset_Sum::match_products( 30000, 'EUR' );
echo "Matched items for 300 EUR:\n";
$sum_2 = 0;
foreach ( $match_2['items'] as $item ) {
	$sum_2 += $item['total_cents'];
	echo sprintf( "   * [%dx] %s @ %.2f EUR = %.2f EUR\n", $item['qty'], $item['name'], $item['unit_price'], $item['total'] );
}
echo "   Subtotal: " . $match_2['subtotal'] . " EUR, Shipping: " . $match_2['shipping']['cost'] . " EUR, Total: " . $match_2['total'] . " EUR\n";
assert( $match_2['total_cents'] === 30000, 'Matched total must be exactly 30000 cents' );

echo "\n=== Generating Real Invoices & ZIP Archive ===\n";
$zip_path = WC_PIBG_Zip_Handler::build_zip( $parsed['data'] );
if ( is_wp_error( $zip_path ) ) {
	die( "ZIP build failed: " . $zip_path->get_error_message() . "\n" );
}

echo "ZIP created successfully at: " . $zip_path . "\n";
$zip = new ZipArchive();
if ( $zip->open( $zip_path ) === true ) {
	echo "Archive files:\n";
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$stat = $zip->statIndex( $i );
		echo "   - " . $stat['name'] . " (" . $stat['size'] . " bytes)\n";
	}
	$zip->close();
}

assert( file_exists( $zip_path ) && filesize( $zip_path ) > 0 );
echo "\n============================================\n";
echo " ALL USER DATASET TESTS PASSED WITH 100%! \n";
echo "============================================\n";
