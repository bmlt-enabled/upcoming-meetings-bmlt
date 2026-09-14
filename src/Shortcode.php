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
        $servicesErrorMessage = '<p><strong>Upcoming Meetings Error: Services missing. Please verify you have entered a service body id using the \'services\' shortcode attribute, or specific meeting ids using the \'meetings\' shortcode attribute.</strong></p>';

        // Check for missing required values
        if (empty($args['root_server'])) {
            return $rootServerErrorMessage;
        }
        if (empty($args['services']) && empty($args['meetings'])) {
            return $servicesErrorMessage;
        }

        // Custom CSS
        $content = "<style>{$this->settings->options['custom_css_um']}</style>";

        // Optional area filter: lets a viewer narrow a region to a single area, re-querying via AJAX.
        $areaFilter = ($args['show_area_filter'] == '1' && !empty($args['services']))
            ? $this->buildAreaFilter($args)
            : '';

        if ($areaFilter !== '') {
            wp_enqueue_script('upcoming-meetings-filter');
            $dataArgs = htmlspecialchars(wp_json_encode($this->ajaxArgs($args)), ENT_QUOTES, 'UTF-8');
            $displayClass = 'upcoming-meetings-display-' . preg_replace('/[^a-z]/', '', strtolower($args['display_type'] ?: 'simple'));
            $content .= '<div class="upcoming-meetings-widget ' . esc_attr($displayClass) . '">'
                . $areaFilter
                . '<div class="upcoming-meetings-results" data-um-args=\'' . $dataArgs . '\'>'
                . $this->buildMeetingsHtml($args)
                . '</div></div>';
        } else {
            $content .= $this->buildMeetingsHtml($args);
        }

        return $content;
    }

    /**
     * Build the meetings HTML for a given set of resolved arguments.
     *
     * Shared by the shortcode render and the AJAX area-filter handler so both produce identical markup.
     *
     * @param array $args The resolved shortcode/query arguments.
     * @return string The rendered meetings HTML (without the surrounding widget wrapper or custom CSS).
     */
    public function buildMeetingsHtml(array $args): string
    {
        // TZ must be valid so we default to one if it isn't
        $timezones = array_map('strtolower', \DateTimeZone::listIdentifiers());
        if (!in_array(strtolower($args['timezone']), $timezones)) {
            $args['timezone'] = 'America/New_York';
        }

        $meetingResults = $this->helper->getMeetingsJson(
            $args['root_server'],
            $args['services'],
            $args['timezone'],
            $args['grace_period'],
            $args['recursive'],
            $args['num_results'],
            $args['custom_query'],
            (bool)($args['limit_to_today'] ?? false),
            $args['meetings']
        );

        $outTimeFormat = ($args['time_format'] == '24') ? 'G:i' : 'g:i a';

        if (in_array($args['display_type'], ['table', 'block'])) {
            return '<div id="upcoming_meetings_div">'
                . $this->helper->meetingsJson2Html($meetingResults, $args['display_type'] === 'block', null, $outTimeFormat, $args['weekday_language'], $args['show_header'])
                . '</div>';
        }
        return $this->helper->renderMeetingsSimple($meetingResults, $args, $outTimeFormat, $args['weekday_language']);
    }

    /**
     * Build the area filter dropdown from the descendant service bodies of the configured region.
     *
     * @param array $args The resolved shortcode arguments.
     * @return string The dropdown HTML, or an empty string if the region has no descendant areas.
     */
    private function buildAreaFilter(array $args): string
    {
        $serviceIds = array_filter(array_map('trim', explode(',', $args['services'])), 'strlen');
        if (empty($serviceIds)) {
            return '';
        }
        $areas = $this->helper->getDescendantServiceBodies($args['root_server'], $serviceIds);
        if (empty($areas)) {
            return '';
        }

        $selectId = 'um_area_' . uniqid();
        $options = '<option value="all">All Areas</option>';
        foreach ($areas as $id => $name) {
            $options .= '<option value="' . esc_attr($id) . '">' . esc_html($name) . '</option>';
        }

        $label = trim($args['area_filter_label'] ?? '');
        $labelHtml = $label !== ''
            ? '<label class="upcoming-meetings-area-label" for="' . esc_attr($selectId) . '">' . esc_html($label) . '</label>'
            : '';

        return '<div class="upcoming-meetings-area-filter">'
            . $labelHtml
            . '<select id="' . esc_attr($selectId) . '" class="upcoming-meetings-area-select" aria-label="' . esc_attr($label !== '' ? $label : 'Filter by area') . '">'
            . $options
            . '</select></div>';
    }

    /**
     * The subset of resolved arguments needed to re-query meetings from the AJAX handler.
     *
     * @param array $args The resolved shortcode arguments.
     * @return array The arguments to embed on the widget for the AJAX re-query.
     */
    private function ajaxArgs(array $args): array
    {
        $keys = [
            'root_server', 'services', 'recursive', 'grace_period', 'num_results', 'timezone',
            'display_type', 'location_text', 'time_format', 'weekday_language', 'show_header',
            'limit_to_today', 'custom_query', 'meetings'
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $args[$key] ?? '';
        }
        return $out;
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
            // Cast to string: with no default service body configured, arraySafeGet returns null,
            // which would break the string-typed getMeetingsJson signature when only 'meetings' is used.
            'services'          => $servicesDropdown ?? '',
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
            'custom_query'      => $this->settings->options['custom_query'],
            'meetings'          => $this->settings->options['meetings'] ?? '',
            'show_area_filter'  => $this->settings->options['show_area_filter_checkbox'] ?? '0',
            'area_filter_label' => 'Filter by area'
        ];
    }
}
