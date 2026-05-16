<?php
/**
 * Maps plugin submission data to the platform's API schema.
 *
 * @package SampleHQForm\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Connection;

use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Forms\FormValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transforms plugin submission + form config into a platform-compatible payload.
 *
 * Priority system: field TYPE takes precedence over key name. A hidden field
 * with key "company" goes to custom_fields, not the standard company column.
 */
class DataMapper {

	/**
	 * Field types that are always skipped (never sent to platform).
	 *
	 * @var string[]
	 */
	private const SKIP_TYPES = [ 'file_upload', 'html' ];

	/**
	 * Field types that always route to custom_fields regardless of key name.
	 *
	 * @var string[]
	 */
	private const CUSTOM_ONLY_TYPES = [ 'hidden', 'consent', 'checkbox', 'radio', 'select', 'number', 'date' ];

	/**
	 * Keys accepted by the platform's STANDARD_FIELDS allowlist.
	 * Only text/textarea types are eligible for key-based mapping.
	 * 'notes' is included (platform maps it to the 'message' column internally).
	 *
	 * @var string[]
	 */
	private const STANDARD_KEYS = [ 'email', 'company', 'phone', 'job_title', 'address', 'notes', 'first_name', 'last_name' ];

	/**
	 * Samples table for looking up shq_sample_id mappings.
	 *
	 * @var SamplesTable
	 */
	private SamplesTable $samples;

	/**
	 * Constructor.
	 *
	 * @param SamplesTable $samples Samples repository for ID rewriting.
	 */
	public function __construct( SamplesTable $samples ) {
		$this->samples = $samples;
	}

	/**
	 * Map a plugin submission to the platform API payload.
	 *
	 * @param array<string, mixed> $submission  Submission row from shqf_submissions.
	 * @param array<string, mixed> $meta        All submission meta (field_key => field_value).
	 * @param array<string, mixed> $form        Form row from shqf_forms (with decoded config).
	 * @return array<string, mixed> Platform-ready payload for POST /plugin/submissions.
	 */
	public function map( array $submission, array $meta, array $form ): array {
		$config     = $form['config'] ?? [];
		$field_defs = $this->get_field_defs( $config );
		$type_map   = $this->build_type_map( $field_defs );

		$standard      = [];
		$custom_fields = [];
		$samples       = [];

		foreach ( $meta as $key => $value ) {
			$type = $type_map[ $key ] ?? null;

			if ( null !== $type && in_array( $type, self::SKIP_TYPES, true ) ) {
				continue;
			}

			if ( 'sample_picker' === $type ) {
				$samples = array_merge( $samples, $this->rewrite_samples( $value, $config, $key ) );
				continue;
			}

			if ( 'name' === $type ) {
				$this->split_composite_name( $value, $standard );
				continue;
			}

			if ( 'email' === $type ) {
				$standard['email'] = (string) $value;
				continue;
			}

			if ( 'phone' === $type ) {
				$standard['phone'] = (string) $value;
				continue;
			}

			if ( 'address' === $type ) {
				$decoded             = json_decode( (string) $value, true );
				$standard['address'] = is_array( $decoded ) ? $decoded : (string) $value;
				continue;
			}

			if ( null !== $type && in_array( $type, self::CUSTOM_ONLY_TYPES, true ) ) {
				$encoded   = wp_json_encode( $value );
				$str_value = is_array( $value ) ? ( false !== $encoded ? $encoded : '' ) : (string) $value;
				if ( '' !== $str_value ) {
					$custom_fields[ $key ] = $str_value;
				}
				continue;
			}

			if ( $this->is_standard_key( $key ) && $this->is_key_eligible_type( $type ) ) {
				$standard[ $key ] = (string) $value;
				continue;
			}

			if ( '' !== (string) $value ) {
				$custom_fields[ $key ] = (string) $value;
			}
		}

		$standard['email'] ??= (string) ( $submission['email'] ?? '' );

		$all_fields = array_merge( $standard, $custom_fields );

		$payload = [
			'plugin_form_id'       => (int) ( $form['id'] ?? 0 ),
			'plugin_form_name'     => (string) ( $form['title'] ?? '' ),
			'plugin_submission_id' => (int) ( $submission['id'] ?? 0 ),
			'email'                => $standard['email'] ?? '',
			'fields'               => $all_fields,
			'samples'              => $samples,
		];

		if ( isset( $submission['source_url'] ) && '' !== $submission['source_url'] ) {
			$payload['source_url'] = (string) $submission['source_url'];
		}

		if ( isset( $submission['created_at'] ) && '' !== $submission['created_at'] ) {
			$payload['created_at'] = (string) $submission['created_at'];
		}

		return $payload;
	}

	/**
	 * Build field_schema array from form config for shadow form metadata.
	 *
	 * @param array<string, mixed> $config Form config (decoded JSON).
	 * @return array<int, array{key: string, type: string, label: string}> Schema entries.
	 */
	public function build_field_schema( array $config ): array {
		$field_defs = $this->get_field_defs( $config );
		$schema     = [];

		foreach ( $field_defs as $def ) {
			$key   = $def['key'] ?? '';
			$type  = $def['type'] ?? '';
			$label = $def['label'] ?? '';

			if ( '' === $key || '' === $type ) {
				continue;
			}

			if ( in_array( $type, self::SKIP_TYPES, true ) ) {
				continue;
			}

			$schema[] = [
				'key'   => $key,
				'type'  => $type,
				'label' => $label,
			];
		}

		return $schema;
	}

	/**
	 * Get flattened field definitions from the form config.
	 *
	 * @param array<string, mixed> $config Form config.
	 * @return array<int, array<string, mixed>> Flat field definitions.
	 */
	private function get_field_defs( array $config ): array {
		$fields = $config['fields'] ?? [];

		return FormValidator::flatten_fields( $fields );
	}

	/**
	 * Build a lookup map from field key to field type.
	 *
	 * @param array<int, array<string, mixed>> $field_defs Flattened field definitions.
	 * @return array<string, string> Key => type.
	 */
	private function build_type_map( array $field_defs ): array {
		$map = [];

		foreach ( $field_defs as $def ) {
			$key  = $def['key'] ?? $def['id'] ?? '';
			$type = $def['type'] ?? '';

			if ( '' !== $key && '' !== $type ) {
				$map[ $key ] = $type;
			}
		}

		return $map;
	}

	/**
	 * Check if a key matches a standard platform column name.
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	private function is_standard_key( string $key ): bool {
		return in_array( $key, self::STANDARD_KEYS, true );
	}

	/**
	 * Check if a field type is eligible for key-based convention mapping.
	 *
	 * Only text and textarea types map by key convention. Null type (unknown
	 * field, no config) also maps by key convention as a fallback.
	 *
	 * @param string|null $type Field type.
	 * @return bool
	 */
	private function is_key_eligible_type( ?string $type ): bool {
		return null === $type || 'text' === $type || 'textarea' === $type;
	}

	/**
	 * Split a composite name field value into first_name and last_name.
	 *
	 * @param mixed                $value    The stored value (JSON string or array).
	 * @param array<string, mixed> &$standard Standard fields being built.
	 */
	private function split_composite_name( mixed $value, array &$standard ): void {
		$decoded = is_string( $value ) ? json_decode( $value, true ) : $value;

		if ( ! is_array( $decoded ) ) {
			return;
		}

		if ( isset( $decoded['first_name'] ) && '' !== (string) $decoded['first_name'] ) {
			$standard['first_name'] = (string) $decoded['first_name'];
		}

		if ( isset( $decoded['last_name'] ) && '' !== (string) $decoded['last_name'] ) {
			$standard['last_name'] = (string) $decoded['last_name'];
		}
	}

	/**
	 * Rewrite sample IDs from plugin namespace to platform namespace.
	 *
	 * @param mixed                $value  The stored sample picker value (JSON string or array).
	 * @param array<string, mixed> $config Form config (for determining sample source).
	 * @param string               $key    The field key (to find config entry).
	 * @return array<int, array<string, mixed>> Platform-format samples.
	 */
	private function rewrite_samples( mixed $value, array $config, string $key ): array {
		$items = is_string( $value ) ? json_decode( $value, true ) : $value;

		if ( ! is_array( $items ) ) {
			return [];
		}

		$field_config = $this->find_field_config( $config, $key );
		$source       = $field_config['config']['source'] ?? 'library';

		$result = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$local_id = (int) ( $item['id'] ?? 0 );
			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );

			if ( 'woocommerce' === ( $item['source'] ?? $source ) ) {
				$result[] = [
					'id'       => null,
					'quantity' => $quantity,
					'name'     => (string) ( $item['name'] ?? '' ),
					'sku'      => (string) ( $item['sku'] ?? '' ),
					'source'   => 'woocommerce',
				];
				continue;
			}

			if ( $local_id <= 0 ) {
				continue;
			}

			$sample = $this->samples->get( $local_id );

			if ( null === $sample ) {
				continue;
			}

			$shq_id = isset( $sample['shq_sample_id'] ) ? (int) $sample['shq_sample_id'] : 0;

			if ( $shq_id > 0 ) {
				$result[] = [
					'id'       => $shq_id,
					'quantity' => $quantity,
				];
			} else {
				$result[] = [
					'id'               => null,
					'quantity'         => $quantity,
					'name'             => (string) ( $sample['name'] ?? '' ),
					'sku'              => (string) ( $sample['sku'] ?? '' ),
					'plugin_sample_id' => $local_id,
				];
			}
		}

		return $result;
	}

	/**
	 * Find the field config entry for a given key.
	 *
	 * @param array<string, mixed> $config Form config.
	 * @param string               $key    Field key to find.
	 * @return array<string, mixed> The field config or empty array.
	 */
	private function find_field_config( array $config, string $key ): array {
		$field_defs = $this->get_field_defs( $config );

		foreach ( $field_defs as $def ) {
			$def_key = $def['key'] ?? $def['id'] ?? '';
			if ( $def_key === $key ) {
				return $def;
			}
		}

		return [];
	}
}
