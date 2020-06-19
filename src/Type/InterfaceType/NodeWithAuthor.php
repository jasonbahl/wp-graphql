<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

/**
 * Class NodeWithAuthor
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class NodeWithAuthor {

	/**
	 * Register the NodeWithAuthor Interface Type
	 *
	 * @param TypeRegistry $type_registry Instance of the Type Registry
	 *
	 * @return void
	 */
	public static function register_type( $type_registry ) {
		register_graphql_interface_type(
			'NodeWithAuthor',
			[
				'description' => __( 'A node that can have an author assigned to it', 'wp-graphql' ),
				'fields'      => [
					'authorId'         => [
						'type'        => 'ID',
						'description' => __( 'The globally unique identifier of the author of the node', 'wp-graphql' ),
					],
					'authorDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'The database identifier of the author of the node', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
