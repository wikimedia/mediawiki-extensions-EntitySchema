<?php

namespace Wikibase\Schema\Tests\MediaWiki\Specials;

use SpecialPageTestBase;
use FauxRequest;
use WikiPage;
use Wikibase\Schema\Domain\Model\SchemaId;
use Title;
use MediaWiki\Revision\SlotRecord;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use CommentStoreComment;
use MediaWiki\MediaWikiServices;
use Wikibase\Schema\MediaWiki\Specials\SetSchemaLabelDescriptionAliases;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Wikibase\Schema\MediaWiki\Specials\SetSchemaLabelDescriptionAliases
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class SetSchemaLabelDescriptionAliasesTest extends SpecialPageTestBase {

	protected function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
	}

	protected function newSpecialPage() {
		return new SetSchemaLabelDescriptionAliases();
	}

	public function testSubmitSelectionCallbackCorrectId() {
		$this->createTestSchema();

		$dataGood = [
			'ID' => 'O123',
			'languagecode' => 'en'
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoGood = $setSchemaInfo->submitSelectionCallback( $dataGood );
		$this->assertSame( true, $infoGood->ok, 'Schema ID or Language Code is wrong or empty' );
	}

	public function testSubmitSelectionCallbackEmptyId() {
		$this->createTestSchema();

		$dataIncomplete = [
			'ID' => '',
			'languagecode' => 'en'
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoIncomplete = $setSchemaInfo->submitSelectionCallback( $dataIncomplete );
		$this->assertSame( 'error',
			$infoIncomplete->errors[0]['type'],
			'The object $infoIncomplete should contain an error'
		);
	}

	public function testSubmitSelectionCallbackWrongId() {
		$this->createTestSchema();

		$dataWrong = [
			'ID' => 'bla',
			'languagecode' => 'en'
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoFalse = $setSchemaInfo->submitSelectionCallback( $dataWrong );

		$this->assertSame(
			'error',
			$infoFalse->errors[0]['type'],
			'The object $infoFalse should contain an error'
		);
	}

	public function testSubmitEditFormCallbackCorrectId() {
		$this->createTestSchema();

		$dataGood = [
			'ID' => 'O123',
			'languagecode' => 'en',
			'label' => 'Schema label',
			'description' => '',
			'aliases' => 'foo | bar | baz',
			'schema-shexc' => 'abc'
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoGood = $setSchemaInfo->submitEditFormCallback( $dataGood );

		$this->assertTrue( $infoGood->ok );
	}

	public function testSubmitEditFormCallbackWrongId() {
		$dataWrong = [
			'ID' => 'O129999999990',
			'languagecode' => 'en',
			'label' => 'Schema label',
			'description' => '',
			'aliases' => 'foo | bar | baz',
			'schema-shexc' => 'abc'
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoFalse = $setSchemaInfo->submitEditFormCallback( $dataWrong );

		$this->assertFalse( $infoFalse->ok );
		$this->assertSame( 'error',
			$infoFalse->errors[0]['type'],
			'The object $infoIncomplete should contain an error'
		);
	}

	public function testSubmitEditFormCallbackMissingId() {
		$dataIncomplete = [
			'ID' => '',
			'languagecode' => 'en',
			'label' => 'Schema label',
			'description' => '',
			'aliases' => 'foo | bar | baz',
			'schema-shexc' => 'abc'
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoIncomplete = $setSchemaInfo->submitEditFormCallback( $dataIncomplete );

		$this->assertFalse( $infoIncomplete->ok );
	}

	public function testValidateSchemaSelectionFormData() {
		$this->createTestSchema();
		$schemaId = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->validateSchemaSelectionFormData( 'O123', 'en' );

		$this->assertInstanceOf( SchemaId::class, $schemaId );
		$this->assertSame( 'O123', $schemaId->getId() );
	}

	public function testValidateSchemaSelectionFormDataNoLanguageCode() {
		$schemaId = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->validateSchemaSelectionFormData( 'O123', null );

		$this->assertFalse( $schemaId );
	}

	public function testValidateSchemaSelectionFormDataInvalidId() {
		$schemaId = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->validateSchemaSelectionFormData( 'Q1111', 'en' );

		$this->assertFalse( $schemaId );
	}

	public function testValidateSchemaSelectionFormDataNonexistentSchema() {
		$schemaId = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->validateSchemaSelectionFormData( 'O1111111111', 'en' );

		$this->assertFalse( $schemaId );
	}

	public function testExecute() {
		$specialPage = $this->executeSpecialPage(
			null,
			new FauxRequest(
				[
					'title' => 'Special:SetSchemaLabelDescriptionAliases',
				],
				true
			)
		);
	   $this->assertContains( "name='submit-selection'", $specialPage[0] );
	}

	/**
	 * @return array $actualSchema an array of Schema text + namebadge
	 */
	private function createTestSchema() {
		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );
		$this->saveSchemaPageContent(
			$page,
			[
				'label' => 'Schema label',
				'description' => 'Schema description',
				'aliases' => [],
				'schema' => 'abc'
			]
		);
		$actualSchema = $this->getCurrentSchemaContent( 'O123' );

		return $actualSchema;
	}

	private function getCurrentSchemaContent( $pageName ) {
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $pageName );
		$rev = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $title->getLatestRevID() );

		return json_decode( $rev->getContent( SlotRecord::MAIN )->getText(), true );
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ) {
		$updater = $page->newPageUpdater( self::getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new WikibaseSchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);
	}

}
