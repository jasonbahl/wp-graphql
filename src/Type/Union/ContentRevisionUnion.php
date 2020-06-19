<?php

namespace WPGraphQL\Type\Union;

use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class ContentRevisionUnion
 *
 * @package WPGraphQL\Type\Union
 */
class ContentRevisionUnion {

	/**
	 * Register the ContentRevisionUnion Type
	 *
	 * @param TypeRegistry $type_registry
	 * @return void;
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		$cpts_with_revisions              = get_post_types_by_support( 'revisions' );
		$allowed_post_types               = \WPGraphQL::get_allowed_post_types();
		$post_types_with_revision_support = array_intersect( $cpts_with_revisions, $allowed_post_types );

		if ( ! empty( $post_types_with_revision_support ) && is_array( $post_types_with_revision_support ) ) {

			$type_names = array_map(
				function( $post_type ) {
					$post_type_object = get_post_type_object( $post_type );

					return isset( $post_type_object->graphql_single_name ) ? $post_type_object->graphql_single_name : null;
				},
				$post_types_with_revision_support
			);

			register_graphql_union_type(
				'ContentRevisionUnion',
				[
					'typeNames'   => $type_names,
					'resolveType' => function( Post $object ) use ( $type_registry ) {

						$type   = 'Post';
						$parent = ! empty( $object->parentDatabaseId ) ? get_post( $object->parentDatabaseId ) : null;
						if ( ! empty( $parent ) && isset( $parent->post_type ) ) {
							$parent_post_type_object = get_post_type_object( $parent->post_type );
							if ( isset( $parent_post_type_object->graphql_single_name ) ) {
								$type = $type_registry->get_type( $parent_post_type_object->graphql_single_name );
							}
						}

						return ! empty( $type ) ? $type : null;

					},
				]
			);
		}

	}
}
