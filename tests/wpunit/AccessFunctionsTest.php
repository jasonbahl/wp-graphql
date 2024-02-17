<?php

class AccessFunctionsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();

		$this->admin                              = self::factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$settings                                 = get_option( 'graphql_general_settings' );
		$settings['public_introspection_enabled'] = 'on';
		update_option( 'graphql_general_settings', $settings );
	}

	public function tearDown(): void {
		// your tear down methods here

		// then
		parent::tearDown();
		$this->clearSchema();
	}

	public function testGraphQLPhpVersion() {

		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/vendor/webonyx/graphql-php/CHANGELOG.md' );
		codecept_debug( $contents );
	}

	/**
	 * Tests whether custom scalars can be registered and used in the Schema
	 *
	 * @throws \Exception
	 */
	public function testCustomScalarCanBeUsedInSchema() {

		$test_value = 'test';

		register_graphql_scalar(
			'TestScalar',
			[
				'description'  => __( 'Test Scalar', 'wp-graphql' ),
				'serialize'    => static function ( $value ) {
					return $value;
				},
				'parseValue'   => static function ( $value ) {
					return $value;
				},
				'parseLiteral' => static function ( $valueNode, array $variables = null ) {
					return isset( $valueNode->value ) ? $valueNode->value : null;
				},
			]
		);

		register_graphql_field(
			'RootQuery',
			'testScalar',
			[
				'type'    => 'TestScalar',
				'resolve' => static function () use ( $test_value ) {
					return $test_value;
				},
			]
		);

		$query    = '{
			__type(name: "TestScalar") {
				kind
			}
		}';
		$response = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( 'SCALAR', $response['data']['__type']['kind'] );

		$query    = '{
			__schema {
				queryType {
					fields {
						name
						type {
							name
							kind
						}
					}
				}
			}
		}';
		$response = $this->graphql( compact( 'query' ) );

		$fields = $response['data']['__schema']['queryType']['fields'];

		$test_scalar = array_filter(
			$fields,
			static function ( $field ) {
				return 'TestScalar' === $field['type']['name'] && 'SCALAR' === $field['type']['kind'] ? $field : null;
			}
		);

		$this->assertNotEmpty( $test_scalar );

		$query    = '{ testScalar }';
		$response = $this->graphql( compact( 'query' ) );

		$this->assertEquals( $test_value, $response['data']['testScalar'] );
	}

	public function testFormatName(): void {
		// Test empty.
		$actual   = graphql_format_name( '' );
		$expected = '';
		$this->assertEquals( $expected, $actual );

		// Test default regex.
		$actual   = graphql_format_name( '^&This Is-some name123%$!' );
		$expected = '_This_Is_some_name123___';
		$this->assertEquals( $expected, $actual );

		// Test custom replacement chars.
		$actual   = graphql_format_name( '^&This Is-some name123%$!', '' );
		$expected = 'ThisIssomename123';
		$this->assertEquals( $expected, $actual );

		// Test custom regex. only letters.
		$actual   = graphql_format_name( '^&This Is-some name123%$!', '', '/[^a-zA-Z]/' );
		$expected = 'ThisIssomename';
		$this->assertEquals( $expected, $actual );

		// Test with filter.
		add_filter(
			'graphql_pre_format_name',
			static function ( $name, string $original_name, string $replacement_chars, string $regex ) {
				// Transliteration example.
				$mapped_chars = [
					'ä' => 'ae',
					'ö' => 'oe',
					'ü' => 'ue',
					'ß' => 'ss',
				];

				$transliterated_name = str_replace( array_keys( $mapped_chars ), array_values( $mapped_chars ), $original_name );

				return preg_replace( $regex, $replacement_chars, $transliterated_name );
			},
			10,
			4
		);

		// Use a name with those special chars.
		$actual   = graphql_format_name( '_#ä_ö ü-ß', '' );
		$expected = '_ae_oeuess';
		$this->assertEquals( $expected, $actual );

		// Cleanup
		remove_all_filters( 'graphql_pre_format_name' );
	}

	public function testFormatNameWithBadFilter() {
		add_filter(
			'graphql_pre_format_name',
			static function ( $name, string $original_name, string $replacement_chars, string $regex ) {
				return 123;
			},
			10,
			4
		);

		$original_name = '^*This Is-some name123%$!';

		// Test filter falls back to default.
		$actual   = graphql_format_name( $original_name, '' );
		$expected = 'ThisIssomename123';

		$this->assertEquals( $expected, $actual );

		// Test a schema query to make sure a warning was logged.
		$query = '{
			__schema {
				queryType {
					fields {
						name
						type {
							name
							kind
						}
					}
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'The `graphql_pre_format_name` filter must return a string or null.', $actual['extensions']['debug']['0']['message'] );
		$this->assertEquals( $original_name, $actual['extensions']['debug']['0']['original_name'] );

		// Cleanup filter
		remove_all_filters( 'graphql_pre_format_name' );
	}

	// tests
	public function testFormatFieldName() {
		$actual   = graphql_format_field_name( 'This is some field name' );
		$expected = 'thisIsSomeFieldName';
		self::assertEquals( $expected, $actual );
	}

	public function testRegisterFieldStartingWithNumberOutputsDebugMessage() {
		register_graphql_field(
			'RootQuery',
			'123TestField',
			[
				'type' => 'String',
			]
		);

		$query    = '{
			posts(first:1) {
				nodes {
					id
				}
			}
		}';
		$response = $this->graphql( compact( 'query' ) );

		codecept_debug( $response );

		$this->assertArrayHasKey( 'debug', $response['extensions'] );

		$has_debug_message = false;

		foreach ( $response['extensions']['debug'] as $debug_message ) {
			if (
				'123TestField' === $debug_message['field_name'] &&
				'RootQuery' === $debug_message['type_name'] &&
				'INVALID_FIELD_NAME' === $debug_message['type']
			) {
				$has_debug_message = true;
				break;
			}
		}

		$this->assertTrue( $has_debug_message );
	}

	public function testRegisterInputField() {

		/**
		 * Register Input Field CPT
		 */
		register_post_type(
			'access_functions_cpt',
			[
				'label'               => __( 'Register Input Field CPT', 'wp-graphql' ),
				'labels'              => [
					'name'          => __( 'Register Input Field CPT', 'wp-graphql' ),
					'singular_name' => __( 'Register Input Field CPT', 'wp-graphql' ),
				],
				'description'         => __( 'test-post-type', 'wp-graphql' ),
				'supports'            => [ 'title' ],
				'show_in_graphql'     => true,
				'graphql_single_name' => 'TestCpt',
				'graphql_plural_name' => 'TestCpts',
			]
		);

		/**
		 * Register a GraphQL Input Field to the connection where args
		 */
		register_graphql_field(
			'RootQueryToTestCptConnectionWhereArgs',
			'testTest',
			[
				'type'        => 'String',
				'description' => 'just testing here',
			]
		);

		/**
		 * Introspection query to query the names of fields on the Type
		 */
		$query = '{
			__type( name: "RootQueryToTestCptConnectionWhereArgs" ) {
				inputFields {
					name
				}
			}
		}';

		$response = $this->graphql( compact( 'query' ) );

		/**
		 * Get an array of names from the inputFields
		 */
		$names = array_column( $response['data']['__type']['inputFields'], 'name' );

		/**
		 * Assert that `testTest` exists in the $names (the field was properly registered)
		 */
		$this->assertTrue( in_array( 'testTest', $names, true ) );

		/**
		 * Cleanup
		 */
		deregister_graphql_field( 'RootQueryToTestCptConnectionWhereArgs', 'testTest' );
		unregister_post_type( 'access_functions_cpt' );
		WPGraphQL::clear_schema();
	}

	/**
	 * Test to make sure "testInputField" doesn't exist in the Schema already
	 *
	 * @throws \Exception
	 */
	public function testFilteredInputFieldDoesntExistByDefault() {
		/**
		 * Query the "RootQueryToPostConnectionWhereArgs" Type
		 */
		$query = '
		{
			__type(name: "RootQueryToPostConnectionWhereArgs") {
				name
				kind
				inputFields {
					name
				}
			}
		}
		';

		$response = $this->graphql( compact( 'query' ) );

		/**
		 * Map the names of the inputFields to be an array so we can properly
		 * assert that the input field is there
		 */
		$field_names = array_map(
			static function ( $field ) {
				return $field['name'];
			},
			$response['data']['__type']['inputFields']
		);

		codecept_debug( $field_names );

		/**
		 * Assert that there is no `testInputField` on the Type already
		 */
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertNotContains( 'testInputField', $field_names );
	}

	/**
	 * Test to make sure filtering in "testInputField" properly adds the input to the Schema
	 *
	 * @throws \Exception
	 */
	public function testFilterInputFields() {

		/**
		 * Query the "RootQueryToPostConnectionWhereArgs" Type
		 */
		$query = '
		{
			__type(name: "RootQueryToPostConnectionWhereArgs") {
				name
				kind
				inputFields {
					name
				}
			}
		}
		';

		/**
		 * Filter in the "testInputField"
		 */
		add_filter(
			'graphql_input_fields',
			static function ( $fields, $type_name, $config, $type_registry ) {
				if ( isset( $config['queryClass'] ) && 'WP_Query' === $config['queryClass'] ) {
					$fields['testInputField'] = [
						'type' => 'String',
					];
				}
				return $fields;
			},
			10,
			4
		);

		$response = $this->graphql( compact( 'query' ) );

		/**
		 * Map the names of the inputFields to be an array so we can properly
		 * assert that the input field is there
		 */
		$field_names = array_map(
			static function ( $field ) {
				return $field['name'];
			},
			$response['data']['__type']['inputFields']
		);

		codecept_debug( $field_names );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertContains( 'testInputField', $field_names );
	}

	public function testRegisterFields() {
		/**
		 * Register Input Field CPT
		 */
		register_post_type(
			'register_fields_cpt',
			[
				'label'               => __( 'Register Fields CPT', 'wp-graphql' ),
				'labels'              => [
					'name'          => __( 'Register Fields CPT', 'wp-graphql' ),
					'singular_name' => __( 'Register Fields CPT', 'wp-graphql' ),
				],
				'description'         => __( 'test-post-type', 'wp-graphql' ),
				'supports'            => [ 'title' ],
				'show_in_graphql'     => true,
				'graphql_single_name' => 'RegisterFieldsCpt',
				'graphql_plural_name' => 'RegisterFieldsCpts',
			]
		);

		/**
		 * Register a GraphQL Input Field to the connection where args
		 */
		register_graphql_fields(
			'RootQueryToRegisterFieldsCptConnectionWhereArgs',
			[
				'firstTestField'  => [
					'type'        => 'String',
					'description' => 'just testing here',
				],
				'secondTestField' => [
					'type'        => 'String',
					'description' => 'just testing here',
				],
			]
		);

		/**
		 * Introspection query to query the names of fields on the Type
		 */
		$query = '{
			__type( name: "RootQueryToRegisterFieldsCptConnectionWhereArgs" ) {
				inputFields {
					name
				}
			}
		}';

		$response = $this->graphql( compact( 'query' ) );

		/**
		 * Get an array of names from the inputFields
		 */
		$names = array_column( $response['data']['__type']['inputFields'], 'name' );

		/**
		 * Assert that `testTest` exists in the $names (the field was properly registered)
		 */
		$this->assertTrue( in_array( 'firstTestField', $names, true ) );
		$this->assertTrue( in_array( 'secondTestField', $names, true ) );

		/**
		 * Cleanup
		 */
		deregister_graphql_field( 'RootQueryToRegisterFieldsCptConnectionWhereArgs', 'firstTestField' );
		deregister_graphql_field( 'RootQueryToRegisterFieldsCptConnectionWhereArgs', 'secondTestField' );
		unregister_post_type( 'register_fields_cpt' );
		WPGraphQL::clear_schema();
	}

	public function testRegisterEdgeField() {
		// Register cpt for connection.
		register_post_type(
			'register_edge_cpt',
			[
				'label'               => __( 'Register Edge Field CPT', 'wp-graphql' ),
				'labels'              => [
					'name'          => __( 'Register Edge Field CPT', 'wp-graphql' ),
					'singular_name' => __( 'Register Edge Field CPT', 'wp-graphql' ),
				],
				'description'         => __( 'test-post-type', 'wp-graphql' ),
				'supports'            => [ 'title' ],
				'show_in_graphql'     => true,
				'graphql_single_name' => 'EdgeFieldCpt',
				'graphql_plural_name' => 'EdgeFieldCpts',
				'public'              => true,
			]
		);

		// Register the edge field
		register_graphql_edge_field(
			'RootQuery',
			'EdgeFieldCpt',
			'testEdgeField',
			[
				'type'        => 'String',
				'description' => 'just testing here',
				'resolve'     => static function () {
					return 'test';
				},
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'register_edge_cpt',
				'post_title'  => 'Test Register Edge CPT',
				'post_status' => 'publish',
			]
		);

		/**
		 * Introspection query to query the names of fields on the Type
		 */
		$query = '{
			edgeFieldCpts {
				edges {
					testEdgeField
					node {
						id
					}
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'test', $actual['data']['edgeFieldCpts']['edges'][0]['testEdgeField'] );

		/**
		 * Cleanup
		 */
		deregister_graphql_field( 'RootQueryToEdgeFieldCptConnectionEdge', 'testEdgeField' );
		unregister_post_type( 'register_edge_cpt' );
		WPGraphQL::clear_schema();
	}

	public function testRegisterEdgeFields() {
		// Register cpt for connection.
		register_post_type(
			'edge_fields_cpt',
			[
				'label'               => __( 'Register Edge Fields CPT', 'wp-graphql' ),
				'labels'              => [
					'name'          => __( 'Register Edge Fields CPT', 'wp-graphql' ),
					'singular_name' => __( 'Register Edge Fields CPT', 'wp-graphql' ),
				],
				'description'         => __( 'test-post-type', 'wp-graphql' ),
				'supports'            => [ 'title' ],
				'show_in_graphql'     => true,
				'graphql_single_name' => 'EdgeFieldsCpt',
				'graphql_plural_name' => 'EdgeFieldsCpts',
				'public'              => true,
			]
		);

		// Register the edge field
		register_graphql_edge_fields(
			'RootQuery',
			'EdgeFieldsCpt',
			[
				'testEdgeField1' => [
					'type'        => 'String',
					'description' => 'just testing here',
					'resolve'     => static function () {
						return 'test';
					},
				],
				'testEdgeField2' => [
					'type'        => 'String',
					'description' => 'just testing here',
					'resolve'     => static function () {
						return 'test2';
					},
				],
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'edge_fields_cpt',
				'post_title'  => 'Test Register Edge CPT',
				'post_status' => 'publish',
			]
		);

		/**
		 * Introspection query to query the names of fields on the Type
		 */
		$query = '{
			edgeFieldsCpts {
				edges {
					testEdgeField1
					testEdgeField2
					node {
						id
					}
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'test', $actual['data']['edgeFieldsCpts']['edges'][0]['testEdgeField1'] );
		$this->assertEquals( 'test2', $actual['data']['edgeFieldsCpts']['edges'][0]['testEdgeField2'] );

		/**
		 * Cleanup
		 */
		deregister_graphql_field( 'RootQueryToEdgeFieldsCptConnectionEdge', 'testEdgeField1' );
		deregister_graphql_field( 'RootQueryToEdgeFieldsCptConnectionEdge', 'testEdgeField2' );
		unregister_post_type( 'edge_fields_cpt' );
		WPGraphQL::clear_schema();
	}

	public function testRegisterConnectionInput() {
		// Register cpt for connection.
		register_post_type(
			'connect_input_cpt',
			[
				'label'               => __( 'Register Connection Inputs CPT', 'wp-graphql' ),
				'labels'              => [
					'name'          => __( 'Register Connection Inputs CPT', 'wp-graphql' ),
					'singular_name' => __( 'Register Connection Inputs CPT', 'wp-graphql' ),
				],
				'description'         => __( 'test-post-type', 'wp-graphql' ),
				'supports'            => [ 'title' ],
				'show_in_graphql'     => true,
				'graphql_single_name' => 'ConnectionInputCpt',
				'graphql_plural_name' => 'ConnectionInputCpts',
				'public'              => true,
			]
		);

		register_graphql_connection_where_arg(
			'RootQuery',
			'ConnectionInputCpt',
			'testInputField',
			[
				'type'        => 'String',
				'description' => 'just testing here',
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'connect_input_cpt',
				'post_title'  => 'Test Register Connection Inputs CPT',
				'post_status' => 'publish',
			]
		);

		/**
		 * Introspection query to query the names of fields on the Type
		 */
		$query = '{
			connectionInputCpts( where: { testInputField: "test" } ) {
				edges {
					node {
						databaseId
					}
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $post_id, $actual['data']['connectionInputCpts']['edges'][0]['node']['databaseId'] );

		/**
		 * Cleanup
		 */
		deregister_graphql_field( 'RootQueryToConnectionInputCptConnectionWhereArgs', 'testInputField' );
		unregister_post_type( 'connect_input_cpt' );
		WPGraphQL::clear_schema();
	}

	public function testRegisterConnectionInputs() {
		// Register cpt for connection.
		register_post_type(
			'connect_inputs_cpt',
			[
				'label'               => __( 'Register Connection Inputs CPT', 'wp-graphql' ),
				'labels'              => [
					'name'          => __( 'Register Connection Inputs CPT', 'wp-graphql' ),
					'singular_name' => __( 'Register Connection Inputs CPT', 'wp-graphql' ),
				],
				'description'         => __( 'test-post-type', 'wp-graphql' ),
				'supports'            => [ 'title' ],
				'show_in_graphql'     => true,
				'graphql_single_name' => 'ConnectionInputsCpt',
				'graphql_plural_name' => 'ConnectionInputsCpts',
				'public'              => true,
			]
		);

		register_graphql_connection_where_args(
			'RootQuery',
			'ConnectionInputsCpt',
			[
				'testInputField1' => [
					'type'        => 'String',
					'description' => 'just testing here',
				],
				'testInputField2' => [
					'type'        => 'String',
					'description' => 'just testing here',
				],
			]
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'connect_inputs_cpt',
				'post_title'  => 'Test Register Connection Inputs CPT',
				'post_status' => 'publish',
			]
		);

		/**
		 * Introspection query to query the names of fields on the Type
		 */
		$query = '{
			connectionInputsCpts( where: { testInputField1: "test", testInputField2: "test2" } ) {
				edges {
					node {
						databaseId
					}
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $post_id, $actual['data']['connectionInputsCpts']['edges'][0]['node']['databaseId'] );

		/**
		 * Cleanup
		 */
		deregister_graphql_field( 'RootQueryToConnectionInputsCpt', 'testInputField1' );
		deregister_graphql_field( 'RootQueryToConnectionInputsCpt', 'testInputField2' );
		unregister_post_type( 'connect_inputs_cpt' );
		WPGraphQL::clear_schema();
	}

	public function testRenameGraphQLFieldName() {

		rename_graphql_field( 'RootQuery', 'user', 'wpUser' );

		$query    = '{ __type(name: "RootQuery") { fields { name } } }';
		$response = $this->graphql( compact( 'query' ) );

		$this->assertQuerySuccessful(
			$response,
			[
				$this->not()->expectedNode( '__type.fields', [ 'name' => 'user' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'wpUser' ] ),
			]
		);
	}

	public function testRenameGraphQLConnectionFieldName() {
		rename_graphql_field( 'RootQuery', 'users', 'wpUsers' );

		$query    = '{ __type(name: "RootQuery") { fields { name } } }';
		$response = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $response );

		$this->assertQuerySuccessful(
			$response,
			[
				$this->not()->expectedNode( '__type.fields', [ 'name' => 'users' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'wpUsers' ] ),
			]
		);
	}

	public function testRenameGraphQLType() {

		register_graphql_union_type(
			'PostObjectUnion',
			[
				'typeNames'   => [ 'Post', 'Page' ],
				'description' => __( 'Union between the post, page and media item types', 'wp-graphql' ),
			]
		);

		rename_graphql_type( 'User', 'WPUser' );
		rename_graphql_type( 'AvatarRatingEnum', 'ImageRatingEnum' );
		rename_graphql_type( 'PostObjectUnion', 'CPTUnion' );
		rename_graphql_type( 'ContentNode', 'PostNode' );

		$query    = '{ __schema { types { name } } }';
		$response = $this->graphql( compact( 'query' ) );

		$this->assertQuerySuccessful(
			$response,
			[
				$this->not()->expectedNode( '__schema.types', [ 'name' => 'User' ] ),
				$this->not()->expectedNode( '__schema.types', [ 'name' => 'AvatarRatingEnum' ] ),
				$this->not()->expectedNode( '__schema.types', [ 'name' => 'PostObjectUnion' ] ),
				$this->not()->expectedNode( '__schema.types', [ 'name' => 'ContentNode' ] ),
				$this->expectedNode( '__schema.types', [ 'name' => 'WPUser' ] ),
				$this->expectedNode( '__schema.types', [ 'name' => 'ImageRatingEnum' ] ),
				$this->expectedNode( '__schema.types', [ 'name' => 'CPTUnion' ] ),
				$this->expectedNode( '__schema.types', [ 'name' => 'PostNode' ] ),
			]
		);
	}

	/**
	 * @throws \Exception
	 */
	public function testRenamedGraphQLTypeCanBeReferencedInFieldRegistration() {

		// Rename the User type
		rename_graphql_type( 'User', 'RenamedUser' );

		// Register a field referencing the "User" Type (this should still work)
		register_graphql_field(
			'RootQuery',
			'testUserField',
			[
				'type' => 'User',
			]
		);

		// Register a field referencing the "RenamedUser" Type (this should also work)
		register_graphql_field(
			'RootQuery',
			'testWpUserField',
			[
				'type' => 'RenamedUser',
			]
		);

		// Query for the RootQuery type
		$query = '
		{
			__type( name:"RootQuery" ) {
				fields {
					name
					type {
						name
					}
				}
			}
		}
		';

		$response = graphql(
			[
				'query' => $query,
			]
		);

		// Both fields registered using the Original Type name and the Replaced Type Name
		// should be respected
		// should now be fields of the Type "RenamedUser"
		$this->assertQuerySuccessful(
			$response,
			[
				$this->expectedNode(
					'__type.fields',
					[
						'name' => 'testUserField',
						'type' => [ 'name' => 'RenamedUser' ],
					]
				),
				$this->expectedNode(
					'__type.fields',
					[
						'name' => 'testWpUserField',
						'type' => [ 'name' => 'RenamedUser' ],
					]
				),
			]
		);
	}

	public function testGraphqlFunctionWorksInResolvers() {

		register_graphql_field(
			'RootQuery',
			'graphqlInResolver',
			[
				'type'        => 'String',
				'description' => __( 'Returns an MD5 hash of the schema, useful in determining if the schema has changed.', 'wp-graphql' ),
				'resolve'     => static function () {
					$query = '
						{
							posts {
								nodes {
									id
								}
							}
						}
					';

					$graphql = \graphql(
						[
							'query' => $query,
						]
					);

					$json_string = \wp_json_encode( $graphql['data'] );
					return md5( $json_string );
				},
			]
		);

		$query = '
			{
				graphqlInResolver
			}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'graphqlInResolver', self::NOT_NULL ),
			]
		);
	}

	public function testGraphqlFunctionWorksInResolversForBatchQueries() {

		register_graphql_field(
			'RootQuery',
			'graphqlInResolver',
			[
				'type'        => 'String',
				'description' => __( 'Returns an MD5 hash of the schema, useful in determining if the schema has changed.', 'wp-graphql' ),
				'resolve'     => static function () {
					$query = '
						{
							posts {
								nodes {
									id
								}
							}
						}
					';

					$graphql = \graphql(
						[
							'query' => $query,
						]
					);

					$json_string = \wp_json_encode( $graphql['data'] );
					return md5( $json_string );
				},
			]
		);

		$query = '
		{
			graphqlInResolver
		}
		';

		$actual = $this->graphql(
			[
				[
					'query' => $query,
				],
				[
					'query' => $query,
				],
			]
		);

		$this->assertTrue( is_array( $actual ) );

		foreach ( $actual as $response ) {
			$this->assertTrue( is_array( $response ) );
			$this->assertQuerySuccessful(
				$response,
				[
					$this->expectedField( 'graphqlInResolver', self::NOT_NULL ),
				]
			);
		}
	}

	public function testSettingRootValueWhenCallingGraphqlFunction() {

		$value = uniqid( 'test-', true );

		register_graphql_field(
			'RootQuery',
			'someRootField',
			[
				'type'    => 'String',
				'resolve' => static function ( $root ) {
					return isset( $root['someRootField'] ) ? $root['someRootField'] : null;
				},
			]
		);

		$actual = graphql(
			[
				'query'      => '{someRootField}',
				'root_value' => [
					'someRootField' => $value,
				],
			]
		);

		$this->assertSame( $value, $actual['data']['someRootField'] );
	}

	public function testRegisterFieldWithIsPrivateConfigReturnsNullForPublicUser() {

		register_graphql_field(
			'RootQuery',
			'testPrivateField',
			[
				'type'      => 'String',
				'resolve'   => static function () {
					return 'privateValue';
				},
				'isPrivate' => true,
			]
		);

		$query = '
		{
			testPrivateField
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// it's a private field, there should be an error
		$this->assertArrayHasKey( 'errors', $actual );

		// there should be only 1 error, because of the private field
		$this->assertCount( 1, $actual['errors'] );

		// the data for the field should be null
		$this->assertNull( $actual['data']['testPrivateField'] );

		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// since we're authenticated now, there should be no errors
		$this->assertArrayNotHasKey( 'errors', $actual );

		// the data for the field should be returned since the user has proper capabilities
		$this->assertSame( 'privateValue', $actual['data']['testPrivateField'] );
	}

	public function testRegisterFieldWithAuthCallbackIsProperlyRespected() {

		register_graphql_field(
			'RootQuery',
			'testAuthCallbackField',
			[
				'type'    => 'String',
				'resolve' => static function () {
					return 'privateValue';
				},
				'auth'    => [
					'callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
			]
		);

		$query = '
		{
			testAuthCallbackField
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// it's a private field, there should be an error
		$this->assertArrayHasKey( 'errors', $actual );

		// there should be only 1 error, because of the private field
		$this->assertCount( 1, $actual['errors'] );

		// the data for the field should be null
		$this->assertNull( $actual['data']['testAuthCallbackField'] );

		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// since we're authenticated now, there should be no errors
		$this->assertArrayNotHasKey( 'errors', $actual );

		// the data for the field should be returned since the user has proper capabilities
		$this->assertSame( 'privateValue', $actual['data']['testAuthCallbackField'] );
	}

	public function testRegisterMutationWithAuthCallbackPreventsMutationExecution() {

		$expected = 'test_value';

		register_graphql_mutation(
			'authCallbackMutation',
			[
				'inputFields'         => [],
				'outputFields'        => [
					'test' => [
						'type' => 'String',
					],
				],
				'auth'                => [
					'callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
				'mutateAndGetPayload' => static function () use ( $expected ) {
					return [
						'test' => $expected,
					];
				},
			]
		);

		$query = '
		mutation {
			authCallbackMutation(input:{ clientMutationId: "test" }) {
				test
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// it's a private field, there should be an error
		$this->assertArrayHasKey( 'errors', $actual );

		// there should be only 1 error, because of the private field
		$this->assertCount( 1, $actual['errors'] );

		// the data for the field should be null
		$this->assertNull( $actual['data']['authCallbackMutation'] );

		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// since we're authenticated now, there should be no errors
		$this->assertArrayNotHasKey( 'errors', $actual );

		// the data for the field should be returned since the user has proper capabilities
		$this->assertSame( $expected, $actual['data']['authCallbackMutation']['test'] );
	}

	public function testRegisterMutationWithIsPrivatePreventsPublicMutationExecution() {

		$expected = 'test_value';

		register_graphql_mutation(
			'isPrivateMutation',
			[
				'inputFields'         => [],
				'outputFields'        => [
					'test' => [
						'type' => 'String',
					],
				],
				'isPrivate'           => true,
				'mutateAndGetPayload' => static function () use ( $expected ) {
					return [
						'test' => $expected,
					];
				},
			]
		);

		$query = '
		mutation {
			isPrivateMutation(input:{ clientMutationId: "test" }) {
				test
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// it's a private field, there should be an error
		$this->assertArrayHasKey( 'errors', $actual );

		// there should be only 1 error, because of the private field
		$this->assertCount( 1, $actual['errors'] );

		// the data for the field should be null
		$this->assertNull( $actual['data']['isPrivateMutation'] );

		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		// since we're authenticated now, there should be no errors
		$this->assertArrayNotHasKey( 'errors', $actual );

		// the data for the field should be returned since the user has proper capabilities
		$this->assertSame( $expected, $actual['data']['isPrivateMutation']['test'] );
	}

	public function testGraphqlGetEndpointUrl() {
		$actual = graphql_get_endpoint_url();
		$this->assertNotEmpty( $actual );
		$expected = site_url( graphql_get_endpoint() );
		$this->assertSame( $expected, $actual );
	}

	public function testFilterGraphqlGetEndpointUrl() {

		$expected = 'potatoes';

		add_filter(
			'graphql_endpoint',
			static function () use ( $expected ) {
				return $expected;
			}
		);

		$actual = graphql_get_endpoint_url();
		$this->assertNotEmpty( $actual );

		$this->assertSame( site_url( $expected ), $actual );
	}

	public function testDeregisterObjectType() {

		deregister_graphql_type( 'Post' );

		// Ensure the schema is still queryable.

		$query = '
		{
			__schema {
				types {
					name
				}
			}
		}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		// Ensure Post-related types have been removed.
		$types = array_column( $actual['data']['__schema']['types'], 'name' );

		$removed_types = [
			'UserToPostConnectionWhereArgs',
			'UserToPostConnection',
			'UserToPostConnectionEdge',
			'Post',
			'NodeWithExcerpt',
			'NodeWithTrackbacks',
			'PostToCategoryConnectionWhereArgs',
			'PostToCategoryConnection',
			'PostToCategoryConnectionEdge',
			'PostToCommentConnectionWhereArgs',
			'PostToCommentConnection',
			'PostToCommentConnectionEdge',
			'PostToPostFormatConnectionWhereArgs',
			'PostToPostFormatConnection',
			'PostToPostFormatConnectionEdge',
			'PostFormatToPostConnectionWhereArgs',
			'PostFormatToPostConnection',
			'PostFormatToPostConnectionEdge',
			'PostToPreviewConnectionEdge',
			'PostToRevisionConnectionWhereArgs',
			'PostToRevisionConnection',
			'PostToRevisionConnectionEdge',
			'PostToTagConnectionWhereArgs',
			'PostToTagConnection',
			'PostToTagConnectionEdge',
			'TagToPostConnectionWhereArgs',
			'TagToPostConnection',
			'TagToPostConnectionEdge',
			'PostToTermNodeConnectionWhereArgs',
			'PostToTermNodeConnection',
			'PostToTermNodeConnectionEdge',
			'CategoryToPostConnectionWhereArgs',
			'CategoryToPostConnection',
			'CategoryToPostConnectionEdge',
			'PostIdType',
			'RootQueryToPostConnectionWhereArgs',
			'RootQueryToPostConnection',
			'RootQueryToPostConnectionEdge',
		];

		$this->assertEmpty( array_intersect( $types, $removed_types ) );

		// Ensure connection is removed.
		$query = '
			{
				categories {
					nodes {
						posts {
							nodes {
								__typename
							}
						}
					}
				}
			}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayHasKey( 'errors', $actual );

		// Ensure Post is removed from interfaces.
		$query  = '
		{
			__type(name: "ContentNode") {
				possibleTypes {
					name
				}
			}
		}
		';
		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'Post', array_column( $actual['data']['__type']['possibleTypes'], 'name' ) );

		// Ensure Post is removed from unions.
		$query = '
		{
			__type(name: "MenuItemObjectUnion") {
				possibleTypes {
					name
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'Post', array_column( $actual['data']['__type']['possibleTypes'], 'name' ) );

		/**
		 * Since only the Post Type is removed, but the connection is still registered, we should expect the resolvers to still return an unresolved type.
		 */
		$post_id = $this->factory()->post->create(
			[
				'post_title'  => 'Test deregister type',
				'post_status' => 'publish',
			]
		);

		$query = '
		{
			contentNodes {
				nodes {
					id
				}
			}
			contentTypes {
				nodes {
					name
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertStringContainsString( 'GraphQL Interface Type `ContentNode` returned `null', $actual['errors'][0]['debugMessage'] );
		$this->assertNull( $actual['data']['contentNodes'] );
		$this->assertContains( 'post', array_column( $actual['data']['contentTypes']['nodes'], 'name' ) );
	}

	public function testDeregisterEnumType() {
		// Test case-sensitivity.
		deregister_graphql_type( 'ContentTypeenum' );

		// Ensure the schema is still queryable.
		$query = '
		{
			__schema {
				types {
					name
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'ContentTypeEnum', array_column( $actual['data']['__schema']['types'], 'name' ) );

		// Ensure the enum is removed from the schema.
		$this->assertArrayNotHasKey( 'errors', $actual );

		// Ensure the enum is removed from input arguments.
		$query = '
		{
			__type(name: "RootQueryToContentNodeConnectionWhereArgs") {
				inputFields {
					name
				}
			}
		}';

		$actual = graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'contentType', array_column( $actual['data']['__type']['inputFields'], 'name' ) );

		// Ensure query still works
		$post_id = $this->factory()->post->create(
			[
				'post_title'  => 'Test deregister enum type',
				'post_status' => 'publish',
			]
		);

		$query = '
		{
			contentNodes {
				nodes {
					id
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['contentNodes']['nodes'] );
	}

	public function testDeregisterInputType() {
		deregister_graphql_type( 'DateQueryInput' );

		// Ensure the schema is still queryable.
		$query = '
		{
			__schema {
				types {
					name
				}
			}
		}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'DateQueryInput', array_column( $actual['data']['__schema']['types'], 'name' ) );

		// Ensure the input is removed from the schema.
		$query = '
		{
			__type(name: "RootQueryToPostConnectionWhereArgs") {
				inputFields {
					name
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'dateQuery', array_column( $actual['data']['__type']['inputFields'], 'name' ) );

		// Ensure query still works
		$post_id = $this->factory()->post->create(
			[
				'post_title'  => 'Test deregister input type',
				'post_status' => 'publish',
			]
		);

		$query = '
		{
			posts {
				nodes {
					id
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['posts']['nodes'] );
	}

	public function testDeregisterInterfaceType() {
		deregister_graphql_type( 'NodeWithTitle' );

		// Ensure the schema is still queryable.
		$query = '
		{
			__schema {
				types {
					name
				}
			}
		}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'NodeWithTitle', array_column( $actual['data']['__schema']['types'], 'name' ) );

		// Ensure the interface is removed from the schema.
		$query = '
		{
			__type(name: "Post") {
				interfaces {
					name
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'NodeWithTitle', array_column( $actual['data']['__type']['interfaces'], 'name' ) );

		// Ensure query still works
		$post_id = $this->factory()->post->create(
			[
				'post_title'  => 'Test deregister interface type',
				'post_status' => 'publish',
			]
		);

		$query = '
		{
			posts {
				nodes {
					id
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertCount( 1, $actual['data']['posts']['nodes'] );
	}

	public function testDeregisterUnionType() {
		deregister_graphql_type( 'MenuItemObjectUnion' );

		// Ensure the schema is still queryable.
		$query = '
		{
			__schema {
				types {
					name
				}
			}
		}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'MenuItemObjectUnion', array_column( $actual['data']['__schema']['types'], 'name' ) );

		// Ensure the union is removed from the schema.
		$query = '
		{
			__type(name: "MenuItem") {
				fields( includeDeprecated: true ) {
					name
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'connectedObject', array_column( $actual['data']['__type']['fields'], 'name' ) );
	}

	public function testDeregisterMutation() {
		deregister_graphql_mutation( 'createPost' );

		// Ensure the schema is still queryable.
		$query = '
		{
			__schema {
				mutationType {
					name
				}
			}
		}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'createPost', array_column( $actual['data']['__schema']['mutationType'], 'name' ) );

		// Ensure the mutation is removed from the schema.
		$query = '
		{
			__type(name: "RootMutation") {
				fields {
					name
				}
			}
			__schema{
				types{
					name
				}
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotContains( 'createPost', array_column( $actual['data']['__type']['fields'], 'name' ) );
		$this->assertNotContains( 'CreatePostInput', array_column( $actual['data']['__schema']['types'], 'name' ) );
		$this->assertNotContains( 'CreatePostPayload', array_column( $actual['data']['__schema']['types'], 'name' ) );


		// Ensure mutation throws an error.
		$query = '
		mutation CreatePost {
			createPost(input: {clientMutationId: "test", title: "Test deregister mutation"}) {
				clientMutationId
				post {
					id
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertStringContainsString( 'Cannot query field "createPost" on type "RootMutation".', $actual['errors'][0]['message'] );
	}

	public function testRegisterConnectionToNonExistentTypeReturnsDebugMessage() {

		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'FakeType',
				'fromFieldName' => 'fakeTypeConnection',
			]
		);

		$actual = graphql(
			[
				'query' => '
			{
				posts(first:1) {
					nodes {
						id
					}
				}
			}
			',
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function testRegisterConnectionFromNonExistentTypeReturnsDebugMessage() {

		register_graphql_connection(
			[
				'fromType'      => 'FakeType',
				'toType'        => 'Post',
				'fromFieldName' => 'fakeTypeConnection',
			]
		);

		$actual = graphql(
			[
				'query' => '
			{
				posts(first:1) {
					nodes {
						id
					}
				}
			}
			',
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	public function testRegisterMutationWithUppercaseFirstAddsToSchemaWithLcFirst() {

		register_graphql_mutation(
			'CreateSomething',
			[
				'inputFields'         => [ 'test' => [ 'type' => 'String' ] ],
				'outputFields'        => [ 'test' => [ 'type' => 'String' ] ],
				'mutateAndGetPayload' => static function ( $input ) {
					return [ 'test' => $input['test'] ];
				},
			]
		);

		$query = '
		mutation CreateSomething($test: String) {
			createSomething(input:{ test: $test }) {
				test
			}
		}
		';

		$test_input = uniqid( 'wpgraphql', true );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'test' => $test_input,
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( $test_input, $actual['data']['createSomething']['test'] );
	}

	public function testRegisterFieldWithUppercaseNameIsAddedToSchemaWithLcFirst() {

		$expected = uniqid( 'gql', true );

		register_graphql_field(
			'RootQuery',
			'UppercaseField',
			[
				'type'    => 'String',
				'resolve' => static function () use ( $expected ) {
					return $expected;
				},
			]
		);

		$query = '
		query {
			uppercaseField
		}
		';


		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['uppercaseField'] );
	}

	public function testRegisterFieldWithUnderscoreIsAddedAsFormattedField() {

		$expected = uniqid( 'gql', true );

		register_graphql_field(
			'RootQuery',
			'field_with_underscore',
			[
				'type'    => 'String',
				'resolve' => static function () use ( $expected ) {
					return $expected;
				},
			]
		);

		$query = '
		query {
			fieldWithUnderscore
		}
		';


		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['fieldWithUnderscore'] );
	}

	public function testRegisterObjectTypeWithFieldWithUnderscoreIsAddedAsFormattedField() {

		$expected = uniqid( 'gql', true );

		register_graphql_object_type(
			'TestType',
			[
				'fields' => [
					'field_with_underscore' => [
						'type'    => 'String',
						'resolve' => static function () use ( $expected ) {
							return $expected;
						},
					],
				],
			]
		);

		register_graphql_field(
			'RootQuery',
			'testField',
			[
				'type'    => 'TestType',
				'resolve' => static function () {
					return true;
				},
			]
		);



		$query = '
		query {
			testField {
				field_with_underscore
			}
		}
		';


		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['testField']['field_with_underscore'] );
	}

	public function testRegisterTypeWithFieldStartingWithUppercaseLetterIsAllowed() {

		register_graphql_object_type(
			'TypeWithUcFirstField',
			[
				'fields' => [
					'UC_First' => [
						'type' => 'String',
					],
				],
			]
		);

		add_filter(
			'graphql_TypeWithUcFirstField_fields',
			static function ( $fields ) {

				$fields[] = [
					'name' => 'UC_Field_2',
					'type' => 'String',
				];

				return $fields;
			}
		);

		$query = '
		query GetType( $name: String! ){
			__type(name:$name) {
				fields(includeDeprecated:true) {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => 'TypeWithUcFirstField',
				],
			]
		);

		$this->assertNotContains( 'errors', $actual );

		$this->assertNotEmpty( $actual['data']['__type']['fields'] );

		$field_names = wp_list_pluck( $actual['data']['__type']['fields'], 'name' );

		codecept_debug( $field_names );

		$this->assertContains( 'UC_Field_2', $field_names );
		$this->assertContains( 'uC_First', $field_names );
		$this->assertNotContains( 'UC_First', $field_names );
	}

	public function testRegisterGraphqlFieldWithAllowedUndercores() {

		$expected_value = uniqid( 'test value: ', true );

		$config = [
			'type'                  => 'String',
			'resolve'               => static function () use ( $expected_value ) {
				return $expected_value;
			},
			'allowFieldUnderscores' => true,
		];

		register_graphql_field( 'RootQuery', 'underscore_test_field', $config, true );

		$query = '
		{
			underscore_test_field
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'underscore_test_field', $expected_value ),
			]
		);
	}

	public function testDeRegisterGraphqlFieldWithAllowedUndercores() {

		$expected_value = uniqid( 'test value: ', true );

		$config = [
			'type'                  => 'String',
			'resolve'               => static function () use ( $expected_value ) {
				return $expected_value;
			},
			'allowFieldUnderscores' => true,
		];

		deregister_graphql_field( 'RootQuery', 'allowed_underscore_test_field' );
		register_graphql_field( 'RootQuery', 'allowed_underscore_test_field', $config, true );

		$query = '
		{
			underscore_test_field
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

//		self::assertQueryError( $actual, [] );
		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testRegisterInputType(): void {
		register_graphql_input_type(
			'TestInput',
			[
				'fields' => [
					'test' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_mutation(
			'testMutationForInputField',
			[
				'inputFields'         => [
					'testInput' => [
						'type' => 'TestInput',
					],
				],
				'outputFields'        => [
					'testValue' => [
						'type' => 'String',
					],
				],
				'mutateAndGetPayload' => static function ( $input ) {
					return [
						'testValue' => $input['testInput']['test'],
					];
				},
			]
		);

		// Test that the input type is registered.
		$query = '
		{
			__type(name: "TestInput") {
				name
				kind
				inputFields {
					name
					type {
						name
						kind
					}
				}
			}
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'TestInput', $actual['data']['__type']['name'] );
		$this->assertSame( 'test', $actual['data']['__type']['inputFields'][0]['name'] );

		// Test that the input type can be used in a mutation.
		$query = '
			mutation TestMutationForInputField($testInput: TestInput!) {
				testMutationForInputField(input: {testInput: $testInput} ) {
					testValue
				}
			}
		';

		$variables = [
			'testInput' => [
				'test' => 'test',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'testMutationForInputField.testValue', 'test' ),
			]
		);
	}

	public function testRegisterUnionType() {
		register_graphql_union_type(
			'TestUnion',
			[
				'typeNames'   => [ 'Post', 'Page' ],
				'resolveType' => static function ( $value ) {
					if ( $value instanceof WP_Post ) {
						return 'Post';
					}

					if ( $value instanceof WP_Post_Type ) {
						return 'Page';
					}

					return null;
				},
			]
		);

		$query = '
		{
			__type(name: "TestUnion") {
				name
				kind
				possibleTypes {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( '__type.name', 'TestUnion' ),
				$this->expectedField( '__type.kind', 'UNION' ),
				$this->expectedField( '__type.possibleTypes.0.name', 'Post' ),
				$this->expectedField( '__type.possibleTypes.1.name', 'Page' ),
			]
		);
	}

	public function testRegisterConnectionWithUnderscores() {

		register_graphql_connection(
			[
				'fromType'              => 'RootQuery',
				'toType'                => 'Post',
				'fromFieldName'         => 'test_field_with_underscores',
				'allowFieldUnderscores' => true,
			]
		);

		$actual = $this->graphql(
			[
				'query' => '
			{
				test_field_with_underscores(first:1) {
					nodes {
						id
					}
				}
			}
			',
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'test_field_with_underscores', self::IS_FALSY ),
			]
		);
	}

	public function testDeRegisterConnectionWithUnderscores() {

		deregister_graphql_connection( 'test_connection_with_underscores' );

		register_graphql_connection(
			[
				'fromType'              => 'RootQuery',
				'toType'                => 'Post',
				'fromFieldName'         => 'test_connection_with_underscores',
				'connectionTypeName'    => 'test_connection_with_underscores',
				'allowFieldUnderscores' => true,
			]
		);

		$actual = $this->graphql(
			[
				'query' => '
			{
				test_connection_with_underscores(first:1) {
					nodes {
						id
					}
				}
			}
			',
			]
		);

		codecept_debug( [
			'$actual' => $actual,
		]);

		// query should error because the connection was deregistered
//		self::assertQueryError( $actual, [] );
		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testRegisterMutationWithUnderscores() {

		$expected = 'test';

		register_graphql_mutation(
			'test_mutation_with_underscores',
			[
				'inputFields'           => [],
				'outputFields'          => [
					'test' => [
						'type' => 'String',
					],
				],
				'mutateAndGetPayload'   => static function () use ( $expected ) {
					return [
						'test' => $expected,
					];
				},
				'allowFieldUnderscores' => true,
			]
		);

		$actual = $this->graphql(
			[
				'query' => '
			mutation WithUnderscores {
				test_mutation_with_underscores(input: { clientMutationId: "test" }) {
					test
				}
			}
			',
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'test_mutation_with_underscores.test', $expected ),
			]
		);
	}

	public function testDeRegisterMutationWithUnderscores() {

		deregister_graphql_mutation( 'test_mutation_with_underscores' );

		register_graphql_mutation(
			'test_mutation_with_underscores',
			[
				'inputFields'           => [],
				'outputFields'          => [
					'test' => [
						'type' => 'String',
					],
				],
				'mutateAndGetPayload'   => static function () {
					return null; },
				'allowFieldUnderscores' => true,
			]
		);

		$actual = $this->graphql(
			[
				'query' => '
			mutation WithUnderscores {
				test_mutation_with_underscores(input: { clientMutationId: "test" }) {
					test
				}
			}
			',
			]
		);

		// query should error because the mutation was deregistered
		$this->assertArrayHasKey( 'errors', $actual );
	}

	public function testRegisterFieldWithNonExistingTypeReturnsErrorWhenFieldIsReferenced() {

		register_graphql_field( 'User', 'fakeField', [
			'type' => 'NonExistingType'
		]);

		// This should query without error because the field doesn't impact types queried here
		$query_one = '{posts{nodes{id}}}';

		// Should not return error because the field with the invalid type is not being queried for
		$query_two = '{users{nodes{id}}}';

		// This should return an error because the fakeField is being queried for and it references a non-existent type
		$query_three = '{users{nodes{id, fakeField}}}';

		$actual = $this->graphql([
			'query' => $query_one
		]);

		self::assertQuerySuccessful( $actual, [
			$this->expectedField( 'posts', self::NOT_NULL ),
		] );

		$actual_two = $this->graphql([
			'query' => $query_two
		]);

		self::assertQuerySuccessful( $actual_two, [
			$this->expectedField( 'users', self::NOT_NULL ),
		] );

		// The third query should throw a GraphQL\Error calling out the fact that the field is referencing a non-existent type
		$this->expectException( GraphQL\Error\Error::class );
		$this->expectExceptionMessageMatches( "/non-existent/" );

		$actual_three = $this->graphql([
			'query' => $query_three
		]);

	}
}
