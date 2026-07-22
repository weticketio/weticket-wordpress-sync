<?php
/**
 * Unit tests for the feed Parser, Mapper and Twig renderer.
 *
 * @package WeTicket\Sync
 */

namespace WeTicket\Sync\Tests;

use PHPUnit\Framework\TestCase;
use WeTicket\Sync\Feed\Client;
use WeTicket\Sync\Feed\Parser;
use WeTicket\Sync\Mapper;
use WeTicket\Sync\Templates\Templates;

class ParserTest extends TestCase {

	/** @return array */
	private function parse_fixture() {
		$xml = file_get_contents( __DIR__ . '/fixtures/events.xml' );
		// Apply the same encoding normalization the Client performs.
		$xml = preg_replace( '/(<\?xml[^>]*encoding=")[^"]*(")/i', '${1}utf-8${2}', $xml, 1 );
		return ( new Parser() )->parse( $xml );
	}

	public function test_parses_feed_despite_encoding_mismatch() {
		$events = $this->parse_fixture();

		$this->assertIsArray( $events );
		$this->assertCount( 2, $events );

		$first = $events[0];
		$this->assertSame( 'b5c21daf-28cb-4ba5-8509-0ea5aad0295c', $first['guid'] );
		$this->assertSame( 'Kantoor POS', $first['title'] );
		$this->assertSame( '2026-12-02T11:00:00.0000000Z', $first['start'] );
		$this->assertSame( '2026-12-03T11:13:00.0000000Z', $first['end'] );
		$this->assertSame( 'WeTicket HQ', $first['location'] );
		$this->assertSame( 'Kantoor Terminal Test', $first['organizer'] );
		$this->assertSame( 'https://terminal-test.staging.weticket.io/kantoor-pos', $first['link'] );
	}

	public function test_extracts_images_from_all_supported_formats() {
		$events = $this->parse_fixture();

		// Item 0: two media:content images.
		$this->assertSame( 'https://cdn.example.com/cover.jpg', $events[0]['image_url'] );
		$this->assertSame(
			array( 'https://cdn.example.com/cover.jpg', 'https://cdn.example.com/extra.png' ),
			$events[0]['image_urls']
		);

		// Item 1: media:thumbnail (preferred as main) plus an image enclosure.
		$this->assertSame( 'https://cdn.example.com/thumb.jpg', $events[1]['image_url'] );
		$this->assertContains( 'https://cdn.example.com/enc.jpg', $events[1]['image_urls'] );
	}

	public function test_mapper_produces_template_variables() {
		$events = $this->parse_fixture();
		$data   = ( new Mapper() )->to_template_data( $events[0] );

		$this->assertSame( 'WeTicket HQ', $data['venue']['title'] );
		$this->assertSame( 'WeTicket HQ', $data['location']['name'] );
		$this->assertSame( 'onsale', $data['status'] );
		$this->assertSame( 'https://cdn.example.com/cover.jpg', $data['media']['mainImageUrl'] );
		$this->assertSame( array( 'https://cdn.example.com/extra.png' ), $data['media']['additionalImageUrls'] );
		$this->assertSame( 'Kantoor Terminal Test', $data['organizationName'] );
	}

	public function test_parses_plain_and_html_descriptions() {
		$events = $this->parse_fixture();

		$this->assertSame( "Hallo wereld\nRegel twee", $events[0]['description'] );
		// content:encoded HTML is read out of its CDATA section.
		$this->assertSame( '<p>Hallo <b>wereld</b></p><ol><li>Feest</li></ol>', $events[0]['text_html'] );

		// Item without content:encoded has an empty HTML description.
		$this->assertSame( '', $events[1]['text_html'] );
	}

	public function test_parses_short_description() {
		$events = $this->parse_fixture();
		$mapper = new Mapper();

		$this->assertSame( 'Feest, DJs en bier op kantoor.', $events[0]['short_description'] );
		$this->assertSame( 'Feest, DJs en bier op kantoor.', $mapper->to_template_data( $events[0] )['shortDescription'] );
		$this->assertSame( 'Feest, DJs en bier op kantoor.', $mapper->to_meta( $events[0] )['weticket_short_description'] );

		// Item without weticket:short_description falls back to empty.
		$this->assertSame( '', $events[1]['short_description'] );
	}

	public function test_mapper_exposes_text_and_texthtml() {
		$events = $this->parse_fixture();
		$data   = ( new Mapper() )->to_template_data( $events[0] );

		$this->assertSame( "Hallo wereld\nRegel twee", $data['text'] );
		$this->assertSame( '<p>Hallo <b>wereld</b></p><ol><li>Feest</li></ol>', $data['textHtml'] );
	}

	public function test_default_template_renders_html_description_unescaped() {
		$events = $this->parse_fixture();
		$data   = ( new Mapper() )->to_template_data( $events[0] );
		$tpl    = file_get_contents( WETICKET_SYNC_PATH . 'templates/default-content.twig' );

		$output = Templates::render( $tpl, $data );

		$this->assertFalse( is_wp_error( $output ) );
		// HTML description rendered raw, not escaped.
		$this->assertStringContainsString( '<b>wereld</b>', $output );
		$this->assertStringContainsString( '<ol><li>Feest</li></ol>', $output );
		$this->assertStringNotContainsString( '&lt;b&gt;', $output );
	}

	public function test_render_outputs_inline_image_in_default_template() {
		$events = $this->parse_fixture();
		$data   = ( new Mapper() )->to_template_data( $events[0] );
		$tpl    = file_get_contents( WETICKET_SYNC_PATH . 'templates/default-content.twig' );

		$output = Templates::render( $tpl, $data );

		$this->assertFalse( is_wp_error( $output ) );
		$this->assertStringContainsString( '<img src="https://cdn.example.com/cover.jpg"', $output );
		$this->assertStringContainsString( 'https://cdn.example.com/extra.png', $output );
	}

	public function test_meta_keys_are_public_and_match_field_registry() {
		$events = $this->parse_fixture();
		$meta   = ( new Mapper() )->to_meta( $events[0] );
		$keys   = array_keys( $meta );

		// No protected (underscore-prefixed) keys among the public fields.
		foreach ( $keys as $key ) {
			$this->assertStringStartsNotWith( '_', $key );
		}

		// The mapper keys match the registered field registry exactly.
		$this->assertSame( \WeTicket\Sync\Fields::keys(), $keys );
		$this->assertSame( 'WeTicket HQ', $meta['weticket_venue'] );
		$this->assertSame( 'onsale', $meta['weticket_status'] );
	}

	public function test_render_handles_unavailable_fields_without_error() {
		$events   = $this->parse_fixture();
		$data     = ( new Mapper() )->to_template_data( $events[0] );
		$template = "{% if prices %}<ul>{% for p in prices %}<li>{{ p.title }}</li>{% endfor %}</ul>{% endif %}OK";

		$output = Templates::render( $template, $data );

		$this->assertFalse( is_wp_error( $output ), 'Unavailable fields must not raise an error.' );
		$this->assertSame( 'OK', $output );
	}

	public function test_render_uses_i18n_date_filter() {
		$events = $this->parse_fixture();
		$data   = ( new Mapper() )->to_template_data( $events[0] );

		$output = Templates::render( '{{ start|date("Y") }}', $data );
		$this->assertSame( '2026', $output );
	}

	public function test_date_filter_converts_utc_to_site_timezone() {
		$events = $this->parse_fixture();
		$data   = ( new Mapper() )->to_template_data( $events[0] );

		// Feed start is 11:00 UTC (with 7-digit fractional seconds). In
		// Europe/Amsterdam (UTC+1 in December) that is 12:00 local time.
		$output = Templates::render( '{{ start|date("Y-m-d H:i") }}', $data );
		$this->assertSame( '2026-12-02 12:00', $output );
	}

	public function test_admin_columns_inserted_after_title() {
		$columns = ( new \WeTicket\Sync\Admin\EventColumns() )->columns(
			array(
				'cb'    => '',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$this->assertSame(
			array( 'cb', 'title', 'weticket_start', 'weticket_venue', 'date' ),
			array_keys( $columns )
		);
		// The built-in date column is relabeled to avoid confusion with start.
		$this->assertSame( 'Creation date', $columns['date'] );
	}

	public function test_render_returns_wp_error_on_syntax_error() {
		$output = Templates::render( '{{ unclosed', array() );
		$this->assertTrue( is_wp_error( $output ) );
	}
}
