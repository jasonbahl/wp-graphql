<?php
namespace WPGraphQL\Type;

$options = [
	'newsest' => __( 'Last', 'wp-graphql' ),
	'oldest' => __( 'First', 'wp-graphql' )
];

$values = [];

foreach ( $options as $key => $description ) {
	$values[ WPEnumType::get_safe_name( $key ) ] = [
		'value' => $key,
		'description' => $description
	];
}

register_graphql_enum_type( 'DefaultCommentsPageEnum', [
	'description' => __( 'The default page comments should show', 'wp-graphql' ),
	'values' => $values
]);
