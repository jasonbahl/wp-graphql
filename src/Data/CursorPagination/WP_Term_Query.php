<?php
namespace WPGraphQL\Data\CursorPagination;

class WP_Term_Query {

	/**
	 * This filters the term_clauses in the WP_Term_Query to support cursor based pagination, where we can
	 * move forward or backward from a particular record, instead of typical offset pagination which can be
	 * much more expensive and less accurate.
	 *
	 * @param array $pieces     Terms query SQL clauses.
	 * @param array $taxonomies An array of taxonomies.
	 * @param array $args       An array of terms query arguments.
	 *
	 * @return array $pieces
	 */
	public function filter_query( array $pieces, array $taxonomies, array $args ) {

		if ( defined( 'GRAPHQL_REQUEST' ) && GRAPHQL_REQUEST && ! empty( $args['graphql_cursor_offset'] ) ) {

			$cursor_offset = $args['graphql_cursor_offset'];

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare = ! empty( $args['graphql_cursor_compare'] ) ? $args['graphql_cursor_compare'] : '>';
				$compare = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';

				$order_by      = ! empty( $args['orderby'] ) ? $args['orderby'] : 'comment_date';
				$order         = ! empty( $args['order'] ) ? $args['order'] : 'DESC';
				$order_compare = ( 'ASC' === $order ) ? '>' : '<';

				// Get the $cursor_post
				$cursor_term = get_term( $cursor_offset );

				if ( ! empty( $cursor_term ) && ! empty( $cursor_term->name ) ) {
					$pieces['where'] .= sprintf( " AND t.{$order_by} {$order_compare} '{$cursor_term->{$order_by}}'" );
				} else {
					$pieces['where'] .= sprintf( ' AND t.term_id %1$s %2$d', $compare, $cursor_offset );
				}
			}
		}

		return $pieces;

	}

}