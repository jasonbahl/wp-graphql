# GraphQL Directives

This document outlines the implementation of GraphQL Directives in WPGraphQL.

## Overview

Directives are a powerful feature of GraphQL that allow for decorating parts of a GraphQL schema or query with additional configuration.

## API Usage

### `register_graphql_directive()`

To register a directive, you can use the `register_graphql_directive()` function.

```php
register_graphql_directive( 'myDirective', [
    'name' => 'myDirective',
    'description' => 'A description of the directive',
    'locations' => [
        // See available locations in GraphQL\Language\DirectiveLocation
    ],
    'args' => [
        'myArg' => [
            'type' => 'String',
            'description' => 'A description of the argument'
        ]
    ],
    // Field execution callbacks
    'before_resolve_field' => function( $source, $field_args, $context, $info, $type_name, $field_key, $field, $field_resolver, $directive_args, $directive ) {
        // do something before the field resolves
    },
    'resolve_field' => function( $result, $source, $field_args, $context, $info, $type_name, $field_key, $field, $field_resolver, $directive_args, $directive ) {
        // do something with the resolved value
        return $result;
    },
    'after_resolve_field' => function( $result, $source, $field_args, $context, $info, $type_name, $field_key, $field, $field_resolver, $directive_args, $directive ) {
        // do something after the field resolves
    },

    // Query execution callbacks
    'before_execute_query' => function( $request, $operation, $directive_args, $directive ) {
        // do something before the query is executed
    },
    'after_execute_query' => function( $response, $request, $operation, $directive_args, $directive ) {
        // do something after the query is executed
    }
] );
```

### Config Arguments

The `register_graphql_directive()` function accepts a config array with the following arguments, which are passed to the underlying `\GraphQL\Type\Definition\Directive` class:

- **`name`** (`string`): The name of the directive.
- **`description`** (`string`): A description of the directive.
- **`locations`** (`array`): An array of locations where the directive can be used. See `GraphQL\Language\DirectiveLocation` for a list of available locations.
- **`args`** (`array`): An array of arguments the directive accepts.
- **`before_resolve_field`** (`callable`): A callback function that is executed before a field is resolved. This hooks into the `graphql_before_resolve_field` action.
- **`resolve_field`** (`callable`): A callback function that is executed when the directive is encountered on a field. This hooks into the `graphql_resolve_field` filter and allows the resolved value to be modified.
- **`after_resolve_field`** (`callable`): A callback function that is executed after a field is resolved. This hooks into the `graphql_after_resolve_field` action.
- **`before_execute_query`** (`callable`): A callback function that is executed when a directive is encountered on a query, before the query is executed. This hooks into the `graphql_before_execute` action.
- **`after_execute_query`** (`callable`): A callback function that is executed when a directive is encountered on a query, after the query is executed. This hooks into the `graphql_after_execute` action.

### Callback Arguments

#### Field Callbacks

The field-level callbacks (`before_resolve_field`, `resolve_field`, `after_resolve_field`) receive the same set of arguments, which are passed through from their corresponding hooks (`graphql_before_resolve_field`, `graphql_resolve_field`, `graphql_after_resolve_field`).

- `$result`: The resolved value of the field. For `before_resolve_field`, this will be `null`.
- `$source`: The source object.
- `$field_args`: The arguments passed to the field in the query.
- `$context`: The application context (`AppContext`).
- `$info`: The resolve info (`ResolveInfo`).
- `$type_name`: The name of the type the field belongs to.
- `$field_key`: The key for the field.
- `$field`: The field definition object (`FieldDefinition`).
- `$field_resolver`: The original field resolver.
- **`$directive_args`**: An associative array of the arguments passed to this specific directive instance.
- **`$directive`**: The `\GraphQL\Type\Definition\Directive` object for the directive being resolved.

#### Query Callbacks

##### `before_execute_query`

- **`$request`**: The `\WPGraphQL\Request` object.
- **`$operation`**: The `\GraphQL\Language\AST\OperationDefinitionNode` for the current query operation.
- **`$directive_args`**: An associative array of the arguments passed to this specific directive instance.
- **`$directive`**: The `\GraphQL\Type\Definition\Directive` object for the directive being resolved.

##### `after_execute_query`

- **`$response`**: The response of the GraphQL query.
- **`$request`**: The `\WPGraphQL\Request` object.
- **`$operation`**: The `\GraphQL\Language\AST\OperationDefinitionNode` for the current query operation.
- **`$directive_args`**: An associative array of the arguments passed to this specific directive instance.
- **`$directive`**: The `\GraphQL\Type\Definition\Directive` object for the directive being resolved.

### Example: The `@translate` Directive

A powerful use case for these hooks is to temporarily modify the application context. For example, a `@translate` directive could change the locale for a specific field or for an entire query.

```php
<?php
use GraphQL\Language\DirectiveLocation;

register_graphql_directive( [
    'name' => 'translate',
    'description' => 'Translate a field or query into a specific locale.',
    'locations' => [
        DirectiveLocation::FIELD,
        DirectiveLocation::QUERY,
    ],
    'args' => [
        'locale' => [
            'type' => 'String',
            'description' => 'The locale to translate into (e.g., "es_ES").',
        ]
    ],
    'before_resolve_field' => function( $source, $field_args, $context, $info, $type_name, $field_key, $field, $field_resolver, $directive_args, $directive ) {
        if ( ! empty( $directive_args['locale'] ) ) {
            $context->set( 'original_locale', get_locale() );
            switch_to_locale( $directive_args['locale'] );
        }
    },
    'after_resolve_field' => function( $result, $source, $field_args, $context, $info, $type_name, $field_key, $field, $field_resolver, $directive_args, $directive ) {
        if ( ! empty( $context->get( 'original_locale' ) ) ) {
            restore_previous_locale();
            $context->set( 'original_locale', null );
        }
    },
    'before_execute_query' => function( $request, $operation, $directive_args, $directive ) {
        if ( ! empty( $directive_args['locale'] ) ) {
            $request->app_context->set( 'original_locale', get_locale() );
            switch_to_locale( $directive_args['locale'] );
        }
    },
    'after_execute_query' => function( $response, $request, $operation, $directive_args, $directive ) {
        if ( ! empty( $request->app_context->get( 'original_locale' ) ) ) {
            restore_previous_locale();
            $request->app_context->set( 'original_locale', null );
        }
    },
] );
```

### Restricting a Directive to Specific Fields

You can control which fields a directive can be applied to by adding logic to the `resolve_field` callback. The callback receives the `$type_name` and `$field_key`, which you can use to enforce your desired restrictions.

If the directive is used on a field where it's not allowed, you should throw a `\GraphQL\Error\UserError` to provide a clear error message to the client.

Here is an example of an `@uppercase` directive that is restricted to only `title` fields:

```php
<?php
use GraphQL\Language\DirectiveLocation;
use GraphQL\Error\UserError;

register_graphql_directive( 'uppercase', [
    'name' => 'uppercase',
    'description' => 'Uppercase the value of a field. Can only be used on `title` fields.',
    'locations' => [
        DirectiveLocation::FIELD,
    ],
    'resolve_field' => function ( $result, $source, $field_args, $context, $info, $type_name, $field_key, $field, $field_resolver, $directive_args, $directive ) {
        // Only allow this directive on 'title' fields.
        if ( 'title' !== $field_key ) {
            throw new UserError( __( 'The @uppercase directive can only be used on "title" fields.', 'wp-graphql' ) );
        }

        // The field resolver has already run, and the result is passed in as the first argument.
        // We can now transform it.
        if ( ! is_string( $result ) ) {
            // If the resolved value is not a string, return it without modification.
            return $result;
        }
        return strtoupper( $result );
    },
] );
```

### Restricting by Field Value Type

A common use case for directives is to format a value based on its type. For example, a `@formatDate` directive should only work on fields that return a date, and a `@formatColor` directive should only work on fields that return a color value.

You can enforce this by inspecting the resolved `$result` inside your `resolve_field` callback and throwing a `UserError` if it's not of the expected type.

Here is an example of a `@formatDate` directive that validates the field's resolved value before formatting it:

```php
<?php
use GraphQL\Language\DirectiveLocation;
use GraphQL\Error\UserError;

// Example of a directive that should only apply to date-like values
register_graphql_directive( 'formatDate', [
    'name' => 'formatDate',
    'description' => 'Formats a date value. Only works on fields that resolve to a valid date string or timestamp.',
    'locations' => [ DirectiveLocation::FIELD ],
    'args' => [
        'format' => [
            'type' => 'String',
            'description' => 'A valid PHP date format string.',
            'defaultValue' => 'F j, Y',
        ]
    ],
    'resolve_field' => function ( $result, $source, $field_args, $context, $info, $type_name, $field_key, $field, $field_resolver, $directive_args, $directive ) {
        // Attempt to create a timestamp from the resolved value.
        // This could be an integer timestamp, or a string like '2023-10-27 10:00:00'.
        $timestamp = is_numeric( $result ) ? $result : strtotime( $result );

        // If the value couldn't be converted to a valid timestamp, it's not a date.
        if ( false === $timestamp ) {
            // Throw a user-friendly error.
            throw new UserError(
                sprintf(
                    'The @formatDate directive cannot be used on the "%s" field. The field does not resolve to a recognizable date format.',
                    $info->fieldName
                )
            );
        }

        // The value is a date, so we can safely format it.
        // Get the format from the directive's arguments, with a default fallback.
        $format = $directive_args['format'] ?? 'F j, Y';
        return wp_date( $format, $timestamp );
    },
] );
```
