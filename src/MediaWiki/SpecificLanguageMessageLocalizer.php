<?php

namespace Wikibase\Schema\MediaWiki;

use Language;
use Message;
use MessageLocalizer;

/**
 * @license GPL-2.0-or-later
 */
class SpecificLanguageMessageLocalizer implements MessageLocalizer {

	/** @var Language */
	private $language;

	/**
	 * @param string $languageCode
	 */
	public function __construct( $languageCode ) {
		$this->language = Language::factory( $languageCode );
	}

	public function msg( $key, ...$params ) {
		$message = new Message( $key, [], $this->language );

		if ( $params ) {
			// we use ->params() instead of the $params constructor parameter
			// because ->params() supports some additional calling conventions,
			// which our callers might also have used
			$message->params( ...$params );
		}

		return $message;
	}

}
