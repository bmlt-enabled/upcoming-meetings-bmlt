<?php

namespace UpcomingMeetings;

require_once 'Settings.php';
require_once 'Helpers.php';

/**
 * Class FormatsShortcode
 * @package UpcomingMeetings
 */
class FormatsShortcode
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
     * Render the meeting formats based on shortcode attributes.
     *
     * This method is responsible for rendering meeting formats based on the provided shortcode attributes.
     * It supports two display types: 'table' for HTML table display and 'list' for CSS list display.
     *
     * @param array $atts An associative array of shortcode attributes.
     * @return string The rendered content as a string.
     */
    public function render($atts = []): string
    {
        $defaults = [
            'root_server' => $this->settings->options['root_server'],
            'display_type' => 'table', // 'table' or 'list'
            'show_description' => '1',
            'language' => 'en'
        ];

        $args = shortcode_atts($defaults, $atts);

        // Error message
        $rootServerErrorMessage = '<p><strong>Meeting Formats Error: Root Server missing. Please verify you have entered a Root Server.</strong></p>';

        // Check for missing required values
        if (empty($args['root_server'])) {
            return $rootServerErrorMessage;
        }

        // Get format results
        $formatResults = $this->helper->getFormatsJson($args['root_server'], $args['language']);

        if (isset($formatResults['error'])) {
            return '<p><strong>Meeting Formats Error: ' . esc_html($formatResults['error']) . '</strong></p>';
        }

        // Custom CSS
        $content = "<style>{$this->settings->options['custom_css_um']}</style>";

        // Render based on display type
        if ($args['display_type'] === 'list') {
            $content .= $this->renderFormatsList($formatResults, (bool)$args['show_description']);
        } else {
            $content .= $this->renderFormatsTable($formatResults, (bool)$args['show_description']);
        }

        return $content;
    }

    /**
     * Render formats as an HTML table.
     *
     * @param array $formats The array of format data from the BMLT server.
     * @param bool $showDescription Whether to display format descriptions.
     * @return string The HTML table content.
     */
    private function renderFormatsTable(array $formats, bool $showDescription): string
    {
        if (empty($formats)) {
            return '<p>No formats available.</p>';
        }

        $content = '<div id="meeting_formats_div">';
        $content .= '<table class="bmlt_formats_table" cellpadding="0" cellspacing="0">';
        $content .= '<thead><tr>';
        $content .= '<th class="format_key">Code</th>';
        $content .= '<th class="format_name">Name</th>';
        if ($showDescription) {
            $content .= '<th class="format_description">Description</th>';
        }
        $content .= '</tr></thead>';
        $content .= '<tbody>';

        $alt = 0;
        foreach ($formats as $format) {
            $keyString = esc_html($format['key_string']);
            $nameString = esc_html($format['name_string']);
            $descriptionString = $showDescription ? esc_html($format['description_string']) : '';

            $content .= '<tr class="bmlt_format_row bmlt_alt_' . $alt . '">';
            $content .= '<td class="format_key">';
            $content .= '<span class="format_code_badge">' . $keyString . '</span>';
            $content .= '</td>';
            $content .= '<td class="format_name">' . $nameString . '</td>';
            if ($showDescription) {
                $content .= '<td class="format_description">' . $descriptionString . '</td>';
            }
            $content .= '</tr>';

            $alt = ($alt + 1) % 2;
        }

        $content .= '</tbody>';
        $content .= '</table>';
        $content .= '</div>';

        return $content;
    }

    /**
     * Render formats as a CSS-styled list.
     *
     * @param array $formats The array of format data from the BMLT server.
     * @param bool $showDescription Whether to display format descriptions.
     * @return string The HTML list content.
     */
    private function renderFormatsList(array $formats, bool $showDescription): string
    {
        if (empty($formats)) {
            return '<p>No formats available.</p>';
        }

        $content = '<div id="meeting_formats_div">';
        $content .= '<ul class="bmlt_formats_list">';

        foreach ($formats as $format) {
            $keyString = esc_html($format['key_string']);
            $nameString = esc_html($format['name_string']);
            $descriptionString = $showDescription ? esc_html($format['description_string']) : '';

            $content .= '<li class="bmlt_format_item">';
            $content .= '<div class="bmlt_format_content">';
            $content .= '<span class="format_code_badge">' . $keyString . '</span>';
            $content .= '<span class="format_name">' . $nameString . '</span>';
            if ($showDescription && !empty($descriptionString)) {
                $content .= '<div class="format_description">' . $descriptionString . '</div>';
            }
            $content .= '</div>';
            $content .= '</li>';
        }

        $content .= '</ul>';
        $content .= '</div>';

        return $content;
    }
}
