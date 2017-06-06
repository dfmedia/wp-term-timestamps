<?php
/**
 * Plugin Name:     WP Term Timestamps
 * Plugin URI:      https://github.com/dfmedia/wp-term-timestamps
 * Description:     This is a simple plugin that records timestamps when terms are created or modified, and the ID of the user who made the modification.
 * Author:          Digital First Media, Jason Bahl
 * Author URI:      https://github.com/dfmedia/wp-term-timestamps
 * Text Domain:     wp-term-timestamps
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Wp_Term_Timestamps
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Term_Timestamps' ) ) :

class WP_Term_Timestamps {

	/**
	 * Holds the value of the meta_key that should be used to store the created timestamp info
	 * @var string
	 */
	public $created_meta_key = 'created';

	/**
	 * Holds the value of the meta_key that should be used to store the modified timestamp info
	 * @var string
	 */
	public $modified_meta_key = 'modified';

	/**
	 * Define the termUpdatedType
	 * @var
	 */
	public static $term_updated_type;

	/**
	 * Sets up the WP_Term_Timestamps class to hook into WordPress
	 */
	public function setup() {

		/**
		 * Filter the meta_key created timestamp data should be saved to
		 *
		 * @param string  $meta_key  The meta_key to store modified timestamp info to
		 */
		$this->created_meta_key = apply_filters( 'wp_term_timestamps_created_meta_key', 'created' );

		/**
		 * Filter the meta_key modified timestamp data should be saved to
		 *
		 * @param string  $meta_key  The meta_key to store modified timestamp info to
		 */
		$this->modified_meta_key = apply_filters( 'wp_term_timestamps_modified_meta_key', 'modified' );

		add_action( 'create_term', [ $this, 'add_term_created_timestamp' ], 10, 3 );
		add_action( 'edit_terms', [ $this, 'add_term_modified_timestamp' ], 10, 2 );
		add_action( 'graphql_generate_schema', [ $this, 'graphql_support' ] );
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
		 * Save the term_meta to the specified mete_key as a unique value
		 */
		add_term_meta( $term_id, $this->created_meta_key, $this->prepare_meta_value( $term_id, $taxonomy ), true );
	}

	/**
	 * Adds a timestamp to a term's term_meta when a term is edited
	 */
	public function add_term_modified_timestamp( $term_id, $taxonomy ) {

		/**
		 * Save the term_meta to the specified mete_key as a non-unique value (allowing every modification to be saved)
		 */
		add_term_meta( $term_id, $this->modified_meta_key, $this->prepare_meta_value( $term_id, $taxonomy ), false );
	}

	/**
	 * This prepares the meta that will be saved in the term_meta whenever a term is updated or created
	 *
	 * @param int $term_id The ID of the term being updated or created
	 * @param string $taxonomy The name of the taxonomy the term being edited or created belongs to
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
		 * Get the allowed WPGraphQL taxonomies
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();

		/**
		 * Filter the fields of each of the allowed taxonomies
		 */
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach( $allowed_taxonomies as $taxonomy ) {
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
			'type' => self::term_updated_type(),
			'description' => __( 'Details on when the term was created', 'wp-term-timestamps' ),
			'resolve' => function( \WP_Term $term, array $args, $context, $info ) {
				$created_meta = get_term_meta( $term->term_id, $this->created_meta_key, true );
				return ! empty( $created_meta ) ? $created_meta : null;
			},
		];

		/**
		 * This allows the key of the created field to be filtered to avoid conflicts with potential existing
		 * GraphQL fields that already use the "modified" key
		 */
		$modified_graphql_field = apply_filters( 'wp_term_timestamps_graphql_modified_field_key', 'modified' );

		/**
		 * Add the "modified" (or filtered name) field to the Schema for the TermObjectType
		 */
		$fields[ $modified_graphql_field ] = [
			'type' => \WPGraphQL\Types::list_of( self::term_updated_type() ),
			'description' => __( 'Details on when the term was created', 'wp-term-timestamps' ),
			'resolve' => function( \WP_Term $term, array $args, $context, $info ) {
				$created_meta = get_term_meta( $term->term_id, $this->modified_meta_key, false );
				return ! empty( $created_meta ) && is_array( $created_meta ) ? array_reverse( $created_meta ) : null;
			},
		];

		return $fields;
	}

	/**
	 * @return null|\WPGraphQL\Type\WPObjectType
	 */
	public static function term_updated_type() {

		if ( null === self::$term_updated_type ) {

			self::$term_updated_type = new \WPGraphQL\Type\WPObjectType([
				'name' => 'termUpdated',
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
			]);
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