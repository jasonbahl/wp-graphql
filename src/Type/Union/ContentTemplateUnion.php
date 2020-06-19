<?php
namespace WPGraphQL\Type\Union;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class ContentTemplateUnion
 *
 * @package WPGraphQL\Type\Union
 */
class ContentTemplateUnion {

	/**
	 * Register the ContentTemplateUnion Type
	 *
	 * @param TypeRegistry $type_registry The WPGraphQL Type Registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		$theme                = wp_get_theme();
		$registered_templates = (array) $theme->get_post_templates() ?? [];

		if ( is_array( $registered_templates ) && ! empty( $registered_templates ) ) {

			$page_templates['default'] = 'Default';
			foreach ( $registered_templates as $post_type_templates ) {
				$post_type_templates = (array) $post_type_templates;
				if ( is_array( $post_type_templates ) && ! empty( $post_type_templates ) ) {
					foreach ( $post_type_templates as $file => $name ) {
						$page_templates[ $file ] = $name;
					}
				}
			}
		}

		if ( ! empty( $page_templates ) && is_array( $page_templates ) ) {
			$type_names         = [];
			$type_names_by_file = [];
			foreach ( $page_templates as $file => $name ) {
				$name               = ucwords( $name );
				$name               = preg_replace( '/[^\w]/', '', $name );
				$template_type_name = $name . 'Template';
				register_graphql_object_type(
					$template_type_name,
					[
						'interfaces'  => [ 'ContentTemplate' ],
						// Translators: Placeholder is the name of the GraphQL Type in the Schema
						'description' => __( 'The template assigned to the node', 'wp-graphql' ),
						'fields'      => [
							'templateName' => [
								'resolve' => function( $template ) {
									return isset( $template['templateName'] ) ? $template['templateName'] : null;
								},
							],
							'templateFile' => [
								'resolve' => function( $template ) {
									return isset( $template['templateFile'] ) ? $template['templateFile'] : null;
								},
							],
						],
					]
				);
				$type_names[]                = $template_type_name;
				$type_names_by_file[ $file ] = $template_type_name;

			}

			if ( ! empty( $type_names ) ) {

				register_graphql_union_type(
					'ContentTemplateUnion',
					[
						'typeNames'   => $type_names,
						'resolveType' => function( $value ) {
							return isset( $value['__typename'] ) ? $value['__typename'] : 'DefaultTemplate';
						},
					]
				);

			}
		}

	}
}
