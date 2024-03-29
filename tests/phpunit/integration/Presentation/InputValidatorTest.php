<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Presentation;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Presentation\InputValidator;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Config\HashConfig;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Message;
use MessageLocalizer;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\Presentation\InputValidator
 */
class InputValidatorTest extends MediaWikiIntegrationTestCase {

	public function testValidateId(): void {
		$this->createTestSchema();

		$inputValidator = $this->getInputValidator();
		$this->assertTrue( $inputValidator->validateIDExists( 'E123' ) );
	}

	public function testValidateIdEmpty(): void {
		$inputValidator = $this->getInputValidator();
		$this->assertNotTrue( $inputValidator->validateIDExists( '' ) );
	}

	public function testValidateIdWrongId(): void {
		$inputValidator = $this->getInputValidator();
		$this->assertNotTrue( $inputValidator->validateIDExists( 'bla' ) );
	}

	public function testValidateLangCode(): void {
		$inputValidator = $this->getInputValidator();
		$this->assertTrue( $inputValidator->validateLangCodeIsSupported( 'de' ) );
	}

	public function testValidateLangCodeEmpty(): void {
		$inputValidator = $this->getInputValidator();
		$this->assertNotTrue( $inputValidator->validateLangCodeIsSupported( '' ) );
	}

	public function testValidateLangCodeWrongCode(): void {
		$inputValidator = $this->getInputValidator();
		$this->assertNotTrue( $inputValidator->validateLangCodeIsSupported( 'i do not exist' ) );
	}

	public function testSchemaTextLengthPass(): void {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertTrue( $inputValidator->validateSchemaTextLength( 'abcde' ) );
	}

	public function testSchemaTextLengthFail(): void {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertNotTrue( $inputValidator->validateSchemaTextLength( 'abcdä' ) );
	}

	public function testAliasesLengthPass(): void {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertTrue( $inputValidator->validateAliasesLength( 'ab | cd | ä' ) );
	}

	public function testAliasesLengthFail(): void {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertNotTrue( $inputValidator->validateAliasesLength( 'ab | cd | ef' ) );
	}

	public function testInputStringLengthPass(): void {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertTrue( $inputValidator->validateStringInputLength( 'abcdä' ) );
	}

	public function testInputStringLengthFail(): void {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertNotTrue( $inputValidator->validateStringInputLength( 'abcdef' ) );
	}

	private function getInputValidator( int $configLengthToReturn = null ): InputValidator {
		$config = new HashConfig( [
			'EntitySchemaNameBadgeMaxSizeChars' => $configLengthToReturn,
			'EntitySchemaSchemaTextMaxSizeBytes' => $configLengthToReturn,
		] );
		$msgLocalizer = $this->getMockBuilder(
			MessageLocalizer::class
		)->getMock();
		$msgLocalizer->method( 'msg' )->willReturn(
			$this->getMockBuilder( Message::class )->disableOriginalConstructor()->getMock()
		);
		return new InputValidator(
			$msgLocalizer,
			$config,
			$this->getServiceContainer()->getLanguageNameUtils()
		);
	}

	private function createTestSchema(): void {
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );
		$this->saveSchemaPageContent(
			$page,
			[
				'labels' => [ 'en' => 'Schema label' ],
				'descriptions' => [ 'en' => 'Schema description' ],
				'aliases' => [],
				'schemaText' => 'abc',
				'serializationVersion' => '3.0',
			]
		);
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ): void {
		$updater = $page->newPageUpdater( self::getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new EntitySchemaContent( json_encode( $content ) ) );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);
	}

}
