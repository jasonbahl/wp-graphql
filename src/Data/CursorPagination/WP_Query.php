<?php
namespace WPGraphQL\Data\CursorPagination;

class WP_Query {

	/**
	 * This filters the WPQuery 'where' $args, enforcing the query to return results before or after the
	 * referenced cursor
	 *
	 * @param string    $where The WHERE clause of the query.
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return string
	 */
	public function filter_query( $where, \WP_Query $query ) {

		/**
		 * Access the global $wpdb object
		 */
		global $wpdb;

		/**
		 * If there's a graphql_cursor_offset in the query, we should check to see if
		 * it should be applied to the query
		 */
		if ( defined( 'GRAPHQL_REQUEST' ) && GRAPHQL_REQUEST ) {

			$cursor_offset = ! empty( $query->query_vars['graphql_cursor_offset'] ) ? $query->query_vars['graphql_cursor_offset'] : 0;

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare          = ! empty( $query->get( 'graphql_cursor_compare' ) ) ? $query->get( 'graphql_cursor_compare' ) : '>';
				$compare          = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';
				$compare_opposite = ( '<' === $compare ) ? '>' : '<';

				// Get the $cursor_post
				$cursor_post = get_post( $cursor_offset );

				/**
				 * If the $cursor_post exists (hasn't been deleted), modify the query to compare based on the ID and post_date values
				 * But if the $cursor_post no longer exists, we're forced to just compare with the ID
				 *
				 */
				if ( ! empty( $cursor_post ) && ! empty( $cursor_post->post_date ) ) {
					$orderby = $query->get( 'orderby' );
					if ( ! empty( $orderby ) && is_array( $orderby ) ) {
						foreach ( $orderby as $by => $order ) {
							$order_compare = ( 'ASC' === $order ) ? '>' : '<';
							$value         = $cursor_post->{$by};
							if ( ! empty( $by ) && ! empty( $value ) ) {
								$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$by} {$order_compare} %s", $value );
							}
						}
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date {$compare}= %s AND NOT ( {$wpdb->posts}.post_date {$compare}= %s AND {$wpdb->posts}.ID {$compare_opposite}= %d )", esc_sql( $cursor_post->post_date ), esc_sql( $cursor_post->post_date ), absint( $cursor_offset ) );
					}
				} else {
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID {$compare} %d", $cursor_offset );
				}
			}
		}


		return $where;

	}

}