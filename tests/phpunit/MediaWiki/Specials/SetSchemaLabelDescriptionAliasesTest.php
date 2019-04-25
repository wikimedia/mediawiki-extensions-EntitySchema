<?php

namespace Wikibase\Schema\Tests\MediaWiki\Specials;

use CommentStoreComment;
use FauxRequest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use SpecialPageTestBase;
use Title;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\MediaWiki\Specials\SetSchemaLabelDescriptionAliases;
use Wikibase\Schema\Tests\Mocks\HTMLFormSpy;
use Wikimedia\TestingAccessWrapper;
use WikiPage;

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

	public function testSubmitEditFormCallbackCorrectId() {
		$this->createTestSchema();

		$dataGood = [
			'ID' => 'E123',
			'languagecode' => 'en',
			'label' => 'Schema label',
			'description' => '',
			'aliases' => 'foo | bar | baz',
			'schema-shexc' => 'abc',
			'base-rev' => $this->getCurrentSchemaRevisionId( 'E123' ),
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoGood = $setSchemaInfo->submitEditFormCallback( $dataGood );

		$this->assertTrue( $infoGood->ok );
	}

	public function testSubmitEditFormCallbackNonEnglish() {
		$initialContent = $this->createTestSchema();

		$langFormKey = SetSchemaLabelDescriptionAliases::FIELD_LANGUAGE;
		$dataGood = [
			'ID' => 'E123',
			$langFormKey => 'de',
			SetSchemaLabelDescriptionAliases::FIELD_LABEL => 'Schema Bezeichnung',
			SetSchemaLabelDescriptionAliases::FIELD_DESCRIPTION => 'Eine Beschreibung auf deutsch.',
			SetSchemaLabelDescriptionAliases::FIELD_ALIASES => 'foo | bar | baz',
			'schema-shexc' => 'def',
			'base-rev' => $this->getCurrentSchemaRevisionId( 'E123' ),
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoGood = $setSchemaInfo->submitEditFormCallback( $dataGood );
		$this->assertTrue( $infoGood->ok );

		$schemaContent = $this->getCurrentSchemaContent( $dataGood['ID'] );
		$expectedLabels = array_merge(
			$initialContent['labels'],
			[ $dataGood[$langFormKey] => $dataGood[SetSchemaLabelDescriptionAliases::FIELD_LABEL] ]
		);
		ksort( $expectedLabels );
		$this->assertSame( $expectedLabels, $schemaContent['labels'] );

		$expectedDescriptions = array_merge(
			$initialContent['descriptions'],
			[ $dataGood[$langFormKey] => $dataGood[SetSchemaLabelDescriptionAliases::FIELD_DESCRIPTION] ]
		);
		ksort( $expectedDescriptions );
		$this->assertSame( $expectedDescriptions, $schemaContent['descriptions'] );

		$expectedAliases = array_merge(
			$initialContent['aliases'],
			[ $dataGood[$langFormKey] => [ 'foo', 'bar', 'baz' ] ]
		);
		ksort( $expectedAliases );
		$this->assertSame( $expectedAliases, $schemaContent['aliases'] );

		$this->assertSame( $initialContent['schemaText'], $schemaContent['schemaText'] );
	}

	public function testSubmitEditFormCallbackDuplicateAliases() {
		$this->createTestSchema();

		$dataGood = [
			'ID' => 'E123',
			'languagecode' => 'en',
			'label' => 'Schema label',
			'description' => '',
			'aliases' => 'foo | bar | foo | baz | bar | foo',
			'schema-shexc' => 'abc',
			'base-rev' => $this->getCurrentSchemaRevisionId( 'E123' ),
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoGood = $setSchemaInfo->submitEditFormCallback( $dataGood );

		$this->assertTrue( $infoGood->ok );
		$schemaContent = $this->getCurrentSchemaContent( $dataGood['ID'] );
		$this->assertSame( [ 'foo', 'bar', 'baz' ], $schemaContent['aliases']['en'] );
	}

	public function testSubmitEditFormCallbackWrongId() {
		$dataWrong = [
			'ID' => 'E129999999990',
			'languagecode' => 'en',
			'label' => 'Schema label',
			'description' => '',
			'aliases' => 'foo | bar | baz',
			'schema-shexc' => 'abc',
			'base-rev' => $this->getCurrentSchemaRevisionId( 'E123' ),
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
			'schema-shexc' => 'abc',
			'base-rev' => $this->getCurrentSchemaRevisionId( 'E123' ),
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoIncomplete = $setSchemaInfo->submitEditFormCallback( $dataIncomplete );

		$this->assertFalse( $infoIncomplete->ok );
	}

	public function testValidateSchemaSelectionFormData() {
		$this->createTestSchema();
		$actualResult = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->isSelectionDataValid( 'E123', 'en' );

		$this->assertTrue( $actualResult );
	}

	public function testValidateSchemaSelectionFormDataNoLanguageCode() {
		$actualResult = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->isSelectionDataValid( 'E123', null );

		$this->assertFalse( $actualResult );
	}

	public function testValidateSchemaSelectionFormDataInvalidId() {
		$actualResult = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->isSelectionDataValid( 'Q1111', 'en' );

		$this->assertFalse( $actualResult );
	}

	public function testValidateSchemaSelectionFormDataNonexistentSchema() {
		$actualResult = TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->isSelectionDataValid( 'E1111111111', 'en' );

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
			],
		];

		yield 'id in request' => [
			null,
			[
				'ID' => 'E1',
			],
			false,
			[
				'ID' => 'E1',
				'languagecode' => 'en',
			],
		];

		yield 'subpage with id only' => [
			'E1',
			[],
			false,
			[
				'ID' => 'E1',
				'languagecode' => 'en',
			],
		];

		yield 'subpage with id and langcode' => [
			'E1/de',
			[],
			false,
			[
				'ID' => 'E1',
				'languagecode' => 'de',
			],
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
		$page = WikiPage::factory( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );
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
		return $this->getCurrentSchemaContent( 'E123' );
	}

	private function getCurrentSchemaContent( $pageName ) {
		$revId = $this->getCurrentSchemaRevisionId( $pageName );
		$rev = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $revId );

		return json_decode( $rev->getContent( SlotRecord::MAIN )->getText(), true );
	}

	private function getCurrentSchemaRevisionId( $pageName ) {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $pageName );
		return $title->getLatestRevID();
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
