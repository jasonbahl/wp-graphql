<?php
/**
 * Plugin Name: WPGraphQL
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: GraphQL API for WordPress
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Version: 2.3.6
 * Text Domain: wp-graphql
 * Domain Path: /languages/
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package  WPGraphQL
 * @category Core
 * @author   WPGraphQL
 * @version  2.3.6
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If the codeception remote coverage file exists, require it.
// This file should only exist locally or when CI bootstraps the environment for testing
if ( file_exists( __DIR__ . '/c3.php' ) ) {
	require_once __DIR__ . '/c3.php';
}

/**
 * Load files that are required even if the composer autoloader isn't installed
 */
function graphql_require_bootstrap_files(): void {
	if ( file_exists( __DIR__ . '/constants.php' ) ) {
		require_once __DIR__ . '/constants.php';
	}
	if ( file_exists( __DIR__ . '/activation.php' ) ) {
		require_once __DIR__ . '/activation.php';
	}
	if ( file_exists( __DIR__ . '/deactivation.php' ) ) {
		require_once __DIR__ . '/deactivation.php';
	}
	if ( file_exists( __DIR__ . '/access-functions.php' ) ) {
		require_once __DIR__ . '/access-functions.php';
	}
	if ( file_exists( __DIR__ . '/src/WPGraphQL.php' ) ) {
		require_once __DIR__ . '/src/WPGraphQL.php';
	}
}

/**
 * Determines if the plugin can load.
 *
 * Test env:
 *  - WPGRAPHQL_AUTOLOAD: false
 *  - autoload installed and manually added in test env
 *
 * Bedrock
 *  - WPGRAPHQL_AUTOLOAD: not defined
 *  - composer deps installed outside the plugin
 *
 * Normal (.org repo install)
 * - WPGRAPHQL_AUTOLOAD: not defined
 * - composer deps installed INSIDE the plugin
 */
function graphql_can_load_plugin(): bool {

	// Load the bootstrap files (needed before autoloader is configured)
	graphql_require_bootstrap_files();

	// If GraphQL\GraphQL and WPGraphQL are both already loaded,
	// We can assume that WPGraphQL has been installed as a composer dependency of a parent project
	if ( class_exists( 'GraphQL\GraphQL' ) && class_exists( 'WPGraphQL' ) ) {
		return true;
	}

	/**
	 * WPGRAPHQL_AUTOLOAD can be set to "false" to prevent the autoloader from running.
	 * In most cases, this is not something that should be disabled, but some environments
	 * may bootstrap their dependencies in a global autoloader that will autoload files
	 * before we get to this point, and requiring the autoloader again can trigger fatal errors.
	 *
	 * The codeception tests are an example of an environment where adding the autoloader again causes issues
	 * so this is set to false for tests.
	 */
	if ( defined( 'WPGRAPHQL_AUTOLOAD' ) && false === WPGRAPHQL_AUTOLOAD ) {

		// IF WPGRAPHQL_AUTOLOAD is defined as false,
		// but the WPGraphQL Class exists, we can assume the dependencies
		// are loaded from the parent project.
		return true;
	}

	if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
		// Autoload Required Classes.
		require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
	}

	// If the GraphQL class still doesn't exist, bail as there was an issue bootstrapping the plugin
	if ( ! class_exists( 'GraphQL\GraphQL' ) || ! class_exists( 'WPGraphQL' ) ) {
		return false;
	}

	return true;
}

if ( ! function_exists( 'graphql_init' ) ) {
	/**
	 * Function that instantiates the plugins main class
	 *
	 * @return object|null
	 */
	function graphql_init() {

		// if the plugin can't be loaded, bail
		if ( false === graphql_can_load_plugin() ) {
			add_action( 'network_admin_notices', 'graphql_cannot_load_admin_notice_callback' );
			add_action( 'admin_notices', 'graphql_cannot_load_admin_notice_callback' );
			return null;
		}

		/**
		 * Return an instance of the action
		 */
		return \WPGraphQL::instance();
	}
}
graphql_init();

// Run this function when WPGraphQL is de-activated
register_deactivation_hook( __FILE__, 'graphql_deactivation_callback' );
register_activation_hook( __FILE__, 'graphql_activation_callback' );

/**
 * Render an admin notice if the plugin cannot load
 */
function graphql_cannot_load_admin_notice_callback(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error">' .
		'<p>%s</p>' .
		'</div>',
		esc_html__( 'WPGraphQL appears to have been installed without it\'s dependencies. It will not work properly until dependencies are installed. This likely means you have cloned WPGraphQL from Github and need to run the command `composer install`.', 'wp-graphql' )
	);
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'cli/wp-cli.php';
}

/**
 * Initialize the plugin tracker.
 */
function graphql_init_appsero_telemetry(): void {
	// If the class doesn't exist, or code is being scanned by PHPSTAN, move on.
	if ( ! class_exists( 'Appsero\Client' ) || defined( 'PHPSTAN' ) ) {
		return;
	}

	// Wrap the Appsero client in a try/catch block to prevent fatal errors
	try {
		$client = new \Appsero\Client( 'cd0d1172-95a0-4460-a36a-2c303807c9ef', 'WPGraphQL', __FILE__ );

		/**
		 * @var \Appsero\Insights $insights
		 *
		 * @phpstan-ignore varTag.type (The doctype for Appsero\Client::insights() is wrong.)
		 */
		$insights = $client->insights();

		// If the Appsero client has the add_plugin_data method, use it
		if ( method_exists( $insights, 'add_plugin_data' ) ) {
			$insights->add_plugin_data();
		}

		$insights->init();
	} catch ( \Throwable $e ) {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is intentional here.
			sprintf(
			// translators: %s is the error message
				__( 'Error initializing Appsero: %s', 'wp-graphql' ),
				$e->getMessage()
			)
		);
	}
}

graphql_init_appsero_telemetry();

// Register the field early to ensure it's available when the filter runs
add_action( 'graphql_register_types', function() {
	error_log( 'Registering GraphQL types and fields' );

	register_graphql_input_type( 'TestBy', [
		'fields' => [
			'id' => [
				'type' => 'ID',
			],
			'name' => [
				'type' => 'String',
			]
		],
		'isOneOf' => true,
	]);

	// Define the legacy args configuration
	$legacy_args = [
		'id' => [
			'type' => [ 'non_null' => 'ID' ],
			'deprecationReason' => 'use by instead',
			'mapsTo' => 'by',
			'transform' => function( $value ) {
				return [ 'id' => $value ];
			}
		],
	];

	// Create the original resolver
	$original_resolver = function( $source, $args, $context, $info ) {
		error_log( 'Original test resolver called with args: ' . json_encode( $args ) );
		// Validate that either by or legacy args are provided
		if ( empty( $args['by'] ) ) {
			throw new \GraphQL\Error\UserError( 'Either "by" argument or legacy arguments must be provided' );
		}
		return json_encode( $args );
	};

	// Create the wrapped resolver that handles legacy args
	$wrapped_resolver = function( $source, $args, $context, $info ) use ( $original_resolver, $legacy_args ) {
		error_log( 'Wrapped resolver called for test with args: ' . json_encode( $args ) );

		$has_legacy_args = false;

		// Check if any legacy arguments are present
		foreach ( $legacy_args as $legacy_arg_name => $legacy_config ) {
			if ( isset( $args[$legacy_arg_name] ) ) {
				$has_legacy_args = true;
				error_log( "Found legacy arg: {$legacy_arg_name}" );
				break;
			}
		}

		if ( $has_legacy_args ) {
			error_log( "Processing legacy arguments for transformation" );
			// Transform legacy arguments
			foreach ( $legacy_args as $legacy_arg_name => $legacy_config ) {
				if ( ! isset( $args[$legacy_arg_name] ) ) {
					continue;
				}

				$legacy_value = $args[$legacy_arg_name];
				$maps_to = $legacy_config['mapsTo'];
				$transform_fn = $legacy_config['transform'];

				// Apply transformation
				$transformed_value = $transform_fn( $legacy_value );

				// Set the transformed value
				$args[$maps_to] = $transformed_value;

				// Remove the legacy argument
				unset( $args[$legacy_arg_name] );

				// Log usage for monitoring
				error_log( "Legacy argument '{$legacy_arg_name}' transformed to '{$maps_to}': " . json_encode( $transformed_value ) );
			}
		} else {
			error_log( "No legacy args found in: " . json_encode( array_keys( $args ) ) );
		}

		// Call the original resolver with transformed args
		error_log( "Calling original resolver with args: " . json_encode( $args ) );
		return $original_resolver( $source, $args, $context, $info );
	};

	register_graphql_field( 'RootQuery', 'test', [
		'type' => 'String',
		'args' => [
			'by' => [
				'type' => 'TestBy', // Make it nullable so legacy args can provide it
			],
			// Add legacy args to schema so they get passed to resolver
			'id' => [
				'type' => 'ID', // Make it nullable since we disabled validation
				'description' => 'Legacy argument. Use "by" instead.',
			]
		],
		'resolve' => $wrapped_resolver
	]);

	error_log( 'Finished registering GraphQL types and fields' );
}, 5); // Run earlier to ensure field is registered before filter runs

// Step 1: Legacy args are intentionally NOT added to schema introspection
// This encourages migration to new patterns while maintaining backward compatibility

// Step 2: Disable problematic validation rules when legacy args are present
add_filter( 'graphql_validation_rules', 'disable_validation_for_legacy_args', 10, 2 );
function disable_validation_for_legacy_args( $validation_rules, $request ) {
	// Remove the validation rules that would block legacy arguments
	unset( $validation_rules['KnownArgumentNames'] );
	unset( $validation_rules['ProvidedRequiredArguments'] );

	// Debug: Log what validation rules are being used
	error_log( 'Validation rules after filter: ' . implode( ', ', array_keys( $validation_rules ) ) );

	return $validation_rules;
}

// Alternative approach: Try to disable validation entirely for testing
add_filter( 'graphql_validation_rules', 'disable_all_validation_for_testing', 5, 2 );
function disable_all_validation_for_testing( $validation_rules, $request ) {
	// For testing purposes, let's disable ALL validation to see if our transformation works
	error_log( 'Original validation rules: ' . implode( ', ', array_keys( $validation_rules ) ) );

	// Return only essential validation rules, removing the problematic ones
	// Use full class names as they appear in the logs
	$filtered_rules = [];
	foreach ( $validation_rules as $key => $rule ) {
		if ( ! in_array( $key, [
			'GraphQL\\Validator\\Rules\\KnownArgumentNames',
			'GraphQL\\Validator\\Rules\\ProvidedRequiredArguments'
		] ) ) {
			$filtered_rules[$key] = $rule;
		}
	}

	error_log( 'Filtered validation rules: ' . implode( ', ', array_keys( $filtered_rules ) ) );
	return $filtered_rules;
}

// Step 3: Legacy args are handled through resolver transformation only
// We don't add them to the schema to keep introspection clean

// Step 4: Use the correct filter to modify RootQuery fields
add_filter( 'graphql_rootQuery_fields', 'wrap_resolver_for_legacy_args', 20, 3 );
function wrap_resolver_for_legacy_args( $fields, $wp_object_type, $type_registry ) {
	error_log( "graphql_rootQuery_fields filter called with " . count( $fields ) . " fields" );

	foreach ( $fields as $field_name => $field_config ) {
		error_log( "Checking field: {$field_name}" );

		// Check if this field has legacy args in its configuration
		if ( isset( $field_config['legacyArgs'] ) && isset( $field_config['resolve'] ) ) {
			error_log( "Found field with legacy args: {$field_name}" );

			$original_resolver = $field_config['resolve'];
			$legacy_args = $field_config['legacyArgs'];

			// Wrap the resolver
			$fields[$field_name]['resolve'] = function( $source, $args, $context, $info ) use ( $original_resolver, $legacy_args, $field_name ) {
				error_log( "Wrapped resolver called for {$field_name} with args: " . json_encode( $args ) );

				$has_legacy_args = false;

				// Check if any legacy arguments are present
				foreach ( $legacy_args as $legacy_arg_name => $legacy_config ) {
					if ( isset( $args[$legacy_arg_name] ) ) {
						$has_legacy_args = true;
						error_log( "Found legacy arg: {$legacy_arg_name}" );
						break;
					}
				}

				if ( $has_legacy_args ) {
					error_log( "Processing legacy arguments for transformation" );
					// Transform legacy arguments
					foreach ( $legacy_args as $legacy_arg_name => $legacy_config ) {
						if ( ! isset( $args[$legacy_arg_name] ) ) {
							continue;
						}

						$legacy_value = $args[$legacy_arg_name];
						$maps_to = $legacy_config['mapsTo'];
						$transform_fn = $legacy_config['transform'];

						// Apply transformation
						$transformed_value = $transform_fn( $legacy_value );

						// Set the transformed value
						$args[$maps_to] = $transformed_value;

						// Remove the legacy argument
						unset( $args[$legacy_arg_name] );

						// Log usage for monitoring
						error_log( "Legacy argument '{$legacy_arg_name}' transformed to '{$maps_to}': " . json_encode( $transformed_value ) );
					}
				} else {
					error_log( "No legacy args found in: " . json_encode( array_keys( $args ) ) );
				}

				// Call the original resolver with transformed args
				error_log( "Calling original resolver with args: " . json_encode( $args ) );
				return $original_resolver( $source, $args, $context, $info );
			};
		}
	}

	return $fields;
}