<?php

namespace UpcomingMeetings;

class Helpers
{
    const BASE_API_ENDPOINT = "/client_interface/json/?switcher=";
    const HTTP_RETRIEVE_ARGS = [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:105.0) Gecko/20100101 Firefox/105.0 +UpcomingMeetingsBMLT'
        ],
        'timeout' => 300
    ];
    const MIDNIGHT = '00:00:00';
    const NOON = '12:00:00';

    public static function arraySafeGet(array $array, $key, $default = null)
    {
        return $array[$key] ?? $default;
    }

    private function getRemoteResponse(string $root_server, array $queryParams = [], string $switcher = 'GetSearchResults'): array
    {

        $url = $root_server . self::BASE_API_ENDPOINT . $switcher;

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


    public function testRootServer($root_server)
    {
        if (!$root_server) {
            return '';
        }
        $response = $this->getRemoteResponse($root_server, [], 'GetServerInfo');
        if (isset($response['error'])) {
            return $response['error'];
        }
        return (isset($response[0]) && is_array($response[0]) && array_key_exists("version", $response[0])) ? $response[0]["version"] : '';
    }

    public function getServiceBodies(string $root_server): array
    {
        return $this->getRemoteResponse($root_server, [], 'GetServiceBodies');
    }

    public function getAreas($root_server)
    {
        $results = $this->getServiceBodies($root_server);

        $unique_areas = [];
        foreach ($results as $value) {
            $parent_name = 'None';
            foreach ($results as $parent) {
                if ($value['parent_id'] == $parent['id']) {
                    $parent_name = $parent['name'];
                }
            }
            $unique_areas[] = $value['name'] . ',' . $value['id'] . ',' . $value['parent_id'] . ',' . $parent_name;
        }
        return $unique_areas;
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
    public function buildMeetingTime($inputTime, $outputFormat)
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
     * @param $root_server
     * @param $services
     * @param $timezone
     * @param $grace_period
     * @param $recursive
     * @param $num_results
     * @param $custom_query
     * @return array
     */
    public function getMeetingsJson($root_server, $services, $timezone, $grace_period, $recursive, $num_results, $custom_query)
    {
        $time_zone = new \DateTimeZone($timezone);

        $currentTime = new \DateTime(null, $time_zone);
        $currentTime->sub(new \DateInterval('PT' . $grace_period . 'M'));
        $hour = $currentTime->format('G');
        $minute = $currentTime->format('i');
        $dayOfWeek = intval($currentTime->format('w')) + 1;
        $nextDayOfWeek = ($dayOfWeek % 7) + 1;
        $url = $root_server . "/client_interface/json/?switcher=GetSearchResults" .
            "&weekdays={$dayOfWeek}&services={$services}" .
            "&StartsAfterH={$hour}&StartsAfterM={$minute}{$custom_query}" .
            ($recursive == "1" ? "&recursive=1" : "");
        $results = $this->httpGet($url);

        if (isset($results['error'])) {
            return $results['error'];
        }

        $results_count = count($results);

        if ($results_count < $num_results) {
            $url_addtl = $root_server . "/client_interface/json/?switcher=GetSearchResults" .
                "&weekdays={$nextDayOfWeek}&services={$services}{$custom_query}" .
                ($recursive == "1" ? "&recursive=1" : "");
            $results_addtl = $this->httpGet($url_addtl);
            if (isset($results_addtl['error'])) {
                return $results_addtl['error'];
            }
            $results = array_merge($results, $results_addtl);
        }
        return array_slice($results, 0, $num_results);
    }

    public function formatLocation($meeting, $selectedParts = null): string
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

    public function buildHtmlMapLink($meeting, $address): string
    {
        $latitude = $meeting['latitude'];
        $longitude = $meeting['longitude'];
        $mapUrl = "https://maps.google.com/maps?q=$latitude,$longitude";
        return '<a href="' . $mapUrl . '" target="_blank">' . $address . '</a>';
    }

    public function formatMeeting($meeting, $days_of_the_week, $in_time_format, $current_weekday, $in_block, $alt)
    {
        $weekday = htmlspecialchars($days_of_the_week[intval($meeting['weekday_tinyint'] - 1)]);
        $time = $this->buildMeetingTime($meeting['start_time'], $in_time_format);
        $name = htmlspecialchars(trim(stripslashes($meeting['meeting_name']))) ?: 'NA Meeting';
        $formats = htmlspecialchars(trim(stripslashes($meeting['formats'])));
        $address = $this->formatLocation($meeting);
        $ret = $in_block ? '<div class="bmlt_simple_meeting_one_meeting_div bmlt_alt_' . intval($alt) . '">' : '<tr class="bmlt_simple_meeting_one_meeting_tr bmlt_alt_' . intval($alt) . '">';
        $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_town_div">' : '<td class="bmlt_simple_meeting_one_meeting_town_td">';
        $ret .= $this->formatLocation($meeting, ['location_municipality']);
        $ret .= $in_block ? '</div>' : '</td>';
        $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_name_div">' : '<td class="bmlt_simple_meeting_one_meeting_name_td">';
        $ret .= $name;
        $ret .= $in_block ? '</div>' : '</td>';
        $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_time_div">' : '<td class="bmlt_simple_meeting_one_meeting_time_td">';
        $ret .= $time;
        $ret .= $in_block ? '</div>' : '</td>';
        $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_weekday_div">' : '<td class="bmlt_simple_meeting_one_meeting_weekday_td">';
        $ret .= $weekday;
        $ret .= $in_block ? '</div>' : '</td>';
        $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_address_div">' : '<td class="bmlt_simple_meeting_one_meeting_address_td">';
        $ret .= $this->buildHtmlMapLink($meeting, $address);
        $ret .= $in_block ? '</div>' : '</td>';
        $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_format_div">' : '<td class="bmlt_simple_meeting_one_meeting_format_td">';
        $ret .= $formats;
        $ret .= $in_block ? '</div>' : '</td>';
        $ret .= $in_block ? '<div class="bmlt_clear_div"></div></div>' : '</tr>';

        return [$ret, $current_weekday];
    }

    /*******************************************************************/
    /**
     * \brief  This returns the search results, in whatever form was requested.
     * \returns XHTML data. It will either be a table, or block elements.
     * @param $results
     * @param bool $in_block
     * @param null $in_container_id
     * @param null $in_time_format
     * @param null $days_of_the_week
     * @return string
     */
    public function meetingsJson2Html(
        $results,                ///< The results.
        $in_block = false,       ///< If this is true, the results will be sent back as block elements (div tags), as opposed to a table. Default is false.
        $in_container_id = null, ///< This is an optional ID for the "wrapper."
        $in_time_format = null,  // Time format
        $weekday_language = null
    ) {
        $days_of_the_week = $this->getTranslatedDaysOfWeek($weekday_language);
        $ret = '';

        // What we do, is to parse the JSON return. We'll pick out certain fields, and format these into a table or block element return.
        if ($results) {
            if (is_array($results) && count($results)) {
                $ret = $in_block ? '<div class="bmlt_simple_meetings_div"' . ($in_container_id ? ' id="' . htmlspecialchars($in_container_id) . '"' : '') . '>' : '<table class="bmlt_simple_meetings_table"' . ($in_container_id ? ' id="' . htmlspecialchars($in_container_id) . '"' : '') . ' cellpadding="0" cellspacing="0" summary="Meetings">';
                $result_keys = [];
                foreach ($results as $sub) {
                    $result_keys = array_merge($result_keys, $sub);
                }
                $keys = array_keys($result_keys);
                $current_weekday = -1;

                for ($count = 0; $count < count($results); $count++) {
                    $meeting = $results[$count];
                    $alt = $count % 2;

                    if ($meeting && is_array($meeting) && count($meeting)) {
                        if (count($meeting) > count($keys)) {
                            $keys[] = 'unused';
                        }

                        list($formattedMeeting, $current_weekday) = $this->formatMeeting($meeting, $days_of_the_week, $in_time_format, $current_weekday, $in_block, $alt);
                        $ret .= $formattedMeeting;
                    }
                }
                $ret .= $in_block ? '</div>' : '</table>';
            }
        }
        return $ret;
    }

    public function isValidUrl($url)
    {
        return wp_http_validate_url($url) !== false;
    }

    public function validateNumber($number)
    {
        return filter_var($number, FILTER_SANITIZE_NUMBER_INT) ? true : false;
    }

    public function renderMeetingsSimple($meetings, $args, $timeFormat, $weekday_language): string
    {
        $days_of_week = $this->getTranslatedDaysOfWeek($weekday_language);
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
                $content .= "<div class='upcoming-meetings-map-link'><a href='https://maps.google.com/maps?q=" . $meeting['latitude'] . "," . $meeting['longitude'] . "' target='new'>Map</a></div>";
            }

            // Virtual Meeting
            if (in_array("VM", $formats)) {
                if ($this->isValidUrl($meeting['virtual_meeting_link'])) {
                    $content .= '<div class="upcoming-meetings-virtual-link"><a href="' . $meeting['virtual_meeting_link'] . '" target="new" class="um_virtual_a">' . $meeting['virtual_meeting_link'] . '</a></div>';
                }
                if ($meeting['virtual_meeting_additional_info']) {
                    $content .= '<div class="upcoming-meetings-virtual-additional-info">' . $meeting['virtual_meeting_additional_info'] . '</div>';
                }
                if ($this->validateNumber($meeting['phone_meeting_number'])) {
                    $content .= '<div class="upcoming-meetings-phone-link"><a href="tel:' . $meeting['phone_meeting_number'] . '" target="new" class="um_tel_a">' . $meeting['virtual_meeting_link'] . '</a></div>';
                }
            }

            // Separator
            $content .= "<div class='upcoming-meetings-break'>";
            $content .= "<hr class='upcoming-meetings-horizontal-rule'>";
            $content .= "</div>";
        }

        return $content;
    }

    public function getLangInfo(): array
    {
        $langs = "zq,da,de,en,es,fa,fr,it,pl,pt,ru,sv";
        $langs = explode(",", $langs);
        $ret = [];

        foreach ($langs as $lang) {
            $daysOfWeek = [];
            $lang_name = \Locale::getDisplayLanguage($lang, $lang);
            $lang_name_en = \Locale::getDisplayLanguage($lang, 'en');
            if (strlen($lang_name) < 3) {
                // If locale is invalid getDisplayLanguage just returns the provided locale
                // So this is how we handle errors as any language should have more than two chars
                // Skip language and move to the next one.
                continue;
            }
            // Capitalize the first letter of the language name
            $firstChar = mb_substr($lang_name, 0, 1, "utf-8");
            $then = mb_substr($lang_name, 1, null, "utf-8");
            $lang_name = mb_strtoupper($firstChar, "utf-8") . $then;

            // Populate the language data
            $ret[$lang]['name'] = $lang_name;
            $ret[$lang]['code'] = $lang;
            $ret[$lang]['en_name'] = $lang_name_en;

            // Populate the days of the week
            for ($i = 0; $i < 7; $i++) {
                $dateTime = new \DateTime("Sunday +{$i} days");
                $day = ucfirst(\IntlDateFormatter::formatObject($dateTime, 'cccc', $lang));
                $daysOfWeek[] = $day;
            }

            $ret[$lang]['days_of_week'] = $daysOfWeek;
        }
        return $ret;
    }

    public function getTranslatedDaysOfWeek($selectedLang): array
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
}
