<?php

/*
Plugin Name: Upcoming Meetings BMLT
Plugin URI: https://wordpress.org/plugins/upcoming-meetings-bmlt/
Contributors: pjaudiomv, bmltenabled
Author: bmlt-enabled
Description: Upcoming Meetings BMLT is a plugin that displays the next 'N' number of meetings from the current time on your page or in a widget using the upcoming_meetings shortcode.
Version: 1.5.0
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

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
class UpcomingMeetings
// phpcs:enable PSR1.Classes.ClassDeclaration.MissingNamespace
{
    private static $instance = null;

    public function __construct()
    {
        add_action('init', [$this, 'pluginSetup']);
    }

    public function pluginSetup()
    {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'optionsMenu']);
            add_action("admin_enqueue_scripts", [$this, "enqueueBackendFiles"], 500);
        } else {
            add_action("wp_enqueue_scripts", [$this, "enqueueFrontendFiles"]);
            add_shortcode('upcoming_meetings', [$this, 'showClosures']);
        }
    }

    public function optionsMenu()
    {
        $dashboard = new Settings();
        $dashboard->createMenu(plugin_basename(__FILE__));
    }

    public function showClosures($atts)
    {
        $shortcode = new Shortcode();
        return $shortcode->render($atts);
    }

    public function enqueueBackendFiles($hook)
    {
        if ($hook !== 'settings_page_upcoming-meetings-bmlt') {
            return;
        }
        $base_url = plugin_dir_url(__FILE__);
        wp_enqueue_style('upcoming-meetings-admin-ui-css', $base_url . 'css/redmond/jquery-ui.css', [], '1.11.4');
        wp_enqueue_style("chosen", $base_url . "css/chosen.min.css", [], '1.2', 'all');
        wp_enqueue_script('chosen', $base_url . 'js/chosen.jquery.min.js', ['jquery'], '1.2', true);
        wp_enqueue_script('upcoming-meetings-admin', $base_url . 'js/upcoming_meetings_admin.js', ['jquery'], filemtime(plugin_dir_path(__FILE__) . 'js/upcoming_meetings_admin.js'), false);
        wp_enqueue_script('common');
        wp_enqueue_script('jquery-ui-accordion');
    }

    public function enqueueFrontendFiles($hook)
    {
        wp_enqueue_style('upcoming-meetings', plugin_dir_url(__FILE__) . 'css/upcoming_meetings.css', false, '1.15', 'all');
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

UpcomingMeetings::getInstance();
