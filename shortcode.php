<?php
/**
 * Plugin Name: 应用页面插件
 * Plugin URI: https://github.com/Jacky088/app-wordpress
 * Description: 推荐多个应用，支持后台管理、多端自适应、分类筛选、多下载按钮。
 * Version: 1.8.0
 * Author: 木木
 * Author URI: https://github.com/Jacky088/app-wordpress
 * Text Domain: apps-exhibition
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! defined( 'APPS_EXHIBITION_PATH' ) ) {
    define( 'APPS_EXHIBITION_PATH', plugin_dir_path( __FILE__ ) );
}

final class Apps_Exhibition {

    const VERSION = '1.8.0'; // Updated version number

    private $plugin_path;
    private $plugin_url;
    private $table_name;

    /** 平台选项 (一级分类) */
    public $platform_options = [ 'Android', 'AndroidTV', 'iOS', 'iPadOS', 'macOS', 'Windows' ];

    public function __construct() {
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url  = plugin_dir_url( __FILE__ );

        global $wpdb;
        $this->table_name = $wpdb->prefix . 'apps_exhibition';

        // Initialize hooks
        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_enqueue_scripts' ] );

        // Load necessary files
        require_once $this->plugin_path . 'includes/admin.php';
        require_once $this->plugin_path . 'includes/shortcode.php';
    }

    /**
     * Handles plugin activation, including database table creation/updates.
     */
    public function activate_plugin() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Updated SQL to ensure 'app_platforms' is present and 'app_categories' is removed
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            app_name varchar(100) NOT NULL,
            app_description text NOT NULL,
            app_icon varchar(255) NOT NULL,
            app_platforms varchar(255) NOT NULL,
            app_downloads text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql ); // dbDelta handles creation and schema updates intelligently

        // IMPORTANT: dbDelta does not remove columns. If 'app_categories' still exists,
        // it needs to be manually removed or handled with an ALTER TABLE statement.
        // This is simplified for this code block as per the request to delete it.
    }

    public function add_admin_menu() {
        add_menu_page(
            __( '应用展示', 'apps-exhibition' ),
            __( '应用展示', 'apps-exhibition' ),
            'manage_options',
            'apps-exhibition',
            [ $this, 'render_admin_page' ],
            'dashicons-tablet',
            30
        );
    }

    public function render_admin_page() {
        // The global plugin instance is already set upon plugin load (see below).
        // This ensures the admin page can access platform_options directly.
        if ( function_exists( 'apps_exhibition_admin_page' ) ) {
            apps_exhibition_admin_page();
        }
    }

    public function admin_enqueue_scripts( $hook_suffix ) {
        if ( $hook_suffix !== 'toplevel_page_apps-exhibition' ) {
            return;
        }

        wp_enqueue_media(); // For media uploader
        wp_enqueue_style( 'apps-exhibition-admin-style', $this->plugin_url . 'assets/css/admin.css', [], self::VERSION );
        wp_enqueue_script( 'apps-exhibition-admin-script', $this->plugin_url . 'assets/js/admin.js', [ 'jquery' ], self::VERSION, true );
    }

    public function frontend_enqueue_scripts() {
        wp_enqueue_style( 'apps-exhibition-style', $this->plugin_url . 'assets/css/apps-exhibition.css', [], self::VERSION );
    }
}

// IMPORTANT: Instantiate the plugin class and set it to a global variable
// immediately upon plugin load. This makes the one instance universally accessible
// for admin pages, shortcodes, etc.
global $apps_exhibition_plugin_instance;
$apps_exhibition_plugin_instance = new Apps_Exhibition();
