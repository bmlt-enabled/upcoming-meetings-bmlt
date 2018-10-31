<?php
/*
Plugin Name: Upcoming Meetings BMLT
Plugin URI: https://wordpress.org/plugins/upcoming-meetings-bmlt/
Author: pjaudiomv
Description: This plugin returns all unique towns or counties for given service body on your site Simply add [list_locations] shortcode to your page and set shortcode attributes accordingly. Required attributes are root_server and services.
Version: 1.1.1
Install: Drop this directory into the "wp-content/plugins/" directory and activate it.
*/
/* Disallow direct access to the plugin file */
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    // die('Sorry, but you cannot access this page directly.');
}

if (!class_exists("UpcomingMeetings")) {
    class UpcomingMeetings {
        var $optionsName = 'upcoming_meetings_options';
        var $options = array();
        const http_retrieve_args = array(
            'headers' => array(
                'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0) +UpcomingMeetingsBMLT'
            ),
            'timeout' => 60
        );
        function __construct() {
            $this->getOptions();
            if (is_admin()) {
                // Back end
                add_action("admin_notices", array(&$this, "is_root_server_missing"));
                add_action("admin_enqueue_scripts", array(&$this, "enqueue_backend_files"),500);
                add_action("admin_menu", array(&$this, "admin_menu_link"));
            } else {
                // Front end
                add_action("wp_enqueue_scripts", array(&$this, "enqueue_frontend_files"));
                add_shortcode('upcoming_meetings', array(
                    &$this,
                    "upcoming_meetings"
                ));
            }
            // Content filter
            add_filter('the_content', array(
                &$this,
                'filter_content'
            ), 0);
        }

        function has_shortcode() {
            $post_to_check = get_post(get_the_ID());
            // check the post content for the short code
            if (stripos($post_to_check->post_content, '[upcoming_meetings') !== false) {
                return true;
            }
            return false;
        }

        function is_root_server_missing() {
            $root_server = $this->options['root_server'];
            if ($root_server == '') {
                echo '<div id="message" class="error"><p>Missing BMLT Root Server in settings for Upcoming Meetings BMLT.</p>';
                $url = admin_url('options-general.php?page=upcoming-meetings.php');
                echo "<p><a href='$url'>Upcoming Meetings BMLT Settings</a></p>";
                echo '</div>';
            }
            add_action("admin_notices", array(
                &$this,
                "clear_admin_message"
            ));
        }

        function clear_admin_message() {
            remove_action("admin_notices", array(
                &$this,
                "is_root_server_missing"
            ));
        }

        function UpcomingMeetings() {
            $this->__construct();
        }

        function filter_content($content) {
            return $content;
        }

        /**
        * @param $hook
        */
        function enqueue_backend_files($hook) {
            if ($hook == 'settings_page_upcoming-meetings') {
                wp_enqueue_style('upcoming-meetings-admin-ui-css',  plugins_url('css/redmond/jquery-ui.css', __FILE__), false, '1.11.4', false);
                wp_enqueue_style("chosen", plugin_dir_url(__FILE__) . "css/chosen.min.css", false, "1.2", 'all');
                wp_enqueue_script("chosen", plugin_dir_url(__FILE__) . "js/chosen.jquery.min.js", array('jquery'), "1.2", true);
                wp_enqueue_script('upcoming-meetings-admin', plugins_url('js/upcoming_meetings_admin.js', __FILE__), array('jquery'), filemtime( plugin_dir_path(__FILE__) . "js/upcoming_meetings_admin.js"), false);
                wp_enqueue_script('common');
                wp_enqueue_script('jquery-ui-accordion');
            }
        }

        function enqueue_frontend_files($hook) {
                wp_enqueue_style( 'upcoming-meetings', plugin_dir_url(__FILE__) . 'css/upcoming_meetings.css',false,'1.15','all');
        }

        function testRootServer($root_server) {
            $args = array(
                'timeout' => '10',
                'headers' => array(
                    'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0) +UpcomingMeetingsBMLT'
                )
            );
            $results = wp_remote_get("$root_server/client_interface/serverInfo.xml", $args);
            $httpcode = wp_remote_retrieve_response_code( $results );
            $response_message = wp_remote_retrieve_response_message( $results );
            if ($httpcode != 200 && $httpcode != 302 && $httpcode != 304 && ! empty( $response_message )) {
                //echo '<p>Problem Connecting to BMLT Root Server: ' . $root_server . '</p>';
                return false;
            };
            $results = simplexml_load_string(wp_remote_retrieve_body($results));
            $results = json_encode($results);
            $results = json_decode($results,true);
            $results = $results['serverVersion']['readableString'];
            return $results;
        }

        function upcoming_meetings($atts, $content = null) {
            global $unique_areas;
            extract(shortcode_atts(array(
                "root_server"  => '',
                'services'     =>  '',
                'recursive'    => '',
                'grace_period' => '',
                'num_results'  => '',
                'timezone'     => '',
                'display_type' => ''
            ), $atts));

            $area_data_dropdown   = explode(',', $this->options['service_body_dropdown']);
            $services_dropdown    = $area_data_dropdown[1];

            $root_server          = ($root_server  != '' ? $root_server  : $this->options['root_server']);
            $services             = ($services     != '' ? $services     : $services_dropdown);
            $recursive            = ($recursive    != '' ? $recursive    : $this->options['recursive']);
            $grace_period         = ($grace_period != '' ? $grace_period : $this->options['grace_period_dropdown']);
            $num_results          = ($num_results  != '' ? $num_results  : $this->options['num_results_dropdown']);
            $timezone             = ($timezone     != '' ? $timezone     : $this->options['timezones_dropdown']);
            $display_type         = ($display_type != '' ? $display_type : $this->options['display_type_dropdown']);

            $days_of_the_week = [1 => "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

            date_default_timezone_set($timezone);

            if ($root_server == '') {
                return '<p><strong>Upcoming Meetings Error: Root Server missing. Please Verify you have entered a Root Server using the \'root_server\' shortcode attribute</strong></p>';
            }
            if ($services == '') {
                return '<p><strong>Upcoming Meetings Error: Services missing. Please verify you have entered a service body id using the \'services\' shortcode attribute</strong></p>';
            }

            $output = '';
            $css_um = $this->options['custom_css_um'];

            $output .= "<style type='text/css'>$css_um</style>";

            $meeting_results = $this->getMeetingsJson($root_server, $services, $timezone, $grace_period, $recursive, $num_results);

            if ($display_type != '' && $display_type == 'table') {
                $output .= '<div id="upcoming_meetings_div">';
                $output .= $this->MeetingsJson2Html($meeting_results,false);
                $output .= '</div>';
            }

            if ($display_type != '' && $display_type == 'block') {
                $output .= '<div id="upcoming_meetings_div">';
                $output .= $this->MeetingsJson2Html($meeting_results,true);
                $output .= '</div>';
            }

            if ($display_type != 'table' && $display_type != 'block') {
                foreach ($meeting_results as $meeting) {
                    $output .= "<div class='upcoming-meetings-time-meeting-name'>" . date('g:i A',strtotime($meeting['start_time'])) . "&nbsp;&nbsp;&nbsp;" .$days_of_the_week[intval ( $meeting['weekday_tinyint'] )]. "&nbsp;&nbsp;&nbsp;" .$meeting['meeting_name'] . "</div>";
                    $output .= "<div class='upcoming-meetings-location-address'>" . $meeting['location_street'] . "&nbsp;&nbsp;&nbsp;" . $meeting['location_municipality'] . ",&nbsp;" . $meeting['location_province'] . "&nbsp;" . $meeting['location_postal_code_1'] . '</div>';
                    $output .= "<div class='upcoming-meetings-formats-location-info-comments'>" . $meeting['formats'] . "&nbsp;&nbsp;&nbsp;" . $meeting['location_info'] . "&nbsp;" . $meeting['comments'] . '</div>';
                    $output .= "<div class='upcoming-meetings-map-link'>" . "<a href='https://maps.google.com/maps?q=" . $meeting['latitude'] . "," . $meeting['longitude'] . "' target='new'>Map</a></div>";
                    $output .= "<div class='upcoming-meetings-break'>&nbsp;</div>";
                }
            }
            return $output;
        }

        /**
         * @desc Adds the options sub-panel
         */
        function get_areas($root_server) {
                $results = wp_remote_get("$root_server/client_interface/json/?switcher=GetServiceBodies", UpcomingMeetings::http_retrieve_args);
                $result = json_decode(wp_remote_retrieve_body($results), true);
                if (is_wp_error($results) ) {
                    echo '<div style="font-size: 20px;text-align:center;font-weight:normal;color:#F00;margin:0 auto;margin-top: 30px;"><p>Problem Connecting to BMLT Root Server</p><p>' . $root_server . '</p><p>Error: ' . $result->get_error_message() . '</p><p>Please try again later</p></div>';
                    return 0;
                }

                $unique_areas = array();
                foreach ($result as $value) {
                    $parent_name = 'None';
                    foreach ($result as $parent) {
                        if ( $value['parent_id'] == $parent['id'] ) {
                            $parent_name = $parent['name'];
                        }
                    }
                    $unique_areas[] = $value['name'] . ',' . $value['id'] . ',' . $value['parent_id'] . ',' . $parent_name;
                }
            return $unique_areas;
        }

        function admin_menu_link() {
            // If you change this from add_options_page, MAKE SURE you change the filter_plugin_actions function (below) to
            // reflect the page file name (i.e. - options-general.php) of the page your plugin is under!
            add_options_page('Upcoming Meetings BMLT', 'Upcoming Meetings BMLT', 'activate_plugins', basename(__FILE__), array(
                &$this,
                'admin_options_page'
            ));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(
                &$this,
                'filter_plugin_actions'
            ), 10, 2);
        }
        /**
         * Adds settings/options page
         */
        function admin_options_page() {
            if (!isset($_POST['upcomingmeetingssave'])) {
                $_POST['upcomingmeetingssave'] = false;
            }
            if ($_POST['upcomingmeetingssave']) {
                if (!wp_verify_nonce($_POST['_wpnonce'], 'upcomingmeetingsupdate-options'))
                    die('Whoops! There was a problem with the data you posted. Please go back and try again.');
                $this->options['root_server']            = esc_url_raw($_POST['root_server']);
                $this->options['service_body_dropdown']  = sanitize_text_field($_POST['service_body_dropdown']);
                $this->options['recursive']              = sanitize_text_field($_POST['recursive']);
                $this->options['grace_period_dropdown']  = sanitize_text_field($_POST['grace_period_dropdown']);
                $this->options['num_results_dropdown']   = sanitize_text_field($_POST['num_results_dropdown']);
                $this->options['timezones_dropdown']     = sanitize_text_field($_POST['timezones_dropdown']);
                $this->options['display_type_dropdown']  = sanitize_text_field($_POST['display_type_dropdown']);
                $this->options['custom_css_um']          = $_POST['custom_css_um'];

                $this->save_admin_options();
                echo '<div class="updated"><p>Success! Your changes were successfully saved!</p></div>';
            }
?>
            <div class="wrap">
                <h2>Upcoming Meetings BMLT</h2>
                <form style="display:inline!important;" method="POST" id="upcoming_meetings_options" name="upcoming_meetings_options">
                    <?php wp_nonce_field('upcomingmeetingsupdate-options'); ?>
                    <?php $this_connected = $this->testRootServer($this->options['root_server']); ?>
                    <?php $connect = "<p><div style='color: #f00;font-size: 16px;vertical-align: text-top;' class='dashicons dashicons-no'></div><span style='color: #f00;'>Connection to Root Server Failed.  Check spelling or try again.  If you are certain spelling is correct, Root Server could be down.</span></p>"; ?>
                    <?php if ( $this_connected != false) { ?>
                        <?php $connect = "<span style='color: #00AD00;'><div style='font-size: 16px;vertical-align: text-top;' class='dashicons dashicons-smiley'></div>Version ".$this_connected."</span>"?>
                        <?php $this_connected = true; ?>
                    <?php } ?>
                    <div style="margin-top: 20px; padding: 0 15px;" class="postbox">
                        <h3>BMLT Root Server URL</h3>
                        <p>Example: https://domain.org/main_server</p>
                        <ul>
                            <li>
                                <label for="root_server">Default Root Server: </label>
                                <input id="root_server" type="text" size="50" name="root_server" value="<?php echo $this->options['root_server']; ?>" /> <?php echo $connect; ?>
                            </li>
                        </ul>
                    </div>
                    <div style="padding: 0 15px;" class="postbox">
                        <h3>Service Body</h3>
                        <p>This service body will be used when no service body is defined in the shortcode.</p>
                        <ul>
                            <li>
                                <label for="service_body_dropdown">Default Service Body: </label>
                                <select style="display:inline;" onchange="getUpcomingMeetingsValueSelected()" id="service_body_dropdown" name="service_body_dropdown" class="upcoming_meetings_service_body_select">
                                <?php if ($this_connected) { ?>
                                    <?php $unique_areas = $this->get_areas($this->options['root_server']); ?>
                                    <?php asort($unique_areas); ?>
                                    <?php foreach ($unique_areas as $key => $unique_area) { ?>
                                        <?php $area_data          = explode(',', $unique_area); ?>
                                        <?php $area_name          = $area_data[0]; ?>
                                        <?php $area_id            = $area_data[1]; ?>
                                        <?php $area_parent        = $area_data[2]; ?>
                                        <?php $area_parent_name   = $area_data[3]; ?>
                                        <?php $option_description = $area_name . " (" . $area_id . ") " . $area_parent_name . " (" . $area_parent . ")" ?></option>
                                        <?php $is_data = explode(',',esc_html($this->options['service_body_dropdown'])); ?>
                                        <?php if ( $area_id == $is_data[1] ) { ?>
                                            <option selected="selected" value="<?php echo $unique_area; ?>"><?php echo $option_description; ?></option>
                                        <?php } else { ?>
                                            <option value="<?php echo $unique_area; ?>"><?php echo $option_description; ?></option>
                                        <?php } ?>
                                    <?php } ?>
                                <?php } else { ?>
                                    <option selected="selected" value="<?php echo $this->options['service_body_dropdown']; ?>"><?php echo 'Not Connected - Can not get Service Bodies'; ?></option>
                                <?php } ?>
                                </select>
                                <div style="display:inline; margin-left:15px;" id="txtSelectedValues1"></div>
                                <p id="txtSelectedValues2"></p>

                                <input type="checkbox" id="recursive" name="recursive" value="1" <?php echo ($this->options['recursive'] == "1" ? "checked" : "") ?>/>
                                <label for="recursive">Recurse Service Bodies</label>
                            </li>
                        </ul>
                    </div>
                    <div style="margin-top: 20px; padding: 0 15px;" class="postbox">
                        <h3>Attribute Options</h3>
                        <ul>
                            <li>
                                <label for="timezones_dropdown">Time Zone: </label>
                                <select style="display:inline;" id="timezones_dropdown" name="timezones_dropdown" class="upcoming_meetings_service_body_select">
                                    <option value=""></option>
                                    <?php
                                    $timezones_array = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
                                    foreach ($timezones_array as $tzItem) {
                                        if ($tzItem == $this->options['timezones_dropdown']) { ?>
                                            <option selected="selected" value="<?php echo $tzItem; ?>"><?php echo $tzItem; ?></option>
                                        <?php } else { ?>
                                            <option value="<?php echo $tzItem; ?>"><?php echo $tzItem; ?></option>
                                        <?php }
                                    } ?>
                                </select>
                            </li>
                            <li>
                                <label for="display_type_dropdown">Display Type: </label>
                                <select style="display:inline;" id="display_type_dropdown" name="display_type_dropdown"  class="display_type_select">
                                    <?php if ($this->options['display_type_dropdown'] == 'simple') { ?>
                                        <option selected="selected" value="simple">Simple</option>
                                        <option value="table">HTML (bmlt table)</option>
                                        <option value="block">HTML (bmlt block)</option>
                                        <?php
                                    }
                                    else if ($this->options['display_type_dropdown'] == 'table') { ?>
                                        <option value="simple">Simple</option>
                                        <option selected="selected" value="table">HTML (bmlt table)</option>
                                        <option value="block">HTML (bmlt block)</option>
                                    <?php
                                    } else { ?>
                                        <option value="simple">Simple</option>
                                        <option value="table">HTML (bmlt table)</option>
                                        <option selected="selected" value="block">HTML (bmlt block)</option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </li>
                            <li>
                                <label for="num_results_dropdown">Number of Results: </label>
                                <select style="display:inline;" id="num_results_dropdown" name="num_results_dropdown"  class="list_by_select">
                                    <?php
                                    $num_results_count = array('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15');
                                    foreach ($num_results_count as $number) {
                                        if ($number == $this->options['num_results_dropdown']) { ?>
                                            <option selected="selected" value="<?php echo $number; ?>"><?php echo $number; ?></option>
                                        <?php } else { ?>
                                            <option value="<?php echo $number; ?>"><?php echo $number; ?></option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                            </li>
                            <li>
                                <label for="grace_period_dropdown">Grace Period: </label>
                                <select style="display:inline;" id="grace_period_dropdown" name="grace_period_dropdown"  class="grace_period_select">
                                    <?php
                                    $grace_period_times = array('0','5','10','15','20','25','30','35','40','45','50','55','60');
                                    foreach ($grace_period_times as $times) {
                                        if ($times == $this->options['grace_period_dropdown']) { ?>
                                            <option selected="selected" value="<?php echo $times; ?>"><?php echo $times; ?></option>
                                        <?php } else { ?>
                                            <option value="<?php echo $times; ?>"><?php echo $times; ?></option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                            </li>
                        </ul>
                    </div>
                    <div style="padding: 0 15px;" class="postbox">
                        <h3>Custom CSS</h3>
                        <p>Allows for custom styling of Upcoming Meetings.</p>
                        <ul>
                            <li>
                                <textarea id="custom_css_um" name="custom_css_um" cols="100" rows="10"><?php echo $this->options['custom_css_um']; ?></textarea>
                            </li>
                        </ul>
                    </div>
                    <input type="submit" value="SAVE CHANGES" name="upcomingmeetingssave" class="button-primary" />
                </form>
                <br/><br/>
                <?php include 'partials/_instructions.php'; ?>
            </div>
            <script type="text/javascript">getUpcomingMeetingsValueSelected();</script>
            <?php
        }

        /**
         * @desc Adds the Settings link to the plugin activate/deactivate page
         */
        function filter_plugin_actions($links, $file) {
            // If your plugin is under a different top-level menu than Settings (IE - you changed the function above to something other than add_options_page)
            // Then you're going to want to change options-general.php below to the name of your top-level page
            $settings_link = '<a href="options-general.php?page=' . basename(__FILE__) . '">' . __('Settings') . '</a>';
            array_unshift($links, $settings_link);
            // before other links
            return $links;
        }
        /**
         * Retrieves the plugin options from the database.
         * @return array
         */
        function getOptions() {
            // Don't forget to set up the default options
            if (!$theOptions = get_option($this->optionsName)) {
                $theOptions = array(
                    "root_server"            => '',
                    "service_body_dropdown"  => '',
                    'recursive'              => '0',
                    'grace_period_dropdown'  => '15',
                    'num_results_dropdown'   => '5',
                    'timezones_dropdown'     => 'America/New_York',
                    'display_type_dropdown'  => 'simple'
                );
                update_option($this->optionsName, $theOptions);
            }
            $this->options = $theOptions;
            $this->options['root_server'] = untrailingslashit(preg_replace('/^(.*)\/(.*php)$/', '$1', $this->options['root_server']));
        }
        /**
         * Saves the admin options to the database.
         */
        function save_admin_options() {
            $this->options['root_server'] = untrailingslashit(preg_replace('/^(.*)\/(.*php)$/', '$1', $this->options['root_server']));
            update_option($this->optionsName, $this->options);
            return;
        }

        /**
         * @param $root_server
         * @param $services
         * @param $timezone
         * @param $grace_period
         * @param $recursive
         * @param $num_results
         * @return string
         */
        public function getMeetingsJson($root_server, $services, $timezone, $grace_period, $recursive, $num_results)
        {
            date_default_timezone_set($timezone);
            list($hour, $minute) = preg_split('/[:]/', date('G:i', strtotime('-' .$grace_period. 'minutes', strtotime(date('G:i')))));
            $serviceBodies = explode(',', $services);
            $services_query = '';
            foreach ($serviceBodies as $serviceBody) {
                $services_query .= '&services[]=' . $serviceBody;
            }
            $serviceBodiesURL =  wp_remote_retrieve_body(wp_remote_get($root_server . "/client_interface/json/?switcher=GetSearchResults&weekdays=" . (date('w')+1) .$services_query. "&StartsAfterH=" .$hour. "&StartsAfterM=" .$minute. ($recursive == "1" ? "&recursive=1" : "")));
            $serviceBodies_results = json_decode($serviceBodiesURL,true);
            $results_count = count($serviceBodies_results);

            if ($results_count != 0 && $results_count < $num_results) {
                $addtl_count_needed = $num_results - $results_count;
                $serviceBodiesURL_addtl =  wp_remote_retrieve_body(wp_remote_get($root_server . "/client_interface/json/?switcher=GetSearchResults&weekdays=" . (date('w')+2) .$services_query. ($recursive == "1" ? "&recursive=1" : "")));
                $serviceBodiesURL_addtl_json = json_decode($serviceBodiesURL_addtl, true);
                $added_results = array_slice($serviceBodiesURL_addtl_json, 0, $addtl_count_needed);
                $final_result = array_merge($serviceBodies_results,$added_results);
            }
            else if ($results_count == 0) {
                $serviceBodiesURL_addtl =  wp_remote_retrieve_body(wp_remote_get($root_server . "/client_interface/json/?switcher=GetSearchResults&weekdays=" . (date('w')+2) .$services_query. ($recursive == "1" ? "&recursive=1" : "")));
                $serviceBodiesURL_addtl_json = json_decode($serviceBodiesURL_addtl, true);
                $final_result = array_slice($serviceBodiesURL_addtl_json, 0, $num_results);
            }
            // if ($results_count >= $num_results) {
            else {
                $final_result = array_slice($serviceBodies_results, 0, $num_results);
            }

            return $final_result;
        }

        /*******************************************************************/
        /**
        \brief	This returns the search results, in whatever form was requested.
        \returns XHTML data. It will either be a table, or block elements.
         */
        public function MeetingsJson2Html (
            $results,			    ///< The results.
            $in_block = false,		///< If this is true, the results will be sent back as block elements (div tags), as opposed to a table. Default is false.
            $in_container_id = null	///< This is an optional ID for the "wrapper."
        )
        {
            $current_weekday = -1;
            $days_of_the_week = [1 => "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

            $ret = '';

            // What we do, is to parse the JSON return. We'll pick out certain fields, and format these into a table or block element return.
            if ( $results )
            {

                if ( is_array ( $results ) && count ( $results ) )
                {
                    $ret = $in_block ? '<div class="bmlt_simple_meetings_div"'.($in_container_id ? ' id="'.htmlspecialchars ( $in_container_id ).'"' : '').'>' : '<table class="bmlt_simple_meetings_table"'.($in_container_id ? ' id="'.htmlspecialchars ( $in_container_id ).'"' : '').' cellpadding="0" cellspacing="0" summary="Meetings">';
                    $result_keys = array();
                    foreach($results as $sub) {
                        $result_keys = array_merge($result_keys, $sub);
                    }
                    $keys = array_keys($result_keys);
                    $weekday_div = FALSE;

                    $alt = 1;	// This is used to provide an alternating class.
                    for ( $count = 0; $count < count ( $results ); $count++ )
                    {
                        $meeting = $results[$count];

                        if ( $meeting )
                        {
                            if ( $alt == 1 )
                            {
                                $alt = 0;
                            }
                            else
                            {
                                $alt = 1;
                            }

                            if ( is_array ( $meeting ) && count ( $meeting ) )
                            {
                                if ( count ( $meeting ) > count ( $keys ) )
                                {
                                    $keys[] = 'unused';
                                }

                                // This is for convenience. We turn the meeting array into an associative one by adding the keys.
                                $meeting = array_combine ( $keys, $meeting );
                                $location_borough = htmlspecialchars ( trim ( stripslashes ( $meeting['location_city_subsection'] ) ) );
                                $location_neighborhood = htmlspecialchars ( trim ( stripslashes ( $meeting['location_neighborhood'] ) ) );
                                $location_province = htmlspecialchars ( trim ( stripslashes ( $meeting['location_province'] ) ) );
                                $location_nation = htmlspecialchars ( trim ( stripslashes ( $meeting['location_nation'] ) ) );
                                $location_postal_code_1 = htmlspecialchars ( trim ( stripslashes ( $meeting['location_postal_code_1'] ) ) );
                                $location_municipality = htmlspecialchars ( trim ( stripslashes ( $meeting['location_municipality'] ) ) );
                                $town = '';

                                if ( $location_municipality )
                                {
                                    if ( $location_borough )
                                    {
                                        // We do it this verbose way, so we will scrag the comma if we want to hide the town.
                                        $town = "<span class=\"c_comdef_search_results_borough\">$location_borough</span><span class=\"bmlt_separator bmlt_separator_comma c_comdef_search_results_municipality_separator\">, </span><span class=\"c_comdef_search_results_municipality\">$location_municipality</span>";
                                    }
                                    else
                                    {
                                        $town = "<span class=\"c_comdef_search_results_municipality\">$location_municipality</span>";
                                    }
                                }
                                elseif ( $location_borough )
                                {
                                    $town = "<span class=\"c_comdef_search_results_municipality_borough\">$location_borough</span>";
                                }

                                if ( $location_province )
                                {
                                    if ( $town )
                                    {
                                        $town .= '<span class="bmlt_separator bmlt_separator_comma c_comdef_search_results_province_separator">, </span>';
                                    }

                                    $town .= "<span class=\"c_comdef_search_results_province\">$location_province</span>";
                                }

                                if ( $location_postal_code_1 )
                                {
                                    if ( $town )
                                    {
                                        $town .= '<span class="bmlt_separator bmlt_separator_comma c_comdef_search_results_zip_separator">, </span>';
                                    }

                                    $town .= "<span class=\"c_comdef_search_results_zip\">$location_postal_code_1</span>";
                                }

                                if ( $location_nation )
                                {
                                    if ( $town )
                                    {
                                        $town .= '<span class="bmlt_separator bmlt_separator_comma c_comdef_search_results_nation_separator">, </span>';
                                    }

                                    $town .= "<span class=\"c_comdef_search_results_nation\">$location_nation</span>";
                                }

                                if ( $location_neighborhood )
                                {
                                    $town_temp = '';

                                    if ( $town )
                                    {
                                        $town_temp = '<span class="bmlt_separator bmlt_separator_paren bmlt_separator_open_paren bmlt_separator_neighborhood_open_paren"> (</span>';
                                    }

                                    $town_temp .= "<span class=\"c_comdef_search_results_neighborhood\">$location_neighborhood</span>";

                                    if ( $town )
                                    {
                                        $town_temp .= '<span class="bmlt_separator bmlt_separator_paren bmlt_separator_close_paren bmlt_separator_neighborhood_close_paren">)</span>';
                                    }

                                    $town .= $town_temp;
                                }

                                $weekday = htmlspecialchars ( $days_of_the_week[intval ( $meeting['weekday_tinyint'] )] );
                                $time = $this->BuildMeetingTime ( $meeting['start_time'] );

                                $address = '';
                                $location_text = htmlspecialchars ( trim ( stripslashes ( $meeting['location_text'] ) ) );
                                $street = htmlspecialchars ( trim ( stripslashes ( $meeting['location_street'] ) ) );
                                $info = htmlspecialchars ( trim ( stripslashes ( $meeting['location_info'] ) ) );

                                if ( $location_text )
                                {
                                    $address = "<span class=\"bmlt_simple_list_location_text\">$location_text</span>";
                                }

                                if ( $street )
                                {
                                    if ( $address )
                                    {
                                        $address .= '<span class="bmlt_separator bmlt_separator_comma bmlt_simple_list_location_street_separator">, </span>';
                                    }

                                    $address .= "<span class=\"bmlt_simple_list_location_street\">$street</span>";
                                }

                                if ( $info )
                                {
                                    if ( $address )
                                    {
                                        $address .= '<span class="bmlt_separator bmlt_separator_space bmlt_simple_list_location_info_separator"> </span>';
                                    }

                                    $address .= "<span class=\"bmlt_simple_list_location_info\">($info)</span>";
                                }

                                $name = htmlspecialchars ( trim ( stripslashes ( $meeting['meeting_name'] ) ) );
                                $format = htmlspecialchars ( trim ( stripslashes ( $meeting['formats'] ) ) );

                                $name_uri = urlencode ( htmlspecialchars_decode ( $name ) );

                                $map_uri = str_replace ( "##LONG##", htmlspecialchars ( $meeting['longitude'] ), str_replace ( "##LAT##", htmlspecialchars ( $meeting['latitude'] ), str_replace ( "##NAME##", $name_uri, 'https://maps.google.com/maps?q=##LAT##,##LONG##+(##NAME##)&amp;ll=##LAT##,##LONG##' ) ) );

                                if ( $time && $weekday && $address )
                                {
                                    $meeting_weekday = $meeting['weekday_tinyint'];

                                    if ( 7 < $meeting_weekday )
                                    {
                                        $meeting_weekday = 1;
                                    }

                                    if ( ($current_weekday != $meeting_weekday) && $in_block )
                                    {
                                        if ( $current_weekday != -1 )
                                        {
                                            $weekday_div = FALSE;
                                            $ret .= '</div>';
                                        }

                                        $current_weekday = $meeting_weekday;

                                        $ret .= '<div class="bmlt_simple_meeting_weekday_div_'.$current_weekday.'">';
                                        $weekday_div = TRUE;
                                        if ( isset ( $in_http_vars['weekday_header'] ) && $in_http_vars['weekday_header'] )
                                        {
                                            $ret .= '<div id="weekday-start-'.$current_weekday.'" class="weekday-header weekday-index-'.$current_weekday.'">'.htmlspecialchars ( $weekday ).'</div>';
                                        }
                                    }

                                    $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_div bmlt_alt_'.intval ( $alt ).'">' : '<tr class="bmlt_simple_meeting_one_meeting_tr bmlt_alt_'.intval ( $alt ).'">';
                                    $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_town_div">' : '<td class="bmlt_simple_meeting_one_meeting_town_td">';
                                    $ret .= $town;
                                    $ret .= $in_block ? '</div>' : '</td>';
                                    $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_name_div">' : '<td class="bmlt_simple_meeting_one_meeting_name_td">';

                                    if ( isset ( $single_uri ) && $single_uri )
                                    {
                                        $ret .= '<a href="'.htmlspecialchars ( $single_uri ).intval ( $meeting['id_bigint'] ).'">';
                                    }

                                    if ( $name )
                                    {
                                        $ret .= $name;
                                    }
                                    else
                                    {
                                        $ret .= 'NA Meeting';
                                    }

                                    if ( isset ( $single_uri ) && $single_uri )
                                    {
                                        $ret .= '</a>';
                                    }

                                    $ret .= $in_block ? '</div>' : '</td>';

                                    $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_time_div">' : '<td class="bmlt_simple_meeting_one_meeting_time_td">';
                                    $ret .= $time;
                                    $ret .= $in_block ? '</div>' : '</td>';

                                    $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_weekday_div">' : '<td class="bmlt_simple_meeting_one_meeting_weekday_td">';
                                    $ret .= $weekday;
                                    $ret .= $in_block ? '</div>' : '</td>';

                                    $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_address_div">' : '<td class="bmlt_simple_meeting_one_meeting_address_td">';
                                    $ret .= '<a href="'.$map_uri.'">'.$address.'</a>';
                                    $ret .= $in_block ? '</div>' : '</td>';

                                    $ret .= $in_block ? '<div class="bmlt_simple_meeting_one_meeting_format_div">' : '<td class="bmlt_simple_meeting_one_meeting_format_td">';
                                    $ret .= $format;
                                    $ret .= $in_block ? '</div>' : '</td>';

                                    $ret .= $in_block ? '<div class="bmlt_clear_div"></div></div>' : '</tr>';
                                }
                            }
                        }
                    }

                    if ( $weekday_div && $in_block )
                    {
                        $ret .= '</div>';
                    }

                    $ret .= $in_block ? '</div>' : '</table>';
                }
            }

            return $ret;
        }

        /*******************************************************************/
        /** \brief This creates a time string to be displayed for the meeting.
        The display is done in non-military time, and "midnight" and
        "noon" are substituted for 12:59:00, 00:00:00 and 12:00:00

        \returns a string, containing the HTML rendered by the function.
         */
        public function BuildMeetingTime ( $in_time ///< A string. The value of the time field.
        )
        {
            $time_format = 'g:i A';
            $time = null;

            if ( ($in_time == "00:00:00") || ($in_time >= "23:55:00") )
            {
                $time = htmlspecialchars ( 'Midnight' );
            }
            elseif ( $in_time == "12:00:00" )
            {
                $time = htmlspecialchars ( 'Noon' );
            }
            else
            {
                $time = htmlspecialchars ( date( $time_format, strtotime ( $in_time ) ) );
            }

            return $time;
        }
    }
    //End Class UpcomingMeetings
}
// end if
// instantiate the class
if (class_exists("UpcomingMeetings")) {
    $UpcomingMeetings_instance = new UpcomingMeetings();
}
?>
