<?php

namespace WPGraphQL\Registry;

use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\Parser;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;
use WPGraphQL\WPGraphQL;

/**
 * Class DirectiveRegistry
 *
 * @package WPGraphQL\Registry
 */
class DirectiveRegistry {
    /**
     * @var array<string, \GraphQL\Type\Definition\Directive>
     */
    protected $directives = [];

    /**
     * Initialize the directive registry
     *
     * @return void
     */
    public function init() {
        // Field resolution hooks
        add_action( 'graphql_before_resolve_field', [ $this, 'before_resolve_field_directives' ], 10, 8 );
        add_action( 'graphql_resolve_field', [ $this, 'resolve_field_directives' ], 10, 9 );
        add_action( 'graphql_after_resolve_field', [ $this, 'after_resolve_field_directives' ], 10, 9 );

        // Query execution hooks
        add_action( 'graphql_before_execute', [ $this, 'before_execute_query_directives' ], 10, 1 );
        add_action( 'graphql_after_execute', [ $this, 'after_execute_query_directives' ], 10, 2 );
    }

    /**
     * @param string $directive_name
     * @param array<string,mixed> $config
     * @return void
     */
    public function register_directive( string $directive_name, array $config ) {
        if ( ! isset( $config['name'] ) ) {
            $config['name'] = $directive_name;
        }

        // The final config that will be passed to the Directive constructor
        $directive_config = $config;

        if ( isset( $config['locations'] ) && is_array( $config['locations'] ) ) {

            // if the locations array is an associative array, it's the new format
            $is_list = array_keys( $config['locations'] ) === range( 0, count( $config['locations'] ) - 1 );

            if ( ! $is_list ) {
                $locations_with_callbacks = $config['locations'];
                $directive_locations = [];

                // remove the old callback keys from the root of the config
                unset(
                    $directive_config['before_resolve_field'],
                    $directive_config['resolve_field'],
                    $directive_config['after_resolve_field'],
                    $directive_config['before_execute_query'],
                    $directive_config['after_execute_query']
                );

                foreach ( $locations_with_callbacks as $location => $callbacks ) {
                    $directive_locations[] = $location;

                    if ( ! is_array( $callbacks ) ) {
                        continue;
                    }

                    // For now, FIELD and FIELD_DEFINITION are treated the same, as directives
                    // can only be executed on Fields in queries, not on field definitions in the Schema.
                    // The directive hook system is tied to field resolution during query execution.
                    if ( 'FIELD' === $location || 'FIELD_DEFINITION' === $location ) {
                        if ( isset( $callbacks['before_resolve'] ) ) {
                            $directive_config['before_resolve_field'] = $callbacks['before_resolve'];
                        }
                        if ( isset( $callbacks['resolve'] ) ) {
                            $directive_config['resolve_field'] = $callbacks['resolve'];
                        }
                        if ( isset( $callbacks['after_resolve'] ) ) {
                            $directive_config['after_resolve_field'] = $callbacks['after_resolve'];
                        }
                    }

                    if ( 'QUERY' === $location ) {
                        if ( isset( $callbacks['before_execute'] ) ) {
                            $directive_config['before_execute_query'] = $callbacks['before_execute'];
                        }
                        if ( isset( $callbacks['after_execute'] ) ) {
                            $directive_config['after_execute_query'] = $callbacks['after_execute'];
                        }
                    }
                }

                $directive_config['locations'] = $directive_locations;
            }
        }

        $this->directives[ $directive_name ] = new Directive( $directive_config );
    }

    /**
     * @return array<string, \GraphQL\Type\Definition\Directive>
     */
    public function get_directives() {
        return $this->directives;
    }

    /**
     * @param mixed                                    $result         The result of the field resolution
     * @param mixed                                    $source         The source passed down the Resolve Tree
     * @param array<string,mixed>                      $args           The args for the field
     * @param \WPGraphQL\AppContext                    $context        The AppContext passed down the ResolveTree
     * @param \GraphQL\Type\Definition\ResolveInfo     $info           The ResolveInfo passed down the ResolveTree
     * @param string|\Closure                          $type_name      The name of the type the fields belong to
     * @param string                                   $field_key      The name of the field
     * @param \GraphQL\Type\Definition\FieldDefinition $field          The Field Definition for the resolving field
     * @param callable|null                            $field_resolver The Resolve function for the field
     * @return mixed
     */
    public function resolve_field_directives( $result, $source, array $args, AppContext $context, ResolveInfo $info, $type_name, string $field_key, FieldDefinition $field, $field_resolver ) {
        if ( empty( $info->fieldNodes ) ) {
            return $result;
        }

        // if the type_name is a closure, execute it to get the string name
        if ( is_callable( $type_name ) ) {
            $type_name = $type_name();
        }

        foreach ( $info->fieldNodes as $field_node ) {
            if ( empty( $field_node->directives ) ) {
                continue;
            }

            foreach ( $field_node->directives as $directive_node ) {
                $directive_name = $directive_node->name->value;
                if ( ! isset( $this->directives[ $directive_name ] ) ) {
                    continue;
                }

                $directive = $this->directives[ $directive_name ];

                if ( ! $this->should_execute_for_field( $directive, $field, $info ) ) {
                    continue;
                }

                if ( isset( $directive->config['resolve_field'] ) && is_callable( $directive->config['resolve_field'] ) ) {
                    $directive_args = Utils::get_directive_args( $directive, $directive_node, $info->variableValues );

                    $result = call_user_func(
                        $directive->config['resolve_field'],
                        $result,
                        $source,
                        $args,
                        $context,
                        $info,
                        $type_name,
                        $field_key,
                        $field,
                        $field_resolver,
                        $directive_args,
                        $directive
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Executes directives hooked into `graphql_before_resolve_field`.
     *
     * @param mixed                                    $source         The source passed down the Resolve Tree
     * @param array<string,mixed>                      $args           The args for the field
     * @param \WPGraphQL\AppContext                    $context        The AppContext passed down the ResolveTree
     * @param \GraphQL\Type\Definition\ResolveInfo     $info           The ResolveInfo passed down the ResolveTree
     * @param callable|null                            $field_resolver The Resolve function for the field
     * @param string|\Closure                          $type_name      The name of the type the fields belong to
     * @param string                                   $field_key      The name of the field
     * @param \GraphQL\Type\Definition\FieldDefinition $field          The Field Definition for the resolving field
     * @return void
     */
    public function before_resolve_field_directives( $source, array $args, AppContext $context, ResolveInfo $info, $field_resolver, $type_name, string $field_key, FieldDefinition $field ) {
        if ( empty( $info->fieldNodes ) ) {
            return;
        }

        // if the type_name is a closure, execute it to get the string name
        if ( is_callable( $type_name ) ) {
            $type_name = $type_name();
        }

        foreach ( $info->fieldNodes as $field_node ) {
            if ( empty( $field_node->directives ) ) {
                continue;
            }

            foreach ( $field_node->directives as $directive_node ) {
                $directive_name = $directive_node->name->value;
                if ( ! isset( $this->directives[ $directive_name ] ) ) {
                    continue;
                }

                $directive = $this->directives[ $directive_name ];

                if ( ! $this->should_execute_for_field( $directive, $field, $info ) ) {
                    continue;
                }

                if ( isset( $directive->config['before_resolve_field'] ) && is_callable( $directive->config['before_resolve_field'] ) ) {
                    $directive_args = Utils::get_directive_args( $directive, $directive_node, $info->variableValues );

                    call_user_func(
                        $directive->config['before_resolve_field'],
                        $source,
                        $args,
                        $context,
                        $info,
                        $type_name,
                        $field_key,
                        $field,
                        $field_resolver,
                        $directive_args,
                        $directive
                    );
                }
            }
        }
    }

    /**
     * Executes directives hooked into `graphql_after_resolve_field`.
     *
     * @param mixed                                    $result         The result of the field resolution
     * @param mixed                                    $source         The source passed down the Resolve Tree
     * @param array<string,mixed>                      $args           The args for the field
     * @param \WPGraphQL\AppContext                    $context        The AppContext passed down the ResolveTree
     * @param \GraphQL\Type\Definition\ResolveInfo     $info           The ResolveInfo passed down the ResolveTree
     * @param callable|null                            $field_resolver The Resolve function for the field
     * @param string|\Closure                          $type_name      The name of the type the fields belong to
     * @param string                                   $field_key      The name of the field
     * @param \GraphQL\Type\Definition\FieldDefinition $field          The Field Definition for the resolving field
     * @return void
     */
    public function after_resolve_field_directives( $source, array $args, AppContext $context, ResolveInfo $info, $field_resolver, $type_name, string $field_key, FieldDefinition $field, $result ) {
        if ( empty( $info->fieldNodes ) ) {
            return;
        }

        // if the type_name is a closure, execute it to get the string name
        if ( is_callable( $type_name ) ) {
            $type_name = $type_name();
        }

        foreach ( $info->fieldNodes as $field_node ) {
            if ( empty( $field_node->directives ) ) {
                continue;
            }

            foreach ( $field_node->directives as $directive_node ) {
                $directive_name = $directive_node->name->value;
                if ( ! isset( $this->directives[ $directive_name ] ) ) {
                    continue;
                }

                $directive = $this->directives[ $directive_name ];

                if ( ! $this->should_execute_for_field( $directive, $field, $info ) ) {
                    continue;
                }

                if ( isset( $directive->config['after_resolve_field'] ) && is_callable( $directive->config['after_resolve_field'] ) ) {
                    $directive_args = Utils::get_directive_args( $directive, $directive_node, $info->variableValues );

                    call_user_func(
                        $directive->config['after_resolve_field'],
                        $result,
                        $source,
                        $args,
                        $context,
                        $info,
                        $type_name,
                        $field_key,
                        $field,
                        $field_resolver,
                        $directive_args,
                        $directive
                    );
                }
            }
        }
    }

    /**
     * @param \WPGraphQL\Request $request The Request object.
     * @return void
     */
    public function before_execute_query_directives( \WPGraphQL\Request $request ) {
        $params = $request->params;
        $operations = is_array( $params ) ? $params : [ $params ];

        foreach ( $operations as $operation_params ) {
            if ( empty( $operation_params->query ) ) {
                continue;
            }

            $ast = Parser::parse( $operation_params->query );

            if ( empty( $ast->definitions ) ) {
                continue;
            }

            foreach ( $ast->definitions as $definition ) {
                if ( ! ( $definition instanceof \GraphQL\Language\AST\OperationDefinitionNode ) ) {
                    continue;
                }

                if ( empty( $definition->directives ) ) {
                    continue;
                }

                foreach ( $definition->directives as $directive_node ) {
                    $directive_name = $directive_node->name->value;
                    if ( ! isset( $this->directives[ $directive_name ] ) ) {
                        continue;
                    }

                    $directive = $this->directives[ $directive_name ];

                    if ( isset( $directive->config['before_execute_query'] ) && is_callable( $directive->config['before_execute_query'] ) ) {
                        $directive_args = Utils::get_directive_args( $directive, $directive_node, $operation_params->variables );

                        call_user_func(
                            $directive->config['before_execute_query'],
                            $request,
                            $definition,
                            $directive_args,
                            $directive
                        );
                    }
                }
            }
        }
    }

    /**
     * @param array<string,mixed>|array<array<string,mixed>> $response The response of the GraphQL query.
     * @param \WPGraphQL\Request                             $request  The Request object.
     * @return void
     */
    public function after_execute_query_directives( $response, \WPGraphQL\Request $request ) {
        $params = $request->params;
        $operations = is_array( $params ) ? $params : [ $params ];

        foreach ( $operations as $operation_params ) {
            if ( empty( $operation_params->query ) ) {
                continue;
            }

            $ast = Parser::parse( $operation_params->query );

            if ( empty( $ast->definitions ) ) {
                continue;
            }

            foreach ( $ast->definitions as $definition ) {
                if ( ! ( $definition instanceof \GraphQL\Language\AST\OperationDefinitionNode ) ) {
                    continue;
                }

                if ( empty( $definition->directives ) ) {
                    continue;
                }

                foreach ( $definition->directives as $directive_node ) {
                    $directive_name = $directive_node->name->value;
                    if ( ! isset( $this->directives[ $directive_name ] ) ) {
                        continue;
                    }

                    $directive = $this->directives[ $directive_name ];

                    if ( isset( $directive->config['after_execute_query'] ) && is_callable( $directive->config['after_execute_query'] ) ) {
                        $directive_args = Utils::get_directive_args( $directive, $directive_node, $operation_params->variables );

                        call_user_func(
                            $directive->config['after_execute_query'],
                            $response,
                            $request,
                            $definition,
                            $directive_args,
                            $directive
                        );
                    }
                }
            }
        }
    }

    private function should_execute_for_field( Directive $directive, FieldDefinition $field, ResolveInfo $info ): bool {
        // Use onTypes if available, otherwise fall back to onScalars for backward compatibility.
        $allowed_types = $directive->config['onTypes'] ?? $directive->config['onScalars'] ?? null;

        if ( ! is_array( $allowed_types ) || empty( $allowed_types ) ) {
            return true;
        }

        $field_type = $field->getType();
        $named_type = Type::getNamedType( $field_type );

        if ( ! in_array( $named_type->name, $allowed_types, true ) ) {
            graphql_debug(
                sprintf(
                    // translators: 1. The name of the directive, 2. The name of the field, 3. The list of allowed types, 4. The name of the type of the field.
                    __( 'The @%1$s directive can only be used on fields that return one of the following types: %3$s. The "%2$s" field returns a "%4$s".', 'wp-graphql' ),
                    $directive->name,
                    $info->fieldName,
                    implode( ', ', $allowed_types ),
                    $named_type->name
                )
            );
            return false;
        }

        return true;
    }
}