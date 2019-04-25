<?php

namespace Wikibase\Schema\Presentation;

use Config;
use ConfigException;
use InvalidArgumentException;
use Language;
use Message;
use MediaWiki\MediaWikiServices;
use MessageLocalizer;
use RequestContext;
use Title;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 */
class InputValidator {

	/**
	 * @var MessageLocalizer
	 */
	private $msgLocalizer;
	/**
	 * @var Config
	 */
	private $configService;

	public static function newFromGlobalState() {
		return new self(
			RequestContext::getMain(),
			MediaWikiServices::getInstance()->getMainConfig()
		);
	}

	public function __construct( MessageLocalizer $msgLocalizer, Config $config ) {
		$this->msgLocalizer = $msgLocalizer;
		$this->configService = $config;
	}

	/**
	 * @param $id
	 *
	 * @return bool|Message returns true on success and Message on failure
	 */
	public function validateIDExists( $id ) {
		try {
			$schemaId = new SchemaId( $id );
		} catch ( InvalidArgumentException $e ) {
			return $this->msgLocalizer->msg( 'wikibaseschema-error-invalid-id' );
		}
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $schemaId->getId() );
		if ( !$title->exists() ) {
			return $this->msgLocalizer->msg( 'wikibaseschema-error-schemadeleted' );
		}

		return true;
	}

	/**
	 * @param $langCode
	 *
	 * @return bool|Message returns true on success and Message on failure
	 */
	public function validateLangCodeIsSupported( $langCode ) {
		if ( !Language::isSupportedLanguage( $langCode ) ) {
			return $this->msgLocalizer->msg( 'wikibaseschema-error-unsupported-langcode' );
		}
		return true;
	}

	/**
	 * @param $schemaText
	 *
	 * @return bool|Message returns true on success and Message on failure
	 * @throws ConfigException
	 */
	public function validateSchemaTextLength( $schemaText ) {
		$maxLengthBytes = $this->configService->get( 'WBSchemaSchemaTextMaxSizeBytes' );
		$schemaTextLengthBytes = strlen( $schemaText );
		if ( $schemaTextLengthBytes > $maxLengthBytes ) {
			return $this->msgLocalizer->msg( 'wikibaseschema-error-schematext-too-long' )
				->numParams( $maxLengthBytes, $schemaTextLengthBytes );
		}

		return true;
	}

	public function validateAliasesLength( $aliasesInput ) {
		$maxLengthChars = $this->configService->get( 'WBSchemaNameBadgeMaxSizeChars' );
		$cleanAliasesString = implode( '', array_map( 'trim', explode( '|', $aliasesInput ) ) );
		$aliasesLengthChars = mb_strlen( $cleanAliasesString );
		if ( $aliasesLengthChars > $maxLengthChars ) {
			return $this->msgLocalizer->msg( 'wikibaseschema-error-input-too-long' )
				->numParams( $maxLengthChars, $aliasesLengthChars );
		}

		return true;
	}

	public function validateStringInputLength( $labelOrDescriptionInput ) {
		$maxLengthChars = $this->configService->get( 'WBSchemaNameBadgeMaxSizeChars' );
		$numInputChars = mb_strlen( $labelOrDescriptionInput );
		if ( $numInputChars > $maxLengthChars ) {
			return $this->msgLocalizer->msg( 'wikibaseschema-error-input-too-long' )
				->numParams( $maxLengthChars, $numInputChars );
		}

		return true;
	}

}
