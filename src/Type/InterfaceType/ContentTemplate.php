<?php

namespace WPGraphQL\Type\InterfaceType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class ContentTemplate
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class ContentTemplate {

	/**
	 * Register the ContentTemplate Interface Type
	 *
	 * @param TypeRegistry $type_registry The WPGraphQL Type Registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'ContentTemplate',
			[
				'description' => __( 'The template assigned to a node of content', 'wp-graphql' ),
				'fields'      => [
					'templateName' => [
						'type'        => 'String',
						'description' => __( 'The name of the template', 'wp-graphql' ),
					],
				],
				'resolveType' => function( $value ) {
					return isset( $value['__typename'] ) ? $value['__typename'] : 'DefaultTemplate';
				},
			]
		);
	}
}
