<?php
/**
 * Integration tests for the Upcoming Meetings BMLT plugin.
 *
 * HTTP is mocked via the pre_http_request filter so tests never hit the network.
 */

use UpcomingMeetings\Helpers;
use UpcomingMeetings\Settings;
use UpcomingMeetings\Shortcode;
use UpcomingMeetings\FormatsShortcode;

class Test_Upcoming_Meetings extends WP_UnitTestCase {

	/**
	 * @var Helpers
	 */
	private $helper;

	public function setUp(): void {
		parent::setUp();
		$this->helper = new Helpers();
		// The plugin registers this handle on wp_enqueue_scripts (which doesn't fire in
		// these tests); register it so the area-filter branch can enqueue it cleanly.
		wp_register_script( 'upcoming-meetings-filter', 'https://example.org/upcoming_meetings.js', array(), '1', true );
	}

	// -------------------------------------------------------------------------
	// HTTP mock helpers
	// -------------------------------------------------------------------------

	/**
	 * Install a URL-aware pre_http_request stub.
	 *
	 * @param callable $responder function(string $url): array|WP_Error
	 * @return object State object exposing ->urls (captured) and ->filter.
	 */
	private function mock_http( callable $responder ): object {
		$state        = new \stdClass();
		$state->urls  = array();
		$state->filter = function ( $preempt, $args, $url ) use ( $state, $responder ) {
			$state->urls[] = $url;
			return $responder( $url );
		};
		add_filter( 'pre_http_request', $state->filter, 10, 3 );
		return $state;
	}

	private function unmock_http( object $state ): void {
		remove_filter( 'pre_http_request', $state->filter, 10 );
	}

	private function http_ok( string $body ): array {
		return array(
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'  => array(),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Route BMLT switcher requests to different JSON bodies by switcher name.
	 *
	 * @param array $bodies Map of switcher substring => JSON body string.
	 */
	private function mock_bmlt_router( array $bodies ): object {
		return $this->mock_http( function ( $url ) use ( $bodies ) {
			foreach ( $bodies as $needle => $body ) {
				if ( strpos( $url, $needle ) !== false ) {
					return $this->http_ok( $body );
				}
			}
			return $this->http_ok( '[]' );
		} );
	}

	// -------------------------------------------------------------------------
	// Fixtures
	// -------------------------------------------------------------------------

	private function sample_meeting( array $overrides = array() ): array {
		return array_merge(
			array(
				'id_bigint'                       => '1',
				'weekday_tinyint'                 => '2',
				'start_time'                      => '19:30:00',
				'meeting_name'                    => 'Monday Night Group',
				'formats'                         => 'O,D',
				'location_text'                   => 'Community Center',
				'location_street'                 => '123 Main St',
				'location_city_subsection'        => '',
				'location_neighborhood'           => '',
				'location_municipality'           => 'Springfield',
				'location_province'               => 'NY',
				'location_postal_code_1'          => '12345',
				'location_info'                   => '',
				'comments'                        => '',
				'latitude'                        => '40.123456',
				'longitude'                       => '-74.654321',
				'virtual_meeting_link'            => '',
				'phone_meeting_number'            => '',
				'virtual_meeting_additional_info' => '',
			),
			$overrides
		);
	}

	private function service_bodies_json(): string {
		return wp_json_encode(
			array(
				array( 'id' => '1', 'name' => 'Region', 'parent_id' => '0' ),
				array( 'id' => '2', 'name' => 'Area B', 'parent_id' => '1' ),
				array( 'id' => '3', 'name' => 'Area A', 'parent_id' => '1' ),
				array( 'id' => '4', 'name' => 'Subarea', 'parent_id' => '2' ),
			)
		);
	}

	private function default_args( array $overrides = array() ): array {
		return array_merge(
			array(
				'root_server'      => 'https://example.org/main_server',
				'services'         => '1',
				'recursive'        => '0',
				'grace_period'     => '15',
				'num_results'      => '5',
				'timezone'         => 'America/New_York',
				'display_type'     => 'simple',
				'location_text'    => '0',
				'time_format'      => '12',
				'weekday_language' => 'en',
				'show_header'      => '0',
				'limit_to_today'   => '0',
				'custom_query'     => '',
				'meetings'         => '',
			),
			$overrides
		);
	}

	// -------------------------------------------------------------------------
	// Plugin bootstrap / registration
	// -------------------------------------------------------------------------

	public function test_singleton_returns_same_instance() {
		$a = UpcomingMeetings::getInstance();
		$b = UpcomingMeetings::getInstance();
		$this->assertSame( $a, $b );
	}

	public function test_upcoming_meetings_shortcode_registered() {
		$this->assertTrue( shortcode_exists( 'upcoming_meetings' ) );
	}

	public function test_meeting_formats_shortcode_registered() {
		$this->assertTrue( shortcode_exists( 'meeting_formats' ) );
	}

	// -------------------------------------------------------------------------
	// Helpers::arraySafeGet
	// -------------------------------------------------------------------------

	public function test_array_safe_get_returns_value() {
		$this->assertSame( 1, Helpers::arraySafeGet( array( 'a' => 1 ), 'a' ) );
	}

	public function test_array_safe_get_missing_returns_null() {
		$this->assertNull( Helpers::arraySafeGet( array( 'a' => 1 ), 'b' ) );
	}

	public function test_array_safe_get_missing_returns_default() {
		$this->assertSame( 'def', Helpers::arraySafeGet( array( 'a' => 1 ), 'b', 'def' ) );
	}

	// -------------------------------------------------------------------------
	// Helpers::translateText
	// -------------------------------------------------------------------------

	public function test_translate_text_known_language_and_key() {
		$this->assertSame( 'Mapa', $this->helper->translateText( 'map', 'es' ) );
		$this->assertSame( 'Link zum Online-Meeting', $this->helper->translateText( 'meeting_link', 'de' ) );
	}

	public function test_translate_text_unknown_language_falls_back_to_english() {
		$this->assertSame( 'Map', $this->helper->translateText( 'map', 'xx' ) );
	}

	public function test_translate_text_unknown_key_returns_key() {
		$this->assertSame( 'nonexistent', $this->helper->translateText( 'nonexistent', 'en' ) );
	}

	public function test_translate_text_defaults_to_english() {
		$this->assertSame( 'Online Meeting Link', $this->helper->translateText( 'meeting_link' ) );
	}

	// -------------------------------------------------------------------------
	// Helpers::getTranslatedDaysOfWeek / getLangInfo
	// -------------------------------------------------------------------------

	public function test_translated_days_english_is_sunday_first() {
		$days = $this->helper->getTranslatedDaysOfWeek( 'en' );
		$this->assertCount( 7, $days );
		// BMLT weekday indexing: 1 = Sunday.
		$this->assertSame( 'Sunday', $days[1] );
		$this->assertSame( 'Saturday', $days[7] );
	}

	public function test_translated_days_spanish() {
		$days = $this->helper->getTranslatedDaysOfWeek( 'es' );
		$this->assertSame( 'Domingo', $days[1] );
	}

	public function test_translated_days_unknown_language_falls_back_to_english() {
		$days = $this->helper->getTranslatedDaysOfWeek( 'xx' );
		$this->assertSame( 'Sunday', $days[1] );
	}

	public function test_lang_info_contains_expected_languages() {
		$info = $this->helper->getLangInfo();
		$this->assertArrayHasKey( 'en', $info );
		$this->assertArrayHasKey( 'es', $info );
		$this->assertSame( 'en', $info['en']['code'] );
		$this->assertNotEmpty( $info['en']['name'] );
		$this->assertCount( 7, $info['en']['days_of_week'] );
	}

	// -------------------------------------------------------------------------
	// Helpers::buildMeetingTime
	// -------------------------------------------------------------------------

	public function test_build_meeting_time_midnight() {
		$this->assertSame( 'Midnight', $this->helper->buildMeetingTime( '00:00:00', 'g:i A' ) );
	}

	public function test_build_meeting_time_noon() {
		$this->assertSame( 'Noon', $this->helper->buildMeetingTime( '12:00:00', 'g:i A' ) );
	}

	public function test_build_meeting_time_12_hour() {
		$this->assertSame( '7:30 pm', $this->helper->buildMeetingTime( '19:30:00', 'g:i a' ) );
	}

	public function test_build_meeting_time_24_hour() {
		$this->assertSame( '19:30', $this->helper->buildMeetingTime( '19:30:00', 'G:i' ) );
	}

	// -------------------------------------------------------------------------
	// Helpers::validateNumber / isValidUrl
	// -------------------------------------------------------------------------

	public function test_validate_number_accepts_digits() {
		$this->assertTrue( $this->helper->validateNumber( '5551234' ) );
		$this->assertTrue( $this->helper->validateNumber( '+1 (555) 123-4567' ) );
	}

	public function test_validate_number_rejects_empty_and_letters() {
		$this->assertFalse( $this->helper->validateNumber( '' ) );
		$this->assertFalse( $this->helper->validateNumber( 'abc' ) );
	}

	public function test_is_valid_url_accepts_http_url() {
		$this->assertTrue( $this->helper->isValidUrl( 'https://zoom.us/j/123' ) );
	}

	public function test_is_valid_url_rejects_garbage() {
		$this->assertFalse( $this->helper->isValidUrl( 'not a url' ) );
		$this->assertFalse( $this->helper->isValidUrl( '' ) );
	}

	// -------------------------------------------------------------------------
	// Helpers::formatLocation
	// -------------------------------------------------------------------------

	public function test_format_location_defaults_join_and_skip_empty() {
		$meeting = $this->sample_meeting(
			array(
				'location_text'            => 'Community Center',
				'location_street'          => '123 Main St',
				'location_city_subsection' => '',
				'location_neighborhood'    => '',
			)
		);
		$this->assertSame( 'Community Center, 123 Main St', $this->helper->formatLocation( $meeting ) );
	}

	public function test_format_location_selected_parts() {
		$meeting = $this->sample_meeting( array( 'location_municipality' => 'Springfield' ) );
		$this->assertSame( 'Springfield', $this->helper->formatLocation( $meeting, array( 'location_municipality' ) ) );
	}

	public function test_format_location_strips_slashes() {
		$meeting = $this->sample_meeting( array( 'location_text' => "O\\'Brien Hall", 'location_street' => '' ) );
		$this->assertStringContainsString( "O&#039;Brien Hall", $this->helper->formatLocation( $meeting ) );
	}

	// -------------------------------------------------------------------------
	// Helpers::buildHtmlLocationInfo
	// -------------------------------------------------------------------------

	public function test_build_html_location_info_builds_maps_link() {
		$meeting = $this->sample_meeting();
		$html    = $this->helper->buildHtmlLocationInfo( $meeting, '123 Main St', array( 'O', 'D' ) );
		$this->assertStringContainsString( 'https://maps.google.com/maps?q=40.123456,-74.654321', $html );
		$this->assertStringContainsString( '123 Main St', $html );
	}

	public function test_build_html_location_info_virtual_only_is_empty() {
		$meeting = $this->sample_meeting();
		$this->assertSame( '', $this->helper->buildHtmlLocationInfo( $meeting, '123 Main St', array( 'VM' ) ) );
	}

	public function test_build_html_location_info_hybrid_keeps_link() {
		$meeting = $this->sample_meeting();
		$html    = $this->helper->buildHtmlLocationInfo( $meeting, '123 Main St', array( 'VM', 'HY' ) );
		$this->assertStringContainsString( 'maps.google.com', $html );
	}

	// -------------------------------------------------------------------------
	// Helpers::formatVirtualLink
	// -------------------------------------------------------------------------

	public function test_format_virtual_link_renders_meeting_link() {
		$meeting = $this->sample_meeting( array( 'virtual_meeting_link' => 'https://zoom.us/j/123' ) );
		$html    = $this->helper->formatVirtualLink( $meeting, array( 'VM' ), 'en' );
		$this->assertStringContainsString( 'https://zoom.us/j/123', $html );
		$this->assertStringContainsString( 'Online Meeting Link', $html );
		$this->assertStringContainsString( 'um_virtual_a', $html );
	}

	public function test_format_virtual_link_renders_phone_link() {
		$meeting = $this->sample_meeting( array( 'phone_meeting_number' => '15551234567' ) );
		$html    = $this->helper->formatVirtualLink( $meeting, array( 'VM' ), 'en' );
		$this->assertStringContainsString( 'tel:15551234567', $html );
	}

	public function test_format_virtual_link_appends_additional_info() {
		$meeting = $this->sample_meeting(
			array(
				'virtual_meeting_link'            => 'https://zoom.us/j/123',
				'virtual_meeting_additional_info' => 'Password: 1234',
			)
		);
		$html = $this->helper->formatVirtualLink( $meeting, array( 'VM' ), 'en' );
		$this->assertStringContainsString( 'Password: 1234', $html );
	}

	public function test_format_virtual_link_translates_label() {
		$meeting = $this->sample_meeting( array( 'virtual_meeting_link' => 'https://zoom.us/j/123' ) );
		$html    = $this->helper->formatVirtualLink( $meeting, array( 'VM' ), 'es' );
		$this->assertStringContainsString( 'Enlace de Reunión en Línea', $html );
	}

	// -------------------------------------------------------------------------
	// Helpers::formatMeeting
	// -------------------------------------------------------------------------

	public function test_format_meeting_table_row() {
		$days = $this->helper->getTranslatedDaysOfWeek( 'en' );
		$html = $this->helper->formatMeeting( $this->sample_meeting(), $days, 'g:i a', false, 0, 'en' );
		$this->assertStringContainsString( '<tr class="bmlt_simple_meeting_one_meeting_tr bmlt_alt_0">', $html );
		$this->assertStringContainsString( 'Monday Night Group', $html );
		$this->assertStringContainsString( 'Monday', $html );
	}

	public function test_format_meeting_block_element() {
		$days = $this->helper->getTranslatedDaysOfWeek( 'en' );
		$html = $this->helper->formatMeeting( $this->sample_meeting(), $days, 'g:i a', true, 1, 'en' );
		$this->assertStringContainsString( '<div class="bmlt_simple_meeting_one_meeting_div bmlt_alt_1">', $html );
	}

	public function test_format_meeting_name_falls_back_to_na_meeting() {
		$days    = $this->helper->getTranslatedDaysOfWeek( 'en' );
		$meeting = $this->sample_meeting( array( 'meeting_name' => '' ) );
		$html    = $this->helper->formatMeeting( $meeting, $days, 'g:i a', false, 0, 'en' );
		$this->assertStringContainsString( 'NA Meeting', $html );
	}

	// -------------------------------------------------------------------------
	// Helpers::meetingsJson2Html
	// -------------------------------------------------------------------------

	public function test_meetings_json_to_html_empty_returns_empty() {
		$this->assertSame( '', $this->helper->meetingsJson2Html( array(), false, null, 'g:i a', 'en', false ) );
	}

	public function test_meetings_json_to_html_table() {
		$html = $this->helper->meetingsJson2Html( array( $this->sample_meeting() ), false, null, 'g:i a', 'en', false );
		$this->assertStringContainsString( '<table class="bmlt_simple_meetings_table"', $html );
		$this->assertStringContainsString( 'Monday Night Group', $html );
	}

	public function test_meetings_json_to_html_table_with_header() {
		$html = $this->helper->meetingsJson2Html( array( $this->sample_meeting() ), false, null, 'g:i a', 'en', true );
		$this->assertStringContainsString( '<th>Location</th>', $html );
	}

	public function test_meetings_json_to_html_block() {
		$html = $this->helper->meetingsJson2Html( array( $this->sample_meeting() ), true, null, 'g:i a', 'en', false );
		$this->assertStringContainsString( '<div class="bmlt_simple_meetings_div"', $html );
	}

	// -------------------------------------------------------------------------
	// Helpers::renderMeetingsSimple
	// -------------------------------------------------------------------------

	public function test_render_meetings_simple_includes_name_and_map() {
		$args = array( 'location_text' => '0' );
		$html = $this->helper->renderMeetingsSimple( array( $this->sample_meeting() ), $args, 'g:i a', 'en' );
		$this->assertStringContainsString( 'Monday Night Group', $html );
		$this->assertStringContainsString( 'maps.google.com', $html );
		$this->assertStringContainsString( 'Map', $html );
	}

	public function test_render_meetings_simple_virtual_meeting() {
		$meeting = $this->sample_meeting(
			array(
				'formats'              => 'VM',
				'virtual_meeting_link' => 'https://zoom.us/j/999',
			)
		);
		$html = $this->helper->renderMeetingsSimple( array( $meeting ), array( 'location_text' => '0' ), 'g:i a', 'en' );
		$this->assertStringContainsString( 'https://zoom.us/j/999', $html );
		$this->assertStringContainsString( 'um_virtual_a', $html );
	}

	// -------------------------------------------------------------------------
	// Helpers HTTP client — testRootServer
	// -------------------------------------------------------------------------

	public function test_test_root_server_returns_version() {
		$state = $this->mock_bmlt_router(
			array( 'GetServerInfo' => wp_json_encode( array( array( 'version' => '3.0.5' ) ) ) )
		);
		$this->assertSame( '3.0.5', $this->helper->testRootServer( 'https://example.org/main_server' ) );
		$this->unmock_http( $state );
	}

	public function test_test_root_server_empty_url() {
		$this->assertStringContainsString( 'empty', $this->helper->testRootServer( '' ) );
	}

	public function test_test_root_server_invalid_format() {
		$state = $this->mock_bmlt_router( array( 'GetServerInfo' => wp_json_encode( array( array( 'nope' => 1 ) ) ) ) );
		$this->assertStringContainsString( 'Invalid server response', $this->helper->testRootServer( 'https://example.org/main_server' ) );
		$this->unmock_http( $state );
	}

	// -------------------------------------------------------------------------
	// Helpers HTTP client — getAreas / getDescendantServiceBodies
	// -------------------------------------------------------------------------

	public function test_get_areas_builds_name_id_parent_strings() {
		$state = $this->mock_bmlt_router( array( 'GetServiceBodies' => $this->service_bodies_json() ) );
		$areas = $this->helper->getAreas( 'https://example.org/main_server' );
		$this->assertContains( 'Region,1,0,None', $areas );
		$this->assertContains( 'Area B,2,1,Region', $areas );
		$this->unmock_http( $state );
	}

	public function test_get_areas_returns_empty_on_error() {
		$state = $this->mock_http( function () {
			return new WP_Error( 'http_request_failed', 'down' );
		} );
		$this->assertSame( array(), $this->helper->getAreas( 'https://example.org/main_server' ) );
		$this->unmock_http( $state );
	}

	public function test_get_descendant_service_bodies_walks_hierarchy() {
		$state       = $this->mock_bmlt_router( array( 'GetServiceBodies' => $this->service_bodies_json() ) );
		$descendants = $this->helper->getDescendantServiceBodies( 'https://example.org/main_server', array( '1' ) );
		// Region (1) has areas 2 and 3; area 2 has subarea 4. Region itself is not a descendant.
		$this->assertArrayHasKey( 2, $descendants );
		$this->assertArrayHasKey( 3, $descendants );
		$this->assertArrayHasKey( 4, $descendants );
		$this->assertArrayNotHasKey( 1, $descendants );
		$this->assertCount( 3, $descendants );
		$this->unmock_http( $state );
	}

	public function test_get_descendant_service_bodies_sorted_by_name() {
		$state       = $this->mock_bmlt_router( array( 'GetServiceBodies' => $this->service_bodies_json() ) );
		$descendants = $this->helper->getDescendantServiceBodies( 'https://example.org/main_server', array( '1' ) );
		// asort by name: Area A (3), Area B (2), Subarea (4).
		$this->assertSame( array( 3, 2, 4 ), array_keys( $descendants ) );
		$this->unmock_http( $state );
	}

	public function test_get_descendant_service_bodies_empty_on_error() {
		$state = $this->mock_http( function () {
			return new WP_Error( 'http_request_failed', 'down' );
		} );
		$this->assertSame( array(), $this->helper->getDescendantServiceBodies( 'https://example.org/main_server', array( '1' ) ) );
		$this->unmock_http( $state );
	}

	// -------------------------------------------------------------------------
	// Helpers HTTP client — getFormatsJson
	// -------------------------------------------------------------------------

	public function test_get_formats_json_sorts_by_key_string() {
		$body = wp_json_encode(
			array(
				array( 'key_string' => 'O', 'name_string' => 'Open', 'description_string' => '', 'lang' => 'en' ),
				array( 'key_string' => 'C', 'name_string' => 'Closed', 'description_string' => '', 'lang' => 'en' ),
			)
		);
		$state   = $this->mock_bmlt_router( array( 'GetFormats' => $body ) );
		$formats = $this->helper->getFormatsJson( 'https://example.org/main_server' );
		$this->assertSame( 'C', $formats[0]['key_string'] );
		$this->assertSame( 'O', $formats[1]['key_string'] );
		$this->unmock_http( $state );
	}

	public function test_get_formats_json_filters_by_language() {
		$body = wp_json_encode(
			array(
				array( 'key_string' => 'O', 'name_string' => 'Open', 'description_string' => '', 'lang' => 'en' ),
				array( 'key_string' => 'A', 'name_string' => 'Abierto', 'description_string' => '', 'lang' => 'es' ),
			)
		);
		$state   = $this->mock_bmlt_router( array( 'GetFormats' => $body ) );
		$formats = $this->helper->getFormatsJson( 'https://example.org/main_server', 'es' );
		$this->assertCount( 1, $formats );
		$this->assertSame( 'A', $formats[0]['key_string'] );
		$this->unmock_http( $state );
	}

	public function test_get_formats_json_error_passthrough() {
		$state   = $this->mock_http( function () {
			return new WP_Error( 'http_request_failed', 'down' );
		} );
		$formats = $this->helper->getFormatsJson( 'https://example.org/main_server' );
		$this->assertArrayHasKey( 'error', $formats );
		$this->unmock_http( $state );
	}

	// -------------------------------------------------------------------------
	// Helpers HTTP client — getMeetingsJson
	// -------------------------------------------------------------------------

	public function test_get_meetings_json_builds_services_query() {
		$state = $this->mock_bmlt_router(
			array( 'GetSearchResults' => wp_json_encode( array( $this->sample_meeting( array( 'id_bigint' => (string) wp_rand( 1, 99999 ) ) ) ) ) )
		);
		$this->helper->getMeetingsJson( 'https://example.org/main_server', '1047,1048', 'America/New_York', 15, true, 5, '' );
		$joined = implode( ' ', $state->urls );
		$this->assertStringContainsString( 'switcher=GetSearchResults', $joined );
		$this->assertStringContainsString( 'services[]=1047', $joined );
		$this->assertStringContainsString( 'services[]=1048', $joined );
		$this->assertStringContainsString( 'recursive=1', $joined );
		$this->unmock_http( $state );
	}

	public function test_get_meetings_json_omits_recursive_when_not_recursive() {
		$state = $this->mock_bmlt_router( array( 'GetSearchResults' => '[]' ) );
		$this->helper->getMeetingsJson( 'https://example.org/main_server', '1', 'America/New_York', 15, false, 5, '' );
		$this->assertStringNotContainsString( 'recursive=1', implode( ' ', $state->urls ) );
		$this->unmock_http( $state );
	}

	public function test_get_meetings_json_today_query_has_starts_after() {
		$state = $this->mock_bmlt_router( array( 'GetSearchResults' => '[]' ) );
		$this->helper->getMeetingsJson( 'https://example.org/main_server', '1', 'America/New_York', 15, false, 5, '' );
		$this->assertStringContainsString( 'StartsAfterH=', implode( ' ', $state->urls ) );
		$this->unmock_http( $state );
	}

	/**
	 * Issue #2: specific meeting ids (bread/crouton style) are queried via meeting_ids[].
	 */
	public function test_get_meetings_json_builds_meeting_ids_query() {
		$state = $this->mock_bmlt_router( array( 'GetSearchResults' => '[]' ) );
		$this->helper->getMeetingsJson( 'https://example.org/main_server', '', 'America/New_York', 15, false, 5, '', false, '123,456' );
		$joined = implode( ' ', $state->urls );
		$this->assertStringContainsString( 'meeting_ids[]=123', $joined );
		$this->assertStringContainsString( 'meeting_ids[]=456', $joined );
		// With no service body, no services[] should be requested.
		$this->assertStringNotContainsString( 'services[]=', $joined );
		$this->unmock_http( $state );
	}

	public function test_get_meetings_json_appends_custom_query() {
		$state = $this->mock_bmlt_router( array( 'GetSearchResults' => '[]' ) );
		$this->helper->getMeetingsJson( 'https://example.org/main_server', '1', 'America/New_York', 15, false, 5, '&formats=54' );
		$this->assertStringContainsString( 'formats=54', implode( ' ', $state->urls ) );
		$this->unmock_http( $state );
	}

	public function test_get_meetings_json_slices_to_num_results() {
		$meetings = array();
		for ( $i = 1; $i <= 8; $i++ ) {
			$meetings[] = $this->sample_meeting(
				array(
					'id_bigint'  => (string) $i,
					'start_time' => sprintf( '%02d:00:00', $i + 8 ),
				)
			);
		}
		$state  = $this->mock_bmlt_router( array( 'GetSearchResults' => wp_json_encode( $meetings ) ) );
		$result = $this->helper->getMeetingsJson( 'https://example.org/main_server', '1', 'America/New_York', 15, false, 3, '' );
		$this->assertCount( 3, $result );
		// Today filled the list, so only one request was made.
		$this->assertCount( 1, $state->urls );
		$this->unmock_http( $state );
	}

	public function test_get_meetings_json_dedupes_by_id() {
		$dupe     = $this->sample_meeting( array( 'id_bigint' => '7' ) );
		$state    = $this->mock_bmlt_router( array( 'GetSearchResults' => wp_json_encode( array( $dupe, $dupe ) ) ) );
		$result   = $this->helper->getMeetingsJson( 'https://example.org/main_server', '1', 'America/New_York', 15, false, 5, '', true );
		$this->assertCount( 1, $result );
		$this->unmock_http( $state );
	}

	public function test_get_meetings_json_limit_to_today_makes_single_request() {
		$state = $this->mock_bmlt_router( array( 'GetSearchResults' => '[]' ) );
		$this->helper->getMeetingsJson( 'https://example.org/main_server', '1', 'America/New_York', 15, false, 5, '', true );
		// limit_to_today never fetches tomorrow, so exactly one services request.
		$this->assertCount( 1, $state->urls );
		$this->unmock_http( $state );
	}

	public function test_get_meetings_json_error_passthrough() {
		$state  = $this->mock_http( function () {
			return new WP_Error( 'http_request_failed', 'down' );
		} );
		$result = $this->helper->getMeetingsJson( 'https://example.org/main_server', '1', 'America/New_York', 15, false, 5, '' );
		$this->assertArrayHasKey( 'error', $result );
		$this->unmock_http( $state );
	}

	// -------------------------------------------------------------------------
	// Shortcode — [upcoming_meetings]
	// -------------------------------------------------------------------------

	public function test_shortcode_missing_root_server_shows_error() {
		$html = do_shortcode( '[upcoming_meetings]' );
		$this->assertStringContainsString( 'Root Server missing', $html );
	}

	public function test_shortcode_missing_services_and_meetings_shows_error() {
		$html = do_shortcode( '[upcoming_meetings root_server="https://example.org/main_server"]' );
		$this->assertStringContainsString( 'Services missing', $html );
	}

	public function test_shortcode_renders_meetings() {
		$state = $this->mock_bmlt_router(
			array( 'GetSearchResults' => wp_json_encode( array( $this->sample_meeting() ) ) )
		);
		$html  = do_shortcode( '[upcoming_meetings root_server="https://example.org/main_server" services="1"]' );
		$this->assertStringContainsString( 'Monday Night Group', $html );
		$this->assertStringNotContainsString( 'Services missing', $html );
		$this->unmock_http( $state );
	}

	/**
	 * Issue #2: providing only meeting ids (no service body) is valid and does not error.
	 */
	public function test_shortcode_meetings_attribute_without_services_is_valid() {
		$state = $this->mock_bmlt_router(
			array( 'GetSearchResults' => wp_json_encode( array( $this->sample_meeting() ) ) )
		);
		$html  = do_shortcode( '[upcoming_meetings root_server="https://example.org/main_server" meetings="123"]' );
		$this->assertStringNotContainsString( 'Services missing', $html );
		$this->assertStringContainsString( 'Monday Night Group', $html );
		$this->unmock_http( $state );
	}

	public function test_build_meetings_html_table_display() {
		$state     = $this->mock_bmlt_router(
			array( 'GetSearchResults' => wp_json_encode( array( $this->sample_meeting() ) ) )
		);
		$shortcode = new Shortcode();
		$html      = $shortcode->buildMeetingsHtml( $this->default_args( array( 'display_type' => 'table' ) ) );
		$this->assertStringContainsString( 'upcoming_meetings_div', $html );
		$this->assertStringContainsString( 'bmlt_simple_meetings_table', $html );
		$this->unmock_http( $state );
	}

	public function test_build_meetings_html_invalid_timezone_falls_back() {
		$state     = $this->mock_bmlt_router( array( 'GetSearchResults' => '[]' ) );
		$shortcode = new Shortcode();
		// An invalid timezone must not throw; it falls back to America/New_York.
		$html = $shortcode->buildMeetingsHtml( $this->default_args( array( 'timezone' => 'Not/AZone' ) ) );
		$this->assertIsString( $html );
		$this->unmock_http( $state );
	}

	// -------------------------------------------------------------------------
	// Shortcode — area filter dropdown (issue #9)
	// -------------------------------------------------------------------------

	public function test_area_filter_renders_dropdown_for_region() {
		$state = $this->mock_bmlt_router(
			array(
				'GetServiceBodies' => $this->service_bodies_json(),
				'GetSearchResults' => wp_json_encode( array( $this->sample_meeting() ) ),
			)
		);
		$html = do_shortcode( '[upcoming_meetings root_server="https://example.org/main_server" services="1" show_area_filter="1"]' );
		$this->assertStringContainsString( 'upcoming-meetings-area-filter', $html );
		$this->assertStringContainsString( 'upcoming-meetings-area-select', $html );
		$this->assertStringContainsString( '<option value="all">All Areas</option>', $html );
		// Descendant areas appear as options.
		$this->assertStringContainsString( 'Area A', $html );
		$this->assertStringContainsString( 'Area B', $html );
		$this->unmock_http( $state );
	}

	public function test_area_filter_wraps_results_with_data_args() {
		$state = $this->mock_bmlt_router(
			array(
				'GetServiceBodies' => $this->service_bodies_json(),
				'GetSearchResults' => wp_json_encode( array( $this->sample_meeting() ) ),
			)
		);
		$html = do_shortcode( '[upcoming_meetings root_server="https://example.org/main_server" services="1" show_area_filter="1"]' );
		$this->assertStringContainsString( 'upcoming-meetings-results', $html );
		$this->assertStringContainsString( 'data-um-args=', $html );
		$this->unmock_http( $state );
	}

	public function test_area_filter_omitted_when_region_has_no_descendants() {
		// Query a leaf service body (4 = Subarea) which has no descendants.
		$state = $this->mock_bmlt_router(
			array(
				'GetServiceBodies' => $this->service_bodies_json(),
				'GetSearchResults' => wp_json_encode( array( $this->sample_meeting() ) ),
			)
		);
		$html = do_shortcode( '[upcoming_meetings root_server="https://example.org/main_server" services="4" show_area_filter="1"]' );
		$this->assertStringNotContainsString( 'upcoming-meetings-area-filter', $html );
		$this->unmock_http( $state );
	}

	public function test_area_filter_disabled_by_default() {
		$state = $this->mock_bmlt_router(
			array(
				'GetServiceBodies' => $this->service_bodies_json(),
				'GetSearchResults' => wp_json_encode( array( $this->sample_meeting() ) ),
			)
		);
		$html = do_shortcode( '[upcoming_meetings root_server="https://example.org/main_server" services="1"]' );
		$this->assertStringNotContainsString( 'upcoming-meetings-area-filter', $html );
		$this->unmock_http( $state );
	}

	// -------------------------------------------------------------------------
	// FormatsShortcode — [meeting_formats]
	// -------------------------------------------------------------------------

	public function test_formats_shortcode_missing_root_server_shows_error() {
		$html = do_shortcode( '[meeting_formats]' );
		$this->assertStringContainsString( 'Root Server missing', $html );
	}

	public function test_formats_shortcode_renders_table() {
		$body  = wp_json_encode(
			array(
				array( 'key_string' => 'O', 'name_string' => 'Open', 'description_string' => 'Open meeting', 'lang' => 'en' ),
			)
		);
		$state = $this->mock_bmlt_router( array( 'GetFormats' => $body ) );
		$html  = do_shortcode( '[meeting_formats root_server="https://example.org/main_server"]' );
		$this->assertStringContainsString( 'bmlt_formats_table', $html );
		$this->assertStringContainsString( 'Open', $html );
		$this->unmock_http( $state );
	}

	public function test_formats_shortcode_renders_list() {
		$body  = wp_json_encode(
			array(
				array( 'key_string' => 'O', 'name_string' => 'Open', 'description_string' => 'Open meeting', 'lang' => 'en' ),
			)
		);
		$state = $this->mock_bmlt_router( array( 'GetFormats' => $body ) );
		$html  = do_shortcode( '[meeting_formats root_server="https://example.org/main_server" display_type="list"]' );
		$this->assertStringContainsString( 'bmlt_formats_list', $html );
		$this->unmock_http( $state );
	}

	public function test_formats_shortcode_error_passthrough() {
		$state = $this->mock_http( function () {
			return new WP_Error( 'http_request_failed', 'down' );
		} );
		$html  = do_shortcode( '[meeting_formats root_server="https://example.org/main_server"]' );
		$this->assertStringContainsString( 'Meeting Formats Error', $html );
		$this->unmock_http( $state );
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	public function test_settings_creates_default_options() {
		delete_option( 'upcoming_meetings_options' );
		$settings = new Settings();
		$this->assertSame( '15', $settings->options['grace_period_dropdown'] );
		$this->assertSame( '5', $settings->options['num_results_dropdown'] );
		$this->assertSame( 'simple', $settings->options['display_type_dropdown'] );
		$this->assertSame( 'en', $settings->options['weekday_language_dropdown'] );
		$this->assertSame( '0', $settings->options['show_area_filter_checkbox'] );
	}

	public function test_settings_strips_php_suffix_from_root_server() {
		update_option(
			'upcoming_meetings_options',
			array( 'root_server' => 'https://example.org/main_server/index.php' )
		);
		$settings = new Settings();
		$this->assertSame( 'https://example.org/main_server', $settings->options['root_server'] );
	}

	public function test_settings_strips_trailing_slash_from_root_server() {
		update_option(
			'upcoming_meetings_options',
			array( 'root_server' => 'https://example.org/main_server/' )
		);
		$settings = new Settings();
		$this->assertSame( 'https://example.org/main_server', $settings->options['root_server'] );
	}

	public function test_settings_plugin_action_link_added() {
		$settings = new Settings();
		$links    = $settings->filterPluginActions( array() );
		$this->assertCount( 1, $links );
		$this->assertStringContainsString( 'options-general.php?page=upcoming-meetings-bmlt', $links[0] );
	}

	public function test_settings_lang_dropdown_options() {
		$settings = new Settings();
		$html     = $settings->printLangDropdownOption( 'en' );
		$this->assertStringContainsString( 'value="en"', $html );
		$this->assertStringContainsString( 'value="es"', $html );
		$this->assertMatchesRegularExpression( '/selected="selected" value="en"/', $html );
	}
}
