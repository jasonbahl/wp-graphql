<?php

namespace WPGraphQL\Type\OneOfInput;

/**
 * Class UserBy
 *
 * OneOf input type for identifying a User by various methods.
 * This replaces the legacy id/idType pattern with a semantic oneOf approach.
 *
 * @package WPGraphQL\Type\OneOfInput
 */
class UserBy {

	/**
	 * Register the UserBy input type
	 */
	public static function register_type(): void {
		register_graphql_input_type(
			'UserBy',
			[
				'description' => static function () {
					return __( 'Identify a User by one of several methods', 'wp-graphql' );
				},
				'fields'      => [
					'id'         => [
						'type'        => 'ID',
						'description' => static function () {
							return __( 'The globally unique ID', 'wp-graphql' );
						},
					],
					'databaseId' => [
						'type'        => 'ID', // Changed from Int to ID for compatibility with legacy transformations
						'description' => static function () {
							return __( 'The database ID', 'wp-graphql' );
						},
					],
					'uri'        => [
						'type'        => 'String',
						'description' => static function () {
							return __( 'The URI/path', 'wp-graphql' );
						},
					],
					'slug'       => [
						'type'        => 'String',
						'description' => static function () {
							return __( 'The user slug/nicename', 'wp-graphql' );
						},
					],
					'email'      => [
						'type'        => 'String',
						'description' => static function () {
							return __( 'The email address', 'wp-graphql' );
						},
					],
					'username'   => [
						'type'        => 'String',
						'description' => static function () {
							return __( 'The username/login', 'wp-graphql' );
						},
					],
				],
				'isOneOf'     => true,
			]
		);
	}
}
