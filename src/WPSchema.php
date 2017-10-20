<?php

namespace WPGraphQL;

use GraphQL\Executor\Executor;
use GraphQL\Schema;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Type\WPObjectType;

/**
 * Class WPSchema
 *
 * Extends the Schema to make some properties accessible via hooks/filters
 *
 * @package WPGraphQL
 */
class WPSchema extends Schema {

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
	 * @param array|null $config
	 *
	 * @since 0.0.9
	 */
	public function __construct( $config ) {

		/**
		 * Set the $filterable_config as the $config that was passed to the WPSchema when instantiated
		 *
		 * @since 0.0.9
		 */
		$this->filterable_config = apply_filters( 'graphql_schema_config', $config );

		parent::__construct( $this->filterable_config );
	}

	/**
	 * This takes in the Schema and escapes it before it's returned to the executor.
	 *
	 * @param \WPGraphQL\WPSchema $schema
	 *
	 * @return mixed
	 */
	public static function sanitize_schema( \WPGraphQL\WPSchema $schema ) {

		/**
		 * Get the prepared TypeMap
		 */
		$types = $schema->getTypeMap();

		/**
		 * Ensure there are types
		 */
		if ( ! empty( $types ) && is_array( $types ) ) {

			/**
			 * Loop through the types
			 */
			foreach ( $types as $type_name => $type_object ) {

				/**
				 * esc the values
				 */
				if ( $type_object instanceof ObjectType || $type_object instanceof WPObjectType ) {

					$sanitized_types[ $type_name ]                    = $type_object;
					$sanitized_types[ $type_name ]->name              = esc_html( $type_object->name );
					$sanitized_types[ $type_name ]->description       = esc_html( $type_object->description );
					$sanitized_types[ $type_name ]->deprecationReason = esc_html( $type_object->description );
					$sanitized_types[ $type_name ]->config['fields']  = self::sanitize_fields_and_wrap_resolver( $type_object->getFields(), $type_object );

				}

			}
		}

		/**
		 * Ensure there are $sanitized_types, and set the config's types as the sanitized types
		 */
		if ( ! empty( $sanitized_types ) && is_array( $sanitized_types ) ) {
			$schema->filterable_config['types'] = $sanitized_types;
		}

		/**
		 * Return the $schema with the sanitized types
		 */
		return $schema;

	}

	/**
	 * Sanitizes the fields of the schema to make sure names and descriptions are safely escaped. Also wraps the
	 * resolver to provide hooks and filters.
	 *
	 * @param array                   $fields      The fields defined for the Type
	 * @param WPObjectType|ObjectType $type_object The Type definition
	 *
	 * @return mixed
	 */
	protected static function sanitize_fields_and_wrap_resolver( $fields, $type_object ) {

		/**
		 * If there are fields configured for the Type object
		 */
		if ( ! empty( $fields ) && is_array( $fields ) ) {

			/**
			 * Sanitize each field, and wrap the resolver
			 */
			foreach ( $fields as $field_key => $field ) {
				if ( $field instanceof FieldDefinition ) {
					$field->name        = esc_html( $field->name );
					$field->description = esc_html( $field->description );
					$field_resolver     = $field->resolveFn;
					$field->resolveFn   = function( $source, array $args, AppContext $context, ResolveInfo $info ) use ( $field, $type_object, $field_resolver ) {
						return self::wrapped_resolver( $field_resolver, $field, $type_object, $source, $args, $context, $info );
					};
				}
			}
		}

		/**
		 * Return the sanitized field
		 */
		return $fields;

	}

	/**
	 * This takes a field resolver and wraps it with actions and filters the output providing APIs
	 * for developers to take advantage of the Resolve process.
	 *
	 * @param callable        $field_resolver The resolve function for the field
	 * @param FieldDefinition $field          The Field definition
	 * @param ObjectType      $type_object    The Type definition that the field belongs to
	 * @param mixed           $source         The source being passed down the Resolve Tree
	 * @param array           $args           The input args for the field
	 * @param AppContext      $context        The AppContext passed down the resolve tree
	 * @param ResolveInfo     $info           The ResolveInfo passed down the resolve tree
	 *
	 * @return callable
	 */
	public static function wrapped_resolver( $field_resolver, FieldDefinition $field, ObjectType $type_object, $source, $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * If there is no defined resolver on the field, use the defaultFieldResolver, otherwise use
		 * the defined resolve function for the field
		 */
		if ( null === $field_resolver || ! is_callable( $field_resolver ) ) {
			$resolve = Executor::defaultFieldResolver( $source, $args, $context, $info );
		} else {
			$resolve = call_user_func( $field_resolver, $source, $args, $context, $info );
		}

		/**
		 * Run an action BEFORE resolving. Can be useful for things like tracking resolver performance, etc.
		 *
		 * @param FieldDefinition $field       The Field being resolved
		 * @param ObjectType      $type_object The Type the resolving field belongs to
		 * @param mixed           $source      The source being passed down the Resolve Tree
		 * @param array           $args        The input args for the field
		 * @param AppContext      $context     The AppContext passed down the resolve tree
		 * @param ResolveInfo     $info        The ResolveInfo passed down the resolve tree
		 */
		do_action( 'graphql_before_resolve', $field, $type_object, $source, $args, $context, $info );

		/**
		 * Resolve the field, with a filter applied
		 *
		 * @param callable        $resolve     The resolve function that is executed
		 * @param FieldDefinition $field       The Field being resolved
		 * @param ObjectType      $type_object The Type the resolving field belongs to
		 * @param mixed           $source      The source being passed down the Resolve Tree
		 * @param array           $args        The input args for the field
		 * @param AppContext      $context     The AppContext passed down the resolve tree
		 * @param ResolveInfo     $info        The ResolveInfo passed down the resolve tree
		 */
		$resolve = apply_filters( 'graphql_resolver', $resolve, $field, $type_object, $source, $args, $context, $info );

		/**
		 * Run an action AFTER resolving. Can be useful for things like tracking resolver performance, etc.
		 *
		 * @param FieldDefinition $field       The Field being resolved
		 * @param ObjectType      $type_object The Type the resolving field belongs to
		 * @param mixed           $source      The source being passed down the Resolve Tree
		 * @param array           $args        The input args for the field
		 * @param AppContext      $context     The AppContext passed down the resolve tree
		 * @param ResolveInfo     $info        The ResolveInfo passed down the resolve tree
		 */
		do_action( 'graphql_after_resolve', $field, $type_object, $source, $args, $context, $info );

		return $resolve;
	}
}
