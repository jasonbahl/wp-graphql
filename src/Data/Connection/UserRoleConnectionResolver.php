<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\User;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 * @since   0.0.5
 */
class UserRoleConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 *
	 * @var array
	 */
	protected $query;

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {

		// Given a list of role slugs
		if ( isset( $this->query_args['slugIn'] ) ) {
			return $this->query_args['slugIn'];
		}

		$ids     = [];
		$queried = $this->get_query();

		if ( empty( $queried ) ) {
			return $ids;
		}

		foreach ( $queried as $key => $item ) {
			$ids[ $key ] = $item;
		}

		return $ids;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_query_args() {
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
		return apply_filters( 'graphql_user_role_connection_query_args', [], $this->source, $this->args, $this->context, $this->info );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	public function get_query() {
		$wp_roles = wp_roles();
		$roles    = ! empty( $wp_roles->get_names() ) ? array_keys( $wp_roles->get_names() ) : [];

		return $roles;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_loader_name() {
		return 'user_role';
	}

	/**
	 * @param mixed $offset Whether the provided offset is valid for the connection
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return (bool) get_role( $offset );
	}

	/**
	 * @return bool
	 */
	public function should_execute() {

		if (
			current_user_can( 'list_users' ) ||
			(
				$this->source instanceof User &&
				get_current_user_id() === $this->source->userId
			)
		) {
			return true;
		}

		return false;
	}

}
