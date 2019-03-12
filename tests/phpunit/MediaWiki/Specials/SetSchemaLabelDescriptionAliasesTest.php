<?php

namespace Wikibase\Schema\Tests\MediaWiki\Specials;

use Wikibase\Schema\Tests\Mocks\HTMLFormSpy;
use SpecialPageTestBase;
use FauxRequest;
use WikiPage;
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

	private $mockHTMLFormProvider;

	protected function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'recentchanges';
	}

	protected function tearDown() {
		$this->mockHTMLFormProvider = null;
		parent::tearDown();
	}

	protected function newSpecialPage() {
		if ( $this->mockHTMLFormProvider !== null ) {
			return new SetSchemaLabelDescriptionAliases( $this->mockHTMLFormProvider );
		}
		return new SetSchemaLabelDescriptionAliases();
	}

	public function testValidateId() {
		$this->createTestSchema();

		$setSchemaInfo = $this->newSpecialPage();
		$this->assertTrue( $setSchemaInfo->validateID( 'O123' ) );
	}

	public function testValidateIdEmpty() {
		$this->createTestSchema();

		$setSchemaInfo = $this->newSpecialPage();
		$this->assertNotTrue( $setSchemaInfo->validateID( '' ) );
	}

	public function testValidateIdWrongId() {
		$this->createTestSchema();

		$setSchemaInfo = $this->newSpecialPage();
		$this->assertNotTrue( $setSchemaInfo->validateID( 'bla' ) );
	}

	public function testValidateLangCode() {
		$this->createTestSchema();

		$setSchemaInfo = $this->newSpecialPage();
		$this->assertTrue( $setSchemaInfo->validateLangCode( 'de' ) );
	}

	public function testValidateLangCodeEmpty() {
		$this->createTestSchema();

		$setSchemaInfo = $this->newSpecialPage();
		$this->assertNotTrue( $setSchemaInfo->validateLangCode( '' ) );
	}

	public function testValidateLangCodeWrongCode() {
		$this->createTestSchema();

		$setSchemaInfo = $this->newSpecialPage();
		$this->assertNotTrue( $setSchemaInfo->validateLangCode( 'i do not exist' ) );
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

	public function testSubmitEditFormCallbackNonEnglish() {
		$initialContent = $this->createTestSchema();

		$langFormKey = SetSchemaLabelDescriptionAliases::FIELD_LANGUAGE;
		$dataGood = [
			'ID' => 'O123',
			$langFormKey => 'de',
			SetSchemaLabelDescriptionAliases::FIELD_LABEL => 'Schema Bezeichnung',
			SetSchemaLabelDescriptionAliases::FIELD_DESCRIPTION => 'Eine Beschreibung auf deutsch.',
			SetSchemaLabelDescriptionAliases::FIELD_ALIASES => 'foo | bar | baz',
			'schema-shexc' => 'def',
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoGood = $setSchemaInfo->submitEditFormCallback( $dataGood );
		$this->assertTrue( $infoGood->ok );

		$schemaContent = $this->getCurrentSchemaContent( $dataGood['ID'] );
		$expectedLabels = array_merge(
			$initialContent['labels'],
			[ $dataGood[$langFormKey] => $dataGood[SetSchemaLabelDescriptionAliases::FIELD_LABEL] ]
		);
		$this->assertSame( $expectedLabels, $schemaContent['labels'] );

		$expectedDescriptions = array_merge(
			$initialContent['descriptions'],
			[ $dataGood[$langFormKey] => $dataGood[SetSchemaLabelDescriptionAliases::FIELD_DESCRIPTION] ]
		);
		$this->assertSame( $expectedDescriptions, $schemaContent['descriptions'] );

		$expectedDescriptions = array_merge(
			$initialContent['aliases'],
			[ $dataGood[$langFormKey] => [ 'foo', 'bar', 'baz' ] ]
		);
		$this->assertSame( $expectedDescriptions, $schemaContent['aliases'] );

		$this->assertSame( $initialContent['schemaText'], $schemaContent['schemaText'] );
	}

	public function testSubmitEditFormCallbackDuplicateAliases() {
		$this->createTestSchema();

		$dataGood = [
			'ID' => 'O123',
			'languagecode' => 'en',
			'label' => 'Schema label',
			'description' => '',
			'aliases' => 'foo | bar | foo | baz | bar | foo',
			'schema-shexc' => 'abc'
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoGood = $setSchemaInfo->submitEditFormCallback( $dataGood );

		$this->assertTrue( $infoGood->ok );
		$schemaContent = $this->getCurrentSchemaContent( $dataGood['ID'] );
		$this->assertSame( [ 'foo', 'bar', 'baz' ], $schemaContent['aliases']['en'] );
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
		$actualResult = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->isSelectionDataValid( 'O123', 'en' );

		$this->assertTrue( $actualResult );
	}

	public function testValidateSchemaSelectionFormDataNoLanguageCode() {
		$actualResult = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->isSelectionDataValid( 'O123', null );

		$this->assertFalse( $actualResult );
	}

	public function testValidateSchemaSelectionFormDataInvalidId() {
		$actualResult = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->isSelectionDataValid( 'Q1111', 'en' );

		$this->assertFalse( $actualResult );
	}

	public function testValidateSchemaSelectionFormDataNonexistentSchema() {
		$actualResult = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->isSelectionDataValid( 'O1111111111', 'en' );

		$this->assertFalse( $actualResult );
	}

	public function provideExecuteData() {
		yield 'plain request' => [
			null,
			[],
			false,
			[
				'ID' => '',
				'languagecode' => 'en',
			]
		];

		yield 'id in request' => [
			null,
			[
				'ID' => 'O1'
			],
			false,
			[
				'ID' => 'O1',
				'languagecode' => 'en',
			]
		];

		yield 'subpage with id only' => [
			'O1',
			[],
			false,
			[
				'ID' => 'O1',
				'languagecode' => 'en',
			]
		];

		yield 'subpage with id and langcode' => [
			'O1/de',
			[],
			false,
			[
				'ID' => 'O1',
				'languagecode' => 'de',
			]
		];
	}

	/**
	 * @dataProvider provideExecuteData
	 */
	public function testExecute( $subPage, $additionalRequestParams, $wasPosted, $expectedFieldData ) {
		$this->mockHTMLFormProvider = HTMLFormSpy::class;
		$this->executeSpecialPage(
			$subPage,
			new FauxRequest(
				array_merge(
				[
					'title' => 'Special:SetSchemaLabelDescriptionAliases',
				], $additionalRequestParams ),
				$wasPosted
			)
		);

		$mockHTMLFormProvider = $this->mockHTMLFormProvider; // FIXME: PHP7: inline this variable!
		$mockHTMLFormProvider::assertFormFieldData( $expectedFieldData );
	}

	/**
	 * @return array $actualSchema an array of Schema text + namebadge
	 */
	private function createTestSchema() {
		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );
		$this->saveSchemaPageContent(
			$page,
			[
				'labels' => [ 'en' => 'Schema label' ],
				'descriptions' => [ 'en' => 'Schema description' ],
				'aliases' => [],
				'schemaText' => 'abc',
				"serializationVersion" => "3.0",
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
