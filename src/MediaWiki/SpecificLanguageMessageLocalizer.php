<?php

namespace EntitySchema\MediaWiki;

use Message;
use MessageLocalizer;

/**
 * @license GPL-2.0-or-later
 */
class SpecificLanguageMessageLocalizer implements MessageLocalizer {

	/** @var string */
	private $languageCode;

	/**
	 * @param string $languageCode
	 */
	public function __construct( $languageCode ) {
		$this->languageCode = $languageCode;
	}

	public function msg( $key, ...$params ) {
		$message = ( new Message( $key, [] ) )->inLanguage( $this->languageCode );

		if ( $params ) {
			// we use ->params() instead of the $params constructor parameter
			// because ->params() supports some additional calling conventions,
			// which our callers might also have used
			$message->params( ...$params );
		}

		return $message;
	}

}
