<?php
/**
 * Plugin bootstrap.
 *
 * @package SampleHQForm
 */

declare( strict_types=1 );

namespace SampleHQForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * Boots the plugin via a static factory method. Not a singleton -- returns a new
 * instance on first boot. Subsequent calls return the same instance (boot guard).
 * The instance is stored in $GLOBALS['samplehq_request_form'] by the main plugin file.
 */
class Plugin {

	/**
	 * Whether the plugin has been booted.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * The booted instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * @var Fields\FieldRegistry
	 */
	private Fields\FieldRegistry $field_registry;

	/**
	 * @var Database\SamplesTable
	 */
	private Database\SamplesTable $samples;

	/**
	 * @var Database\SampleImagesTable
	 */
	private Database\SampleImagesTable $images;

	/**
	 * @var Database\SampleCategoryMapTable
	 */
	private Database\SampleCategoryMapTable $category_map;

	/**
	 * @var Database\SampleCategoriesTable
	 */
	private Database\SampleCategoriesTable $categories;

	/**
	 * @var Database\FormsTable
	 */
	private Database\FormsTable $forms;

	/**
	 * @var Spam\FormToken
	 */
	private Spam\FormToken $form_token;

	/**
	 * @var Forms\FormRenderer
	 */
	private Forms\FormRenderer $renderer;

	/**
	 * @var Database\RateLimitsTable
	 */
	private Database\RateLimitsTable $rate_limits;

	/**
	 * @var Database\SubmissionsTable
	 */
	private Database\SubmissionsTable $submissions;

	/**
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * @param \wpdb $wpdb WordPress database abstraction.
	 */
	private function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Boot the plugin.
	 *
	 * Returns the existing instance if already booted.
	 *
	 * @return self
	 */
	public static function boot(): self {
		if ( self::$booted && self::$instance instanceof self ) {
			return self::$instance;
		}

		self::$instance = new self( $GLOBALS['wpdb'] );
		self::$instance->register_hooks();
		self::$booted = true;

		return self::$instance;
	}

	/**
	 * Register all plugin hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		register_activation_hook( SHQF_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( SHQF_FILE, [ $this, 'deactivate' ] );

		add_action( 'init', [ $this, 'load_textdomain' ] );

		// WooCommerce HPOS + Blocks compatibility declarations.
		add_action( 'before_woocommerce_init', [ WooCommerce\WooDetector::class, 'declare_compatibility' ] );

		// Run pending migrations on upgrade (activation hook only fires on manual activate).
		$this->maybe_upgrade();

		// --- Database repositories ---
		$forms_table           = new Database\FormsTable( $this->wpdb );
		$samples_table         = new Database\SamplesTable( $this->wpdb );
		$categories            = new Database\SampleCategoriesTable( $this->wpdb );
		$category_map          = new Database\SampleCategoryMapTable( $this->wpdb );
		$images_table          = new Database\SampleImagesTable( $this->wpdb );
		$submissions           = new Database\SubmissionsTable( $this->wpdb );
		$submission_meta       = new Database\SubmissionMetaTable( $this->wpdb );
		$rate_limits           = new Database\RateLimitsTable( $this->wpdb );
		$this->forms           = $forms_table;
		$this->samples         = $samples_table;
		$this->categories      = $categories;
		$this->category_map    = $category_map;
		$this->images          = $images_table;
		$this->submissions     = $submissions;
		$this->rate_limits     = $rate_limits;

		// --- Field type registry ---
		$field_reg = new Fields\FieldRegistry();
		$field_reg->register( new Fields\TextField() );
		$field_reg->register( new Fields\EmailField() );
		$field_reg->register( new Fields\PhoneField() );
		$field_reg->register( new Fields\TextareaField() );
		$field_reg->register( new Fields\NumberField() );
		$field_reg->register( new Fields\SelectField() );
		$field_reg->register( new Fields\RadioField() );
		$field_reg->register( new Fields\CheckboxField() );
		$field_reg->register( new Fields\NameField() );
		$field_reg->register( new Fields\AddressField() );
		$field_reg->register( new Fields\DateField() );
		$field_reg->register( new Fields\UrlField() );
		$field_reg->register( new Fields\HiddenField() );
		$field_reg->register( new Fields\HtmlField() );
		$field_reg->register( new Fields\ConsentField() );
		$field_reg->register( new Fields\FileUploadField() );
		// --- WooCommerce integration ---
		// SamplePickerField is registered immediately with null woo_source.
		// If WooCommerce is active, we replace it on plugins_loaded (after WC loads).
		$field_reg->register( new Fields\SamplePickerField( $samples_table, $images_table, $category_map, $categories ) );

		// --- Form rendering + validation ---
		$renderer              = new Forms\FormRenderer( $field_reg );
		$validator             = new Forms\FormValidator( $field_reg );
		$form_token            = new Spam\FormToken();
		$this->field_registry  = $field_reg;
		$this->renderer        = $renderer;
		$this->form_token      = $form_token;

		// Defer WC wiring to plugins_loaded so WooCommerce class is available.
		// Our plugin may load before WooCommerce in the active_plugins array.
		add_action( 'plugins_loaded', [ $this, 'wire_woocommerce' ] );
		$honeypot   = new Spam\HoneypotValidator();
		$mailer     = new Email\Mailer( $submission_meta );
		$turnstile  = new Spam\TurnstileVerifier();

		// --- Form processor (submission pipeline) ---
		$processor = new Forms\FormProcessor(
			$forms_table,
			$submissions,
			$submission_meta,
			$rate_limits,
			$validator,
			$form_token,
			$honeypot,
			$mailer,
			$turnstile
		);

		// --- REST API endpoints ---
		$public_api = new Api\PublicEndpoints( $processor );
		$public_api->register();

		$admin_samples_api = new Api\AdminSamplesEndpoints( $samples_table, $categories, $category_map, $images_table );
		$admin_samples_api->register();

		$admin_categories_api = new Api\AdminCategoriesEndpoints( $categories );
		$admin_categories_api->register();

		$admin_forms_api = new Api\AdminFormsEndpoints( $forms_table );
		$admin_forms_api->register();

		$admin_submissions_api = new Api\AdminSubmissionsEndpoints( $submissions, $submission_meta );
		$admin_submissions_api->register();

		$upload_api = new Api\UploadEndpoint( $forms_table, $form_token, $rate_limits );
		$upload_api->register();

		// --- Admin menu ---
		$admin_menu = new Admin\AdminMenu( $renderer, $form_token );
		$admin_menu->register();

		// --- Admin notices: security health checks ---
		add_action( 'admin_notices', [ $this, 'render_turnstile_failure_notice' ] );
		add_action( 'admin_notices', [ $this, 'render_upload_protection_notice' ] );

		// --- Privacy API (GDPR export + erase) ---
		$privacy = new Privacy\PrivacyHandler( $submissions, $submission_meta, $rate_limits );
		$privacy->register();

		// --- Shortcode ---
		$shortcode = new Shortcode( $forms_table, $renderer, $form_token );
		$shortcode->register();

		// --- Gutenberg block ---
		$block = new Blocks\FormBlock( $forms_table, $renderer, $form_token );
		$block->register();

		// --- Elementor widget (conditional) ---
		add_action( 'elementor/widgets/register', [ self::class, 'register_elementor_widget' ] );

		// --- Upload protection one-shot check (fires post-activation) ---
		add_action( 'shqf_check_upload_protection', [ self::class, 'check_upload_protection' ] );

		// --- Daily cleanup cron ---
		add_action( 'shqf_daily_cleanup', [ $this, 'run_daily_cleanup' ] );
	}

	/**
	 * Check if the database schema needs upgrading.
	 *
	 * Runs on every load (cheap int comparison). Only triggers migrations
	 * when the stored version is behind the code version.
	 *
	 * @return void
	 */
	private function maybe_upgrade(): void {
		$stored = (int) get_option( Database\Migrator::VERSION_OPTION, 0 );
		if ( $stored >= Database\Migrator::LATEST_VERSION ) {
			return;
		}

		$migrator = new Database\Migrator( $this->wpdb );
		$migrator->migrate_to_latest();
	}

	/**
	 * Plugin activation callback.
	 *
	 * Creates or updates database tables via the Migrator.
	 *
	 * @return void
	 */
	public function activate(): void {
		$migrator = new Database\Migrator( $this->wpdb );
		$migrator->migrate_to_latest();

		if ( ! wp_next_scheduled( 'shqf_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'shqf_daily_cleanup' );
		}

		// Schedule upload protection check (non-blocking, runs async via cron).
		wp_schedule_single_event( time() + 10, 'shqf_check_upload_protection' );
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'samplehq-request-form', false, dirname( plugin_basename( SHQF_FILE ) ) . '/languages' );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$timestamp = wp_next_scheduled( 'shqf_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'shqf_daily_cleanup' );
		}
	}

	/**
	 * Wire WooCommerce integration if active and enabled.
	 *
	 * Hooked to `plugins_loaded` so WooCommerce classes are available.
	 *
	 * @return void
	 */
	public function wire_woocommerce(): void {
		if ( ! WooCommerce\WooDetector::is_active() || ! get_option( 'shqf_woo_enabled' ) ) {
			return;
		}

		$woo_source = new Fields\ProductSource\WooCommerceSource();

		$this->field_registry->register( new Fields\SamplePickerField(
			$this->samples,
			$this->images,
			$this->category_map,
			$this->categories,
			$woo_source
		) );

		$cache_clear = [ Fields\ProductSource\WooCommerceSource::class, 'clear_cache' ];
		add_action( 'woocommerce_new_product', $cache_clear );
		add_action( 'woocommerce_update_product', $cache_clear );
		add_action( 'woocommerce_delete_product', $cache_clear );
		add_action( 'woocommerce_trash_product', $cache_clear );
		add_action( 'woocommerce_product_import_inserted_product_object', $cache_clear );
		add_action( 'edited_product_cat', $cache_clear );
		add_action( 'edited_product_tag', $cache_clear );

		$woo_button = new WooCommerce\ProductButton();
		$woo_button->register();

		$woo_assets = new WooCommerce\ProductPageAssets( $this->forms, $this->renderer, $this->form_token );
		$woo_assets->register();
	}

	/**
	 * Register the Elementor form widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function register_elementor_widget( $widgets_manager ): void {
		$widgets_manager->register( new Elementor\FormWidget() );
	}

	/**
	 * Run the upload protection health check.
	 *
	 * @return void
	 */
	public static function check_upload_protection(): void {
		( new Admin\UploadProtectionCheck() )->run();
	}

	/**
	 * Daily cleanup cron callback.
	 *
	 * @return void
	 */
	public function run_daily_cleanup(): void {
		( new Admin\UploadProtectionCheck() )->run();

		$this->rate_limits->cleanup();

		$retention_days = (int) get_option( 'shqf_ip_retention_days', 90 );
		if ( get_option( 'shqf_collect_ip' ) ) {
			$this->submissions->purge_old_ip_data( $retention_days );
		}

		$cutoff      = time() - DAY_IN_SECONDS;
		$pending_ids = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_key'       => '_shqf_pending',
			'meta_value'     => (string) $cutoff,
			'meta_compare'   => '<',
			'meta_type'      => 'NUMERIC',
			'fields'         => 'ids',
			'posts_per_page' => 50,
		] );

		foreach ( $pending_ids as $attach_id ) {
			wp_delete_attachment( $attach_id, true );
		}
	}

	/**
	 * Render admin notice if Turnstile API has recently failed.
	 *
	 * @return void
	 */
	public function render_turnstile_failure_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$failure = Spam\TurnstileVerifier::get_recent_failure();
		if ( false === $failure ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo '<strong>' . esc_html__( 'SampleHQ Form:', 'samplehq-request-form' ) . '</strong> ';
		echo esc_html(
			sprintf(
				/* translators: %s: error reason */
				__( 'Turnstile CAPTCHA verification is currently failing (%s). Form submissions with CAPTCHA enabled will be blocked until the issue resolves.', 'samplehq-request-form' ),
				$failure
			)
		);
		echo '</p></div>';
	}

	/**
	 * Render admin notice if the upload directory is not protected.
	 *
	 * @return void
	 */
	public function render_upload_protection_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Admin\UploadProtectionCheck::is_exposed() ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'SampleHQ Form:', 'samplehq-request-form' ) . '</strong> ';
		echo esc_html__( 'The upload directory (wp-content/uploads/shqf/) is publicly accessible. Uploaded files are not protected. If you are using Nginx, add this to your server block:', 'samplehq-request-form' );
		echo ' <code>location ~* /wp-content/uploads/shqf/ { deny all; return 403; }</code>';
		echo '</p></div>';
	}

	/**
	 * Reset boot state. Only for use in tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$booted   = false;
		self::$instance = null;
	}
}
