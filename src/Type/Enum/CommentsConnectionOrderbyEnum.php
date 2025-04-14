<?php
namespace WPGraphQL\Type\Enum;

class CommentsConnectionOrderbyEnum {

	/**
	 * Register the CommentsConnectionOrderbyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'CommentsConnectionOrderbyEnum',
			[
				'description' => static function () {
					return __( 'Options for ordering the connection', 'wp-graphql' );
				},
				'values'      => [
					'COMMENT_AGENT'        => [
						'description' => static function () {
							return __( 'Order by browser user agent of the commenter.', 'wp-graphql' );
						},
						'value'       => 'comment_agent',
					],
					'COMMENT_APPROVED'     => [
						'description' => static function () {
							return __( 'Order by approval status of the comment.', 'wp-graphql' );
						},
						'value'       => 'comment_approved',
					],
					'COMMENT_AUTHOR'       => [
						'description' => static function () {
							return __( 'Order by name of the comment author.', 'wp-graphql' );
						},
						'value'       => 'comment_author',
					],
					'COMMENT_AUTHOR_EMAIL' => [
						'description' => static function () {
							return __( 'Order by e-mail of the comment author.', 'wp-graphql' );
						},
						'value'       => 'comment_author_email',
					],
					'COMMENT_AUTHOR_IP'    => [
						'description' => static function () {
							return __( 'Order by IP address of the comment author.', 'wp-graphql' );
						},
						'value'       => 'comment_author_IP',
					],
					'COMMENT_AUTHOR_URL'   => [
						'description' => static function () {
							return __( 'Order by URL address of the comment author.', 'wp-graphql' );
						},
						'value'       => 'comment_author_url',
					],
					'COMMENT_CONTENT'      => [
						'description' => static function () {
							return __( 'Order by the comment contents.', 'wp-graphql' );
						},
						'value'       => 'comment_content',
					],
					'COMMENT_DATE'         => [
						'description' => static function () {
							return __( 'Order by date/time timestamp of the comment.', 'wp-graphql' );
						},
						'value'       => 'comment_date',
					],
					'COMMENT_DATE_GMT'     => [
						'description' => static function () {
							return __( 'Order by GMT timezone date/time timestamp of the comment.', 'wp-graphql' );
						},
						'value'       => 'comment_date_gmt',
					],
					'COMMENT_ID'           => [
						'description' => static function () {
							return __( 'Order by the globally unique identifier for the comment object', 'wp-graphql' );
						},
						'value'       => 'comment_ID',
					],
					'COMMENT_IN'           => [
						'description' => static function () {
							return __( 'Order by the array list of comment IDs listed in the where clause.', 'wp-graphql' );
						},
						'value'       => 'comment__in',
					],
					'COMMENT_KARMA'        => [
						'description' => static function () {
							return __( 'Order by the comment karma score.', 'wp-graphql' );
						},
						'value'       => 'comment_karma',
					],
					'COMMENT_PARENT'       => [
						'description' => static function () {
							return __( 'Order by the comment parent ID.', 'wp-graphql' );
						},
						'value'       => 'comment_parent',
					],
					'COMMENT_POST_ID'      => [
						'description' => static function () {
							return __( 'Order by the post object ID.', 'wp-graphql' );
						},
						'value'       => 'comment_post_ID',
					],
					'COMMENT_TYPE'         => [
						'description' => static function () {
							return __( 'Order by the the type of comment, such as \'comment\', \'pingback\', or \'trackback\'.', 'wp-graphql' );
						},
						'value'       => 'comment_type',
					],
					'USER_ID'              => [
						'description' => static function () {
							return __( 'Order by the user ID.', 'wp-graphql' );
						},
						'value'       => 'user_id',
					],
				],
			]
		);
	}
}
