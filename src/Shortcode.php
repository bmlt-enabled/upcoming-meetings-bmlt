<?php

namespace UpcomingMeetings;

require_once 'Settings.php';
require_once 'Helpers.php';

class Shortcode
{
    private $settings;
    private $helper;

    public function __construct()
    {
        $this->settings = new Settings();
        $this->helper = new Helpers();
    }

    public function render($atts = []): string
    {
        // Default values
        $content = '';
        $defaults = $this->getDefaultValues();
        $args = shortcode_atts($defaults, $atts);

        // Error messages
        $rootServerErrorMessage = '<p><strong>Contacts BMLT Error: Root Server missing. Please Verify you have entered a Root Server.</strong></p>';
        $servicesErrorMessage = '<p><strong>Temporary Closures Error: Services missing. Please verify you have entered a service body id using the \'services\' shortcode attribute</strong></p>';

        // Check for missing required values
        if (empty($args['root_server'])) {
            return $rootServerErrorMessage;
        }

        if (empty($args['services'])) {
            return $servicesErrorMessage;
        }

        // Set timezone
        date_default_timezone_set($args['timezone']);

        // Days of the week
        $days_of_the_week = ($args['weekday_language'] == 'da_DK') ?
            ["Søndag", "Mandag", "Tirsdag", "Onsdag", "Torsdag", "Fredag", "Lørdag"] :
            ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

        // Initialize content
        $content = '';

        // Custom CSS
        $content .= "<style type='text/css'>{$this->settings->options['custom_css_um']}</style>";

        // Get meeting results
        $meeting_results = $this->helper->getMeetingsJson(
            $args['root_server'],
            $args['services'],
            $args['timezone'],
            $args['grace_period'],
            $args['recursive'],
            $args['num_results'],
            $args['custom_query']
        );

        // Time format
        $out_time_format = ($args['time_format'] == '24') ? 'G:i' : 'g:i a';

        if (in_array($args['display_type'], ['table', 'block'])) {
            $content .= '<div id="upcoming_meetings_div">';
            $content .= $this->helper->meetingsJson2Html($meeting_results, $args['display_type'] === 'block', null, $out_time_format, $args['weekday_language']);
            $content .= '</div>';
        } else {
            $content .= $this->helper->renderMeetingsSimple($meeting_results, $args, $out_time_format, $args['weekday_language']);
        }

        return $content;
    }

    private function getDefaultValues(): array
    {
        $services_data_dropdown   = explode(',', $this->settings->options['service_body_dropdown']);
        $services_dropdown    = $this->helper->arraySafeGet($services_data_dropdown, 1);
        return [
            'root_server'       => $this->settings->options['root_server'],
            'services'          =>  $services_dropdown,
            'recursive'         => $this->settings->options['recursive'],
            'grace_period'      => $this->settings->options['grace_period_dropdown'],
            'num_results'       => $this->settings->options['num_results_dropdown'],
            'timezone'          => $this->settings->options['timezones_dropdown'],
            'display_type'      => $this->settings->options['display_type_dropdown'],
            'location_text'     => $this->settings->options['location_text_checkbox'],
            'time_format'       => $this->settings->options['time_format_dropdown'] ?? '',
            'weekday_language'  => $this->settings->options['weekday_language_dropdown'],
            'custom_query'      => $this->settings->options['custom_query']
        ];
    }
}
