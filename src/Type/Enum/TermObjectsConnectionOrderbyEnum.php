<?php
namespace WPGraphQL\Type\Enum;

class TermObjectsConnectionOrderbyEnum {

	/**
	 * Register the TermObjectsConnectionOrderbyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'TermObjectsConnectionOrderbyEnum',
			[
				'description' => __( 'Sorting attributes for taxonomy term collections. Determines which property of taxonomy terms is used for ordering results.', 'wp-graphql' ),
				'values'      => [
					'COUNT'       => [
						'value'       => 'count',
						'description' => __( 'Ordering by number of associated content items.', 'wp-graphql' ),
					],
					'DESCRIPTION' => [
						'value'       => 'description',
						'description' => __( 'Alphabetical ordering by term description text.', 'wp-graphql' ),
					],
					'NAME'        => [
						'value'       => 'name',
						'description' => __( 'Alphabetical ordering by term name.', 'wp-graphql' ),
					],
					'SLUG'        => [
						'value'       => 'slug',
						'description' => __( 'Alphabetical ordering by URL-friendly name.', 'wp-graphql' ),
					],
					'TERM_GROUP'  => [
						'value'       => 'term_group',
						'description' => __( 'Ordering by assigned term grouping value.', 'wp-graphql' ),
					],
					'TERM_ID'     => [
						'value'       => 'term_id',
						'description' => __( 'Ordering by internal identifier.', 'wp-graphql' ),
					],
					'TERM_ORDER'  => [
						'value'       => 'term_order',
						'description' => __( 'Ordering by manually defined sort position.', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
