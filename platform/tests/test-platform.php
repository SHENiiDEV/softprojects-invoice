<?php
/**
 * Test Suite for Universal Catalog Matcher & Invoice Hub.
 * Run via: php platform/tests/test-platform.php
 */

require_once __DIR__ . '/../classes/class-crawler.php';
require_once __DIR__ . '/../classes/class-matcher.php';
require_once __DIR__ . '/../classes/class-pdf-builder.php';

echo "=== 1. Testing Schema.org & DOM Parsers ===\n";

$mock_html = '
<!DOCTYPE html>
<html>
<head>
<script type="application/ld+json">
{
  "@context": "https://schema.org/",
  "@type": "ItemList",
  "itemListElement": [
    {
      "@type": "Product",
      "name": "Urban Cargo Pants",
      "sku": "CRG-01",
      "offers": {
        "@type": "Offer",
        "price": "79.50",
        "priceCurrency": "EUR"
      }
    },
    {
      "@type": "Product",
      "name": "Oversized Hoodie Black",
      "sku": "HOD-OVR",
      "offers": {
        "@type": "Offer",
        "price": "89.00",
        "priceCurrency": "EUR"
      }
    }
  ]
}
</script>
</head>
<body>
  <div class="product-item">
    <h3 class="product-title">Graphic Tee Neon</h3>
    <span class="price">35,00 €</span>
  </div>
</body>
</html>
';

$parsed_jsonld = Universal_Catalog_Crawler::parse_json_ld( $mock_html, 'https://example.com' );
echo "Parsed " . count( $parsed_jsonld ) . " products from Schema.org JSON-LD:\n";
foreach ( $parsed_jsonld as $p ) {
	echo "  - " . $p['name'] . " @ " . $p['price'] . " " . $p['currency'] . "\n";
}
assert( count( $parsed_jsonld ) === 2, 'Must parse 2 products from JSON-LD' );

$parsed_dom = Universal_Catalog_Crawler::parse_html_dom( $mock_html, 'https://example.com' );
echo "Parsed " . count( $parsed_dom ) . " products from DOM Scraper:\n";
foreach ( $parsed_dom as $p ) {
	echo "  - " . $p['name'] . " @ " . $p['price'] . " " . $p['currency'] . "\n";
}
assert( count( $parsed_dom ) >= 1, 'Must parse DOM product' );
echo "✓ Parsers verified successfully!\n\n";

echo "=== 2. Testing Subset Sum Matching & Shipping Rules ===\n";

$test_catalog = array(
	array( 'name' => 'Italian Silk Dress', 'price' => 140.00, 'price_cents' => 14000, 'currency' => 'EUR' ),
	array( 'name' => 'Designer Wool Coat', 'price' => 250.00, 'price_cents' => 25000, 'currency' => 'EUR' ),
	array( 'name' => 'Leather Jacket', 'price' => 180.00, 'price_cents' => 18000, 'currency' => 'EUR' ),
	array( 'name' => 'Classic Cotton Shirt', 'price' => 45.00, 'price_cents' => 4500, 'currency' => 'EUR' ),
	array( 'name' => 'Unisex Organic Sweatshirt', 'price' => 59.68, 'price_cents' => 5968, 'currency' => 'EUR' ),
	array( 'name' => 'Designer Scarf', 'price' => 35.00, 'price_cents' => 3500, 'currency' => 'EUR' ),
	array( 'name' => 'Youth Heavy Blend Hoodie', 'price' => 21.78, 'price_cents' => 2178, 'currency' => 'EUR' ),
);

// Test 20.00 EUR (<= 50 EUR -> 11.69 EUR shipping)
$match_20 = Universal_Catalog_Matcher::match_amount( $test_catalog, 20.00, 'EUR' );
echo "Test 20.00 EUR Match:\n";
echo "  - Subtotal: " . $match_20['subtotal'] . " EUR\n";
echo "  - Shipping: " . $match_20['shipping']['method_title'] . " (" . $match_20['shipping']['cost'] . " EUR)\n";
echo "  - Total: " . $match_20['total'] . " EUR\n";
assert( $match_20['total_cents'] === 2000 );
assert( $match_20['shipping']['cost_cents'] === 1169 );
assert( $match_20['subtotal_cents'] === 831 );
echo "✓ 20.00 EUR order verified!\n\n";

// Test 200.00 EUR (> 50 EUR -> Free shipping)
$match_200 = Universal_Catalog_Matcher::match_amount( $test_catalog, 200.00, 'EUR' );
echo "Test 200.00 EUR Match:\n";
echo "  - Subtotal: " . $match_200['subtotal'] . " EUR\n";
echo "  - Shipping: " . $match_200['shipping']['method_title'] . " (" . $match_200['shipping']['cost'] . " EUR)\n";
echo "  - Total: " . $match_200['total'] . " EUR\n";
foreach ( $match_200['items'] as $item ) {
	echo sprintf( "     * [%dx] %s @ %.2f EUR = %.2f EUR\n", $item['qty'], $item['name'], $item['unit_price'], $item['total'] );
	assert( $item['qty'] <= 3, 'Qty must be bounded <= 3' );
}
assert( $match_200['total_cents'] === 20000 );
assert( $match_200['shipping']['cost_cents'] === 0 );
echo "✓ 200.00 EUR order verified!\n\n";

echo "=== 3. Testing Text Receipt Formatter ===\n";
$customer = array(
	'order_id' => 'DRZ-37708',
	'card_pan' => '433467***4469',
	'name'     => 'David Jurado Giles',
	'email'    => 'info.imprentajurado@gmail.com',
	'phone'    => '34 626829761',
	'address'  => 'Carrer dels Espardenyers, 25, Valls, 43800, Spain',
	'date'     => '29.06.2026',
);

$receipt_txt = Universal_Catalog_Matcher::format_text_receipt( $match_200, $customer, 'DREZZA' );
echo $receipt_txt . "\n\n";
assert( strpos( $receipt_txt, '433467***4469' ) !== false );
assert( strpos( $receipt_txt, 'DRZ-37708' ) !== false );
assert( strpos( $receipt_txt, 'David Jurado Giles' ) !== false );
echo "✓ Text receipt verified!\n\n";

echo "=== 4. Testing PDF Generation via wkhtmltopdf ===\n";
$pdf_out = __DIR__ . '/test_receipt_output.pdf';
$pdf_res = Universal_PDF_Builder::generate_pdf( $match_200, $customer, $pdf_out, 'DREZZA' );
assert( $pdf_res === true, 'PDF generation must succeed' );
assert( file_exists( $pdf_out ) && filesize( $pdf_out ) > 0 );
echo "PDF created successfully: " . $pdf_out . " (" . filesize( $pdf_out ) . " bytes)\n";
@unlink( $pdf_out );

echo "============================================================\n";
echo " ALL PLATFORM TESTS PASSED 100%! \n";
echo "============================================================\n";
