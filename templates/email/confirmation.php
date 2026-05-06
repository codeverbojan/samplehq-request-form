<?php
/**
 * Confirmation email template (sent to the form submitter).
 *
 * This template can be overridden by copying it to:
 * yourtheme/samplehq-request-form/email/confirmation.php
 *
 * Available variables:
 *
 * @var string               $submitter_name Submitter's full name (escaped).
 * @var array<string, mixed> $submission     Submission data row.
 * @var array<string, mixed> $meta           Submission meta (field_key => value).
 * @var array<string, mixed> $form           Full form data row.
 * @var string               $site_name      Site name (escaped).
 *
 * @package SampleHQForm\Email
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
<?php
if ( ! empty( $submitter_name ) ) {
	printf(
		/* translators: %s: submitter name */
		esc_html__( 'Hi %s,', 'samplehq-request-form' ),
		esc_html( $submitter_name )
	);
} else {
	esc_html_e( 'Hello,', 'samplehq-request-form' );
}
?>
</p>

<p><?php esc_html_e( 'We have received your sample request and will review it shortly.', 'samplehq-request-form' ); ?></p>

<p><?php esc_html_e( 'Thank you for your interest.', 'samplehq-request-form' ); ?></p>

<p>-- <?php echo esc_html( $site_name ); ?></p>
