<?php
/**
 * Test verifying CURCY Multi-Currency (EUR conversion) and Shipping deduction/display.
 * Run via: php tests/test-curcy-shipping.php
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
if ( ! function_exists( 'get_woocommerce_currency' ) ) { function get_woocommerce_currency() { return 'GBP'; } }
if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) { function get_woocommerce_currency_symbol( $c ) { return $c === 'EUR' ? '€' : '£'; } }
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $c; private $m;
		public function __construct( $c, $m ) { $this->c = $c; $this->m = $m; }
		public function get_error_message() { return $this->m; }
	}
	function is_wp_error( $t ) { return $t instanceof WP_Error; }
}

require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-csv-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-xlsx-parser.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-subset-sum.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-pdf-generator.php';
require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-zip-handler.php';

echo "=== Testing CURCY Multi-Currency (EUR) and Shipping Integration ===\n";

// Set custom rates: GBP = 1.0 (base), EUR = 1.17 (target)
WC_PIBG_Subset_Sum::set_custom_rates( array(
	'GBP' => 1.0,
	'EUR' => 1.17,
) );

// Mock realistic catalog of products in EUR
$mock_products_eur = array(
	array( 'id' => 201, 'name' => 'Italian Silk Dress', 'sku' => 'DRS-SILK', 'price' => 140.00, 'price_cents' => 14000, 'currency' => 'EUR' ),
	array( 'id' => 202, 'name' => 'Designer Wool Coat', 'sku' => 'COT-DSG', 'price' => 250.00, 'price_cents' => 25000, 'currency' => 'EUR' ),
	array( 'id' => 203, 'name' => 'Leather Jacket', 'sku' => 'JKT-LTH', 'price' => 180.00, 'price_cents' => 18000, 'currency' => 'EUR' ),
	array( 'id' => 204, 'name' => 'Classic Cotton Shirt', 'sku' => 'SHT-COT', 'price' => 45.00, 'price_cents' => 4500, 'currency' => 'EUR' ),
	array( 'id' => 205, 'name' => 'Unisex Organic Sweatshirt', 'sku' => 'SWT-ORG', 'price' => 59.68, 'price_cents' => 5968, 'currency' => 'EUR' ),
	array( 'id' => 206, 'name' => 'Weekender Duffle Bag', 'sku' => 'BAG-WKD', 'price' => 64.50, 'price_cents' => 6450, 'currency' => 'EUR' ),
	array( 'id' => 207, 'name' => 'Leather Belt', 'sku' => 'BLT-LTH', 'price' => 25.00, 'price_cents' => 2500, 'currency' => 'EUR' ),
	array( 'id' => 208, 'name' => 'Designer Scarf', 'sku' => 'SCF-DSG', 'price' => 35.00, 'price_cents' => 3500, 'currency' => 'EUR' ),
	array( 'id' => 209, 'name' => 'Recycled Basketball Jersey', 'sku' => 'JRS-REC', 'price' => 24.05, 'price_cents' => 2405, 'currency' => 'EUR' ),
	array( 'id' => 210, 'name' => 'Youth Heavy Blend Hoodie', 'sku' => 'HOD-BLD', 'price' => 21.78, 'price_cents' => 2178, 'currency' => 'EUR' ),
);

WC_PIBG_Subset_Sum::set_products( $mock_products_eur, 'EUR' );

// 1. Check shipping for order <= 50.00 EUR (e.g. 45.00 EUR)
// 9.99 GBP @ 1.17 = 11.69 EUR Flat rate
$shipping_low = WC_PIBG_Subset_Sum::resolve_shipping( 45.00, 'EUR' );
echo "Order <= 50 EUR (45.00 EUR) Shipping:\n";
echo "  - Base: " . $shipping_low['base_cost'] . " " . $shipping_low['base_currency'] . "\n";
echo "  - Converted: " . $shipping_low['cost'] . " EUR (" . $shipping_low['cost_cents'] . " cents) via " . $shipping_low['method_title'] . "\n";
assert( $shipping_low['cost_cents'] === 1169, '45 EUR order must have 11.69 EUR (1169 cents) shipping' );
assert( $shipping_low['method_title'] === 'Flat rate' );

$match_low = WC_PIBG_Subset_Sum::match_products( 4500, 'EUR' );
echo "  - Subtotal: " . $match_low['subtotal'] . " EUR, Shipping: " . $match_low['shipping']['cost'] . " EUR, Total: " . $match_low['total'] . " EUR\n";
assert( $match_low['subtotal_cents'] + $match_low['shipping']['cost_cents'] === 4500 );
echo "✓ <= 50.00 EUR order with 11.69 EUR shipping verified!\n\n";

// 2. Check shipping for order > 50.00 EUR (e.g. 200.00 EUR, 998.00 EUR)
// FREE SHIPPING (0.00 EUR)
$shipping_high = WC_PIBG_Subset_Sum::resolve_shipping( 200.00, 'EUR' );
echo "Order > 50 EUR (200.00 EUR) Shipping:\n";
echo "  - Cost: " . $shipping_high['cost'] . " EUR, Method: " . $shipping_high['method_title'] . "\n";
assert( $shipping_high['cost_cents'] === 0, '200 EUR order must have FREE shipping (0 cents)' );
assert( $shipping_high['method_title'] === 'Free shipping' );
echo "✓ > 50.00 EUR order Free shipping verified!\n\n";

// 3. Match 998.00 EUR with Diverse Bounded Products
$match_998 = WC_PIBG_Subset_Sum::match_products( 99800, 'EUR' );
echo "Results for 998.00 EUR Order (Diverse Cart):\n";
echo "  - Subtotal: " . $match_998['subtotal'] . " EUR\n";
echo "  - Shipping: " . $match_998['shipping']['method_title'] . " (" . $match_998['shipping']['cost'] . " EUR)\n";
echo "  - Total: " . $match_998['total'] . " EUR\n";
echo "  - Items in basket:\n";

foreach ( $match_998['items'] as $item ) {
	echo sprintf( "     * [%dx] %s @ %.2f EUR = %.2f EUR\n", $item['qty'], $item['name'], $item['unit_price'], $item['total'] );
	// Ensure no single item has absurd quantity (e.g. max 2 or 3)
	assert( $item['qty'] <= 3, 'Item quantity must be reasonable (<= 3), got: ' . $item['qty'] );
}

assert( $match_998['total_cents'] === 99800, 'Total must equal 998.00 EUR (99800 cents)' );
assert( $match_998['shipping']['cost_cents'] === 0, 'Shipping must be free for 998 EUR' );
// 3b. Test 20.00 EUR order (like user's screenshot: Antonio Zullo 20.00 EUR)
$match_20 = WC_PIBG_Subset_Sum::match_products( 2000, 'EUR' );
echo "Results for 20.00 EUR Order (No dummy goods):\n";
echo "  - Subtotal: " . $match_20['subtotal'] . " EUR\n";
echo "  - Shipping: " . $match_20['shipping']['cost'] . " EUR (" . $match_20['shipping']['method_title'] . ")\n";
echo "  - Total: " . $match_20['total'] . " EUR\n";
echo "  - Items:\n";
foreach ( $match_20['items'] as $item ) {
	echo sprintf( "     * [%dx] %s (SKU: %s) @ %.2f EUR = %.2f EUR\n", $item['qty'], $item['name'], $item['sku'], $item['unit_price'], $item['total'] );
	assert( $item['sku'] !== 'ORD-ITEM', 'Must NOT have dummy ORD-ITEM goods' );
}
assert( $match_20['total_cents'] === 2000, 'Total must equal 20.00 EUR' );
assert( $match_20['shipping']['cost_cents'] === 1169, 'Shipping must be 11.69 EUR (1169 cents)' );
assert( $match_20['subtotal_cents'] === 831, 'Subtotal must be 8.31 EUR' );
echo "✓ 20.00 EUR order (8.31 subtotal + 11.69 shipping = 20.00 total) verified with NO dummy items!\n\n";

// 4. Test PDF generation
$record = array(
	'row_id'         => 1,
	'transaction_id' => 'a771a5934e90',
	'created_at'     => '2026-06-22 15:08:49',
	'state'          => 'COMPLETED',
	'first_name'     => 'Kevin',
	'last_name'      => 'Savigny',
	'full_name'      => 'Kevin Savigny',
	'amount'         => 998.00,
	'amount_cents'   => 99800,
	'currency'       => 'EUR',
	'account_number' => '513024***5893',
	'email'          => 'kevinsavigny@gmail.com',
	'phone'          => '33 612345678',
	'dob'            => '6/26/1993',
	'address'        => 'Quai Morand, 19, 14',
	'city'           => 'Paimpol',
	'country'        => 'France',
	'postal_code'    => '22500',
);

$test_pdf_path = __DIR__ . '/test_invoice_kevin_savigny.pdf';
$pdf_res = WC_PIBG_PDF_Generator::generate_pdf( $record, $match_998, $test_pdf_path );

assert( ! is_wp_error( $pdf_res ) );
assert( file_exists( $test_pdf_path ) && filesize( $test_pdf_path ) > 0 );
echo "PDF generated successfully at: " . $test_pdf_path . " (Size: " . filesize( $test_pdf_path ) . " bytes)\n";
unlink( $test_pdf_path );

echo "============================================\n";
echo " CURCY MULTI-CURRENCY & SHIPPING PASSED 100% \n";
echo "============================================\n";
