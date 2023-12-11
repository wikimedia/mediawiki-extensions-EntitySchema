<?php

declare( strict_types = 1 );

namespace EntitySchema\Presentation;

use EntitySchema\Domain\Model\EntitySchemaId;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Message;
use MessageLocalizer;
use RequestContext;

/**
 * @license GPL-2.0-or-later
 */
class InputValidator {

	private MessageLocalizer $msgLocalizer;
	private Config $configService;
	private LanguageNameUtils $languageNameUtils;

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
	 * @param string $id
	 *
	 * @return true|Message returns true on success and Message on failure
	 */
	public function validateIDExists( string $id ) {
		try {
			$entitySchemaId = new EntitySchemaId( $id );
		} catch ( InvalidArgumentException $e ) {
			return $this->msgLocalizer->msg( 'entityschema-error-invalid-id' );
		}
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $entitySchemaId->getId() );
		if ( !$title->exists() ) {
			return $this->msgLocalizer->msg( 'entityschema-error-schemadeleted' );
		}

		return true;
	}

	/**
	 * @param string $langCode
	 *
	 * @return true|Message returns true on success and Message on failure
	 */
	public function validateLangCodeIsSupported( string $langCode ) {
		if ( !$this->languageNameUtils->isSupportedLanguage( $langCode ) ) {
			return $this->msgLocalizer->msg( 'entityschema-error-unsupported-langcode' );
		}
		return true;
	}

	/**
	 * @param string $schemaText
	 *
	 * @return true|Message returns true on success and Message on failure
	 * @throws ConfigException
	 */
	public function validateSchemaTextLength( string $schemaText ) {
		$maxLengthBytes = $this->configService->get( 'EntitySchemaSchemaTextMaxSizeBytes' );
		$schemaTextLengthBytes = strlen( $schemaText );
		if ( $schemaTextLengthBytes > $maxLengthBytes ) {
			return $this->msgLocalizer->msg( 'entityschema-error-schematext-too-long' )
				->numParams( $maxLengthBytes, $schemaTextLengthBytes );
		}

		return true;
	}

	public function validateAliasesLength( string $aliasesInput ) {
		$maxLengthChars = $this->configService->get( 'EntitySchemaNameBadgeMaxSizeChars' );
		$cleanAliasesString = implode( '', array_map( 'trim', explode( '|', $aliasesInput ) ) );
		$aliasesLengthChars = mb_strlen( $cleanAliasesString );
		if ( $aliasesLengthChars > $maxLengthChars ) {
			return $this->msgLocalizer->msg( 'entityschema-error-input-too-long' )
				->numParams( $maxLengthChars, $aliasesLengthChars );
		}

		return true;
	}

	public function validateStringInputLength( string $labelOrDescriptionInput ) {
		$maxLengthChars = $this->configService->get( 'EntitySchemaNameBadgeMaxSizeChars' );
		$numInputChars = mb_strlen( $labelOrDescriptionInput );
		if ( $numInputChars > $maxLengthChars ) {
			return $this->msgLocalizer->msg( 'entityschema-error-input-too-long' )
				->numParams( $maxLengthChars, $numInputChars );
		}

		return true;
	}

}
