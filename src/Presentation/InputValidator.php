<?php

namespace EntitySchema\Presentation;

use Config;
use ConfigException;
use EntitySchema\Domain\Model\SchemaId;
use InvalidArgumentException;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use Message;
use MessageLocalizer;
use RequestContext;
use Title;

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
	/**
	 * @var LanguageNameUtils
	 */
	private $languageNameUtils;

	public static function newFromGlobalState() {
		return new self(
			RequestContext::getMain(),
			MediaWikiServices::getInstance()->getMainConfig(),
			MediaWikiServices::getInstance()->getLanguageNameUtils()
		);
	}

	public function __construct(
		MessageLocalizer $msgLocalizer,
		Config $config,
		LanguageNameUtils $languageNameUtils
	) {
		$this->msgLocalizer = $msgLocalizer;
		$this->configService = $config;
		$this->languageNameUtils = $languageNameUtils;
	}

	/**
	 * @param $id
	 *
	 * @return true|Message returns true on success and Message on failure
	 */
	public function validateIDExists( $id ) {
		try {
			$schemaId = new SchemaId( $id );
		} catch ( InvalidArgumentException $e ) {
			return $this->msgLocalizer->msg( 'entityschema-error-invalid-id' );
		}
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $schemaId->getId() );
		if ( !$title->exists() ) {
			return $this->msgLocalizer->msg( 'entityschema-error-schemadeleted' );
		}

		return true;
	}

	/**
	 * @param $langCode
	 *
	 * @return true|Message returns true on success and Message on failure
	 */
	public function validateLangCodeIsSupported( $langCode ) {
		if ( !$this->languageNameUtils->isSupportedLanguage( $langCode ) ) {
			return $this->msgLocalizer->msg( 'entityschema-error-unsupported-langcode' );
		}
		return true;
	}

	/**
	 * @param $schemaText
	 *
	 * @return true|Message returns true on success and Message on failure
	 * @throws ConfigException
	 */
	public function validateSchemaTextLength( $schemaText ) {
		$maxLengthBytes = $this->configService->get( 'EntitySchemaSchemaTextMaxSizeBytes' );
		$schemaTextLengthBytes = strlen( $schemaText );
		if ( $schemaTextLengthBytes > $maxLengthBytes ) {
			return $this->msgLocalizer->msg( 'entityschema-error-schematext-too-long' )
				->numParams( $maxLengthBytes, $schemaTextLengthBytes );
		}

		return true;
	}

	public function validateAliasesLength( $aliasesInput ) {
		$maxLengthChars = $this->configService->get( 'EntitySchemaNameBadgeMaxSizeChars' );
		$cleanAliasesString = implode( '', array_map( 'trim', explode( '|', $aliasesInput ) ) );
		$aliasesLengthChars = mb_strlen( $cleanAliasesString );
		if ( $aliasesLengthChars > $maxLengthChars ) {
			return $this->msgLocalizer->msg( 'entityschema-error-input-too-long' )
				->numParams( $maxLengthChars, $aliasesLengthChars );
		}

		return true;
	}

	public function validateStringInputLength( $labelOrDescriptionInput ) {
		$maxLengthChars = $this->configService->get( 'EntitySchemaNameBadgeMaxSizeChars' );
		$numInputChars = mb_strlen( $labelOrDescriptionInput );
		if ( $numInputChars > $maxLengthChars ) {
			return $this->msgLocalizer->msg( 'entityschema-error-input-too-long' )
				->numParams( $maxLengthChars, $numInputChars );
		}

		return true;
	}

}
