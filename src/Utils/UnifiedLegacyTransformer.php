<?php

namespace WPGraphQL\Utils;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use WPGraphQL\Registry\LegacyArgsRegistry;

/**
 * Class UnifiedLegacyTransformer
 *
 * Single transformation system that handles both query structure and variable values
 * for legacy argument compatibility.
 *
 * @package WPGraphQL\Utils
 */
class UnifiedLegacyTransformer {

	/**
	 * Transform a GraphQL request (query + variables) to handle legacy arguments
	 *
	 * @param string               $query The GraphQL query string
	 * @param array<string, mixed> $variables The query variables
	 * @return array{query: string, variables: array<string, mixed>} Transformed query and variables
	 */
	public static function transform_request( string $query, array $variables = [] ): array {
		try {
			// Parse the query to AST
			$ast = Parser::parse( $query );

			// Get transformation rules
			$transformation_rules = LegacyArgsRegistry::get_transformation_rules();

			if ( empty( $transformation_rules ) ) {
				return [
					'query'     => $query,
					'variables' => $variables,
				];
			}

			// Quick check: if query doesn't contain legacy field names, skip transformation
			$has_potential_legacy_fields = false;
			foreach ( $transformation_rules as $rule_key => $rule ) {
				list( , $field_name ) = explode( '.', $rule_key );
				if ( strpos( $query, $field_name ) !== false ) {
					$has_potential_legacy_fields = true;
					break;
				}
			}

			if ( ! $has_potential_legacy_fields ) {
				return [
					'query'     => $query,
					'variables' => $variables,
				];
			}

			// Track transformations needed
			$transformations = [];
			$new_variables   = [];

			// First pass: Analyze the query to find legacy argument usage
			Visitor::visit(
				$ast,
				/** @phpstan-ignore-next-line */
				[
					'Field' => [
						'enter' => static function ( FieldNode $node ) use ( $transformation_rules, $variables, &$transformations ) {
							// Check if this field has transformation rules
							$field_name = $node->name->value;

							// Try to match transformation rules (we don't have type context here, so check all)
							foreach ( $transformation_rules as $rule_key => $rule ) {
								if ( strpos( $rule_key, '.' . $field_name ) !== false ) {
									$legacy_args = $rule['legacy_args'];

									// Check if this field uses legacy arguments
									$has_legacy_args = false;
									$legacy_values   = [];

									foreach ( $node->arguments as $arg ) {
										$arg_name = $arg->name->value;
										if ( in_array( $arg_name, $legacy_args, true ) ) {
											$has_legacy_args            = true;
											$legacy_values[ $arg_name ] = self::extract_argument_value( $arg->value, $variables );
										}
									}

									if ( $has_legacy_args ) {
										// Create a unique key for this specific field node based on its position and arguments
										// Use a combination of field name and the actual argument nodes to ensure uniqueness
										$arg_signature = [];
										foreach ( $node->arguments as $arg ) {
											$arg_signature[ $arg->name->value ] = $arg->value->kind . ':' . ( $arg->value->value ?? $arg->value->name->value ?? 'complex' );
										}
										ksort( $arg_signature );
										$node_key                     = $field_name . ':' . wp_json_encode( $arg_signature );
										$transformations[ $node_key ] = [
											'field_name' => $field_name,
											'rule'       => $rule,
											'legacy_values' => $legacy_values,
										];
									}
									break;
								}
							}
						},
					],
				]
			);

			// If no transformations needed, return original
			if ( empty( $transformations ) ) {
				return [
					'query'     => $query,
					'variables' => $variables,
				];
			}

			// Second pass: Transform the AST and generate new variables
			$transformed_ast = Visitor::visit(
				$ast,
				/** @phpstan-ignore-next-line */
				[
					'Field' => [
						'leave' => static function ( FieldNode $node ) use ( $transformations, &$new_variables ) {
							// Check if this node needs transformation
							$field_name = $node->name->value;

							// Create node key based on argument structure (same as first pass)
							$arg_signature = [];
							foreach ( $node->arguments as $arg ) {
								$arg_signature[ $arg->name->value ] = $arg->value->kind . ':' . ( $arg->value->value ?? $arg->value->name->value ?? 'complex' );
							}
							ksort( $arg_signature );
							$node_key = $field_name . ':' . wp_json_encode( $arg_signature );

							if ( isset( $transformations[ $node_key ] ) ) {
								return self::transform_field_node( $node, $transformations[ $node_key ], $new_variables );
							}

							return $node;
						},
					],
				]
			);

			// Third pass: Generate new variable declarations that match the transformed query
			$final_ast = self::generate_new_variable_declarations( $transformed_ast, $new_variables );

			// Convert back to query string
			$transformed_query = Printer::doPrint( $final_ast );

			// Extract just the values from the new variables
			$final_variables = [];
			foreach ( $new_variables as $var_name => $var_info ) {
				$final_variables[ $var_name ] = $var_info['value'];
			}

			return [
				'query'     => $transformed_query,
				'variables' => $final_variables,
			];
		} catch ( \Throwable $e ) {
			// If transformation fails, return original query silently
			return [
				'query'     => $query,
				'variables' => $variables,
			];
		}
	}

	/**
	 * Extract value from an argument, resolving variables if needed
	 *
	 * @param mixed                $value_node The argument value node
	 * @param array<string, mixed> $variables Available variables
	 * @return mixed The resolved value
	 */
	private static function extract_argument_value( $value_node, array $variables ) {
		if ( $value_node instanceof VariableNode ) {
			$var_name = $value_node->name->value;
			return $variables[ $var_name ] ?? null;
		} elseif ( $value_node instanceof StringValueNode ) {
			return $value_node->value;
		} elseif ( $value_node instanceof \GraphQL\Language\AST\IntValueNode ) {
			return (int) $value_node->value;
		} elseif ( $value_node instanceof \GraphQL\Language\AST\FloatValueNode ) {
			return (float) $value_node->value;
		} elseif ( $value_node instanceof \GraphQL\Language\AST\BooleanValueNode ) {
			return $value_node->value;
		} elseif ( $value_node instanceof \GraphQL\Language\AST\EnumValueNode ) {
			return $value_node->value;
		}
		// Add other value types as needed
		return null;
	}

	/**
	 * Transform a field node with legacy arguments to use modern arguments
	 *
	 * @param \GraphQL\Language\AST\FieldNode $node The field node to transform
	 * @param array<string, mixed>            $transformation Transformation info
	 * @param array<string, mixed>            $new_variables Variables array (modified by reference)
	 * @return \GraphQL\Language\AST\FieldNode The transformed field node
	 */
	private static function transform_field_node( FieldNode $node, array $transformation, array &$new_variables ): FieldNode {
		$rule          = $transformation['rule'];
		$legacy_values = $transformation['legacy_values'];
		$transform_fn  = $rule['transform'];
		$modern_arg    = $rule['modern_arg'];
		$legacy_args   = $rule['legacy_args'];

		// Apply transformation function
		$transform_result = $transform_fn( $legacy_values );
		$modern_value     = is_array( $transform_result ) && isset( $transform_result['value'] )
			? $transform_result['value']
			: $transform_result;

		// Create new arguments array
		$new_arguments = [];

		// Keep non-legacy arguments
		foreach ( $node->arguments as $arg ) {
			if ( ! in_array( $arg->name->value, $legacy_args, true ) ) {
				$new_arguments[] = $arg;
			}
		}

		// Generate a new variable for the modern argument
		$var_name = $modern_arg . '_' . uniqid();
		$var_type = self::determine_variable_type( $modern_arg, $transformation['field_name'] );

		// Store the new variable info
		$new_variables[ $var_name ] = [
			'type'  => $var_type,
			'value' => $modern_value,
		];

		// Add modern argument using the new variable
		$modern_arg_node = new ArgumentNode(
			[
				'name'  => new \GraphQL\Language\AST\NameNode( [ 'value' => $modern_arg ] ),
				'value' => new \GraphQL\Language\AST\VariableNode(
					[
						'name' => new \GraphQL\Language\AST\NameNode( [ 'value' => $var_name ] ),
					]
				),
			]
		);

		$new_arguments[] = $modern_arg_node;

		// Return transformed field node
		return new FieldNode(
			[
				'name'         => $node->name,
				'alias'        => $node->alias,
				'arguments'    => new NodeList( $new_arguments ),
				'directives'   => $node->directives,
				'selectionSet' => $node->selectionSet,
				'loc'          => $node->loc,
			]
		);
	}

	/**
	 * Determine the GraphQL type for a variable based on the argument name and field context
	 *
	 * @param string $arg_name The argument name
	 * @param string $field_name The field name for context
	 * @return string The GraphQL type
	 */
	private static function determine_variable_type( string $arg_name, string $field_name ): string {
		if ( 'by' === $arg_name ) {
			switch ( $field_name ) {
				case 'user':
					return 'UserBy!';
				case 'comment':
					return 'CommentBy!';
				default:
					return 'String!';
			}
		}

		return 'String';
	}


	/**
	 * Generate new variable declarations that match the transformed query
	 *
	 * @param mixed                $ast The GraphQL AST
	 * @param array<string, mixed> $new_variables Array of new variables and their values
	 * @return mixed The AST with updated variable declarations
	 */
	private static function generate_new_variable_declarations( $ast, array $new_variables ) {
		return Visitor::visit(
			$ast,
			[
				'OperationDefinition' => [
					'leave' => static function ( $node ) use ( $new_variables ) {
						if ( empty( $new_variables ) ) {
							// No new variables needed, remove all variable definitions
							$new_node                      = clone $node;
							$new_node->variableDefinitions = new NodeList( [] );
							return $new_node;
						}

						// Create new variable definitions based on the new variables
						$new_definitions = [];
						foreach ( $new_variables as $var_name => $var_info ) {
							$new_definitions[] = new \GraphQL\Language\AST\VariableDefinitionNode(
								[
									'variable' => new \GraphQL\Language\AST\VariableNode(
										[
											'name' => new \GraphQL\Language\AST\NameNode( [ 'value' => $var_name ] ),
										]
									),
									'type'     => self::create_type_node( $var_info['type'] ),
								]
							);
						}

						// Create new operation definition with new variable definitions
						$new_node                      = clone $node;
						$new_node->variableDefinitions = new NodeList( $new_definitions );
						return $new_node;
					},
				],
			]
		);
	}

	/**
	 * Create a GraphQL type node from a type string
	 *
	 * @param string $type_string The type string (e.g., 'String!', 'UserBy!')
	 * @return mixed The GraphQL type node
	 */
	private static function create_type_node( string $type_string ) {
		// Handle non-null types
		if ( str_ends_with( $type_string, '!' ) ) {
			$inner_type = substr( $type_string, 0, -1 );
			return new \GraphQL\Language\AST\NonNullTypeNode(
				[
					'type' => self::create_type_node( $inner_type ),
				]
			);
		}

		// Handle named types
		return new \GraphQL\Language\AST\NamedTypeNode(
			[
				'name' => new \GraphQL\Language\AST\NameNode( [ 'value' => $type_string ] ),
			]
		);
	}
}
