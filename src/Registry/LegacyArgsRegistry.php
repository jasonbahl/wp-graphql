<?php

namespace WPGraphQL\Registry;

/**
 * Class LegacyArgsRegistry
 *
 * Registry for collecting and managing legacy argument transformation rules
 * from field registrations across all GraphQL types.
 *
 * @package WPGraphQL\Registry
 */
class LegacyArgsRegistry {

	/**
	 * Array storing transformation rules for legacy arguments
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $transformation_rules = [];

	/**
	 * Flag to track if rules have been initialized
	 */
	private static bool $rules_initialized = false;

	/**
	 * Register a field transformation rule
	 *
	 * @param string               $type_name The GraphQL type name
	 * @param string               $field_name The field name
	 * @param array<string, mixed> $legacy_args_config The legacy arguments configuration
	 */
	public static function register_field_transformation( string $type_name, string $field_name, array $legacy_args_config ): void {
		$rule = self::parse_legacy_args_config( $legacy_args_config );

		if ( $rule ) {
			$rule_key                                = "{$type_name}.{$field_name}";
			self::$transformation_rules[ $rule_key ] = $rule;
		}
	}

	/**
	 * Get all transformation rules
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_transformation_rules(): array {
		if ( ! self::$rules_initialized ) {
			self::initialize_rules();
		}

		return self::$transformation_rules;
	}

	/**
	 * Initialize rules by triggering schema building if not already done
	 */
	private static function initialize_rules(): void {
		if ( self::$rules_initialized ) {
			return;
		}

		// Force schema building to collect legacy_args
		if ( function_exists( 'WPGraphQL' ) && class_exists( 'WPGraphQL' ) ) {
			\WPGraphQL::get_schema();
		}

		self::$rules_initialized = true;
	}

	/**
	 * Intercept field registration to extract legacy_args configurations
	 *
	 * @param array<string, mixed> $fields The fields array
	 * @param string               $type_name The type name
	 * @return array<string, mixed> The modified fields array
	 */
	public static function intercept_field_registration( array $fields, string $type_name ): array {
		foreach ( $fields as $field_name => $field_config ) {
			if ( isset( $field_config['legacy_args'] ) ) {
				// Register the transformation rule
				self::register_field_transformation( $type_name, $field_name, $field_config['legacy_args'] );

				// Remove legacy_args from the field config so they don't appear in introspection
				unset( $fields[ $field_name ]['legacy_args'] );
			}
		}

		return $fields;
	}

	/**
	 * Parse legacy arguments configuration into a transformation rule
	 *
	 * @param array<string, mixed> $legacy_args_config The legacy arguments configuration
	 * @return array<string, mixed>|null The parsed transformation rule or null if invalid
	 */
	private static function parse_legacy_args_config( array $legacy_args_config ): ?array {
		if ( empty( $legacy_args_config ) ) {
			return null;
		}

		// Extract legacy argument names
		$legacy_arg_names = array_keys( $legacy_args_config );

		// Find the modern argument name (look for maps_to values)
		$modern_arg   = null;
		$transform_fn = null;

		// Look for transformation patterns and target type specifications
		$target_types = [];
		foreach ( $legacy_args_config as $arg_name => $arg_config ) {
			if ( isset( $arg_config['transform'] ) && is_callable( $arg_config['transform'] ) ) {
				$transform_fn = $arg_config['transform'];
			}

			// Check for target type specification
			if ( isset( $arg_config['target_type'] ) ) {
				$target_types[ $arg_name ] = $arg_config['target_type'];
			}

			if ( isset( $arg_config['maps_to'] ) ) {
				$maps_to = $arg_config['maps_to'];
				if ( strpos( $maps_to, '.' ) === false ) {
					$modern_arg = $maps_to;
				} else {
					// Handle dot notation like 'by.id'
					$parts      = explode( '.', $maps_to );
					$modern_arg = $parts[0];
				}
			}
		}

		// If no explicit transform function, create default id/idType transformer
		if ( ! $transform_fn && in_array( 'id', $legacy_arg_names, true ) && in_array( 'idType', $legacy_arg_names, true ) ) {
			$transform_fn = self::create_default_id_transformer();
		}

		if ( ! $modern_arg || ! $transform_fn ) {
			return null;
		}

		return [
			'legacy_args'  => $legacy_arg_names,
			'modern_arg'   => $modern_arg,
			'transform'    => $transform_fn,
			'target_types' => $target_types,
		];
	}

	/**
	 * Create default transformer for id/idType pattern
	 *
	 * @return callable The transformation function
	 */
	private static function create_default_id_transformer(): callable {
		return static function ( array $legacy_values ): array {
			$by_value = [];

			if ( isset( $legacy_values['id'] ) ) {
				$id_type  = $legacy_values['idType'] ?? 'ID';
				$id_value = $legacy_values['id'];

				switch ( $id_type ) {
					case 'database_id':
					case 'DATABASE_ID':
						$by_value['databaseId'] = (int) $id_value;
						break;
					case 'global_id':
					case 'ID':
					case 'id':
					default:
						$by_value['id'] = $id_value;
						break;
				}
			}

			return $by_value;
		};
	}
}
