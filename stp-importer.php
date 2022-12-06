<?php
/*
Plugin Name: Simple Tags Importer
Plugin URI: http://wordpress.org/extend/plugins/stp-importer/
Description: Import Simple Tagging tags into WordPress tags.
Author: wordpressdotorg
Author URI: http://wordpress.org/
Version: 0.3.1
Stable tag: 0.3.1
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

/**
 * Simple Tags Importer
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class STP_Import extends WP_Importer {
	function header()  {
		echo '<div class="wrap">';

		if ( version_compare( get_bloginfo( 'version' ), '3.8.0', '<' ) ) {
			screen_icon();
		}

		echo '<h2>'.__('Import Simple Tagging', 'stp-importer').'</h2>';
		echo '<p>'.__('Steps may take a few minutes depending on the size of your database. Please be patient.', 'stp-importer').'<br /><br /></p>';
	}

	function footer() {
		echo '</div>';
	}

	function greet() {
		echo '<div class="narrow">';
		echo '<p>'.__('Howdy! This imports tags from Simple Tagging 1.6.2 into WordPress tags.', 'stp-importer').'</p>';
		echo '<p>'.__('This has not been tested on any other versions of Simple Tagging. Mileage may vary.', 'stp-importer').'</p>';
		echo '<p>'.__('To accommodate larger databases for those tag-crazy authors out there, we have made this into an easy 4-step program to help you kick that nasty Simple Tagging habit. Just keep clicking along and we will let you know when you are in the clear!', 'stp-importer').'</p>';
		echo '<p><strong>'.__('Don&#8217;t be stupid - backup your database before proceeding!', 'stp-importer').'</strong></p>';
		echo '<form action="admin.php?import=stp&amp;step=1" method="post">';
		wp_nonce_field('import-stp');
		echo '<p class="submit"><input type="submit" name="submit" class="button" value="'.esc_attr__('Step 1', 'stp-importer').'" /></p>';
		echo '</form>';
		echo '</div>';
	}

	function dispatch () {
		if ( empty( $_GET['step'] ) ) {
			$step = 0;
		} else {
			$step = (int) $_GET['step'];
		}
		// load the header
		$this->header();
		switch ( $step ) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer('import-stp');
				$this->import_posts();
				break;
			case 2:
				check_admin_referer('import-stp');
				$this->import_t2p();
				break;
			case 3:
				check_admin_referer('import-stp');
				$this->cleanup_import();
				break;
		}
		// load the footer
		$this->footer();
	}


	function import_posts ( ) {
		echo '<div class="narrow">';
		echo '<p><h3>'.__('Reading STP Post Tags&#8230;', 'stp-importer').'</h3></p>';

		// read in all the STP tag -> post settings
		$posts = $this->get_stp_posts();

		// if we didn't get any tags back, that's all there is folks!
		if ( !is_array($posts) ) {
			echo '<p>' . __('No posts were found to have tags!', 'stp-importer') . '</p>';
			return false;
		}
		else {
			// if there's an existing entry, delete it
			if ( get_option('stpimp_posts') ) {
				delete_option('stpimp_posts');
			}

			add_option('stpimp_posts', $posts);
			$count = count($posts);
			echo '<p>' . sprintf( _n('Done! <strong>%s</strong> tag to post relationships were read.', 'Done! <strong>%s</strong> tags to post relationships were read.', $count, 'stp-importer'), $count ) . '<br /></p>';
		}

		echo '<form action="admin.php?import=stp&amp;step=2" method="post">';
		wp_nonce_field('import-stp');
		echo '<p class="submit"><input type="submit" name="submit" class="button" value="'.esc_attr__('Step 2', 'stp-importer').'" /></p>';
		echo '</form>';
		echo '</div>';
	}


	function import_t2p ( ) {
		echo '<div class="narrow">';
		echo '<p><h3>'.__('Adding Tags to Posts&#8230;', 'stp-importer').'</h3></p>';

		// run that funky magic!
		$tags_added = $this->tag2post();

		echo '<p>' . sprintf( _n('Done! <strong>%s</strong> tag was added!', 'Done! <strong>%s</strong> tags were added!', $tags_added, 'stp-importer'), $tags_added ) . '<br /></p>';
		echo '<form action="admin.php?import=stp&amp;step=3" method="post">';
		wp_nonce_field('import-stp');
		echo '<p class="submit"><input type="submit" name="submit" class="button" value="'.esc_attr__('Step 3', 'stp-importer').'" /></p>';
		echo '</form>';
		echo '</div>';
	}

	function get_stp_posts ( ) {
		global $wpdb;
		// read in all the posts from the STP post->tag table: should be wp_post2tag
		$posts_query = "SELECT post_id, tag_name FROM " . $wpdb->prefix . "stp_tags";
		$posts = $wpdb->get_results($posts_query);
		return $posts;
	}

	function tag2post ( ) {
		global $wpdb;

		// get the tags and posts we imported in the last 2 steps
		$posts = get_option('stpimp_posts');

		// null out our results
		$tags_added = 0;

		// loop through each post and add its tags to the db
		foreach ( $posts as $this_post ) {
			$the_post = (int) $this_post->post_id;
			$the_tag = $wpdb->escape($this_post->tag_name);
			// try to add the tag
			wp_add_post_tags($the_post, $the_tag);
			$tags_added++;
		}

		// that's it, all posts should be linked to their tags properly, pending any errors we just spit out!
		return $tags_added;
	}

	function cleanup_import ( ) {
		delete_option('stpimp_posts');
		$this->done();
	}

	function done ( ) {
		echo '<div class="narrow">';
		echo '<p><h3>'.__('Import Complete!', 'stp-importer').'</h3></p>';
		echo '<p>' . __('OK, so we lied about this being a 4-step program! You&#8217;re done!', 'stp-importer') . '</p>';
		echo '<p>' . __('Now wasn&#8217;t that easy?', 'stp-importer') . '</p>';
		echo '</div>';
	}
}

// create the import object
$stp_import = new STP_Import();

// add it to the import page!
register_importer('stp', 'Simple Tagging', __('Import Simple Tagging tags into WordPress tags.', 'stp-importer'), array($stp_import, 'dispatch'));

} // class_exists( 'WP_Importer' )

function stp_importer_init() {
    load_plugin_textdomain( 'stp-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'stp_importer_init' );
