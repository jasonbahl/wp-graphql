<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

/**
 * Class NodeWithRevisions
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class NodeWithRevisions {

	/**
	 * Register the NodeWithRevisions Interface Type
	 *
	 * @param TypeRegistry $type_registry The WPGraphQL Type Registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithRevisions',
			[
				'description' => __( 'A node that can have revisions', 'wp-graphql' ),
				'fields'      => [
					'isRevision' => [
						'type'        => 'Boolean',
						'description' => __( 'True if the node is a revision of another node', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
