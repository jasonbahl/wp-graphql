<?php

namespace WPGraphQL\Data\Connection;

class CommentAuthorConnectionResolver extends CommentConnectionResolver {

	public function get_loader_name() {
		return 'comment_author';
	}

	public function get_query_args() {
		$args = parent::get_query_args();

		// When querying for Comment Authors, we only care about comments
		// that have no user as the author
		$args['author__in'] = 0;

		return $args;

	}

}
