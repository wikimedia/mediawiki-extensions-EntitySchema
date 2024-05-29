<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Specials;

use EntitySchema\MediaWiki\Specials\SetEntitySchemaLabelDescriptionAliases;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use EntitySchema\Tests\Mocks\HTMLFormSpy;
use ExtensionRegistry;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\TempUser\TempUserConfig;
use SpecialPageTestBase;
use Wikibase\Lib\SettingsArray;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\MediaWiki\Specials\SetEntitySchemaLabelDescriptionAliases
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class SetEntitySchemaLabelDescriptionAliasesTest extends SpecialPageTestBase {
	use EntitySchemaIntegrationTestCaseTrait;

	private ?string $mockHTMLFormProvider = null;
	private bool $tempUserEnabled = false;

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	protected function tearDown(): void {
		$this->mockHTMLFormProvider = null;
		parent::tearDown();
	}

	protected function newSpecialPage(): SetEntitySchemaLabelDescriptionAliases {
		$fakeSettings = new SettingsArray( [
			'dataRightsUrl' => 'https://example.com',
			'dataRightsText' => 'CC0',
		] );
		if ( $this->mockHTMLFormProvider !== null ) {
			return new SetEntitySchemaLabelDescriptionAliases(
				$this->getMockTempUserConfig(),
				$fakeSettings,
				$this->mockHTMLFormProvider
			);
		}
		return new SetEntitySchemaLabelDescriptionAliases(
			$this->getMockTempUserConfig(),
			$fakeSettings
		);
	}

	protected function getMockTempUserConfig(): TempUserConfig {
		$tempUserConfig = $this->createMock( TempUserConfig::class );
		$tempUserConfig->expects( $this->atMost( 1 ) )
			->method( 'isEnabled' )
			->willReturn( $this->tempUserEnabled );
		return $tempUserConfig;
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

		$this->assertTrue( $infoGood->isOK() );
	}

	public function testSubmitEditFormCallbackNonEnglish() {
		$initialContent = $this->createTestSchema();

		$langFormKey = SetEntitySchemaLabelDescriptionAliases::FIELD_LANGUAGE;
		$dataGood = [
			'ID' => 'E123',
			$langFormKey => 'de',
			SetEntitySchemaLabelDescriptionAliases::FIELD_LABEL => 'Schema Bezeichnung',
			SetEntitySchemaLabelDescriptionAliases::FIELD_DESCRIPTION => 'Eine Beschreibung auf deutsch.',
			SetEntitySchemaLabelDescriptionAliases::FIELD_ALIASES => 'foo | bar | baz',
			'schema-shexc' => 'def',
			'base-rev' => $this->getCurrentSchemaRevisionId( 'E123' ),
		];

		$setSchemaInfo = $this->newSpecialPage();
		$infoGood = $setSchemaInfo->submitEditFormCallback( $dataGood );
		$this->assertTrue( $infoGood->isOK() );

		$schemaContent = $this->getCurrentSchemaContent( $dataGood['ID'] );
		$expectedLabels = array_merge(
			$initialContent['labels'],
			[ $dataGood[$langFormKey] => $dataGood[SetEntitySchemaLabelDescriptionAliases::FIELD_LABEL] ]
		);
		ksort( $expectedLabels );
		$this->assertSame( $expectedLabels, $schemaContent['labels'] );

		$expectedDescriptions = array_merge(
			$initialContent['descriptions'],
			[
				$dataGood[$langFormKey] => $dataGood[SetEntitySchemaLabelDescriptionAliases::FIELD_DESCRIPTION],
			]
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

		$this->assertTrue( $infoGood->isOK() );
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

		$this->assertFalse( $infoFalse->isOK() );
		$this->assertSame( 'error',
			$infoFalse->getErrors()[0]['type'],
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

		$this->assertFalse( $infoIncomplete->isOK() );
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

	public static function provideExecuteData(): iterable {
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
	public function testExecute(
		?string $subPage,
		array $additionalRequestParams,
		bool $wasPosted,
		array $expectedFieldData
	) {
		$this->mockHTMLFormProvider = HTMLFormSpy::class;
		$this->executeSpecialPage(
			$subPage,
			new FauxRequest(
				array_merge(
				[
					'title' => 'Special:SetEntitySchemaLabelDescriptionAliases',
				], $additionalRequestParams ),
				$wasPosted
			),
			'en' // default is qqx but qqx terms do not exist
		);

		$this->mockHTMLFormProvider::assertFormFieldData( $expectedFieldData );
	}

	private function renderWarnings(): string {
		$string = '';
		$output = $this->createMock( OutputPage::class );
		$output->expects( $this->atMost( 1 ) )
			->method( 'addHTML' )
			->willReturnCallback( static function ( $text ) use ( &$string ) {
				$string .= $text;
			} );
		$this->setUserLang( 'qqx' );
		TestingAccessWrapper::newFromObject( $this->newSpecialPage() )
			->displayWarnings( $output );
		return $string;
	}

	public function testDisplayWarningForEditsByAnonymousUsers() {
		$this->tempUserEnabled = false;
		$string = $this->renderWarnings();
		$this->assertStringContainsString( 'entityschema-anonymouseditwarning', $string );
	}

	public function testDoNotDisplayWarningForEditsByAnonymousUsersWithTempUserEnabled() {
		$this->tempUserEnabled = true;
		$string = $this->renderWarnings();
		$this->assertStringNotContainsString( 'entityschema-anonymouseditwarning', $string );
	}

	/**
	 * @return array $actualSchema an array of Schema text + namebadge
	 */
	private function createTestSchema(): array {
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
		return $this->getCurrentSchemaContent( 'E123' );
	}

}
