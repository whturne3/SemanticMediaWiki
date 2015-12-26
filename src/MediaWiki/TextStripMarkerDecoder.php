<?php

namespace SMW\MediaWiki;

use StripState;
use Parser;

/**
 * @license GNU GPL v2+
 * @since 2.4
 *
 * @author mwjames
 */
class TextStripMarkerDecoder {

	/**
	 * @var StripState
	 */
	private $stripState = null;

	/**
	 * @var boolean|text
	 */
	private $unmodifiedText = false;

	/**
	 * @var boolean
	 */
	private $decoderState = false;

	/**
	 * @since 2.4
	 *
	 * @param StripState $stripState
	 */
	public function __construct( StripState $stripState ) {
		$this->stripState = $stripState;
	}

	/**
	 * @since 2.4
	 *
	 * @param boolean $decoderState
	 */
	public function setDecoderUsageState( $decoderState ) {
		$this->decoderState = $decoderState;
	}

	/**
	 * @since 2.4
	 *
	 * @return boolean
	 */
	public function canUse() {
		return $this->decoderState;
	}

	/**
	 * @since 2.4
	 *
	 * @param string $text
	 *
	 * @return boolean
	 */
	public function hasStripMarker( $text ) {
		return strpos( $text, Parser::MARKER_SUFFIX );
	}

	/**
	 * @since 2.4
	 *
	 * @return text
	 */
	public function getUnmodifiedText() {
		return $this->unmodifiedText;
	}

	/**
	 * @since 2.4
	 *
	 * @return text
	 */
	public function unstrip( $text ) {

		$this->unmodifiedText = $text;

		// Escape the text case to avoid any HTML elements
		// cause an issue during parsing
		return str_replace(
			array( '<', '>', ' ', '[', '{', '=', "'", ':', "\n" ),
			array( '&lt;', '&gt;', ' ', '&#x005B;', '&#x007B;', '&#x003D;', '&#x0027;', '&#58;', "<br />" ),
			$this->doUnstrip( $text )
		);
	}

	public function doUnstrip( $text ) {

		if ( ( $value = $this->stripState->unstripNoWiki( $text ) ) !== '' && !$this->hasStripMarker( $value ) ) {
			return $this->addNoWikiToUnstripValue( $value );
		}

		if ( ( $value = $this->stripState->unstripGeneral( $text ) ) !== '' && !$this->hasStripMarker( $value ) ) {
			return $value;
		}

	    return $this->doUnstrip( $value );
	}

	private function addNoWikiToUnstripValue( $text ) {
		return '<nowiki>' . $text . '</nowiki>';
	}

}
