<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Loads Composer autoloader and sets up Brain\Monkey.
 * Does NOT load WordPress -- unit tests run with WP stubs only.
 *
 * @package SampleHQForm\Tests
 */

declare( strict_types=1 );

// Define ABSPATH before anything else (some autoloaded files check it).
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

// Define plugin constants for tests (mirrors the main plugin file).
// Keep version in sync with the plugin header.
if ( ! defined( 'SHQF_VERSION' ) ) {
	define( 'SHQF_VERSION', '1.0.1' );
}
if ( ! defined( 'SHQF_FILE' ) ) {
	define( 'SHQF_FILE', dirname( __DIR__ ) . '/samplehq-request-form.php' );
}
if ( ! defined( 'SHQF_DIR' ) ) {
	define( 'SHQF_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SHQF_URL' ) ) {
	define( 'SHQF_URL', 'https://example.com/wp-content/plugins/samplehq-request-form/' );
}

// WordPress constants used by $wpdb methods.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// WordPress time constants.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

// Composer autoloader (loads brain/monkey, mockery, and test class autoload-dev).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Minimal WP class stubs for unit tests (not available without full WP).

if ( ! class_exists( 'WP_List_Table' ) ) {
	// phpcs:ignore
	abstract class WP_List_Table {
		public array $items = [];
		protected array $_column_headers = [];
		protected array $_args = [];
		public function __construct( $args = [] ) { $this->_args = $args; }
		abstract public function get_columns();
		public function prepare_items() {}
		public function display() {}
		public function get_bulk_actions() { return []; }
		public function get_pagenum() { return 1; }
		public function set_pagination_args( $args ) {}
		public function single_row( $item ) {}
		protected function get_sortable_columns() { return []; }
		public function search_box( $text, $input_id ) {}
		public function row_actions( $actions, $always_visible = false ) {
			$out = '<div class="row-actions">';
			foreach ( $actions as $action => $link ) { $out .= "<span class='$action'>$link</span> "; }
			$out .= '</div>';
			return $out;
		}
		public function views() {}
	}
}

// Minimal wpdb stub for unit tests.
if ( ! class_exists( 'wpdb' ) ) {
	// phpcs:ignore
	class wpdb {
		public string $prefix = 'wp_';
		public ?string $last_error = null;
		public ?int $insert_id = null;
		public function prepare( $query, ...$args ) { return $query; }
		public function insert( $table, $data, $format = null ) { return 1; }
		public function update( $table, $data, $where, $format = null, $where_format = null ) { return 1; }
		public function delete( $table, $where, $where_format = null ) { return 1; }
		public function get_row( $query, $output = OBJECT, $y = 0 ) { return null; }
		public function get_results( $query, $output = OBJECT ) { return []; }
		public function get_var( $query = null, $x = 0, $y = 0 ) { return null; }
		public function query( $query ) { return true; }
		public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
	}
}

// Minimal WP REST class stubs for unit tests.
if ( ! class_exists( 'WP_REST_Response' ) ) {
	// phpcs:ignore
	class WP_REST_Response {
		private $data;
		private int $status;
		private array $headers = [];
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function get_data() { return $this->data; }
		public function get_status(): int { return $this->status; }
		public function header( string $key, string $value ): void { $this->headers[ $key ] = $value; }
		public function get_headers(): array { return $this->headers; }
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	// phpcs:ignore
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'PUT, PATCH';
		const DELETABLE = 'DELETE';
	}
}

// Minimal WP_Term stub for unit tests.
if ( ! class_exists( 'WP_Term' ) ) {
	// phpcs:ignore
	class WP_Term {
		public int $term_id;
		public string $name;
		public string $slug;
		public string $taxonomy;
		public int $parent = 0;
		public function __construct( array $data = [] ) {
			$this->term_id  = (int) ( $data['term_id'] ?? 0 );
			$this->name     = $data['name'] ?? '';
			$this->slug     = $data['slug'] ?? '';
			$this->taxonomy = $data['taxonomy'] ?? '';
			$this->parent   = (int) ( $data['parent'] ?? 0 );
		}
	}
}

// WooCommerce class stubs for unit tests.
if ( ! class_exists( 'WC_Product' ) ) {
	// phpcs:ignore
	class WC_Product {
		private array $data = [];
		public function __construct( array $data = [] ) { $this->data = $data; }
		public function get_id(): int { return (int) ( $this->data['id'] ?? 0 ); }
		public function get_name(): string { return $this->data['name'] ?? ''; }
		public function get_sku(): string { return $this->data['sku'] ?? ''; }
		public function get_short_description(): string { return $this->data['short_description'] ?? ''; }
		public function get_description(): string { return $this->data['description'] ?? ''; }
		public function get_image_id(): int { return (int) ( $this->data['image_id'] ?? 0 ); }
		public function get_gallery_image_ids(): array { return $this->data['gallery_image_ids'] ?? []; }
		public function get_category_ids(): array { return $this->data['category_ids'] ?? []; }
		public function get_stock_status(): string { return $this->data['stock_status'] ?? 'instock'; }
		public function get_status(): string { return $this->data['status'] ?? 'publish'; }
		public function get_type(): string { return $this->data['type'] ?? 'simple'; }
		public function is_in_stock(): bool { return 'instock' === $this->get_stock_status(); }
		public function get_tag_ids(): array { return $this->data['tag_ids'] ?? []; }
		public function get_children(): array { return $this->data['children'] ?? []; }
		public function is_virtual(): bool { return ! empty( $this->data['virtual'] ); }
		public function is_downloadable(): bool { return ! empty( $this->data['downloadable'] ); }
	}
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	// phpcs:ignore
	class WC_Product_Simple extends WC_Product {}
}
