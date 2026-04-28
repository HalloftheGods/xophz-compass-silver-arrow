<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Xophz_Compass_Silver_Arrow
 * @subpackage Xophz_Compass_Silver_Arrow/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Xophz_Compass_Silver_Arrow
 * @subpackage Xophz_Compass_Silver_Arrow/public
 * @author     Your Name <email@example.com>
 */
class Xophz_Compass_Silver_Arrow_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Xophz_Compass_Silver_Arrow_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Xophz_Compass_Silver_Arrow_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/xophz-compass-silver-arrow-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function route_traffic() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;

		$is_targeted = get_post_meta( $post->ID, '_sa_is_targeted', true );
		if ( ! $is_targeted ) {
			return;
		}

		// Enterprise Fail-Safe: Force Cache Bypass for Targeted Pages so the PHP router executes
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		// Enterprise Fail-Safe: Bot Detection
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		if ( preg_match( '/bot|crawl|slurp|spider|mediapartners|facebookexternalhit|uptime/i', $user_agent ) ) {
			return; // Bots fall through to the live page and are NOT tracked
		}

		// Check if we are actively serving a variant (via query string)
		if ( isset( $_GET['sa_rev'] ) ) {
			$rev_id = intval( $_GET['sa_rev'] );
			$test_id = isset( $_GET['sa_test_id'] ) ? intval( $_GET['sa_test_id'] ) : null;
			
			$this->set_double_tag_cookie( $post->ID, $test_id, $rev_id );
			$this->log_impression( $post->ID, $rev_id, $test_id );
			return;
		}

		// Check if user already has a sticky session for this post
		if ( isset( $_COOKIE['sa_session'] ) ) {
			$session = json_decode( stripslashes( $_COOKIE['sa_session'] ), true );
			if ( $session && isset( $session['post_id'] ) && $session['post_id'] == $post->ID ) {
				if ( isset( $session['test_id'] ) && $session['test_id'] ) {
					// Redirect them back to their assigned test bucket
					$redirect_url = add_query_arg( array(
						'sa_test_id' => $session['test_id'],
						'sa_rev' => $session['rev_id']
					), get_permalink( $post->ID ) );
					wp_redirect( $redirect_url, 302 );
					exit;
				}
			}
		}

		// We need to route a fresh visitor
		$active_tests = get_posts( array(
			'post_type' => 'sa_test',
			'meta_key' => '_sa_target_post_id',
			'meta_value' => $post->ID,
			'post_status' => 'publish',
			'posts_per_page' => -1
		) );

		// Simplistic global allocation engine for MVP:
		// We collect total allocated weight of tests.
		$total_test_weight = 0;
		$test_pool = array();

		foreach ( $active_tests as $test ) {
			$weight = intval( get_post_meta( $test->ID, '_sa_global_weight', true ) );
			if ( $weight > 0 ) {
				$total_test_weight += $weight;
				$test_pool[] = array( 'test' => $test, 'weight' => $weight );
			}
		}

		// Roll a 1-100 dice
		$dice = mt_rand( 1, 100 );
		$cumulative = 0;
		$selected_test = null;

		foreach ( $test_pool as $pool_item ) {
			$cumulative += $pool_item['weight'];
			if ( $dice <= $cumulative ) {
				$selected_test = $pool_item['test'];
				break;
			}
		}

		if ( $selected_test ) {
			// Route to test bucket
			$test_config = get_post_meta( $selected_test->ID, '_sa_test_config', true );
			if ( $test_config && is_array( $test_config ) && ! empty( $test_config['revisions'] ) ) {
				
				// Roll for internal test revision
				$internal_dice = mt_rand( 1, 100 );
				$internal_cumulative = 0;
				$selected_rev = null;

				foreach ( $test_config['revisions'] as $rev ) {
					$internal_cumulative += intval( $rev['weight'] );
					if ( $internal_dice <= $internal_cumulative ) {
						$selected_rev = $rev;
						break;
					}
				}

				if ( $selected_rev ) {
					$redirect_url = get_permalink( $post->ID );
					
					// Split URL override check
					if ( ! empty( $selected_rev['split_url'] ) ) {
						$redirect_url = $selected_rev['split_url'];
					}

					$redirect_url = add_query_arg( array(
						'sa_test_id' => $selected_test->ID,
						'sa_rev' => $selected_rev['id']
					), $redirect_url );

					wp_redirect( $redirect_url, 302 );
					exit;
				}
			}
		}

		// If no test caught them (or dice fell into Live pool) -> Passive Live Tracking
		$revisions = wp_get_post_revisions( $post->ID );
		$live_rev_id = !empty($revisions) ? current($revisions)->ID : $post->ID;

		$this->set_double_tag_cookie( $post->ID, null, $live_rev_id );
		$this->log_impression( $post->ID, $live_rev_id, null );
	}

	public function inject_canonical() {
		if ( isset( $_GET['sa_rev'] ) && is_singular() ) {
			global $post;
			$clean_url = get_permalink( $post->ID );
			echo '<link rel="canonical" href="' . esc_url( $clean_url ) . '" />' . "\n";
		}
	}

	public function capture_meta_snapshot( $revision_id, $revision ) {
		// When a revision is created, if the parent post is targeted, snapshot its DNA (Meta)
		$post_id = $revision->post_parent;
		if ( ! $post_id ) return;

		$is_targeted = get_post_meta( $post_id, '_sa_is_targeted', true );
		if ( ! $is_targeted ) return;

		// Grab all meta for the parent post
		$all_meta = get_post_meta( $post_id );
		
		// Clean up internal meta we don't want to clone
		foreach ( $all_meta as $key => $val ) {
			if ( strpos( $key, '_sa_' ) === 0 ) {
				unset( $all_meta[$key] );
			}
		}

		$json_archive = wp_json_encode( $all_meta );
		update_post_meta( $post_id, '_sa_meta_archive_' . $revision_id, $json_archive );
	}

	public function intercept_meta_requests( $value, $object_id, $meta_key, $single ) {
		if ( ! isset( $_GET['sa_rev'] ) || is_admin() ) {
			return $value; // Normal flow
		}

		// Only intercept if we are actively viewing a variant and requesting meta for the main post
		global $post;
		if ( ! $post || $object_id != $post->ID ) {
			return $value;
		}

		// Avoid infinite loops by letting our internal meta keys pass through
		if ( strpos( $meta_key, '_sa_' ) === 0 ) {
			return $value;
		}

		$rev_id = intval( $_GET['sa_rev'] );
		
		// Fetch our DNA archive for this specific revision
		$archive_json = get_post_meta( $post->ID, '_sa_meta_archive_' . $rev_id, true );
		if ( ! $archive_json ) {
			return $value; // Fallback to live meta if no archive exists
		}

		$archive = json_decode( $archive_json, true );

		// If the requested key isn't in the archive, let WP handle it
		if ( $meta_key && ! isset( $archive[$meta_key] ) ) {
			return $value;
		}

		if ( $meta_key ) {
			$meta_val = $archive[$meta_key];
			// get_post_meta normally returns an array of values unless $single is true.
			// Our archive stored the raw output of get_post_meta(id), which is an array of arrays/strings.
			if ( $single ) {
				return maybe_unserialize( $meta_val[0] );
			}
			return array_map( 'maybe_unserialize', $meta_val );
		}

		// If no specific key requested, return the whole unserialized archive
		$unserialized_archive = array();
		foreach ( $archive as $k => $v ) {
			$unserialized_archive[$k] = array_map( 'maybe_unserialize', $v );
		}
		return $unserialized_archive;
	}

	public function swap_post_content( $post_object ) {
		if ( isset( $_GET['sa_rev'] ) && is_main_query() ) {
			$rev_id = intval( $_GET['sa_rev'] );
			$revision = get_post( $rev_id );
			
			// Ensure it's a valid revision of the current post
			if ( $revision && $revision->post_parent == $post_object->ID && $revision->post_type === 'revision' ) {
				$post_object->post_content = $revision->post_content;
				$post_object->post_title = $revision->post_title;
			}
		}
	}

	public function track_conversion( $entry, $module_id, $field_data_array ) {
		if ( isset( $_COOKIE['sa_session'] ) ) {
			$session = json_decode( stripslashes( $_COOKIE['sa_session'] ), true );
			if ( $session && isset( $session['rev_id'] ) ) {
				$rev_id = intval( $session['rev_id'] );
				$test_id = isset( $session['test_id'] ) ? intval( $session['test_id'] ) : null;
				
				// Global Conversions
				$this->increment_daily_meta( $rev_id, '_sa_conversions_global' );
				
				// Test-Specific Conversions
				if ( $test_id ) {
					$this->increment_daily_meta( $rev_id, "_sa_conversions_test_{$test_id}" );
				}
			}
		}
	}

	private function set_double_tag_cookie( $post_id, $test_id, $rev_id ) {
		$session_data = json_encode( array(
			'post_id' => $post_id,
			'test_id' => $test_id,
			'rev_id' => $rev_id
		) );
		setcookie( 'sa_session', $session_data, time() + (86400 * 30), COOKIEPATH, COOKIE_DOMAIN );
	}

	private function log_impression( $post_id, $rev_id, $test_id ) {
		// Global Impressions
		$this->increment_daily_meta( $rev_id, '_sa_impressions_global' );
		
		// Test-Specific Impressions
		if ( $test_id ) {
			$this->increment_daily_meta( $rev_id, "_sa_impressions_test_{$test_id}" );
		}
	}

	private function increment_daily_meta( $post_id, $base_meta_key ) {
		global $wpdb;
		$date_suffix = gmdate( 'Ymd' );
		$meta_key = "{$base_meta_key}_{$date_suffix}";

		// Check if the meta key exists for today
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
			$post_id,
			$meta_key
		) );

		if ( $exists ) {
			// Atomic increment to prevent race conditions
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE meta_id = %d",
				$exists
			) );
		} else {
			// Initialize today's counter
			add_post_meta( $post_id, $meta_key, 1, true );
		}
	}

}
