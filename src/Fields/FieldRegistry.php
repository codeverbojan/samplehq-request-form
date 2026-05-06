<?php
/**
 * Field type registry.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry for field types.
 *
 * Stores and retrieves field type implementations by their type identifier.
 */
class FieldRegistry {

	/**
	 * Registered field types.
	 *
	 * @var array<string, FieldInterface>
	 */
	private array $fields = [];

	/**
	 * Register a field type.
	 *
	 * @param FieldInterface $field The field type instance.
	 * @return void
	 */
	public function register( FieldInterface $field ): void {
		$this->fields[ $field->get_type() ] = $field;
	}

	/**
	 * Get a field type by its identifier.
	 *
	 * @param string $type Field type name.
	 * @return FieldInterface|null The field instance or null if not registered.
	 */
	public function get( string $type ): ?FieldInterface {
		return $this->fields[ $type ] ?? null;
	}

	/**
	 * Check if a field type is registered.
	 *
	 * @param string $type Field type name.
	 * @return bool True if registered.
	 */
	public function has( string $type ): bool {
		return isset( $this->fields[ $type ] );
	}

	/**
	 * Get all registered field type names.
	 *
	 * @return string[] Array of type identifiers.
	 */
	public function get_types(): array {
		return array_keys( $this->fields );
	}
}
