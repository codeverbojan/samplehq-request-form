/**
 * Playwright global setup: authenticate + create seed data.
 *
 * Runs once before all test projects. Creates samples, forms from
 * all templates, and pages with shortcodes. Stores IDs and URLs
 * in .fixtures.json for tests to consume.
 */

import { test as setup, expect } from '@playwright/test';
import { execSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'password';
const AUTH_FILE = 'tests/e2e/.auth/admin.json';
const FIXTURES_FILE = 'tests/e2e/.fixtures.json';

function cli( command: string ): string {
	const raw = execSync( `npx wp-env run cli -- ${ command }`, {
		encoding: 'utf-8',
		timeout: 20000,
	} );
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
	const escaped = php.replace( /'/g, "'\\''" );
	return cli( `wp eval '${ escaped }'` );
}

function lastLine( output: string ): string {
	return output.split( '\n' ).pop()?.trim() || '';
}

setup( 'authenticate as admin', async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', ADMIN_USER );
	await page.fill( '#user_pass', ADMIN_PASS );
	await page.click( '#wp-submit' );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
	await page.context().storageState( { path: AUTH_FILE } );
} );

setup( 'create seed data', async () => {
	// Skip if fixtures already exist (idempotent).
	const fixturesPath = path.resolve( FIXTURES_FILE );
	if ( fs.existsSync( fixturesPath ) ) {
		const existing = JSON.parse( fs.readFileSync( fixturesPath, 'utf-8' ) );
		if ( existing.ready ) {
			return;
		}
	}

	// Create categories.
	const cat1Id = lastLine(
		wpEval( `
		global $wpdb;
		$c = new SampleHQForm\\Database\\SampleCategoriesTable($wpdb);
		try { echo $c->create(["name" => "Textiles"]); } catch (Exception $e) { echo "0"; }
	` )
	);
	const cat2Id = lastLine(
		wpEval( `
		global $wpdb;
		$c = new SampleHQForm\\Database\\SampleCategoriesTable($wpdb);
		try { echo $c->create(["name" => "Metals", "parent_id" => 0]); } catch (Exception $e) { echo "0"; }
	` )
	);

	// Create 5 samples.
	const sampleIds: string[] = [];
	const sampleNames = [
		'Blue Fabric',
		'Red Fabric',
		'Steel Sheet',
		'Copper Wire',
		'Wood Panel',
	];
	const sampleSkus = [
		'E2E-BF-01',
		'E2E-RF-02',
		'E2E-SS-03',
		'E2E-CW-04',
		'E2E-WP-05',
	];
	for ( let i = 0; i < 5; i++ ) {
		const id = lastLine(
			wpEval( `
			global $wpdb;
			$s = new SampleHQForm\\Database\\SamplesTable($wpdb);
			try { echo $s->create(["name" => "${ sampleNames[ i ] }", "sku" => "${
				sampleSkus[ i ]
			}", "description" => "E2E test sample ${
				i + 1
			}"]); } catch (Exception $e) { echo "0"; }
		` )
		);
		sampleIds.push( id );
	}

	// Assign categories to samples.
	if ( parseInt( cat1Id ) > 0 && parseInt( sampleIds[ 0 ] ) > 0 ) {
		wpEval( `
			global $wpdb;
			$m = new SampleHQForm\\Database\\SampleCategoryMapTable($wpdb);
			$m->add(${ sampleIds[ 0 ] }, ${ cat1Id });
			$m->add(${ sampleIds[ 1 ] }, ${ cat1Id });
			$m->add(${ sampleIds[ 2 ] }, ${ cat2Id });
		` );
	}

	// Create forms from templates.
	const templates = [ 'wizard', 'grid', 'checklist', 'blank' ];
	const formIds: Record< string, string > = {};
	const pageUrls: Record< string, string > = {};

	for ( const tpl of templates ) {
		const formId = lastLine(
			wpEval( `
			global $wpdb;
			$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
			$tpl = SampleHQForm\\Forms\\FormTemplates::get("${ tpl }");
			$data = ["title" => "E2E ${ tpl } Form", "status" => "published", "created_by" => 1];
			if ($tpl && !empty($tpl["config"])) { $data["config"] = $tpl["config"]; }
			echo $forms->create($data);
		` )
		);
		formIds[ tpl ] = formId;

		// Create page with shortcode.
		if ( tpl !== 'blank' ) {
			const pageId = lastLine(
				cli(
					`wp post create --post_type=page --post_title="E2E ${ tpl } Page" --post_status=publish --post_content='[samplehq_form id="${ formId }"]' --porcelain`
				)
			);
			const slug = lastLine(
				cli( `wp post get ${ pageId } --field=post_name` )
			);
			pageUrls[ tpl ] = `http://localhost:8888/${ slug }/`;
		}
	}

	// Create "all-fields" form with every field type for Phase B testing.
	const allFieldsFormId = lastLine(
		wpEval( `
		global $wpdb;
		$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
		$config = [
			"schema_version" => 1,
			"layout" => "grid",
			"title" => "All Field Types",
			"subtitle" => "Testing every field type",
			"fields" => [
				["id" => "f_picker", "type" => "sample_picker", "key" => "samples", "label" => "Samples", "required" => true, "enabled" => true, "step_index" => 0, "config" => ["source" => "library", "filter" => ["mode" => "all"], "max_selections" => 3, "allow_quantity" => true, "default_max_quantity" => 5, "layout" => "grid", "show_images" => true, "show_descriptions" => true]],
				["id" => "f_text", "type" => "text", "key" => "text_field", "label" => "Text Field", "required" => true, "enabled" => true, "step_index" => 0],
				["id" => "f_email", "type" => "email", "key" => "email", "label" => "Email", "required" => true, "enabled" => true, "step_index" => 0],
				["id" => "f_phone", "type" => "phone", "key" => "phone", "label" => "Phone", "required" => false, "enabled" => true, "step_index" => 0],
				["id" => "f_textarea", "type" => "textarea", "key" => "message", "label" => "Message", "required" => false, "enabled" => true, "step_index" => 0],
				["id" => "f_number", "type" => "number", "key" => "quantity", "label" => "Quantity", "required" => false, "enabled" => true, "step_index" => 0, "validation" => ["min" => 1, "max" => 100]],
				["id" => "f_name", "type" => "name", "key" => "full_name", "label" => "Full Name", "required" => true, "enabled" => true, "step_index" => 0],
				["id" => "f_select", "type" => "select", "key" => "department", "label" => "Department", "required" => true, "enabled" => true, "step_index" => 0, "options" => [["value" => "sales", "label" => "Sales"], ["value" => "engineering", "label" => "Engineering"], ["value" => "design", "label" => "Design"]]],
				["id" => "f_radio", "type" => "radio", "key" => "priority", "label" => "Priority", "required" => true, "enabled" => true, "step_index" => 0, "options" => [["value" => "low", "label" => "Low"], ["value" => "medium", "label" => "Medium"], ["value" => "high", "label" => "High"]]],
				["id" => "f_checkbox", "type" => "checkbox", "key" => "interests", "label" => "Interests", "required" => true, "enabled" => true, "step_index" => 0, "options" => [["value" => "color", "label" => "Color options"], ["value" => "texture", "label" => "Texture"], ["value" => "durability", "label" => "Durability"]]],
				["id" => "f_date", "type" => "date", "key" => "needed_by", "label" => "Needed By", "required" => false, "enabled" => true, "step_index" => 0],
				["id" => "f_url", "type" => "url", "key" => "website", "label" => "Website", "required" => false, "enabled" => true, "step_index" => 0],
				["id" => "f_address", "type" => "address", "key" => "shipping_address", "label" => "Shipping Address", "required" => false, "enabled" => true, "step_index" => 0],
				["id" => "f_hidden", "type" => "hidden", "key" => "source_ref", "label" => "", "required" => false, "enabled" => true, "step_index" => 0, "default_value" => "e2e-test"],
				["id" => "f_html", "type" => "html", "key" => "info_block", "label" => "", "required" => false, "enabled" => true, "step_index" => 0, "content" => "<p class=\\"shqf-html-content\\">This is an informational block.</p>"],
				["id" => "f_consent", "type" => "consent", "key" => "consent", "label" => "Consent", "required" => true, "enabled" => true, "step_index" => 0, "consent_text" => "I agree to the terms and conditions."],
				["id" => "f_file", "type" => "file_upload", "key" => "attachment", "label" => "Attachment", "required" => false, "enabled" => true, "step_index" => 0, "validation" => ["allowed_types" => ["image/png", "image/jpeg", "application/pdf"], "max_size_mb" => 2]],
			],
			"appearance" => ["primary_color" => "#0F766E", "button_color" => "#0F766E", "button_text_color" => "#FFFFFF", "border_radius" => 8],
			"behavior" => ["success_type" => "message", "success_message" => "All fields submitted successfully.", "submit_button_text" => "Submit"],
		];
		echo $forms->create(["title" => "E2E All Fields Form", "status" => "published", "created_by" => 1, "config" => $config]);
	` )
	);
	formIds.allFields = allFieldsFormId;

	// Page for the all-fields form.
	const allFieldsPageId = lastLine(
		cli(
			`wp post create --post_type=page --post_title="E2E All Fields Page" --post_status=publish --post_content='[samplehq_form id="${ allFieldsFormId }"]' --porcelain`
		)
	);
	const allFieldsSlug = lastLine(
		cli( `wp post get ${ allFieldsPageId } --field=post_name` )
	);
	pageUrls.allFields = `http://localhost:8888/${ allFieldsSlug }/`;

	// Create "conditional" form for Phase H testing.
	// Field A (trigger) + Field B (conditional, shown when trigger equals "show") + name + email + picker.
	const conditionalFormId = lastLine(
		wpEval( `
		global $wpdb;
		$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
		$config = [
			"schema_version" => 1,
			"layout" => "grid",
			"title" => "Conditional Logic Test",
			"subtitle" => "",
			"fields" => [
				["id" => "f_cpicker", "type" => "sample_picker", "key" => "samples", "label" => "Samples", "required" => true, "enabled" => true, "step_index" => 0, "config" => ["source" => "library", "filter" => ["mode" => "all"], "max_selections" => 3, "allow_quantity" => false, "layout" => "grid", "show_images" => false, "show_descriptions" => false]],
				["id" => "f_trigger", "type" => "text", "key" => "trigger", "label" => "Trigger Field", "required" => false, "enabled" => true, "step_index" => 0],
				["id" => "f_cond", "type" => "text", "key" => "conditional_field", "label" => "Conditional Field", "required" => false, "enabled" => true, "step_index" => 0, "conditions" => ["logic" => "all", "rules" => [["field_key" => "trigger", "operator" => "equals", "value" => "show"]]]],
				["id" => "f_cname", "type" => "name", "key" => "full_name", "label" => "Full Name", "required" => true, "enabled" => true, "step_index" => 0],
				["id" => "f_cemail", "type" => "email", "key" => "email", "label" => "Email", "required" => true, "enabled" => true, "step_index" => 0],
			],
			"appearance" => ["primary_color" => "#0F766E", "button_color" => "#0F766E", "button_text_color" => "#FFFFFF", "border_radius" => 8],
			"behavior" => ["success_type" => "message", "success_message" => "Submitted.", "submit_button_text" => "Submit"],
		];
		echo $forms->create(["title" => "E2E Conditional Form", "status" => "published", "created_by" => 1, "config" => $config]);
	` )
	);
	formIds.conditional = conditionalFormId;

	const conditionalPageId = lastLine(
		cli(
			`wp post create --post_type=page --post_title="E2E Conditional Page" --post_status=publish --post_content='[samplehq_form id="${ conditionalFormId }"]' --porcelain`
		)
	);
	const conditionalSlug = lastLine(
		cli( `wp post get ${ conditionalPageId } --field=post_name` )
	);
	pageUrls.conditional = `http://localhost:8888/${ conditionalSlug }/`;

	// Write fixtures.
	const fixtures = {
		ready: true,
		categories: { textiles: cat1Id, metals: cat2Id },
		sampleIds,
		sampleNames,
		formIds,
		pageUrls,
	};
	fs.writeFileSync( fixturesPath, JSON.stringify( fixtures, null, '\t' ) );
} );
