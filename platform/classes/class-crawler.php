<?php
/**
 * Universal Multi-Engine E-Commerce Catalog Crawler.
 * 
 * Supports:
 *  1. WooCommerce Store API (/wp-json/wc/store/v1/products)
 *  2. WordPress REST API (/wp-json/wp/v2/product)
 *  3. Schema.org / JSON-LD Product Markup
 *  4. OpenGraph & Microdata Product metadata
 *  5. Generic HTML DOM / CSS Heuristic Scraper
 */

class Universal_Catalog_Crawler {

	private static $cache_dir = __DIR__ . '/../storage/catalogs/';

	/**
	 * Normalize and clean URL, fixing missing schema and accidental Cyrillic homoglyphs.
	 *
	 * @param string $url
	 * @return string
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( empty( $url ) ) {
			return '';
		}

		// Fix accidental Cyrillic homoglyphs (e.g. Cyrillic 'с' in 'co.uk')
		$homoglyphs = array(
			'с' => 'c', 'С' => 'C',
			'а' => 'a', 'А' => 'A',
			'о' => 'o', 'О' => 'O',
			'е' => 'e', 'Е' => 'E',
			'р' => 'p', 'Р' => 'P',
			'х' => 'x', 'Х' => 'X',
			'у' => 'y', 'У' => 'Y',
			'к' => 'k', 'К' => 'K',
			'В' => 'B', 'М' => 'M',
			'Н' => 'H', 'Т' => 'T',
		);
		$url = strtr( $url, $homoglyphs );

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}

		return $url;
	}

	/**
	 * Check if URL is valid.
	 *
	 * @param string $url
	 * @return bool
	 */
	public static function is_valid_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return true;
		}
		return (bool) preg_match( '#^https?://[a-z0-9\-\.]+\.[a-z]{2,}(/.*)?$#i', $url );
	}

	/**
	 * Get products for a given catalog URL (uses cache unless force_refresh is true).
	 *
	 * @param string $url
	 * @param string $target_currency Target currency code (e.g. 'EUR', 'USD', 'GBP')
	 * @param bool   $force_refresh
	 * @return array Array with 'success', 'source', 'products', 'currency', 'cached', 'error'
	 */
	public static function get_catalog( $url, $target_currency = 'EUR', $force_refresh = false ) {
		$url             = self::normalize_url( $url );
		$target_currency = strtoupper( trim( $target_currency ?: 'EUR' ) );

		if ( ! self::is_valid_url( $url ) ) {
			return array(
				'success'  => false,
				'error'    => 'Некорректный URL адрес магазина.',
				'products' => array(),
			);
		}

		$cache_key  = md5( preg_replace( '/^https?:\/\//i', '', rtrim( strtolower( $url ), '/' ) ) . '_' . strtolower( $target_currency ) );
		$cache_file = self::$cache_dir . $cache_key . '.json';

		if ( ! $force_refresh && file_exists( $cache_file ) ) {
			$cached_data = json_decode( file_get_contents( $cache_file ), true );
			if ( ! empty( $cached_data ) && ! empty( $cached_data['products'] ) ) {
				$cached_data['cached'] = true;
				return $cached_data;
			}
		}

		$result = self::scrape_url( $url, $target_currency );

		if ( $result['success'] && ! empty( $result['products'] ) ) {
			if ( ! file_exists( self::$cache_dir ) ) {
				@mkdir( self::$cache_dir, 0755, true );
			}
			$result['cached']     = false;
			$result['scraped_at'] = date( 'Y-m-d H:i:s' );
			@file_put_contents( $cache_file, json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}

		return $result;
	}

	/**
	 * Scrape website by sequentially attempting multiple extraction engines.
	 *
	 * @param string $url
	 * @param string $target_currency
	 * @return array
	 */
	public static function scrape_url( $url, $target_currency = 'EUR' ) {
		$target_currency = strtoupper( trim( $target_currency ?: 'EUR' ) );
		$parsed_url      = parse_url( $url );
		$origin          = ( isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] : 'https' ) . '://' . ( isset( $parsed_url['host'] ) ? $parsed_url['host'] : '' );

		// 1. Try WooCommerce Store API endpoint with multi-currency cookies/params
		$wc_products = self::try_woocommerce_store_api( $origin, $target_currency );
		if ( ! empty( $wc_products ) ) {
			return array(
				'success'   => true,
				'source'    => 'WooCommerce Store API',
				'url'       => $url,
				'currency'  => $target_currency,
				'products'  => $wc_products,
				'count'     => count( $wc_products ),
			);
		}

		// 2. Fetch HTML of the catalog page with currency switcher cookies and query param
		$sep = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
		$url_with_curr = $url . $sep . 'wmc-currency=' . $target_currency . '&currency=' . $target_currency;

		$html = self::fetch_http( $url_with_curr, $target_currency );
		if ( empty( $html ) ) {
			// Try original URL without extra query param
			$html = self::fetch_http( $url, $target_currency );
		}

		if ( empty( $html ) ) {
			return array(
				'success'  => false,
				'error'    => 'Не удалось загрузить страницу сайта. Проверьте доступность URL.',
				'products' => array(),
			);
		}

		// 3. Try Headless JS Entity / REST API discovery from scripts and HTML
		$headless_products = self::try_headless_js_entity_api( $html, $origin, $target_currency, $url );
		if ( ! empty( $headless_products ) ) {
			return array(
				'success'   => true,
				'source'    => 'Headless JS Entity REST API',
				'url'       => $url,
				'currency'  => $target_currency,
				'products'  => self::deduplicate_products( $headless_products ),
				'count'     => count( $headless_products ),
			);
		}

		// 4. Try Schema.org / JSON-LD extraction
		$jsonld_products = self::parse_json_ld( $html, $origin, $target_currency );
		if ( count( $jsonld_products ) >= 2 ) {
			return array(
				'success'   => true,
				'source'    => 'Schema.org JSON-LD',
				'url'       => $url,
				'currency'  => $target_currency,
				'products'  => self::deduplicate_products( $jsonld_products ),
				'count'     => count( $jsonld_products ),
			);
		}

		// 5. Try HTML DOM Scraper
		$dom_products = self::parse_html_dom( $html, $origin, $target_currency );
		if ( ! empty( $dom_products ) ) {
			// Merge any Schema.org products found earlier
			$all_products = array_merge( $jsonld_products, $dom_products );
			$unique       = self::deduplicate_products( $all_products );

			if ( ! empty( $unique ) ) {
				return array(
					'success'   => true,
					'source'    => 'HTML DOM Catalog Scraper',
					'url'       => $url,
					'currency'  => $target_currency,
					'products'  => $unique,
					'count'     => count( $unique ),
				);
			}
		}

		return array(
			'success'  => false,
			'error'    => 'На странице не найдено товаров или каталог загружается через сложный JavaScript. Попробуйте передать прямую ссылку на раздел /shop, /catalog или /products.',
			'products' => array(),
		);
	}

	/**
	 * Attempt to fetch products via WooCommerce public Store API.
	 *
	 * @param string $origin
	 * @param string $target_currency
	 * @return array
	 */
	private static function try_woocommerce_store_api( $origin, $target_currency = 'EUR' ) {
		$target_currency = strtoupper( trim( $target_currency ?: 'EUR' ) );

		$endpoints = array(
			$origin . '/wp-json/wc/store/v1/products?per_page=100&wmc-currency=' . $target_currency . '&currency=' . $target_currency,
			$origin . '/wp-json/wp/v2/product?per_page=100&wmc-currency=' . $target_currency . '&currency=' . $target_currency,
		);

		foreach ( $endpoints as $api_url ) {
			$json_content = self::fetch_http( $api_url, $target_currency );
			if ( empty( $json_content ) ) {
				continue;
			}

			$data = @json_decode( $json_content, true );
			if ( ! empty( $data ) && is_array( $data ) && ! isset( $data['code'] ) ) {
				$products = array();
				foreach ( $data as $item ) {
					$name = '';
					if ( isset( $item['name'] ) ) {
						$name = html_entity_decode( trim( $item['name'] ), ENT_QUOTES, 'UTF-8' );
					} elseif ( isset( $item['title']['rendered'] ) ) {
						$name = html_entity_decode( trim( $item['title']['rendered'] ), ENT_QUOTES, 'UTF-8' );
					}

					if ( empty( $name ) ) {
						continue;
					}

					$price = 0.0;
					$curr  = $target_currency;

					if ( isset( $item['prices']['price'] ) ) {
						$raw_p = (float) $item['prices']['price'];
						$decimals = isset( $item['prices']['currency_minor_unit'] ) ? (int) $item['prices']['currency_minor_unit'] : 2;
						$price    = $raw_p / ( 10 ** $decimals );
						$resp_cur = isset( $item['prices']['currency_code'] ) ? strtoupper( $item['prices']['currency_code'] ) : '';

						// If API still returned GBP prices while target is EUR, convert using CURCY exchange rate
						if ( $resp_cur === 'GBP' && $target_currency === 'EUR' ) {
							$price = round( $price * 1.17017, 2 );
						} elseif ( $resp_cur === 'EUR' && $target_currency === 'GBP' ) {
							$price = round( $price / 1.17017, 2 );
						}
					} elseif ( isset( $item['price'] ) ) {
						$price = (float) $item['price'];
					}

					if ( $price > 0 ) {
						$products[] = array(
							'id'          => isset( $item['id'] ) ? (int) $item['id'] : crc32( $name ),
							'name'        => $name,
							'sku'         => ! empty( $item['sku'] ) ? $item['sku'] : '',
							'price'       => round( $price, 2 ),
							'price_cents' => (int) round( $price * 100 ),
							'currency'    => $target_currency,
							'link'        => isset( $item['permalink'] ) ? $item['permalink'] : ( isset( $item['link'] ) ? $item['link'] : '' ),
						);
					}
				}

				if ( ! empty( $products ) ) {
					return $products;
				}
			}
		}

		return array();
	}

	/**
	 * Inspect HTML and linked JavaScript bundles to discover and fetch Headless Entity/Product REST APIs and JSON endpoints.
	 *
	 * @param string $html
	 * @param string $origin
	 * @param string $target_currency
	 * @param string $page_url
	 * @return array
	 */
	public static function try_headless_js_entity_api( $html, $origin, $target_currency = 'EUR', $page_url = '' ) {
		$target_currency  = strtoupper( trim( $target_currency ?: 'EUR' ) );
		$endpoints_to_try = array();

		// Detect language prefix from page URL (e.g. /en/catalog -> /en)
		$lang_prefix = '';
		if ( ! empty( $page_url ) ) {
			$path = parse_url( $page_url, PHP_URL_PATH );
			if ( preg_match( '#^/([a-z]{2})(?:/|$)#i', $path, $lm ) ) {
				$lang_prefix = '/' . strtolower( $lm[1] );
			}
		}

		// 1. Direct Universal E-Commerce REST API probes
		$standard_routes = array(
			'/api/products?page=1&sort=newest',
			'/api/products?page=1',
			'/api/products?limit=250',
			'/api/products',
			'/api/v1/products?limit=250',
			'/api/v1/products',
			'/api/catalog?limit=250',
			'/api/catalog',
			'/api/shop/products',
			'/api/items',
			'/products.json?limit=250',
			'/collections/all/products.json?limit=250',
		);

		foreach ( $standard_routes as $route ) {
			$endpoints_to_try[] = $origin . $route;
			if ( ! empty( $lang_prefix ) ) {
				$endpoints_to_try[] = $origin . $lang_prefix . $route;
			}
		}

		// 2. Look for 24-hex App IDs (Base44 / Headless apps like kidwear.co.uk) in HTML
		if ( preg_match_all( '#\b([a-f0-9]{24})\b#i', $html, $html_app_matches ) ) {
			foreach ( array_unique( $html_app_matches[1] ) as $app_id ) {
				$endpoints_to_try[] = $origin . '/api/apps/' . $app_id . '/entities/Product?q=%7B%7D&sort=-created_date&limit=1000';
				$endpoints_to_try[] = $origin . '/api/apps/' . $app_id . '/entities/Products?limit=1000';
			}
		}

		// 3. Scan inline script tags in HTML
		if ( preg_match_all( '#<script\b[^>]*>(.*?)</script>#is', $html, $inline_scripts ) ) {
			foreach ( $inline_scripts[1] as $inline_js ) {
				if ( empty( $inline_js ) ) continue;
				if ( preg_match_all( '#\b([a-f0-9]{24})\b#i', $inline_js, $in_app_matches ) ) {
					foreach ( array_unique( $in_app_matches[1] ) as $app_id ) {
						$endpoints_to_try[] = $origin . '/api/apps/' . $app_id . '/entities/Product?q=%7B%7D&sort=-created_date&limit=1000';
					}
				}
				if ( preg_match_all( '#["\'](/api/(?:v\d+/)?(?:products|catalog|items|goods|shop)[^"\'\s`<>]*?)["\']#i', $inline_js, $in_routes ) ) {
					foreach ( $in_routes[1] as $route ) {
						$endpoints_to_try[] = $origin . $route;
					}
				}
			}
		}

		// 4. Scan external script bundles referenced in the HTML (up to 30 scripts for Next.js/Vite)
		if ( preg_match_all( '#<script[^>]+src=["\']([^"\']+\.js[^"\']*)["\']#i', $html, $script_matches ) ) {
			foreach ( array_slice( array_unique( $script_matches[1] ), 0, 30 ) as $src ) {
				if ( strpos( $src, 'http' ) !== 0 ) {
					$src = rtrim( $origin, '/' ) . '/' . ltrim( $src, '/' );
				}
				$js_content = self::fetch_http( $src, $target_currency );
				if ( empty( $js_content ) ) {
					continue;
				}

				// Search for 24-hex app ID in bundle
				if ( preg_match_all( '#\b([a-f0-9]{24})\b#i', $js_content, $js_app_matches ) ) {
					foreach ( array_slice( array_unique( $js_app_matches[1] ), 0, 5 ) as $app_id ) {
						$endpoints_to_try[] = $origin . '/api/apps/' . $app_id . '/entities/Product?q=%7B%7D&sort=-created_date&limit=1000';
					}
				}

				// Search for REST API routes in bundle
				if ( preg_match_all( '#["\'](/api/(?:v\d+/)?(?:products|catalog|items|goods|shop)[^"\'\s`<>]*?)["\']#i', $js_content, $api_routes ) ) {
					foreach ( $api_routes[1] as $route ) {
						$endpoints_to_try[] = $origin . $route;
					}
				}
			}
		}

		$endpoints_to_try = array_unique( $endpoints_to_try );

		foreach ( $endpoints_to_try as $api_url ) {
			$products = self::fetch_and_auto_paginate_api( $api_url, $origin, $target_currency, $lang_prefix );
			if ( ! empty( $products ) && count( $products ) >= 2 ) {
				return $products;
			}
		}

		return array();
	}

	/**
	 * Fetch products from an API endpoint with automatic pagination support.
	 *
	 * @param string $api_url
	 * @param string $origin
	 * @param string $target_currency
	 * @param string $lang_prefix
	 * @return array
	 */
	private static function fetch_and_auto_paginate_api( $api_url, $origin, $target_currency = 'EUR', $lang_prefix = '' ) {
		$all_products = array();
		$seen_ids     = array();
		$max_pages    = 30; // Max pages to fetch

		// Check if endpoint already has page=1 or is paginatable
		$has_page_param = (bool) preg_match( '/[?&]page=(\d+)/i', $api_url );
		$is_shopify     = ( strpos( $api_url, 'products.json' ) !== false );

		for ( $page = 1; $page <= $max_pages; $page++ ) {
			$current_url = $api_url;

			if ( $has_page_param ) {
				$current_url = preg_replace( '/([?&])page=\d+/i', '${1}page=' . $page, $api_url );
			} elseif ( $page > 1 ) {
				if ( $is_shopify ) {
					$sep         = ( strpos( $api_url, '?' ) !== false ) ? '&' : '?';
					$current_url = $api_url . $sep . 'page=' . $page;
				} elseif ( strpos( $api_url, '/api/products' ) !== false || strpos( $api_url, '/api/catalog' ) !== false ) {
					$sep         = ( strpos( $api_url, '?' ) !== false ) ? '&' : '?';
					$current_url = $api_url . $sep . 'page=' . $page;
				} else {
					// Single-shot endpoint (e.g. limit=1000)
					break;
				}
			}

			$json_resp = self::fetch_http( $current_url, $target_currency );
			if ( empty( $json_resp ) ) {
				break;
			}

			$data = @json_decode( $json_resp, true );
			if ( empty( $data ) || ! is_array( $data ) ) {
				break;
			}

			// Handle root array or object with data/items/products/results
			$items_array = array();
			if ( isset( $data[0] ) ) {
				$items_array = $data;
			} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
				$items_array = $data['data'];
			} elseif ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
				$items_array = $data['items'];
			} elseif ( isset( $data['products'] ) && is_array( $data['products'] ) ) {
				$items_array = $data['products'];
			} elseif ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
				$items_array = $data['results'];
			}

			if ( empty( $items_array ) ) {
				break;
			}

			$page_new_count = 0;

			foreach ( $items_array as $item ) {
				if ( ! is_array( $item ) ) continue;

				$name = '';
				if ( ! empty( $item['name'] ) ) {
					$name = html_entity_decode( trim( (string) $item['name'] ), ENT_QUOTES, 'UTF-8' );
				} elseif ( ! empty( $item['title'] ) ) {
					$name = html_entity_decode( trim( (string) $item['title'] ), ENT_QUOTES, 'UTF-8' );
				} elseif ( ! empty( $item['productName'] ) ) {
					$name = html_entity_decode( trim( (string) $item['productName'] ), ENT_QUOTES, 'UTF-8' );
				}

				if ( empty( $name ) ) continue;

				$raw_price = 0.0;
				if ( isset( $item['price'] ) && is_numeric( $item['price'] ) ) {
					$raw_price = (float) $item['price'];
				} elseif ( isset( $item['salePrice'] ) && is_numeric( $item['salePrice'] ) ) {
					$raw_price = (float) $item['salePrice'];
				} elseif ( isset( $item['sale_price'] ) && is_numeric( $item['sale_price'] ) ) {
					$raw_price = (float) $item['sale_price'];
				} elseif ( isset( $item['regularPrice'] ) && is_numeric( $item['regularPrice'] ) ) {
					$raw_price = (float) $item['regularPrice'];
				} elseif ( isset( $item['variants'][0]['price'] ) && is_numeric( $item['variants'][0]['price'] ) ) { // Shopify
					$raw_price = (float) $item['variants'][0]['price'];
				} elseif ( isset( $item['min_price'] ) && is_numeric( $item['min_price'] ) ) {
					$raw_price = (float) $item['min_price'];
				} elseif ( isset( $item['minPrice'] ) && is_numeric( $item['minPrice'] ) ) {
					$raw_price = (float) $item['minPrice'];
				}

				// Currency conversion
				$item_currency = ! empty( $item['currency'] ) ? strtoupper( trim( $item['currency'] ) ) : ( stripos( $origin, '.co.uk' ) !== false ? 'GBP' : 'EUR' );

				$final_price = $raw_price;
				if ( $item_currency === 'GBP' && $target_currency === 'EUR' ) {
					$final_price = round( $raw_price * 1.17017, 2 );
				} elseif ( $item_currency === 'EUR' && $target_currency === 'GBP' ) {
					$final_price = round( $raw_price / 1.17017, 2 );
				} elseif ( $item_currency === 'GBP' && $target_currency === 'USD' ) {
					$final_price = round( $raw_price * 1.28028, 2 );
				}

				$slug = ! empty( $item['slug'] ) ? $item['slug'] : ( ! empty( $item['handle'] ) ? $item['handle'] : '' );
				$link = '';
				if ( ! empty( $item['source_url'] ) ) {
					$link = $item['source_url'];
				} elseif ( ! empty( $item['url'] ) ) {
					$link = ( strpos( $item['url'], 'http' ) === 0 ) ? $item['url'] : rtrim( $origin, '/' ) . '/' . ltrim( $item['url'], '/' );
				} elseif ( ! empty( $slug ) ) {
					$prefix = ! empty( $lang_prefix ) ? $lang_prefix : '';
					$link   = rtrim( $origin, '/' ) . $prefix . '/product/' . $slug;
				}

				$item_id = isset( $item['id'] ) ? (string) $item['id'] : $name;
				if ( isset( $seen_ids[ $item_id ] ) ) {
					continue;
				}
				$seen_ids[ $item_id ] = true;

				if ( $final_price > 0 ) {
					$all_products[] = array(
						'id'          => crc32( $item_id . '_' . $final_price ),
						'name'        => $name,
						'sku'         => ! empty( $item['sku'] ) ? (string) $item['sku'] : '',
						'price'       => round( $final_price, 2 ),
						'price_cents' => (int) round( $final_price * 100 ),
						'currency'    => $target_currency,
						'link'        => $link,
					);
					$page_new_count++;
				}
			}

			// If page had no new items or single page mode, stop paginating
			if ( $page_new_count === 0 || ( ! $has_page_param && ! $is_shopify && count( $items_array ) < 10 ) ) {
				break;
			}
		}

		return $all_products;
	}

	/**
	 * Parse JSON-LD Schema.org scripts for Product objects.
	 *
	 * @param string $html
	 * @param string $origin
	 * @return array
	 */
	public static function parse_json_ld( $html, $origin ) {
		$products = array();

		if ( ! preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches ) ) {
			return $products;
		}

		foreach ( $matches[1] as $json_str ) {
			$json_str = trim( $json_str );
			if ( empty( $json_str ) ) {
				continue;
			}

			$data = @json_decode( $json_str, true );
			if ( empty( $data ) || ! is_array( $data ) ) {
				continue;
			}

			self::extract_products_from_json_node( $data, $products, $origin );
		}

		return $products;
	}

	/**
	 * Recursively extract Product objects from Schema.org structure.
	 *
	 * @param array  $node
	 * @param array  &$products
	 * @param string $origin
	 */
	private static function extract_products_from_json_node( $node, &$products, $origin ) {
		if ( ! is_array( $node ) ) {
			return;
		}

		// Handle @graph collections
		if ( isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
			foreach ( $node['@graph'] as $sub_node ) {
				self::extract_products_from_json_node( $sub_node, $products, $origin );
			}
			return;
		}

		// Handle ItemList / Carousel of products
		if ( isset( $node['@type'] ) && ( $node['@type'] === 'ItemList' || $node['@type'] === 'OfferCatalog' ) && isset( $node['itemListElement'] ) ) {
			foreach ( $node['itemListElement'] as $item_wrapper ) {
				if ( isset( $item_wrapper['item'] ) ) {
					self::extract_products_from_json_node( $item_wrapper['item'], $products, $origin );
				} else {
					self::extract_products_from_json_node( $item_wrapper, $products, $origin );
				}
			}
			return;
		}

		// Handle direct array of nodes
		if ( isset( $node[0] ) ) {
			foreach ( $node as $sub ) {
				self::extract_products_from_json_node( $sub, $products, $origin );
			}
			return;
		}

		$type = isset( $node['@type'] ) ? $node['@type'] : '';
		if ( $type === 'Product' || ( is_array( $type ) && in_array( 'Product', $type, true ) ) ) {
			$name = isset( $node['name'] ) ? html_entity_decode( trim( (string) $node['name'] ), ENT_QUOTES, 'UTF-8' ) : '';
			if ( empty( $name ) ) {
				return;
			}

			$price    = 0.0;
			$currency = 'EUR';

			if ( isset( $node['offers'] ) ) {
				$offers = $node['offers'];
				if ( isset( $offers['price'] ) ) {
					$price    = (float) $offers['price'];
					$currency = isset( $offers['priceCurrency'] ) ? strtoupper( $offers['priceCurrency'] ) : 'EUR';
				} elseif ( isset( $offers['lowPrice'] ) ) {
					$price    = (float) $offers['lowPrice'];
					$currency = isset( $offers['priceCurrency'] ) ? strtoupper( $offers['priceCurrency'] ) : 'EUR';
				} elseif ( isset( $offers[0]['price'] ) ) {
					$price    = (float) $offers[0]['price'];
					$currency = isset( $offers[0]['priceCurrency'] ) ? strtoupper( $offers[0]['priceCurrency'] ) : 'EUR';
				}
			}

			if ( $price > 0 ) {
				$products[] = array(
					'id'          => crc32( $name . '_' . $price ),
					'name'        => $name,
					'sku'         => isset( $node['sku'] ) ? (string) $node['sku'] : '',
					'price'       => round( $price, 2 ),
					'price_cents' => (int) round( $price * 100 ),
					'currency'    => $currency,
					'link'        => isset( $node['url'] ) ? $node['url'] : '',
				);
			}
		}
	}

	/**
	 * Parse HTML DOM by searching for product cards and prices.
	 *
	 * @param string $html
	 * @param string $origin
	 * @return array
	 */
	public static function parse_html_dom( $html, $origin ) {
		$products = array();

		$dom = new DOMDocument();
		@$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
		$xpath = new DOMXPath( $dom );

		// Common product container queries
		$query = "//*[contains(concat(' ', normalize-space(@class), ' '), ' product ') or contains(@class, 'product-item') or contains(@class, 'product-card') or contains(@class, 'type-product')]";
		$nodes = $xpath->query( $query );

		if ( $nodes->length === 0 ) {
			// Fallback to li items or cards
			$query = "//li[.//span[contains(@class, 'price')] or .//div[contains(@class, 'price')]]";
			$nodes = $xpath->query( $query );
		}

		foreach ( $nodes as $node ) {
			// Extract title
			$title_nodes = $xpath->query( ".//h2|.//h3|.//h4|.//a[contains(@class, 'title') or contains(@class, 'name')]|.//*[contains(@class, 'woocommerce-loop-product__title')]", $node );
			$name = '';
			if ( $title_nodes && $title_nodes->length > 0 ) {
				$name = trim( $title_nodes->item(0)->textContent );
			}

			// Extract link
			$link_nodes = $xpath->query( ".//a[@href]", $node );
			$link = '';
			if ( $link_nodes->length > 0 ) {
				$raw_href = $link_nodes->item(0)->getAttribute( 'href' );
				if ( strpos( $raw_href, 'http' ) === 0 ) {
					$link = $raw_href;
				} else {
					$link = rtrim( $origin, '/' ) . '/' . ltrim( $raw_href, '/' );
				}
			}

			// Extract price text
			$price_nodes = $xpath->query( ".//*[contains(@class, 'price') or contains(@class, 'amount') or contains(@itemprop, 'price')]", $node );
			$price_str   = '';
			if ( $price_nodes->length > 0 ) {
				// Use the last text (sale price if discounted)
				$price_str = trim( $price_nodes->item( $price_nodes->length - 1 )->textContent );
			} else {
				$price_str = trim( $node->textContent );
			}

			$parsed_price = self::extract_price_number( $price_str );
			if ( ! empty( $name ) && $parsed_price['amount'] > 0 ) {
				$name = preg_replace( '/\s+/', ' ', $name );
				// Clean name from trailing prices
				$name = preg_replace( '/[\d,.]+\s*(?:€|£|\$|EUR|GBP|USD).*/i', '', $name );

				$products[] = array(
					'id'          => crc32( $name . '_' . $parsed_price['amount'] ),
					'name'        => trim( $name ),
					'sku'         => '',
					'price'       => round( $parsed_price['amount'], 2 ),
					'price_cents' => (int) round( $parsed_price['amount'] * 100 ),
					'currency'    => $parsed_price['currency'],
					'link'        => $link,
				);
			}
		}

		return $products;
	}

	/**
	 * Extract numerical price and currency code from string.
	 *
	 * @param string $str
	 * @return array
	 */
	public static function extract_price_number( $str ) {
		$currency = 'EUR';
		if ( stripos( $str, '£' ) !== false || stripos( $str, 'GBP' ) !== false ) {
			$currency = 'GBP';
		} elseif ( stripos( $str, '$' ) !== false || stripos( $str, 'USD' ) !== false ) {
			$currency = 'USD';
		}

		// Match price numbers: e.g. 19.99, 119,50, 1 250.00
		if ( preg_match( '/(?:€|£|\$|EUR|GBP|USD)?\s*([0-9\s]+(?:[.,][0-9]{2})?)\s*(?:€|£|\$|EUR|GBP|USD)?/i', $str, $m ) ) {
			$num_str = trim( $m[1] );
			$num_str = str_replace( array( ' ', "\xC2\xA0" ), '', $num_str );
			$num_str = str_replace( ',', '.', $num_str );

			// If multiple dots, fix thousand separators
			if ( substr_count( $num_str, '.' ) > 1 ) {
				$parts = explode( '.', $num_str );
				$last  = array_pop( $parts );
				$num_str = implode( '', $parts ) . '.' . $last;
			}

			$amount = (float) $num_str;
			if ( $amount > 0 && $amount < 50000 ) {
				return array( 'amount' => $amount, 'currency' => $currency );
			}
		}

		return array( 'amount' => 0.0, 'currency' => $currency );
	}

	/**
	 * Detect majority currency from scraped products.
	 *
	 * @param array $products
	 * @return string
	 */
	private static function detect_currency_from_products( $products ) {
		if ( empty( $products ) ) {
			return 'EUR';
		}
		$counts = array();
		foreach ( $products as $p ) {
			$c = ! empty( $p['currency'] ) ? $p['currency'] : 'EUR';
			$counts[ $c ] = isset( $counts[ $c ] ) ? $counts[ $c ] + 1 : 1;
		}
		arsort( $counts );
		return key( $counts );
	}

	/**
	 * Deduplicate products array by normalized title.
	 *
	 * @param array $products
	 * @return array
	 */
	private static function deduplicate_products( $products ) {
		$seen   = array();
		$unique = array();

		foreach ( $products as $p ) {
			$key = mb_strtolower( trim( $p['name'] ), 'UTF-8' );
			if ( ! empty( $key ) && ! isset( $seen[ $key ] ) && $p['price'] > 0 ) {
				$seen[ $key ] = true;
				$unique[]     = $p;
			}
		}

		return array_values( $unique );
	}

	/**
	 * Perform HTTP request with browser-like headers and multi-currency cookies.
	 *
	 * @param string $url
	 * @param string $target_currency
	 * @return string|false
	 */
	public static function fetch_http( $url, $target_currency = 'EUR' ) {
		$target_currency = strtoupper( trim( $target_currency ?: 'EUR' ) );
		$cookie_str      = "wmc_current_currency={$target_currency}; woocommerce_current_currency={$target_currency}; woocs_current_currency={$target_currency}; aelia_customer_currency={$target_currency}; currency={$target_currency};";

		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init();
			curl_setopt_array( $ch, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_CONNECTTIMEOUT => 7,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_COOKIE         => $cookie_str,
				CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
				CURLOPT_HTTPHEADER     => array(
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/json,*/*;q=0.8',
					'Accept-Language: en-US,en;q=0.9,ru;q=0.8',
					'X-WC-Currency: ' . $target_currency,
					'Cache-Control: no-cache',
				),
			) );
			$response = curl_exec( $ch );
			$status   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			if ( $status >= 200 && $status < 400 && ! empty( $response ) ) {
				return $response;
			}
		}

		// Fallback
		$opts = array(
			'http' => array(
				'method'  => 'GET',
				'timeout' => 15,
				'header'  => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)\r\nAccept: text/html,application/xhtml+xml,application/json\r\nCookie: {$cookie_str}\r\nX-WC-Currency: {$target_currency}\r\n",
			),
			'ssl' => array(
				'verify_peer'      => false,
				'verify_peer_name' => false,
			),
		);
		$context = stream_context_create( $opts );
		return @file_get_contents( $url, false, $context );
	}
}
