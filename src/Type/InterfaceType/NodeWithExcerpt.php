<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

/**
 * Class NodeWithExcerpt
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class NodeWithExcerpt {

	/**
	 * Register the NodeWithExcerpt Interface Type
	 *
	 * @param TypeRegistry $type_registry Instance of the Type Registry
	 *
	 * @return void
	 */
	public static function register_type( $type_registry ) {
		register_graphql_interface_type(
			'NodeWithExcerpt',
			[
				'description' => __( 'A node that can have an excerpt', 'wp-graphql' ),
				'fields'      => [
					'excerpt' => [
						'type'        => 'String',
						'description' => __( 'The excerpt of the post.', 'wp-graphql' ),
						'args'        => [
							'format' => [
								'type'        => 'PostObjectFieldFormatEnum',
								'description' => __( 'Format of the field output', 'wp-graphql' ),
							],
						],
						'resolve'     => function( $source, $args ) {
							if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
								// @codingStandardsIgnoreLine.
								return $source->excerptRaw;
							}

							// @codingStandardsIgnoreLine.
							return $source->excerptRendered;
						},
					],
				],
			]
		);
	}
}
