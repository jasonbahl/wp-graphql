<?php
namespace WPGraphQL\Type;

$ratings = [
	/* translators: Content suitability rating: https://en.wikipedia.org/wiki/Motion_Picture_Association_of_America_film_rating_system */
	'G'  => __( 'G &#8212; Suitable for all audiences' ),
	/* translators: Content suitability rating: https://en.wikipedia.org/wiki/Motion_Picture_Association_of_America_film_rating_system */
	'PG' => __( 'PG &#8212; Possibly offensive, usually for audiences 13 and above' ),
	/* translators: Content suitability rating: https://en.wikipedia.org/wiki/Motion_Picture_Association_of_America_film_rating_system */
	'R'  => __( 'R &#8212; Intended for adult audiences above 17' ),
	/* translators: Content suitability rating: https://en.wikipedia.org/wiki/Motion_Picture_Association_of_America_film_rating_system */
	'X'  => __( 'X &#8212; Even more mature than above' ),
];

$values = [];
foreach ( $ratings as $rating => $description ) {
	$values[ WPEnumType::get_safe_name( $rating ) ] = [
		'value' => $rating,
		'description' => $description
	];
}

register_graphql_enum_type( 'AvatarRatingEnum', [
	'description' => __( "What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', and are judged in that order. Default is the value of the 'avatar_rating' option", 'wp-graphql' ),
	'values'      => $values
] );
