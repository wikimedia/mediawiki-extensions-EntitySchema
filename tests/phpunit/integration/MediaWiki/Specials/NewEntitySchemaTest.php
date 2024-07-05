<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Specials;

use Content;
use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\MediaWiki\Specials\NewEntitySchema;
use ExtensionRegistry;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use PermissionsError;
use ReadOnlyError;
use SpecialPageTestBase;
use TextContent;
use Wikibase\Lib\SettingsArray;
use Wikimedia\Rdbms\ReadOnlyMode;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \EntitySchema\MediaWiki\Specials\NewEntitySchema
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class NewEntitySchemaTest extends SpecialPageTestBase {

	private DatabaseBlock $block;
	private bool $tempUserEnabled = false;

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public function testReadOnly() {
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$this->expectException( ReadOnlyError::class );
		$this->executeSpecialPage( null );
	}

	public function testNoRights() {
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions',
			[ '*' => [ 'createpage' => false ] ] );
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage( null );
	}

	/**
	 * @group slow
	 */
	public function testNewSchemaIsCreatedWithMinData() {
		$testLabel = uniqid( 'testLabel_' . __FUNCTION__ . '_' );
		$this->executeSpecialPage(
			null,
			new FauxRequest(
				[
					NewEntitySchema::FIELD_LABEL => $testLabel,
					NewEntitySchema::FIELD_LANGUAGE => 'en',
				],
				true
			)
		);
		$textOfNewestPage = $this->getLastCreatedPageText();
		$this->assertStringContainsString( $testLabel, $textOfNewestPage );
	}

	public function testNewSchemaIsNotCreatedWhenBlocked() {
		$testuser = self::getTestUser()->getUser();
		$this->block = new DatabaseBlock(
			[
				'address' => $testuser,
				'reason' => 'testing in ' . __CLASS__,
				'by' => $testuser,
			]
		);
		$this->block->insert();

		$testLabel = uniqid( 'testLabel_' . __FUNCTION__ . '_' );

		try {
			$this->executeSpecialPage(
				null,
				new FauxRequest(
					[
						NewEntitySchema::FIELD_LABEL => $testLabel,
					],
					true
				),
				'en',
				$testuser
			);

			$this->fail( 'a blocked user must cause an exception!' );
		} catch ( PermissionsError $e ) {
			// we expect that
		}

		$textOfNewestPage = $this->getLastCreatedPageText();
		$this->assertStringNotContainsString(
			$testLabel,
			$textOfNewestPage ?? '',
			'Blocked User was able to create new Schema!'
		);
	}

	public function testHookRunnerFailurePropogratesStatusMessageToForm() {
		$testuser = self::getTestUser()->getUser();
		$this->setTemporaryHook( 'EditFilterMergedContent', static function (
			IContextSource $context,
			Content $content,
			Status &$status,
			$summary,
			User $user,
			$minorEdit
		)  {
			$status = Status::newFatal( 'Something went wrong' );
			return false;
		} );

		$testLabel = uniqid( 'testLabel_' . __FUNCTION__ . '_' );

		[ $resultText, $resultRequest ] = $this->executeSpecialPage(
			null,
			new FauxRequest(
				[
					NewEntitySchema::FIELD_LABEL => $testLabel,
				],
				true
			),
			'qqq',
			$testuser
		);

		$this->assertStringContainsString(
			'⧼Something went wrong⧽',
			$resultText
		);
	}

	public function testNewSchemaIsNotCreatedWithInvalidData() {
		$this->setUserLang( 'qqx' );
		[ $html ] = $this->executeSpecialPage(
			null,
			new FauxRequest(
				[

				], true
			)
		);

		$this->assertStringContainsString(
			'(entityschema-error-',
			$html,
			'error status message is missing'
		);
		$this->assertStringContainsString(
			'(htmlform-required)',
			$html,
			'message about required value is missing'
		);
	}

	public function testShowWarningForAnonymousUsers() {
		$this->tempUserEnabled = false;
		$this->setUserLang( 'qqx' );
		[ $html ] = $this->executeSpecialPage(
			null,
			new FauxRequest(
				[

				], true
			)
		);

		$this->assertStringContainsString(
			'(entityschema-anonymouseditwarning)',
			$html,
			'anonymous edit warning is missing'
		);
	}

	public function testDoNotShowWarningForAnonymousUsersWhenTempUserEnabled() {
		$this->tempUserEnabled = true;
		$this->setUserLang( 'qqx' );
		[ $html ] = $this->executeSpecialPage(
			null,
			new FauxRequest(
				[

				], true
			)
		);

		$this->assertStringNotContainsString(
			'(entityschema-anonymouseditwarning)',
			$html,
			'anonymous edit warning is present'
		);
	}

	protected function tearDown(): void {
		if ( isset( $this->block ) ) {
			$this->block->delete();
		}

		parent::tearDown();
	}

	/**
	 * Gets the last created page (if any).
	 */
	private function getLastCreatedTitle(): ?LinkTarget {
		$row = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->orderBy( [ 'page_id' ], SelectQueryBuilder::SORT_DESC )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $row === false ) {
			return null;
		}
		return new TitleValue( (int)$row->page_namespace, $row->page_title );
	}

	/**
	 * Gets the text of the last created page (if any).
	 */
	protected function getLastCreatedPageText(): ?string {
		$lastTitle = $this->getLastCreatedTitle();
		if ( $lastTitle === null ) {
			return null;
		}
		$content = $this->getServiceContainer()
			->getRevisionLookup()
			->getRevisionByTitle( $lastTitle )
			->getContent( SlotRecord::MAIN );
		return ( $content instanceof TextContent ) ? $content->getText() : null;
	}

	protected function newSpecialPage(): NewEntitySchema {
		$idGenerator = EntitySchemaServices::getIdGenerator( $this->getServiceContainer() );
		$repoSettings = $this->createMock( SettingsArray::class );
		$tempUserConfig = $this->createMock( TempUserConfig::class );
		$tempUserConfig->expects( $this->atMost( 1 ) )
			->method( 'isEnabled' )
			->willReturn( $this->tempUserEnabled );
		return new NewEntitySchema( $tempUserConfig, $repoSettings, $idGenerator );
	}

}
