<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\EnumType;

/**
 * Class WPEnumType
 *
 * EnumTypes should extend this class to have filters and sorting applied, etc.
 *
 * @package WPGraphQL\Type
 */
class WPEnumType extends EnumType {

	/**
	 * WPEnumType constructor.
	 *
	 * @param array<string,mixed> $config
	 */
	public function __construct( $config ) {
		$name             = ucfirst( $config['name'] );
		$config['name']   = apply_filters( 'graphql_type_name', $name, $config, $this );
		$config['values'] = self::prepare_values( $config['values'], $config['name'] );
		parent::__construct( $config );
	}

	/**
	 * Generate a safe / sanitized Enum value from a string.
	 *
	 * @param  string $value Enum value.
	 * @return string
	 */
	public static function get_safe_name( string $value ) {
		$sanitized_enum_name = graphql_format_name( $value, '_' );

		// If the sanitized name is empty, we want to return the original value so it displays in the error.
		if ( ! empty( $sanitized_enum_name ) ) {
			$value = $sanitized_enum_name;
		}

		$safe_name = strtoupper( $value );

		// Enum names must start with a letter or underscore.
		if ( ! preg_match( '#^[_a-zA-Z]#', $safe_name ) ) {
			return '_' . $safe_name;
		}

		return $safe_name;
	}

	/**
	 * This function sorts the values and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the enum.
	 *
	 * @param array<string,mixed> $values
	 * @param string              $type_name
	 * @return array<string,mixed>
	 * @since 0.0.5
	 */
	private static function prepare_values( $values, $type_name ) {

		// map over the values and if the description is a callable, call it
		foreach ( $values as $key => $value ) {
			$formatted_key = self::get_safe_name( $key );
			$values[ $key ]['description'] = self::get_value_description( $value, $formatted_key, $type_name );
		}

		/**
		 * Filter all object fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param array<string,mixed> $values
		 */
		$values = apply_filters( 'graphql_enum_values', $values );

		/**
		 * Pass the values through a filter
		 *
		 * Filter for lcfirst( $type_name ) was added for backward compatibility
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array<string,mixed> $values
		 *
		 * @since 0.0.5
		 */
		$values = apply_filters( 'graphql_' . lcfirst( $type_name ) . '_values', $values );
		$values = apply_filters( 'graphql_' . $type_name . '_values', $values );

		/**
		 * Sort the values alphabetically by key. This makes reading through docs much easier
		 *
		 * @since 0.0.5
		 */
		ksort( $values );

		// wp_send_json([
		// 	'values' => $values
		// ]);

		/**
		 * Return the filtered, sorted $fields
		 *
		 * @since 0.0.5
		 */
		return $values;
	}

	private static function get_value_description( $value, $key, $enum_type ) {

		codecept_debug([
			'value' => $value,
			'key' => $key,
			'enum_type' => $enum_type
		]);

		$value_name = $key;
		$value_description = $value['description'] ?? '';

		/**
		 * Filter the description for an enum value.
		 *
		 * @param null|string $pre_value_description The pre-filtered description.
		 * @param string $enum_type The name of the enum type.
		 * @param string $value The value of the enum.
		 */
		$pre_value_description = apply_filters(
			'graphql_pre_enum_value_description',
			null,
			$value_description,
			$value_name,
			$enum_type
		);

		if ( null !== $pre_value_description ) {
			return $pre_value_description;
		}

		if ( ! empty( $value_description ) && is_callable( $value_description ) ) {
			return $value_description();
		}
		return is_string( $value_description ) ? $value_description : '';

	}

}