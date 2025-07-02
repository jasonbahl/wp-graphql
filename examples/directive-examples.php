<?php
/**
 * Plugin Name: WPGraphQL Directive Examples
 * Description: Examples of how to use the WPGraphQL directive API.
 * Author: WPGraphQL
 * Author URI: https://www.wpgraphql.com
 * Version: 1.0.0
 * Text Domain: wpgraphql-directive-examples
 * Domain Path: /languages
 */

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use WPGraphQL\Type\Scalar\Date;

// Register a "setLocale" directive on the Query and Field types

add_action( 'graphql_register_types', function () {


	// Register a "setLocale" directive on the Query and Field types
	// that filters WordPress to exectue the query in the specified language
	// When the directive is used for the full query, it will be executed in the specified language
	// When the directive is used for a field, it will be executed in the specified language for that field only
	// The directive will accept a single argument: "language"
	// The argument will be a string representing the language code
	register_graphql_directive( 'setLocale', [
		'description' => 'Modifies the query or field to execute in the specified language based on the locale argument',
		'args' => [
			'locale' => [
				'type' => Type::string(),
				'description' => 'The language code to execute the query in. If not provided or not valid, the default locale on the server will be used.',
			],
		],
		'locations' => [
			'QUERY' => [
				'before_execute' => function ( $request, $operation, $directive_args, $directive ) {

					$installed_locales = get_available_languages();

					if ( ! empty( $directive_args['locale'] ) ) {

						if ( ! in_array( $directive_args['locale'], $installed_locales, true ) ) {
							graphql_debug( sprintf( 'The @translate directive cannot be used with the "%s" locale. The locale is not installed on the server.', $directive_args['locale'] ), [
								'installed_locales' => $installed_locales,
							] );
							return;
						}

						$request->app_context->original_locale = get_locale();
						switch_to_locale( $directive_args['locale'] );

					}
				},
				'after_execute' => function ( $response, $request, $operation, $directive_args, $directive ) {
					if ( ! empty( $request->app_context->original_locale ) ) {
						restore_previous_locale();
						$request->app_context->original_locale = null;
					}
				},
			],
			'FIELD' => [
				'before_resolve' => function ( $source, $field_args, $context, $info, $field_resolver, $type_name, $field_key, $field, $directive_args, $directive ) {
					$installed_locales = get_available_languages();

					if ( ! empty( $directive_args['locale'] ) ) {

						if ( ! in_array( $directive_args['locale'], $installed_locales, true ) ) {
							graphql_debug( sprintf( 'The @translate directive cannot be used with the "%s" locale. The locale is not installed on the server.', $directive_args['locale'] ), [
								'installed_locales' => $installed_locales,
							] );
							return;
						}

						$context->set( 'original_locale', get_locale() );
						switch_to_locale( $directive_args['locale'] );
					}
				},
				'after_resolve' => function ( $result, $source, $field_args, $context, $info, $field_resolver, $type_name, $field_key, $field, $directive_args, $directive ) {
					if ( ! empty( $context->get( 'original_locale' ) ) ) {
						restore_previous_locale();
						$context->set( 'original_locale', null );
					}
				},
			],
		],
	] );

	// Example FIELD directives - these operate on field values during query execution
	// The directive is applied to the field in the query like: @stringToUppercase
	register_graphql_directive( 'stringToUppercase', [
		'description' => 'Uppercase the value',
		'locations'   => [
			'FIELD' => [
				'resolve' => function ( $value ) {
					return strtoupper( $value );
				},
			],
		],
		'onTypes'   => [ 'String' ],
	] );

	register_graphql_directive( 'stringToTitleCase', [
		'description' => 'Converts a string to title case.',
		'locations'   => [
			'FIELD' => [
				'resolve' => function ( $value ) {
					return ucwords( $value );
				},
			],
		],
		'onTypes'   => [ 'String' ],
	] );

	// ======================================
	// FIELD_DEFINITION DIRECTIVE EXAMPLES
	// ======================================
	// These directives modify the field definition during schema registration,
	// rather than during query execution. They can add arguments, modify resolvers,
	// and enforce field-level constraints.

	// Example of a FIELD_DEFINITION directive that enforces field arguments
	// This directive modifies the field definition to add a required argument
	// and wraps the resolver to handle the formatting
	//
	// Usage in field registration:
	// register_graphql_field( 'Post', 'publishDate', [
	//     'type' => 'String',
	//     'directives' => [ 'formatDate' ],
	//     'resolve' => function($post) { return get_the_date('c', $post->ID); }
	// ] );
	//
	// Usage in query:
	// {
	//   posts {
	//     nodes {
	//       publishDate(format: "Y-m-d")
	//     }
	//   }
	// }
	register_graphql_directive( 'formatDate', [
		'description' => 'Formats a date value. This directive enforces that the field has a "format" argument.',
		'locations'   => [
			'FIELD_DEFINITION' => [
				'apply' => function ( $field_config, $field_name, $type_name, $type_config, $directive ) {
					// Add the format argument to the field
					if ( ! isset( $field_config['args'] ) ) {
						$field_config['args'] = [];
					}

					$field_config['args']['format'] = [
						'type'         => Type::string(),
						'description'  => 'PHP date format string for formatting the date',
						'defaultValue' => 'F j, Y',
					];

					// Wrap the existing resolver to handle date formatting
					$original_resolver = $field_config['resolve'] ?? null;

					$field_config['resolve'] = function ( $source, $args, $context, ResolveInfo $info ) use ( $original_resolver ) {
						// Get the original value first
						$result = null;
						if ( is_callable( $original_resolver ) ) {
							$result = call_user_func( $original_resolver, $source, $args, $context, $info );
						} else {
							// Fall back to default field resolution
							$result = $source->{$info->fieldName} ?? null;
						}

						// If no format argument is provided, return the original result
						if ( empty( $args['format'] ) ) {
							return $result;
						}

						// Attempt to create a timestamp from the resolved value
						$timestamp = is_numeric( $result ) ? $result : strtotime( $result );

						// If the value couldn't be converted to a valid timestamp, return original
						if ( false === $timestamp ) {
							graphql_debug( sprintf( 'The formatDate directive on field "%s" could not parse the date value.', $info->fieldName ), [
								'field_name' => $info->fieldName,
								'raw_value'  => $result,
							] );
							return $result;
						}

						// Format the date
						return wp_date( $args['format'], $timestamp );
					};

					return $field_config;
				},
			],
		],
		'onTypes' => [ 'String', 'Date' ], // Only allow on String and Date type fields
	] );

	// Another example: A directive that enforces required arguments and validation
	register_graphql_directive( 'requireAuth', [
		'description' => 'Enforces authentication and optionally specific capabilities for field access.',
		'locations'   => [
			'FIELD_DEFINITION' => [
				'apply' => function ( $field_config, $field_name, $type_name, $type_config, $directive ) {
					// Add an optional capability argument
					if ( ! isset( $field_config['args'] ) ) {
						$field_config['args'] = [];
					}

					$field_config['args']['requireCapability'] = [
						'type'         => Type::string(),
						'description'  => 'Required capability to access this field',
						'defaultValue' => null,
					];

					// Wrap the resolver to enforce authentication
					$original_resolver = $field_config['resolve'] ?? null;

					$field_config['resolve'] = function ( $source, $args, $context, ResolveInfo $info ) use ( $original_resolver ) {
						// Check if user is authenticated
						if ( ! is_user_logged_in() ) {
							throw new \GraphQL\Error\UserError( 'Authentication required to access this field.' );
						}

						// Check capability if specified
						if ( ! empty( $args['requireCapability'] ) && ! current_user_can( $args['requireCapability'] ) ) {
							throw new \GraphQL\Error\UserError( sprintf( 'Insufficient permissions. Required capability: %s', $args['requireCapability'] ) );
						}

						// Proceed with original resolution
						if ( is_callable( $original_resolver ) ) {
							return call_user_func( $original_resolver, $source, $args, $context, $info );
						}

						return $source->{$info->fieldName} ?? null;
					};

					return $field_config;
				},
			],
		],
	] );

	// ======================================
	// EXAMPLE FIELD REGISTRATIONS
	// ======================================

	// Example field registration using the formatDate directive
	register_graphql_field( 'Post', 'publishDate', [
		'type'        => 'String',
		'description' => 'The publish date of the post, formatted according to the format argument.',
		'directives'  => [ 'formatDate' ],
		'resolve'     => function ( $post ) {
			return get_the_date( 'c', $post->ID ); // Return ISO 8601 date by default
		},
	] );

	// Example field with authentication requirement
	register_graphql_field( 'Post', 'privateNotes', [
		'type'        => 'String',
		'description' => 'Private notes about this post (requires authentication).',
		'directives'  => [ 'requireAuth' ],
		'resolve'     => function ( $post ) {
			return get_post_meta( $post->ID, '_private_notes', true );
		},
	] );
} );
