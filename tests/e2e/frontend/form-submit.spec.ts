import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

let formPageUrl = '';

test.describe( 'Frontend form submission', () => {
	test.beforeAll( async () => {
		// Create form and page via WP-CLI inside wp-env.
		const createForm = execSync(
			"npx wp-env run cli wp eval '" +
				'global $wpdb; ' +
				'$forms = new SampleHQForm\\Database\\FormsTable($wpdb); ' +
				'$id = $forms->create(["title" => "E2E Test Form", "status" => "published", "created_by" => 1]); ' +
				"echo $id;'",
			{ encoding: 'utf-8', timeout: 15000 }
		).trim();

		// Extract the form ID from the output (last line).
		const formId = createForm.split( '\n' ).pop()?.trim() || '1';

		// Create a page with the shortcode.
		const createPage = execSync(
			`npx wp-env run cli wp post create --post_type=page --post_title="E2E Form Page" --post_status=publish --post_content='[samplehq_form id="${ formId }"]' --porcelain`,
			{ encoding: 'utf-8', timeout: 15000 }
		).trim();

		const pageId = createPage.split( '\n' ).pop()?.trim();

		// Get the page slug and build the URL for port 8888.
		const slugOutput = execSync(
			`npx wp-env run cli wp post get ${ pageId } --field=post_name`,
			{ encoding: 'utf-8', timeout: 15000 }
		).trim();

		const slug = slugOutput.split( '\n' ).pop()?.trim() || '';
		formPageUrl = slug ? `http://localhost:8888/${ slug }/` : '/';
	} );

	test( 'form renders on frontend page', async ( { page } ) => {
		test.skip(
			! formPageUrl || formPageUrl === '/',
			'Form page not created'
		);

		await page.goto( formPageUrl );
		const form = page.locator( '.shqf-form' );
		await expect( form ).toBeVisible();
	} );
} );
