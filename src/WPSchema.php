<?php

namespace WPGraphQL;

use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;


/**
 * Class WPSchema
 *
 * Extends the Schema to make some properties accessible via hooks/filters
 *
 * @package WPGraphQL
 */
class WPSchema extends Schema {

	/**
	 * @var array|null
	 */
	public $config;

	/**
	 * Holds the $filterable_config which allows WordPress access to modifying the
	 * $config that gets passed down to the Executable Schema
	 *
	 * @var array|null
	 * @since 0.0.9
	 */
	public $filterable_config;

	/**
	 * WPSchema constructor.
	 *
	 * @param array|null $config The config for the Schema.
	 *
	 * @since 0.0.9
	 */
	public function __construct( $config ) {

		$this->config = $config;

		/**
		 * Set the $filterable_config as the $config that was passed to the WPSchema when instantiated
		 *
		 * @since 0.0.9
		 */
		$this->filterable_config = apply_filters( 'graphql_schema_config', $config );
		parent::__construct( $this->filterable_config );
	}

}
