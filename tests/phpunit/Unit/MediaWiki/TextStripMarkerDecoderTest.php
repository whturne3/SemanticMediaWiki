<?php

namespace SMW\Tests\MediaWiki;

use SMW\MediaWiki\TextStripMarkerDecoder;

use Title;

/**
 * @covers \SMW\MediaWiki\TextStripMarkerDecoder
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since  2.4
 *
 * @author mwjames
 */
class TextStripMarkerDecoderTest extends \PHPUnit_Framework_TestCase {

	public function testCanConstruct() {

		$stripState = $this->getMockBuilder( '\StripState' )
			->disableOriginalConstructor()
			->getMock();

		$this->assertInstanceOf(
			'\SMW\MediaWiki\TextStripMarkerDecoder',
			new TextStripMarkerDecoder( $stripState )
		);
	}


}
