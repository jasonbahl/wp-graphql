<?php

namespace WPGraphQL\Utils;

use WPGraphQL\Registry\LegacyArgsRegistry;

/**
 * Class ExecutionTimeTransformer
 *
 * Handles transformation of legacy arguments at execution time,
 * after GraphQL variables have been resolved.
 *
 * @package WPGraphQL\Utils
 */
class ExecutionTimeTransformer {

	/**
	 * Transform legacy arguments at execution time
	 *
	 * This method is called via the graphql_pre_resolve_field filter
	 * and transforms legacy arguments after variable substitution.
	 *
	 * @param mixed                                    $nil            Unique nil value
	 * @param mixed                                    $source         The source passed down the Resolve Tree
	 * @param array<string,mixed>                      $args           The args for the field
	 * @param \WPGraphQL\AppContext                    $context        The AppContext passed down the ResolveTree
	 * @param \GraphQL\Type\Definition\ResolveInfo     $info           The ResolveInfo passed down the ResolveTree
	 * @param string                                   $type_name      The name of the type the fields belong to
	 * @param string                                   $field_key      The name of the field
	 * @param \GraphQL\Type\Definition\FieldDefinition $field          The Field Definition for the resolving field
	 * @param ?callable                                $field_resolver The default field resolver
	 * @return mixed Returns $nil to continue normal execution, or a different value to override
	 */
	public static function transform_field_args( $nil, $source, array $args, $context, $info, string $type_name, string $field_key, $field, $field_resolver ) {
		// Get transformation rules for this field
		$transformation_key   = $type_name . '.' . $field_key;
		$transformation_rules = LegacyArgsRegistry::get_transformation_rules();

		if ( ! isset( $transformation_rules[ $transformation_key ] ) ) {
			return $nil; // No transformation needed
		}

		$rule             = $transformation_rules[ $transformation_key ];
		$transform_fn     = $rule['transform'];
		$legacy_arg_names = $rule['legacy_args'];
		$modern_arg       = $rule['modern_arg'];

		// Check if this request contains legacy arguments
		$legacy_values   = [];
		$has_legacy_args = false;

		foreach ( $legacy_arg_names as $legacy_arg_name ) {
			if ( isset( $args[ $legacy_arg_name ] ) ) {
				$legacy_values[ $legacy_arg_name ] = $args[ $legacy_arg_name ];
				$has_legacy_args                   = true;
			}
		}

		if ( ! $has_legacy_args ) {
			return $nil; // No legacy args present
		}

		// Don't transform if modern arg is already present
		if ( isset( $args[ $modern_arg ] ) ) {
			return $nil; // Modern arg takes precedence
		}

		// Transform legacy arguments to modern equivalent
		$transform_result = $transform_fn( $legacy_values );

		// Extract the transformed value
		if ( is_array( $transform_result ) && isset( $transform_result['value'] ) ) {
			$modern_value = $transform_result['value'];
		} else {
			// Legacy behavior - transform function returns only the value
			$modern_value = $transform_result;
		}

		// Create new args array with modern argument
		$new_args = $args;

		// Remove legacy arguments
		foreach ( $legacy_arg_names as $legacy_arg_name ) {
			unset( $new_args[ $legacy_arg_name ] );
		}

		// Add modern argument
		$new_args[ $modern_arg ] = $modern_value;

		// Call the resolver with transformed arguments
		if ( null === $field_resolver ) {
			return \GraphQL\Executor\Executor::defaultFieldResolver( $source, $new_args, $context, $info );
		} else {
			return $field_resolver( $source, $new_args, $context, $info );
		}
	}
}
