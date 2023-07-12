<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use InvalidArgumentException;

/**
 * Immutable value object.
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaTerm {

	/**
	 * @var string MediaWiki language code identifying the language of the text
	 */
	private string $languageCode;

	private string $text;

	/**
	 * @param string $languageCode Language of the text, validating it is the responsibility of the caller
	 * @param string $text
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( string $languageCode, string $text ) {
		if ( $languageCode === '' ) {
			throw new InvalidArgumentException( '$languageCode must be a non-empty string' );
		}

		$this->languageCode = $languageCode;
		$this->text = $text;
	}

	public function getLanguageCode(): string {
		return $this->languageCode;
	}

	public function getText(): string {
		return $this->text;
	}
}
