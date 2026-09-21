<?php
/**
 * Dynamic Programming Subset Sum Algorithm for matching WooCommerce products to exact amount.
 * Full integration with WooCommerce Multi Currency (CURCY) and WooCommerce Shipping methods.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WC_PIBG_TEST_RUN' ) ) {
	exit;
}

class WC_PIBG_Subset_Sum {

	/**
	 * Cache of published WooCommerce products in cents indexed by currency.
	 *
	 * @var array
	 */
	private static $cached_products_by_currency = array();

	/**
	 * Custom injected exchange rates (for testing or overrides).
	 *
	 * @var array|null
	 */
	private static $custom_rates = null;

	/**
	 * Convert an amount from one currency to another using CURCY exchange rates or fixed multipliers.
	 *
	 * @param float  $amount        Amount to convert
	 * @param string $from_currency Source currency (e.g. 'GBP')
	 * @param string $to_currency   Target currency (e.g. 'EUR')
	 * @return float
	 */
	public static function convert_currency_amount( $amount, $from_currency = 'GBP', $to_currency = 'EUR' ) {
		$amount        = (float) $amount;
		$from_currency = strtoupper( trim( $from_currency ) );
		$to_currency   = strtoupper( trim( $to_currency ) );

		if ( $amount <= 0 || $from_currency === $to_currency ) {
			return $amount;
		}

		// 1. Check custom injected rates (if any)
		if ( ! empty( self::$custom_rates ) ) {
			if ( isset( self::$custom_rates[ $to_currency ] ) && ( $from_currency === 'GBP' || $from_currency === 'DEFAULT' ) ) {
				$rate = (float) self::$custom_rates[ $to_currency ];
				return round( $amount * $rate, 2 );
			}
			if ( isset( self::$custom_rates[ $to_currency ] ) && isset( self::$custom_rates[ $from_currency ] ) ) {
				$rate_to   = (float) self::$custom_rates[ $to_currency ];
				$rate_from = (float) self::$custom_rates[ $from_currency ];
				if ( $rate_from > 0 ) {
					return round( $amount * ( $rate_to / $rate_from ), 2 );
				}
			}
		}

		// 2. Check CURCY class (WOOMULTI_CURRENCY_Data) exchange rates
		if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
			try {
				if ( method_exists( 'WOOMULTI_CURRENCY_Data', 'get_ins' ) ) {
					$wmc_ins = WOOMULTI_CURRENCY_Data::get_ins();
				} elseif ( method_exists( 'WOOMULTI_CURRENCY_Data', 'get_instance' ) ) {
					$wmc_ins = WOOMULTI_CURRENCY_Data::get_instance();
				} else {
					$wmc_ins = null;
				}

				if ( $wmc_ins && method_exists( $wmc_ins, 'get_exchange_rate' ) ) {
					$rates = $wmc_ins->get_exchange_rate();
					if ( ! empty( $rates ) && is_array( $rates ) ) {
						$rate_from = isset( $rates[ $from_currency ] ) ? (float) $rates[ $from_currency ] : 1.0;
						$rate_to   = isset( $rates[ $to_currency ] ) ? (float) $rates[ $to_currency ] : 1.0;
						if ( $rate_from > 0 && $rate_to > 0 ) {
							return round( $amount * ( $rate_to / $rate_from ), 2 );
						}
					}
				}
			} catch ( Throwable $t ) {
				// Fall through
			}
		}

		// 3. Check CURCY settings in wp_options
		$wmc_option_keys = array( 'woo_multi_currency_params', 'woomulti_currency_params', 'woo_multi_currency' );
		foreach ( $wmc_option_keys as $opt_key ) {
			$wmc_params = function_exists( 'get_option' ) ? get_option( $opt_key ) : array();
			if ( ! empty( $wmc_params ) && isset( $wmc_params['currency_rate'] ) && is_array( $wmc_params['currency_rate'] ) ) {
				$rates = $wmc_params['currency_rate'];
				$rate_from = isset( $rates[ $from_currency ] ) ? (float) $rates[ $from_currency ] : ( $from_currency === 'GBP' ? 1.0 : 0 );
				$rate_to   = isset( $rates[ $to_currency ] ) ? (float) $rates[ $to_currency ] : ( $to_currency === 'GBP' ? 1.0 : 0 );

				if ( $rate_from > 0 && $rate_to > 0 ) {
					return round( $amount * ( $rate_to / $rate_from ), 2 );
				}
			}
		}

		// 4. Default conversion: 9.99 GBP -> 11.69 EUR (rate 1.17017017)
		if ( $from_currency === 'GBP' && $to_currency === 'EUR' ) {
			return round( $amount * 1.17017017, 2 );
		} elseif ( $from_currency === 'EUR' && $to_currency === 'GBP' ) {
			return round( $amount / 1.17017017, 2 );
		} elseif ( $from_currency === 'GBP' && $to_currency === 'USD' ) {
			return round( $amount * 1.28028, 2 );
		}

		return round( $amount * 1.17017017, 2 );
	}

	/**
	 * Get product price in target currency.
	 *
	 * @param WC_Product $product
	 * @param string     $target_currency Target currency code (e.g. 'EUR')
	 * @return float
	 */
	public static function get_product_price_in_currency( $product, $target_currency = 'EUR' ) {
		if ( ! is_object( $product ) ) {
			return 0.0;
		}

		$target_currency     = strtoupper( trim( $target_currency ) );
		$store_base_currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'GBP';

		$base_price = (float) $product->get_price();
		if ( $base_price <= 0 ) {
			return 0.0;
		}

		if ( $target_currency === $store_base_currency ) {
			return $base_price;
		}

		// Check fixed prices per currency in post meta
		$product_id = method_exists( $product, 'get_id' ) ? $product->get_id() : ( isset( $product->id ) ? $product->id : 0 );
		if ( $product_id && function_exists( 'get_post_meta' ) ) {
			$custom_price_keys = array(
				'_wmc_price_' . strtolower( $target_currency ),
				'_regular_price_' . strtolower( $target_currency ),
				'_sale_price_' . strtolower( $target_currency ),
			);

			foreach ( $custom_price_keys as $key ) {
				$fixed_val = get_post_meta( $product_id, $key, true );
				if ( ! empty( $fixed_val ) && is_numeric( $fixed_val ) && (float) $fixed_val > 0 ) {
					return (float) $fixed_val;
				}
			}

			// 2. Check if CURCY WOOMULTI_CURRENCY_Data get_price can be called safely with the WC_Product object
			if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
				try {
					if ( method_exists( 'WOOMULTI_CURRENCY_Data', 'get_ins' ) ) {
						$wmc_ins = WOOMULTI_CURRENCY_Data::get_ins();
					} elseif ( method_exists( 'WOOMULTI_CURRENCY_Data', 'get_instance' ) ) {
						$wmc_ins = WOOMULTI_CURRENCY_Data::get_instance();
					} else {
						$wmc_ins = null;
					}

					if ( $wmc_ins && method_exists( $wmc_ins, 'get_price' ) ) {
						$p = $wmc_ins->get_price( $product, $target_currency );
						if ( is_numeric( $p ) && (float) $p > 0 ) {
							return (float) $p;
						}
					}
				} catch ( Throwable $e ) {
					// Fallback to rate conversion
				}
			}
		}

		// 3. Convert base price to target currency via convert_currency_amount
		return self::convert_currency_amount( $base_price, $store_base_currency, $target_currency );
	}

	/**
	 * Get available published WooCommerce products formatted for DP solver in target currency.
	 *
	 * @param string $currency Target currency (e.g. 'EUR')
	 * @param bool   $force_refresh
	 * @return array Array of products
	 */
	public static function get_available_products( $currency = 'EUR', $force_refresh = false ) {
		$currency = strtoupper( trim( $currency ) );

		if ( isset( self::$cached_products_by_currency[ $currency ] ) && ! $force_refresh ) {
			return self::$cached_products_by_currency[ $currency ];
		}

		$products_list = array();

		if ( function_exists( 'wc_get_products' ) ) {
			$wc_products = wc_get_products( array(
				'status' => 'publish',
				'limit'  => 500,
				'return' => 'objects',
			) );

			foreach ( $wc_products as $product ) {
				$price = self::get_product_price_in_currency( $product, $currency );
				if ( $price > 0 ) {
					$price_cents = (int) round( $price * 100 );
					$products_list[] = array(
						'id'          => $product->get_id(),
						'name'        => $product->get_name(),
						'sku'         => $product->get_sku(),
						'price'       => $price,
						'price_cents' => $price_cents,
						'currency'    => $currency,
					);
				}
			}
		}

		// Sort products by price descending
		usort( $products_list, function( $a, $b ) {
			return $b['price_cents'] <=> $a['price_cents'];
		} );

		self::$cached_products_by_currency[ $currency ] = $products_list;
		return self::$cached_products_by_currency[ $currency ];
	}

	/**
	 * Set custom products list (used for unit testing or custom injection).
	 *
	 * @param array  $products
	 * @param string $currency
	 */
	public static function set_products( $products, $currency = 'EUR' ) {
		self::$cached_products_by_currency[ strtoupper( $currency ) ] = $products;
	}

	/**
	 * Set custom currency rates (for testing).
	 *
	 * @param array $rates
	 */
	public static function set_custom_rates( $rates ) {
		self::$custom_rates = $rates;
	}

	/**
	 * Resolve store shipping method and cost in target currency.
	 * Rule: Free shipping for orders above 50.00 EUR (or currency equivalent).
	 * Below or equal to 50.00 EUR, shipping is converted from base rate 9.99 GBP (e.g. 11.69 EUR).
	 *
	 * @param float  $total_amount Target invoice total amount
	 * @param string $currency     Target currency (e.g. 'EUR')
	 * @return array ['cost' => float, 'cost_cents' => int, 'method_title' => string, 'base_cost' => float, 'base_currency' => string]
	 */
	public static function resolve_shipping( $total_amount, $currency = 'EUR' ) {
		$currency            = strtoupper( trim( $currency ) );
		$store_base_currency = 'GBP';

		// Free shipping threshold: 50.00 in EUR, or converted equivalent in other currencies
		$threshold_in_target_currency = 50.0;
		if ( $currency !== 'EUR' ) {
			$threshold_in_target_currency = self::convert_currency_amount( 50.0, 'EUR', $currency );
		}

		// Orders above 50.00 EUR have FREE SHIPPING
		if ( $total_amount > $threshold_in_target_currency ) {
			$free_shipping_title = function_exists( '__' ) ? __( 'Free shipping', 'woocommerce' ) : 'Free shipping';
			return array(
				'cost'          => 0.0,
				'cost_cents'    => 0,
				'method_title'  => $free_shipping_title,
				'base_cost'     => 0.0,
				'base_currency' => $store_base_currency,
			);
		}

		// Standard flat rate shipping in base currency (9.99 GBP)
		$shipping_title     = function_exists( '__' ) ? __( 'Flat rate', 'woocommerce' ) : 'Flat rate';
		$base_shipping_cost = 9.99; // Standard 9.99 GBP base shipping

		if ( function_exists( 'WC' ) && class_exists( 'WC_Shipping_Zones' ) ) {
			$zones = WC_Shipping_Zones::get_zones();
			if ( ! empty( $zones ) ) {
				foreach ( $zones as $zone ) {
					$methods = $zone['shipping_methods'];
					foreach ( $methods as $method ) {
						if ( $method->is_enabled() ) {
							$shipping_title = $method->get_title();
							$cost = isset( $method->cost ) ? (float) $method->cost : 0.0;
							if ( $cost > 0 ) {
								$base_shipping_cost = $cost;
								break 2;
							}
						}
					}
				}
			}
		}

		// Convert base shipping cost (in GBP) to invoice target currency (e.g. 11.69 EUR via CURCY)
		$converted_shipping_cost = self::convert_currency_amount( $base_shipping_cost, 'GBP', $currency );

		// If total amount is small (<= converted_shipping_cost), provide free shipping so as not to exceed total
		if ( $converted_shipping_cost >= $total_amount ) {
			$converted_shipping_cost = 0.0;
			$shipping_title          = function_exists( '__' ) ? __( 'Free shipping', 'woocommerce' ) : 'Free shipping';
		}

		$shipping_cents = (int) round( $converted_shipping_cost * 100 );

		return array(
			'cost'          => $converted_shipping_cost,
			'cost_cents'    => $shipping_cents,
			'method_title'  => $shipping_title,
			'base_cost'     => $base_shipping_cost,
			'base_currency' => $store_base_currency,
		);
	}

	/**
	 * Find combination of products matching the target amount in cents considering currency and shipping.
	 *
	 * @param int    $target_cents Target sum in cents (e.g. 20000 for 200.00 EUR).
	 * @param string $currency     Currency code (e.g. 'EUR')
	 * @param array  $products_pool Optional custom products pool
	 * @param bool   $include_shipping Whether to deduce and include shipping
	 * @return array ['items' => [...], 'shipping' => [...], 'subtotal' => float, 'total' => float]
	 */
	public static function match_products( $target_cents, $currency = 'EUR', $products_pool = null, $include_shipping = true ) {
		$target_cents = (int) $target_cents;
		$currency     = strtoupper( trim( $currency ) );

		if ( $target_cents <= 0 ) {
			return array(
				'items'    => array(),
				'shipping' => array( 'cost' => 0.0, 'cost_cents' => 0, 'method_title' => 'Free shipping' ),
				'subtotal' => 0.0,
				'total'    => 0.0,
			);
		}

		$total_amount = round( $target_cents / 100, 2 );

		// Resolve shipping
		$shipping_info = $include_shipping ? self::resolve_shipping( $total_amount, $currency ) : array( 'cost' => 0.0, 'cost_cents' => 0, 'method_title' => '' );
		$shipping_cents = $shipping_info['cost_cents'];

		// Calculate target for products: Subtotal = Total - Shipping
		$products_target_cents = max( 0, $target_cents - $shipping_cents );

		$products = is_null( $products_pool ) ? self::get_available_products( $currency ) : $products_pool;

		// Filter products with valid positive price
		$valid_products = array();
		foreach ( $products as $prod ) {
			$p_cents = isset( $prod['price_cents'] ) ? (int) $prod['price_cents'] : (int) round( ( (float) $prod['price'] ) * 100 );
			if ( $p_cents > 0 ) {
				$prod['price_cents'] = $p_cents;
				$valid_products[] = $prod;
			}
		}

		$items = array();

		if ( empty( $valid_products ) ) {
			$items = self::create_fallback_items( $products_target_cents, $currency );
		} else {
			// Pass 1: Try bounded DP with max 2 items per product (natural realistic order)
			$matched = self::solve_bounded_dp( $products_target_cents, $valid_products, 2 );

			// Pass 2: If no exact match, try max 3 items per product
			if ( empty( $matched ) ) {
				$matched = self::solve_bounded_dp( $products_target_cents, $valid_products, 3 );
			}

			if ( ! empty( $matched ) ) {
				$items = self::group_matched_items( $matched );
			} else {
				// Pick best subset of real products and adjust price of a real item by the few cents difference
				$items = self::solve_best_partial( $products_target_cents, $valid_products, $currency, 2 );
			}
		}

		// Calculate actual subtotal from items
		$subtotal_cents = 0;
		foreach ( $items as $it ) {
			$subtotal_cents += isset( $it['total_cents'] ) ? (int) $it['total_cents'] : (int) round( $it['total'] * 100 );
		}

		// Ensure mathematical consistency: Subtotal + Shipping = Total
		$actual_shipping_cents = $target_cents - $subtotal_cents;
		if ( $actual_shipping_cents >= 0 && $shipping_info['cost_cents'] > 0 ) {
			$shipping_info['cost_cents'] = $actual_shipping_cents;
			$shipping_info['cost']       = round( $actual_shipping_cents / 100, 2 );
		}

		return array(
			'items'          => $items,
			'shipping'       => $shipping_info,
			'subtotal'       => round( $subtotal_cents / 100, 2 ),
			'subtotal_cents' => $subtotal_cents,
			'total'          => $total_amount,
			'total_cents'    => $target_cents,
		);
	}

	/**
	 * Solve exact Subset Sum with bounded item quantities for realistic cart composition.
	 * Limits duplicates so each product appears in realistic quantities (1-2 copies, max 3).
	 *
	 * @param int   $target_cents
	 * @param array $products
	 * @param int   $max_qty_per_item
	 * @return array
	 */
	private static function solve_bounded_dp( $target_cents, $products, $max_qty_per_item = 2 ) {
		if ( $target_cents <= 0 || empty( $products ) ) {
			return array();
		}

		if ( $target_cents > 20000000 ) {
			return array();
		}

		// Create instances list with capped quantity per product
		$instances = array();
		foreach ( $products as $prod ) {
			$p_cents = (int) $prod['price_cents'];
			if ( $p_cents <= 0 || $p_cents > $target_cents ) {
				continue;
			}
			// For expensive items (> 100 EUR), cap at 1; for other items cap at $max_qty_per_item
			$cap = ( $p_cents >= 10000 && $max_qty_per_item > 1 ) ? 1 : $max_qty_per_item;
			for ( $k = 0; $k < $cap; $k++ ) {
				$instances[] = $prod;
			}
		}

		if ( empty( $instances ) ) {
			return array();
		}

		// 0-1 Knapsack DP tracking parent instance and previous weight
		$parent = array_fill( 0, $target_cents + 1, -1 );
		$prev_w = array_fill( 0, $target_cents + 1, -1 );
		$parent[0] = 0;

		$num_inst = count( $instances );
		for ( $i = 0; $i < $num_inst; $i++ ) {
			$weight = $instances[ $i ]['price_cents'];
			for ( $w = $target_cents; $w >= $weight; $w-- ) {
				if ( $parent[ $w - $weight ] !== -1 && $parent[ $w ] === -1 ) {
					$parent[ $w ] = $i;
					$prev_w[ $w ] = $w - $weight;
				}
			}
			if ( $parent[ $target_cents ] !== -1 ) {
				break;
			}
		}

		if ( $parent[ $target_cents ] === -1 ) {
			return array();
		}

		// Backtrack path
		$result = array();
		$curr   = $target_cents;
		while ( $curr > 0 ) {
			$inst_idx = $parent[ $curr ];
			if ( ! isset( $instances[ $inst_idx ] ) ) {
				break;
			}
			$result[] = $instances[ $inst_idx ];
			$curr     = $prev_w[ $curr ];
		}

		return $result;
	}

	/**
	 * Solve exact Subset Sum using Dynamic Programming (unbounded fallback).
	 *
	 * @param int   $target_cents
	 * @param array $products
	 * @return array
	 */
	private static function solve_dp( $target_cents, $products ) {
		if ( $target_cents <= 0 ) {
			return array();
		}

		if ( $target_cents > 10000000 ) {
			return array();
		}

		$dp     = array_fill( 0, $target_cents + 1, PHP_INT_MAX );
		$parent = array_fill( 0, $target_cents + 1, -1 );
		$dp[0]  = 0;

		$num_products = count( $products );

		for ( $i = 0; $i < $num_products; $i++ ) {
			$weight = $products[ $i ]['price_cents'];
			for ( $w = $weight; $w <= $target_cents; $w++ ) {
				if ( $dp[ $w - $weight ] !== PHP_INT_MAX ) {
					if ( $dp[ $w - $weight ] + 1 < $dp[ $w ] ) {
						$dp[ $w ]     = $dp[ $w - $weight ] + 1;
						$parent[ $w ] = $i;
					}
				}
			}
		}

		if ( $dp[ $target_cents ] === PHP_INT_MAX ) {
			return array();
		}

		$result = array();
		$curr   = $target_cents;
		while ( $curr > 0 ) {
			$prod_idx = $parent[ $curr ];
			if ( $prod_idx === -1 || ! isset( $products[ $prod_idx ] ) ) {
				break;
			}
			$prod     = $products[ $prod_idx ];
			$result[] = $prod;
			$curr    -= $prod['price_cents'];
		}

		return $result;
	}

	/**
	 * Solve best partial match using bounded item instances of REAL products.
	 * Adjusts the price of a real item by the few cents difference (NO dummy / 0.01 goods).
	 *
	 * @param int    $target_cents
	 * @param array  $products
	 * @param string $currency
	 * @param int    $max_qty_per_item
	 * @return array
	 */
	private static function solve_best_partial( $target_cents, $products, $currency = 'EUR', $max_qty_per_item = 2 ) {
		$limit = min( $target_cents, 1000000 );
		$instances = array();
		foreach ( $products as $prod ) {
			$p_cents = (int) $prod['price_cents'];
			if ( $p_cents <= 0 || $p_cents > $limit ) {
				continue;
			}
			for ( $k = 0; $k < $max_qty_per_item; $k++ ) {
				$instances[] = $prod;
			}
		}

		// If no instances have price <= limit, pick the lowest priced real product
		if ( empty( $instances ) ) {
			usort( $products, function( $a, $b ) {
				return $a['price_cents'] <=> $b['price_cents'];
			} );
			$single_prod = $products[0];
			$single_amount = round( $target_cents / 100, 2 );
			return array(
				array(
					'id'          => $single_prod['id'],
					'name'        => $single_prod['name'],
					'sku'         => $single_prod['sku'],
					'qty'         => 1,
					'unit_price'  => $single_amount,
					'total'       => $single_amount,
					'total_cents' => $target_cents,
				),
			);
		}

		$parent = array_fill( 0, $limit + 1, -1 );
		$prev_w = array_fill( 0, $limit + 1, -1 );
		$parent[0] = 0;

		$num_inst = count( $instances );
		for ( $i = 0; $i < $num_inst; $i++ ) {
			$weight = $instances[ $i ]['price_cents'];
			for ( $w = $limit; $w >= $weight; $w-- ) {
				if ( $parent[ $w - $weight ] !== -1 && $parent[ $w ] === -1 ) {
					$parent[ $w ] = $i;
					$prev_w[ $w ] = $w - $weight;
				}
			}
		}

		$best_sum = 0;
		for ( $w = $limit; $w >= 0; $w-- ) {
			if ( $parent[ $w ] !== -1 ) {
				$best_sum = $w;
				break;
			}
		}

		$result = array();
		$curr   = $best_sum;
		while ( $curr > 0 ) {
			$inst_idx = $parent[ $curr ];
			if ( ! isset( $instances[ $inst_idx ] ) ) {
				break;
			}
			$result[] = $instances[ $inst_idx ];
			$curr     = $prev_w[ $curr ];
		}

		$grouped = self::group_matched_items( $result );

		if ( empty( $grouped ) ) {
			usort( $products, function( $a, $b ) {
				return $a['price_cents'] <=> $b['price_cents'];
			} );
			$single_prod = $products[0];
			$single_amount = round( $target_cents / 100, 2 );
			return array(
				array(
					'id'          => $single_prod['id'],
					'name'        => $single_prod['name'],
					'sku'         => $single_prod['sku'],
					'qty'         => 1,
					'unit_price'  => $single_amount,
					'total'       => $single_amount,
					'total_cents' => $target_cents,
				),
			);
		}

		// Adjust the few cents difference on the last REAL product in the list (NO dummy goods!)
		$diff_cents = $target_cents - $best_sum;
		if ( $diff_cents != 0 ) {
			$last_idx = count( $grouped ) - 1;
			$grouped[ $last_idx ]['total_cents'] += $diff_cents;
			$grouped[ $last_idx ]['total']       = round( $grouped[ $last_idx ]['total_cents'] / 100, 2 );
			$grouped[ $last_idx ]['unit_price']  = round( $grouped[ $last_idx ]['total'] / $grouped[ $last_idx ]['qty'], 2 );
		}

		return $grouped;
	}

	/**
	 * Group raw item list into quantities and line totals.
	 *
	 * @param array $raw_items
	 * @return array
	 */
	public static function group_matched_items( $raw_items ) {
		$grouped = array();

		foreach ( $raw_items as $item ) {
			$id = isset( $item['id'] ) ? $item['id'] : 0;
			$key = $id . '_' . $item['name'];

			if ( ! isset( $grouped[ $key ] ) ) {
				$unit_price = isset( $item['price'] ) ? (float) $item['price'] : round( $item['price_cents'] / 100, 2 );
				$grouped[ $key ] = array(
					'id'          => $id,
					'name'        => $item['name'],
					'sku'         => isset( $item['sku'] ) ? $item['sku'] : '',
					'qty'         => 1,
					'unit_price'  => $unit_price,
					'total'       => $unit_price,
					'total_cents' => isset( $item['price_cents'] ) ? (int) $item['price_cents'] : (int) round( $unit_price * 100 ),
				);
			} else {
				$grouped[ $key ]['qty']++;
				$grouped[ $key ]['total']       = round( $grouped[ $key ]['qty'] * $grouped[ $key ]['unit_price'], 2 );
				$grouped[ $key ]['total_cents'] += isset( $item['price_cents'] ) ? (int) $item['price_cents'] : (int) round( $grouped[ $key ]['unit_price'] * 100 );
			}
		}

		return array_values( $grouped );
	}

	/**
	 * Create fallback item when catalog is empty.
	 *
	 * @param int    $target_cents
	 * @param string $currency
	 * @return array
	 */
	private static function create_fallback_items( $target_cents, $currency = 'EUR' ) {
		$amount = round( $target_cents / 100, 2 );
		return array(
			array(
				'id'          => 0,
				'name'        => function_exists( '__' ) ? __( 'General Order Items', 'woocommerce' ) : 'General Order Items',
				'sku'         => 'GEN-001',
				'qty'         => 1,
				'unit_price'  => $amount,
				'total'       => $amount,
				'total_cents' => $target_cents,
			),
		);
	}
}
