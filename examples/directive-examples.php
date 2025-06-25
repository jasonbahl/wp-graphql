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

	register_graphql_directive( 'formatDate', [
		'description' => 'Formats a date value. Only works on fields that resolve to a valid date string or timestamp.',
		'args'        => [
			'format' => [
				'type'        => Type::string(),
				'description' => 'A valid PHP date format string.',
			],
		],
		'locations'   => [
			'FIELD' => [
				'resolve' => function ( $result, $source, $field_args, $context, ResolveInfo $info, $type_name, $field_key, $field, $field_resolver, $directive_args ) {
					// Attempt to create a timestamp from the resolved value.
					// This could be an integer timestamp, or a string like '2023-10-27 10:00:00'.
					$timestamp = is_numeric( $result ) ? $result : strtotime( $result );

					// If the value couldn't be converted to a valid timestamp, it's not a date.
					if ( false === $timestamp ) {
						return $result;
					}

					// The value is a date, so we can safely format it.
					$format = $directive_args['format'] ?? 'F j, Y';

					return wp_date( $format, $timestamp );
				},
			],
		],
		'onTypes'   => [ 'String' ],
	] );
} );
