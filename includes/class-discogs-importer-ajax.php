<?php
/**
 * Discogs Importer AJAX Handler.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Discogs_Importer_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register AJAX Actions.
		add_action( 'wp_ajax_discogs_importer_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_discogs_importer_import', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_discogs_importer_collection', array( $this, 'ajax_collection' ) );
	}

	/**
	 * Handle AJAX Search.
	 */
	public function ajax_search() {
		// Verify Nonce.
		check_ajax_referer( 'discogs_importer_nonce', 'nonce' );

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissions check failed.', 'discogs-importer' ) );
		}

		$query        = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		$search_field = isset( $_POST['search_field'] ) ? sanitize_text_field( wp_unslash( $_POST['search_field'] ) ) : 'q';
		$page         = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		if ( empty( $query ) ) {
			wp_send_json_error( __( 'Please enter a search query.', 'discogs-importer' ) );
		}

		// Check Token.
		$token = get_option( 'discogs_importer_token', '' );
		if ( empty( $token ) ) {
			wp_send_json_error( __( 'API Token is missing. Please configure it in Settings.', 'discogs-importer' ) );
		}

		// Handle search by field.
		if ( 'release_id' === $search_field ) {
			// Search for specific Release ID.
			$result = Discogs_Importer_API::get_release( $query );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			}

			// Format single release details to look like a search result for JS compatibility.
			$mock_search_results = array(
				'pagination' => array(
					'page'     => 1,
					'pages'    => 1,
					'per_page' => 1,
					'items'    => 1,
				),
				'results'    => array(
					array(
						'id'          => $result['id'],
						'title'       => $result['title'],
						'thumb'       => isset( $result['images'][0]['uri150'] ) ? $result['images'][0]['uri150'] : '',
						'cover_image' => isset( $result['images'][0]['resource_url'] ) ? $result['images'][0]['resource_url'] : '',
						'year'        => isset( $result['year'] ) ? $result['year'] : '',
						'country'     => isset( $result['country'] ) ? $result['country'] : '',
						'genre'       => isset( $result['genres'] ) ? $result['genres'] : array(),
						'style'       => isset( $result['styles'] ) ? $result['styles'] : array(),
						'format'      => isset( $result['formats'][0]['name'] ) ? array( $result['formats'][0]['name'] ) : array(),
						'label'       => isset( $result['labels'][0]['name'] ) ? array( $result['labels'][0]['name'] ) : array(),
						'catno'       => isset( $result['labels'][0]['catno'] ) ? $result['labels'][0]['catno'] : '',
					)
				),
			);

			wp_send_json_success( $mock_search_results );
		}

		// General searches.
		$args = array(
			'page'     => $page,
			'per_page' => 12,
		);

		if ( 'q' === $search_field ) {
			$args['q'] = $query;
		} else {
			// Map specific search fields.
			$args[ $search_field ] = $query;
		}

		$results = Discogs_Importer_API::search( $args );

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( $results->get_error_message() );
		}

		wp_send_json_success( $results );
	}

	/**
	 * Handle AJAX Collection Load.
	 */
	public function ajax_collection() {
		check_ajax_referer( 'discogs_importer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissions check failed.', 'discogs-importer' ) );
		}

		$username = get_option( 'discogs_importer_username', '' );
		if ( empty( $username ) ) {
			wp_send_json_error( __( 'Username is missing. Please configure it in Settings.', 'discogs-importer' ) );
		}

		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		$token = get_option( 'discogs_importer_token', '' );
		if ( empty( $token ) ) {
			wp_send_json_error( __( 'API Token is missing. Please configure it in Settings.', 'discogs-importer' ) );
		}

		$results = Discogs_Importer_API::get_collection( $username, $page, 12 );

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( $results->get_error_message() );
		}

		// Transform collection response format to match search results format.
		$transformed_results = array();
		if ( ! empty( $results['releases'] ) && is_array( $results['releases'] ) ) {
			foreach ( $results['releases'] as $item ) {
				$info = isset( $item['basic_information'] ) ? $item['basic_information'] : array();
				if ( empty( $info ) ) {
					continue;
				}

				// Format artist strings.
				$artists = array();
				if ( ! empty( $info['artists'] ) && is_array( $info['artists'] ) ) {
					foreach ( $info['artists'] as $art ) {
						$artists[] = $art['name'];
					}
				}
				$artist_title = ! empty( $artists ) ? implode( ' - ', $artists ) : 'Unknown Artist';

				$transformed_results[] = array(
					'id'          => isset( $info['id'] ) ? $info['id'] : 0,
					'title'       => $artist_title . ' - ' . ( isset( $info['title'] ) ? $info['title'] : 'Untitled' ),
					'thumb'       => isset( $info['thumb'] ) ? $info['thumb'] : '',
					'cover_image' => isset( $info['cover_image'] ) ? $info['cover_image'] : '',
					'year'        => isset( $info['year'] ) ? $info['year'] : '',
					'country'     => isset( $info['country'] ) ? $info['country'] : 'N/A',
					'genre'       => isset( $info['genres'] ) ? $info['genres'] : array(),
					'style'       => isset( $info['styles'] ) ? $info['styles'] : array(),
					'format'      => isset( $info['formats'][0]['name'] ) ? array( $info['formats'][0]['name'] ) : array(),
					'label'       => isset( $info['labels'][0]['name'] ) ? array( $info['labels'][0]['name'] ) : array(),
					'catno'       => isset( $info['labels'][0]['catno'] ) ? $info['labels'][0]['catno'] : '',
				);
			}
		}

		$response = array(
			'pagination' => isset( $results['pagination'] ) ? $results['pagination'] : array(),
			'results'    => $transformed_results,
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle AJAX Import.
	 */
	public function ajax_import() {
		// Verify Nonce.
		check_ajax_referer( 'discogs_importer_nonce', 'nonce' );

		// Check capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissions check failed.', 'discogs-importer' ) );
		}

		$release_id = isset( $_POST['release_id'] ) ? absint( $_POST['release_id'] ) : 0;
		if ( empty( $release_id ) ) {
			wp_send_json_error( __( 'Invalid Release ID.', 'discogs-importer' ) );
		}

		// Fetch full release details.
		$release = Discogs_Importer_API::get_release( $release_id );
		if ( is_wp_error( $release ) ) {
			wp_send_json_error( sprintf( __( 'Failed to fetch release from Discogs: %s', 'discogs-importer' ), $release->get_error_message() ) );
		}

		// Get configurations.
		$import_type     = get_option( 'discogs_importer_import_type', 'cpt' );
		$post_status     = get_option( 'discogs_importer_post_status', 'draft' );
		$download_images = get_option( 'discogs_importer_download_images', '1' );
		$default_price   = get_option( 'discogs_importer_default_price', '' );

		$is_wc_active = class_exists( 'WooCommerce' );
		$target_post_type = ( 'woocommerce' === $import_type && $is_wc_active ) ? 'product' : 'discogs_record';

		// Ensure taxonomy is registered early for tracklist links.
		if ( 'product' === $target_post_type ) {
			$this->ensure_attribute_taxonomy( 'artist', __( 'Artist', 'discogs-importer' ) );
		}

		// Check if already imported.
		$existing = new WP_Query( array(
			'post_type'      => $target_post_type,
			'posts_per_page' => 1,
			'meta_key'       => '_discogs_release_id',
			'meta_value'     => $release_id,
			'post_status'    => 'any',
		) );

		if ( $existing->have_posts() ) {
			$existing_post = $existing->posts[0];
			$edit_link = get_edit_post_link( $existing_post->ID );
			wp_send_json_error( sprintf(
				__( 'This release was already imported as <a href="%s" target="_blank">%s</a>.', 'discogs-importer' ),
				esc_url( $edit_link ),
				esc_html( $existing_post->post_title )
			) );
		}

		// Parse primary artist names.
		$primary_artists = array();
		if ( ! empty( $release['artists'] ) && is_array( $release['artists'] ) ) {
			foreach ( $release['artists'] as $art ) {
				$primary_artists[] = $this->clean_artist_name( $art['name'] );
			}
		}
		$artist_name = ! empty( $primary_artists ) ? implode( ', ', $primary_artists ) : 'Unknown Artist';

		// Parse ALL artists (including track-level and extra/collaborator artists).
		$all_artists = $this->extract_all_artists( $release );

		// Parse label names.
		$labels = array();
		$catno  = '';
		if ( ! empty( $release['labels'] ) && is_array( $release['labels'] ) ) {
			foreach ( $release['labels'] as $lbl ) {
				$labels[] = preg_replace( '/\s\(\d+\)$/', '', $lbl['name'] );
				if ( empty( $catno ) && ! empty( $lbl['catno'] ) ) {
					$catno = $lbl['catno'];
				}
			}
		}
		$label_name = ! empty( $labels ) ? implode( ', ', $labels ) : 'Unknown Label';

		// Parse Format.
		$formats = array();
		if ( ! empty( $release['formats'] ) && is_array( $release['formats'] ) ) {
			foreach ( $release['formats'] as $fmt ) {
				$format_str = $fmt['name'];
				if ( ! empty( $fmt['descriptions'] ) && is_array( $fmt['descriptions'] ) ) {
					$format_str .= ' (' . implode( ', ', $fmt['descriptions'] ) . ')';
				}
				$formats[] = $format_str;
			}
		}
		$format_name = ! empty( $formats ) ? implode( ', ', $formats ) : 'Vinyl';

		// Format Title.
		$post_title = $artist_name . ' - ' . $release['title'];

		// Construct Tracklist HTML.
		$tracklist_html = '';
		if ( ! empty( $release['tracklist'] ) && is_array( $release['tracklist'] ) ) {
			$tracklist_html .= '<h3 class="discogs-tracklist-heading">' . esc_html__( 'Tracklist', 'discogs-importer' ) . '</h3>';
			$tracklist_html .= '<table class="discogs-tracklist-table" style="width:100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px;">';
			$tracklist_html .= '<thead><tr style="border-bottom: 2px solid #eee; text-align: left;"><th style="padding: 8px;">#</th><th style="padding: 8px;">Title</th><th style="padding: 8px; text-align: right;">Duration</th></tr></thead>';
			$tracklist_html .= '<tbody>';
			foreach ( $release['tracklist'] as $track ) {
				if ( isset( $track['type_'] ) && 'heading' === $track['type_'] ) {
					$tracklist_html .= '<tr class="track-heading-row" style="background-color: #f9f9f9;"><td colspan="3" style="padding: 8px; font-weight: bold; border-bottom: 1px solid #eee;">' . esc_html( $track['title'] ) . '</td></tr>';
				} else {
					// Parse track artists if available.
					$track_artists = array();
					if ( ! empty( $track['artists'] ) && is_array( $track['artists'] ) ) {
						foreach ( $track['artists'] as $art ) {
							$clean_name = $this->clean_artist_name( $art['name'] );
							$taxonomy   = ( 'product' === $target_post_type ) ? 'pa_artist' : 'record_artist';

							// Ensure term exists in taxonomy.
							$term = get_term_by( 'name', $clean_name, $taxonomy );
							if ( ! $term ) {
								$term_info = wp_insert_term( $clean_name, $taxonomy );
								if ( ! is_wp_error( $term_info ) && isset( $term_info['term_id'] ) ) {
									$term = get_term( $term_info['term_id'], $taxonomy );
								}
							}

							$link = '';
							if ( $term && ! is_wp_error( $term ) ) {
								$term_link = get_term_link( $term, $taxonomy );
								if ( ! is_wp_error( $term_link ) ) {
									$link = $term_link;
								}
							}

							if ( ! empty( $link ) ) {
								$track_artists[] = '<a href="' . esc_url( $link ) . '" class="track-artist-link" style="color: var(--discogs-primary, #10b981); text-decoration: none; font-weight: 600;">' . esc_html( $clean_name ) . '</a>';
							} else {
								$track_artists[] = '<span class="track-artist-name" style="font-weight: 600;">' . esc_html( $clean_name ) . '</span>';
							}
						}
					}
					$track_artist_str = ! empty( $track_artists ) ? implode( ' & ', $track_artists ) : '';

					// Format the display title.
					$display_title = esc_html( $track['title'] );
					if ( ! empty( $track_artist_str ) ) {
						$display_title = '<span class="track-artist">' . $track_artist_str . '</span> &ndash; ' . $display_title;
					}

					$tracklist_html .= '<tr style="border-bottom: 1px solid #eee;">';
					$tracklist_html .= '<td style="padding: 8px; color: #666; width: 10%;">' . esc_html( $track['position'] ) . '</td>';
					$tracklist_html .= '<td style="padding: 8px;">' . $display_title . '</td>';
					$tracklist_html .= '<td style="padding: 8px; text-align: right; color: #666; width: 15%;">' . esc_html( $track['duration'] ) . '</td>';
					$tracklist_html .= '</tr>';
				}
			}
			$tracklist_html .= '</tbody></table>';
		}

		// Construct Description.
		$post_content = '';
		if ( ! empty( $release['notes'] ) ) {
			$post_content .= '<div class="discogs-notes">';
			$post_content .= '<h3 class="discogs-notes-heading">' . esc_html__( 'Notes', 'discogs-importer' ) . '</h3>';
			$post_content .= '<p>' . nl2br( esc_html( $release['notes'] ) ) . '</p>';
			$post_content .= '</div>';
		}
		$post_content .= $tracklist_html;

		// Create Post.
		$post_data = array(
			'post_title'   => $post_title,
			'post_content' => $post_content,
			'post_status'  => $post_status,
			'post_type'    => $target_post_type,
		);

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_send_json_error( __( 'Failed to create database entry.', 'discogs-importer' ) );
		}

		// Save Common Post Meta.
		update_post_meta( $post_id, '_discogs_release_id', $release_id );
		update_post_meta( $post_id, '_discogs_artist', $artist_name );
		update_post_meta( $post_id, '_discogs_label', $label_name );
		update_post_meta( $post_id, '_discogs_year', isset( $release['year'] ) ? $release['year'] : '' );
		update_post_meta( $post_id, '_discogs_country', isset( $release['country'] ) ? $release['country'] : '' );
		update_post_meta( $post_id, '_discogs_catalog_number', $catno );
		update_post_meta( $post_id, '_discogs_format', $format_name );
		update_post_meta( $post_id, '_discogs_tracklist', wp_json_encode( $release['tracklist'] ) );
		update_post_meta( $post_id, '_discogs_raw_data', wp_json_encode( $release ) );

		// Process Taxonomies or Product Attributes.
		if ( 'product' === $target_post_type ) {
			// Handle WooCommerce Product Meta and Attributes.
			$this->process_woocommerce_product( $post_id, $release, $all_artists, $label_name, $catno, $format_name, $default_price );
		} else {
			// Handle Custom Post Type Taxonomies.
			$this->process_custom_post_taxonomies( $post_id, $release, $all_artists, $labels, $formats );
		}

		// Sideload Image.
		if ( '1' === $download_images && ! empty( $release['images'] ) && is_array( $release['images'] ) ) {
			// Find primary image, fallback to first image.
			$image_url = '';
			foreach ( $release['images'] as $img ) {
				if ( isset( $img['type'] ) && 'primary' === $img['type'] ) {
					$image_url = $img['resource_url'];
					break;
				}
			}
			if ( empty( $image_url ) ) {
				$image_url = $release['images'][0]['resource_url'];
			}

			if ( ! empty( $image_url ) ) {
				$attachment_id = $this->sideload_discogs_image( $image_url, $post_id, $post_title );
				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}
		}

		$edit_link = get_edit_post_link( $post_id );
		$view_link = get_permalink( $post_id );

		wp_send_json_success( array(
			'post_id'   => $post_id,
			'edit_url'  => $edit_link,
			'view_url'  => $view_link,
			'title'     => $post_title,
			'message'   => __( 'Release imported successfully!', 'discogs-importer' )
		) );
	}

	/**
	 * Setup and register WooCommerce global attributes and assign values.
	 */
	private function process_woocommerce_product( $product_id, $release, $artists, $label, $catno, $format, $default_price ) {
		// Set WooCommerce meta.
		update_post_meta( $product_id, '_visibility', 'visible' );
		update_post_meta( $product_id, '_stock_status', 'instock' );
		update_post_meta( $product_id, '_manage_stock', 'no' );
		update_post_meta( $product_id, '_virtual', 'no' );
		update_post_meta( $product_id, '_downloadable', 'no' );
		
		if ( ! empty( $default_price ) ) {
			update_post_meta( $product_id, '_price', $default_price );
			update_post_meta( $product_id, '_regular_price', $default_price );
		}

		if ( ! empty( $catno ) ) {
			update_post_meta( $product_id, '_sku', sanitize_text_field( $catno ) );
		} else {
			update_post_meta( $product_id, '_sku', 'DSCG-' . $release['id'] );
		}

		// We will map global product attributes.
		$attributes_map = array(
			'artist'  => array( 'name' => __( 'Artist', 'discogs-importer' ), 'values' => array() ),
			'label'   => array( 'name' => __( 'Label', 'discogs-importer' ), 'values' => array() ),
			'year'    => array( 'name' => __( 'Year', 'discogs-importer' ), 'values' => array() ),
			'genre'   => array( 'name' => __( 'Genre', 'discogs-importer' ), 'values' => array() ),
			'style'   => array( 'name' => __( 'Style', 'discogs-importer' ), 'values' => array() ),
			'format'  => array( 'name' => __( 'Format', 'discogs-importer' ), 'values' => array() ),
			'country' => array( 'name' => __( 'Country', 'discogs-importer' ), 'values' => array() ),
		);

		// Extract Values.
		$attributes_map['artist']['values'] = $artists;
		if ( ! empty( $release['labels'] ) && is_array( $release['labels'] ) ) {
			foreach ( $release['labels'] as $lbl ) {
				$attributes_map['label']['values'][] = preg_replace( '/\s\(\d+\)$/', '', $lbl['name'] );
			}
		}
		if ( ! empty( $release['year'] ) ) {
			$attributes_map['year']['values'][] = $release['year'];
		}
		if ( ! empty( $release['genres'] ) && is_array( $release['genres'] ) ) {
			$attributes_map['genre']['values'] = $release['genres'];
		}
		if ( ! empty( $release['styles'] ) && is_array( $release['styles'] ) ) {
			$attributes_map['style']['values'] = $release['styles'];
		}
		if ( ! empty( $release['formats'] ) && is_array( $release['formats'] ) ) {
			foreach ( $release['formats'] as $fmt ) {
				$attributes_map['format']['values'][] = $fmt['name'];
			}
		}
		if ( ! empty( $release['country'] ) ) {
			$attributes_map['country']['values'][] = $release['country'];
		}

		$product_attributes = array();
		$position = 0;

		foreach ( $attributes_map as $slug => $attr ) {
			if ( empty( $attr['values'] ) ) {
				continue;
			}

			$taxonomy = 'pa_' . $slug;

			// Register attribute if it doesn't exist.
			if ( function_exists( 'wc_create_attribute' ) ) {
				$attribute_id = wc_attribute_taxonomy_id_by_name( $slug );
				if ( ! $attribute_id ) {
					wc_create_attribute( array(
						'name'         => $attr['name'],
						'slug'         => $slug,
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => true,
					) );
				}
			}

			// Ensure taxonomy is registered in current process.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'hierarchical' => false,
						'label'        => $attr['name'],
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => array( 'slug' => 'pa-' . $slug ),
					)
				);
			}

			// Add terms to taxonomy.
			wp_set_object_terms( $product_id, $attr['values'], $taxonomy, false );

			// Build the product attribute metadata array.
			$product_attributes[ $taxonomy ] = array(
				'name'         => $taxonomy,
				'value'        => '',
				'position'     => $position,
				'is_visible'   => 1,
				'is_variation' => 0,
				'is_taxonomy'  => 1,
			);
			$position++;
		}

		// Update product attributes meta.
		if ( ! empty( $product_attributes ) ) {
			update_post_meta( $product_id, '_product_attributes', $product_attributes );
		}

		// Attempt to set WooCommerce Product Category.
		// If WooCommerce categories exist, add a "Vinyl" or first "Genre" category.
		if ( ! empty( $release['genres'] ) && is_array( $release['genres'] ) ) {
			$category_name = $release['genres'][0];
			$term = get_term_by( 'name', $category_name, 'product_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( $category_name, 'product_cat' );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$cat_id = is_array( $term ) ? $term['term_id'] : $term->term_id;
				wp_set_object_terms( $product_id, array( $cat_id ), 'product_cat' );
			}
		}
	}

	/**
	 * Setup taxonomies for standard Custom Post Type.
	 */
	private function process_custom_post_taxonomies( $post_id, $release, $artists, $labels, $formats ) {
		// Set Artists.
		wp_set_object_terms( $post_id, $artists, 'record_artist', false );

		// Set Labels.
		wp_set_object_terms( $post_id, $labels, 'record_label', false );

		// Set Genres.
		if ( ! empty( $release['genres'] ) && is_array( $release['genres'] ) ) {
			wp_set_object_terms( $post_id, $release['genres'], 'record_genre', false );
		}

		// Set Styles.
		if ( ! empty( $release['styles'] ) && is_array( $release['styles'] ) ) {
			wp_set_object_terms( $post_id, $release['styles'], 'record_style', false );
		}

		// Set Formats.
		wp_set_object_terms( $post_id, $formats, 'record_format', false );
	}

	/**
	 * Custom image sideload function to fetch image with correct headers.
	 * Returns attachment ID on success, or false/WP_Error on failure.
	 */
	private function sideload_discogs_image( $image_url, $post_id, $title ) {
		if ( empty( $image_url ) ) {
			return false;
		}

		$token = get_option( 'discogs_importer_token', '' );
		$args = array(
			'headers' => array(
				'User-Agent'    => 'DiscogsRecordImporterWordPressPlugin/' . DISCOGS_IMPORTER_VERSION . ' +http://localhost',
				'Authorization' => 'Discogs token=' . trim( $token ),
			),
			'timeout' => 20,
		);

		// Download the image content.
		$response = wp_remote_get( $image_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error( 'image_download_failed', sprintf( __( 'Image download returned HTTP %d', 'discogs-importer' ), $response_code ) );
		}

		$image_data = wp_remote_retrieve_body( $response );
		$filename   = sanitize_file_name( $title . '-' . basename( parse_url( $image_url, PHP_URL_PATH ) ) );
		
		// If filename has no extension or is generic, append JPG.
		$file_type = wp_remote_retrieve_header( $response, 'content-type' );
		$extension = 'jpg';
		if ( 'image/png' === $file_type ) {
			$extension = 'png';
		} elseif ( 'image/gif' === $file_type ) {
			$extension = 'gif';
		} elseif ( 'image/webp' === $file_type ) {
			$extension = 'webp';
		}

		if ( ! preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $filename ) ) {
			$filename .= '.' . $extension;
		}

		// Upload the file content into WP Uploads directory.
		$upload = wp_upload_bits( $filename, null, $image_data );

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'image_upload_bits_error', $upload['error'] );
		}

		// Insert into Media Library.
		$attachment_data = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => $title . ' - Cover',
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $upload['file'], $post_id );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error( 'attachment_creation_failed', __( 'Could not insert image into media library.', 'discogs-importer' ) );
		}

		// Generate image metadata (sizes, etc).
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attach_metadata );

		return $attachment_id;
	}

	/**
	 * Extracts all unique artists from the release object (main, track artists, extra artists).
	 *
	 * @param array $release Release details.
	 * @return array List of unique artist names.
	 */
	private function extract_all_artists( $release ) {
		$artists = array();

		// 1. Release Level Artists
		if ( ! empty( $release['artists'] ) && is_array( $release['artists'] ) ) {
			foreach ( $release['artists'] as $art ) {
				if ( ! empty( $art['name'] ) ) {
					$artists[] = $this->clean_artist_name( $art['name'] );
				}
			}
		}

		// 2. Release Level Extra Artists
		if ( ! empty( $release['extraartists'] ) && is_array( $release['extraartists'] ) ) {
			foreach ( $release['extraartists'] as $art ) {
				if ( ! empty( $art['name'] ) ) {
					$artists[] = $this->clean_artist_name( $art['name'] );
				}
			}
		}

		// 3. Track Level Artists & Extra Artists
		if ( ! empty( $release['tracklist'] ) && is_array( $release['tracklist'] ) ) {
			foreach ( $release['tracklist'] as $track ) {
				// Track main artists
				if ( ! empty( $track['artists'] ) && is_array( $track['artists'] ) ) {
					foreach ( $track['artists'] as $art ) {
						if ( ! empty( $art['name'] ) ) {
							$artists[] = $this->clean_artist_name( $art['name'] );
						}
					}
				}
				// Track extra artists
				if ( ! empty( $track['extraartists'] ) && is_array( $track['extraartists'] ) ) {
					foreach ( $track['extraartists'] as $art ) {
						if ( ! empty( $art['name'] ) ) {
							$artists[] = $this->clean_artist_name( $art['name'] );
						}
					}
				}
			}
		}

		// Filters: Remove duplicates and empty/Various entries.
		$artists = array_unique( $artists );
		$artists = array_filter( $artists, function( $name ) {
			return ! empty( $name ) && strcasecmp( $name, 'Various' ) !== 0;
		});

		// Fallback to main release artists if no other specific artists found.
		if ( empty( $artists ) && ! empty( $release['artists'] ) && is_array( $release['artists'] ) ) {
			foreach ( $release['artists'] as $art ) {
				if ( ! empty( $art['name'] ) ) {
					$artists[] = $this->clean_artist_name( $art['name'] );
				}
			}
		}

		return array_values( $artists );
	}

	/**
	 * Cleans Discogs artist name by removing parenthetical numbers (e.g. "Pink Floyd (2)" -> "Pink Floyd").
	 *
	 * @param string $name Artist name.
	 * @return string
	 */
	private function clean_artist_name( $name ) {
		return preg_replace( '/\s\(\d+\)$/', '', $name );
	}

	/**
	 * Ensures a WooCommerce global attribute taxonomy is registered and exists.
	 *
	 * @param string $slug Attribute slug.
	 * @param string $name Attribute name.
	 */
	private function ensure_attribute_taxonomy( $slug, $name ) {
		$taxonomy = 'pa_' . $slug;

		if ( function_exists( 'wc_create_attribute' ) ) {
			$attribute_id = wc_attribute_taxonomy_id_by_name( $slug );
			if ( ! $attribute_id ) {
				wc_create_attribute( array(
					'name'         => $name,
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => true,
				) );
			}
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				array( 'product' ),
				array(
					'hierarchical' => false,
					'label'        => $name,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => array( 'slug' => 'pa-' . $slug ),
				)
			);
		}
	}
}
