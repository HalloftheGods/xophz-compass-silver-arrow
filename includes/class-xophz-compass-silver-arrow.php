<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Xophz_Compass_Silver_Arrow
 * @subpackage Xophz_Compass_Silver_Arrow/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Xophz_Compass_Silver_Arrow
 * @subpackage Xophz_Compass_Silver_Arrow/includes
 * @author     Your Name <email@example.com>
 */
class Xophz_Compass_Silver_Arrow {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Xophz_Compass_Silver_Arrow_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'XOPHZ_COMPASS_SILVER_ARROW_VERSION' ) ) {
			$this->version = XOPHZ_COMPASS_SILVER_ARROW_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'xophz-compass-silver-arrow';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Xophz_Compass_Silver_Arrow_Loader. Orchestrates the hooks of the plugin.
	 * - Xophz_Compass_Silver_Arrow_i18n. Defines internationalization functionality.
	 * - Xophz_Compass_Silver_Arrow_Admin. Defines all hooks for the admin area.
	 * - Xophz_Compass_Silver_Arrow_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-xophz-compass-silver-arrow-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-xophz-compass-silver-arrow-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-xophz-compass-silver-arrow-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-xophz-compass-silver-arrow-public.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-silver-arrow-rest.php';

		$this->loader = new Xophz_Compass_Silver_Arrow_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Xophz_Compass_Silver_Arrow_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Xophz_Compass_Silver_Arrow_i18n();

		$this->loader->add_action( 'init', $plugin_i18n, 'load_plugin_textdomain', 5 );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Xophz_Compass_Silver_Arrow_Admin( $this->get_xophz_compass_silver_arrow(), $this->get_version() );

		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		// $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'addToMenu' );
		$this->loader->add_action( 'init', $this, 'register_post_types' );

		$rest_api = new Xophz_Compass_Silver_Arrow_Rest();
		$this->loader->add_action( 'rest_api_init', $rest_api, 'register_routes' );

	}

	public function register_post_types() {
		$args = array(
			'public' => false,
			'show_ui' => false,
			'label'  => 'Silver Arrow Tests',
			'supports' => array('title', 'custom-fields')
		);
		register_post_type( 'sa_test', $args );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Xophz_Compass_Silver_Arrow_Public( $this->get_xophz_compass_silver_arrow(), $this->get_version() );

		$this->loader->add_action( 'template_redirect', $plugin_public, 'route_traffic', 1 );
		$this->loader->add_action( 'the_post', $plugin_public, 'swap_post_content' );
		$this->loader->add_action( 'wp_head', $plugin_public, 'inject_canonical' );
		$this->loader->add_action( 'forminator_custom_form_submit_before_set_fields', $plugin_public, 'track_conversion', 10, 3 );
		
		// The DNA Snapshot Hooks
		$this->loader->add_action( 'wp_put_post_revision', $plugin_public, 'capture_meta_snapshot', 10, 2 );
		$this->loader->add_filter( 'get_post_metadata', $plugin_public, 'intercept_meta_requests', 10, 4 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_xophz_compass_silver_arrow() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Xophz_Compass_Silver_Arrow_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
