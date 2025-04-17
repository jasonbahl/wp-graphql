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
				'description' => __( 'Sorting attributes for comment collections. Specifies which comment property determines the order of results.', 'wp-graphql' ),
				'values'      => [
					'COMMENT_AGENT'        => [
						'description' => __( 'Order by browser user agent of the commenter.', 'wp-graphql' ),
						'value'       => 'comment_agent',
					],
					'COMMENT_APPROVED'     => [
						'description' => __( 'Order by approval status of the comment.', 'wp-graphql' ),
						'value'       => 'comment_approved',
					],
					'COMMENT_AUTHOR'       => [
						'description' => __( 'Order by name of the comment author.', 'wp-graphql' ),
						'value'       => 'comment_author',
					],
					'COMMENT_AUTHOR_EMAIL' => [
						'description' => __( 'Order by e-mail of the comment author.', 'wp-graphql' ),
						'value'       => 'comment_author_email',
					],
					'COMMENT_AUTHOR_IP'    => [
						'description' => __( 'Order by IP address of the comment author.', 'wp-graphql' ),
						'value'       => 'comment_author_IP',
					],
					'COMMENT_AUTHOR_URL'   => [
						'description' => __( 'Order by URL address of the comment author.', 'wp-graphql' ),
						'value'       => 'comment_author_url',
					],
					'COMMENT_CONTENT'      => [
						'description' => __( 'Order by the comment contents.', 'wp-graphql' ),
						'value'       => 'comment_content',
					],
					'COMMENT_DATE'         => [
						'description' => __( 'Chronological ordering by comment submission date.', 'wp-graphql' ),
						'value'       => 'comment_date',
					],
					'COMMENT_DATE_GMT'     => [
						'description' => __( 'Chronological ordering by comment date in UTC/GMT time.', 'wp-graphql' ),
						'value'       => 'comment_date_gmt',
					],
					'COMMENT_ID'           => [
						'description' => __( 'Ordering by internal ID (typically reflects creation order).', 'wp-graphql' ),
						'value'       => 'comment_ID',
					],
					'COMMENT_IN'           => [
						'description' => __( 'Preserve custom order of IDs as specified in the query.', 'wp-graphql' ),
						'value'       => 'comment__in',
					],
					'COMMENT_KARMA'        => [
						'description' => __( 'Order by the comment karma score.', 'wp-graphql' ),
						'value'       => 'comment_karma',
					],
					'COMMENT_PARENT'       => [
						'description' => __( 'Ordering by parent comment relationship (threaded discussions).', 'wp-graphql' ),
						'value'       => 'comment_parent',
					],
					'COMMENT_POST_ID'      => [
						'description' => __( 'Ordering by associated content item ID.', 'wp-graphql' ),
						'value'       => 'comment_post_ID',
					],
					'COMMENT_TYPE'         => [
						'description' => __( 'Ordering by comment classification (standard comments, pingbacks, etc.).', 'wp-graphql' ),
						'value'       => 'comment_type',
					],
					'USER_ID'              => [
						'description' => __( 'Ordering by the user account ID associated with the comment as the comment author.', 'wp-graphql' ),
						'value'       => 'user_id',
					],
				],
			]
		);
	}
}
