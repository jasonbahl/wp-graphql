<?php

namespace WPGraphQL\Data;

/**
 * Class Config
 *
 * This class contains configurations for various data-related things, such as query filters for cursor pagination.
 *
 * @package WPGraphQL\Data
 */
class Config {

	/**
	 * Config constructor.
	 */
	public function __construct() {

		/**
		 * Filter the term_clauses in the WP_Term_Query to allow for cursor pagination support where a Term ID
		 * can be used as a point of comparison when slicing the results to return.
		 */
		add_filter( 'comments_clauses', [ new \WPGraphQL\Data\CursorPagination\WP_Comment_Query(), 'filter_query' ], 10, 2 );

		/**
		 * Filter the WP_Query to support cursor based pagination where a post ID can be used
		 * as a point of comparison when slicing the results to return.
		 */
		add_filter( 'posts_where', [ new \WPGraphQL\Data\CursorPagination\WP_Query(), 'filter_query' ], 10, 2 );

		/**
		 * Filter the term_clauses in the WP_Term_Query to allow for cursor pagination support where a Term ID
		 * can be used as a point of comparison when slicing the results to return.
		 */
		add_filter( 'terms_clauses', [ new \WPGraphQL\Data\CursorPagination\WP_Term_Query(), 'filter_query' ], 10, 3 );

	}

}
