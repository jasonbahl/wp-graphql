<?php

namespace WPGraphQL\Utils;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use WPGraphQL\Registry\LegacyArgsRegistry;

/**
 * Class QueryTransformer
 *
 * Transforms GraphQL queries at the AST level to handle legacy argument mappings.
 * This allows deprecated arguments to be transparently converted to modern equivalents
 * while maintaining backward compatibility.
 *
 * @package WPGraphQL\Utils
 */
class QueryTransformer {

	/**
	 * Array of transformation rules loaded from the registry
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $transformation_rules = [];

	/**
	 * Array tracking variable transformations that need to be applied
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $variable_transformations = [];

	/**
	 * Array tracking variables that should be removed from operation definitions
	 *
	 * @var array<string, bool>
	 */
	private array $variables_to_remove = [];

	/**
	 * Transform a GraphQL query string by applying legacy argument mappings
	 *
	 * @param string $query The original GraphQL query string
	 * @return string The transformed query string
	 */
	public function transform_query( string $query ): string {
		try {
			// Parse the query into an AST
			$ast = Parser::parse( $query );

			// Transform the AST
			$transformed_ast = $this->transform_ast( $ast );

			// Convert the AST back to a query string
			return Printer::doPrint( $transformed_ast );
		} catch ( \Throwable $e ) {
			// If transformation fails, return the original query
			// error_log( 'Query transformation failed: ' . $e->getMessage() );
			return $query;
		}
	}

	/**
	 * Transform the AST by applying legacy argument mappings
	 *
	 * @param \GraphQL\Language\AST\DocumentNode $ast The parsed GraphQL AST
	 * @return \GraphQL\Language\AST\DocumentNode The transformed AST
	 */
	private function transform_ast( DocumentNode $ast ): DocumentNode {
		// Load transformation rules dynamically
		$this->transformation_rules = LegacyArgsRegistry::get_transformation_rules();

		// Track variable usage transformations for later variable declaration updates
		$this->variable_transformations = [];

		// Use GraphQL-PHP's visitor pattern to traverse and transform the AST
		return Visitor::visit(
			$ast,
			[
				'Field'               => [
					'leave' => function ( Node $node ) {
						if ( $node instanceof FieldNode ) {
							return $this->transform_field_node( $node );
						}
						return $node;
					},
				],
				'OperationDefinition' => [
					'leave' => function ( Node $node ) {
						if ( $node instanceof OperationDefinitionNode ) {
							return $this->transform_operation_variables( $node );
						}
						return $node;
					},
				],
			]
		);
	}

	/**
	 * Transform a field node by applying legacy argument mappings
	 *
	 * @param \GraphQL\Language\AST\FieldNode $field_node The field node to transform
	 * @return \GraphQL\Language\AST\FieldNode The transformed field node
	 */
	private function transform_field_node( FieldNode $field_node ): FieldNode {
		$field_name = $field_node->name->value;

		// Try to find a transformation rule for this field
		// We check multiple possible type contexts since we don't have type context here
		$rule = null;

		// Check all registered transformation rules for this field name
		// Since we don't have type context in the AST visitor, we need to check all possible matches
		foreach ( $this->transformation_rules as $test_key => $test_rule ) {
			$parts = explode( '.', $test_key );
			if ( count( $parts ) === 2 && $parts[1] === $field_name ) {
				$rule = $test_rule;
				break;
			}
		}

		if ( ! $rule ) {
			return $field_node; // No transformation rule for this field
		}

		$transform_fn     = $rule['transform'];
		$legacy_arg_names = $rule['legacy_args'];
		$modern_arg       = $rule['modern_arg'];

		// Extract legacy argument values from the field node
		$legacy_values   = [];
		$has_legacy_args = false;

		foreach ( $field_node->arguments as $arg ) {
			if ( in_array( $arg->name->value, $legacy_arg_names, true ) ) {
				$legacy_values[ $arg->name->value ] = $this->extract_argument_value( $arg->value );
				$has_legacy_args                    = true;
			}
		}

		if ( ! $has_legacy_args ) {
			return $field_node; // No legacy args, no transformation needed
		}

		// Transform legacy arguments to modern equivalent
		$transform_result = $transform_fn( $legacy_values );

		// Check if transform function returned enhanced result with target types
		if ( is_array( $transform_result ) && isset( $transform_result['value'] ) && isset( $transform_result['target_types'] ) ) {
			$modern_value         = $transform_result['value'];
			$rule['target_types'] = $transform_result['target_types'];
		} else {
			// Legacy behavior - transform function returns only the value
			$modern_value = $transform_result;
		}

		// Track variable transformations for later variable declaration updates
		$this->track_variable_transformations( $legacy_values, $modern_value, $rule );

		// Create new arguments array without legacy args but with modern arg
		$new_arguments = [];

		// Keep non-legacy arguments
		foreach ( $field_node->arguments as $arg ) {
			if ( ! in_array( $arg->name->value, $legacy_arg_names, true ) ) {
				$new_arguments[] = $arg;
			}
		}

		// Add the modern argument with transformed value
		$modern_arg_value = $this->create_object_value_node( $modern_value );
		$new_arguments[]  = new ArgumentNode(
			[
				'name'  => new \GraphQL\Language\AST\NameNode( [ 'value' => $modern_arg ] ),
				'value' => $modern_arg_value,
			]
		);

		// Create new field node with transformed arguments
		$node_list = new NodeList( $new_arguments );

		return new FieldNode(
			[
				'name'         => $field_node->name,
				'alias'        => $field_node->alias,
				'arguments'    => $node_list,
				'directives'   => $field_node->directives,
				'selectionSet' => $field_node->selectionSet,
				'loc'          => $field_node->loc,
			]
		);
	}

	/**
	 * Extract the value from an argument value node
	 *
	 * @param mixed $value_node The argument value node
	 * @return mixed The extracted value
	 */
	private function extract_argument_value( $value_node ) {
		if ( $value_node instanceof \GraphQL\Language\AST\StringValueNode ) {
			return $value_node->value;
		} elseif ( $value_node instanceof \GraphQL\Language\AST\IntValueNode ) {
			return (int) $value_node->value;
		} elseif ( $value_node instanceof \GraphQL\Language\AST\FloatValueNode ) {
			return (float) $value_node->value;
		} elseif ( $value_node instanceof \GraphQL\Language\AST\BooleanValueNode ) {
			return $value_node->value;
		} elseif ( $value_node instanceof \GraphQL\Language\AST\EnumValueNode ) {
			return $value_node->value;
		} elseif ( $value_node instanceof VariableNode ) {
			// Return a special marker for variables so we can track them
			return [ '_variable' => $value_node ];
		}

		return null;
	}

	/**
	 * Create an ObjectValueNode from an array of values
	 *
	 * @param array<string, mixed> $values Array of field names to values
	 * @return \GraphQL\Language\AST\ObjectValueNode The created object value node
	 */
	private function create_object_value_node( array $values ): ObjectValueNode {
		$fields = [];

		foreach ( $values as $field_name => $field_value ) {
			if ( is_array( $field_value ) && isset( $field_value['_variable'] ) ) {
				// This is a variable reference
				$value_node = $field_value['_variable'];
			} elseif ( is_string( $field_value ) ) {
				$value_node = new \GraphQL\Language\AST\StringValueNode( [ 'value' => $field_value ] );
			} elseif ( is_int( $field_value ) ) {
				$value_node = new \GraphQL\Language\AST\IntValueNode( [ 'value' => (string) $field_value ] );
			} elseif ( is_float( $field_value ) ) {
				$value_node = new \GraphQL\Language\AST\FloatValueNode( [ 'value' => (string) $field_value ] );
			} elseif ( is_bool( $field_value ) ) {
				$value_node = new \GraphQL\Language\AST\BooleanValueNode( [ 'value' => $field_value ] );
			} else {
				$value_node = new \GraphQL\Language\AST\StringValueNode( [ 'value' => (string) $field_value ] );
			}

			$fields[] = new ObjectFieldNode(
				[
					'name'  => new \GraphQL\Language\AST\NameNode( [ 'value' => $field_name ] ),
					'value' => $value_node,
				]
			);
		}

		$node_list = new NodeList( $fields );

		return new ObjectValueNode( [ 'fields' => $node_list ] );
	}

	/**
	 * Track variable transformations that need to be applied to variable declarations
	 *
	 * @param array<string, mixed> $legacy_values The original legacy argument values
	 * @param array<string, mixed> $modern_value The transformed modern values
	 * @param array<string, mixed> $rule The transformation rule
	 */
	private function track_variable_transformations( array $legacy_values, array $modern_value, array $rule ): void {
		// Track variables used in the modern value (these get transformed)
		$used_variables = [];
		foreach ( $modern_value as $field_name => $field_value ) {
			if ( is_array( $field_value ) && isset( $field_value['_variable'] ) ) {
				$variable_node = $field_value['_variable'];
				$variable_name = $variable_node->name->value;
				$used_variables[ $variable_name ] = true;

				// Check if there's an explicit target type for this variable
				$target_type = null;
				if ( isset( $rule['target_types'] ) && isset( $rule['target_types'][ $variable_name ] ) ) {
					$target_type = $rule['target_types'][ $variable_name ];
				} else {
					// Fall back to field-based type determination
					$target_type = $this->get_target_type_for_field( $field_name );
				}

				$this->variable_transformations[ $variable_name ] = [
					'original_type' => 'ID!', // We'll improve this detection later
					'target_type'   => $target_type,
					'field_name'    => $field_name,
				];
			}
		}

		// Track variables that were in legacy values but not used in modern value (these get removed)
		foreach ( $legacy_values as $legacy_arg_name => $legacy_value ) {
			if ( is_array( $legacy_value ) && isset( $legacy_value['_variable'] ) ) {
				$variable_node = $legacy_value['_variable'];
				$variable_name = $variable_node->name->value;

				// If this variable is not used in the modern value, mark it for removal
				if ( ! isset( $used_variables[ $variable_name ] ) ) {
					$this->variables_to_remove[ $variable_name ] = true;
				}
			}
		}
	}

	/**
	 * Get the target GraphQL type for a given field name
	 * This is a fallback when no explicit target_type is specified
	 *
	 * @param string $field_name The field name to determine type for
	 * @return string The target GraphQL type
	 */
	private function get_target_type_for_field( string $field_name ): string {
		switch ( $field_name ) {
			case 'databaseId':
				return 'ID!'; // databaseId field accepts ID type
			case 'id':
				return 'ID!'; // Global ID field accepts ID type
			case 'slug':
				return 'String!'; // Slug field expects String
			case 'uri':
				return 'String!'; // URI field expects String
			case 'email':
				return 'String!'; // Email field expects String
			case 'username':
				return 'String!'; // Username field expects String
			default:
				return 'ID!'; // Default to ID for backward compatibility
		}
	}

	/**
	 * Transform operation variable declarations based on tracked transformations
	 *
	 * @param \GraphQL\Language\AST\OperationDefinitionNode $operation_node The operation definition node
	 * @return \GraphQL\Language\AST\OperationDefinitionNode The transformed operation definition node
	 */
	private function transform_operation_variables( OperationDefinitionNode $operation_node ): OperationDefinitionNode {
		if ( ( empty( $this->variable_transformations ) && empty( $this->variables_to_remove ) ) || empty( $operation_node->variableDefinitions ) ) {
			return $operation_node; // No variable transformations needed
		}

		$new_variable_definitions = [];

		foreach ( $operation_node->variableDefinitions as $var_def ) {
			$variable_name = $var_def->variable->name->value;

			// Skip variables marked for removal
			if ( isset( $this->variables_to_remove[ $variable_name ] ) ) {
				continue;
			}

			if ( isset( $this->variable_transformations[ $variable_name ] ) ) {
				$transformation = $this->variable_transformations[ $variable_name ];
				$target_type    = $transformation['target_type'];

				// Create new type node for the target type
				$new_type_node = $this->create_type_node( $target_type );

				// Create new variable definition with updated type
				$new_variable_definitions[] = new VariableDefinitionNode(
					[
						'variable'     => $var_def->variable,
						'type'         => $new_type_node,
						'defaultValue' => $var_def->defaultValue,
						'directives'   => $var_def->directives,
						'loc'          => $var_def->loc,
					]
				);
			} else {
				// Keep the original variable definition
				$new_variable_definitions[] = $var_def;
			}
		}

		$node_list = new NodeList( $new_variable_definitions );

		return new OperationDefinitionNode(
			[
				'operation'           => $operation_node->operation,
				'name'                => $operation_node->name,
				'variableDefinitions' => $node_list,
				'directives'          => $operation_node->directives,
				'selectionSet'        => $operation_node->selectionSet,
				'loc'                 => $operation_node->loc,
			]
		);
	}

	/**
	 * Create a TypeNode from a type string
	 *
	 * @param string $type_string The type string (e.g., "String!", "ID", "[String]!")
	 * @return \GraphQL\Language\AST\TypeNode The created type node
	 */
	private function create_type_node( string $type_string ): TypeNode {
		// For now, handle the common cases. This could be expanded for more complex types.
		if ( 'String!' === $type_string ) {
			return new \GraphQL\Language\AST\NonNullTypeNode(
				[
					'type' => new \GraphQL\Language\AST\NamedTypeNode(
						[
							'name' => new \GraphQL\Language\AST\NameNode( [ 'value' => 'String' ] ),
						]
					),
				]
			);
		} elseif ( 'ID!' === $type_string ) {
			return new \GraphQL\Language\AST\NonNullTypeNode(
				[
					'type' => new \GraphQL\Language\AST\NamedTypeNode(
						[
							'name' => new \GraphQL\Language\AST\NameNode( [ 'value' => 'ID' ] ),
						]
					),
				]
			);
		} elseif ( 'String' === $type_string ) {
			return new \GraphQL\Language\AST\NamedTypeNode(
				[
					'name' => new \GraphQL\Language\AST\NameNode( [ 'value' => 'String' ] ),
				]
			);
		} elseif ( 'ID' === $type_string ) {
			return new \GraphQL\Language\AST\NamedTypeNode(
				[
					'name' => new \GraphQL\Language\AST\NameNode( [ 'value' => 'ID' ] ),
				]
			);
		}

		// Default fallback
		return new \GraphQL\Language\AST\NamedTypeNode(
			[
				'name' => new \GraphQL\Language\AST\NameNode( [ 'value' => 'ID' ] ),
			]
		);
	}
}
