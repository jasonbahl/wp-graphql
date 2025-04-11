<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class OneToOneConnection {
	/**
	 * Register the Connection Interface
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'OneToOneConnection',
			[
				'description' => __( 'A singular connection from one Node to another, with support for relational data on the "edge" of the connection.', 'wp-graphql' ),
				'interfaces'  => [ 'Edge' ],
				'fields'      => function() {
					return [
					'node' => [
						'type'        => [ 'non_null' => 'Node' ],
						'description' => __( 'The connected node', 'wp-graphql' ),
					],
				];
			},
			]
		);
	}
}
