<?php
namespace WPGraphQL\Data\CursorPagination;

class WP_Comment_Query {

	/**
	 * This returns a modified version of the $pieces of the comment query clauses if the request is a GRAPHQL_REQUEST
	 * and the query has a graphql_cursor_offset defined
	 *
	 * @param array             $pieces A compacted array of comment query clauses.
	 * @param \WP_Comment_Query $query  Current instance of WP_Comment_Query, passed by reference.
	 *
	 * @return array $pieces
	 */
	public function filter_query( array $pieces, \WP_Comment_Query $query ) {

		if (
			defined( 'GRAPHQL_REQUEST' ) && GRAPHQL_REQUEST &&
			( is_array( $query->query_vars ) && array_key_exists( 'graphql_cursor_offset', $query->query_vars ) )
		) {

			$cursor_offset = $query->query_vars['graphql_cursor_offset'];

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare = ! empty( $query->get( 'graphql_cursor_compare' ) ) ? $query->get( 'graphql_cursor_compare' ) : '>';
				$compare = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';

				$order_by      = ! empty( $query->query_vars['order_by'] ) ? $query->query_vars['order_by'] : 'comment_date';
				$order         = ! empty( $query->query_vars['order'] ) ? $query->query_vars['order'] : 'DESC';
				$order_compare = ( 'ASC' === $order ) ? '>' : '<';

				// Get the $cursor_post
				$cursor_comment = get_comment( $cursor_offset );
				if ( ! empty( $cursor_comment ) ) {
					$pieces['where'] .= sprintf( " AND {$order_by} {$order_compare} '{$cursor_comment->{$order_by}}'" );
				} else {
					$pieces['where'] .= sprintf( ' AND comment_ID %1$s %2$d', $compare, $cursor_offset );
				}
			}
		}

		return $pieces;

	}

}