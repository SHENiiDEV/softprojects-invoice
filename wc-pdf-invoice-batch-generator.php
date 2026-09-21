<?php
/**
 * Plugin Name: WooCommerce PDF Invoice Batch Generator
 * Plugin URI:  https://github.com/woocommerce/wc-pdf-invoice-batch-generator
 * Description: Пакетная генерация PDF-инвойсов для WooCommerce на основе CSV, Apple Numbers и Excel (.xlsx) с динамическим подбором товаров и дизайном в стиле клиентского email.
 * Version:     1.11.0
 * Author:      Antigravity
 * Text Domain: wc-pdf-invoice-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'WC_PIBG_VERSION', '1.11.0' );
define( 'WC_PIBG_PLUGIN_FILE', __FILE__ );
define( 'WC_PIBG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_PIBG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class.
 */
final class WC_PDF_Invoice_Batch_Generator {

	/**
	 * Single instance of the plugin.
	 *
	 * @var WC_PDF_Invoice_Batch_Generator
	 */
	private static $instance = null;

	/**
	 * Admin controller instance.
	 *
	 * @var WC_PIBG_Admin
	 */
	public $admin;

	/**
	 * Main instance getter.
	 *
	 * @return WC_PDF_Invoice_Batch_Generator
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-csv-parser.php';
		require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-numbers-parser.php';
		require_once WC_PIBG_PLUGIN_DIR . 'includes/SimpleXLSX.php';
		require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-xlsx-parser.php';
		require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-subset-sum.php';
		require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-pdf-generator.php';
		require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-zip-handler.php';
		require_once WC_PIBG_PLUGIN_DIR . 'includes/class-wc-pibg-admin.php';
	}

	/**
	 * Hook into WordPress lifecycle.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		register_activation_hook( WC_PIBG_PLUGIN_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Load plugin localization textdomain on init action.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wc-pdf-invoice-generator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Plugins loaded check.
	 */
	public function on_plugins_loaded() {
		if ( ! $this->check_dependencies() ) {
			return;
		}

		if ( is_admin() ) {
			$this->admin = new WC_PIBG_Admin();
		}
	}

	/**
	 * Check if WooCommerce and ZipArchive are present.
	 *
	 * @return bool
	 */
	public function check_dependencies() {
		$errors = array();

		if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
			$errors[] = __( 'WooCommerce должен быть установлен и активирован для работы плагина WooCommerce PDF Invoice Batch Generator.', 'wc-pdf-invoice-generator' );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			$errors[] = __( 'Для работы плагина требуется расширение PHP ZipArchive.', 'wc-pdf-invoice-generator' );
		}

		if ( ! empty( $errors ) ) {
			add_action( 'admin_notices', function () use ( $errors ) {
				foreach ( $errors as $error ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
				}
			} );
			return false;
		}

		return true;
	}

	/**
	 * Plugin activation hook.
	 */
	public function activate() {
		// Ensure upload directory exists and is secured
		$upload_dir = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array( 'basedir' => sys_get_temp_dir() );
		$temp_dir   = ( function_exists( 'trailingslashit' ) ? trailingslashit( $upload_dir['basedir'] ) : rtrim( $upload_dir['basedir'], '/\\' ) . '/' ) . 'wc-pibg-temp';

		if ( ! file_exists( $temp_dir ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				@wp_mkdir_p( $temp_dir );
			} else {
				@mkdir( $temp_dir, 0755, true );
			}
			// Add index.php and .htaccess to prevent directory listing
			@file_put_contents( $temp_dir . '/index.php', '<?php // Silence is golden' );
			@file_put_contents( $temp_dir . '/.htaccess', 'Deny from all' );
		}
	}
}

/**
 * Global function to instantiate plugin.
 */
function wc_pibg() {
	return WC_PDF_Invoice_Batch_Generator::instance();
}

wc_pibg();
