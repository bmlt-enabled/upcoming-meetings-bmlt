<?php

/*
Plugin Name: Upcoming Meetings BMLT
Plugin URI: https://wordpress.org/plugins/upcoming-meetings-bmlt/
Contributors: pjaudiomv, bmltenabled
Author: bmlt-enabled
Description: Upcoming Meetings BMLT is a plugin that displays the next 'N' number of meetings from the current time on your page or in a widget using the upcoming_meetings shortcode.
Version: 1.7.0
Install: Drop this directory into the "wp-content/plugins/" directory and activate it.
*/
/* Disallow direct access to the plugin file */
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Sorry, but you cannot access this page directly.');
}

spl_autoload_register(function (string $class) {
    if (strpos($class, 'UpcomingMeetings\\') === 0) {
        $class = str_replace('UpcomingMeetings\\', '', $class);
        require __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    }
});

use UpcomingMeetings\Settings;
use UpcomingMeetings\Shortcode;
use UpcomingMeetings\FormatsShortcode;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
class UpcomingMeetings
// phpcs:enable PSR1.Classes.ClassDeclaration.MissingNamespace
{
    /**
     * Singleton instance of the class.
     *
     * @var null|self
     */
    private static $instance = null;

    /**
     * Initialize the plugin and set up its functionality.
     *
     * This constructor function is called when an instance of the plugin class is created.
     * It hooks the 'pluginSetup' method to the 'init' action, which sets up the plugin's functionality.
     */
    public function __construct()
    {
        add_action('init', [$this, 'pluginSetup']);
    }

    /**
     * Set up the plugin's functionality and actions.
     *
     * This function is responsible for setting up various actions and hooks based on whether
     * the current context is in the WordPress admin or frontend.
     * - In the admin context, it adds menu options and enqueues backend files.
     * - In the frontend context, it enqueues frontend files and registers a shortcode for displaying meetings.
     */
    public function pluginSetup(): void
    {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'optionsMenu']);
            add_action("admin_enqueue_scripts", [$this, "enqueueBackendFiles"], 500);
        } else {
            add_action("wp_enqueue_scripts", [$this, "enqueueFrontendFiles"]);
            add_shortcode('upcoming_meetings', [$this, 'showMeetings']);
            add_shortcode('meeting_formats', [$this, 'showFormats']);
        }
        // The area filter AJAX endpoint must be registered for both logged-in and anonymous
        // visitors, and runs through admin-ajax.php (which is an admin context), so it lives outside
        // the branch above.
        add_action('wp_ajax_upcoming_meetings_filter', [$this, 'ajaxFilterMeetings']);
        add_action('wp_ajax_nopriv_upcoming_meetings_filter', [$this, 'ajaxFilterMeetings']);
    }

    /**
     * AJAX handler for the area filter: re-queries meetings scoped to the selected area and returns HTML.
     */
    public function ajaxFilterMeetings(): void
    {
        check_ajax_referer('upcoming_meetings_filter', 'nonce');

        $rootServer = isset($_POST['root_server']) ? esc_url_raw(wp_unslash($_POST['root_server'])) : '';
        if (empty($rootServer) || !wp_http_validate_url($rootServer)) {
            wp_send_json_error('Invalid root server.', 400);
        }

        $serviceBody = isset($_POST['service_body']) ? sanitize_text_field(wp_unslash($_POST['service_body'])) : 'all';
        $originalServices = isset($_POST['services']) ? sanitize_text_field(wp_unslash($_POST['services'])) : '';

        $args = [
            'root_server'      => $rootServer,
            // When a specific area is chosen, scope to it recursively; otherwise keep the original region.
            'services'         => ($serviceBody !== 'all' && $serviceBody !== '') ? $serviceBody : $originalServices,
            'recursive'        => ($serviceBody !== 'all' && $serviceBody !== '') ? '1' : (isset($_POST['recursive']) ? sanitize_text_field(wp_unslash($_POST['recursive'])) : '0'),
            'grace_period'     => isset($_POST['grace_period']) ? sanitize_text_field(wp_unslash($_POST['grace_period'])) : '15',
            'num_results'      => isset($_POST['num_results']) ? sanitize_text_field(wp_unslash($_POST['num_results'])) : '5',
            'timezone'         => isset($_POST['timezone']) ? sanitize_text_field(wp_unslash($_POST['timezone'])) : 'America/New_York',
            'display_type'     => isset($_POST['display_type']) ? sanitize_text_field(wp_unslash($_POST['display_type'])) : 'simple',
            'location_text'    => isset($_POST['location_text']) ? sanitize_text_field(wp_unslash($_POST['location_text'])) : '',
            'time_format'      => isset($_POST['time_format']) ? sanitize_text_field(wp_unslash($_POST['time_format'])) : '12',
            'weekday_language' => isset($_POST['weekday_language']) ? sanitize_text_field(wp_unslash($_POST['weekday_language'])) : 'en',
            'show_header'      => isset($_POST['show_header']) ? sanitize_text_field(wp_unslash($_POST['show_header'])) : '0',
            'limit_to_today'   => isset($_POST['limit_to_today']) ? sanitize_text_field(wp_unslash($_POST['limit_to_today'])) : '0',
            'custom_query'     => isset($_POST['custom_query']) ? sanitize_text_field(wp_unslash($_POST['custom_query'])) : '',
            'meetings'         => isset($_POST['meetings']) ? sanitize_text_field(wp_unslash($_POST['meetings'])) : '',
        ];

        $shortcode = new Shortcode();
        echo $shortcode->buildMeetingsHtml($args);
        wp_die();
    }

    /**
     * Set up the plugin settings menu page.
     *
     * This function is responsible for setting up the plugin's settings menu page in the WordPress admin panel.
     * It creates a menu using the Settings class and associates it with the plugin file.
     */
    public function optionsMenu(): void
    {
        $dashboard = new Settings();
        $dashboard->createMenu(plugin_basename(__FILE__));
    }

    /**
     * Display meetings using a shortcode.
     *
     * This function is used to display meetings on a WordPress page or post using a shortcode.
     * It creates a new instance of the Shortcode class and renders the shortcode with the provided attributes.
     *
     * @param array $atts An associative array of attributes passed to the shortcode.
     * @return string The rendered content of the shortcode.
     */
    public function showMeetings($atts): string
    {
        $shortcode = new Shortcode();
        return $shortcode->render($atts);
    }

    /**
     * Display meeting formats using a shortcode.
     *
     * This function is used to display meeting formats from a BMLT server on a WordPress page or post.
     * It creates a new instance of the FormatsShortcode class and renders the shortcode with the provided attributes.
     *
     * @param array $atts An associative array of attributes passed to the shortcode.
     * @return string The rendered content of the shortcode.
     */
    public function showFormats($atts): string
    {
        $formatsShortcode = new FormatsShortcode();
        return $formatsShortcode->render($atts);
    }

    /**
     * Enqueue backend CSS and JavaScript files for the plugin.
     *
     * This function enqueues the necessary CSS and JavaScript files for the plugin's backend settings page.
     *
     * @param string $hook The current admin page's hook name.
     */
    public function enqueueBackendFiles(string $hook): void
    {
        if ($hook !== 'settings_page_upcoming-meetings-bmlt') {
            return;
        }
        $baseUrl = plugin_dir_url(__FILE__);
        wp_enqueue_style('upcoming-meetings-admin-ui-css', $baseUrl . 'css/redmond/jquery-ui.css', [], '1.11.4');
        wp_enqueue_style("chosen", $baseUrl . "css/chosen.min.css", [], '1.2', 'all');
        wp_enqueue_script('chosen', $baseUrl . 'js/chosen.jquery.min.js', ['jquery'], '1.2', true);
        wp_enqueue_script('upcoming-meetings-admin', $baseUrl . 'js/upcoming_meetings_admin.js', ['jquery'], filemtime(plugin_dir_path(__FILE__) . 'js/upcoming_meetings_admin.js'), false);
        wp_enqueue_script('common');
        wp_enqueue_script('jquery-ui-accordion');
    }

    /**
     * Enqueue frontend CSS files for the plugin.
     *
     * This function enqueues the 'upcoming meetings' CSS file for use in the frontend.
     * It is typically used to include stylesheets required for displaying content or features
     * on the public-facing side of the website.
     */
    public function enqueueFrontendFiles(): void
    {
        wp_enqueue_style('upcoming-meetings', plugin_dir_url(__FILE__) . 'css/upcoming_meetings.css', false, '1.7.0', 'all');
        // Registered here but only enqueued by the shortcode when the area filter is enabled.
        $filterScript = plugin_dir_path(__FILE__) . 'js/upcoming_meetings.js';
        wp_register_script('upcoming-meetings-filter', plugin_dir_url(__FILE__) . 'js/upcoming_meetings.js', [], filemtime($filterScript), true);
        wp_localize_script('upcoming-meetings-filter', 'UpcomingMeetingsFilter', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('upcoming_meetings_filter'),
        ]);
    }

    /**
     * Get the instance of the class (Singleton pattern).
     *
     * @return self The instance of the class.
     */
    public static function getInstance(): self
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

UpcomingMeetings::getInstance();
