<?php

class FiltersTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 *
	 */
	public $filter_values = [];

	public function setUp(): void {
		$this->filter_values = [];
		parent::setUp();
		$this->clearSchema();
	}

	public function tearDown(): void {
		$this->filter_values = [];
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Normalize a GraphQL query by parsing and printing it to remove formatting differences
	 *
	 * @param string $query The GraphQL query string
	 * @return string The normalized query string
	 */
	private function normalizeGraphQLQuery( string $query ): string {
		try {
			$ast = \GraphQL\Language\Parser::parse( $query );
			return \GraphQL\Language\Printer::doPrint( $ast );
		} catch ( \Exception $e ) {
			// If parsing fails, return original query
			return $query;
		}
	}

	public function testFilterGraphqlRequestResults() {

		add_filter(
			'graphql_request_results',
			function ( $response, $schema, $operation, $query, $variables, $request ) {
				$this->filter_values = [
					'query'     => $query,
					'variables' => $variables,
				];
			},
			10,
			6
		);

		$request = [
			'query'     => 'query GetPosts($first:Int){posts(first:$first){nodes{id,title}}}',
			'variables' => [ 'first' => 1 ],
		];

		$actual = graphql( $request );

		codecept_debug( $this->filter_values, $request );

		// The filter receives the formatted query from AST transformation
		$expected = [
			'query'     => "query GetPosts(\$first: Int) {\n  posts(first: \$first) {\n    nodes {\n      id\n      title\n    }\n  }\n}\n",
			'variables' => [ 'first' => 1 ],
		];

		// Normalize both queries for comparison to ignore formatting differences
		$expected_normalized = $expected;
		$expected_normalized['query'] = $this->normalizeGraphQLQuery( $expected['query'] );

		$actual_normalized = $this->filter_values;
		$actual_normalized['query'] = $this->normalizeGraphQLQuery( $this->filter_values['query'] );

		$this->assertSame( $expected_normalized, $actual_normalized );
	}

	public function testFilterGraphqlRequestResultsForBatchQuery() {

		add_filter(
			'graphql_request_results',
			function ( $response, $schema, $operation, $query, $variables, $request ) {

				$this->filter_values[] = [
					'query'     => $query,
					'variables' => $variables,
				];

				return $response;
			},
			10,
			6
		);

		$request = [
			[
				'query'     => 'query GetPosts($first:Int){posts(first:$first){nodes{id,title}}}',
				'variables' => [ 'first' => 1 ],
			],
			[
				'query'     => 'query GetPosts($first:Int){posts(first:$first){nodes{id}}}',
				'variables' => [ 'first' => 2 ],
			],
		];

		$actual = graphql( $request );

		codecept_debug( $this->filter_values );
		codecept_debug( $request );

		// The filter receives the formatted queries from AST transformation
		$expected = [
			[
				'query'     => "query GetPosts(\$first: Int) {\n  posts(first: \$first) {\n    nodes {\n      id\n      title\n    }\n  }\n}\n",
				'variables' => [ 'first' => 1 ],
			],
			[
				'query'     => "query GetPosts(\$first: Int) {\n  posts(first: \$first) {\n    nodes {\n      id\n    }\n  }\n}\n",
				'variables' => [ 'first' => 2 ],
			],
		];

		// Normalize both query arrays for comparison to ignore formatting differences
		$expected_normalized = $expected;
		$actual_normalized = $this->filter_values;

		for ( $i = 0; $i < count( $expected ); $i++ ) {
			$expected_normalized[$i]['query'] = $this->normalizeGraphQLQuery( $expected[$i]['query'] );
			$actual_normalized[$i]['query'] = $this->normalizeGraphQLQuery( $this->filter_values[$i]['query'] );
		}

		$this->assertSame( $expected_normalized, $actual_normalized );
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/2048
	 */
	public function testFilterConnectionQueryArgsForUserRoleQueriesDoesntReturnError() {

		$admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);

		$this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $admin,
				'post_title'  => 'Test Filters',
			]
		);

		set_current_user( $admin );

		$query = '
		{
			users {
				nodes {
					roles {
						nodes {
							displayName
							id
						}
					}
				}
			}
		}
		';

		// Add a filter to the connection query args
		// This should not throw an error because it's returning the $query_args untouched
		add_filter( 'graphql_connection_query_args', 'my_custom_filter', 10, 2 );
		function my_custom_filter( array $query_args, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver ) {
			return $query_args;
		}

		$actual = graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug( $actual );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'users.nodes', self::NOT_NULL ),
			]
		);
	}

	public function testFilterWPConnectionTypeConfigDoesntReturnError() {
		// Add a filter to the connection type config
		// This should not throw an error because it's returning the $config untouched
		add_filter(
			'graphql_wp_connection_type_config',
			function ( $config, $wp_connection_type ) {
				// Ensure the connection instance is passed correctly.
				$this->assertInstanceOf( '\WPGraphQL\Type\WPConnectionType', $wp_connection_type );

				return $config;
			},
			10,
			2
		);

		$query = '
		{
			posts {
				nodes {
					id
					title
				}
			}
		}
		';

		$actual = graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug( $actual );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'posts.nodes', self::NOT_NULL ),
			]
		);
	}

		/**
	 * Test that the graphql_root_value filter has access to request params
	 * and can modify the root value based on query context.
	 */
	public function testFilterGraphqlRootValueHasAccessToRequestParams() {
		$filter_called = false;
		$captured_params = null;
		$captured_request = null;

		// Add a filter to capture and modify the root value based on request params
		add_filter(
			'graphql_root_value',
			static function ( $root_value, $request ) use ( &$filter_called, &$captured_params, &$captured_request ) {
				$filter_called = true;
				$captured_request = $request;
				$captured_params = $request->get_params();

				codecept_debug( 'Filter called! Root value:', $root_value );
				codecept_debug( 'Request object type:', get_class( $request ) );
				codecept_debug( 'Params from get_params():', $captured_params );

				return $root_value;
			},
			10,
			2
		);

		$query = '{ posts { nodes { id } } }';

		$request = [
			'query' => $query,
		];

		$actual = graphql( $request );

		codecept_debug( 'Filter was called:', $filter_called ? 'YES' : 'NO' );
		codecept_debug( 'GraphQL Response:', $actual );

		// If there are errors, show them
		if ( isset( $actual['errors'] ) ) {
			codecept_debug( 'GraphQL Errors:', $actual['errors'] );
		}

		// The main assertion: the filter should have been called
		$this->assertTrue( $filter_called, 'The graphql_root_value filter should be called' );

		if ( $filter_called ) {
			$this->assertNotNull( $captured_request, 'Request should be passed to graphql_root_value filter' );
			$this->assertNotNull( $captured_params, 'Request params should be available in graphql_root_value filter' );

			if ( $captured_params ) {
				$this->assertInstanceOf( '\GraphQL\Server\OperationParams', $captured_params, 'Params should be OperationParams instance' );
				// The query is formatted by AST transformation - normalize for comparison
				$expected_formatted_query = "{\n  posts {\n    nodes {\n      id\n    }\n  }\n}\n";
				$expected_normalized = $this->normalizeGraphQLQuery( $expected_formatted_query );
				$actual_normalized = $this->normalizeGraphQLQuery( $captured_params->query );
				$this->assertEquals( $expected_normalized, $actual_normalized, 'Query should match formatted version' );
			}
		}
	}

		/**
	 * Test that the graphql_root_value filter works correctly with batch requests
	 */
	public function testFilterGraphqlRootValueHasAccessToRequestParamsForBatchQuery() {
		$filter_call_count = 0;
		$captured_requests = [];
		$captured_params = [];

		// Add a filter to capture params for each request in the batch
		add_filter(
			'graphql_root_value',
			static function ( $root_value, $request ) use ( &$filter_call_count, &$captured_requests, &$captured_params ) {
				$filter_call_count++;
				$captured_requests[] = $request;
				$captured_params[] = $request->get_params();

				codecept_debug( "Filter called #{$filter_call_count}" );
				codecept_debug( 'Request object type:', get_class( $request ) );
				codecept_debug( 'Params from get_params():', $request->get_params() );

				return $root_value;
			},
			10,
			2
		);

		$request = [
			[
				'query' => '{ posts { nodes { id } } }',
			],
			[
				'query' => '{ users { nodes { id } } }',
			],
		];

		$actual = graphql( $request );

		codecept_debug( 'Filter call count:', $filter_call_count );
		codecept_debug( 'Captured Requests Count:', count( $captured_requests ) );
		codecept_debug( 'Captured Params Count:', count( $captured_params ) );
		codecept_debug( 'Batch GraphQL Response:', $actual );

		// Assert that the filter was called for each request in the batch
		$this->assertEquals( 2, $filter_call_count, 'Filter should be called twice for batch query' );
		$this->assertCount( 2, $captured_requests, 'Should capture 2 requests for batch query' );
		$this->assertCount( 2, $captured_params, 'Should capture 2 sets of params for batch query' );

		// Assert that both params are OperationParams instances
		if ( count( $captured_params ) >= 2 ) {
			$this->assertInstanceOf( '\GraphQL\Server\OperationParams', $captured_params[0], 'First params should be OperationParams instance' );
			$this->assertInstanceOf( '\GraphQL\Server\OperationParams', $captured_params[1], 'Second params should be OperationParams instance' );

			// Assert that params contain the expected formatted query information - normalize for comparison
			$expected_posts_query = "{\n  posts {\n    nodes {\n      id\n    }\n  }\n}\n";
			$expected_users_query = "{\n  users {\n    nodes {\n      id\n    }\n  }\n}\n";

			$expected_posts_normalized = $this->normalizeGraphQLQuery( $expected_posts_query );
			$actual_posts_normalized = $this->normalizeGraphQLQuery( $captured_params[0]->query );
			$this->assertEquals( $expected_posts_normalized, $actual_posts_normalized, 'First query should match formatted version' );

			$expected_users_normalized = $this->normalizeGraphQLQuery( $expected_users_query );
			$actual_users_normalized = $this->normalizeGraphQLQuery( $captured_params[1]->query );
			$this->assertEquals( $expected_users_normalized, $actual_users_normalized, 'Second query should match formatted version' );
		}
	}
}