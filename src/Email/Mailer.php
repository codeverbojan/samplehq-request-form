<?php
/**
 * Email notification service.
 *
 * @package SampleHQForm\Email
 */

declare( strict_types=1 );

namespace SampleHQForm\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends email notifications for form submissions.
 *
 * Admin notification: sent to the site admin when a new submission arrives.
 * Confirmation email: sent to the submitter to confirm receipt.
 *
 * All content is filterable via apply_filters for customization.
 */
class Mailer {

	/**
	 * Submission meta table for logging email events.
	 *
	 * @var \SampleHQForm\Database\SubmissionMetaTable|null
	 */
	private ?\SampleHQForm\Database\SubmissionMetaTable $meta_table;

	/**
	 * Constructor.
	 *
	 * @param \SampleHQForm\Database\SubmissionMetaTable|null $meta_table Optional meta table for email logging.
	 */
	public function __construct( ?\SampleHQForm\Database\SubmissionMetaTable $meta_table = null ) {
		$this->meta_table = $meta_table;
	}

	/**
	 * Resolve email configuration with cascade:
	 * per-form email_config -> global shqf_email_defaults -> WordPress defaults.
	 *
	 * @param array<string, mixed> $form Form data row (may contain config with email key).
	 * @return array{notification_email: string, from_name: string, send_confirmation: bool}
	 */
	private function resolve_email_config( array $form ): array {
		$global = get_option( 'shqf_email_defaults', [] );
		$config = [];

		if ( ! empty( $form['config'] ) ) {
			$decoded = is_string( $form['config'] ) ? json_decode( $form['config'], true ) : $form['config'];
			$config  = is_array( $decoded ) && isset( $decoded['email'] ) ? $decoded['email'] : [];
		}

		// Cascade: per-form -> global -> WordPress default.
		$notification_email = ! empty( $config['notification_email'] )
			? sanitize_email( $config['notification_email'] )
			: ( ! empty( $global['notification_email'] ) ? $global['notification_email'] : get_option( 'admin_email' ) );

		$from_name = ! empty( $config['from_name'] )
			? sanitize_text_field( $config['from_name'] )
			: ( ! empty( $global['from_name'] ) ? $global['from_name'] : '' );

		// send_confirmation: per-form (if set) -> global -> true.
		$send_confirmation = true;
		if ( isset( $config['send_confirmation'] ) ) {
			$send_confirmation = (bool) $config['send_confirmation'];
		} elseif ( isset( $global['send_confirmation'] ) ) {
			$send_confirmation = '1' === $global['send_confirmation'];
		}

		return [
			'notification_email' => $notification_email,
			'from_name'          => $from_name,
			'send_confirmation'  => $send_confirmation,
		];
	}

	/**
	 * Send an admin notification for a new submission.
	 *
	 * @param array<string, mixed> $submission Submission data row.
	 * @param array<string, mixed> $meta       Submission meta (field_key => value).
	 * @param array<string, mixed> $form       Form data row.
	 * @return bool True if the email was sent.
	 */
	public function send_admin_notification( array $submission, array $meta, array $form ): bool {
		$email_config = $this->resolve_email_config( $form );
		$to           = $email_config['notification_email'];

		if ( empty( $to ) ) {
			return false;
		}

		$form_title = $form['title'] ?? __( 'Sample Request Form', 'samplehq-request-form' );

		$subject = sprintf(
			/* translators: 1: form title, 2: site name */
			__( 'New sample request: %1$s [%2$s]', 'samplehq-request-form' ),
			$form_title,
			get_bloginfo( 'name' )
		);

		$body = $this->build_admin_body( $submission, $meta, $form );

		/**
		 * Filter the admin notification email content.
		 *
		 * @param string               $body       Email body.
		 * @param array<string, mixed> $submission Submission data.
		 * @param array<string, mixed> $meta       Submission meta.
		 * @param array<string, mixed> $form       Form data.
		 */
		$body = apply_filters( 'shqf_admin_notification_content', $body, $submission, $meta, $form );

		/**
		 * Filter the admin notification email subject.
		 *
		 * @param string               $subject    Email subject.
		 * @param array<string, mixed> $submission Submission data.
		 * @param array<string, mixed> $form       Form data.
		 */
		$subject = apply_filters( 'shqf_admin_notification_subject', $subject, $submission, $form );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Custom from name if configured.
		if ( ! empty( $email_config['from_name'] ) ) {
			$headers[] = 'From: ' . $email_config['from_name'] . ' <' . get_option( 'admin_email' ) . '>';
		}

		// Set reply-to if submitter email is available.
		$submitter_email = $submission['email'] ?? '';
		if ( ! empty( $submitter_email ) && is_email( $submitter_email ) ) {
			$name      = trim( ( $submission['first_name'] ?? '' ) . ' ' . ( $submission['last_name'] ?? '' ) );
			$headers[] = 'Reply-To: ' . ( ! empty( $name ) ? $name . ' <' . $submitter_email . '>' : $submitter_email );
		}

		$sent = wp_mail( $to, $subject, $body, $headers );
		$this->log_email( (int) ( $submission['id'] ?? 0 ), 'admin_notification', $to, $sent );

		return $sent;
	}

	/**
	 * Send a confirmation email to the form submitter.
	 *
	 * Respects the send_confirmation toggle from email config cascade.
	 *
	 * @param array<string, mixed> $submission Submission data row.
	 * @param array<string, mixed> $meta       Submission meta.
	 * @param array<string, mixed> $form       Form data row.
	 * @return bool True if the email was sent, false if disabled or no email.
	 */
	public function send_confirmation( array $submission, array $meta, array $form ): bool {
		$email_config = $this->resolve_email_config( $form );

		if ( ! $email_config['send_confirmation'] ) {
			return false;
		}

		$to = $submission['email'] ?? '';

		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		$form_title = $form['title'] ?? __( 'Sample Request Form', 'samplehq-request-form' );

		$subject = sprintf(
			/* translators: %s: form title */
			__( 'We received your sample request: %s', 'samplehq-request-form' ),
			$form_title
		);

		$body = $this->build_confirmation_body( $submission, $meta, $form );

		/**
		 * Filter the confirmation email content.
		 *
		 * @param string               $body       Email body.
		 * @param array<string, mixed> $submission Submission data.
		 * @param array<string, mixed> $meta       Submission meta.
		 * @param array<string, mixed> $form       Form data.
		 */
		$body = apply_filters( 'shqf_confirmation_email_content', $body, $submission, $meta, $form );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Custom from name if configured.
		if ( ! empty( $email_config['from_name'] ) ) {
			$headers[] = 'From: ' . $email_config['from_name'] . ' <' . get_option( 'admin_email' ) . '>';
		}

		$sent = wp_mail( $to, $subject, $body, $headers );
		$this->log_email( (int) ( $submission['id'] ?? 0 ), 'confirmation', $to, $sent );

		return $sent;
	}

	/**
	 * Log an email event to submission meta.
	 *
	 * Stored as JSON in a meta key like _email_admin_notification or _email_confirmation.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $type          Email type (admin_notification, confirmation).
	 * @param string $recipient     Recipient email address.
	 * @param bool   $success       Whether wp_mail returned true.
	 * @return void
	 */
	private function log_email( int $submission_id, string $type, string $recipient, bool $success ): void {
		if ( 0 === $submission_id || null === $this->meta_table ) {
			return;
		}

		$log = wp_json_encode(
			[
				'to'      => $recipient,
				'status'  => $success ? 'sent' : 'failed',
				'sent_at' => current_time( 'mysql', true ),
			]
		);

		$this->meta_table->add( $submission_id, '_email_' . $type, (string) $log );
	}

	/**
	 * Build the admin notification email body.
	 *
	 * @param array<string, mixed> $submission Submission data.
	 * @param array<string, mixed> $meta       Submission meta.
	 * @param array<string, mixed> $form       Form data.
	 * @return string HTML email body.
	 */
	private function build_admin_body( array $submission, array $meta, array $form ): string {
		$form_title = $form['title'] ?? '';

		return $this->render_template( 'admin-notification', compact( 'form_title', 'submission', 'meta', 'form' ) );
	}

	/**
	 * Build the confirmation email body.
	 *
	 * @param array<string, mixed> $submission Submission data.
	 * @param array<string, mixed> $meta       Submission meta.
	 * @param array<string, mixed> $form       Form data.
	 * @return string HTML email body.
	 */
	private function build_confirmation_body( array $submission, array $meta, array $form ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $meta and $form passed to template via compact().
		$submitter_name = trim( ( $submission['first_name'] ?? '' ) . ' ' . ( $submission['last_name'] ?? '' ) );
		$site_name      = get_bloginfo( 'name' );

		return $this->render_template( 'confirmation', compact( 'submitter_name', 'submission', 'meta', 'form', 'site_name' ) );
	}

	/**
	 * Render an email template with theme override support.
	 *
	 * Looks for the template in:
	 * 1. yourtheme/samplehq-request-form/email/{template}.php
	 * 2. plugin/templates/email/{template}.php
	 *
	 * @param string               $template Template name (without extension).
	 * @param array<string, mixed> $vars     Variables to extract into the template scope.
	 * @return string Rendered HTML.
	 */
	private function render_template( string $template, array $vars ): string {
		$theme_file = locate_template( 'samplehq-request-form/email/' . $template . '.php' );
		$file       = $theme_file ? $theme_file : SHQF_DIR . 'templates/email/' . $template . '.php';

		if ( ! file_exists( $file ) ) {
			return '';
		}

		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variables are explicitly defined by the caller.

		ob_start();
		include $file;
		return (string) ob_get_clean();
	}
}
