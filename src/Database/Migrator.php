<?php
/**
 * Database schema migrator.
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages versioned database schema migrations using dbDelta().
 *
 * Tracks the current schema version in wp_options (shqf_db_version).
 * Each migration is a method named migrate_to_X() where X is the target version.
 * Migrations run sequentially from current version + 1 to latest.
 */
class Migrator {

	/**
	 * Option key for tracking the current database schema version.
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'shqf_db_version';

	/**
	 * The latest schema version. Increment when adding new migrations.
	 *
	 * @var int
	 */
	public const LATEST_VERSION = 3;

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Transient key used as an advisory lock to prevent concurrent migrations.
	 *
	 * @var string
	 */
	private const LOCK_KEY = 'shqf_migrating';

	/**
	 * Lock timeout in seconds (2 minutes — generous for slow hosts).
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 120;

	/**
	 * Run all pending migrations to reach the latest version.
	 *
	 * Uses an option-based advisory lock to prevent concurrent execution
	 * (e.g., simultaneous plugin activations in a cluster). The lock uses
	 * add_option() which is atomic (INSERT with UNIQUE constraint).
	 *
	 * @return void
	 */
	public function migrate_to_latest(): void {
		$current = $this->get_current_version();

		if ( $current >= self::LATEST_VERSION ) {
			return;
		}

		if ( ! $this->acquire_lock() ) {
			return;
		}

		try {
			// Re-check after acquiring lock (another process may have finished).
			$current = $this->get_current_version();

			for ( $version = $current + 1; $version <= self::LATEST_VERSION; $version++ ) {
				$method = 'migrate_to_' . $version;

				if ( method_exists( $this, $method ) ) {
					$this->$method();
					$this->set_current_version( $version );
				}
			}
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Acquire the migration advisory lock.
	 *
	 * Uses add_option() which fails atomically on duplicate key (INSERT with
	 * UNIQUE constraint on option_name). Falls back to timestamp-based expiry
	 * to recover from crashed processes that never released the lock.
	 *
	 * @return bool True if lock acquired, false if another process holds it.
	 */
	private function acquire_lock(): bool {
		$acquired = add_option( self::LOCK_KEY, time(), '', 'no' );

		if ( $acquired ) {
			return true;
		}

		// Lock exists — check if expired (stale from a crashed process).
		$lock_time = (int) get_option( self::LOCK_KEY );
		if ( $lock_time && ( time() - $lock_time ) > self::LOCK_TIMEOUT ) {
			update_option( self::LOCK_KEY, time() );
			return true;
		}

		return false;
	}

	/**
	 * Release the migration advisory lock.
	 *
	 * @return void
	 */
	private function release_lock(): void {
		delete_option( self::LOCK_KEY );
	}

	/**
	 * Get the current schema version from wp_options.
	 *
	 * @return int
	 */
	public function get_current_version(): int {
		return (int) get_option( self::VERSION_OPTION, 0 );
	}

	/**
	 * Set the current schema version in wp_options.
	 *
	 * @param int $version The version number.
	 * @return void
	 */
	private function set_current_version( int $version ): void {
		update_option( self::VERSION_OPTION, $version, true );
	}

	/**
	 * Drop all plugin tables and remove the version option.
	 *
	 * Called from uninstall.php when the plugin is deleted.
	 * Tables are dropped in reverse order (children before parents) to respect
	 * any future foreign key constraints.
	 *
	 * @return void
	 */
	public function drop_all_tables(): void {
		$tables = array_reverse( $this->get_table_names() );

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is from get_table_names() (hardcoded prefixed names).
			$this->wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Delete all plugin options from wp_options.
	 *
	 * Called from uninstall.php alongside drop_all_tables().
	 *
	 * @return void
	 */
	public function delete_all_options(): void {
		$options = [
			self::VERSION_OPTION,
			'shqf_collect_ip',
			'shqf_ip_retention_days',
			'shqf_turnstile_site_key',
			'shqf_turnstile_secret_key',
			'shqf_email_defaults',
			'shqf_connection',
			'shqf_woo_enabled',
			'shqf_woo_button_text',
			'shqf_woo_form_id',
			'shqf_woo_product_filter',
			'shqf_woo_sample_tag',
			'shqf_woo_sample_categories',
			'shqf_woo_max_quantity',
			'shqf_woo_show_loop_badge',
			'shqf_woo_badge_text',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Clean up advisory lock option (normally released, but belt-and-suspenders).
		delete_option( self::LOCK_KEY );

		// Clean up transients.
		delete_transient( 'shqf_unread_count' );

		// Clean up all plugin transients (shqf_token_*, shqf_woo_samples_*).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->options is a core table name, not user input.
			"DELETE FROM {$this->wpdb->options} WHERE option_name LIKE '_transient_shqf_%' OR option_name LIKE '_transient_timeout_shqf_%'"
		);

		// Clean up user meta (dismissed CTA + screen options).
		delete_metadata( 'user', 0, 'shqf_dismissed_cta', '', true );
		delete_metadata( 'user', 0, 'shqf_samples_per_page', '', true );
		delete_metadata( 'user', 0, 'shqf_forms_per_page', '', true );
		delete_metadata( 'user', 0, 'shqf_submissions_per_page', '', true );
	}

	/**
	 * Get all plugin table names with the correct prefix.
	 *
	 * @return string[]
	 */
	public function get_table_names(): array {
		$prefix = $this->wpdb->prefix . 'shqf_';

		return [
			$prefix . 'samples',
			$prefix . 'sample_categories',
			$prefix . 'sample_category_map',
			$prefix . 'sample_images',
			$prefix . 'forms',
			$prefix . 'submissions',
			$prefix . 'submission_meta',
			$prefix . 'rate_limits',
		];
	}

	/**
	 * Run dbDelta with the given SQL statements.
	 *
	 * @param string $sql The SQL CREATE TABLE statements.
	 * @return array<string, string> Results from dbDelta.
	 */
	protected function run_dbdelta( string $sql ): array {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		return dbDelta( $sql );
	}

	/**
	 * Migration to version 1: Create all initial tables.
	 *
	 * @return void
	 */
	private function migrate_to_1(): void {
		$charset_collate = $this->wpdb->get_charset_collate();
		$prefix          = $this->wpdb->prefix . 'shqf_';

		$sql = "
			CREATE TABLE {$prefix}samples (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				sku varchar(100) DEFAULT NULL,
				description text,
				max_quantity int(10) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'active',
				sort_order int(11) NOT NULL DEFAULT 0,
				shq_sample_id bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY sku (sku),
				KEY status (status),
				KEY shq_sample_id (shq_sample_id)
			) {$charset_collate};

			CREATE TABLE {$prefix}sample_categories (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				slug varchar(100) NOT NULL,
				description text,
				parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
				sort_order int(11) NOT NULL DEFAULT 0,
				shq_category_id bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY parent_id (parent_id)
			) {$charset_collate};

			CREATE TABLE {$prefix}sample_category_map (
				sample_id bigint(20) unsigned NOT NULL,
				category_id bigint(20) unsigned NOT NULL,
				PRIMARY KEY  (sample_id,category_id),
				KEY category_id (category_id)
			) {$charset_collate};

			CREATE TABLE {$prefix}sample_images (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				sample_id bigint(20) unsigned NOT NULL,
				attachment_id bigint(20) unsigned NOT NULL,
				sort_order int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY sample_id (sample_id)
			) {$charset_collate};

			CREATE TABLE {$prefix}forms (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) NOT NULL,
				slug varchar(100) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'draft',
				config mediumtext NOT NULL,
				email_config mediumtext,
				spam_config mediumtext,
				settings mediumtext,
				submissions_count int(10) unsigned NOT NULL DEFAULT 0,
				shq_form_id bigint(20) unsigned DEFAULT NULL,
				created_by bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY status (status)
			) {$charset_collate};

			CREATE TABLE {$prefix}submissions (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'new',
				email varchar(255) DEFAULT NULL,
				first_name varchar(100) DEFAULT NULL,
				last_name varchar(100) DEFAULT NULL,
				source_url varchar(500) DEFAULT NULL,
				ip_address varchar(45) DEFAULT NULL,
				user_agent varchar(500) DEFAULT NULL,
				is_starred tinyint(1) NOT NULL DEFAULT 0,
				is_read tinyint(1) NOT NULL DEFAULT 0,
				synced_to_shq tinyint(1) NOT NULL DEFAULT 0,
				shq_request_id bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY form_id (form_id),
				KEY status (status),
				KEY email (email),
				KEY created_at (created_at),
				KEY synced_to_shq (synced_to_shq)
			) {$charset_collate};

			CREATE TABLE {$prefix}submission_meta (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				submission_id bigint(20) unsigned NOT NULL,
				field_key varchar(100) NOT NULL,
				field_value longtext,
				field_value_short varchar(191) DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY submission_field (submission_id,field_key),
				KEY field_value_short (field_value_short)
			) {$charset_collate};

			CREATE TABLE {$prefix}rate_limits (
				ip_address varchar(45) NOT NULL,
				form_id bigint(20) unsigned NOT NULL,
				count int(10) unsigned NOT NULL DEFAULT 1,
				window_start datetime NOT NULL,
				PRIMARY KEY  (ip_address,form_id)
			) {$charset_collate};
		";

		$this->run_dbdelta( $sql );
	}

	/**
	 * Migration to version 2: Add custom_fields column to samples table.
	 *
	 * @return void
	 */
	private function migrate_to_2(): void {
		$table = $this->wpdb->prefix . 'shqf_samples';

		// Guard: only add column if it doesn't already exist (idempotent).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Table name is from $wpdb->prefix, not user input.
		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				$this->wpdb->dbname, // @phpstan-ignore property.protected
				$table,
				'custom_fields'
			)
		);

		if ( ! $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->query( "ALTER TABLE {$table} ADD COLUMN custom_fields mediumtext DEFAULT NULL AFTER description" );
		}
		// phpcs:enable
	}

	/**
	 * Migration to version 3: Add composite index on (status, is_read) to submissions.
	 *
	 * Covers the unread-count query: WHERE status = 'new' AND is_read = 0.
	 *
	 * @return void
	 */
	private function migrate_to_3(): void {
		$table = $this->wpdb->prefix . 'shqf_submissions';

		// Guard: only add index if it doesn't already exist (idempotent).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s',
				$this->wpdb->dbname, // @phpstan-ignore property.protected
				$table,
				'idx_status_is_read'
			)
		);

		if ( ! $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$this->wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_status_is_read (status, is_read)" );
		}
		// phpcs:enable
	}
}
