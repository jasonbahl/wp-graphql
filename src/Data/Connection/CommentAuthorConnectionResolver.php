<?php

namespace WPGraphQL\Data\Connection;

class CommentAuthorConnectionResolver extends CommentConnectionResolver {

	public function get_loader_name() {
		return 'comment_author';
	}

}
