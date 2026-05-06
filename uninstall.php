<?php
/**
 * Plugin uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Drops all custom database tables and removes plugin options.
 * Uses the Migrator class directly to keep the table/option list in one place.
 *
 * @package SampleHQForm
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the Migrator directly -- do NOT require the main plugin file
// because that would call Plugin::boot() and register hooks during uninstall.
require_once __DIR__ . '/src/Database/Migrator.php';

global $wpdb;

$shqf_migrator = new SampleHQForm\Database\Migrator( $wpdb );
$shqf_migrator->drop_all_tables();
$shqf_migrator->delete_all_options();

// Unschedule cron events.
$shqf_cron_timestamp = wp_next_scheduled( 'shqf_daily_cleanup' );
if ( $shqf_cron_timestamp ) {
	wp_unschedule_event( $shqf_cron_timestamp, 'shqf_daily_cleanup' );
}
