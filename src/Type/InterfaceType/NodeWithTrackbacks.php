<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

/**
 * Class NodeWithTrackbacks
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class NodeWithTrackbacks {

	/**
	 * Register the NodeWithTrackbacks Interface Type
	 *
	 * @param TypeRegistry $type_registry The WPGraphQL Type Registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithTrackbacks',
			[
				'description' => __( 'A node that can have trackbacks and pingbacks', 'wp-graphql' ),
				'fields'      => [
					'toPing'     => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'URLs queued to be pinged.', 'wp-graphql' ),
					],
					'pinged'     => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'URLs that have been pinged.', 'wp-graphql' ),
					],
					'pingStatus' => [
						'type'        => 'String',
						'description' => __( 'Whether the pings are open or closed for this particular post.', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
