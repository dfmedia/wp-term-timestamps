<?php
/**
 * Plugin Name:     WP Term Timestamps
 * Plugin URI:      https://github.com/dfmedia/wp-term-timestamps
 * Description:     This is a simple plugin that records timestamps when terms are created or modified, and the ID of
 * the user who made the modification. Author:          Digital First Media, Jason Bahl Author URI:
 * https://github.com/dfmedia/wp-term-timestamps Text Domain:     wp-term-timestamps Domain Path:     /languages
 * Version:         0.1.2
 *
 * @package         WP_Term_Timestamps
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Term_Timestamps' ) ) :

	class WP_Term_Timestamps {

		/**
		 * Holds the value of the meta_key that should be used to store the ID of the user that created the term
		 *
		 * @var string
		 */
		public $created_by_meta_key = 'created_by';

		/**
		 * Holds the value of the meta_key that should be used to store the created timestamp
		 *
		 * @var string
		 */
		public $created_timestamp_meta_key = 'created_timestamp';

		/**
		 * Holds the value of the meta_key that should be used to store the modifications history
		 *
		 * @var string
		 */
		public $modifications_meta_key = 'modifications';

		/**
		 * Holds the value of the meta_key that should be used to store the last modified history
		 *
		 * @var string
		 */
		public $last_modified_timestamp_meta_key = 'last_modified_timestamp';

		/**
		 * Holds the value of the meta_key that should be used to store the last modified user ID
		 *
		 * @var string
		 */
		public $last_modified_by_meta_key = 'last_modified_by';

		/**
		 * Define the WPGraphQL type to return when querying term updates
		 *
		 * @var mixed|\WPGraphQL\Type\WPObjectType|null
		 */
		public static $term_updated_type;

		/**
		 * Sets up the WP_Term_Timestamps class to hook into WordPress
		 */
		public function setup() {

			/**
			 * Define the plugin version
			 */
			define( 'WP_TERM_TIMESTAMPS_VERSION', '0.1.2' );

			/**
			 * Apply filters to class vars
			 */
			$this->apply_filters();

			/**
			 * Listen for terms to be created/updated and store term_meta
			 */
			add_action( 'create_term', [ $this, 'add_term_created_timestamp' ], 10, 3 );
			add_action( 'edit_terms', [ $this, 'add_term_modified_meta' ], 10, 2 );

			/**
			 * Add created/modified columns to the Term Edit screens
			 */
			$taxonomies = get_taxonomies([
				'show_ui' => true,
			]);

			if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy ) {
					add_filter( "manage_edit-{$taxonomy}_columns", [ $this, 'add_timestamps_to_term_columns' ] );
					add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'term_column_output' ], 10, 3 );
				}
			}

			/**
			 * Setup support for WPGraphQL
			 */
			add_action( 'graphql_generate_schema', [ $this, 'graphql_support' ] );
		}

		/**
		 * Applies filters to the class vars
		 */
		public function apply_filters() {

			/**
			 * Filter the meta_key the created_by user ID should be stored in
			 *
			 * @param string $meta_key The meta_key to store the user ID of the user that created the term
			 */
			$this->created_by_meta_key = apply_filters( 'wp_term_timestamps_created_by_meta_key', $this->created_by_meta_key );

			/**
			 * Filter the meta_key the created_timestamp should be saved to
			 *
			 * @param string $meta_key The meta_key to store the created_timestamp
			 */
			$this->created_timestamp_meta_key = apply_filters( 'wp_term_timestamps_created_timestamp_key', $this->created_timestamp_meta_key );

			/**
			 * Filter the meta_key for where term modifications should be stored
			 *
			 * @param string $meta_key The meta_key to store modifications history to
			 */
			$this->modifications_meta_key = apply_filters( 'wp_term_modifications_meta_key', $this->modifications_meta_key );

			/**
			 * Filter the meta_key for where the last_modified_timestamp should be stored
			 *
			 * @param string $meta_key The meta_key to store the timestamp of when the term was last modified
			 */
			$this->last_modified_timestamp_meta_key = apply_filters( 'wp_term_timestamps_last_modified_timestamp_meta_key', $this->last_modified_timestamp_meta_key );

			/**
			 * Filter the meta_key for where the User ID of who modified the term most recently should be stored
			 *
			 * @param string $meta_key The meta_key to store User ID of the user who most recently modified the term
			 */
			$this->last_modified_by_meta_key = apply_filters( 'wp_term_timestamps_created_meta_key', $this->last_modified_by_meta_key );

		}

		/**
		 * Adds a timestamp to a term's term_meta when a term is created
		 *
		 * @param int    $term_id  Term ID.
		 * @param int    $tt_id    Term taxonomy ID.
		 * @param string $taxonomy Taxonomy slug.
		 */
		public function add_term_created_timestamp( $term_id, $tt_id, $taxonomy ) {

			/**
			 * Store the created_by and created_timestamp term meta for the term being created
			 */
			update_term_meta( $term_id, $this->created_by_meta_key, get_current_user_id(), true );
			update_term_meta( $term_id, $this->created_timestamp_meta_key, current_time( 'mysql' ), true );

		}

		/**
		 * Adds a timestamp to a term's term_meta when a term is edited
		 */
		public function add_term_modified_meta( $term_id, $taxonomy ) {

			/**
			 * Save the term_meta to the specified mete_key as a non-unique value (allowing every modification to be saved)
			 */
			update_term_meta( $term_id, $this->last_modified_by_meta_key, get_current_user_id(), true );
			update_term_meta( $term_id, $this->last_modified_timestamp_meta_key, current_time( 'mysql' ), true );
			add_term_meta( $term_id, $this->modifications_meta_key, $this->prepare_meta_value( $term_id, $taxonomy ), false );
		}

		/**
		 * Add created and modified columns to the term edit screens
		 *
		 * @param array $columns The columns registered to the term edit screen
		 *
		 * @return mixed
		 */
		public function add_timestamps_to_term_columns( $columns ) {
			$columns['created']       = __( 'Created', 'wp-term-timestamps' );
			$columns['last_modified'] = __( 'Last Modified', 'wp-term-timestamps' );

			return $columns;
		}

		/**
		 * Populates the columns that were registered to the Term Edit Screens
		 *
		 * @param mixed  $content     The contend displayed in the column
		 * @param string $column_name The name of the column
		 * @param int    $term_id     The ID of the term
		 *
		 * @return false|null|string
		 */
		public function term_column_output( $content, $column_name, $term_id ) {

			switch ( $column_name ) {
				case 'created':
					$created_time = get_term_meta( $term_id, $this->created_timestamp_meta_key, true );

					return ! empty( $created_time ) && false !== strtotime( $created_time ) ? date( 'D M j,Y H:i:s', strtotime( $created_time ) ) : '';
				case 'last_modified':
					$modified_time = get_term_meta( $term_id, $this->last_modified_timestamp_meta_key, true );

					return ! empty( $modified_time ) && false !== strtotime( $modified_time ) ? date( 'D M j,Y H:i:s', strtotime( $modified_time ) ) : '';
				default:
					return $content;
			}

		}

		/**
		 * This prepares the meta that will be saved in the term_meta whenever a term is updated
		 *
		 * @param int    $term_id  The ID of the term being updated or created
		 * @param string $taxonomy The name of the taxonomy the term being edited or created belongs to
		 *
		 * @return array
		 */
		public function prepare_meta_value( $term_id, $taxonomy ) {

			/**
			 * Prepare the meta_value that will be saved when terms are created or modified
			 */
			$meta_value = [
				'user_id'   => get_current_user_id(),
				'timestamp' => current_time( 'mysql' ),
			];

			/**
			 * Filter the value that gets stored when terms are created or modified
			 *
			 * @param array  $meta_value The value to get stored when terms are created or updated
			 * @param int    $term_id    The ID of the term being created or updated
			 * @param string $taxonomy   The name of the taxonomy the created or updated term belongs to
			 */
			return apply_filters( 'wp_term_timestamps_meta_value', $meta_value, $term_id, $taxonomy );
		}

		/**
		 * This sets up support for WPGraphQL
		 */
		public function graphql_support() {

			/**
			 * This plugin requires version 0.0.12 or higher of the WPGraphQL Plugin
			 */
			if ( defined( 'WPGRAPHQL_VERSION' ) && version_compare( WPGRAPHQL_VERSION, '0.0.12' ) < 0 ) {
				return;
			}

			/**
			 * Get the allowed WPGraphQL taxonomies
			 */
			$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();

			/**
			 * Filter the fields of each of the allowed taxonomies
			 */
			if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
				foreach ( $allowed_taxonomies as $taxonomy ) {
					$tax_object = get_taxonomy( $taxonomy );
					if ( ! empty( $tax_object->graphql_single_name ) ) {
						$tax_name = $tax_object->graphql_single_name;
						add_filter( "graphql_{$tax_name}_fields", [ $this, 'add_term_timestamp_fields_to_graphql' ] );
					}
				}
			}

		}

		/**
		 * This adds a created and modified
		 *
		 * @param array $fields The existing fields for the TermObjectType being filtered
		 *
		 * @return array
		 */
		public function add_term_timestamp_fields_to_graphql( $fields ) {

			/**
			 * This allows the key of the created field to be filtered to avoid conflicts with potential existing
			 * GraphQL fields that already use the "created" key
			 */
			$created_graphql_field = apply_filters( 'wp_term_timestamps_graphql_created_field_key', 'created' );

			/**
			 * Add the "created" (or filtered name) field to the Schema for the TermObjectType
			 */
			$fields[ $created_graphql_field ] = [
				'type'        => $this->term_updated_type(),
				'description' => __( 'Details on when the term was created', 'wp-term-timestamps' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {

					/**
					 * Create an array of meta to return
					 */
					$meta = [];

					/**
					 * Get the created term_meta
					 */
					$created_time       = get_term_meta( $term->term_id, $this->created_timestamp_meta_key, true );
					$created_by_user_id = get_term_meta( $term->term_id, $this->created_by_meta_key, true );

					/**
					 * Add the meta values to the array to return
					 */
					$meta['timestamp'] = ( ! empty( $created_time ) && false !== strtotime( $created_time ) ) ? date( 'D M j,Y H:i:s', strtotime( $created_time ) ) : null;
					$meta['user_id']   = ( ! empty( $created_by_user_id ) && absint( $created_by_user_id ) ) ? $created_by_user_id : null;

					return $meta;
				},
			];

			/**
			 * This allows the key of the created field to be filtered to avoid conflicts with potential existing
			 * GraphQL fields that already use the "modified" key
			 */
			$modifications_graphql_field = apply_filters( 'wp_term_timestamps_graphql_modifications_field_key', 'modifications' );

			/**
			 * Add the "modified" (or filtered name) field to the Schema for the TermObjectType
			 */
			$fields[ $modifications_graphql_field ] = [
				'type'        => \WPGraphQL\Types::list_of( $this->term_updated_type() ),
				'description' => __( 'Details on Term modification history', 'wp-term-timestamps' ),
				'resolve'     => function( \WP_Term $term, array $args, $context, $info ) {
					$created_meta = get_term_meta( $term->term_id, $this->modifications_meta_key, false );

					return ! empty( $created_meta ) && is_array( $created_meta ) ? array_reverse( $created_meta ) : null;
				},
			];

			$last_modified_graphql_field = apply_filters( 'wp_term_timestamps_graphql_modifications_field_key', 'lastModified' );

			$fields[ $last_modified_graphql_field ] = [
				'type' => $this->term_updated_type(),
				'description' => __( 'Details on the last modified time and user', 'wp-term-timestamps' ),
				'resolve' => function( \WP_Term $term, array $args, $context, $info ) {
					/**
					 * Create an array of meta to return
					 */
					$meta = [];

					/**
					 * Get the created term_meta
					 */
					$last_modified_time       = get_term_meta( $term->term_id, $this->last_modified_timestamp_meta_key, true );
					$last_modified_by_user_id = get_term_meta( $term->term_id, $this->last_modified_by_meta_key, true );

					/**
					 * Add the meta values to the array to return
					 */
					$meta['timestamp'] = ( ! empty( $last_modified_time ) && false !== strtotime( $last_modified_time ) ) ? date( 'D M j,Y H:i:s', strtotime( $last_modified_time ) ) : null;
					$meta['user_id']   = ( ! empty( $last_modified_by_user_id ) && absint( $last_modified_by_user_id ) ) ? $last_modified_by_user_id : null;

					return $meta;
				},
			];

			return $fields;
		}

		/**
		 * @return null|\WPGraphQL\Type\WPObjectType
		 */
		public function term_updated_type() {

			if ( null === self::$term_updated_type ) {

				self::$term_updated_type = new \WPGraphQL\Type\WPObjectType( [
					'name'   => 'termUpdated',
					'fields' => function() {
						return \WPGraphQL\Type\WPObjectType::prepare_fields( [
							'time' => [
								'type'        => \WPGraphQL\Types::string(),
								'description' => __( 'The timestamp of when the object was created', 'wp-term-timestamps' ),
								'resolve'     => function( array $meta, array $args, $context, $info ) {
									return ! empty( $meta['timestamp'] ) ? $meta['timestamp'] : null;
								},
							],
							'user' => [
								'type'        => \WPGraphQL\Types::user(),
								'description' => __( 'The user that created the term', 'wp-term-relationships' ),
								'resolve'     => function( array $meta, array $args, $context, $info ) {
									return ( ! empty( $meta['user_id'] ) && absint( $meta['user_id'] ) ) ? get_user_by( 'id', absint( $meta['user_id'] ) ) : null;
								}
							]
						], 'termCreated' );
					},
				] );
			}

			return ! empty( self::$term_updated_type ) ? self::$term_updated_type : null;
		}

	}

endif;

/**
 * This initializes the WP_Term_Timestamps class
 */
function wp_term_timestamps_init() {

	/**
	 * Instantiate the class
	 */
	$wp_term_timestamps = new WP_Term_Timestamps();

	/**
	 * Setup the class
	 */
	$wp_term_timestamps->setup();
}

add_action( 'plugins_loaded', 'wp_term_timestamps_init' );