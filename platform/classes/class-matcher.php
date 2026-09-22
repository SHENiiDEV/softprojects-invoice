<?php
/**
 * Bounded Knapsack Subset Sum Matcher & Formatter for Universal Catalog Matcher.
 */

class Universal_Catalog_Matcher {

	/**
	 * Match products from catalog to reach target amount exactly.
	 *
	 * @param array  $catalog_products List of catalog products ([name, price, price_cents, sku, currency])
	 * @param float  $target_amount   Target order amount in decimal (e.g. 200.00)
	 * @param string $target_currency Target currency code (e.g. 'EUR')
	 * @param array  $custom_options  Optional options (max_qty_per_item, shipping_override)
	 * @return array
	 */
	public static function match_amount( $catalog_products, $target_amount, $target_currency = 'EUR', $custom_options = array() ) {
		$target_amount   = round( (float) $target_amount, 2 );
		$target_cents    = (int) round( $target_amount * 100 );
		$target_currency = strtoupper( trim( $target_currency ) );

		if ( $target_cents <= 0 ) {
			return array(
				'success'  => false,
				'error'    => 'Сумма заказа должна быть больше 0.',
				'items'    => array(),
				'subtotal' => 0,
				'shipping' => array( 'cost' => 0, 'method_title' => 'None' ),
				'total'    => 0,
			);
		}

		if ( empty( $catalog_products ) ) {
			return array(
				'success'  => false,
				'error'    => 'Каталог пуст. Не удалось подобрать товары.',
				'items'    => array(),
				'subtotal' => 0,
				'shipping' => array( 'cost' => 0, 'method_title' => 'None' ),
				'total'    => 0,
			);
		}

		// 1. Calculate or apply manual shipping in target currency
		if ( isset( $custom_options['shipping_cost'] ) && is_numeric( $custom_options['shipping_cost'] ) ) {
			$manual_cost = round( (float) $custom_options['shipping_cost'], 2 );
			if ( $manual_cost < 0 ) {
				$manual_cost = 0.0;
			}
			$manual_title = ! empty( $custom_options['shipping_title'] )
				? trim( $custom_options['shipping_title'] )
				: ( $manual_cost > 0 ? 'Flat rate' : 'Free shipping' );

			$shipping_info = array(
				'cost'          => $manual_cost,
				'cost_cents'    => (int) round( $manual_cost * 100 ),
				'method_title'  => $manual_title,
				'base_cost'     => $manual_cost,
				'base_currency' => $target_currency,
			);
		} else {
			$shipping_info = self::resolve_shipping( $target_amount, $target_currency );
		}

		$shipping_cents = (int) $shipping_info['cost_cents'];
		$subtotal_target_cents = $target_cents - $shipping_cents;

		// Handle small amounts where shipping >= total
		if ( $subtotal_target_cents <= 0 ) {
			if ( isset( $custom_options['shipping_cost'] ) ) {
				return array(
					'success'  => false,
					'error'    => sprintf( 'Сумма доставки (%s %s) не может быть больше или равна общей сумме заказа (%s %s).', number_format( $shipping_info['cost'], 2 ), $target_currency, number_format( $target_amount, 2 ), $target_currency ),
					'items'    => array(),
					'subtotal' => 0,
					'shipping' => $shipping_info,
					'total'    => 0,
				);
			}
			$subtotal_target_cents = $target_cents;
			$shipping_info['cost']       = 0.0;
			$shipping_info['cost_cents'] = 0;
			$shipping_info['method_title'] = 'Free shipping';
		}

		// 2. Normalize and filter products with non-zero prices
		$items_pool = array();
		foreach ( $catalog_products as $p ) {
			$price = isset( $p['price'] ) ? (float) $p['price'] : ( (float) $p['price_cents'] / 100 );
			$cents = (int) round( $price * 100 );

			if ( $cents > 0 && $cents <= $subtotal_target_cents ) {
				$items_pool[] = array(
					'id'          => isset( $p['id'] ) ? $p['id'] : crc32( $p['name'] ),
					'name'        => $p['name'],
					'sku'         => ! empty( $p['sku'] ) ? $p['sku'] : '',
					'price'       => $price,
					'price_cents' => $cents,
					'currency'    => $target_currency,
					'link'        => ! empty( $p['link'] ) ? $p['link'] : '',
				);
			}
		}

		// If all products are more expensive than subtotal, take the cheapest and scale down
		if ( empty( $items_pool ) ) {
			usort( $catalog_products, function( $a, $b ) {
				return $a['price_cents'] <=> $b['price_cents'];
			} );
			$cheapest = $catalog_products[0];
			$single_p = round( $subtotal_target_cents / 100, 2 );
			return array(
				'success'        => true,
				'items'          => array(
					array(
						'name'        => $cheapest['name'],
						'sku'         => ! empty( $cheapest['sku'] ) ? $cheapest['sku'] : '',
						'qty'         => 1,
						'unit_price'  => $single_p,
						'total'       => $single_p,
						'total_cents' => $subtotal_target_cents,
					),
				),
				'subtotal'       => $single_p,
				'subtotal_cents' => $subtotal_target_cents,
				'shipping'       => $shipping_info,
				'total'          => $target_amount,
				'total_cents'    => $target_cents,
				'currency'       => $target_currency,
			);
		}

		// 3. Solve Bounded Knapsack DP (max 2 duplicates per item, cap 3)
		$max_copies      = isset( $custom_options['max_qty'] ) ? (int) $custom_options['max_qty'] : 2;
		$max_items_limit = isset( $custom_options['max_items'] ) ? (int) $custom_options['max_items'] : 0;

		// If user specified a manual maximum/exact items limit
		if ( $max_items_limit > 0 ) {
			$matched_items = self::solve_constrained_knapsack( $items_pool, $subtotal_target_cents, $max_items_limit, $max_copies );
			return self::format_result( $matched_items, $subtotal_target_cents, $shipping_info, $target_amount, $target_currency );
		}

		$expanded = array();
		foreach ( $items_pool as $item ) {
			for ( $k = 1; $k <= $max_copies; $k++ ) {
				$expanded[] = $item;
			}
		}

		// Shuffle slightly to give diverse combinations across runs
		shuffle( $expanded );

		$dp = array();
		$dp[0] = array(
			'prev_sum'  => -1,
			'item_idx'  => -1,
			'count'     => 0,
		);

		foreach ( $expanded as $idx => $item ) {
			$cost = (int) $item['price_cents'];
			for ( $s = $subtotal_target_cents; $s >= $cost; $s-- ) {
				$prev = $s - $cost;
				if ( isset( $dp[ $prev ] ) ) {
					$new_count = $dp[ $prev ]['count'] + 1;
					if ( ! isset( $dp[ $s ] ) || $new_count < $dp[ $s ]['count'] ) {
						$dp[ $s ] = array(
							'prev_sum' => $prev,
							'item_idx' => $idx,
							'count'    => $new_count,
						);
					}
				}
			}
		}

		// Check exact match
		if ( isset( $dp[ $subtotal_target_cents ] ) ) {
			$matched_items = self::reconstruct_items( $dp, $expanded, $subtotal_target_cents );
			return self::format_result( $matched_items, $subtotal_target_cents, $shipping_info, $target_amount, $target_currency );
		}

		// If no exact match: find closest sum <= target, and adjust cents on real catalog item
		$best_sum = 0;
		for ( $s = $subtotal_target_cents; $s >= 0; $s-- ) {
			if ( isset( $dp[ $s ] ) && $s > $best_sum ) {
				$best_sum = $s;
				break;
			}
		}

		if ( $best_sum > 0 ) {
			$matched_items = self::reconstruct_items( $dp, $expanded, $best_sum );
			$diff_cents    = $subtotal_target_cents - $best_sum;

			// Adjust diff directly on the last product
			$last_idx = count( $matched_items ) - 1;
			$matched_items[ $last_idx ]['total_cents'] += $diff_cents;
			$matched_items[ $last_idx ]['total'] = round( $matched_items[ $last_idx ]['total_cents'] / 100, 2 );
			$matched_items[ $last_idx ]['unit_price'] = round( $matched_items[ $last_idx ]['total'] / $matched_items[ $last_idx ]['qty'], 2 );

			return self::format_result( $matched_items, $subtotal_target_cents, $shipping_info, $target_amount, $target_currency, $diff_cents );
		}

		// Fallback: single item scaled to target
		$first_p = $items_pool[0];
		$sub_val = round( $subtotal_target_cents / 100, 2 );
		$fallback_items = array(
			array(
				'name'        => $first_p['name'],
				'sku'         => $first_p['sku'],
				'qty'         => 1,
				'unit_price'  => $sub_val,
				'total'       => $sub_val,
				'total_cents' => $subtotal_target_cents,
			),
		);

		return self::format_result( $fallback_items, $subtotal_target_cents, $shipping_info, $target_amount, $target_currency );
	}

	/**
	 * Reconstruct grouped items from DP path.
	 *
	 * @param array $dp
	 * @param array $expanded
	 * @param int   $target_sum
	 * @return array
	 */
	private static function reconstruct_items( $dp, $expanded, $target_sum ) {
		$raw_picked = array();
		$curr = $target_sum;

		while ( $curr > 0 && isset( $dp[ $curr ] ) && $dp[ $curr ]['item_idx'] !== -1 ) {
			$item_idx     = $dp[ $curr ]['item_idx'];
			$raw_picked[] = $expanded[ $item_idx ];
			$curr         = $dp[ $curr ]['prev_sum'];
		}

		// Group items by name
		$grouped = array();
		foreach ( $raw_picked as $p ) {
			$k = $p['name'];
			if ( ! isset( $grouped[ $k ] ) ) {
				$grouped[ $k ] = array(
					'name'        => $p['name'],
					'sku'         => $p['sku'],
					'qty'         => 0,
					'unit_price'  => $p['price'],
					'total'       => 0.0,
					'total_cents' => 0,
					'link'        => ! empty( $p['link'] ) ? $p['link'] : '',
				);
			}
			$grouped[ $k ]['qty']++;
			$grouped[ $k ]['total_cents'] += $p['price_cents'];
			$grouped[ $k ]['total'] = round( $grouped[ $k ]['total_cents'] / 100, 2 );
		}

		return array_values( $grouped );
	}

	/**
	 * Solve Knapsack DP with maximum item count constraint.
	 *
	 * @param array $items_pool
	 * @param int   $subtotal_target_cents
	 * @param int   $max_items
	 * @param int   $max_copies
	 * @return array
	 */
	private static function solve_constrained_knapsack( $items_pool, $subtotal_target_cents, $max_items, $max_copies = 2 ) {
		$max_items = max( 1, (int) $max_items );

		// Special case: 1 item requested
		if ( $max_items === 1 ) {
			// 1. Exact match
			foreach ( $items_pool as $p ) {
				if ( (int) $p['price_cents'] === $subtotal_target_cents ) {
					return array(
						array(
							'name'        => $p['name'],
							'sku'         => $p['sku'],
							'qty'         => 1,
							'unit_price'  => $p['price'],
							'total'       => $p['price'],
							'total_cents' => $p['price_cents'],
							'link'        => ! empty( $p['link'] ) ? $p['link'] : '',
						),
					);
				}
			}

			// 2. Closest item scaled to subtotal
			$best_item = $items_pool[0];
			$min_diff  = abs( $best_item['price_cents'] - $subtotal_target_cents );
			foreach ( $items_pool as $p ) {
				$diff = abs( $p['price_cents'] - $subtotal_target_cents );
				if ( $diff < $min_diff ) {
					$min_diff  = $diff;
					$best_item = $p;
				}
			}

			$unit_p = round( $subtotal_target_cents / 100, 2 );
			return array(
				array(
					'name'        => $best_item['name'],
					'sku'         => $best_item['sku'],
					'qty'         => 1,
					'unit_price'  => $unit_p,
					'total'       => $unit_p,
					'total_cents' => $subtotal_target_cents,
					'link'        => ! empty( $best_item['link'] ) ? $best_item['link'] : '',
				),
			);
		}

		$expanded = array();
		// Prefer distinct unique products so each picked item is a distinct position (row)
		if ( count( $items_pool ) >= $max_items ) {
			foreach ( $items_pool as $item ) {
				$expanded[] = $item;
			}
		} else {
			$copies_needed = (int) ceil( $max_items / max( 1, count( $items_pool ) ) );
			foreach ( $items_pool as $item ) {
				for ( $k = 1; $k <= $copies_needed; $k++ ) {
					$expanded[] = $item;
				}
			}
		}
		shuffle( $expanded );

		// 2D DP table: $dp[count][sum] = ['prev_s' => int, 'prev_c' => int, 'idx' => int]
		$dp = array();
		$dp[0][0] = array(
			'prev_s' => -1,
			'prev_c' => -1,
			'idx'    => -1,
		);

		foreach ( $expanded as $idx => $item ) {
			$cost = (int) $item['price_cents'];
			if ( $cost <= 0 || $cost > $subtotal_target_cents ) {
				continue;
			}

			for ( $c = $max_items; $c >= 1; $c-- ) {
				$prev_c = $c - 1;
				if ( ! isset( $dp[ $prev_c ] ) ) {
					continue;
				}

				foreach ( $dp[ $prev_c ] as $prev_s => $info ) {
					$new_s = $prev_s + $cost;
					if ( $new_s <= $subtotal_target_cents ) {
						if ( ! isset( $dp[ $c ][ $new_s ] ) ) {
							$dp[ $c ][ $new_s ] = array(
								'prev_s' => $prev_s,
								'prev_c' => $prev_c,
								'idx'    => $idx,
							);
						}
					}
				}
			}
		}

		// 1. Check exact match for subtotal_target_cents with c <= max_items
		for ( $c = $max_items; $c >= 1; $c-- ) {
			if ( isset( $dp[ $c ][ $subtotal_target_cents ] ) ) {
				return self::reconstruct_2d_items( $dp, $expanded, $c, $subtotal_target_cents );
			}
		}

		// 2. Find closest sum <= subtotal_target_cents across valid counts
		$best_s = 0;
		$best_c = 0;
		for ( $s = $subtotal_target_cents; $s >= 0; $s-- ) {
			for ( $c = 1; $c <= $max_items; $c++ ) {
				if ( isset( $dp[ $c ][ $s ] ) && $s > $best_s ) {
					$best_s = $s;
					$best_c = $c;
					break 2;
				}
			}
		}

		if ( $best_s > 0 && $best_c > 0 ) {
			$matched_items = self::reconstruct_2d_items( $dp, $expanded, $best_c, $best_s );
			$diff_cents    = $subtotal_target_cents - $best_s;

			$last_idx = count( $matched_items ) - 1;
			$matched_items[ $last_idx ]['total_cents'] += $diff_cents;
			$matched_items[ $last_idx ]['total'] = round( $matched_items[ $last_idx ]['total_cents'] / 100, 2 );
			$matched_items[ $last_idx ]['unit_price'] = round( $matched_items[ $last_idx ]['total'] / $matched_items[ $last_idx ]['qty'], 2 );

			return $matched_items;
		}

		// Fallback: 1 item scaled
		$first_p = $items_pool[0];
		$sub_val = round( $subtotal_target_cents / 100, 2 );
		return array(
			array(
				'name'        => $first_p['name'],
				'sku'         => $first_p['sku'],
				'qty'         => 1,
				'unit_price'  => $sub_val,
				'total'       => $sub_val,
				'total_cents' => $subtotal_target_cents,
				'link'        => ! empty( $first_p['link'] ) ? $first_p['link'] : '',
			),
		);
	}

	/**
	 * Reconstruct grouped items from 2D DP path.
	 *
	 * @param array $dp
	 * @param array $expanded
	 * @param int   $start_c
	 * @param int   $start_s
	 * @return array
	 */
	private static function reconstruct_2d_items( $dp, $expanded, $start_c, $start_s ) {
		$raw_picked = array();
		$curr_c     = $start_c;
		$curr_s     = $start_s;

		while ( $curr_c > 0 && $curr_s > 0 && isset( $dp[ $curr_c ][ $curr_s ] ) && $dp[ $curr_c ][ $curr_s ]['idx'] !== -1 ) {
			$info         = $dp[ $curr_c ][ $curr_s ];
			$raw_picked[] = $expanded[ $info['idx'] ];
			$curr_c       = $info['prev_c'];
			$curr_s       = $info['prev_s'];
		}

		$grouped = array();
		foreach ( $raw_picked as $p ) {
			$k = $p['name'];
			if ( ! isset( $grouped[ $k ] ) ) {
				$grouped[ $k ] = array(
					'name'        => $p['name'],
					'sku'         => $p['sku'],
					'qty'         => 0,
					'unit_price'  => $p['price'],
					'total'       => 0.0,
					'total_cents' => 0,
					'link'        => ! empty( $p['link'] ) ? $p['link'] : '',
				);
			}
			$grouped[ $k ]['qty']++;
			$grouped[ $k ]['total_cents'] += $p['price_cents'];
			$grouped[ $k ]['total'] = round( $grouped[ $k ]['total_cents'] / 100, 2 );
		}

		return array_values( $grouped );
	}

	/**
	 * Format complete output response.
	 *
	 * @param array  $items
	 * @param int    $subtotal_cents
	 * @param array  $shipping_info
	 * @param float  $total_amount
	 * @param string $currency
	 * @param int    $cent_adjustment
	 * @return array
	 */
	private static function format_result( $items, $subtotal_cents, $shipping_info, $total_amount, $currency, $cent_adjustment = 0 ) {
		$subtotal = round( $subtotal_cents / 100, 2 );
		return array(
			'success'         => true,
			'items'           => $items,
			'items_count'     => count( $items ),
			'subtotal'        => $subtotal,
			'subtotal_cents'  => $subtotal_cents,
			'shipping'        => $shipping_info,
			'total'           => $total_amount,
			'total_cents'     => (int) round( $total_amount * 100 ),
			'currency'        => $currency,
			'cent_adjustment' => $cent_adjustment,
		);
	}

	/**
	 * Calculate shipping for order amount:
	 *  - Order <= 50 EUR: 9.99 GBP converted (11.69 EUR Flat rate)
	 *  - Order > 50 EUR: Free shipping (0.00 EUR)
	 *
	 * @param float  $amount
	 * @param string $currency
	 * @return array
	 */
	public static function resolve_shipping( $amount, $currency = 'EUR' ) {
		$currency = strtoupper( trim( $currency ) );

		if ( $amount > 50.00 ) {
			return array(
				'cost'          => 0.0,
				'cost_cents'    => 0,
				'method_title'  => 'Free shipping',
				'base_cost'     => 0.0,
				'base_currency' => 'GBP',
			);
		}

		// Fixed 9.99 GBP rate -> 11.69 EUR
		$cost = 11.69;
		if ( $currency === 'GBP' ) {
			$cost = 9.99;
		} elseif ( $currency === 'USD' ) {
			$cost = 12.80;
		}

		return array(
			'cost'          => $cost,
			'cost_cents'    => (int) round( $cost * 100 ),
			'method_title'  => 'Flat rate',
			'base_cost'     => 9.99,
			'base_currency' => 'GBP',
		);
	}

	/**
	 * Generate formatted copyable text receipt.
	 *
	 * @param array $match_result
	 * @param array $customer_data
	 * @param string $store_name
	 * @return string
	 */
	public static function format_text_receipt( $match_result, $customer_data = array(), $store_name = 'DREZZA' ) {
		$currency  = ! empty( $match_result['currency'] ) ? $match_result['currency'] : 'EUR';
		$order_id  = ! empty( $customer_data['order_id'] ) ? $customer_data['order_id'] : ( ! empty( $customer_data['row_id'] ) ? 'DRZ-' . ( 37700 + (int) $customer_data['row_id'] ) : 'DRZ-37708' );
		if ( strpos( $order_id, '#' ) !== 0 ) {
			$order_id = '#' . $order_id;
		}

		$date      = ! empty( $customer_data['date'] ) ? $customer_data['date'] : date( 'd.m.Y' );
		$name      = ! empty( $customer_data['name'] ) ? $customer_data['name'] : 'Customer';
		$card_pan  = ! empty( $customer_data['card_pan'] ) ? $customer_data['card_pan'] : '';
		$subtotal  = number_format( (float) $match_result['subtotal'], 2, '.', ' ' );
		$total     = number_format( (float) $match_result['total'], 2, '.', ' ' );

		$shipping_txt = ( (float) $match_result['shipping']['cost'] > 0 )
			? sprintf( '%s (%s %s)', $match_result['shipping']['method_title'], number_format( (float) $match_result['shipping']['cost'], 2, '.', ' ' ), $currency )
			: 'Free shipping (0.00 ' . $currency . ')';

		$lines   = array();
		$lines[] = '============================================================';
		$lines[] = sprintf( 'Store: %s', $store_name );
		$lines[] = sprintf( 'Order: %s | Date: %s', $order_id, $date );
		$lines[] = sprintf( 'Customer: %s', $name );
		if ( ! empty( $card_pan ) ) {
			$lines[] = sprintf( 'Card Pan: %s', $card_pan );
		}
		if ( ! empty( $customer_data['email'] ) ) {
			$lines[] = sprintf( 'Email: %s', $customer_data['email'] );
		}
		if ( ! empty( $customer_data['address'] ) ) {
			$lines[] = sprintf( 'Address: %s', $customer_data['address'] );
		}
		$lines[] = '------------------------------------------------------------';
		$lines[] = 'ITEMS IN BASKET:';

		$idx = 1;
		foreach ( $match_result['items'] as $item ) {
			$item_total = number_format( (float) $item['total'], 2, '.', ' ' );
			$item_unit  = number_format( (float) $item['unit_price'], 2, '.', ' ' );
			$lines[]    = sprintf( '%d. %s  [x%d @ %s %s]  =  %s %s', $idx, $item['name'], $item['qty'], $item_unit, $currency, $item_total, $currency );
			$idx++;
		}

		$lines[] = '------------------------------------------------------------';
		$lines[] = sprintf( 'Subtotal: %s %s', $subtotal, $currency );
		$lines[] = sprintf( 'Shipping: %s', $shipping_txt );
		$lines[] = sprintf( 'TOTAL:    %s %s', $total, $currency );
		$lines[] = '============================================================';

		return implode( "\n", $lines );
	}

	/**
	 * Format items list as clean compact lines for quick copying.
	 *
	 * @param array $match_result
	 * @param string $currency
	 * @return string
	 */
	public static function format_compact_list( $match_result, $currency = 'EUR' ) {
		$lines = array();
		$idx = 1;
		foreach ( $match_result['items'] as $item ) {
			$item_total = number_format( (float) $item['total'], 2, '.', '' );
			$item_unit  = number_format( (float) $item['unit_price'], 2, '.', '' );
			$lines[] = sprintf( '%d. %s [x%d @ %s %s] = %s %s', $idx, $item['name'], $item['qty'], $item_unit, $currency, $item_total, $currency );
			$idx++;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Format items as Tab-Separated Values (TSV) for direct paste into Excel / Google Sheets.
	 *
	 * @param array $match_result
	 * @param string $currency
	 * @return string
	 */
	public static function format_tsv( $match_result, $currency = 'EUR' ) {
		$lines   = array();
		$lines[] = "Product Name\tQuantity\tUnit Price\tTotal\tCurrency\tSKU\tLink";
		foreach ( $match_result['items'] as $item ) {
			$lines[] = sprintf(
				"%s\t%d\t%.2f\t%.2f\t%s\t%s\t%s",
				$item['name'],
				$item['qty'],
				$item['unit_price'],
				$item['total'],
				$currency,
				! empty( $item['sku'] ) ? $item['sku'] : '',
				! empty( $item['link'] ) ? $item['link'] : ''
			);
		}
		return implode( "\n", $lines );
	}

	/**
	 * Recalculate totals and items from modified basket items.
	 *
	 * @param array  $items
	 * @param string $currency
	 * @param array  $shipping_info
	 * @return array
	 */
	public static function recalculate_from_items( $items, $currency = 'EUR', $shipping_info = array() ) {
		$subtotal_cents = 0;
		$clean_items    = array();

		foreach ( $items as $item ) {
			$qty        = max( 1, (int) $item['qty'] );
			$unit_price = round( (float) $item['unit_price'], 2 );
			$unit_cents = (int) round( $unit_price * 100 );
			$line_cents = $unit_cents * $qty;
			$subtotal_cents += $line_cents;

			$clean_items[] = array(
				'name'        => trim( (string) $item['name'] ),
				'sku'         => ! empty( $item['sku'] ) ? trim( (string) $item['sku'] ) : '',
				'qty'         => $qty,
				'unit_price'  => $unit_price,
				'total'       => round( $line_cents / 100, 2 ),
				'total_cents' => $line_cents,
				'link'        => ! empty( $item['link'] ) ? trim( (string) $item['link'] ) : '',
			);
		}

		$subtotal = round( $subtotal_cents / 100, 2 );

		if ( empty( $shipping_info ) ) {
			$shipping_info = self::resolve_shipping( $subtotal, $currency );
		}

		$shipping_cost = round( (float) $shipping_info['cost'], 2 );
		$total         = round( $subtotal + $shipping_cost, 2 );

		return array(
			'success'        => true,
			'items'          => $clean_items,
			'items_count'    => count( $clean_items ),
			'subtotal'       => $subtotal,
			'subtotal_cents' => $subtotal_cents,
			'shipping'       => $shipping_info,
			'total'          => $total,
			'total_cents'    => (int) round( $total * 100 ),
			'currency'       => $currency,
		);
	}
}
