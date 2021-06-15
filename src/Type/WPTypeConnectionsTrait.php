<?php
namespace WPGraphQL\Type;

use Exception;

/**
 * Trait WPTypeConnectionsTrait
 *
 * @package WPGraphQL\Type
 */
trait WPTypeConnectionsTrait {

	/**
	 * Given a config array, determines if any connections for the type have been configured
	 * and registers them to the Schema.
	 *
	 * @param string $from_type_name The Name of the type the connection is coming from
	 * @param array $config The Type config
	 *
	 * @throws Exception
	 */
	public function register_type_connections( string $from_type_name, array $config ) {

		if ( ! empty( $config['connections'] ) && is_array( $config['connections'] ) ) {
			foreach ( $config['connections'] as $field_name => $connection_config ) {

				if ( ! is_array( $connection_config ) ) {
					continue;
				}
				$connection_config['fromType']      = $from_type_name;
				$connection_config['fromFieldName'] = $field_name;

				register_graphql_connection( $connection_config );

			}
		}

	}

}
