<?php
/**
 * Class WP_Term_Timestamp_Tests
 *
 * @package WP_Term_Timestamps
 */

/**
 * WP_Term_Timestamp_Tests.
 */
class WP_Term_Timestamp_Tests extends WP_UnitTestCase {

	/**
	 * The admin user that is used to test creating and updating terms
	 * @var
	 */
	public $admin;

	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();
		$this->taxonomy = 'category';
		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );
	}

	/**
	 * Runs after each method
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Helper function that creates a term and returns the Term ID
	 * @return mixed
	 */
	public function createTerm() {

		/**
		 * Create a term
		 */
		$term_id = $this->factory->term->create( [
			'name'     => 'A Category',
			'taxonomy' => 'category',
			'description' => 'just a description',
		] );

		return $term_id;

	}

	/**
	 * This tests whether timestamp data is saved when a term is created or updated.
	 */
	public function testTimestampOnCreateAndEdit() {

		/**
		 * Set the current user to someone with permission to create terms
		 */
		wp_set_current_user( $this->admin );

		/**
		 * Create a term
		 */
		$term_id = $this->createTerm();

		/**
		 * Ensure the "create_term" action was fired
		 */
		$this->assertNotFalse( did_action( 'create_term' ) );

		/**
		 * Ensure that a $term_id was returned when the term was created
		 */
		$this->assertNotEmpty( $term_id );

		/**
		 * Get the $created_meta_key, after filters have been applied
		 */
		$created_meta_key = apply_filters( 'wp_term_timestamps_created_meta_key', 'created', $term_id, 'category' );

		/**
		 * Get the term_meta for the created term
		 */
		$actual = get_term_meta( $term_id, $created_meta_key, false );

		/**
		 * Ensure there is some data stored in the term_meta
		 */
		$this->assertNotEmpty( $actual );

		/**
		 * Ensure that the timestamp is a valid timestamp
		 */
		$this->assertNotEmpty( $actual[0]['timestamp'] );
		$this->assertNotFalse( strtotime($actual[0]['timestamp']) );

		/**
		 * Ensure that the user_id is populated and is the same as the user that created the term
		 */
		$this->assertNotEmpty( $actual[0]['user_id'] );
		$this->assertEquals( $actual[0]['user_id'], $this->admin );

		/**
		 * Update the term
		 */
		wp_update_term( $term_id, 'category', [ 'description' => 'updated description' ]);

		/**
		 * Get the $modified_meta_key, after filters have been applied
		 */
		$modified_meta_key = apply_filters( 'wp_term_timestamps_modified_meta_key', 'modified', $term_id, 'category' );

		/**
		 * Get the term_meta for the modified term
		 */
		$actual = get_term_meta( $term_id, $modified_meta_key, false );

		/**
		 * Ensure there is some data stored in the term_meta
		 */
		$this->assertNotEmpty( $actual );

		/**
		 * Ensure that the timestamp is a valid timestamp
		 */
		$this->assertNotEmpty( $actual[0]['timestamp'] );
		$this->assertNotFalse( strtotime($actual[0]['timestamp']) );

		/**
		 * Ensure that the user_id is populated and is the same as the user that created the term
		 */
		$this->assertNotEmpty( $actual[0]['user_id'] );
		$this->assertEquals( $actual[0]['user_id'], $this->admin );

	}

	/**
	 * This tests that the fields exist in the GraphQL schema
	 */
	public function testWPGraphQLSupport() {

		/**
		 * Only run these tests if WPGraphQL is installed in the same environment where these tests are being run
		 */
		if ( function_exists( 'do_graphql_request' ) && defined( 'WPGRAPHQL_VERSION' ) && version_compare( WPGRAPHQL_VERSION, '0.0.12' ) >= 0  ) {

			/**
			 * Set the current user to someone with permission to create terms
			 */
			wp_set_current_user( $this->admin );

			/**
			 * Create a term
			 */
			$term_id = $this->createTerm();

			/**
			 * Get the Global ID based on the $term_id and taxonomy type
			 */
			$global_id = \GraphQLRelay\Relay::toGlobalId( 'category', $term_id );

			/**
			 * Ensure the $global_id came back populated
			 */
			$this->assertNotEmpty( $global_id );

			/**
			 * Create the static GraphQL request
			 */
			$request = '
			query getCategory($id:ID!){
				category(id:$id){
					id
					name
					categoryId
					created {
						user{
							userId
						}
					}
				}
			}
			';

			/**
			 * Define the variables for the GraphQL request
			 */
			$variables = [
				'id' => $global_id
			];

			/**
			 * Process the GraphQL query
			 */
			$actual = do_graphql_request( $request, 'getCategory', $variables );

			/**
			 * Ensure the GraphQL Query returns a response
			 */
			$this->assertNotEmpty( $actual );

			/**
			 * Define what we're expecting to see from the query
			 */
			$expected = [
				'data' => [
					'category' => [
						'id' => $global_id,
						'name' => 'A Category',
						'categoryId' => $term_id,
						'created' => [
							'user' => [
								'userId' => $this->admin
							],
						],
					],
				],
			];

			/**
			 * Ensure the processed GraphQL request matches our expectation
			 */
			$this->assertEquals( $actual, $expected );

		} else {

			/**
			 * Output the message to PHPUnit
			 */
			fwrite(STDERR, print_r( 'WPGraphQL is either not installed or the version is not compatible with this plugin', TRUE ) );

		}

	}
}
