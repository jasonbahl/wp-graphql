<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'CommentsConnectionOrderByEnum', [
	'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
	'values' => [
		'COMMENT_AGENT'        => [
			'value' => 'comment_agent',
			'description' => __( 'Order by the comment agent field', 'wp-graphql' ),
		],
		'COMMENT_APPROVED'     => [
			'value' => 'comment_approved',
			'description' => __( 'Order by the comment approved field', 'wp-graphql' ),
		],
		'COMMENT_AUTHOR'       => [
			'value' => 'comment_author',
			'description' => __( 'Order by the comment author', 'wp-graphql' ),
		],
		'COMMENT_AUTHOR_EMAIL' => [
			'value' => 'comment_author_email',
			'description' => __( 'Order by the comment author email', 'wp-graphql' ),
		],
		'COMMENT_AUTHOR_IP'    => [
			'value' => 'comment_author_IP',
			'description' => __( 'Order by the comment author IP', 'wp-graphql' ),
		],
		'COMMENT_AUTHOR_URL'   => [
			'value' => 'comment_author_url',
			'description' => __( 'Order by the comment author url', 'wp-graphql' ),
		],
		'COMMENT_CONTENT'      => [
			'value' => 'comment_content',
			'description' => __( 'Order by the comment content', 'wp-graphql' ),
		],
		'COMMENT_DATE'         => [
			'value' => 'comment_date',
			'description' => __( 'Order by the comment date', 'wp-graphql' ),
		],
		'COMMENT_DATE_GMT'     => [
			'value' => 'comment_date_gmt',
			'description' => __( 'Order by the comment date GMT', 'wp-graphql' ),
		],
		'COMMENT_ID'           => [
			'value' => 'comment_ID',
			'description' => __( 'Order by the comment ID', 'wp-graphql' ),
		],
		'COMMENT_KARMA'        => [
			'value' => 'comment_karma',
			'description' => __( 'Order by the comment karma', 'wp-graphql' ),
		],
		'COMMENT_PARENT'       => [
			'value' => 'comment_parent',
			'description' => __( 'Order by the comment parent', 'wp-graphql' ),
		],
		'COMMENT_POST_ID'      => [
			'value' => 'comment_post_ID',
			'description' => __( 'Order by the ID of the Post the comment was posted to', 'wp-graphql' ),
		],
		'COMMENT_TYPE'         => [
			'value' => 'comment_type',
			'description' => __( 'Order by the comment type', 'wp-graphql' ),
		],
		'USER_ID'              => [
			'value' => 'user_id',
			'description' => __( 'Order by the ID of the user that made the comment', 'wp-graphql' ),
		],
		'COMMENT_IN'           => [
			'value' => 'comment__in',
			'description' => __( 'Order by the ids passed to the COMMENT_IN field', 'wp-graphql' ),
		],
	],
] );
