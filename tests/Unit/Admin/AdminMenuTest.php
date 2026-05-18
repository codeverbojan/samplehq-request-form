<?php
/**
 * Tests for the AdminMenu class.
 *
 * @package SampleHQForm\Tests\Unit\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\AdminMenu;
use SampleHQForm\Admin\DashboardPage;
use SampleHQForm\Admin\SettingsPage;
use SampleHQForm\Connection\ConnectionManager;

/**
 * AdminMenu unit tests.
 */
class AdminMenuTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Register should hook into admin_menu.
	 */
	public function test_register_hooks_admin_menu(): void {
		Monkey\Functions\expect( 'add_action' )
			->times( 4 );

		Monkey\Functions\expect( 'add_filter' )
			->once();

		$menu = new AdminMenu();
		$menu->register();
	}

	/**
	 * Add menus should register top-level menu and 5 submenus.
	 */
	public function test_add_menus_registers_all_pages(): void {
		Monkey\Functions\stubs( [
			'__'                 => static fn( $s ) => $s,
			'get_transient'      => static fn() => 0,
			'number_format_i18n' => static fn( $n ) => (string) $n,
			'esc_attr'           => static fn( $s ) => $s,
		] );

		Monkey\Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'SampleHQ Forms',
				'SampleHQ Forms',
				AdminMenu::CAPABILITY,
				AdminMenu::MENU_SLUG,
				\Mockery::type( 'array' ),
				'dashicons-clipboard',
				30
			);

		// 6 submenus: Dashboard, Sample Library, Categories, Forms, Submissions, Settings.
		Monkey\Functions\expect( 'add_submenu_page' )->times( 6 );

		$menu = new AdminMenu();
		$menu->add_menus();
	}

	/**
	 * Submenu slugs should use shqf- prefix.
	 */
	public function test_submenu_slugs_use_correct_prefix(): void {
		Monkey\Functions\stubs( [
			'__'                 => static fn( $s ) => $s,
			'add_menu_page'      => null,
			'get_transient'      => static fn() => 0,
			'number_format_i18n' => static fn( $n ) => (string) $n,
			'esc_attr'           => static fn( $s ) => $s,
		] );

		$slugs = [];
		Monkey\Functions\expect( 'add_submenu_page' )
			->times( 6 )
			->andReturnUsing(
				static function ( $parent, $title, $menu_title, $cap, $slug ) use ( &$slugs ) {
					$slugs[] = $slug;
				}
			);

		$menu = new AdminMenu();
		$menu->add_menus();

		$this->assertContains( 'shqf-dashboard', $slugs );
		$this->assertContains( 'shqf-samples', $slugs );
		$this->assertContains( 'shqf-categories', $slugs );
		$this->assertContains( 'shqf-forms', $slugs );
		$this->assertContains( 'shqf-submissions', $slugs );
		$this->assertContains( 'shqf-settings', $slugs );
	}

	/**
	 * All menus require manage_options capability.
	 */
	public function test_all_menus_require_manage_options(): void {
		Monkey\Functions\stubs( [
			'__'                 => static fn( $s ) => $s,
			'get_transient'      => static fn() => 0,
			'number_format_i18n' => static fn( $n ) => (string) $n,
			'esc_attr'           => static fn( $s ) => $s,
		] );

		Monkey\Functions\expect( 'add_menu_page' )
			->once()
			->with(
				\Mockery::any(),
				\Mockery::any(),
				'manage_options',
				\Mockery::any(),
				\Mockery::any(),
				\Mockery::any(),
				\Mockery::any()
			);

		Monkey\Functions\expect( 'add_submenu_page' )
			->times( 6 )
			->with(
				\Mockery::any(),
				\Mockery::any(),
				\Mockery::any(),
				'manage_options',
				\Mockery::any(),
				\Mockery::any()
			);

		$menu = new AdminMenu();
		$menu->add_menus();
	}

	/**
	 * Set up common stubs for dashboard rendering.
	 *
	 * @return \wpdb&\Mockery\MockInterface
	 */
	private function setup_dashboard_stubs(): \Mockery\MockInterface {
		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'esc_html'            => static fn( $s ) => $s,
			'esc_html__'          => static fn( $s ) => $s,
			'esc_url'             => static fn( $s ) => $s,
			'esc_attr'            => static fn( $s ) => $s,
			'esc_attr__'          => static fn( $s ) => $s,
			'admin_url'           => static fn( $s ) => 'http://example.com/wp-admin/' . $s,
			'wp_nonce_url'        => static fn( $url ) => $url,
			'get_current_user_id' => static fn() => 1,
			'get_transient'       => static fn() => false,
			'get_user_meta'       => static fn() => '',
			'absint'              => static fn( $v ) => abs( (int) $v ),
			'selected'            => static fn() => '',
		] );

		$wpdb         = \Mockery::mock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$wpdb->shouldReceive( 'get_results' )->andReturn( [] );
		$wpdb->shouldReceive( 'esc_like' )->andReturn( '' );
		$GLOBALS['wpdb'] = $wpdb;

		return $wpdb;
	}

	/**
	 * Render dashboard outputs wrapped HTML with all sections.
	 */
	public function test_render_dashboard_outputs_html(): void {
		$this->setup_dashboard_stubs();

		$dashboard = new DashboardPage();

		ob_start();
		$dashboard->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringContainsString( 'SampleHQ Forms', $output );
		$this->assertStringContainsString( 'Quick Actions', $output );
		$this->assertStringContainsString( 'Recent Submissions', $output );
		$this->assertStringContainsString( 'Getting Started', $output );
	}

	/**
	 * Dashboard renders stat cards with icons.
	 */
	public function test_dashboard_renders_stat_cards_with_icons(): void {
		$this->setup_dashboard_stubs();

		$dashboard = new DashboardPage();

		ob_start();
		$dashboard->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'shqf-dashboard-card', $output );
		$this->assertStringContainsString( 'dashicons-format-gallery', $output );
		$this->assertStringContainsString( 'dashicons-feedback', $output );
		$this->assertStringContainsString( 'dashicons-email-alt', $output );
		$this->assertStringContainsString( 'dashicons-bell', $output );
		$this->assertStringContainsString( 'border-left-color', $output );
	}

	/**
	 * Dashboard renders quick action cards.
	 */
	public function test_dashboard_renders_action_cards(): void {
		$this->setup_dashboard_stubs();

		$dashboard = new DashboardPage();

		ob_start();
		$dashboard->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'shqf-action-card', $output );
		$this->assertStringContainsString( 'Add Sample', $output );
		$this->assertStringContainsString( 'Create Form', $output );
		$this->assertStringContainsString( 'Import CSV', $output );
	}

	/**
	 * Dashboard renders recent forms section.
	 */
	public function test_dashboard_renders_recent_forms(): void {
		$this->setup_dashboard_stubs();

		$dashboard = new DashboardPage();

		ob_start();
		$dashboard->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Recent Forms', $output );
	}

	/**
	 * Dashboard renders SampleHQ CTA when not dismissed.
	 */
	public function test_dashboard_renders_cta_when_not_dismissed(): void {
		$this->setup_dashboard_stubs();

		$dashboard = new DashboardPage();

		ob_start();
		$dashboard->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'shqf-cta-card', $output );
		$this->assertStringContainsString( 'SampleHQ Platform', $output );
		$this->assertStringContainsString( 'dismiss_shq_cta', $output );
	}

	/**
	 * Dashboard hides CTA when dismissed.
	 */
	public function test_dashboard_hides_cta_when_dismissed(): void {
		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'esc_html'            => static fn( $s ) => $s,
			'esc_html__'          => static fn( $s ) => $s,
			'esc_url'             => static fn( $s ) => $s,
			'esc_attr'            => static fn( $s ) => $s,
			'esc_attr__'          => static fn( $s ) => $s,
			'admin_url'           => static fn( $s ) => 'http://example.com/wp-admin/' . $s,
			'wp_nonce_url'        => static fn( $url ) => $url,
			'get_current_user_id' => static fn() => 1,
			'get_transient'       => static fn() => false,
			'get_user_meta'       => static fn() => '1',
			'absint'              => static fn( $v ) => abs( (int) $v ),
			'selected'            => static fn() => '',
		] );

		$wpdb         = \Mockery::mock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$wpdb->shouldReceive( 'get_results' )->andReturn( [] );
		$wpdb->shouldReceive( 'esc_like' )->andReturn( '' );
		$GLOBALS['wpdb'] = $wpdb;

		$dashboard = new DashboardPage();

		ob_start();
		$dashboard->render();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'shqf-cta-card', $output );
	}

	/**
	 * Getting started shows progress counter.
	 */
	public function test_getting_started_shows_progress(): void {
		$this->setup_dashboard_stubs();

		$dashboard = new DashboardPage();

		ob_start();
		$dashboard->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '0/3', $output );
		$this->assertStringContainsString( 'shqf-step__number', $output );
	}

	/**
	 * Set up stubs for settings page rendering.
	 *
	 * @return void
	 */
	private function setup_settings_stubs(): void {
		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'esc_html'            => static fn( $s ) => $s,
			'esc_html__'          => static fn( $s ) => $s,
			'esc_url'             => static fn( $s ) => $s,
			'esc_attr'            => static fn( $s ) => $s,
			'esc_attr__'          => static fn( $s ) => $s,
			'admin_url'           => static fn( $s ) => 'http://example.com/wp-admin/' . $s,
			'get_option'          => static fn( $k, $d = '' ) => $d,
			'get_bloginfo'        => static fn() => 'Test Site',
			'checked'             => static fn( $a, $b, $e ) => $a === $b ? ' checked="checked"' : '',
			'wp_nonce_field'      => static fn() => null,
			'submit_button'       => static fn() => null,
			'sanitize_text_field' => static fn( $s ) => $s,
			'wp_unslash'          => static fn( $s ) => $s,
			'get_transient'       => static fn() => false,
			'get_current_user_id' => static fn() => 1,
			'wp_enqueue_script'   => static fn() => null,
			'wp_localize_script'  => static fn() => null,
			'wp_create_nonce'     => static fn() => 'test_nonce',
		] );
	}

	/**
	 * Settings renders 4 tabs.
	 */
	public function test_settings_renders_four_tabs(): void {
		$this->setup_settings_stubs();

		// Simulate no query params.
		$_GET = [];

		$settings = new SettingsPage();

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'nav-tab-wrapper', $output );
		$this->assertStringContainsString( 'tab=general', $output );
		$this->assertStringContainsString( 'tab=spam', $output );
		$this->assertStringContainsString( 'tab=email', $output );
		$this->assertStringContainsString( 'tab=connection', $output );
		// General tab active by default.
		$this->assertStringContainsString( 'Privacy & Data', $output );
	}

	/**
	 * Settings spam tab renders Turnstile fields.
	 */
	public function test_settings_spam_tab_renders_turnstile(): void {
		$this->setup_settings_stubs();

		$_GET['tab'] = 'spam';

		$settings = new SettingsPage();

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Cloudflare Turnstile', $output );
		$this->assertStringContainsString( 'shqf_turnstile_site_key', $output );
		$this->assertStringContainsString( 'shqf_turnstile_secret_key', $output );
		$this->assertStringContainsString( 'Built-in Protection', $output );

		unset( $_GET['tab'] );
	}

	/**
	 * Settings email tab renders notification and confirmation fields.
	 */
	public function test_settings_email_tab_renders_email_fields(): void {
		$this->setup_settings_stubs();

		$_GET['tab'] = 'email';

		$settings = new SettingsPage();

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Admin Notification', $output );
		$this->assertStringContainsString( 'shqf_notification_email', $output );
		$this->assertStringContainsString( 'shqf_from_name', $output );
		$this->assertStringContainsString( 'Submitter Confirmation', $output );
		$this->assertStringContainsString( 'shqf_send_confirmation', $output );

		unset( $_GET['tab'] );
	}

	/**
	 * Connection tab shows disconnected state with connect button and polling states.
	 */
	public function test_connection_tab_disconnected(): void {
		$this->setup_settings_stubs();

		Monkey\Functions\stubs( [
			'wp_nonce_url' => static fn( $url ) => $url . '&_wpnonce=abc',
		] );

		$_GET['tab'] = 'connection';

		$settings = new SettingsPage();

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'shqf-connection-status--disconnected', $output );
		$this->assertStringContainsString( 'Not connected', $output );
		$this->assertStringContainsString( 'Connect to SampleHQ', $output );
		$this->assertStringContainsString( 'shqf-connect-btn', $output );
		$this->assertStringContainsString( 'button-primary', $output );
		$this->assertStringContainsString( 'cloud sync', $output );
		$this->assertStringNotContainsString( 'Connection will be available', $output );

		unset( $_GET['tab'] );
	}

	/**
	 * Connection tab shows connected state when shqf_connection is set.
	 */
	public function test_connection_tab_connected(): void {
		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'esc_html'            => static fn( $s ) => $s,
			'esc_html__'          => static fn( $s ) => $s,
			'esc_url'             => static fn( $s ) => $s,
			'esc_attr'            => static fn( $s ) => $s,
			'esc_attr__'          => static fn( $s ) => $s,
			'admin_url'           => static fn( $s ) => 'http://example.com/wp-admin/' . $s,
			'get_bloginfo'        => static fn() => 'Test Site',
			'sanitize_text_field' => static fn( $s ) => $s,
			'wp_unslash'          => static fn( $s ) => $s,
			'get_transient'       => static fn() => false,
			'get_current_user_id' => static fn() => 1,
			'wp_nonce_url'        => static fn( $url ) => $url . '&_wpnonce=abc',
			'wp_date'             => static fn( $f, $t ) => '2026-04-28',
		] );

		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static function ( $key, $default = '' ) {
				if ( 'shqf_connection' === $key ) {
					return [
						'workspace_id'   => 42,
						'workspace_name' => 'Acme Labels',
						'workspace_url'  => 'https://acme.samplehq.io',
						'connected_by'   => 'john@acme.com',
						'connected_at'   => 1745755200,
					];
				}
				return $default;
			} );

		$_GET['tab'] = 'connection';

		$settings = new SettingsPage();

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'shqf-connection-status--connected', $output );
		$this->assertStringContainsString( 'Acme Labels', $output );
		$this->assertStringContainsString( 'john@acme.com', $output );
		$this->assertStringContainsString( 'Disconnect', $output );

		unset( $_GET['tab'] );
	}

	/**
	 * Connect button opens in new tab via JS (button element, not anchor link).
	 */
	public function test_connection_tab_disconnected_renders_button_for_new_tab(): void {
		$this->setup_settings_stubs();

		Monkey\Functions\stubs( [
			'wp_nonce_url' => static fn( $url ) => $url . '&_wpnonce=abc',
		] );

		$_GET['tab'] = 'connection';

		$settings = new SettingsPage();

		ob_start();
		$settings->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<button', $output );
		$this->assertStringContainsString( 'shqf-connect-btn', $output );
		$this->assertStringContainsString( 'button-primary', $output );
		$this->assertStringContainsString( 'shqf-connect-waiting', $output );
		$this->assertStringContainsString( 'shqf-connect-timeout', $output );

		unset( $_GET['tab'] );
	}

	/**
	 * AdminMenu accepts ConnectionManager without error.
	 */
	public function test_admin_menu_accepts_connection_manager(): void {
		$cm = \Mockery::mock( ConnectionManager::class );

		Monkey\Functions\expect( 'add_action' )->times( 4 );
		Monkey\Functions\expect( 'add_filter' )->once();

		$menu = new AdminMenu( null, null, $cm );
		$menu->register();

		$this->assertInstanceOf( AdminMenu::class, $menu );
	}

}
