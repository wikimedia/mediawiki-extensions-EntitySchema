<?php

namespace EntitySchema\Tests\Integration\Presentation;

use CommentStoreComment;
use Config;
use MediaWiki\Revision\SlotRecord;
use MediaWikiTestCase;
use Message;
use MessageLocalizer;
use Title;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Presentation\InputValidator;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\Presentation\InputValidator
 */
class InputValidatorTest extends MediaWikiTestCase {

	protected function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'recentchanges';
	}

	public function testValidateId() {
		$this->createTestSchema();

		$inputValidator = $this->getInputValidator();
		$this->assertTrue( $inputValidator->validateIDExists( 'E123' ) );
	}

	public function testValidateIdEmpty() {
		$inputValidator = $this->getInputValidator();
		$this->assertNotTrue( $inputValidator->validateIDExists( '' ) );
	}

	public function testValidateIdWrongId() {
		$inputValidator = $this->getInputValidator();
		$this->assertNotTrue( $inputValidator->validateIDExists( 'bla' ) );
	}

	public function testValidateLangCode() {
		$inputValidator = $this->getInputValidator();
		$this->assertTrue( $inputValidator->validateLangCodeIsSupported( 'de' ) );
	}

	public function testValidateLangCodeEmpty() {
		$inputValidator = $this->getInputValidator();
		$this->assertNotTrue( $inputValidator->validateLangCodeIsSupported( '' ) );
	}

	public function testValidateLangCodeWrongCode() {
		$inputValidator = $this->getInputValidator();
		$this->assertNotTrue( $inputValidator->validateLangCodeIsSupported( 'i do not exist' ) );
	}

	public function testSchemaTextLengthPass() {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertTrue( $inputValidator->validateSchemaTextLength( 'abcde' ) );
	}

	public function testSchemaTextLengthFail() {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertNotTrue( $inputValidator->validateSchemaTextLength( 'abcdä' ) );
	}

	public function testAliasesLengthPass() {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertTrue( $inputValidator->validateAliasesLength( 'ab | cd | ä' ) );
	}

	public function testAliasesLengthFail() {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertNotTrue( $inputValidator->validateAliasesLength( 'ab | cd | ef' ) );
	}

	public function testInputStringLengthPass() {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertTrue( $inputValidator->validateStringInputLength( 'abcdä' ) );
	}

	public function testInputStringLengthFail() {
		$inputValidator = $this->getInputValidator( 5 );
		$this->assertNotTrue( $inputValidator->validateStringInputLength( 'abcdef' ) );
	}

	private function getInputValidator( $configLengthToReturn = null ): InputValidator {
		$mockConfig = $this->getMockBuilder(
			Config::class
		)->getMock();
		$mockConfig->method( 'get' )->willReturn( $configLengthToReturn );
		$msgLocalizer = $this->getMockBuilder(
			MessageLocalizer::class
		)->getMock();
		$msgLocalizer->method( 'msg' )->willReturn(
			$this->getMockBuilder( Message::class )->disableOriginalConstructor()->getMock()
		);
		return new InputValidator(
			$msgLocalizer,
			$mockConfig
		);
	}

	private function createTestSchema() {
		$page = WikiPage::factory( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );
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

	private function saveSchemaPageContent( WikiPage $page, array $content ) {
		$updater = $page->newPageUpdater( self::getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new EntitySchemaContent( json_encode( $content ) ) );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);
	}

}
