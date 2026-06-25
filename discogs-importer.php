<?php

/**
 * Plugin Name: Discogs Record Importer
 * Description: Search and import music records from the Discogs API directly into WooCommerce Products or a custom post type complete with images, tracklists, and metadata.
 * Version: 1.0.0
 * Author: Rishabh Gupta
 * License: GPL2 or later
 * Text Domain: discogs-importer
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
	exit;
}

// Define Constants.
define('DISCOGS_IMPORTER_VERSION', '1.0.0');
define('DISCOGS_IMPORTER_PATH', plugin_dir_path(__FILE__));
define('DISCOGS_IMPORTER_URL', plugin_dir_url(__FILE__));
define('DISCOGS_IMPORTER_BASENAME', plugin_basename(__FILE__));

/**
 * Register custom post type and taxonomies for non-WooCommerce setups.
 */
function discogs_importer_register_post_type()
{
	// Register Custom Post Type "discogs_record"
	$labels = array(
		'name' => _x('Music Records', 'post type general name', 'discogs-importer'),
		'singular_name' => _x('Music Record', 'post type singular name', 'discogs-importer'),
		'menu_name' => _x('Music Records', 'admin menu', 'discogs-importer'),
		'name_admin_bar' => _x('Music Record', 'add new on admin bar', 'discogs-importer'),
		'add_new' => _x('Add New', 'record', 'discogs-importer'),
		'add_new_item' => __('Add New Record', 'discogs-importer'),
		'new_item' => __('New Record', 'discogs-importer'),
		'edit_item' => __('Edit Record', 'discogs-importer'),
		'view_item' => __('View Record', 'discogs-importer'),
		'all_items' => __('All Records', 'discogs-importer'),
		'search_items' => __('Search Records', 'discogs-importer'),
		'parent_item_colon' => __('Parent Records:', 'discogs-importer'),
		'not_found' => __('No records found.', 'discogs-importer'),
		'not_found_in_trash' => __('No records found in Trash.', 'discogs-importer')
	);

	$args = array(
		'labels' => $labels,
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'query_var' => true,
		'rewrite' => array('slug' => 'records'),
		'capability_type' => 'post',
		'has_archive' => true,
		'hierarchical' => false,
		'menu_position' => 25,
		'menu_icon' => 'dashicons-format-audio',
		'show_in_rest' => true,  // Enable Block Editor
		'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields')
	);

	register_post_type('discogs_record', $args);

	// Register Taxonomy: Record Artist
	register_taxonomy('record_artist', 'discogs_record', array(
		'label' => __('Artists', 'discogs-importer'),
		'rewrite' => array('slug' => 'record-artist'),
		'hierarchical' => false,
		'show_in_rest' => true,
	));

	// Register Taxonomy: Record Label
	register_taxonomy('record_label', 'discogs_record', array(
		'label' => __('Labels', 'discogs-importer'),
		'rewrite' => array('slug' => 'record-label'),
		'hierarchical' => false,
		'show_in_rest' => true,
	));

	// Register Taxonomy: Record Genre
	register_taxonomy('record_genre', 'discogs_record', array(
		'label' => __('Genres', 'discogs-importer'),
		'rewrite' => array('slug' => 'record-genre'),
		'hierarchical' => true,
		'show_in_rest' => true,
	));

	// Register Taxonomy: Record Style
	register_taxonomy('record_style', 'discogs_record', array(
		'label' => __('Styles', 'discogs-importer'),
		'rewrite' => array('slug' => 'record-style'),
		'hierarchical' => true,
		'show_in_rest' => true,
	));

	// Register Taxonomy: Record Format
	register_taxonomy('record_format', 'discogs_record', array(
		'label' => __('Formats', 'discogs-importer'),
		'rewrite' => array('slug' => 'record-format'),
		'hierarchical' => false,
		'show_in_rest' => true,
	));
}

add_action('init', 'discogs_importer_register_post_type');

/**
 * Activation hook code.
 */
function discogs_importer_activate()
{
	discogs_importer_register_post_type();
	flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'discogs_importer_activate');

/**
 * Deactivation hook code.
 */
function discogs_importer_deactivate()
{
	flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'discogs_importer_deactivate');

// Include required classes.
require_once DISCOGS_IMPORTER_PATH . 'includes/class-discogs-importer-api.php';
require_once DISCOGS_IMPORTER_PATH . 'includes/class-discogs-importer-admin.php';
require_once DISCOGS_IMPORTER_PATH . 'includes/class-discogs-importer-ajax.php';

// Initialize the Admin and AJAX classes.
if (is_admin()) {
	new Discogs_Importer_Admin();
	new Discogs_Importer_Ajax();
}
