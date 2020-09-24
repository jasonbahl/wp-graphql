<?php

namespace WPGraphQL\Admin\SchemaLogger;

use GraphQL\Type\Schema;
use GraphQL\Utils\BreakingChangesFinder;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\SchemaPrinter;
use WPGraphQL\Router;

class  SchemaLogger {

	public $post_type;
	public $skip_breaking_change_check = false;

	public function init() {
		return;

		$this->post_type = 'graphql_schema_logs';
//		add_action( 'graphql_register_types', function() {
//
//			register_graphql_field( 'RootQuery', 'foo', [
//				'type' => 'String',
//				'description' => __( 'Test...', 'wp-graphql' ),
//				'resolve' => function() {
//					return 'yo...';
//				}
//			] );
//
//			register_graphql_field( 'Post', 'foo', [
//				'type' => 'String',
//				'description' => __( 'Test...', 'wp-graphql' ),
//				'resolve' => function() {
//					return 'yo...';
//				}
//			] );
//		});
		add_action( 'graphql_register_types', function() {
			register_graphql_field( 'RootMutation', 'checkGraphQLBreakingChanges', [
				'type' => 'Boolean',
				'description' => __( 'Check for GraphQL Breaking changes', 'wp-graphql' ),
				'resolve' => function() {
					// If the request is to check for breaking changes
					// Prevent it from triggering another check
					$this->skip_breaking_change_check = true;
					$count = (int) get_option( 'graphql_breaking_change_check', 0 );
					$count++;
					update_option( 'graphql_breaking_change_check', $count );
					return ! empty( $count ) ? true : false;
				}
			]);
		} );

		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'graphql_execute', [ $this, 'store_schema_and_compare_with_previous' ], 10, 5 );
		add_action( 'graphql_return_response', function( $response ) {

			$skip_check = apply_filters( 'graphql_skip_breaking_changes_check', $this->skip_breaking_change_check );
			if ( true === $skip_check ) {
				return $response;
			}

			$request = wp_remote_post( trailingslashit( site_url() ) . Router::$route, [
				'headers'  => [
					'Content-Type' => 'application/json',
				],
				'blocking' => false,
				'body'     => wp_json_encode( [
					'query' => 'mutation{ checkGraphQLBreakingChanges }'
				] ),
			] );

			wp_send_json( $request );

			return $response;
		});

	}

	public function register_post_type() {
		register_post_type( $this->post_type, [
			'label'               => __( 'GraphQL Schema Logs', 'wp-graphql' ),
			'show_ui'             => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'SchemaLog',
			'graphql_plural_name' => 'SchemaLogs',
			'public'              => false,
			'supports' => [ 'title', 'editor', 'revisions' ]
		] );
	}

	/**
	 * Check if previous schema exists,
	 * - if no:
	 * - - store new schema
	 * - if prev schema exists
	 * - - compare prev and current schemas, find changes, etc
	 * - - If prev is different than current
	 * - - - store current schema for future reference
	 *
	 *
	 * @param $response
	 * @param $schema
	 * @param $operation
	 * @param $query
	 * @param $variable
	 *
	 *
	 */
	public function store_schema_and_compare_with_previous( $response, $schema, $operation, $query, $variable ) {


		$prev_schema = $this->get_previous_schema();

		if ( empty( $prev_schema ) ) {
			$this->store_current_schema( $schema );
		} else {

			if ( $prev_schema !== $this->encode_schema( $schema ) ) {
				// $breaking = $this->compare_schemas( $prev_schema, $schema );
				$post_id = $this->store_schema( $schema );
//				if ( ! empty( $breaking ) ) {
//					update_post_meta( $post_id, '_breaking', $breaking );
//					wp_send_json( $breaking );
//				}
			}
		}

		return $response;

//		$last_schema = new \WP_Query([
//			'post_type' => $this->post_type,
//			'posts_per_page' => 1,
//			'no_found_rows' => true,
//			'post_status' => 'publish'
//		]);
//
//		$prev_schema = isset( $last_schema->posts[0]->post_content ) ? $last_schema->posts[0]->post_content : null;
//
//		if ( ! $schema instanceof Schema ) {
//			return;
//		}
//
//		$printed = \GraphQL\Utils\SchemaPrinter::doPrint( $schema );
//		$encoded = base64_encode( $printed );
//
//		if ( $prev_schema === $encoded ) {
//			return;
//		}
//
//		$query = $wpdb->prepare( "SELECT
//			*
//			FROM $wpdb->posts
//			WHERE post_type = 'graphql_schema_logs'
//			AND post_content = %s", $encoded );
//
//		$existing = $wpdb->get_results( $query );
//
//		wp_send_json( $existing );
//
//		if ( empty( $existing ) ) {
//			wp_insert_post( [
//				'post_type'    => $this->post_type,
//				'post_status'  => 'publish',
//				'post_title'   => 'Schema Log',
//				'post_content' => $encoded
//			] );
//		}


//			$prev = BuildSchema::build( base64_decode( $existing->post_content ) );
//			$current = BuildSchema::build( base64_decode( $encoded ) );
//
//			wp_send_json( $prev );
//
//			$breaking = BreakingChangesFinder::findBreakingChanges( $prev, $current );
//
//			wp_send_json( $breaking );
//		}

	}

	/**
	 * Returns the previous Schema
	 *
	 * @return mixed|string|null
	 */
	public function get_previous_schema() {

		/**
		 * Query for the most recently stored Schema
		 */
		$query = new \WP_Query( [
			'post_type'      => $this->post_type,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'post_status'    => 'publish'
		] );

		/**
		 * Return the decoded Schema
		 */
		if ( ! isset( $query->posts[0]->post_content ) ) {
			return null;
		}
;
		return ! empty( $query->posts[0]->post_content ) ? $query->posts[0]->post_content : null;

	}

	/**
	 * @param string $schema
	 *
	 * @return Schema
	 */
	public function decode_schema( $schema ) {
		return json_decode( $schema );
	}

	public function encode_schema( $schema ) {
		return SchemaPrinter::doPrint( $schema );
	}

	public function compare_schemas( $prev_schema, $current_schema ) {
		$prev_schema = BuildSchema::build( $prev_schema );
		$current_schema = $this->encode_schema( $current_schema );
		$current_schema = BuildSchema::build( $current_schema );
		$breaking = BreakingChangesFinder::findBreakingChanges( $prev_schema, $current_schema );
		return $breaking;
	}

	public function store_current_schema( $schema ) {

		// If there's a Schema, encode it
		if ( ! empty( $schema ) ) {
			$schema = $this->encode_schema( $schema );
		}

		// Get the previous latest schema
		$prev_schema = get_option( 'graphql_latest_schema', null );

		if ( ! empty( $prev_schema ) ) {

			// Update the previous schema
			update_option( 'graphql_previous_schema', $prev_schema );
		}

		// Update the latest schema
		update_option( 'graphql_latest_schema', $schema );
	}

	/**
	 * @param $schema
	 *
	 * @return int|\WP_Error
	 */
	public function store_schema( $schema ) {

		if ( ! empty( $schema ) ) {
			$schema = $this->encode_schema( $schema );
		}

		$post_id = wp_insert_post( [
			'post_type'    => $this->post_type,
			'post_status'  => 'publish',
			'post_title'   => 'Schema Log',
			'post_content' => $schema
		] );

		return $post_id;
	}

	public function is_base64_encoded( $data ) {
		if ( ! empty( $data ) && preg_match( '%^[a-zA-Z0-9/+]*={0,2}$%', $data ) ) {
			return true;
		} else {
			return false;
		}
	}


}
