<?php
/**
 * Discogs Importer Admin Class.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Discogs_Importer_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register Admin Menu.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		// Register Settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Enqueue Assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register Admin Menu Page.
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Discogs Importer', 'discogs-importer' ),
			__( 'Discogs Importer', 'discogs-importer' ),
			'manage_options',
			'discogs-importer',
			array( $this, 'render_admin_page' ),
			'dashicons-format-audio',
			30
		);
	}

	/**
	 * Register Plugin Settings.
	 */
	public function register_settings() {
		register_setting( 'discogs_importer_settings_group', 'discogs_importer_token' );
		register_setting( 'discogs_importer_settings_group', 'discogs_importer_post_status' );
		register_setting( 'discogs_importer_settings_group', 'discogs_importer_download_images' );
		register_setting( 'discogs_importer_settings_group', 'discogs_importer_default_price' );
		register_setting( 'discogs_importer_settings_group', 'discogs_importer_import_type' );

		// Set default values if not exists.
		if ( false === get_option( 'discogs_importer_post_status' ) ) {
			update_option( 'discogs_importer_post_status', 'draft' );
		}
		if ( false === get_option( 'discogs_importer_download_images' ) ) {
			update_option( 'discogs_importer_download_images', '1' );
		}
		if ( false === get_option( 'discogs_importer_import_type' ) ) {
			$default_type = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'cpt';
			update_option( 'discogs_importer_import_type', $default_type );
		}
	}

	/**
	 * Enqueue Styles and Scripts.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_discogs-importer' !== $hook ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'discogs-importer-admin-css',
			DISCOGS_IMPORTER_URL . 'admin/css/discogs-importer-admin.css',
			array(),
			DISCOGS_IMPORTER_VERSION
		);

		// Enqueue Google Font (Inter) for premium aesthetics.
		wp_enqueue_style(
			'discogs-importer-google-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
			array(),
			null
		);

		// Enqueue JS.
		wp_enqueue_script(
			'discogs-importer-admin-js',
			DISCOGS_IMPORTER_URL . 'admin/js/discogs-importer-admin.js',
			array( 'jquery' ),
			DISCOGS_IMPORTER_VERSION,
			true
		);

		// Localize Script for AJAX.
		wp_localize_script(
			'discogs-importer-admin-js',
			'discogsImporter',
			array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'discogs_importer_nonce' ),
				'is_woocommerce_active' => class_exists( 'WooCommerce' ) ? 1 : 0,
				'text_importing'      => __( 'Importing...', 'discogs-importer' ),
				'text_imported'       => __( 'Imported', 'discogs-importer' ),
				'text_import_failed'  => __( 'Import Failed', 'discogs-importer' ),
			)
		);
	}

	/**
	 * Render the Admin Page Dashboard.
	 */
	public function render_admin_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'discogs-importer' ) );
		}

		$token           = get_option( 'discogs_importer_token', '' );
		$post_status     = get_option( 'discogs_importer_post_status', 'draft' );
		$download_images = get_option( 'discogs_importer_download_images', '1' );
		$default_price   = get_option( 'discogs_importer_default_price', '' );
		$import_type     = get_option( 'discogs_importer_import_type', 'cpt' );
		
		$is_wc_active    = class_exists( 'WooCommerce' );
		?>
		<div class="wrap discogs-importer-wrap">
			<!-- Header -->
			<header class="discogs-header">
				<div class="header-logo">
					<span class="logo-icon">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="10"></circle>
							<circle cx="12" cy="12" r="3"></circle>
						</svg>
					</span>
					<div>
						<h1><?php esc_html_e( 'Discogs Record Importer', 'discogs-importer' ); ?></h1>
						<p class="description"><?php esc_html_e( 'Search and import music records seamlessly into WordPress or WooCommerce.', 'discogs-importer' ); ?></p>
					</div>
				</div>
			</header>

			<!-- Tabs Navigation -->
			<h2 class="nav-tab-wrapper discogs-tabs-nav">
				<a href="#tab-search" class="nav-tab nav-tab-active" data-tab="search"><?php esc_html_e( 'Search & Import', 'discogs-importer' ); ?></a>
				<a href="#tab-settings" class="nav-tab" data-tab="settings"><?php esc_html_e( 'Settings', 'discogs-importer' ); ?></a>
			</h2>

			<!-- Tab Content: Search -->
			<div id="discogs-tab-search" class="discogs-tab-content tab-active">
				
				<?php if ( empty( $token ) ) : ?>
					<div class="discogs-alert discogs-alert-warning">
						<p>
							<strong><?php esc_html_e( 'API Token Required!', 'discogs-importer' ); ?></strong>
							<?php esc_html_e( 'Please go to the Settings tab and add your Discogs Personal Access Token to enable searching and importing.', 'discogs-importer' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<!-- Search Panel -->
				<div class="discogs-search-panel">
					<form id="discogs-search-form" class="discogs-search-form">
						<div class="search-row">
							<div class="search-input-group">
								<label for="discogs-search-query"><?php esc_html_e( 'Search Query', 'discogs-importer' ); ?></label>
								<input type="text" id="discogs-search-query" name="q" placeholder="<?php esc_html_e( 'Enter artist, album title, keywords...', 'discogs-importer' ); ?>" <?php disabled( empty( $token ) ); ?> />
							</div>
							
							<div class="search-select-group">
								<label for="discogs-search-field"><?php esc_html_e( 'Search Field', 'discogs-importer' ); ?></label>
								<select id="discogs-search-field" name="search_field" <?php disabled( empty( $token ) ); ?>>
									<option value="q"><?php esc_html_e( 'All Fields (Default)', 'discogs-importer' ); ?></option>
									<option value="artist"><?php esc_html_e( 'Artist', 'discogs-importer' ); ?></option>
									<option value="title"><?php esc_html_e( 'Album / Release Title', 'discogs-importer' ); ?></option>
									<option value="barcode"><?php esc_html_e( 'Barcode', 'discogs-importer' ); ?></option>
									<option value="catno"><?php esc_html_e( 'Catalog Number', 'discogs-importer' ); ?></option>
									<option value="release_id"><?php esc_html_e( 'Discogs Release ID', 'discogs-importer' ); ?></option>
								</select>
							</div>
						</div>

						<div class="search-row search-actions-row">
							<button type="submit" class="button button-primary discogs-btn" <?php disabled( empty( $token ) ); ?>>
								<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Search Discogs', 'discogs-importer' ); ?>
							</button>
							<button type="button" id="discogs-clear-search" class="button discogs-btn-secondary" style="display: none;">
								<?php esc_html_e( 'Clear Results', 'discogs-importer' ); ?>
							</button>
						</div>
					</form>
				</div>

				<!-- Live Progress Info -->
				<div id="discogs-import-progress-container" class="discogs-import-progress-container" style="display: none;">
					<div class="progress-details">
						<span id="discogs-progress-status"><?php esc_html_e( 'Importing release...', 'discogs-importer' ); ?></span>
						<span id="discogs-progress-percent">0%</span>
					</div>
					<div class="progress-bar-bg">
						<div id="discogs-progress-bar" class="progress-bar-fill"></div>
					</div>
				</div>

				<!-- Results Container -->
				<div class="discogs-results-header" style="display: none;">
					<h3 id="discogs-results-title"><?php esc_html_e( 'Search Results', 'discogs-importer' ); ?></h3>
					<span id="discogs-results-count"></span>
				</div>
				
				<div id="discogs-search-results" class="discogs-results-grid">
					<!-- Dynamically Populated -->
				</div>

				<!-- Pagination Container -->
				<div id="discogs-pagination" class="discogs-pagination" style="display: none;">
					<!-- Dynamically Populated -->
				</div>
			</div>

			<!-- Tab Content: Settings -->
			<div id="discogs-tab-settings" class="discogs-tab-content">
				<div class="discogs-settings-card">
					<form method="post" action="options.php">
						<?php settings_fields( 'discogs_importer_settings_group' ); ?>
						
						<h3><?php esc_html_e( 'API Authentication', 'discogs-importer' ); ?></h3>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="discogs_importer_token"><?php esc_html_e( 'Discogs Personal Access Token', 'discogs-importer' ); ?></label></th>
								<td>
									<input type="password" id="discogs_importer_token" name="discogs_importer_token" value="<?php echo esc_attr( $token ); ?>" class="regular-text" />
									<p class="description">
										<?php esc_html_e( 'Create a Personal Access Token in your ', 'discogs-importer' ); ?>
										<a href="https://www.discogs.com/settings/developers" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Discogs Developer Settings', 'discogs-importer' ); ?></a>.
										<?php esc_html_e( 'This is required to access the Discogs search database.', 'discogs-importer' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<hr class="settings-divider" />

						<h3><?php esc_html_e( 'Import Configuration', 'discogs-importer' ); ?></h3>
						<table class="form-table">
							<tr>
								<th scope="row"><label for="discogs_importer_import_type"><?php esc_html_e( 'Import Destination', 'discogs-importer' ); ?></label></th>
								<td>
									<select id="discogs_importer_import_type" name="discogs_importer_import_type">
										<option value="cpt" <?php selected( $import_type, 'cpt' ); ?>><?php esc_html_e( 'Music Records Custom Post Type', 'discogs-importer' ); ?></option>
										<option value="woocommerce" <?php selected( $import_type, 'woocommerce' ); ?> <?php disabled( ! $is_wc_active ); ?>>
											<?php esc_html_e( 'WooCommerce Products', 'discogs-importer' ); ?>
											<?php if ( ! $is_wc_active ) : ?> (<?php esc_html_e( 'WooCommerce is not active', 'discogs-importer' ); ?>)<?php endif; ?>
										</option>
									</select>
									<p class="description">
										<?php esc_html_e( 'Choose whether to import releases as custom post type records or as WooCommerce products.', 'discogs-importer' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="discogs_importer_post_status"><?php esc_html_e( 'Default Post Status', 'discogs-importer' ); ?></label></th>
								<td>
									<select id="discogs_importer_post_status" name="discogs_importer_post_status">
										<option value="draft" <?php selected( $post_status, 'draft' ); ?>><?php esc_html_e( 'Draft (Recommended)', 'discogs-importer' ); ?></option>
										<option value="publish" <?php selected( $post_status, 'publish' ); ?>><?php esc_html_e( 'Published', 'discogs-importer' ); ?></option>
									</select>
									<p class="description">
										<?php esc_html_e( 'Default status of imported posts/products.', 'discogs-importer' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row"><label for="discogs_importer_download_images"><?php esc_html_e( 'Download Images', 'discogs-importer' ); ?></label></th>
								<td>
									<label>
										<input type="checkbox" id="discogs_importer_download_images" name="discogs_importer_download_images" value="1" <?php checked( $download_images, '1' ); ?> />
										<?php esc_html_e( 'Download Discogs cover images to local media library and set as featured image.', 'discogs-importer' ); ?>
									</label>
								</td>
							</tr>

							<tr class="woocommerce-only-row" style="<?php echo ( 'woocommerce' !== $import_type ) ? 'display: none;' : ''; ?>">
								<th scope="row"><label for="discogs_importer_default_price"><?php esc_html_e( 'Default Product Price ($)', 'discogs-importer' ); ?></label></th>
								<td>
									<input type="number" step="0.01" min="0" id="discogs_importer_default_price" name="discogs_importer_default_price" value="<?php echo esc_attr( $default_price ); ?>" class="small-text" />
									<p class="description">
										<?php esc_html_e( 'Default regular price assigned to imported WooCommerce products.', 'discogs-importer' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<?php submit_button( __( 'Save Importer Settings', 'discogs-importer' ), 'primary discogs-btn' ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
