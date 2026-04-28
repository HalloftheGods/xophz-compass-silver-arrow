<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://www.mycompassconsulting.com/getcompass/
 * @since             1.0.0
 * @package           Xophz_Compass_
 *
 * @wordpress-plugin
 * Category:          Trajectory
 * Plugin Name:       Xophz A/B Silver Arrow
 * Plugin URI:        https://github.com/HalloftheGods/xophz-compass-silver-arrow
 * Description:       The silver bullet to A/B Split Testing.
 * Version:           26.4.28
 * Author:            Hall of the Gods, Inc.
 * Author URI:        http://www.hallofthegods.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       xophz-compass-silver-arrow
 * Domain Path:       /languages
 * Update URI:        https://github.com/HalloftheGods/xophz-compass-silver-arrow
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'XOPHZ_COMPASS_SILVER_ARROW_VERSION', '26.4.28' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-xophz-compass-silver-arrow-activator.php
 */
function activate_xophz_compass_silver_arrow() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xophz-compass-silver-arrow-activator.php';
	Xophz_Compass_Silver_Arrow_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-xophz-compass-silver-arrow-deactivator.php
 */
function deactivate_xophz_compass_silver_arrow() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xophz-compass-silver-arrow-deactivator.php';
	Xophz_Compass_Silver_Arrow_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_xophz_compass_silver_arrow' );
register_deactivation_hook( __FILE__, 'deactivate_xophz_compass_silver_arrow' );

add_filter( 'compass_perform_widgets', function( $widgets ) {
	$widgets[] = array(
		'key'           => 'silver-arrow-overview',
		'plugin'        => 'xophz-compass-silver-arrow',
		'title'         => 'A/B Testing',
		'icon'          => 'fad fa-crosshairs',
		'color'         => '#62c9ff',
		'component'     => 'perform-widget-silver-arrow',
		'data_endpoint' => '/wp-json/xophz-compass/v1/silver-arrow/targeted',
		'size'          => 'sm',
		'order'         => 30,
	);
	return $widgets;
});

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-xophz-compass-silver-arrow.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_xophz_compass_silver_arrow() {

  if ( ! class_exists( 'Xophz_Compass' ) ) {
    add_action( 'admin_init', 'shutoff_xophz_compass_silver_arrow' );
    add_action( 'admin_notices', 'admin_notice_xophz_compass_silver_arrow' );

    function shutoff_xophz_compass_silver_arrow() {
      if ( ! function_exists( 'deactivate_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
      }
      deactivate_plugins( plugin_basename( __FILE__ ) );
    }

    function admin_notice_xophz_compass_silver_arrow() {
      echo '<div class="error"><h2><strong>Xophz_Compass_Silver_Arrow</strong> requires Compass to run. It has self <strong>deactivated</strong>.</h2></div>';
      if ( isset( $_GET['activate'] ) )
        unset( $_GET['activate'] );
    }
  } else {
    $plugin = new Xophz_Compass_Silver_Arrow();
    $plugin->run();
  }
  
}
add_action( 'plugins_loaded', 'run_xophz_compass_silver_arrow' );
