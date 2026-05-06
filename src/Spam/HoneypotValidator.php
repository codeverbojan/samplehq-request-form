<?php
/**
 * Honeypot field validator.
 *
 * @package SampleHQForm\Spam
 */

declare( strict_types=1 );

namespace SampleHQForm\Spam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates the honeypot hidden field.
 *
 * The honeypot field (shqf_hp) is rendered as a hidden text input that should
 * always be empty. Bots that fill all form fields will trigger this check.
 */
class HoneypotValidator {

	/**
	 * Check if the honeypot field was filled (indicating a bot).
	 *
	 * @param string $value The submitted honeypot field value.
	 * @return bool True if the submission is likely from a bot.
	 */
	public function is_spam( string $value ): bool {
		return '' !== trim( $value );
	}
}
