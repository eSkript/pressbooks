<?php

namespace Pressbooks\Api\Endpoints\Controller;

use Pressbooks\Book;

class Toc extends \WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'pressbooks/v2';
		$this->rest_base = 'toc';
	}

	/**
	 *  Registers routes for TOC
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods' => \WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args' => $this->get_collection_params(),
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );
	}

	/**
	 * Retrieves TOC schema, conforming to JSON Schema
	 *
	 * @return array
	 */
	public function get_item_schema() {

		// TODO: context => [ view, edit, embed ], readonly => true, ... ?

		$item = [
			'id' => [
				'description' => __( 'Unique identifier for the object.' ),
				'type' => 'integer',
			],
			'title' => [
				'description' => __( 'The title for the object.' ),
				'type' => 'string',
			],
			'slug' => [
				'description' => __( 'An alphanumeric identifier for the object unique to its type.' ),
				'type' => 'string',
			],
			'author' => [
				'description' => __( 'The ID for the author of the object.' ),
				'type' => 'integer',
			],
			'comment_count' => [
				'description' => __( 'Comment count', 'pressbooks' ),
				'type' => 'integer',
			],
			'menu_order' => [
				'description' => __( 'The order of the object in relation to other object of its type.' ),
				'type' => 'integer',
			],
			'status' => [
				'description' => __( 'A named status for the object.' ),
				'type' => 'string',
				'enum' => array_keys( get_post_stati( [ 'internal' => false ] ) ),
			],
			'export' => [
				'description' => __( 'Include in exports.', 'pressbooks' ),
				'type' => 'boolean',
			],
			'has_post_content' => [
				'description' => __( 'Has post content, the content field is not empty.', 'pressbooks' ),
				'type' => 'boolean',
			],
		];

		$schema = [
			'$schema' => 'http://json-schema.org/schema#',
			'title' => 'toc',
			'type' => 'object',
			'properties' => [
				'front-matter' => [
					'description' => __( 'Front Matter', 'pressbooks' ),
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => $item,
					],
				],
				'part' => [
					'description' => __( 'Part', 'pressbooks' ),
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => array_merge( $item, [
							'chapter' => [
								'description' => __( 'Chapter', 'pressbooks' ),
								'type' => 'array',
								'items' => [
									'type' => 'object',
									'properties' => $item,
								],
							],
						] ),
					],
				],
				'back-matter' => [
					'description' => __( 'Back Matter', 'pressbooks' ),
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => $item,
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * @param  \WP_REST_Request $request Full details about the request.
	 *
	 * @return bool True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {

		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		if ( get_option( 'blog_public' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param \WP_REST_Request $request Full data about the request.
	 *
	 * @return \WP_Error|\WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {

		$struct = Book::getBookStructure();
		unset( $struct['__order'], $struct['__export_lookup'] );
		$struct = $this->fixBookStructure( $struct, current_user_can( 'edit_posts' ) );

		$response = rest_ensure_response( $struct );
		$links = [ 'self' => [ 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ] ];
		$response->add_links( $links );

		return $response;
	}

	/**
	 * @param array $book_structure
	 * @param bool $has_permission
	 *
	 * @return array
	 */
	private function fixBookStructure( array $book_structure, $has_permission ) {

		$toc = [];

		$replacement_keys = [
			'ID' => 'id',
			'post_title' => 'title',
			'post_name' => 'slug',
			'post_author' => 'author',
			'post_status' => 'status',
		];

		$toc['front-matter'] = $this->fixFrontMatterStructure( $book_structure, $has_permission, $replacement_keys );
		$toc['part'] = $this->fixPartChapterStructure( $book_structure, $has_permission, $replacement_keys );
		$toc['back-matter'] = $this->fixBackMatterStructure( $book_structure, $has_permission, $replacement_keys );

		return $toc;
	}

	/**
	 * @param array $book_structure
	 * @param $has_permission
	 * @param array $replacement_keys
	 *
	 * @return array
	 */
	private function fixFrontMatterStructure( array $book_structure, $has_permission, array $replacement_keys ) {

		$base = 'front-matter';
		$rest_url = rest_url( sprintf( '%s/%s', $this->namespace, $base ) );

		// Front-matter
		$front_matter = [];
		foreach ( $book_structure['front-matter'] as $old_fm ) {
			if ( $has_permission || 'publish' === $old_fm['post_status'] ) {
				$new_fm = [];
				foreach ( $old_fm as $old_key => $val ) {
					$new_key = strtr( $old_key, $replacement_keys );
					$new_fm[ $new_key ] = $val;
				}
				$new_fm['_links'] = [
					'up' => [ 'href' => trailingslashit( $rest_url ) . $new_fm['id'] ],
					'collection' => [ 'href' => $rest_url ],
					'about' => [ 'href' => rest_url( 'wp/v2/types/' . $base ) ],
				];
				$front_matter[] = $new_fm;
			}
		}

		return $front_matter;
	}

	/**
	 * @param array $book_structure
	 * @param $has_permission
	 * @param array $replacement_keys
	 *
	 * @return array
	 */
	private function fixPartChapterStructure( array $book_structure, $has_permission, array $replacement_keys ) {

		$part_base = 'parts';
		$part_rest_url = rest_url( sprintf( '%s/%s', $this->namespace, $part_base ) );
		$chapter_base = 'chapters';
		$chapter_rest_url = rest_url( sprintf( '%s/%s', $this->namespace, $chapter_base ) );

		$part = [];
		foreach ( $book_structure['part'] as $old_p ) {
			$new_p = [];
			foreach ( $old_p as $old_key => $val ) {
				$new_key = strtr( $old_key, $replacement_keys );
				$new_p[ $new_key ] = $val;
			}
			$chapters = [];
			foreach ( $new_p['chapters'] as $old_ch ) {
				if ( $has_permission || 'publish' === $old_ch['post_status'] ) {
					$new_ch = [];
					foreach ( $old_ch as $old_key => $val ) {
						$new_key = strtr( $old_key, $replacement_keys );
						$new_ch[ $new_key ] = $val;
					}
					$new_ch['_links'] = [
						'up' => [ 'href' => trailingslashit( $chapter_rest_url ) . $new_ch['id'] ],
						'collection' => [ 'href' => $chapter_rest_url ],
						'about' => [ 'href' => rest_url( 'wp/v2/types/' . $chapter_base ) ],
					];
					$chapters[] = $new_ch;
				}
			}
			$new_p['chapters'] = $chapters;
			$new_p['_links'] = [
				'up' => [ 'href' => trailingslashit( $part_rest_url ) . $new_p['id'] ],
				'collection' => [ 'href' => $part_rest_url ],
				'about' => [ 'href' => rest_url( 'wp/v2/types/' . $part_base ) ],
			];
			$part[] = $new_p;
		}

		return $part;
	}

	/**
	 * @param array $book_structure
	 * @param $has_permission
	 * @param array $replacement_keys
	 *
	 * @return array
	 */
	private function fixBackMatterStructure( array $book_structure, $has_permission, array $replacement_keys ) {

		$base = 'back-matter';
		$rest_url = rest_url( sprintf( '%s/%s', $this->namespace, $base ) );

		$back_matter = [];
		foreach ( $book_structure['back-matter'] as $old_bm ) {
			if ( $has_permission || 'publish' === $old_bm['post_status'] ) {
				$new_bm = [];
				foreach ( $old_bm as $old_key => $val ) {
					$new_key = strtr( $old_key, $replacement_keys );
					$new_bm[ $new_key ] = $val;
				}
				$new_bm['_links'] = [
					'up' => [ 'href' => trailingslashit( $rest_url ) . $new_bm['id'] ],
					'collection' => [ 'href' => $rest_url ],
					'about' => [ 'href' => rest_url( 'wp/v2/types/' . $base ) ],
				];
				$back_matter[] = $new_bm;
			}
		}

		return $back_matter;
	}

}