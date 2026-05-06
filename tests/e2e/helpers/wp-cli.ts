/**
 * WP-CLI helpers for E2E tests.
 * Runs commands inside the wp-env cli container.
 */

import { execSync } from 'child_process';

function wpCli( command: string ): string {
	const raw = execSync( `npx wp-env run cli -- ${ command }`, {
		encoding: 'utf-8',
		timeout: 20000,
	} );
	// Strip wp-env status lines, return only command output.
	return raw
		.split( '\n' )
		.filter(
			( l ) =>
				! l.startsWith( '\u001b' ) &&
				! l.includes( 'Starting' ) &&
				! l.includes( 'Ran `' ) &&
				l.trim() !== ''
		)
		.join( '\n' )
		.trim();
}

function wpEval( php: string ): string {
	// Escape single quotes in PHP code for shell.
	const escaped = php.replace( /'/g, "'\\''" );
	return wpCli( `wp eval '${ escaped }'` );
}

export function createSample(
	name: string,
	sku: string,
	description = ''
): number {
	const output = wpEval( `
		global $wpdb;
		$s = new SampleHQForm\\Database\\SamplesTable($wpdb);
		echo $s->create(["name" => "${ name }", "sku" => "${ sku }", "description" => "${ description }"]);
	` );
	return parseInt( output.split( '\n' ).pop() || '0', 10 );
}

export function createCategory( name: string ): number {
	const output = wpEval( `
		global $wpdb;
		$c = new SampleHQForm\\Database\\SampleCategoriesTable($wpdb);
		echo $c->create(["name" => "${ name }"]);
	` );
	return parseInt( output.split( '\n' ).pop() || '0', 10 );
}

export function createFormFromTemplate( template: string ): number {
	const output = wpEval( `
		global $wpdb;
		$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
		$tpl = SampleHQForm\\Forms\\FormTemplates::get("${ template }");
		$data = ["title" => "E2E ${ template } Form", "status" => "published", "created_by" => 1];
		if ($tpl && !empty($tpl["config"])) { $data["config"] = $tpl["config"]; }
		echo $forms->create($data);
	` );
	return parseInt( output.split( '\n' ).pop() || '0', 10 );
}

export function createBlankForm( title: string, config = '{}' ): number {
	const output = wpEval( `
		global $wpdb;
		$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
		echo $forms->create(["title" => "${ title }", "status" => "published", "created_by" => 1, "config" => json_encode(json_decode(base64_decode("${ Buffer.from( config ).toString( 'base64' ) }"), true))]);
	` );
	return parseInt( output.split( '\n' ).pop() || '0', 10 );
}

export function createPageWithShortcode(
	title: string,
	formId: number
): string {
	const idStr = wpCli(
		`wp post create --post_type=page --post_title="${ title }" --post_status=publish --post_content='[samplehq_form id="${ formId }"]' --porcelain`
	);
	const pageId = idStr.split( '\n' ).pop()?.trim() || '';
	const slug = wpCli( `wp post get ${ pageId } --field=post_name` )
		.split( '\n' )
		.pop()
		?.trim();
	return `http://localhost:8888/${ slug }/`;
}

export function getSubmissionCount( formId: number ): number {
	const output = wpEval( `
		global $wpdb;
		$s = new SampleHQForm\\Database\\SubmissionsTable($wpdb);
		echo $s->count(["form_id" => ${ formId }]);
	` );
	return parseInt( output.split( '\n' ).pop() || '0', 10 );
}

export function getFormSlug( formId: number ): string {
	const output = wpEval( `
		global $wpdb;
		$f = new SampleHQForm\\Database\\FormsTable($wpdb);
		$form = $f->get(${ formId });
		echo $form["slug"] ?? "";
	` );
	return output.split( '\n' ).pop()?.trim() || '';
}
