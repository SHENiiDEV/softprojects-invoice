<?php
/**
 * Invoice Template in WooCommerce Customer Email Style (DREZZA Urban Spirit theme).
 * Fully localized using standard WooCommerce and WordPress translation functions.
 *
 * Variables passed into template:
 * @var array  $record          Customer record (first_name, full_name, email, account_number, address, city, country, postal_code, amount, currency)
 * @var array  $items           Array of matched products ([name, sku, qty, unit_price, total])
 * @var string $invoice_number  Generated invoice number (e.g. DRZ-37708)
 * @var string $invoice_date    Formatted date string
 * @var string $store_name      WooCommerce store name
 * @var string $currency_symbol Currency symbol or code
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WC_PIBG_TEST_RUN' ) ) {
	exit;
}

$first_name     = ! empty( $record['first_name'] ) ? esc_html( $record['first_name'] ) : __( 'Customer', 'woocommerce' );
$full_name      = ! empty( $record['full_name'] ) ? esc_html( $record['full_name'] ) : $first_name;
$email          = ! empty( $record['email'] ) ? esc_html( $record['email'] ) : '';
$card_pan       = ! empty( $record['account_number'] ) ? esc_html( $record['account_number'] ) : '';
$address        = ! empty( $record['address'] ) ? esc_html( $record['address'] ) : '';
$city           = ! empty( $record['city'] ) ? esc_html( $record['city'] ) : '';
$country        = ! empty( $record['country'] ) ? esc_html( $record['country'] ) : '';
$postal_code    = ! empty( $record['postal_code'] ) ? esc_html( $record['postal_code'] ) : '';
$currency       = ! empty( $record['currency'] ) ? esc_html( $record['currency'] ) : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR' );
$formatted_total    = number_format( (float) $record['amount'], 2, '.', ' ' );
$formatted_subtotal = isset( $subtotal ) ? number_format( (float) $subtotal, 2, '.', ' ' ) : $formatted_total;
$site_lang          = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'language' ) : 'en-US';

// Format display order number (#DRZ-37708)
$display_order_id = ( strpos( $invoice_number, '#' ) === 0 ) ? $invoice_number : '#' . $invoice_number;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $site_lang ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( sprintf( __( 'Invoice %s', 'woocommerce' ), $display_order_id ) ); ?></title>
	<style>
		@page {
			size: A4 portrait;
			margin: 8mm;
		}
		* {
			box-sizing: border-box;
			-webkit-print-color-adjust: exact;
			print-color-adjust: exact;
		}
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			background-color: #f4f5f7;
			margin: 0;
			padding: 10px;
			color: #374151;
			font-size: 14px;
			line-height: 1.5;
		}
		.invoice-container {
			background-color: #ffffff;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			width: 100%;
			max-width: 780px;
			margin: 0 auto;
			box-shadow: 0 2px 8px rgba(0,0,0,0.06);
			overflow: hidden;
		}
		.invoice-header {
			background-color: #181a1e;
			color: #ffffff;
			padding: 32px 40px;
			text-align: left;
			border-bottom: 4px solid #00f09a;
			position: relative;
		}
		.brand-badge {
			display: inline-block;
			text-transform: uppercase;
			font-size: 11px;
			font-weight: 800;
			letter-spacing: 2px;
			color: #00f09a;
			margin-bottom: 8px;
		}
		.invoice-header h1 {
			color: #ffffff;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			font-size: 26px;
			font-weight: 800;
			letter-spacing: -0.5px;
			line-height: 1.2;
			margin: 0 0 8px 0;
		}
		.invoice-header p {
			margin: 0;
			font-size: 14px;
			color: #e5e7eb;
			font-weight: 500;
		}
		.invoice-header .order-highlight {
			color: #00f09a;
			font-weight: 700;
		}
		.invoice-body {
			padding: 36px 40px;
		}
		.greeting {
			font-size: 15px;
			color: #4b5563;
			margin-bottom: 24px;
			line-height: 1.6;
		}
		.greeting strong {
			color: #111827;
		}
		.order-title {
			color: #181a1e;
			font-size: 18px;
			font-weight: 700;
			margin: 0 0 20px 0;
			border-bottom: 2px solid #00f09a;
			padding-bottom: 8px;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.order-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 30px;
			font-size: 14px;
		}
		.order-table th {
			color: #111827;
			border: 1px solid #e5e7eb;
			padding: 12px 14px;
			text-align: left;
			background-color: #f9fafb;
			font-weight: 700;
			text-transform: uppercase;
			font-size: 12px;
			letter-spacing: 0.5px;
		}
		.order-table th.col-price,
		.order-table td.col-price {
			text-align: right;
		}
		.order-table th.col-qty,
		.order-table td.col-qty {
			text-align: center;
			width: 90px;
		}
		.order-table td {
			color: #374151;
			border: 1px solid #e5e7eb;
			padding: 14px;
			vertical-align: middle;
		}
		.item-name {
			font-weight: 600;
			color: #111827;
			font-size: 14px;
		}
		.totals-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 30px;
		}
		.totals-table td {
			padding: 10px 14px;
			border: 1px solid #e5e7eb;
			font-size: 14px;
		}
		.totals-table .label {
			text-align: left;
			font-weight: 600;
			width: 65%;
			color: #4b5563;
			background-color: #f9fafb;
		}
		.totals-table .value {
			text-align: right;
			font-weight: 600;
			color: #111827;
		}
		.totals-table .total-row .label,
		.totals-table .total-row .value {
			font-size: 16px;
			font-weight: 800;
			color: #181a1e;
			background-color: #f0fdf4;
			border-top: 2px solid #00f09a;
			border-bottom: 2px solid #00f09a;
		}
		.customer-details-box {
			background-color: #f9fafb;
			border: 1px solid #e5e7eb;
			border-left: 4px solid #00f09a;
			border-radius: 4px;
			padding: 20px 24px;
			margin-bottom: 25px;
		}
		.customer-details-box h3 {
			margin: 0 0 14px 0;
			font-size: 15px;
			color: #181a1e;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		.customer-info-row {
			margin-bottom: 6px;
			line-height: 1.5;
			color: #374151;
		}
		.customer-info-row strong {
			color: #111827;
			font-weight: 600;
			display: inline-block;
			min-width: 130px;
		}
		.invoice-footer {
			text-align: center;
			padding: 24px 40px;
			font-size: 12px;
			color: #6b7280;
			background-color: #fafafa;
			border-top: 1px solid #e5e7eb;
		}
		.invoice-footer p {
			margin: 4px 0;
		}
		.invoice-footer .brand-sign {
			font-weight: 700;
			color: #181a1e;
		}
	</style>
</head>
<body>

<div class="invoice-container">
	<!-- Header -->
	<div class="invoice-header">
		<div class="brand-badge"><?php echo esc_html( $store_name ); ?></div>
		<h1><?php echo esc_html__( 'Invoice for order', 'woocommerce' ); ?></h1>
		<p>
			<?php 
				/* translators: 1: order number, 2: order date */
				printf( 
					esc_html__( 'Details for order %1$s • %2$s', 'woocommerce' ), 
					'<span class="order-highlight">' . esc_html( $display_order_id ) . '</span>', 
					esc_html( $invoice_date ) 
				); 
			?>
		</p>
	</div>

	<!-- Body -->
	<div class="invoice-body">
		<div class="greeting">
			<?php 
				/* translators: %s: Customer Name */
				printf( esc_html__( 'Hi %s,', 'woocommerce' ), '<strong>' . $full_name . '</strong>' ); 
			?><br>
			<?php 
				/* translators: %s: Store Name */
				printf( esc_html__( 'Thanks for your order in %s. Here are the details of your invoice:', 'woocommerce' ), '<strong>' . esc_html( $store_name ) . '</strong>' ); 
			?>
		</div>

		<div class="order-title">
			<span><?php echo esc_html( sprintf( __( 'Order %1$s (%2$s)', 'woocommerce' ), $display_order_id, $invoice_date ) ); ?></span>
		</div>

		<!-- Products Table -->
		<table class="order-table" cellspacing="0" cellpadding="0">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Product', 'woocommerce' ); ?></th>
					<th class="col-qty"><?php echo esc_html__( 'Quantity', 'woocommerce' ); ?></th>
					<th class="col-price"><?php echo esc_html__( 'Price', 'woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $items ) ) : ?>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td>
								<div class="item-name"><?php echo esc_html( $item['name'] ); ?></div>
							</td>
							<td class="col-qty"><?php echo esc_html( $item['qty'] ); ?></td>
							<td class="col-price">
								<?php echo esc_html( number_format( (float) $item['total'], 2, '.', ' ' ) . ' ' . $currency ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="3"><?php echo esc_html__( 'No items in order.', 'woocommerce' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Totals Table -->
		<table class="totals-table" cellspacing="0" cellpadding="0">
			<tbody>
				<tr>
					<td class="label"><?php echo esc_html__( 'Subtotal:', 'woocommerce' ); ?></td>
					<td class="value"><?php echo esc_html( $formatted_subtotal . ' ' . $currency ); ?></td>
				</tr>
				<?php if ( ! empty( $shipping ) && isset( $shipping['cost'] ) ) : ?>
				<tr>
					<td class="label"><?php echo esc_html__( 'Shipping:', 'woocommerce' ); ?></td>
					<td class="value">
						<?php 
						if ( (float) $shipping['cost'] > 0 ) {
							$method_name = ! empty( $shipping['method_title'] ) ? $shipping['method_title'] : __( 'Flat rate', 'woocommerce' );
							echo esc_html( sprintf( '%s: %s %s', $method_name, number_format( (float) $shipping['cost'], 2, '.', ' ' ), $currency ) );
						} else {
							echo esc_html( $shipping['method_title'] ?: __( 'Free shipping', 'woocommerce' ) );
						}
						?>
					</td>
				</tr>
				<?php endif; ?>
				<?php if ( ! empty( $card_pan ) ) : ?>
				<tr>
					<td class="label"><?php echo esc_html__( 'Card Pan:', 'woocommerce' ); ?></td>
					<td class="value"><?php echo esc_html( $card_pan ); ?></td>
				</tr>
				<?php endif; ?>
				<tr>
					<td class="label"><?php echo esc_html__( 'Payment method:', 'woocommerce' ); ?></td>
					<td class="value"><?php echo esc_html__( 'Direct bank transfer / Online', 'woocommerce' ); ?></td>
				</tr>
				<tr class="total-row">
					<td class="label"><?php echo esc_html__( 'Total:', 'woocommerce' ); ?></td>
					<td class="value"><?php echo esc_html( $formatted_total . ' ' . $currency ); ?></td>
				</tr>
			</tbody>
		</table>

		<!-- Customer / Billing Info Box -->
		<div class="customer-details-box">
			<h3><?php echo esc_html__( 'Billing address', 'woocommerce' ); ?></h3>
			<div class="customer-info-row">
				<strong><?php echo esc_html__( 'Name:', 'woocommerce' ); ?></strong> <?php echo $full_name; ?>
			</div>
			<?php if ( ! empty( $email ) ) : ?>
				<div class="customer-info-row">
					<strong><?php echo esc_html__( 'Email:', 'woocommerce' ); ?></strong> <?php echo $email; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $record['phone'] ) ) : ?>
				<div class="customer-info-row">
					<strong><?php echo esc_html__( 'Phone:', 'woocommerce' ); ?></strong> <?php echo esc_html( $record['phone'] ); ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $address ) ) : ?>
				<div class="customer-info-row">
					<strong><?php echo esc_html__( 'Address:', 'woocommerce' ); ?></strong> <?php echo $address; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $city ) || ! empty( $postal_code ) || ! empty( $country ) ) : ?>
				<div class="customer-info-row">
					<strong><?php echo esc_html__( 'City / Postcode:', 'woocommerce' ); ?></strong>
					<?php 
						$geo_parts = array_filter( array( $city, $postal_code, $country ) );
						echo esc_html( implode( ', ', $geo_parts ) );
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Footer -->
	<div class="invoice-footer">
		<p><span class="brand-sign"><?php echo esc_html( $store_name ); ?></span> &mdash; <?php echo esc_html__( 'Built with WooCommerce', 'woocommerce' ); ?></p>
		<p><?php echo esc_html__( 'This document is an official invoice for your order.', 'woocommerce' ); ?></p>
	</div>
</div>

</body>
</html>
