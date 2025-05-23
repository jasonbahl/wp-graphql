<?php

class PostObjectQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;
	public $contributor;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->clearSchema();

		$this->current_time     = strtotime( '- 1 day' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->contributor      = $this->factory()->user->create(
			[
				'role' => 'contributor',
			]
		);

		add_shortcode(
			'wpgql_test_shortcode',
			static function ( $attrs, $content = null ) {
				global $post;
				if ( 'post' !== $post->post_type ) {
					return $content;
				}

				return 'overridden content';
			}
		);

		add_shortcode(
			'graphql_tests_basic_post_list',
			function ( $atts ) {
				$query = '
			query basicPostList($first:Int){
				posts(first:$first){
					edges{
						node{
							id
							title
							date
						}
					}
				}
			}
			';

				$variables = [
					'first' => ! empty( $atts['first'] ) ? absint( $atts['first'] ) : 5,
				];

				$data  = $this->graphql( compact( 'query', 'variables' ) );
				$edges = ! empty( $data['data']['posts']['edges'] ) ? $data['data']['posts']['edges'] : [];

				if ( ! empty( $edges ) && is_array( $edges ) ) {
					$output = '<ul class="gql-test-shortcode-list">';
					foreach ( $edges as $edge ) {
						$node = ! empty( $edge['node'] ) ? $edge['node'] : '';
						if ( ! empty( $node ) && is_array( $node ) ) {
							$output .= '<li id="' . $node['id'] . '">' . $node['title'] . ' ' . $node['date'] . '</li>';
						}
					}
					$output .= '</ul>';
				}

				return ! empty( $output ) ? $output : '';
			}
		);
	}

	public function tearDown(): void {
		// your tear down methods here

		$this->clearSchema();
		// then
		parent::tearDown();
	}

	/**
	 * @param string $structure
	 */
	public function set_permalink_structure( $structure = '' ) {
		global $wp_rewrite;
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( $structure );
		$wp_rewrite->flush_rules();
	}

	public function createPostObject( $args ) {
		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'post_author'  => $this->admin,
			'post_content' => 'Test page content',
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_title'   => 'Test Title for PostObjectQueriesTest',
			'post_type'    => 'post',
			'post_date'    => $this->current_date,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$post_id = $this->factory()->post->create( $args );

		/**
		 * Update the _edit_last and _edit_lock fields to simulate a user editing the page to
		 * test retrieving the fields
		 *
		 * @since 0.0.5
		 */
		update_post_meta( $post_id, '_edit_lock', $this->current_time . ':' . $this->admin );
		update_post_meta( $post_id, '_edit_last', $this->admin );

		/**
		 * Return the $id of the post_object that was created
		 */
		return $post_id;
	}

	/**
	 * testPostQuery
	 *
	 * This tests creating a single post with data and retrieving said post via a GraphQL query
	 *
	 * @since 0.0.5
	 * @throws \Exception
	 */
	public function testPostQuery() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type' => 'post',
			]
		);

		add_filter(
			'upload_dir',
			static function ( $param ) {
				$dir           = trailingslashit( WP_CONTENT_DIR ) . 'uploads';
				$param['path'] = $dir;
				return $param;
			}
		);

		/**
		 * Create a featured image and attach it to the post
		 */
		$filename          = ( WPGRAPHQL_PLUGIN_DIR . 'tests/_data/images/test.png' );
		$featured_image_id = $this->factory()->attachment->create_upload_object( $filename );
		update_post_meta( $post_id, '_thumbnail_id', $featured_image_id );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				author{
					node {
					userId
					}
				}
				commentCount
				commentStatus
				content
				date
				dateGmt
				desiredSlug
				lastEditedBy{
					node {
					userId
					}
				}
				editingLockedBy{
					lockTimestamp
					node{
						userId
					}
				}
				enclosure
				excerpt
				hasPassword
				password
				status
				link
				postId
				slug
				toPing
				pinged
				modified
				modifiedGmt
				title
				guid
				featuredImage{
					node {
					mediaItemId
					thumbnail: sourceUrl(size: THUMBNAIL)
					medium: sourceUrl(size: MEDIUM)
					full: sourceUrl(size: LARGE)
					sourceUrl
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'post' => [
				'id'              => $global_id,
				'author'          => [
					'node' => [
						'userId' => $this->admin,
					],
				],
				'commentCount'    => null,
				'commentStatus'   => 'open',
				'content'         => apply_filters( 'the_content', 'Test page content' ),
				'date'            => \WPGraphQL\Utils\Utils::prepare_date_response( 'now', $this->current_date ),
				'dateGmt'         => \WPGraphQL\Utils\Utils::prepare_date_response( get_post( $post_id )->post_modified_gmt ),
				'desiredSlug'     => null,
				'lastEditedBy'    => [
					'node' => [
						'userId' => $this->admin,
					],
				],
				'editingLockedBy' => null,
				'enclosure'       => null,
				'excerpt'         => apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', 'Test excerpt' ) ),
				'status'          => 'publish',
				'link'            => get_permalink( $post_id ),
				'postId'          => $post_id,
				'slug'            => 'test-title-for-postobjectqueriestest',
				'toPing'          => null,
				'pinged'          => null,
				'hasPassword'     => false,
				'password'        => null,
				'modified'        => \WPGraphQL\Utils\Utils::prepare_date_response( get_post( $post_id )->post_modified ),
				'modifiedGmt'     => \WPGraphQL\Utils\Utils::prepare_date_response( get_post( $post_id )->post_modified_gmt ),
				'title'           => apply_filters( 'the_title', 'Test Title for PostObjectQueriesTest' ),
				'guid'            => get_post( $post_id )->guid,
				'featuredImage'   => [
					'node' => [
						'mediaItemId' => $featured_image_id,
						'thumbnail'   => wp_get_attachment_image_src( $featured_image_id, 'thumbnail' )[0],
						'medium'      => wp_get_attachment_image_src( $featured_image_id, 'medium' )[0],
						'full'        => wp_get_attachment_image_src( $featured_image_id, 'large' )[0],
						'sourceUrl'   => wp_get_attachment_image_src( $featured_image_id, 'full' )[0],
					],
				],
			],
		];

		wp_delete_attachment( $featured_image_id, true );

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testPostQueryWherePostDoesNotExist
	 *
	 * Tests a query for non existent post.
	 *
	 * @since 0.0.34
	 */
	public function testPostQueryWherePostDoesNotExist() {
		/**
		 * Create the global ID based on the plugin_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', 'doesNotExist' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
 		query {
			post(id: \"{$global_id}\") {
				slug
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * There should be an internal server error when requesting a non-existent post
		 */
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * testPostQueryWithoutFeaturedImage
	 *
	 * This tests querying featuredImage on a post without one.
	 *
	 * @since 0.0.34
	 */
	public function testPostQueryWithoutFeaturedImage() {
		/**
		 * Create Post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type' => 'post',
			]
		);
		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );
		$query     = "
		query {
			post(id: \"{$global_id}\") {
				id
				featuredImage {
					node {
					altText
					author {
						node {
						id
						}
					}
					caption
					commentCount
					commentStatus
					comments {
						edges {
							node {
								id
							}
						}
					}
					date
					dateGmt
					description
					desiredSlug
					lastEditedBy {
						node {
						userId
							}
					}
					editingLockedBy {
						lockTimestamp
					}
					enclosure
					guid
					id
					link
					mediaDetails {
						file
						height
						meta {
							aperture
							credit
							camera
							caption
							createdTimestamp
							copyright
							focalLength
							iso
							shutterSpeed
							title
							orientation
							keywords
						}
						sizes {
							name
							file
							width
							height
							mimeType
							sourceUrl
						}
						width
					}
					mediaItemId
					mediaType
					mimeType
					modified
					modifiedGmt
					parent {
						node {
						...on Post {
							id
						}
							}
					}
					slug
					sourceUrl
					status
					title
					}
				}
			}
		}
		";

		$actual = $this->graphql( compact( 'query' ) );

		$expected = [
			'post' => [
				'id'            => $global_id,
				'featuredImage' => null,
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testPostQueryWithComments
	 *
	 * This tests creating a single post with comments.
	 *
	 * @since 0.0.5
	 */
	public function testPostQueryWithComments() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		// Create a comment and assign it to post.
		$comment_id = $this->factory()->comment->create(
			[
				'comment_post_ID' => $post_id,
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				commentCount
				commentStatus
				comments {
					edges {
						node {
							commentId
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'post' => [
				'id'            => $global_id,
				'comments'      => [
					'edges' => [
						[
							'node' => [
								'commentId' => $comment_id,
							],
						],
					],
				],
				'commentCount'  => 1,
				'commentStatus' => 'open',
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testPageQueryWithParent
	 *
	 * This tests a hierarchical post type assigned a parent.
	 *
	 * @since 0.0.5
	 */
	public function testPageQueryWithParent() {

		// Parent post.
		$parent_id = $this->createPostObject(
			[
				'post_type' => 'page',
			]
		);

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			page(id: \"{$global_id}\") {
				id
				parentId
				parentDatabaseId
				parent {
					node {
					... on Page {
						databaseId
					}
						}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Create the global ID of the parent too for asserting
		 */
		$global_parent_id = \GraphQLRelay\Relay::toGlobalId( 'post', $parent_id );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'page' => [
				'id'               => $global_id,
				'parentId'         => $global_parent_id,
				'parentDatabaseId' => $parent_id,
				'parent'           => [
					'node' => [
						'databaseId' => $parent_id,
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	public function testPageWithChildren() {

		$parent_id = $this->factory->post->create(
			[
				'post_type' => 'page',
			]
		);

		$child_id = $this->factory->post->create(
			[
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			]
		);

		$global_id       = \GraphQLRelay\Relay::toGlobalId( 'post', $parent_id );
		$global_child_id = \GraphQLRelay\Relay::toGlobalId( 'post', $child_id );

		$query = '
		{
			page( id: "' . $global_id . '" ) {
				id
				pageId
				children {
					edges {
						node {
								...on Page {
								id
								pageId
							}
						}
					}
				}
			}
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * Make sure the query didn't return any errors
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );

		$parent = $actual['data']['page'];
		$child  = $parent['children']['edges'][0]['node'];

		/**
		 * Make sure the child and parent data matches what we expect
		 */
		$this->assertEquals( $global_id, $parent['id'] );
		$this->assertEquals( $parent_id, $parent['pageId'] );
		$this->assertEquals( $global_child_id, $child['id'] );
		$this->assertEquals( $child_id, $child['pageId'] );
	}

	/**
	 * testPostQueryWithTags
	 *
	 * This tests creating a single post with assigned post tags.
	 *
	 * @since 0.0.5
	 */
	public function testPostQueryWithTags() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type' => 'post',
			]
		);

		// Create a comment and assign it to post.
		$tag_id = $this->factory()->tag->create(
			[
				'name' => 'Test Tag',
			]
		);

		wp_delete_object_term_relationships( $post_id, [ 'post_tag', 'category' ] );
		wp_set_object_terms( $post_id, $tag_id, 'post_tag', true );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				tags {
					edges {
						node {
							tagId
							name
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'post' => [
				'id'   => $global_id,
				'tags' => [
					'edges' => [
						[
							'node' => [
								'tagId' => $tag_id,
								'name'  => 'Test Tag',
							],
						],
					],
				],

			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * testPostQueryWithCategories
	 *
	 * This tests creating a single post with categories assigned.
	 *
	 * @since 0.0.5
	 */
	public function testPostQueryWithCategories() {

		wp_set_current_user( $this->admin );

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $this->admin,
				'post_title'  => 'Test Post for PostQueryWithCategories',
			]
		);

		// Create a comment and assign it to post.
		$category_id = $this->factory()->category->create(
			[
				'name' => 'A category',
			]
		);

		wp_set_object_terms( $post_id, $category_id, 'category', false );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				categories {
					edges {
						node {
							categoryId
							name
						}
					}
				}
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'post' => [
				'id'         => $global_id,
				'categories' => [
					'edges' => [
						[
							'node' => [
								'categoryId' => $category_id,
								'name'       => 'A category',
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * Test querying a post using the postBy query
	 */
	public function testPostByIdQuery() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type'  => 'post',
				'post_title' => 'Test Post for PostByIdQuery',
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			postBy(id: \"{$global_id}\") {
				id
				title
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'postBy' => [
				'id'    => $global_id,
				'title' => 'Test Post for PostByIdQuery',
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * Test querying a post using the postBy query and the URI arg
	 */
	public function testPostByUriQuery() {

		/**
		 * Create a post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type'  => 'post',
				'post_title' => 'Test post for PostByUriQuery',
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		$slug = get_post( $post_id )->post_name;

		/**
		 * Create the GraphQL query.
		 */
		$query = "
		query {
			postBy(slug: \"{$slug}\") {
				id
				title
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'postBy' => [
				'id'    => $global_id,
				'title' => 'Test post for PostByUriQuery',
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * Test querying a page using the pageBy query and the URI arg
	 */
	public function testPageByUri() {

		/**
		 * Create a page
		 */
		$parent_id = $this->createPostObject(
			[
				'post_type'   => 'page',
				'post_type'   => 'page',
				'post_title'  => 'Parent Page for PageByUri',
				'post_name'   => 'parent-page',
				'post_status' => 'publish',
			]
		);

		$child_id = $this->createPostObject(
			[
				'post_type'   => 'page',
				'post_title'  => 'Child Page for PageByUri',
				'post_name'   => 'child-page',
				'post_parent' => $parent_id,
				'post_status' => 'publish',
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_child_id = \GraphQLRelay\Relay::toGlobalId( 'post', $child_id );

		/**
		 * Get the uri to the Child Page
		 */
		$uri = rtrim( str_ireplace( home_url(), '', get_permalink( $child_id ) ), '' );

		codecept_debug( $uri );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			pageBy(uri: \"{$uri}\") {
				id
				title
				uri
			}
		}";

		wp_set_current_user( $this->admin );

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'pageBy' => [
				'id'    => $global_child_id,
				'title' => 'Child Page for PageByUri',
				'uri'   => $uri,
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * Test querying the same node multiple ways and ensuring we get the same response each time
	 */
	public function testPageByQueries() {

		wp_set_current_user( $this->admin );

		$post_id = $this->createPostObject(
			[
				'post_type'   => 'page',
				'post_title'  => 'Page for PageByQueries',
				'post_author' => $this->admin,
				'post_status' => 'publish',
			]
		);

		$path = get_page_uri( $post_id );
		codecept_debug( $path );
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Let's query the same node 3 different ways, then assert it's the same node
		 * each time
		 */
		$query = '
		{
			pages(first:1){
				edges{
					node{
						...pageData
					}
				}
			}
			byUri:pageBy(uri:"' . $path . '") {
				...pageData
			}
			byPageId:pageBy(pageId:' . $post_id . '){
				...pageData
			}
			byId:pageBy(id:"' . $global_id . '"){
				...pageData
			}
		}

		fragment pageData on Page {
			__typename
			id
			pageId
			title
			uri
			link
			slug
			date
		}
		';

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$node     = $actual['data']['pages']['edges'][0]['node'];
		$byUri    = $actual['data']['byUri'];
		$byPageId = $actual['data']['byPageId'];
		$byId     = $actual['data']['byId'];

		$this->assertNotEmpty( $node );
		$this->assertEquals( 'Page', $actual['data']['pages']['edges'][0]['node']['__typename'] );
		$this->assertEquals( $node, $byUri );
		$this->assertEquals( $node, $byPageId );
		$this->assertEquals( $node, $byId );
	}

	/**
	 * Query with an invalid ID, should return an error
	 */
	public function testPostByQueryWithInvalidId() {

		$query = '{
			postBy(id: "invalid ID") {
				id
				title
			}
		}';

		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * This should return an error as we tried to query with an invalid ID
		 */
		$this->assertArrayHasKey( 'errors', $actual );
	}

	/**
	 * Query for a post that was deleted
	 */
	public function testPostByQueryAfterPostWasDeleted() {

		/**
		 * Create the post
		 */
		$post_id = $this->createPostObject(
			[
				'post_type'  => 'post',
				'post_title' => 'Post that will be deleted',
			]
		);

		/**
		 * Get the ID
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Delete the post, because we want to query for a post that's been deleted
		 * and make sure it returns an error properly.
		 */
		wp_delete_post( $post_id, true );

		/**
		 * Query for the post
		 */
		$query = '{
			postBy(id: "' . $global_id . '") {
				id
				title
			}
		}';

		/**
		 * Run the query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * This should not return errors, and postBy should be null
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['postBy'] );
	}

	/**
	 * Test querying for a post with an ID that belongs to a different type
	 */
	public function testPostByQueryWithIDForADifferentType() {

		/**
		 * Create the page
		 */
		$page_id = $this->createPostObject(
			[
				'post_type'  => 'page',
				'post_title' => 'Test for PostByQueryWithIDForADifferentType',
			]
		);

		/**
		 * Get the ID
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $page_id );

		/**
		 * Query for the post, using a global ID for a page
		 */
		$query = '{
			postBy(id: "' . $global_id . '") {
				id
				title
			}
		}';

		/**
		 * Run the query
		 */
		$actual = $this->graphql( compact( 'query' ) );

		/**
		 * This should not return an error, but should return null for the postBy response
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['postBy'] );
	}

	/**
	 * testPostObjectFieldRawFormat
	 *
	 * This tests that we request the "raw" format from post object fields.
	 *
	 * @since 0.0.18
	 */
	public function testPostObjectFieldRawFormat() {
		/**
		 * Create a post that we can query via GraphQL.
		 */
		$graphql_query_post_id = $this->createPostObject( [] );

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $graphql_query_post_id );

		/**
		 * Add a filter that should be called when the content fields are
		 * requested in "rendered" format (the default).
		 */
		function override_for_testPostObjectFieldRawFormat() {
			return 'Overridden for testPostObjectFieldRawFormat';
		}

		add_filter( 'the_content', 'override_for_testPostObjectFieldRawFormat', 10, 0 );
		add_filter( 'the_excerpt', 'override_for_testPostObjectFieldRawFormat', 10, 0 );
		add_filter( 'the_title', 'override_for_testPostObjectFieldRawFormat', 10, 0 );

		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				content
				excerpt
				title
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$graphql_query_data = $this->graphql( compact( 'query' ) );

		/**
		 * Assert that the filters were called.
		 */
		$this->assertEquals( 'Overridden for testPostObjectFieldRawFormat', $graphql_query_data['data']['post']['content'] );
		$this->assertEquals( 'Overridden for testPostObjectFieldRawFormat', $graphql_query_data['data']['post']['excerpt'] );
		$this->assertEquals( 'Overridden for testPostObjectFieldRawFormat', $graphql_query_data['data']['post']['title'] );

		/**
		 * Run the same query but request the fields in raw form.
		 */
		wp_set_current_user( $this->admin );
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				content(format: RAW)
				excerpt(format: RAW)
				title(format: RAW)
			}
		}";

		/**
		 * Rerun the GraphQL query
		 */
		$graphql_query_data = $this->graphql( compact( 'query' ) );

		/**
		 * Assert that the filters were not called.
		 */
		$this->assertEquals( 'Test page content', $graphql_query_data['data']['post']['content'] );
		$this->assertEquals( 'Test excerpt', $graphql_query_data['data']['post']['excerpt'] );
		$this->assertEquals( 'Test Title for PostObjectQueriesTest', $graphql_query_data['data']['post']['title'] );
	}

	/**
	 * testPostQueryPostDataSetup
	 *
	 * This tests that we correctly setup post data for field resolvers.
	 *
	 * @since 0.0.18
	 */
	public function testPostQueryPostDataSetup() {
		/**
		 * Create a post that we can query via GraphQL.
		 */
		$graphql_query_post_id = $this->factory()->post->create();

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $graphql_query_post_id );

		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				content
			}
		}";

		/**
		 * Add a filter that will be called when the content field from the query
		 * above is resolved.
		 */
		add_filter(
			'the_content',
			function () use ( $graphql_query_post_id ) {
				/**
				 * Assert that post data was correctly set up.
				 */
				$this->assertEquals( $graphql_query_post_id, $GLOBALS['post']->ID );

				return 'Overridden for testPostQueryPostDataSetup';
			},
			99,
			0
		);

		/**
		 * Run the GraphQL query
		 */
		$graphql_query_data = $this->graphql( compact( 'query' ) );

		/**
		 * Assert that the filter was called.
		 */
		$this->assertEquals( 'Overridden for testPostQueryPostDataSetup', $graphql_query_data['data']['post']['content'] );
	}

	/**
	 * testPostQueryPostDataReset
	 *
	 * This tests that we correctly reset postdata without disturbing anything
	 * external to WPGraphQL.
	 *
	 * @since 0.0.18
	 */
	public function testPostQueryPostDataReset() {
		/**
		 * Create a post and simulate being in the main query / loop. We want to
		 * make sure that the query is properly reset after the GraphQL request is
		 * completed.
		 */
		global $post;
		$main_query_post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $main_query_post_id ) );
		setup_postdata( $post );

		/**
		 * Create another post that we can query via GraphQL.
		 */
		$graphql_query_post_id = $this->factory()->post->create();

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $graphql_query_post_id );

		/**
		 * Create the GraphQL query.
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$this->graphql( compact( 'query' ) );

		/**
		 * Assert that the query has been reset to the main query.
		 */
		$this->assertEquals( $main_query_post_id, $post->ID );

		// setup_postdata sets the global $id too so assert it is reset back to
		// original
		// https://github.com/WordPress/WordPress/blob/b5542c6b1b41d69b4e5c26ef8280c6e85de67224/wp-includes/class-wp-query.php#L4158
		$this->assertEquals( $main_query_post_id, $GLOBALS['id'] );
	}

	/**
	 *
	 */
	public function testPostQueryWithShortcodeInContent() {

		/**
		 * Create a post and simulate being in the main query / loop. We want to
		 * make sure that the query is properly reset after the GraphQL request is
		 * completed.
		 */
		global $post;
		$main_query_post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $main_query_post_id ) );
		setup_postdata( $post );

		/**
		 * Create another post that we can query via GraphQL.
		 */
		$graphql_query_post_id = $this->factory()->post->create(
			[
				'post_content' => '<p>Some content before the shortcode</p>[wpgql_test_shortcode]some test content[/wpgql_test_shortcode]<p>Some content after the shortcode</p>',
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $graphql_query_post_id );

		/**
		 * Create the GraphQL query.
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				content
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$response = $this->graphql( compact( 'query' ) );

		$this->assertNotFalse( strpos( $response['data']['post']['content'], 'Some content before the shortcode' ) );
		$this->assertNotFalse( strpos( $response['data']['post']['content'], 'overridden content' ) );
		$this->assertNotFalse( strpos( $response['data']['post']['content'], 'Some content after the shortcode' ) );

		/**
		 * Asset that the query has been reset to the main query.
		 */
		$this->assertEquals( $main_query_post_id, $post->ID );
	}

	public function testPostQueryPageWithShortcodeInContent() {

		/**
		 * Create a post and simulate being in the main query / loop. We want to
		 * make sure that the query is properly reset after the GraphQL request is
		 * completed.
		 */
		global $post;
		$main_query_post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $main_query_post_id ) );
		setup_postdata( $post );

		/**
		 * Create another post that we can query via GraphQL.
		 */
		$graphql_query_page_id = $this->factory()->post->create(
			[
				'post_content' => '<p>Some content before the shortcode</p>[wpgql_test_shortcode]some test content[/wpgql_test_shortcode]<p>Some content after the shortcode</p>',
				'post_type'    => 'page',
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $graphql_query_page_id );

		/**
		 * Create the GraphQL query.
		 */
		$query = "
		query {
			page(id: \"{$global_id}\") {
				id
				content
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$response = $this->graphql( compact( 'query' ) );

		$this->assertNotFalse( strpos( $response['data']['page']['content'], 'Some content before the shortcode' ) );
		$this->assertNotFalse( strpos( $response['data']['page']['content'], 'some test content' ) );
		$this->assertNotFalse( strpos( $response['data']['page']['content'], 'Some content after the shortcode' ) );

		/**
		 * Asset that the query has been reset to the main query.
		 */
		$this->assertEquals( $main_query_post_id, $post->ID );
	}

	/**
	 * This was a use case presented as something that _could_ break things.
	 *
	 * A WPGraphQL Query could be used within a shortcode to populate the shortcode content. If the
	 * global $post was set and _not_ reset, the content after the query would be broken.
	 *
	 * This simply ensures that the content before and after the shortcode are working as expected
	 * and that the global
	 * $post is reset properly after a gql query is performed.
	 */
	public function testGraphQLQueryShortcodeInContent() {

		/**
		 * Create a post and simulate being in the main query / loop. We want to
		 * make sure that the query is properly reset after the GraphQL request is
		 * completed.
		 */
		global $post;
		$main_query_post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $main_query_post_id ) );
		setup_postdata( $post );

		/**
		 * Create another post that we can query via GraphQL.
		 */
		$graphql_query_page_id = $this->factory()->post->create(
			[
				'post_content' => '<p>Some content before the shortcode</p>[graphql_tests_basic_post_list]<p>Some content after the shortcode</p>',
				'post_type'    => 'page',
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $graphql_query_page_id );

		/**
		 * Create the GraphQL query.
		 */
		$query = "
		query {
			page(id: \"{$global_id}\") {
				id
				content
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$response = $this->graphql( compact( 'query' ) );

		/**
		 * Here we're asserting that the shortcode is showing up (rendered) in the middle of the content, but that the content before and after
		 * is in place as expected as well.
		 */
		$this->assertNotFalse( strpos( $response['data']['page']['content'], 'Some content before the shortcode' ) );
		$this->assertNotFalse( strpos( $response['data']['page']['content'], '<ul class="gql-test-shortcode-list">' ) );
		$this->assertNotFalse( strpos( $response['data']['page']['content'], 'Some content after the shortcode' ) );

		/**
		 * Asset that the query has been reset to the main query.
		 */
		$this->assertEquals( $main_query_post_id, $post->ID );
	}

	/**
	 * Assert that no data is being leaked on private posts that are directly queried without auth.
	 */
	public function testPrivatePosts() {

		$post_id = $this->factory()->post->create(
			[
				'post_status'  => 'private',
				'post_content' => 'Test private posts',
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		$query = "
		query {
			postBy(id: \"{$global_id}\") {
					status
					title
					categories {
						nodes {
							id
							name
							slug
						}
					}
				}
			}";

		$expected = [
			'postBy' => null,
		];

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
	 * Test restricted posts returned on certain statuses
	 *
	 * @dataProvider dataProviderRestrictedPosts
	 */
	public function testRestrictedPosts( $status, $author, $user, $restricted ) {

		if ( ! empty( $author ) ) {
			$author = $this->{$author};
		}
		if ( ! empty( $user ) ) {
			$user = $this->{$user};
		}

		$title     = 'RestrictedPost from author: ' . (string) $author;
		$content   = 'Test Content';
		$post_date = time();

		if ( 'future' === $status ) {
			$post_date = $post_date + 600;
		}

		$post_date = date( 'Y-m-d H:i:s', $post_date );
		$post_id   = $this->factory()->post->create(
			[
				'post_status'  => $status,
				'post_author'  => $author,
				'post_title'   => $title,
				'post_content' => $content,
				'post_date'    => $post_date,
			]
		);

		/**
		 * Create the global ID based on the post_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		/**
		 * Create the GraphQL query.
		 */
		$query = "
		query {
			post(id: \"{$global_id}\") {
				id
				title
				status
				author{
					node {
					userId
					}
				}
				content
			}
		}";

		wp_set_current_user( $user );

		$actual = $this->graphql( compact( 'query' ) );

		codecept_debug( $user );
		codecept_debug( $actual );

		$expected = [
			'post' => [
				'id'      => $global_id,
				'title'   => $title,
				'status'  => $status,
				'author'  => [
					'node' => [
						'userId' => $author,
					],
				],
				'content' => apply_filters( 'the_content', $content ),
			],
		];

		if ( true === $restricted ) {
			$expected['post']['content'] = null;
			$expected['post']['author']  = null;
		}

		if ( 0 === $author ) {
			$expected['post']['author'] = null;
		}

		/**
		 * If the status is not "publish" and the user is a subscriber, the Post is considered
		 * private, so trying to fetch a private post by ID will return null, but no error
		 */
		if ( 'publish' !== $status && ! current_user_can( get_post_type_object( get_post( $post_id )->post_type )->cap->edit_posts ) ) {
			$this->assertArrayNotHasKey( 'errors', $actual );

			$expected = [
				'post' => null,
			];

			$this->assertEquals( $expected, $actual['data'] );
		} else {
			$this->assertEquals( $expected, $actual['data'] );
		}
	}

	public function dataProviderRestrictedPosts() {

		$test_vars = [];
		$statuses  = [ 'future', 'draft', 'pending' ];

		foreach ( $statuses as $status ) {
			$test_vars[] = [
				'status'     => $status,
				'author'     => 0,
				'user'       => 'admin',
				'restricted' => false,
			];

			$test_vars[] = [
				'status'     => $status,
				'author'     => 0,
				'user'       => 0,
				'restricted' => true,
			];

			$test_vars[] = [
				'status'     => $status,
				'author'     => 'contributor',
				'user'       => 'contributor',
				'restricted' => false,
			];
		}

		return $test_vars;
	}


	/**
	 * Tests to make sure the page set as the front page shows as the front page
	 *
	 * @throws \Exception
	 */
	public function testIsFrontPage() {

		/**
		 * Make sure no page is set as the front page
		 */
		update_option( 'show_on_front', 'post' );
		update_option( 'page_on_front', 0 );

		$pageId = $this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_title'  => 'Test Front Page',
			]
		);

		$other_pageId = $this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_title'  => 'Test Not Front Page',
			]
		);

		$query = '
		query Page( $pageId: Int ) {
			pageBy( pageId: $pageId ) {
				id
				title
				isFrontPage
			}
 		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'pageId' => $pageId,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertFalse( $actual['data']['pageBy']['isFrontPage'] );

		/**
		 * Set the page as the front page
		 */
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $pageId );

		/**
		 * Query again
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'pageId' => $pageId,
				],
			]
		);

		/**
		 * Assert that the page is showing as the front page
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['pageBy']['isFrontPage'] );

		/**
		 * Query a page that is NOT set as the front page
		 * so we can assert that isFrontPage is FALSE for it
		 */
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'pageId' => $other_pageId, // <-- NOTE OTHER PAGE ID
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertFalse( $actual['data']['pageBy']['isFrontPage'] );
	}

	/**
	 * Tests to make sure the page set as the privacy page shows as the privacy page
	 *
	 * @throws \Exception
	 */
	public function testIsPrivacyPage() {

		/**
		 * Set up test
		 */
		$notPrivacyPageId = $this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_title'  => 'Test is not Privacy Page',
			]
		);

		$privacyPageId = $this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_title'  => 'Test is Privacy Page',
			]
		);

		update_option( 'wp_page_for_privacy_policy', $privacyPageId );

		$query = '
		query Page( $pageId: Int ) {
			pageBy( pageId: $pageId ) {
				id
				title
				isPrivacyPage
			}
 		}
		';

		/**
		 * Make sure page not set as privacy page returns false
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'pageId' => $notPrivacyPageId,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertFalse( $actual['data']['pageBy']['isPrivacyPage'] );

		/**
		 * Make sure page set as privacy page returns true
		 */
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'pageId' => $privacyPageId,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertTrue( $actual['data']['pageBy']['isPrivacyPage'] );
	}

	/**
	 * This tests to query posts using the new idType option for single
	 * node entry points
	 *
	 * @throws \Exception
	 */
	public function testQueryPostUsingIDType() {

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test for QueryPostUsingIDType',
			]
		);

		$post = get_post( $post_id );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', absint( $post_id ) );
		$slug      = $post->post_name;
		$uri       = wp_make_link_relative( get_permalink( $post_id ) );
		$title     = get_post( $post_id )->post_title;

		codecept_debug( $uri );

		$expected = [
			'id'     => $global_id,
			'postId' => $post_id,
			'title'  => $title,
			'uri'    => $uri,
			'slug'   => $slug,
		];

		codecept_debug( $expected );

		/**
		 * Here we query a single post node by various entry points
		 * and assert that it's the same node in each response
		 */
		$query = '
		{
			postBySlugID: post(id: "' . $slug . '", idType: SLUG) {
				...PostFields
			}
			postByUriID: post(id: "' . $uri . '", idType: URI) {
				...PostFields
			}
			postByDatabaseID: post(id: "' . $post_id . '", idType: DATABASE_ID) {
				...PostFields
			}
			postByGlobalId: post(id: "' . $global_id . '", idType: ID) {
				...PostFields
			}
			postBySlug: postBy(slug: "' . $slug . '") {
				...PostFields
			}
			postByUri: postBy(uri: "' . $uri . '") {
				...PostFields
			}
			postById: postBy(id: "' . $global_id . '") {
				...PostFields
			}
			postByPostId: postBy(postId: ' . $post_id . ') {
				...PostFields
			}
		}

		fragment PostFields on Post {
			id
			postId
			title
			uri
			slug
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBySlugID'] );
		$this->assertSame( $expected, $actual['data']['postByUriID'] );
		$this->assertSame( $expected, $actual['data']['postByDatabaseID'] );
		$this->assertSame( $expected, $actual['data']['postByGlobalId'] );
		$this->assertSame( $expected, $actual['data']['postBySlug'] );
		$this->assertSame( $expected, $actual['data']['postByUri'] );
		$this->assertSame( $expected, $actual['data']['postById'] );
		$this->assertSame( $expected, $actual['data']['postByPostId'] );
	}

	/**
	 * This tests to query posts using the new idType option for single
	 * node entry points
	 *
	 * @throws \Exception
	 */
	public function testQueryPageUsingIDType() {

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test for QueryPageUsingIDType',
			]
		);

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', absint( $page_id ) );
		$slug      = get_post( $page_id )->post_name;
		$uri       = get_page_uri( $page_id );
		$title     = get_post( $page_id )->post_title;

		$expected = [
			'id'     => $global_id,
			'pageId' => $page_id,
			'title'  => $title,
			'uri'    => str_ireplace( home_url(), '', get_permalink( $page_id ) ),
			'slug'   => $slug,
		];

		codecept_debug( $expected );

		/**
		 * Here we query a single page node by various entry points
		 * and assert that it's the same node in each response
		 */
		$query = '
		{
			pageByUriID: page(id: "' . $uri . '", idType: URI) {
				...pageFields
			}
			pageByDatabaseID: page(id: "' . $page_id . '", idType: DATABASE_ID) {
				...pageFields
			}
			pageByGlobalId: page(id: "' . $global_id . '", idType: ID) {
				...pageFields
			}
			pageByUri: pageBy(uri: "' . $uri . '") {
				...pageFields
			}
			pageById: pageBy(id: "' . $global_id . '") {
				...pageFields
			}
			pageBypageId: pageBy(pageId: ' . $page_id . ') {
				...pageFields
			}
		}

		fragment pageFields on Page {
			id
			pageId
			title
			uri
			slug
		}
		';

		$actual = $this->graphql(
			[
				'query' => $query,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['pageByUriID'] );
		$this->assertSame( $expected, $actual['data']['pageByDatabaseID'] );
		$this->assertSame( $expected, $actual['data']['pageByGlobalId'] );
		$this->assertSame( $expected, $actual['data']['pageByUri'] );
		$this->assertSame( $expected, $actual['data']['pageById'] );
		$this->assertSame( $expected, $actual['data']['pageBypageId'] );
	}

	public function testQueryPostOfAnotherPostTypeReturnsNull() {

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => $this->admin,
			]
		);

		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_author' => $this->admin,
			]
		);

		$query = '
		query getPage($id:ID!){
			page(id:$id) {
				id
				__typename
			}
		}
		';

		$global_post_id = \GraphQLRelay\Relay::toGlobalId( 'post', $post_id );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $global_post_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( null, $actual['data']['page'] );

		$global_page_id = \GraphQLRelay\Relay::toGlobalId( 'post', $page_id );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $global_page_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $global_page_id, $actual['data']['page']['id'] );
	}

	public function testQueryPostByPageSlugReturnsNull() {

		$slug = 'test-page-slug';

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
			]
		);

		$query = '
		query PostAndPageByUri($uri:ID!){
			page(id:$uri idType:URI) {
				__typename
				databaseId
			}
			post(id:$uri idType:URI) {
				__typename
				databaseId
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $slug,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['post'] );
		$this->assertSame( $post_id, $actual['data']['page']['databaseId'] );
	}

	public function testQueryCustomPostTypeByPageUriReturnsNull() {

		register_post_type(
			'block',
			[
				'exclude_from_search' => true,
				'graphql_single_name' => 'block',
				'graphql_plural_name' => 'blocks',
				'labels'              => [
					'edit_item'     => 'Edit Block',
					'name'          => 'Blocks',
					'new_item'      => 'New Block',
					'singular_name' => 'Block',
				],
				'has_archive'         => true,
				'menu_icon'           => 'dashicons-screenoptions',
				'menu_position'       => 20,
				'public'              => false,
				'show_ui'             => true,
				'show_in_graphql'     => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'supports'            => [ 'custom-fields', 'revisions', 'title' ],
			]
		);

		$slug = 'test-page-slug';

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
			]
		);

		$query = '
		query BlockAndPageByUri($uri:ID!){
			page(id:$uri idType:URI) {
				__typename
				databaseId
			}
			block(id:$uri idType:URI) {
				__typename
				databaseId
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $slug,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['block'] );
		$this->assertSame( $post_id, $actual['data']['page']['databaseId'] );
	}

	public function testQueryNonPostsAsPostReturnsNull() {
		$query = '
		query PostByUri($uri:ID!){
			post(id:$uri idType:URI) {
				__typename
				databaseId
			}
		}
		';

		// Test page.
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_author' => $this->admin,
			]
		);

		$uri = wp_make_link_relative( get_permalink( $post_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['post'] );

		// Test term.
		$term_id = $this->factory()->term->create(
			[
				'taxonomy' => 'category',
			]
		);

		$uri = wp_make_link_relative( get_term_link( $term_id ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['post'] );

		// Test User.
		$uri = wp_make_link_relative( get_author_posts_url( $this->admin ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['post'] );

		// Test post type archive
		$uri = wp_make_link_relative( get_post_type_archive_link( 'post' ) );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => $uri,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['post'] );
	}

	// create a post using emoji
	public function testQueryPostBySlugWithEmojiSlug() {

		$raw_title = '🍌';

		// the $encoded_slug will have a dash between words i.e. 'سلا-دنیا'
		$encoded_slug = urldecode( sanitize_title( $raw_title ) );

		$non_ascii_post = $this->factory()->post->create_and_get(
			[
				'post_title'  => $raw_title,
				'post_status' => 'publish',
				'post_author' => $this->admin,
			]
		);

		codecept_debug(
			[
				'$non_ascii_string' => $raw_title,
				'$encoded_slug'     => $encoded_slug,
				'$post_name'        => $non_ascii_post->post_name,
				'post'              => $non_ascii_post,
			]
		);

		$query = '
		query getPostBySlug( $id: ID! ) {
			post( id: $id idType: SLUG ) {
				__typename
				title
				slug
				databaseId
			}
		}
		';

		// query the post by (encoded) slug
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $encoded_slug, // سلام-دنیا //
				],
			]
		);

		// assert that the response is what we expect
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.__typename', 'Post' ),
				$this->expectedField( 'post.title', $raw_title ),
				$this->expectedField( 'post.slug', $encoded_slug ),
				$this->expectedField( 'post.databaseId', $non_ascii_post->ID ),
			]
		);
	}

	// create a post using unicode
	public function testQueryPostBySlugWithNonAsciiSlug() {

		$raw_title = 'سلام دنیا';

		// the $encoded_slug will have a dash between words i.e. 'سلا-دنیا'
		$encoded_slug = urldecode( sanitize_title( $raw_title ) );

		$non_ascii_post = $this->factory()->post->create_and_get(
			[
				'post_title'  => $raw_title,
				'post_status' => 'publish',
				'post_author' => $this->admin,
			]
		);

		codecept_debug(
			[
				'$non_ascii_string' => $raw_title,
				'$encoded_slug'     => $encoded_slug,
				'$post_name'        => $non_ascii_post->post_name,
				'post'              => $non_ascii_post,
			]
		);

		$query = '
		query getPostBySlug( $id: ID! ) {
			post( id: $id idType: SLUG ) {
				__typename
				title
				slug
				databaseId
			}
		}
		';

		// query the post by (encoded) slug
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $encoded_slug, // سلام-دنیا //
				],
			]
		);

		// assert that the response is what we expect
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.__typename', 'Post' ),
				$this->expectedField( 'post.title', $raw_title ),
				$this->expectedField( 'post.slug', $encoded_slug ),
				$this->expectedField( 'post.databaseId', $non_ascii_post->ID ),
			]
		);
	}

	public function testPasswordProtectedPost() {
		$subscriber = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);

		$post_author_one = $this->factory()->user->create(
			[
				'role' => 'author',
			]
		);

		$post_author_two = $this->factory()->user->create(
			[
				'role' => 'author',
			]
		);

		$post = $this->factory()->post->create_and_get(
			[
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => 'mypassword',
				'title'         => 'Password Protected post',
				'content'       => 'Some content',
				'post_author'   => $post_author_one,
			]
		);

		$query = '
		query GetPasswordProtectedPost( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				title
				content
				status
				password
				hasPassword
			}
		}
		';

		// Test password protected post as unauthenticated user.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post->ID,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $post->post_title, $actual['data']['post']['title'], 'Title should be returned when unauthenticated' );
		$this->assertEquals( 'publish', $actual['data']['post']['status'], 'Status should be "publish" when unauthenticated' );
		$this->assertNull( $actual['data']['post']['content'], 'Content should be null when unauthenticated' );
		$this->assertNull( $actual['data']['post']['password'], 'Password should be null when unauthenticated' );
		$this->assertTrue( $actual['data']['post']['hasPassword'], 'hasPassword should be true when unauthenticated' );

		// Test password protected post as a subscriber.
		wp_set_current_user( $subscriber );
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post->ID,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $post->post_title, $actual['data']['post']['title'], 'Title should be returned when lacking permissions' );
		$this->assertEquals( 'publish', $actual['data']['post']['status'], 'Status should be "publish" when lacking permissions' );
		$this->assertNull( $actual['data']['post']['content'], 'Content should be null when lacking permissions' );
		$this->assertNull( $actual['data']['post']['password'], 'Password should be null when lacking permissions' );
		$this->assertTrue( $actual['data']['post']['hasPassword'], 'hasPassword should be true when lacking permissions' );

		// Test password protected post as different author.
		wp_set_current_user( $post_author_two );
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post->ID,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $post->post_title, $actual['data']['post']['title'], 'Title should be returned when lacking permissions' );
		$this->assertEquals( 'publish', $actual['data']['post']['status'], 'Status should be "publish" when lacking permissions' );
		$this->assertNull( $actual['data']['post']['content'], 'Content should be null when lacking permissions' );
		$this->assertNull( $actual['data']['post']['password'], 'Password should be null when lacking permissions' );
		$this->assertTrue( $actual['data']['post']['hasPassword'], 'hasPassword should be true when lacking permissions' );

		// Test password protected post as current author.
		wp_set_current_user( $post_author_one );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post->ID,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $post->post_title, $actual['data']['post']['title'], 'Title should be returned when authenticated' );
		$this->assertEquals( 'publish', $actual['data']['post']['status'], 'Status should be "publish" when authenticated' );
		$this->assertNotEmpty( $actual['data']['post']['content'], 'Content should be returned when authenticated' );
		$this->assertEquals( $post->post_password, $actual['data']['post']['password'], 'Password should be returned when authenticated' );
		$this->assertTrue( $actual['data']['post']['hasPassword'], 'hasPassword should be true when authenticated' );

		// Test password protected post as admin.
		wp_set_current_user( $this->admin );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $post->ID,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $post->post_title, $actual['data']['post']['title'], 'Title should be returned when authenticated' );
		$this->assertEquals( 'publish', $actual['data']['post']['status'], 'Status should be "publish" when authenticated' );
		$this->assertNotEmpty( $actual['data']['post']['content'], 'Content should be returned when authenticated' );
		$this->assertEquals( $post->post_password, $actual['data']['post']['password'], 'Password should be returned when authenticated' );
		$this->assertTrue( $actual['data']['post']['hasPassword'], 'hasPassword should be true when authenticated' );

		// @todo add case with password supplied once supported.
		// @see https://github.com/wp-graphql/wp-graphql/issues/930

		// cleanup
		wp_delete_post( $post->ID, true );
		wp_delete_user( $subscriber );
	}
}
