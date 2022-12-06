<?php

namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class MenuConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class MenuConnectionResolver extends TermObjectConnectionResolver {

	/**
	 * Get the connection args for use in WP_Term_Query to query the menus
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_query_args() {
		$term_args = [
			'hide_empty' => false,
			'include'    => [],
			'taxonomy'   => 'nav_menu',
			'fields'     => 'ids',
		];

		if ( ! empty( $this->args['where']['slug'] ) ) {
			$term_args['slug']    = $this->args['where']['slug'];
			$term_args['include'] = null;
		}

		$theme_locations = get_nav_menu_locations();

		// If a location is specified in the args, use it
		if ( ! empty( $this->args['where']['location'] ) ) {
			// Exclude unset and non-existent locations
			$term_args['include'] = ! empty( $theme_locations[ $this->args['where']['location'] ] ) ? $theme_locations[ $this->args['where']['location'] ] : -1;
			// If the current user cannot edit theme options
		} elseif ( ! current_user_can( 'edit_theme_options' ) ) {
			$term_args['include'] = array_values( $theme_locations );
		}

		if ( ! empty( $this->args['where']['id'] ) ) {
			$term_args['include'] = $this->args['where']['id'];
		}

		$query_args = parent::get_query_args();

		$query_args = array_merge( $query_args, $term_args );

		/**
		 * Filter the query_args that should be applied to the query. This filter is applied AFTER the input args from
		 * the GraphQL Query have been applied and has the potential to override the GraphQL Query Input Args.
		 *
		 * @param array       $query_args array of query_args being passed to the
		 * @param mixed       $source     source passed down from the resolve tree
		 * @param array       $args       array of arguments input in the field as part of the GraphQL query
		 * @param AppContext  $context    object passed down the resolve tree
		 * @param ResolveInfo $info       info about fields passed down the resolve tree
		 *
		 * @since 0.0.6
		 */
		return apply_filters( 'graphql_menu_connection_query_args', $query_args, $this->source, $this->args, $this->context, $this->info );
	}

}
