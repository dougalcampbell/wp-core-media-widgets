<?php
/**
 * Plugin Name: QUnit Print Script Dependencies WP-CLI Command
 * Description: Print dependencies for scripts to be tested and then add into a QUnit HTML test runner file. Uses `wp_print_scripts()`.
 * Author: Weston Ruter, XWP
 * Author URI: https://make.xwp.co/
 * Plugin URI: https://github.com/xwp/wp-qunit-print-script-dependencies
 * Version: 0.1
 * License: GPLv2+
 *
 * Copyright (c) 2016 XWP (https://xwp.co/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package WordPress
 */

/**
 * Managing core media widgets.
 */
class Media_Widgets_WP_CLI_Command extends WP_CLI_Command {

	const CORE_BASE_HREF = 'https://develop.svn.wordpress.org/trunk/src/';

	const PLUGIN_BASE_HREF = '../../';

	/**
	 * Plugin script handles that will need to be enqueued.
	 *
	 * @var array
	 */
	static $plugin_script_handles = array(
		'media-widgets',
		'media-image-widget',
		'media-video-widget',
		'media-audio-widget',
	);

	/**
	 * Replace the base URL for script sources.
	 *
	 * @param string $script_tag Script tag.
	 * @param string $handle     Script handle.
	 * @return string Rewritten script src.
	 */
	static function filter_script_loader_tag( $script_tag, $handle ) {
		if ( in_array( $handle, self::$plugin_script_handles, true ) ) {
			$script_tag = preg_replace( '#https?://[^"]+?/wp-content/plugins/[^/]+/#', self::PLUGIN_BASE_HREF, $script_tag );
		} else {
			$script_tag = preg_replace( '#https?://[^"]+?/(?=(wp-includes|wp-admin)/)#', self::CORE_BASE_HREF, $script_tag );
		}
		return $script_tag;
	}

	/**
	 * Print dependencies for the supplied script handles.
	 *
	 * @global WP_Widget_Factory $wp_widget_factory
	 * @subcommand generate-qunit-test-suite
	 */
	public function generate_qunit_test_suite() {
		global $wp_widget_factory;

		$is_develop_src = ( false !== strpos( get_bloginfo( 'version' ), '-src' ) );
		$is_script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		if ( ! $is_develop_src ) {
			WP_CLI::error( 'Must be invoked as part of a wordpress-develop source repo install.' );
		}
		if ( ! $is_script_debug ) {
			WP_CLI::error( 'Must be invoked when SCRIPT_DEBUG is enabled.' );
		}

		$test_suite_file = dirname( __FILE__ ) . '/../tests/qunit/index.html';
		$test_suite_template = dirname( __FILE__ ) . '/../tests/qunit/test-suite.template';

		foreach ( self::$plugin_script_handles as $script_handle ) {
			if ( ! wp_scripts()->query( $script_handle, 'registered' ) ) {
				WP_CLI::error( "Script handle not registered: $script_handle" );
			}
		}

		add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_loader_tag' ), 10, 2 );
		wp_enqueue_media();

		ob_start();
		echo "<div hidden>\n";
		$original_enqueued_scripts = wp_scripts()->queue;
		foreach ( $wp_widget_factory->widgets as $widget ) {
			if ( $widget instanceof WP_Widget_Media ) {
				$widget->enqueue_admin_scripts();
				$widget->render_control_template_scripts();
			}
		}
		$enqueued_scripts = array_diff( wp_scripts()->queue, $original_enqueued_scripts );
		wp_print_scripts( $enqueued_scripts );
		wp_print_media_templates();
		echo "</div>\n";
		$output = ob_get_clean();

		$output = preg_replace( '/<link.+?>/', '', $output );
		$output = preg_replace( '#<style[^>]*>.+?</style>#s', '', $output );

		$test_suite = file_get_contents( $test_suite_template );
		$test_suite = preg_replace( '/(?=<html)/', "<!-- WARNING! Do not edit this file. It is generated via: wp media-widgets generate-qunit-test-suite -->\n", $test_suite );
		$test_suite = str_replace( '{{dependencies}}', $output, $test_suite );
		file_put_contents( $test_suite_file, $test_suite );

		remove_filter( 'script_loader_tag', array( __CLASS__, 'filter_script_loader_tag' ), 10 );

		WP_CLI::success( sprintf( 'Wrote test suite to %s', realpath( $test_suite_file ) ) );
	}
}
