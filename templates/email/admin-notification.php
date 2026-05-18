<?php
/**
 * Admin notification email template.
 *
 * This template can be overridden by copying it to:
 * yourtheme/samplehq-request-form/email/admin-notification.php
 *
 * Available variables:
 *
 * @var string               $form_title  Form title (escaped).
 * @var array<string, mixed> $submission  Submission data row.
 * @var array<string, mixed> $meta        Submission meta (field_key => value).
 * @var array<string, mixed> $form        Full form data row.
 *
 * @package SampleHQForm\Email
 */

defined( 'ABSPATH' ) || exit;
?>
<h2>
<?php
printf(
	/* translators: %s: form title */
	esc_html__( 'New submission from %s', 'samplehq-request-form' ),
	esc_html( $form_title )
);
?>
</h2>

<table style="border-collapse:collapse;width:100%;max-width:600px;">
<?php foreach ( $meta as $shqf_key => $shqf_value ) : ?>
	<?php
	// Skip internal meta keys (email logs, etc.).
	if ( str_starts_with( $shqf_key, '_' ) ) {
		continue;
	}
	$shqf_display = \SampleHQForm\Admin\SubmissionsPage::format_meta_value( $shqf_key, $shqf_value );
	?>
	<tr>
		<td style="padding:8px;border:1px solid #ddd;font-weight:bold;"><?php echo esc_html( $shqf_key ); ?></td>
		<td style="padding:8px;border:1px solid #ddd;"><?php echo esc_html( $shqf_display ); ?></td>
	</tr>
<?php endforeach; ?>
</table>

<?php if ( ! empty( $submission['source_url'] ) ) : ?>
<p><small><?php esc_html_e( 'Submitted from:', 'samplehq-request-form' ); ?> <?php echo esc_url( $submission['source_url'] ); ?></small></p>
<?php endif; ?>
