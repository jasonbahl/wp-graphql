<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class UserRoleEnum {

	/**
	 * Register the UserRoleEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$all_roles = wp_roles()->roles;
		$roles     = [];

		foreach ( $all_roles as $key => $role ) {
			$formatted_role = WPEnumType::get_safe_name( isset( $role['name'] ) ? $role['name'] : $key );

			switch ( $role ) {
				case 'administrator':
					$description = __( 'Full system access with ability to manage all aspects of the site.', 'wp-graphql' );
					break;
				case 'editor':
					$description = __( 'Content management access without administrative capabilities.', 'wp-graphql' );
					break;
				case 'author':
					$description = __( 'Can publish and manage their own content.', 'wp-graphql' );
					break;
				case 'contributor':
					$description = __( 'Can write and manage their own content but cannot publish.', 'wp-graphql' );
					break;
				case 'subscriber':
					$description = __( 'Can only manage their profile and read content.', 'wp-graphql' );
					break;
				default:
					$description = __( 'User role with specific capabilities', 'wp-graphql' );
			}

			$roles[ $formatted_role ] = [
				'description' => $description,
				'value'       => $key,
			];
		}

		// Bail if there are no roles to register.
		if ( empty( $roles ) ) {
			return;
		}

		register_graphql_enum_type(
			'UserRoleEnum',
			[
				'description' => __( 'Permission levels for user accounts. Defines the standard access levels that control what actions users can perform within the system.', 'wp-graphql' ),
				'values'      => $roles,
			]
		);
	}

}
