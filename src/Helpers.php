<?php

namespace UpcomingMeetings;

/**
 * Class Helpers
 * @package UpcomingMeetings
 */
class Helpers
{
    /**
     * Base API endpoint for BMLT requests.
     *
     * This constant defines the base endpoint used for making BMLT API requests.
     */
    const BASE_API_ENDPOINT = "/client_interface/json/?switcher=";

    /**
     * HTTP retrieval arguments for API requests.
     *
     * This constant defines the HTTP retrieval arguments, including headers and timeout,
     * used for making API requests.
     */
    const HTTP_RETRIEVE_ARGS = [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:105.0) Gecko/20100101 Firefox/105.0 +UpcomingMeetingsBMLT'
        ],
        'timeout' => 300
    ];

    /**
     * Constant representing midnight time.
     *
     * This constant represents the time value for midnight (00:00:00).
     */
    const MIDNIGHT = '00:00:00';

    /**
     * Constant representing noon time.
     *
     * This constant represents the time value for noon (12:00:00).
     */
    const NOON = '12:00:00';

    /**
     * Translation data for multiple languages.
     *
     * This associative array contains translations for various languages, with each language
     * represented by its ISO language code. Each language array contains translations for various keys.
     *
     * Example Usage:
     * - To access the translation for 'Online Meeting Link' in English: TRANSLATIONS['en']['meeting_link']
     * - To access the translation for 'Map' in French: TRANSLATIONS['fr']['map']
     *
     * @var array $TRANSLATIONS
     */
    const TRANSLATIONS = [
        'da' => [
            'meeting_link' => 'Online Mødelink',
            'map' => 'Kort'
        ],
        'de' => [
            'meeting_link' => 'Link zum Online-Meeting',
            'map' => 'Karte'
        ],
        'en' => [
            'meeting_link' => 'Online Meeting Link',
            'map' => 'Map'
        ],
        'es' => [
            'meeting_link' => 'Enlace de Reunión en Línea',
            'map' => 'Mapa'
        ],
        'fa' => [
            'meeting_link' => 'لینک جلسه آنلاین',
            'map' => 'نقشه'
        ],
        'fr' => [
            'meeting_link' => 'Lien de Réunion en Ligne',
            'map' => 'Carte'
        ],
        'it' => [
            'meeting_link' => 'Link per Riunione Online',
            'map' => 'Mappa'
        ],
        'pl' => [
            'meeting_link' => 'Link do Spotkania Online',
            'map' => 'Mapa'
        ],
        'pt' => [
            'meeting_link' => 'Link de Reunião Online',
            'map' => 'Mapa'
        ],
        'ru' => [
            'meeting_link' => 'Ссылка на Онлайн-собрание',
            'map' => 'Карта'
        ],
        'sv' => [
            'meeting_link' => 'Länk till Online-möte',
            'map' => 'Karta'
        ]
    ];


    /**
     * Safely retrieve a value from an associative array.
     *
     * This static method allows you to safely retrieve a value from an associative array
     * by providing a key and an optional default value to return if the key is not found.
     *
     * @param array $array An associative array from which to retrieve the value.
     * @param mixed $key The key to look up in the array.
     * @param mixed $default (optional) The default value to return if the key is not found. Defaults to null.
     * @return mixed The value associated with the key, or the default value if the key is not found.
     */
    public static function arraySafeGet(array $array, $key, $default = null)
    {
        return $array[$key] ?? $default;
    }


    /**
     * Get a remote JSON response from the specified BMLT server.
     *
     * This private method sends an HTTP GET request to a BMLT server with optional query parameters
     * and retrieves a JSON response. It handles errors, JSON decoding, and empty responses.
     *
     * @param string $rootServer The root server URL to send the request to.
     * @param array $queryParams (optional) An associative array of query parameters to include in the request.
     * @param string $switcher (optional) The switcher parameter for the API request. Defaults to 'GetSearchResults'.
     * @return array An associative array representing the JSON response or an error message.
     */
    private function getRemoteResponse(string $rootServer, array $queryParams = [], string $switcher = 'GetSearchResults'): array
    {

        $url = $rootServer . self::BASE_API_ENDPOINT . $switcher;

        if (!empty($queryParams)) {
            $url .= '&' . http_build_query($queryParams);
        }

        $response = wp_remote_get($url, self::HTTP_RETRIEVE_ARGS);

        if (is_wp_error($response)) {
            return ['error' => 'Error fetching data from server: ' . $response->get_error_message()];
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Error decoding JSON response.'];
        }
        if (empty($data)) {
            return ['error' => 'Received empty data from server.'];
        }
        return $data;
    }


    /**
     * Tests the root server and retrieves its version information.
     *
     * This function sends a test request to the specified root server to check its availability
     * and retrieves version information if available.
     *
     * @param string $rootServer The root server URL to test.
     *
     * @return string|bool The version information of the root server if available, a specific error message,
     *                    or false if the server is not responsive or an error occurs.
     */
    public function testRootServer(string $rootServer): string|bool
    {
        if (empty($rootServer)) {
            return "Error: Root server URL is empty";
        }

        $response = $this->getRemoteResponse($rootServer, [], 'GetServerInfo');

        if (isset($response['error'])) {
            return "Error: " . $response['error'];
        }

        if (!isset($response[0]) || !is_array($response[0]) || !array_key_exists("version", $response[0])) {
            return "Error: Invalid server response format";
        }

        return $response[0]["version"];
    }

    /**
     * Retrieves service bodies data from a remote server.
     *
     * This function sends a request to the specified root server to retrieve service bodies data.
     * The data is typically returned in an array format.
     *
     * @param string $rootServer The root server URL from which to fetch service bodies data.
     *
     * @return array An array containing service bodies data, typically in associative format.
     *               Returns an array with an 'error' key if an error occurs.
     */
    public function getServiceBodies(string $rootServer): array
    {
        if (empty($rootServer)) {
            return ['error' => 'Root server URL is empty.'];
        }

        return $this->getRemoteResponse($rootServer, [], 'GetServiceBodies');
    }

    /**
     * Retrieves and processes areas data from a root server.
     *
     * This function retrieves data related to areas from a specified root server
     * and processes it to create an array containing information about each area,
     * including name, ID, parent ID, and parent name.
     *
     * @param string $rootServer The root server from which to retrieve areas data.
     *
     * @return array An array of area information, where each element is a concatenated
     *               string in the format "name,ID,parent_id,parent_name".
     */
    public function getAreas(string $rootServer): array
    {
        $results = $this->getServiceBodies($rootServer);

        // Check if results contain an error
        if (empty($results) || isset($results['error'])) {
            return [];
        }

        $uniqueAreas = [];
        foreach ($results as $value) {
            $parent_name = 'None';
            foreach ($results as $parent) {
                if ($value['parent_id'] == $parent['id']) {
                    $parent_name = $parent['name'];
                }
            }
            $uniqueAreas[] = $value['name'] . ',' . $value['id'] . ',' . $value['parent_id'] . ',' . $parent_name;
        }
        return $uniqueAreas;
    }

    /*******************************************************************/
    /** \brief This creates a time string to be displayed for the meeting.
     * The display is done in non-military time, and "midnight" and
     * "noon" are substituted for 12:59:00, 00:00:00 and 12:00:00
     *
     * \returns a string, containing the HTML rendered by the function.
     * @param $inputTime
     * @param $outputFormat
     * @return string
     */
    public function buildMeetingTime(string $inputTime, string $outputFormat): string
    {
        if ($inputTime === self::MIDNIGHT && $outputFormat === 'g:i A') {
            return htmlspecialchars('Midnight');
        } elseif ($inputTime === self::NOON && $outputFormat === 'g:i A') {
            return htmlspecialchars('Noon');
        } else {
            return htmlspecialchars(date($outputFormat, strtotime($inputTime)));
        }
    }

    /**
     * Perform an HTTP GET request
     *
     * @param string $url The URL to make the GET request to.
     *
     * @return array|WP_Error An array with the response data or a WP_Error object on failure.
     */
    public function httpGet($url)
    {
        $response = wp_remote_get($url, self::HTTP_RETRIEVE_ARGS);
        if (is_wp_error($response)) {
            return ['error' => 'An error occurred while fetching data.'];
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Error decoding JSON response.'];
        }
        return $data;
    }

    /**
     * Retrieves JSON data for meetings based on specified parameters.
     *
     * This function sends a request to the specified root server to retrieve JSON data
     * for meetings that match the given criteria, including the day of the week, services,
     * start time, and custom query. It can retrieve both current and additional meetings.
     *
     * @param string $rootServer   The root server URL from which to fetch meeting data.
     * @param string $services     A comma-separated list of service IDs to filter meetings by.
     * @param string $timezone     The timezone to use for date and time calculations.
     * @param int    $gracePeriod  The grace period in minutes to consider when filtering meetings.
     * @param bool   $recursive    Indicates whether to fetch recursive meetings (1 for true, 0 for false).
     * @param int    $numResults   The maximum number of meeting results to return.
     * @param string $customQuery  Custom query parameters to add to the request URL.
     * @param bool   $limitToToday If true, only return meetings from today (don't fetch tomorrow's meetings).
     *
     * @return array An array of meeting data in JSON format if successful.
     *                If an error occurs, an error message is returned.
     */
    public function getMeetingsJson(
        string $rootServer,
        string $services,
        string $timezone,
        int $gracePeriod,
        bool $recursive,
        int $numResults,
        string $customQuery,
        bool $limitToToday = false
    ): array {

        $modifiedServicesString = '';
        foreach (explode(',', $services) as $id) {
            $modifiedServicesString .= '&services[]=' . $id;
        }

        $time_zone = new \DateTimeZone($timezone);
        $currentTime = new \DateTime('now', $time_zone);
        $currentTime->sub(new \DateInterval('PT' . $gracePeriod . 'M'));
        $hour = $currentTime->format('G');
        $minute = $currentTime->format('i');
        $dayOfWeek = intval($currentTime->format('w')) + 1;
        $nextDayOfWeek = ($dayOfWeek % 7) + 1;
        $url = $rootServer . "/client_interface/json/?switcher=GetSearchResults" .
            "&weekdays={$dayOfWeek}$modifiedServicesString" .
            "&StartsAfterH={$hour}&StartsAfterM={$minute}{$customQuery}" .
            ($recursive == "1" ? "&recursive=1" : "");
        $results = $this->httpGet($url);

        if (isset($results['error'])) {
            return ['error' => $results['error']];
        }

        $results_count = count($results);

        if (!$limitToToday && $results_count < $numResults) {
            $url_addtl = $rootServer . "/client_interface/json/?switcher=GetSearchResults" .
                "&weekdays={$nextDayOfWeek}{$modifiedServicesString}{$customQuery}" .
                ($recursive == "1" ? "&recursive=1" : "");
            $results_addtl = $this->httpGet($url_addtl);
            if (isset($results_addtl['error'])) {
                return ['error' => $results_addtl['error']];
            }
            $results = array_merge($results, $results_addtl);
        }
        return array_slice($results, 0, $numResults);
    }

    /**
     * Format the meeting location based on selected parts or defaults.
     *
     * @param array $meeting         The meeting data containing location information.
     * @param array|null $selectedParts Optional. An array of selected location parts to include.
     *                                 If not provided, defaults will be used.
     *
     * @return string The formatted location string.
     */
    public function formatLocation(array $meeting, array $selectedParts = null): string
    {
        $location_defaults = [
            'location_text',
            'location_street',
            'location_city_subsection',
            'location_neighborhood',
        ];

        $location_parts = $selectedParts ?? $location_defaults;

        $location_values = [];

        foreach ($location_parts as $part) {
            $value = htmlspecialchars(trim(stripslashes($meeting[$part])));
            if ($value) {
                $location_values[] = $value;
            }
        }

        return implode(', ', $location_values);
    }

    /**
     * Format virtual meeting links and phone meeting numbers for display.
     *
     * @param array $meeting   The meeting data containing virtual and phone meeting information.
     * @param array $formats   An array of meeting formats.
     *
     * @return string The formatted HTML content with links and numbers.
     */
    public function formatVirtualLink(array $meeting, array $formats, string $weekdayLanguage): string
    {
        $content = '';
        if ($this->isValidUrl($meeting['virtual_meeting_link'])) {
            if (in_array("HY", $formats)) {
                $content .= '<br>';
            }
            $content .= '<a href="' . $meeting['virtual_meeting_link'] . '" target="new" class="um_virtual_a">' . $this->translateText('meeting_link', $weekdayLanguage) . '</a>';
        }
        if ($this->validateNumber($meeting['phone_meeting_number'])) {
            if ($this->isValidUrl($meeting['virtual_meeting_link'])) {
                $content .= '&nbsp;&nbsp;&nbsp;<br>';
            }
            $content .= '<a href="tel:' . $meeting['phone_meeting_number'] . '" target="new" class="um_tel_a">' . $meeting['phone_meeting_number'] . '</a>';
        }
        // Add virtual meeting additional info for any virtual or hybrid meetings
        if ((in_array("VM", $formats) || in_array("HY", $formats)) && $meeting['virtual_meeting_additional_info']) {
            $content .= '<br>' . htmlspecialchars($meeting['virtual_meeting_additional_info']);
        }
        return $content;
    }

    /**
     * Build HTML content for meeting location information.
     *
     * @param array  $meeting  The meeting data containing location information.
     * @param string $address  The address associated with the meeting location.
     * @param array  $formats  An array of meeting formats.
     *
     * @return string The formatted HTML content for location information.
     */
    public function buildHtmlLocationInfo(array $meeting, string $address, array $formats): string
    {
        $latitude = $meeting['latitude'];
        $longitude = $meeting['longitude'];
        $mapUrl = "https://maps.google.com/maps?q=$latitude,$longitude";

        $content = '<a href="' . $mapUrl . '" target="_blank">' . $address . '</a>';

        // For VM-only meetings, don't show address info (it's all in the Location column)
        if (in_array("VM", $formats) && !in_array("HY", $formats)) {
            $content = '';
        }

        return $content;
    }


    /**
     * Formats a meeting for display.
     *
     * This function takes meeting data and formats it into HTML for display, including
     * details like meeting time, name, formats, location, and more.
     *
     * @param array    $meeting          The meeting data to be formatted.
     * @param string[] $daysOfTheWeek  An array of days of the week.
     * @param string   $inTimeFormat    The format for displaying meeting times.
     * @param bool     $inBlock         True if the meeting should be in a block element, false for table row.
     * @param int      $alt              An integer representing the alternative styling (0 or 1).
     *
     * @return string Containing the formatted HTML content.
     */
    public function formatMeeting(array $meeting, array $daysOfTheWeek, string $inTimeFormat, bool $inBlock, int $alt, string $weekdayLanguage): string
    {
        $weekday = htmlspecialchars($daysOfTheWeek[intval($meeting['weekday_tinyint'])]);
        $time = $this->buildMeetingTime($meeting['start_time'], $inTimeFormat);
        $name = htmlspecialchars(trim(stripslashes($meeting['meeting_name']))) ?: 'NA Meeting';
        $formats = htmlspecialchars(trim(stripslashes($meeting['formats'])));
        $formatsArray = explode(",", $formats);
        $address = $this->formatLocation($meeting);
        $content = $inBlock ? '<div class="bmlt_simple_meeting_one_meeting_div bmlt_alt_' . $alt . '">' : '<tr class="bmlt_simple_meeting_one_meeting_tr bmlt_alt_' . $alt . '">';
        $content .= $inBlock ? '<div class="bmlt_simple_meeting_one_meeting_town_div">' : '<td class="bmlt_simple_meeting_one_meeting_town_td">';
        // Is Meeting Virtual Only
        if (in_array("VM", $formatsArray) && !in_array("HY", $formatsArray)) {
            $content .= $this->formatVirtualLink($meeting, $formatsArray, $weekdayLanguage);
        } else if (in_array("HY", $formatsArray)) {
            $content .= $this->formatLocation($meeting, ['location_municipality']);
            $content .= $this->formatVirtualLink($meeting, $formatsArray, $weekdayLanguage);
        } else {
            $content .= $this->formatLocation($meeting, ['location_municipality']);
        }
        $content .= $inBlock ? '</div>' : '</td>';
        $content .= $inBlock ? '<div class="bmlt_simple_meeting_one_meeting_name_div">' : '<td class="bmlt_simple_meeting_one_meeting_name_td">';
        $content .= $name;
        $content .= $inBlock ? '</div>' : '</td>';
        $content .= $inBlock ? '<div class="bmlt_simple_meeting_one_meeting_time_div">' : '<td class="bmlt_simple_meeting_one_meeting_time_td">';
        $content .= $time;
        $content .= $inBlock ? '</div>' : '</td>';
        $content .= $inBlock ? '<div class="bmlt_simple_meeting_one_meeting_weekday_div">' : '<td class="bmlt_simple_meeting_one_meeting_weekday_td">';
        $content .= $weekday;
        $content .= $inBlock ? '</div>' : '</td>';
        $content .= $inBlock ? '<div class="bmlt_simple_meeting_one_meeting_address_div">' : '<td class="bmlt_simple_meeting_one_meeting_address_td">';
        $content .= $this->buildHtmlLocationInfo($meeting, $address, $formatsArray);
        $content .= $inBlock ? '</div>' : '</td>';
        $content .= $inBlock ? '<div class="bmlt_simple_meeting_one_meeting_format_div">' : '<td class="bmlt_simple_meeting_one_meeting_format_td">';
        $content .= $formats;
        $content .= $inBlock ? '</div>' : '</td>';
        $content .= $inBlock ? '<div class="bmlt_clear_div"></div></div>' : '</tr>';

        return $content;
    }

    /*******************************************************************/
    /**
     * \brief  This returns the search results, in whatever form was requested.
     * \returns XHTML data. It will either be a table, or block elements.
     * @param $results
     * @param bool $inBlock
     * @param null $in_container_id
     * @param null $inTimeFormat
     * @param null $weekdayLanguage
     * @param null $showHeader
     * @return string
     */
    public function meetingsJson2Html(
        $results,                ///< The results.
        $inBlock = false,       ///< If this is true, the results will be sent back as block elements (div tags), as opposed to a table. Default is false.
        $in_container_id = null, ///< This is an optional ID for the "wrapper."
        $inTimeFormat = null,  // Time format
        $weekdayLanguage = null,
        $showHeader = null
    ) {
        $daysOfTheWeek = $this->getTranslatedDaysOfWeek($weekdayLanguage);
        $content = '';
        $headerRow = $inBlock
            ? '<div class="bmlt_simple_meeting_one_meeting_header_div">
        <div class="bmlt_simple_meeting_one_meeting_header_town_div">Location</div>
        <div class="bmlt_simple_meeting_one_meeting_header_name_div">Name</div>
        <div class="bmlt_simple_meeting_one_meeting_header_time_div">Time</div>
        <div class="bmlt_simple_meeting_one_meeting_header_weekday_div">Day</div>
        <div class="bmlt_simple_meeting_one_meeting_header_address_div">Info</div>
        <div class="bmlt_simple_meeting_one_meeting_header_format_div">Formats</div>
      </div>'
            : '<tr>
        <th>Location</th>
        <th>Name</th>
        <th>Time</th>
        <th>Day</th>
        <th>Info</th>
        <th>Formats</th>
      </tr>';

        // What we do, is to parse the JSON return. We'll pick out certain fields, and format these into a table or block element return.
        if ($results) {
            if (is_array($results) && count($results)) {
                $content = $inBlock ? '<div class="bmlt_simple_meetings_div"' . ($in_container_id ? ' id="' . htmlspecialchars($in_container_id) . '"' : '') . '>' : '<table class="bmlt_simple_meetings_table"' . ($in_container_id ? ' id="' . htmlspecialchars($in_container_id) . '"' : '') . ' cellpadding="0" cellspacing="0" summary="Meetings">';
                $content .= $showHeader ? $headerRow : '';
                $result_keys = [];
                foreach ($results as $sub) {
                    $result_keys = array_merge($result_keys, $sub);
                }
                $keys = array_keys($result_keys);
                $currentWeekday = -1;

                for ($count = 0; $count < count($results); $count++) {
                    $meeting = $results[$count];
                    $alt = $count % 2;

                    if ($meeting && is_array($meeting) && count($meeting)) {
                        if (count($meeting) > count($keys)) {
                            $keys[] = 'unused';
                        }
                        $content .= $this->formatMeeting($meeting, $daysOfTheWeek, $inTimeFormat, $inBlock, $alt, $weekdayLanguage);
                        ;
                    }
                }
                $content .= $inBlock ? '</div>' : '</table>';
            }
        }
        return $content;
    }

    /**
     * Validates whether a given URL is valid.
     *
     * This function checks if the provided URL is valid using the wp_http_validate_url()
     * function and returns true if it's a valid URL, otherwise returns false.
     *
     * @param string $url The URL to be validated.
     *
     * @return bool True if the URL is valid, false otherwise.
     */
    public function isValidUrl(string $url): bool
    {
        return (bool) wp_http_validate_url($url);
    }

    /**
     * Validates whether a given value is a valid integer number.
     *
     * This function checks if the provided value is a valid integer by using
     * the FILTER_SANITIZE_NUMBER_INT filter and casting the result to a boolean.
     *
     * @param mixed $number The value to be validated.
     *
     * @return bool True if the value is a valid integer, false otherwise.
     */
    public function validateNumber($number): bool
    {
        return (bool)filter_var($number, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Renders a list of meetings as HTML content.
     *
     * This function takes an array of meetings, formatting them into HTML content
     * for display. It includes details like meeting time, location, formats, and links
     * for virtual meetings.
     *
     * @param array    $meetings         An array of meetings data.
     * @param array    $args             Additional arguments for rendering.
     * @param string   $timeFormat       The format for displaying meeting times.
     * @param string   $weekdayLanguage  The language for displaying weekdays.
     *
     * @return string  HTML content representing the meetings.
     */
    public function renderMeetingsSimple(array $meetings, array $args, string $timeFormat, string $weekdayLanguage): string
    {
        $days_of_week = $this->getTranslatedDaysOfWeek($weekdayLanguage);
        $content = '';
        foreach ($meetings as $meeting) {
            $formats = explode(",", $meeting['formats']);

            // Meeting information
            $content .= "<div class='upcoming-meetings-time-meeting-name'>" . date($timeFormat, strtotime($meeting['start_time'])) . "&nbsp;&nbsp;&nbsp;" . $days_of_week[$meeting['weekday_tinyint']] . "&nbsp;&nbsp;&nbsp;" . $meeting['meeting_name'] . "</div>";
            $content .= $args['location_text'] ? "<div class='upcoming-meetings-location-text'>" . $meeting['location_text'] . "</div>" : '';

            // Address info?
            if (in_array("VM", $formats) && !in_array("HY", $formats)) {
                $content .= "<div class='upcoming-meetings-formats'>" . $meeting['formats'] . '</div>';
            } else {
                $content .= "<div class='upcoming-meetings-location-address'>" . $meeting['location_street'] . "&nbsp;&nbsp;&nbsp;" . $meeting['location_municipality'] . ",&nbsp;" . $meeting['location_province'] . "&nbsp;" . $meeting['location_postal_code_1'] . '</div>';
                $content .= "<div class='upcoming-meetings-formats-location-info-comments'>" . $meeting['formats'] . "&nbsp;&nbsp;&nbsp;" . $meeting['location_info'] . "&nbsp;" . $meeting['comments'] . '</div>';
                $content .= "<div class='upcoming-meetings-map-link'><a href='https://maps.google.com/maps?q=" . $meeting['latitude'] . "," . $meeting['longitude'] . "' target='new'>" . $this->translateText('map', $weekdayLanguage) . "</a></div>";
            }

            // Virtual Meeting
            if (in_array("VM", $formats)) {
                if ($this->isValidUrl($meeting['virtual_meeting_link'])) {
                    $content .= '<div class="upcoming-meetings-virtual-link"><a href="' . $meeting['virtual_meeting_link'] . '" target="new" class="um_virtual_a">' . $this->translateText('meeting_link', $weekdayLanguage) . '</a></div>';
                }
                if ($meeting['virtual_meeting_additional_info']) {
                    $content .= '<div class="upcoming-meetings-virtual-additional-info">' . $meeting['virtual_meeting_additional_info'] . '</div>';
                }
                if ($this->validateNumber($meeting['phone_meeting_number'])) {
                    $content .= '<div class="upcoming-meetings-phone-link"><a href="tel:' . $meeting['phone_meeting_number'] . '" target="new" class="um_tel_a">' . $meeting['phone_meeting_number'] . '</a></div>';
                }
            }

            $content .= "<div class='upcoming-meetings-break'>";
            $content .= "<hr class='upcoming-meetings-horizontal-rule'>";
            $content .= "</div>";
        }

        return $content;
    }

    /**
     * Get language information including translated days of the week.
     *
     * This function retrieves language information for a predefined set of languages
     * and includes translated days of the week for each language.
     *
     * @return array An associative array with language information including names, codes, and days of the week.
     */
    public function getLangInfo(): array
    {
        $langs = ['da', 'de', 'en', 'es', 'fa', 'fr', 'it', 'pl', 'pt', 'ru', 'sv'];
        $content = [];

        foreach ($langs as $lang) {
            $daysOfWeek = [];
            $langName = \Locale::getDisplayLanguage($lang, $lang);
            $langNameEn = \Locale::getDisplayLanguage($lang, 'en');
            if (strlen($langName) < 3) {
                // If locale is invalid getDisplayLanguage just returns the provided locale
                // So this is how we handle errors as any language should have more than two chars
                // Skip language and move to the next one.
                continue;
            }
            // Capitalize the first letter of the language name
            $firstChar = mb_substr($langName, 0, 1, "utf-8");
            $then = mb_substr($langName, 1, null, "utf-8");
            $langName = mb_strtoupper($firstChar, "utf-8") . $then;

            // Populate the language data
            $content[$lang]['name'] = $langName;
            $content[$lang]['code'] = $lang;
            $content[$lang]['en_name'] = $langNameEn;

            // Populate the days of the week
            for ($i = 0; $i < 7; $i++) {
                $dateTime = new \DateTime("Sunday +{$i} days");
                $day = ucfirst(\IntlDateFormatter::formatObject($dateTime, 'cccc', $lang));
                // BMLT and all code that uses this function expects 1 to be Sunday
                $daysOfWeek[$i + 1] = $day;
            }

            $content[$lang]['days_of_week'] = $daysOfWeek;
        }
        return $content;
    }

    /**
     * Get translated days of the week for a selected language.
     *
     * This function retrieves the translated days of the week for a selected language.
     *
     * @param string $selectedLang The selected language code.
     * @return array An array of translated days of the week.
     */
    public function getTranslatedDaysOfWeek(string $selectedLang): array
    {
        $langs = $this->getLangInfo();
        // set fallback to english
        $dow = $langs['en']['days_of_week'];
        foreach ($langs as $key => $name) {
            if ($selectedLang === $key) {
                $dow =  $name['days_of_week'];
            }
        }
        return $dow;
    }

    /**
     * Translate text to the specified language with fallback to 'en' language.
     *
     * @param string $key      The key for the text you want to translate.
     * @param string $language (Optional) The target language for the translation. Default is 'en'.
     *
     * @return string The translated text, 'en' translation, or the input key if not found.
     */
    public function translateText(string $key, string $language = 'en'): string
    {
        if (array_key_exists($language, self::TRANSLATIONS)) {
            if (array_key_exists($key, self::TRANSLATIONS[$language])) {
                return self::TRANSLATIONS[$language][$key];
            }
        }
        if (array_key_exists('en', self::TRANSLATIONS)) {
            if (array_key_exists($key, self::TRANSLATIONS['en'])) {
                return self::TRANSLATIONS['en'][$key];
            }
        }
        // If the language, key, or 'en' translation is not found, return the input key
        return $key;
    }
}
