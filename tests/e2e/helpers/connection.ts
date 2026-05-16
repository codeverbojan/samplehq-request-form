/**
 * WP-CLI helpers for connection-related E2E tests.
 *
 * Seeds connection state, migration progress, and sync failures
 * via wp-env CLI so tests can exercise admin UI without needing
 * a live platform.
 */

import { execSync } from 'child_process';
import path from 'path';

const PLUGIN_ROOT = path.resolve( __dirname, '../../..' );

function wpCli( command: string ): string {
	const raw = execSync( `npx wp-env run cli -- ${ command }`, {
		encoding: 'utf-8',
		timeout: 20000,
		cwd: PLUGIN_ROOT,
	} );
	return raw
		.split( '\n' )
		.filter(
			( l ) =>
				! l.startsWith( '' ) &&
				! l.includes( 'Starting' ) &&
				! l.includes( 'Ran `' ) &&
				l.trim() !== ''
		)
		.join( '\n' )
		.trim();
}

export function wpEval( php: string ): string {
	const escaped = php.replace( /'/g, "'\\''" );
	return wpCli( `wp eval '${ escaped }'` );
}

function lastLine( output: string ): string {
	return output.split( '\n' ).pop()?.trim() || '';
}

/**
 * Seed a connected state by writing the shqf_connection option.
 *
 * Uses a fake connection_secret (is_connected() only checks non-emptiness,
 * it does not decrypt). This lets the settings page render the connected UI.
 * @param overrides
 */
export function seedConnectedState(
	overrides: Record< string, string | number > = {}
): void {
	const defaults: Record< string, string | number > = {
		workspace_url: 'https://app.samplehq.io/workspace/test-e2e',
		workspace_id: 999,
		workspace_name: 'E2E Test Workspace',
		connection_secret: 'fake-secret-for-e2e-testing',
		connected_by: 'admin@example.com',
		connected_at: Math.floor( Date.now() / 1000 ),
	};

	const data = { ...defaults, ...overrides };
	const json = JSON.stringify( data );
	const b64 = Buffer.from( json ).toString( 'base64' );

	wpEval(
		`update_option( "shqf_connection", json_decode( base64_decode( "${ b64 }" ), true ) );`
	);
}

/**
 * Remove the connection option to simulate a disconnected state.
 */
export function seedDisconnectedState(): void {
	wpEval( `delete_option( "shqf_connection" );` );
}

/**
 * Seed a sync failure on a submission by writing _sync_error meta.
 * @param submissionId
 * @param error
 * @param attempts
 */
export function seedFailedSync(
	submissionId: number,
	error = 'HTTP 401 - authentication_failed',
	attempts = 3
): void {
	const b64Error = Buffer.from( error ).toString( 'base64' );
	wpEval( `
		global $wpdb;
		$meta = new SampleHQForm\\Database\\SubmissionMetaTable( $wpdb );
		$err = base64_decode( "${ b64Error }" );
		$meta->delete( ${ submissionId }, "_sync_error" );
		$meta->add( ${ submissionId }, "_sync_error", $err );
		$meta->delete( ${ submissionId }, "_sync_attempts" );
		$meta->add( ${ submissionId }, "_sync_attempts", "${ attempts }" );
	` );
}

/**
 * Seed migration progress by writing the shqf_migration_progress option.
 * @param phase
 * @param counts
 */
export function seedMigrationProgress(
	phase: string,
	counts: Record< string, number > = {}
): void {
	const progress: Record< string, string | number | boolean > = {
		phase,
		categories_total: counts.categories_total ?? 0,
		categories_completed: counts.categories_completed ?? 0,
		categories_created: counts.categories_created ?? 0,
		categories_updated: counts.categories_updated ?? 0,
		samples_total: counts.samples_total ?? 0,
		samples_completed: counts.samples_completed ?? 0,
		samples_created: counts.samples_created ?? 0,
		samples_updated: counts.samples_updated ?? 0,
		samples_skipped: counts.samples_skipped ?? 0,
		submissions_total: counts.submissions_total ?? 0,
		submissions_completed: counts.submissions_completed ?? 0,
		submissions_accepted: counts.submissions_accepted ?? 0,
		submissions_duplicates: counts.submissions_duplicates ?? 0,
		include_submissions: counts.include_submissions ?? 0,
	};

	const json = JSON.stringify( progress );
	const b64 = Buffer.from( json ).toString( 'base64' );

	wpEval(
		`update_option( "shqf_migration_progress", json_decode( base64_decode( "${ b64 }" ), true ), false );`
	);
}

/**
 * Read a submission meta value via wp-cli.
 * @param submissionId
 * @param key
 */
export function getSubmissionMeta(
	submissionId: number,
	key: string
): string | null {
	const output = lastLine(
		wpEval( `
			global $wpdb;
			$meta = new SampleHQForm\\Database\\SubmissionMetaTable( $wpdb );
			$val = $meta->get( ${ submissionId }, "${ key }" );
			echo $val ?? "__NULL__";
		` )
	);
	return output === '__NULL__' ? null : output;
}

/**
 * Run all pending WP-Cron events.
 */
export function triggerCron(): void {
	wpCli( 'wp cron event run --all' );
}

/**
 * Create a submission and return its ID. Used to seed sync failure tests.
 * @param formId
 * @param email
 */
export function createSubmission(
	formId: number,
	email = 'e2e@example.com'
): number {
	const b64Email = Buffer.from( email ).toString( 'base64' );
	const output = lastLine(
		wpEval( `
			global $wpdb;
			$s = new SampleHQForm\\Database\\SubmissionsTable( $wpdb );
			echo $s->create(
				${ formId },
				[
					"email"      => base64_decode( "${ b64Email }" ),
					"first_name" => "E2E",
					"last_name"  => "Test",
				],
				[
					"ip_address" => "127.0.0.1",
					"source_url" => "http://localhost:8888/test/",
				]
			);
		` )
	);
	return parseInt( output, 10 );
}

/**
 * Clean up all connection-related state.
 */
export function cleanupConnectionState(): void {
	wpEval( `
		delete_option( "shqf_connection" );
		delete_option( "shqf_migration_progress" );
		delete_option( "shqf_connect_state" );
	` );
}
