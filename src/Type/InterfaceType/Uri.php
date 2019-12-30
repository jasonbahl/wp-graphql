<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;

class Uri {
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'Uri',
			[
				'description' => __( 'Any node that has a URI', 'wp-graphql' ),
				'fields'      => [
					'uri' => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'The unique resource identifier path', 'wp-graphql' ),
					],
				],
				'resolveType' => function( $node ) use ( $type_registry ) {

					switch ( true ) {
						case $node instanceof Post:
							$type = $type_registry->get_type( get_post_type_object( $node->post_type )->graphql_single_name );
							break;
						case $node instanceof Term:
							$type = $type_registry->get_type( get_taxonomy( $node->taxonomyName )->graphql_single_name );
							break;

						case $node instanceof User:
							$type = $type_registry->get_type( 'User' );
							break;
						default:
							$type = null;
					}

					return $type;

				},
			]
		);
	}
}