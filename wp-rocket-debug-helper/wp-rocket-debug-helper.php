<?php
defined( 'ABSPATH' ) or die();
/**
 * Plugin Name: WP Rocket | Debug Helper
 * Description: Checks for various constants, filters, and per-page cache options, prints their values as an HTML comment in the footer of the HTML source code.
 * Plugin URI:  https://github.com/wp-media/wp-rocket-helpers/tree/master/wp-rocket-debug-helper/
 * Author:      WP Rocket Support Team
 * Author URI:  http://wp-rocket.me/
 * License:     GNU General Public License v3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */


/**
 * Render complete formatted debug notice
 */
function wp_rocket_debug_helper__render_formatted_debug_notice() {

	if ( ! function_exists( 'get_rocket_option' ) ) {
		return;
	}

	/**
	 * BEGIN
	 */
	$html  = wp_rocket_debug_helper__render__begin();
	$html .= PHP_EOL;

	/**
	 * Disable HTML minification on the fly.
	 * (Otherwise the HTML comment would get stripped from the source code.)
	 */
	add_filter( 'get_rocket_option_minify_html', '__return_false' );
	$html .= sprintf( 'Note: %s', wp_rocket_debug_helper__render__note_minify_html() );
	$html .= PHP_EOL . PHP_EOL;

	/**
	 * CONSTANTS
	 */
	$html .= '## Constants' . PHP_EOL;
	$html .= PHP_EOL;
	$html .= wp_rocket_debug_helper__render__constants();
	$html .= PHP_EOL;

	/**
	 * FILTERS
	 */
	$html .= '## Filters' . PHP_EOL;
 	$html .= 'Note: Filter `rocket_override_donotcachepage` gets set by WP Rocket core in certain environments.';
	$html .= PHP_EOL . PHP_EOL;
	$html .= wp_rocket_debug_helper__render__filters();
	$html .= PHP_EOL;

	/**
	 * Only on singular views: Cache Options metabox values
	 */
	if ( is_singular() ) {

		// Get current post ID.
		$current_post_id = absint( $GLOBALS['post']->ID );

		/**
		 * METABOX
		 */
		$html .= '## Cache Options metabox' . PHP_EOL;
		$html .= sprintf( 'Note: You’re viewing post ID #%s', $current_post_id );
		$html .= PHP_EOL . PHP_EOL;

		// Excluded from cache?
		$html .= wp_rocket_debug_helper__render__cache_reject( $current_post_id );
		$html .= PHP_EOL;

		// Other cache options
		$html .= wp_rocket_debug_helper__render__metabox( $current_post_id );
		$html .= PHP_EOL;
	}

	/**
	 * END
	 */
	$html .= wp_rocket_debug_helper__render__end();
	$html .= PHP_EOL;

	print $html;
}
add_action( 'wp_footer', 'wp_rocket_debug_helper__render_formatted_debug_notice', PHP_INT_MAX );

/**
 * Render begin of debug notice
 */
function wp_rocket_debug_helper__render__begin() {

	$html  = PHP_EOL . PHP_EOL;
	$html .= '<!--' . PHP_EOL;
	$html .= '#################################################### ';
	$html .= PHP_EOL;
	$html .= '## WP ROCKET DEBUG ##';

	return $html;
}

/**
 * Render end of debug notice
 */
function wp_rocket_debug_helper__render__end() {

	$html  = PHP_EOL . '####################################################';
	$html .= PHP_EOL;
	$html .= '-->';

	return $html;
}

/**
 * Render section: Contants
 */
function wp_rocket_debug_helper__render__constants() {

	$html = '';

	/**
	 * Cannot use var_export() or print_r() inside an output buffer, so
	 * apologies, this looks a bit wonky.
	 */
	$constant_names = array(
		'WP_CACHE',
		'DONOTCACHEPAGE',
		'DONOTMINIFY',
		'DONOTMINIFYCSS',
		'DONOTMINIFYJS',
	);

	$constant_values = array();

	foreach ( $constant_names as $constant ) {

		if( ! defined( $constant ) ) {
			$constant_values[ $constant ] = 'not defined';
		} else {
			$constant_values[ $constant ] = true === constant( $constant ) || 'true' === constant( $constant ) ? 'TRUE' : 'FALSE';
		}

		$html .= sprintf(
			'- constant %1$s is: %2$s',
			$constant,
			$constant_values[ $constant ]
		);
		$html .= PHP_EOL;
	}

	return $html;
}

/**
 * Render section: Filters
 */
function wp_rocket_debug_helper__render__filters( $filters = array(
	'do_rocket_generate_caching_files',
	'rocket_override_donotcachepage'
) ) {

	$html = '';

	foreach ( $filters as $filter ) {

		$html_filter = 'not set';

		global $wp_filter;

		foreach ( $wp_filter as $filter_name => $filter_value ) {
			if ( false !== strpos( $filter_name, $filter ) ) {

				$current_filter = $filter_value->current();

				foreach ( $current_filter as $key => $value ) {

					$html_filter  = 'set';
					$html_filter .= sprintf( ' (%s)', var_export( $value['function'], true ) );

					if ( 'rocket_override_donotcachepage_on_thrive_leads' === $value['function'] ) {
						$html_filter = sprintf( 'default (%s)', var_export( $value['function'], true ) );
					}

				}

			}
		}

		$html .= sprintf( '- filter %1$s is: %2$s', $filter, $html_filter );
		$html .= PHP_EOL;
	}

	return $html;
}

/**
 * Render section: Cache options metabox
 */
function wp_rocket_debug_helper__render__metabox() {

	$html = '';

	$cache_options = array(
		'lazyload',
		'lazyload_iframes',
		'minify_html',
		'minify_css',
		'minify_js',
		'cdn',
		'async_css',
		'defer_all_js',
	);

	foreach ( $cache_options as $cache_option ) {

		$value = get_rocket_option( $cache_option );
		$value = is_rocket_post_excluded_option( $cache_option );

		if ( '1' === $value ) {
			$formatted_value = 'DEACTIVATED';
		} else {
			$formatted_value = 'unchanged';
		}

		if ( 'minify_html' === $cache_option ) {
			$formatted_value .= PHP_EOL;
			$formatted_value .= sprintf( '  (Remember: %s)',
				wp_rocket_debug_helper__render__note_minify_html()
			);
		}

		$html .= sprintf( '- Cache option %1$s: %2$s',
			$cache_option,
			$formatted_value
		);
		$html .= PHP_EOL;
	}

	return $html;
}

/**
 * Helper: Check for cache exclusion
 */
function wp_rocket_debug_helper__render__cache_reject( $current_post_id ) {

	// No way to find out if the “Never cache this page” option is checked,
	// but we can find out whether or not this post is excluded from cache.
	$excluded_post_paths = get_rocket_option( 'cache_reject_uri', array() );
	$current_post_path   = rocket_clean_exclude_file( get_permalink( $current_post_id ) );
	$maybe_post_excluded = in_array( $current_post_path, $excluded_post_paths);

	$html_maybe_excluded = 'not excluded from caching via “Never cache this page”, or “Never cache (URL)”';

	if ( $maybe_post_excluded ) {
		$html_maybe_excluded = 'EXCLUDED from caching via “Never cache this page”, or “Never cache (URL)”';
	}

	return sprintf( '- This post is %s', $html_maybe_excluded );
}

/**
 * Helper: Note about Minify HTML
 */
function wp_rocket_debug_helper__render__note_minify_html() {

	return 'Minify HTML is dynamically disabled, so this debug notice can be displayed.';
}
