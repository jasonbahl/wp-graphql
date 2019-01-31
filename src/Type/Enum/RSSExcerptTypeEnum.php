<?php
namespace WPGraphQL\Type;

$options = [ __( 'Full Text' ), __( 'Summary' ) ];

$values = [];
foreach ( $options as $key => $option ) {
	$values[ WPEnumType::get_safe_name( $option ) ] = [
		'value' => $key,
		'description' => __( $option, 'wp-graphql' )
	];
}

register_graphql_enum_type( 'RSSExcerptTypeEnum', [
	'description' => __( 'What to show for each article in a feed', 'wp-graphql' ),
	'values' => $values,
]);
