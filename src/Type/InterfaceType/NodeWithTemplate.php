<?php
namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithTemplate {

	/**
	 * Registers the NodeWithTemplate Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type( 'NodeWithTemplate', [
			'description' => __( 'A node that can have a template associated with it', 'wp-graphql' ),
			'interfaces'  => [ 'Node', 'ContentNode', 'DatabaseIdentifier' ],
			'fields'      => [
				'template' => [
					'description' => __( 'The template assigned to the node', 'wp-graphql' ),
					'type'        => 'ContentTemplate',
				],
			],
		]);
	}
}
