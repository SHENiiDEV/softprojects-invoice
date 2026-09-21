<?php
/**
 * Admin interface and request controller for WooCommerce PDF Invoice Batch Generator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_PIBG_Admin {

	/**
	 * Menu slug.
	 */
	const MENU_SLUG = 'wc-pdf-invoice-generator';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Register Tools sub-menu.
	 */
	public function register_admin_menu() {
		add_management_page(
			__( 'Генератор инвойсов', 'wc-pdf-invoice-generator' ),
			__( 'Генератор инвойсов', 'wc-pdf-invoice-generator' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin stylesheets and scripts.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tools_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wc-pibg-admin-css',
			WC_PIBG_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_PIBG_VERSION
		);

		wp_enqueue_script(
			'wc-pibg-admin-js',
			WC_PIBG_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WC_PIBG_VERSION,
			true
		);

		wp_localize_script(
			'wc-pibg-admin-js',
			'wcPibgVars',
			array(
				'selectAtLeastOne' => __( 'Пожалуйста, выберите хотя бы одну запись для генерации инвойсов.', 'wc-pdf-invoice-generator' ),
				'generatingText'   => __( 'Генерация PDF и сборка архива...', 'wc-pdf-invoice-generator' ),
			)
		);
	}

	/**
	 * Handle file upload and ZIP generation POST requests.
	 */
	public function handle_actions() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 1. Handle File Upload (CSV, Apple Numbers, or Excel XLSX)
		if ( isset( $_POST['wc_pibg_action'] ) && $_POST['wc_pibg_action'] === 'upload_csv' ) {
			check_admin_referer( 'wc_pibg_upload_nonce', 'wc_pibg_nonce' );

			if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
				wp_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'error' => 'empty_file' ), admin_url( 'tools.php' ) ) );
				exit;
			}

			$file = $_FILES['csv_file'];
			$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			$allowed_exts = array( 'csv', 'numbers', 'xlsx' );

			if ( ! in_array( $ext, $allowed_exts, true ) ) {
				wp_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'error' => 'invalid_type' ), admin_url( 'tools.php' ) ) );
				exit;
			}

			if ( $ext === 'xlsx' ) {
				$parsed = WC_PIBG_XLSX_Parser::parse( $file['tmp_name'] );
			} elseif ( $ext === 'numbers' ) {
				$parsed = WC_PIBG_Numbers_Parser::parse( $file['tmp_name'] );
			} else {
				$parsed = WC_PIBG_CSV_Parser::parse( $file['tmp_name'] );
			}

			if ( ! $parsed['success'] ) {
				$error_code = urlencode( $parsed['error'] );
				wp_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'custom_error' => $error_code ), admin_url( 'tools.php' ) ) );
				exit;
			}

			// Store parsed data in transient for 2 hours
			$session_id = 'wc_pibg_' . md5( uniqid( (string) wp_rand(), true ) );
			set_transient( $session_id, $parsed['data'], 2 * HOUR_IN_SECONDS );

			wp_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'session_id' => $session_id ), admin_url( 'tools.php' ) ) );
			exit;
		}

		// 2. Handle ZIP Generation & Download
		if ( isset( $_POST['wc_pibg_action'] ) && $_POST['wc_pibg_action'] === 'generate_zip' ) {
			check_admin_referer( 'wc_pibg_generate_nonce', 'wc_pibg_nonce' );

			$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';
			$records    = get_transient( $session_id );

			if ( ! $records || ! is_array( $records ) ) {
				wp_die( esc_html__( 'Сессия истекла или данные CSV устарели. Загрузите файл повторно.', 'wc-pdf-invoice-generator' ) );
			}

			$selected_ids = isset( $_POST['selected_rows'] ) ? array_map( 'intval', (array) $_POST['selected_rows'] ) : array();

			if ( empty( $selected_ids ) ) {
				wp_die( esc_html__( 'Не выбрано ни одной записи для генерации.', 'wc-pdf-invoice-generator' ) );
			}

			// Filter only selected records
			$selected_records = array();
			foreach ( $records as $row ) {
				if ( in_array( (int) $row['row_id'], $selected_ids, true ) ) {
					$selected_records[] = $row;
				}
			}

			$zip_result = WC_PIBG_Zip_Handler::build_zip( $selected_records );

			if ( is_wp_error( $zip_result ) ) {
				wp_die( esc_html( $zip_result->get_error_message() ) );
			}

			WC_PIBG_Zip_Handler::stream_and_exit( $zip_result );
		}
	}

	/**
	 * Render Admin page.
	 */
	public function render_page() {
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( $_GET['session_id'] ) : '';
		$records    = ! empty( $session_id ) ? get_transient( $session_id ) : false;

		$binary_path = WC_PIBG_PDF_Generator::get_binary_path();
		?>
		<div class="wrap wc-pibg-container">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-pdf" style="font-size:30px;width:30px;height:30px;vertical-align:middle;margin-right:8px;color:#96588a;"></span>
				<?php esc_html_e( 'Пакетный генератор PDF-инвойсов WooCommerce', 'wc-pdf-invoice-generator' ); ?>
			</h1>
			<hr class="wp-header-end">

			<?php $this->render_notices( $binary_path ); ?>

			<div class="wc-pibg-card">
				<?php if ( ! empty( $records ) && is_array( $records ) ) : ?>
					<?php $this->render_preview_screen( $records, $session_id ); ?>
				<?php else : ?>
					<?php $this->render_upload_screen(); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render system notices & error alerts.
	 *
	 * @param string|false $binary_path
	 */
	private function render_notices( $binary_path ) {
		if ( ! $binary_path ) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Внимание:', 'wc-pdf-invoice-generator' ); ?></strong>
					<?php esc_html_e( 'Утилита wkhtmltopdf не найдена на сервере! Для генерации PDF требуется установленный wkhtmltopdf в PATH (/usr/local/bin/wkhtmltopdf или /usr/bin/wkhtmltopdf).', 'wc-pdf-invoice-generator' ); ?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( $_GET['error'] );
			$msg   = ( $error === 'invalid_type' ) 
				? __( 'Разрешены только файлы CSV (.csv), Apple Numbers (.numbers) и Excel (.xlsx).', 'wc-pdf-invoice-generator' )
				: __( 'Файл не был загружен или пуст.', 'wc-pdf-invoice-generator' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		if ( isset( $_GET['custom_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['custom_error'] ) ) ) . '</p></div>';
		}
	}

	/**
	 * Render Upload Screen (State 1).
	 */
	private function render_upload_screen() {
		?>
		<div class="wc-pibg-upload-box">
			<div class="wc-pibg-upload-header">
				<h2><?php esc_html_e( 'Шаг 1: Загрузка файла транзакций (CSV, Apple Numbers или Excel XLSX)', 'wc-pdf-invoice-generator' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Загрузите CSV, Excel (.xlsx) или Apple Numbers (.numbers) для предварительного просмотра и пакетной генерации инвойсов. Поддерживаются разделители запятая (,) и точка с запятой (;), а также форматы сумм с запятой (100,50).', 'wc-pdf-invoice-generator' ); ?>
				</p>
			</div>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ); ?>" class="wc-pibg-form">
				<?php wp_nonce_field( 'wc_pibg_upload_nonce', 'wc_pibg_nonce' ); ?>
				<input type="hidden" name="wc_pibg_action" value="upload_csv">

				<div class="wc-pibg-dropzone">
					<span class="dashicons dashicons-upload"></span>
					<label for="csv_file_input" class="wc-pibg-file-label">
						<strong><?php esc_html_e( 'Выберите файл CSV, .numbers или .xlsx', 'wc-pdf-invoice-generator' ); ?></strong>
						<span><?php esc_html_e( 'или перетащите его сюда', 'wc-pdf-invoice-generator' ); ?></span>
					</label>
					<input type="file" name="csv_file" id="csv_file_input" accept=".csv,.numbers,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,application/vnd.apple.numbers,application/x-iwork-numbers-sffnumbers,application/vnd.ms-excel" required>
					<div id="file-chosen-name" class="file-chosen-name"></div>
				</div>

				<div class="wc-pibg-actions">
					<button type="submit" class="button button-primary button-hero">
						<span class="dashicons dashicons-arrow-right-alt" style="vertical-align:middle;margin-right:4px;"></span>
						<?php esc_html_e( 'Загрузить и просмотреть список', 'wc-pdf-invoice-generator' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Preview Table Screen (State 2).
	 *
	 * @param array  $records
	 * @param string $session_id
	 */
	private function render_preview_screen( $records, $session_id ) {
		$total_count = count( $records );
		$total_sum   = 0;
		foreach ( $records as $r ) {
			$total_sum += (float) $r['amount'];
		}
		?>
		<div class="wc-pibg-preview-header">
			<div>
				<h2><?php esc_html_e( 'Шаг 2: Выбор записей для генерации инвойсов', 'wc-pdf-invoice-generator' ); ?></h2>
				<p class="description">
					<?php 
					printf(
						/* translators: 1: total items, 2: total sum */
						esc_html__( 'Загружено записей: %1$d на общую сумму %2$s. Отметьте нужные строки и нажмите «Сгенерировать и скачать ZIP».', 'wc-pdf-invoice-generator' ),
						$total_count,
						'<strong>' . number_format( $total_sum, 2, '.', ' ' ) . '</strong>'
					); 
					?>
				</p>
			</div>
			<div>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ); ?>" class="button button-secondary">
					&larr; <?php esc_html_e( 'Загрузить другой файл', 'wc-pdf-invoice-generator' ); ?>
				</a>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=' . self::MENU_SLUG ) ); ?>" id="wc-pibg-generate-form">
			<?php wp_nonce_field( 'wc_pibg_generate_nonce', 'wc_pibg_nonce' ); ?>
			<input type="hidden" name="wc_pibg_action" value="generate_zip">
			<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>">

			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<button type="submit" class="button button-primary button-large" id="btn-generate-zip">
						<span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:4px;"></span>
						<?php esc_html_e( 'Сгенерировать и скачать ZIP', 'wc-pdf-invoice-generator' ); ?>
						(<span id="selected-count"><?php echo esc_html( $total_count ); ?></span>)
					</button>
				</div>
				<div class="tablenav-pages">
					<span class="displaying-num"><?php printf( _n( '%s запись', '%s записей', $total_count, 'wc-pdf-invoice-generator' ), number_format_i18n( $total_count ) ); ?></span>
				</div>
				<div class="clear"></div>
			</div>

			<table class="wp-list-table widefat fixed striped table-view-list wc-pibg-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all" checked>
						</td>
						<th class="manage-column column-id" style="width: 50px;">#</th>
						<th class="manage-column column-name"><strong><?php esc_html_e( 'Имя клиента', 'wc-pdf-invoice-generator' ); ?></strong></th>
						<th class="manage-column column-amount"><strong><?php esc_html_e( 'Сумма', 'wc-pdf-invoice-generator' ); ?></strong></th>
						<th class="manage-column column-currency" style="width: 80px;"><?php esc_html_e( 'Валюта', 'wc-pdf-invoice-generator' ); ?></th>
						<th class="manage-column column-email"><?php esc_html_e( 'Email', 'wc-pdf-invoice-generator' ); ?></th>
						<th class="manage-column column-account"><?php esc_html_e( 'Card Pan', 'wc-pdf-invoice-generator' ); ?></th>
						<th class="manage-column column-address"><?php esc_html_e( 'Адрес плательщика', 'wc-pdf-invoice-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $records as $row ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="selected_rows[]" class="row-checkbox" value="<?php echo esc_attr( $row['row_id'] ); ?>" checked>
							</th>
							<td><?php echo esc_html( $row['row_id'] ); ?></td>
							<td class="column-name">
								<strong><?php echo esc_html( $row['full_name'] ); ?></strong>
							</td>
							<td class="column-amount">
								<span class="amount-badge">
									<?php echo esc_html( number_format( (float) $row['amount'], 2, '.', ' ' ) ); ?>
								</span>
							</td>
							<td class="column-currency">
								<code><?php echo esc_html( $row['currency'] ); ?></code>
							</td>
							<td class="column-email">
								<?php echo ! empty( $row['email'] ) ? esc_html( $row['email'] ) : '<span class="na">&mdash;</span>'; ?>
							</td>
							<td class="column-account">
								<?php echo ! empty( $row['account_number'] ) ? esc_html( $row['account_number'] ) : '<span class="na">&mdash;</span>'; ?>
							</td>
							<td class="column-address">
								<?php 
									$addr_parts = array_filter( array( $row['address'], $row['city'], $row['postal_code'], $row['country'] ) );
									echo ! empty( $addr_parts ) ? esc_html( implode( ', ', $addr_parts ) ) : '<span class="na">&mdash;</span>';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all-foot" checked>
						</td>
						<th class="manage-column">#</th>
						<th class="manage-column"><?php esc_html_e( 'Имя клиента', 'wc-pdf-invoice-generator' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Сумма', 'wc-pdf-invoice-generator' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Валюта', 'wc-pdf-invoice-generator' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Email', 'wc-pdf-invoice-generator' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Card Pan', 'wc-pdf-invoice-generator' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Адрес плательщика', 'wc-pdf-invoice-generator' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<div class="tablenav bottom" style="margin-top: 15px;">
				<div class="alignleft actions bulkactions">
					<button type="submit" class="button button-primary button-large">
						<span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:4px;"></span>
						<?php esc_html_e( 'Сгенерировать и скачать ZIP', 'wc-pdf-invoice-generator' ); ?>
					</button>
				</div>
				<div class="clear"></div>
			</div>
		</form>

		<div id="wc-pibg-modal-overlay" class="wc-pibg-modal-overlay" style="display:none;">
			<div class="wc-pibg-modal">
				<div class="wc-pibg-spinner"></div>
				<h3><?php esc_html_e( 'Генерация инвойсов...', 'wc-pdf-invoice-generator' ); ?></h3>
				<p><?php esc_html_e( 'Пожалуйста, подождите. Создаются PDF документы и упаковываются в ZIP-архив.', 'wc-pdf-invoice-generator' ); ?></p>
			</div>
		</div>
		<?php
	}
}
