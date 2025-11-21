<?php

namespace UpcomingMeetings;

require_once 'Settings.php';
require_once 'Helpers.php';

/**
 * Class Shortcode
 * @package UpcomingMeetings
 */
class Shortcode
{
    /**
     * Instance of the Settings class.
     *
     * @var Settings
     */
    private $settings;

    /**
     * Instance of the Helpers class.
     *
     * @var Helpers
     */
    private $helper;

    /**
     * Constructor method for the class.
     *
     * Initializes the $settings and $helper properties by creating instances of their respective classes.
     */
    public function __construct()
    {
        $this->settings = new Settings();
        $this->helper = new Helpers();
    }

    /**
     * Render the plugin's content based on shortcode attributes.
     *
     * This method is responsible for rendering the content based on the provided shortcode attributes.
     * It processes the attributes, performs necessary checks, retrieves meeting results, and generates HTML content.
     *
     * @param array $atts An associative array of shortcode attributes.
     * @return string The rendered content as a string.
     */
    public function render($atts = []): string
    {
        $defaults = $this->getDefaultValues();
        $args = shortcode_atts($defaults, $atts);
        $missing_extensions = [];
        if (!extension_loaded('date')) {
            $missing_extensions[] = 'datetimezone';
        }
        if (!extension_loaded('intl')) {
            $missing_extensions[] = 'intl';
        }
        if (!empty($missing_extensions)) {
            return '<p><strong>Upcoming Meetings Error: The following Required PHP modules are not installed: ' . implode(', ', $missing_extensions) . '.</strong></p>';
        }

        // Error messages
        $rootServerErrorMessage = '<p><strong>Upcoming Meetings Error: Root Server missing. Please Verify you have entered a Root Server.</strong></p>';
        $servicesErrorMessage = '<p><strong>Upcoming Meetings Error: Services missing. Please verify you have entered a service body id using the \'services\' shortcode attribute</strong></p>';

        // Check for missing required values
        if (empty($args['root_server'])) {
            return $rootServerErrorMessage;
        }
        if (empty($args['services'])) {
            return $servicesErrorMessage;
        }

        $timezones = array_map(function ($e) {
            return strtolower($e);
        }, \DateTimeZone::listIdentifiers());

        # TZ must be valid so we default to one if it isn't
        if (!in_array(strtolower($args['timezone']), $timezones)) {
            $args['timezone'] = 'America/New_York';
        }

        // Custom CSS
        $content = "<style>{$this->settings->options['custom_css_um']}</style>";

        // Get meeting results
        $meetingResults = $this->helper->getMeetingsJson(
            $args['root_server'],
            $args['services'],
            $args['timezone'],
            $args['grace_period'],
            $args['recursive'],
            $args['num_results'],
            $args['custom_query'],
            (bool)($args['limit_to_today'] ?? false)
        );

        // Time format
        $outTimeFormat = ($args['time_format'] == '24') ? 'G:i' : 'g:i a';

        if (in_array($args['display_type'], ['table', 'block'])) {
            $content .= '<div id="upcoming_meetings_div">';
            $content .= $this->helper->meetingsJson2Html($meetingResults, $args['display_type'] === 'block', null, $outTimeFormat, $args['weekday_language'], $args['show_header']);
            $content .= '</div>';
        } else {
            $content .= $this->helper->renderMeetingsSimple($meetingResults, $args, $outTimeFormat, $args['weekday_language']);
        }

        return $content;
    }

    /**
     * Get the default values for plugin settings.
     *
     * This method retrieves and returns an array of default values for various plugin settings.
     *
     * @return array An associative array containing default settings values.
     */
    private function getDefaultValues(): array
    {
        $servicesDataDropdown   = explode(',', $this->settings->options['service_body_dropdown']);
        $servicesDropdown    = $this->helper->arraySafeGet($servicesDataDropdown, 1);
        return [
            'root_server'       => $this->settings->options['root_server'],
            'services'          => $servicesDropdown,
            'recursive'         => $this->settings->options['recursive'],
            'grace_period'      => $this->settings->options['grace_period_dropdown'],
            'num_results'       => $this->settings->options['num_results_dropdown'],
            'timezone'          => $this->settings->options['timezones_dropdown'],
            'display_type'      => $this->settings->options['display_type_dropdown'],
            'location_text'     => $this->settings->options['location_text_checkbox'],
            'time_format'       => $this->settings->options['time_format_dropdown'] ?? '',
            'weekday_language'  => $this->settings->options['weekday_language_dropdown'],
            'show_header'       => $this->settings->options['show_header_checkbox'],
            'limit_to_today'    => $this->settings->options['limit_to_today_checkbox'] ?? '0',
            'custom_query'      => $this->settings->options['custom_query']
        ];
    }
}
