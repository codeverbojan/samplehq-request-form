<?php
/**
 * Form submission processor.
 *
 * @package SampleHQForm\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\RateLimitsTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;
use SampleHQForm\Email\Mailer;
use SampleHQForm\Fields\FieldRegistry;
use SampleHQForm\Spam\FormToken;
use SampleHQForm\Spam\HoneypotValidator;
use SampleHQForm\Spam\TurnstileVerifier;

/**
 * Orchestrates the full submission flow.
 *
 * Validates form exists + published, checks rate limit, validates token + honeypot,
 * runs field validation, sanitizes input, creates submission + meta, increments
 * form count, and returns a result array.
 */
class FormProcessor {

	/**
	 * Forms repository.
	 *
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * Submissions repository.
	 *
	 * @var SubmissionsTable
	 */
	private SubmissionsTable $submissions;

	/**
	 * Submission meta repository.
	 *
	 * @var SubmissionMetaTable
	 */
	private SubmissionMetaTable $submission_meta;

	/**
	 * Rate limits repository.
	 *
	 * @var RateLimitsTable
	 */
	private RateLimitsTable $rate_limits;

	/**
	 * Form validator.
	 *
	 * @var FormValidator
	 */
	private FormValidator $validator;

	/**
	 * Form token (CSRF).
	 *
	 * @var FormToken
	 */
	private FormToken $token;

	/**
	 * Honeypot validator.
	 *
	 * @var HoneypotValidator
	 */
	private HoneypotValidator $honeypot;

	/**
	 * Email mailer.
	 *
	 * @var Mailer
	 */
	private Mailer $mailer;

	/**
	 * Turnstile verifier (optional CAPTCHA).
	 *
	 * @var TurnstileVerifier
	 */
	private TurnstileVerifier $turnstile;

	/**
	 * Constructor.
	 *
	 * @param FormsTable          $forms           Forms repository.
	 * @param SubmissionsTable    $submissions     Submissions repository.
	 * @param SubmissionMetaTable $submission_meta Submission meta repository.
	 * @param RateLimitsTable     $rate_limits     Rate limits repository.
	 * @param FormValidator       $validator       Field validator.
	 * @param FormToken           $token           CSRF token handler.
	 * @param HoneypotValidator   $honeypot        Honeypot checker.
	 * @param Mailer              $mailer          Email notification sender.
	 * @param TurnstileVerifier   $turnstile       Turnstile CAPTCHA verifier.
	 */
	public function __construct(
		FormsTable $forms,
		SubmissionsTable $submissions,
		SubmissionMetaTable $submission_meta,
		RateLimitsTable $rate_limits,
		FormValidator $validator,
		FormToken $token,
		HoneypotValidator $honeypot,
		Mailer $mailer,
		TurnstileVerifier $turnstile
	) {
		$this->forms           = $forms;
		$this->submissions     = $submissions;
		$this->submission_meta = $submission_meta;
		$this->rate_limits     = $rate_limits;
		$this->validator       = $validator;
		$this->token           = $token;
		$this->honeypot        = $honeypot;
		$this->mailer          = $mailer;
		$this->turnstile       = $turnstile;
	}

	/**
	 * Process a form submission.
	 *
	 * @param array<string, mixed> $data Submission data: form_id, shqf_token, shqf_hp,
	 *                                   shqf_fields (field values), plus meta (ip, ua, url).
	 * @return array{success: bool, errors?: array<string, string>, message?: string, submission_id?: int, status_code: int}
	 */
	public function process( array $data ): array {
		$form_id = absint( $data['form_id'] ?? 0 );

		// 1. Load the form.
		$form = $this->forms->get( $form_id );
		if ( null === $form ) {
			return $this->error_response(
				__( 'Form not found.', 'samplehq-request-form' ),
				404
			);
		}

		if ( 'published' !== ( $form['status'] ?? '' ) ) {
			return $this->error_response(
				__( 'This form is not accepting submissions.', 'samplehq-request-form' ),
				403
			);
		}

		$config = $form['config'] ?? [];

		// 2. Rate limit check.
		$ip = sanitize_text_field( $data['ip_address'] ?? '' );
		if ( ! empty( $ip ) && ! $this->should_bypass_rate_limit() ) {
			$allowed = $this->rate_limits->check_and_increment( $ip, $form_id );
			if ( ! $allowed ) {
				return $this->error_response(
					__( 'Too many submissions. Please try again later.', 'samplehq-request-form' ),
					429
				);
			}
		}

		// 3. Honeypot check.
		$honeypot_value = (string) ( $data['shqf_hp'] ?? '' );
		if ( $this->honeypot->is_spam( $honeypot_value ) ) {
			return $this->success_response( $config, 0 );
		}

		// 4. Turnstile CAPTCHA check (skipped if not configured).
		$turnstile_token = (string) ( $data['cf_turnstile_response'] ?? '' );
		if ( ! $this->turnstile->verify( $turnstile_token, $ip ) ) {
			return $this->error_response(
				__( 'CAPTCHA verification failed. Please try again.', 'samplehq-request-form' ),
				403
			);
		}

		// 5. CSRF token check.
		$token_value = (string) ( $data['shqf_token'] ?? '' );
		if ( ! $this->token->validate( $form_id, $token_value ) ) {
			return $this->error_response(
				__( 'Your session has expired. Please reload the page and try again.', 'samplehq-request-form' ),
				403
			);
		}

		// 5. Field validation.
		$fields = $data['shqf_fields'] ?? [];
		if ( ! is_array( $fields ) ) {
			$fields = [];
		}

		$errors = $this->validator->validate( $fields, $config );
		if ( ! empty( $errors ) ) {
			return [
				'success'     => false,
				'errors'      => $errors,
				'status_code' => 422,
			];
		}

		// 6. Sanitize all field values.
		$sanitized = $this->validator->sanitize_all( $fields, $config );

		// 7. Create the submission.
		$meta = [
			'source_url' => $data['source_url'] ?? '',
			'ip_address' => $this->should_collect_ip() ? $ip : null,
			'user_agent' => $this->should_collect_ip() ? ( $data['user_agent'] ?? '' ) : null,
		];

		try {
			$submission_id = $this->submissions->create( $form_id, $sanitized, $meta );
		} catch ( \RuntimeException $e ) {
			return $this->error_response(
				__( 'An error occurred while saving your submission. Please reload the page and try again.', 'samplehq-request-form' ),
				500
			);
		}

		// 8. Store all field values as meta.
		$this->submission_meta->add_many( $submission_id, $sanitized );

		// 9. Increment the form submissions counter.
		$this->forms->increment_submissions_count( $form_id );

		// 10. Send email notifications.
		$submission_row = $this->submissions->get( $submission_id );
		if ( null !== $submission_row ) {
			$this->mailer->send_admin_notification( $submission_row, $sanitized, $form );
			$this->mailer->send_confirmation( $submission_row, $sanitized, $form );
		}

		return $this->success_response( $config, $submission_id );
	}

	/**
	 * Check if the current user should bypass rate limiting (admin testing).
	 *
	 * @return bool True if rate limiting should be skipped.
	 */
	private function should_bypass_rate_limit(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if IP/UA collection is enabled.
	 *
	 * @return bool True if IP collection is enabled.
	 */
	private function should_collect_ip(): bool {
		return (bool) get_option( 'shqf_collect_ip', true );
	}

	/**
	 * Build a success response.
	 *
	 * @param array<string, mixed> $config        Form config.
	 * @param int                  $submission_id The created submission ID.
	 * @return array{success: bool, message: string, submission_id: int, status_code: int}
	 */
	private function success_response( array $config, int $submission_id ): array {
		$behavior = $config['behavior'] ?? [];
		$message  = $behavior['success_message']
			?? __( 'Thank you! Your sample request has been submitted.', 'samplehq-request-form' );

		return [
			'success'       => true,
			'message'       => $message,
			'submission_id' => $submission_id,
			'status_code'   => 200,
		];
	}

	/**
	 * Build an error response.
	 *
	 * @param string $message     Error message.
	 * @param int    $status_code HTTP status code.
	 * @return array{success: bool, errors: array<string, string>, status_code: int}
	 */
	private function error_response( string $message, int $status_code ): array {
		return [
			'success'     => false,
			'errors'      => [ 'general' => $message ],
			'status_code' => $status_code,
		];
	}
}
