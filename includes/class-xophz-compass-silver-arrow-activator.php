<?php

/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Xophz_Compass_Silver_Arrow
 * @subpackage Xophz_Compass_Silver_Arrow/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Xophz_Compass_Silver_Arrow
 * @subpackage Xophz_Compass_Silver_Arrow/includes
 * @author     Your Name <email@example.com>
 */
class Xophz_Compass_Silver_Arrow_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
	    if ( !class_exists( 'Xophz_Compass' ) ) {  
	    	die('This plugin requires COMPASS to be active.</a></div>');
	    } 
	}

}
