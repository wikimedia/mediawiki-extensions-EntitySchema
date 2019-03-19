<?php

namespace Wikibase\Schema\Tests\Presentation;

use CommentStoreComment;
use Config;
use MediaWiki\Revision\SlotRecord;
use MediaWikiTestCase;
use Message;
use MessageLocalizer;
use Title;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Presentation\InputValidator;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \Wikibase\Schema\Presentation\InputValidator
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
		$this->assertTrue( $inputValidator->validateIDExists( 'O123' ) );
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
		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );
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
		$updater->setContent( SlotRecord::MAIN, new WikibaseSchemaContent( json_encode( $content ) ) );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);
	}

}
