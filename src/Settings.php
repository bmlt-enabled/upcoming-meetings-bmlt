<?php

namespace UpcomingMeetings;

require_once 'Helpers.php';

/**
 * Class Settings
 * @package UpcomingMeetings
 */
class Settings
{
    /**
     * Instance of the Helpers class.
     *
     * @var Helpers
     */
    private $helper;
    public $optionsName = 'upcoming_meetings_options';
    public $options = [];

    /**
     * Constructor for the UpcomingMeetingsBMLT class.
     */
    public function __construct()
    {
        $this->getOptions();
        $this->helper = new Helpers();
        add_action("admin_notices", [$this, "isRootServerMissing"]);
    }

    /**
     * Create the admin menu for the plugin.
     *
     * This function adds an options page to the WordPress admin menu and registers a plugin action link.
     *
     * @param string $baseFile The base file of the plugin.
     * @return void
     */
    public function createMenu(string $baseFile): void
    {
        add_options_page(
            'Upcoming Meetings BMLT', // Page Title
            'Upcoming Meetings BMLT', // Menu Title
            'activate_plugins',    // Capability
            'upcoming-meetings-bmlt', // Menu Slug
            [$this, 'adminOptionsPage'] // Callback function to display the page content
        );
        add_filter('plugin_action_links_' . $baseFile, [$this, 'filterPluginActions'], 10, 2);
    }

    /**
     * Display the admin options page and handle form submissions.
     *
     * This function handles the display of the admin options page and processes form submissions.
     *
     * @return void
     */
    public function adminOptionsPage(): void
    {
        if (!empty($_POST['upcomingmeetingssave']) && wp_verify_nonce($_POST['_wpnonce'], 'upcomingmeetingsupdate-options')) {
            $this->updateAdminOptions();
            $this->printSuccessMessage();
        }
        $this->printAdminForm();
    }

    /**
     * Update the admin options based on POST data.
     *
     * This function updates the plugin's options based on the POST data received from the admin settings form.
     *
     * @return void
     */
    private function updateAdminOptions(): void
    {
        $this->options['root_server']                = isset($_POST['root_server']) ? esc_url_raw($_POST['root_server']) : '';
        $this->options['service_body_dropdown']      = isset($_POST['service_body_dropdown']) ? sanitize_text_field($_POST['service_body_dropdown']) : '';
        $this->options['recursive']                  = isset($_POST['recursive']) ? sanitize_text_field($_POST['recursive']) : '';
        $this->options['grace_period_dropdown']      = isset($_POST['grace_period_dropdown']) ? sanitize_text_field($_POST['grace_period_dropdown']) : '';
        $this->options['num_results_dropdown']       = isset($_POST['num_results_dropdown']) ? sanitize_text_field($_POST['num_results_dropdown']) : '';
        $this->options['timezones_dropdown']         = isset($_POST['timezones_dropdown']) ? sanitize_text_field($_POST['timezones_dropdown']) : '';
        $this->options['display_type_dropdown']      = isset($_POST['display_type_dropdown']) ? sanitize_text_field($_POST['display_type_dropdown']) : '';
        $this->options['location_text_checkbox']     = isset($_POST['location_text_checkbox']) ? sanitize_text_field($_POST['location_text_checkbox']) : '';
        $this->options['show_header_checkbox']       = isset($_POST['show_header_checkbox']) ? sanitize_text_field($_POST['show_header_checkbox']) : '';
        $this->options['time_format_dropdown']       = isset($_POST['time_format_dropdown']) ? sanitize_text_field($_POST['time_format_dropdown']) : '';
        $this->options['weekday_language_dropdown']  = isset($_POST['weekday_language_dropdown']) ? sanitize_text_field($_POST['weekday_language_dropdown']) : '';
        $this->options['custom_query']               = isset($_POST['custom_query']) ? sanitize_text_field($_POST['custom_query']) : '';
        $this->options['custom_css_um']              = $_POST['custom_css_um'];
        $this->saveAdminOptions();
    }

    /**
     * Display a success message.
     *
     * This function outputs a success message indicating that changes were successfully saved.
     *
     * @return void
     */
    private function printSuccessMessage(): void
    {
        echo '<div class="updated"><p>Success! Your changes were successfully saved!</p></div>';
    }

    /**
     * Get the connection status to the BMLT Root Server.
     *
     * This function tests the connection to the BMLT Root Server and returns the status and a message.
     *
     * @return array An associative array with 'msg' and 'status' keys indicating the status and message.
     */
    private function getConnectionStatus(): array
    {
        $this_connected = $this->helper->testRootServer($this->options['root_server']);
        return $this_connected ? [
            'msg' => "<span style='color: #00AD00;'><div style='font-size: 16px;vertical-align: text-top;' class='dashicons dashicons-smiley'></div>Version {$this_connected}</span>",
            'status' => true
        ] : [
            'msg' => "<p><div style='color: #f00;font-size: 16px;vertical-align: text-top;' class='dashicons dashicons-no'></div><span style='color: #f00;'>Connection to Root Server Failed.  Check spelling or try again.  If you are certain spelling is correct, Root Server could be down.</span></p>",
            'status' => false
        ];
    }

    /**
     * Display the admin settings form for the plugin.
     *
     * This function generates and displays the admin settings form for the plugin.
     *
     * @return void
     */
    private function printAdminForm(): void
    {
        $connectionStatus = $this->getConnectionStatus();
        ?>
        <div class="wrap">
            <h2>Upcoming Meetings BMLT</h2>
            <form style="display:inline!important;" method="POST" id="upcoming_meetings_options" name="upcoming_meetings_options">
                <?php wp_nonce_field('upcomingmeetingsupdate-options'); ?>

                <!-- Connection Status Display -->
                <div style="margin-top: 20px; padding: 0 15px;" class="postbox">
                    <h3>BMLT Root Server URL</h3>
                    <p>Example: https://domain.org/main_server</p>
                    <ul>
                        <li>
                            <label for="root_server">Default Root Server: </label>
                            <input id="root_server" type="text" size="50" name="root_server" value="<?php echo esc_attr($this->options['root_server']); ?>" />
                            <?php echo $connectionStatus['msg']; ?>
                        </li>
                    </ul>
                </div>

                <!-- Service Body Section -->
                <div style="padding: 0 15px;" class="postbox">
                    <h3>Service Body</h3>
                    <p>This service body will be used when no service body is defined in the shortcode.</p>
                    <ul>
                        <li>
                            <label for="service_body_dropdown">Default Service Body: </label>
                            <select style="display:inline;" onchange="getUpcomingMeetingsValueSelected()" id="service_body_dropdown" name="service_body_dropdown" class="upcoming_meetings_service_body_select">
                                <?php if ($connectionStatus['status']) { ?>
                                    <?php $unique_areas = $this->helper->getAreas($this->options['root_server']); ?>
                                    <?php asort($unique_areas); ?>
                                    <?php foreach ($unique_areas as $key => $unique_area) { ?>
                                        <?php $area_data          = explode(',', $unique_area); ?>
                                        <?php $area_name          = $this->helper->arraySafeGet($area_data, 0); ?>
                                        <?php $area_id            = $this->helper->arraySafeGet($area_data, 1); ?>
                                        <?php $area_parent        = $this->helper->arraySafeGet($area_data, 2); ?>
                                        <?php $area_parent_name   = $this->helper->arraySafeGet($area_data, 3); ?>
                                        <?php $option_description = $area_name . " (" . $area_id . ") " . $area_parent_name . " (" . $area_parent . ")" ?>
                                        <?php $is_data = explode(',', esc_html($this->options['service_body_dropdown'])); ?>
                                        <?php if ($area_id == $this->helper->arraySafeGet($is_data, 1)) { ?>
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
                                $timezones_array = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
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
                                } elseif ($this->options['display_type_dropdown'] == 'table') { ?>
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
                            <label for="time_format_dropdown">Time Format: </label>
                            <select id="time_format_dropdown" name="time_format_dropdown" class="time_format_select">
                                <option value="24" <?= ($this->options['time_format_dropdown'] == '24') ? 'selected' : '' ?>>24 Hour</option>
                                <option value="12" <?= ($this->options['time_format_dropdown'] == '12') ? 'selected' : '' ?>>12 Hour</option>
                            </select>
                        </li>
                        <li>
                            <label for="weekday_language_dropdown">Weekday Language: </label>
                            <select style="display:inline;" id="weekday_language_dropdown" name="weekday_language_dropdown"  class="weekday_language_select">
                                <?php
                                echo $this->printLangDropdownOption($this->options['weekday_language_dropdown']);
                                ?>
                            </select>
                        </li>
                        <li>
                            <label for="num_results_dropdown">Number of Results: </label>
                            <select style="display:inline;" id="num_results_dropdown" name="num_results_dropdown"  class="list_by_select">
                                <?php
                                foreach (range(1, 15) as $number) {
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
                                foreach (range(0, 60, 5) as $times) {
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
                        <li>
                            <input type="checkbox" id="location_text_checkbox" name="location_text_checkbox" value="1" <?php echo ($this->options['location_text_checkbox'] == "1" ? "checked" : "") ?>/>
                            <label for="location_text_checkbox">Show Location Text (for simple display)</label>
                        </li>
                        <li>
                            <input type="checkbox" id="show_header_checkbox" name="show_header_checkbox" value="1" <?php echo ($this->options['show_header_checkbox'] == "1" ? "checked" : "") ?>/>
                            <label for="show_header_checkbox">Show Header Info (for Table/Block display)</label>
                        </li>
                    </ul>
                </div>
                <div style="padding: 0 15px;" class="postbox">
                    <h3>Custom Query</h3>
                    <p>Ex. &formats=54</p>
                    <ul>
                        <li>
                            <input type="text" id="custom_query" name="custom_query" value="<?php echo $this->options['custom_query']; ?>">
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
     * Generate a language dropdown option list.
     *
     * This function generates an HTML option list for a language dropdown, with the specified option preselected.
     *
     * @param string $selectedValue The value of the option to be preselected.
     * @return string The generated HTML option list.
     */
    public function printLangDropdownOption(string $selectedValue): string
    {
        $langs = $this->helper->getLangInfo();
        $ret = '';
        foreach ($langs as $key => $name) {
            $isSelected = $key === $selectedValue ? ' selected="selected"' : ' ';
            $ret .= '<option' . $isSelected . ' value="' . $key . '">' . htmlspecialchars($name['name']) . '</option>' . "\n";
        }
        return $ret;
    }

    /**
     * Filter the plugin action links displayed on the Plugins page.
     *
     * This function adds a "Settings" link to the plugin's action links on the Plugins page in the WordPress admin.
     *
     * @param array $links The array of action links.
     * @return array The modified array of action links.
     */
    public function filterPluginActions(array $links): array
    {
        // If your plugin is under a different top-level menu than Settings (IE - you changed the function above to something other than add_options_page)
        // Then you're going to want to change options-general.php below to the name of your top-level page
        $settings_link = '<a href="options-general.php?page=upcomming-meetings-bmlt">Settings</a>';
        array_unshift($links, $settings_link);
        // before other links
        return $links;
    }

    /**
     * Retrieves and initializes plugin options.
     *
     * This function retrieves the plugin options from WordPress options and initializes
     * default values if the options do not exist.
     *
     * @return void
     */
    public function getOptions(): void
    {
        // Don't forget to set up the default options
        if (!$theOptions = get_option($this->optionsName)) {
            $theOptions = [
                "root_server"               => '',
                "service_body_dropdown"     => '',
                'recursive'                 => '0',
                'grace_period_dropdown'     => '15',
                'num_results_dropdown'      => '5',
                'timezones_dropdown'        => get_option('timezone_string'),
                'display_type_dropdown'     => 'simple',
                'location_text_checkbox'    => '0',
                'show_header_checkbox'      => '0',
                'time_format'               => '12',
                'weekday_language_dropdown' => 'en',
                'custom_query'              => ''
            ];
            update_option($this->optionsName, $theOptions);
        }
        $this->options = $theOptions;
        $this->options['root_server'] = untrailingslashit(preg_replace('/^(.*)\/(.*php)$/', '$1', $this->options['root_server']));
    }

    /**
     * Saves the admin options for the plugin.
     *
     * This function updates the BMLT Root Server option and saves it in the WordPress options.
     *
     * @return void
     */
    public function saveAdminOptions(): void
    {
        $this->options['root_server'] = untrailingslashit(preg_replace('/^(.*)\/(.*php)$/', '$1', $this->options['root_server']));
        update_option($this->optionsName, $this->options);
        return;
    }

    /**
     * Checks if the BMLT Root Server is missing in the plugin settings.
     *
     * @return void
     */
    public function isRootServerMissing(): void
    {
        if (empty($this->options['root_server'])) {
            $url = esc_url(admin_url('options-general.php?page=upcoming-meetings-bmlt'));
            echo '<div id="message" class="error">';
            echo '<p>Missing BMLT Root Server in settings for Upcoming Meetings BMLT.</p>';
            echo "<p><a href='{$url}'>Upcoming Meetings BMLT Settings</a></p>";
            echo '</div>';
        }
    }
}
