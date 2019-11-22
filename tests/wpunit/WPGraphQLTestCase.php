<?php
class WPGraphQLTestCase extends \Codeception\TestCase\WPTestCase {

	public $admin_user;
	public $editor_user;
	public $author_user;
	public $contributor_user;
	public $subscriber_user;

	public function setUp() {r
		parent::setUp();
		$this->create_users();
		$this->create_terms();
		$this->create_media();
		$this->create_posts();
		$this->create_comments();
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Create users for use in queries and Post creation
	 */
	public function create_users() {
		$this->admin_user = $this->factory()->user->create([
			'user_login' => 'admin',
			'role' => 'administrator',
			'user_email' => 'administrator@example.com',
			'first_name' => 'Admin',
			'last_name' => 'Istrator',
			'nickname' => 'admin',
			'user_nicename' => 'admin',
			'display_name' => 'admin',
			'user_url' => get_bloginfo( 'url' ),
			'description' => 'Hello, I am an admin',
			'show_admin_bar_front' => true,
			'comment_shortcuts' => false,
			'admin_color' => 'fresh',
			'rich_editing' => false,
			'syntax_highlighting' => false,
			'use_ssl' => false,
			'spam' => false,
			'locale' => '',
		]);

		$this->editor_user = $this->factory()->user->create([
			'role' => 'editor',
			'email' => 'administrator@example.com',
			'user_email' => 'administrator@example.com',
			'first_name' => 'Admin',
			'last_name' => 'Istrator',
			'nickname' => 'admin',
			'user_nicename' => 'admin',
			'display_name' => 'admin',
			'user_url' => get_bloginfo( 'url' ),
			'description' => 'Hello, I am an admin',
			'show_admin_bar_front' => true,
			'comment_shortcuts' => false,
			'admin_color' => 'fresh',
			'rich_editing' => false,
			'syntax_highlighting' => false,
			'use_ssl' => false,
			'spam' => false,
			'locale' => '',
		]);

		$this->author_user = $this->factory()->user->create([
			'role' => 'author',
			'email' => 'administrator@example.com'
		]);

		$this->contributor_user = $this->factory()->user->create([
			'role' => 'contributor',
			'email' => 'administrator@example.com'
		]);

		$this->subscriber_user = $this->factory()->user->create([
			'role' => 'subscriber',
			'email' => 'administrator@example.com'
		]);

	}

	public function create_terms() {

	}

	public function create_media() {

	}

	public function create_posts() {

	}

	public function create_comments() {

	}
}

