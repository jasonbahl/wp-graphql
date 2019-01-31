<?php
namespace WPGraphQL\Type;

$days = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
$values = [];

foreach ( $days as $key => $day ) {
	$values[ WPEnumType::get_safe_name( $day ) ] = [
		'value' => (int) $key,
		'description' => $day,
	];
}

register_graphql_enum_type( 'WeekdayEnum', [
	'description' => __( 'Days of the week. The underlying value is the integer of the day of the week from 0-6.', 'wp-graphql' ),
	'values' => $values
]);
