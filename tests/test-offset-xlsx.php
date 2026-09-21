<?php
/**
 * Test specifically verifying offset header handling (Row 1 = Title, Row 2 = Headers, Row 3+ = Data).
 * Run via: php tests/test-offset-xlsx.php
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

require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-csv-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-xlsx-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-subset-sum.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-pdf-generator.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-zip-handler.php';

echo "=== Testing Excel with Row 1 Title ('Payments (16)') and Row 2 Headers ===\n";

$temp_xlsx = tempnam( sys_get_temp_dir(), 'test_offset_' ) . '.xlsx';
$zip = new ZipArchive();
if ( $zip->open( $temp_xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
	$strings = array(
		"Payments (16)", // 0: Title row
		"Id", "Created", "State", "Amount", "Currency", // 1..5
		"Customer Account Number", "Customer Email", "Customer First Name", "Customer Last Name", // 6..9
		"Customer Date Of Birth", "Customer Phone", "Billing Address", "Billing City", // 10..13
		"Billing Country", "Billing Postal Code", // 14..15
		"a34bfd7eacfc42cea81f5ac762a805fd", "2026-06-29 22:26:51", "COMPLETED", "200", "EUR", // 16..20
		"433467***4469", "info.imprentajurado@gmail.com", "David", "Jurado Giles", // 21..24
		"8/24/1987", "34 626829761", "Carrer dels Espardenyers, 25, 7", "Valls", // 25..28
		"Spain", "43800", // 29..30
		"f8989e0386414c9d8fbe57166de99c7c", "2026-06-29 15:08:49", "300" // 31..33
	);

	$sst_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count( $strings ) . '">';
	foreach ( $strings as $s ) {
		$sst_xml .= '<si><t>' . htmlspecialchars( $s ) . '</t></si>';
	}
	$sst_xml .= '</sst>';

	$sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
		<sheetData>
			<!-- Row 1: Title Only -->
			<row r="1">
				<c r="A1" t="s"><v>0</v></c>
			</row>
			<!-- Row 2: Headers (Id, Created, State, Amount, ...) -->
			<row r="2">
				<c r="A2" t="s"><v>1</v></c>
				<c r="B2" t="s"><v>2</v></c>
				<c r="C2" t="s"><v>3</v></c>
				<c r="D2" t="s"><v>4</v></c>
				<c r="E2" t="s"><v>5</v></c>
				<c r="F2" t="s"><v>6</v></c>
				<c r="G2" t="s"><v>7</v></c>
				<c r="H2" t="s"><v>8</v></c>
				<c r="I2" t="s"><v>9</v></c>
				<c r="J2" t="s"><v>10</v></c>
				<c r="K2" t="s"><v>11</v></c>
				<c r="L2" t="s"><v>12</v></c>
				<c r="M2" t="s"><v>13</v></c>
				<c r="N2" t="s"><v>14</v></c>
				<c r="O2" t="s"><v>15</v></c>
			</row>
			<!-- Row 3: Data Row 1 (200 EUR) -->
			<row r="3">
				<c r="A3" t="s"><v>16</v></c>
				<c r="B3" t="s"><v>17</v></c>
				<c r="C3" t="s"><v>18</v></c>
				<c r="D3"><v>200</v></c>
				<c r="E3" t="s"><v>20</v></c>
				<c r="F3" t="s"><v>21</v></c>
				<c r="G3" t="s"><v>22</v></c>
				<c r="H3" t="s"><v>23</v></c>
				<c r="I3" t="s"><v>24</v></c>
				<c r="J3" t="s"><v>25</v></c>
				<c r="K3" t="s"><v>26</v></c>
				<c r="L3" t="s"><v>27</v></c>
				<c r="M3" t="s"><v>28</v></c>
				<c r="N3" t="s"><v>29</v></c>
				<c r="O3" t="s"><v>30</v></c>
			</row>
			<!-- Row 4: Data Row 2 (300 EUR) -->
			<row r="4">
				<c r="A4" t="s"><v>31</v></c>
				<c r="B4" t="s"><v>32</v></c>
				<c r="C4" t="s"><v>18</v></c>
				<c r="D4"><v>300</v></c>
				<c r="E4" t="s"><v>20</v></c>
				<c r="F4" t="s"><v>21</v></c>
				<c r="G4" t="s"><v>22</v></c>
				<c r="H4" t="s"><v>23</v></c>
				<c r="I4" t="s"><v>24</v></c>
				<c r="J4" t="s"><v>25</v></c>
				<c r="K4" t="s"><v>26</v></c>
				<c r="L4" t="s"><v>27</v></c>
				<c r="M4" t="s"><v>28</v></c>
				<c r="N4" t="s"><v>29</v></c>
				<c r="O4" t="s"><v>30</v></c>
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
	die( "XLSX Parse Failed: " . $parsed['error'] . "\n" );
}

echo "Successfully parsed " . count( $parsed['data'] ) . " transactions from offset XLSX:\n\n";

foreach ( $parsed['data'] as $idx => $row ) {
	echo "Transaction #" . ( $idx + 1 ) . ":\n";
	echo "  - Full Name: " . $row['full_name'] . "\n";
	echo "  - First Name: " . $row['first_name'] . "\n";
	echo "  - Last Name: " . $row['last_name'] . "\n";
	echo "  - Amount: " . $row['amount'] . " " . $row['currency'] . " (" . $row['amount_cents'] . " cents)\n";
	echo "  - Account: " . $row['account_number'] . "\n";
	echo "  - Email: " . $row['email'] . "\n";
	echo "  - Phone: " . $row['phone'] . "\n";
	echo "  - Address: " . $row['address'] . ", " . $row['city'] . ", " . $row['postal_code'] . ", " . $row['country'] . "\n";
	echo "  - Date: " . $row['created_at'] . "\n\n";
}

assert( count( $parsed['data'] ) === 2 );
assert( $parsed['data'][0]['first_name'] === 'David' );
assert( $parsed['data'][0]['last_name'] === 'Jurado Giles' );
assert( $parsed['data'][0]['full_name'] === 'David Jurado Giles' );
assert( $parsed['data'][0]['amount_cents'] === 20000 );
assert( $parsed['data'][1]['amount_cents'] === 30000 );
assert( $parsed['data'][0]['email'] === 'info.imprentajurado@gmail.com' );
assert( $parsed['data'][0]['phone'] === '34 626829761' );
assert( $parsed['data'][0]['address'] === 'Carrer dels Espardenyers, 25, 7' );
assert( $parsed['data'][0]['city'] === 'Valls' );
assert( $parsed['data'][0]['country'] === 'Spain' );
assert( $parsed['data'][0]['postal_code'] === '43800' );

echo "============================================\n";
echo " OFFSET XLSX TEST PASSED 100%! \n";
echo "============================================\n";
