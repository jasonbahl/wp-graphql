<?php

namespace WPGraphQL\Type\OneOfInput;

/**
 * Class CommentBy
 *
 * OneOf input type for identifying a Comment by various methods.
 * This replaces the legacy id/idType pattern with a semantic oneOf approach.
 *
 * @package WPGraphQL\Type\OneOfInput
 */
class CommentBy {

	/**
	 * Register the CommentBy input type
	 */
	public static function register_type(): void {
		register_graphql_input_type(
			'CommentBy',
			[
				'description' => static function () {
					return __( 'Identify a Comment by one of several methods', 'wp-graphql' );
				},
				'fields'      => [
					'id'         => [
						'type'        => 'ID',
						'description' => static function () {
							return __( 'The globally unique ID', 'wp-graphql' );
						},
					],
					'databaseId' => [
						'type'        => 'ID', // Use ID type for compatibility with legacy transformations
						'description' => static function () {
							return __( 'The database ID', 'wp-graphql' );
						},
					],
				],
				'isOneOf'     => true,
			]
		);
	}
}
