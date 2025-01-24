<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Specials;

use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\MediaWiki\Specials\NewEntitySchema;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use PermissionsError;
use ReadOnlyError;
use SpecialPageTestBase;
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

	use EntitySchemaIntegrationTestCaseTrait;
	use TempUserTestTrait;

	private DatabaseBlock $block;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
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
		$this->setGroupPermissions( [ '*' => [ 'createpage' => false ] ] );
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
			Status $status,
			$summary,
			User $user,
			$minorEdit
		)  {
			$status->fatal( 'Something went wrong' );
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
		$this->disableAutoCreateTempUser();
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
		$this->enableAutoCreateTempUser();
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

	public function testCreateTempUser(): void {
		$this->enableAutoCreateTempUser();
		$this->addTempUserHook();

		[ , $webResponse ] = $this->executeSpecialPage(
			null,
			new FauxRequest(
				[
					NewEntitySchema::FIELD_LABEL => 'label',
					NewEntitySchema::FIELD_LANGUAGE => 'en',
				],
				true
			)
		);

		$services = $this->getServiceContainer();
		$lastTitle = $this->getLastCreatedTitle();
		$revision = $services->getRevisionLookup()
			->getRevisionByTitle( $lastTitle );
		$user = $revision->getUser();
		$this->assertTrue( $services->getUserIdentityUtils()->isTemp( $user ) );
		$redirectUrl = $webResponse->getHeader( 'location' );
		$this->assertRedirectToEntitySchema( $lastTitle, $redirectUrl );
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
		$services = $this->getServiceContainer();
		return new NewEntitySchema(
			$services->getTempUserConfig(),
			$this->createMock( SettingsArray::class ),
			EntitySchemaServices::getIdGenerator( $services ),
			EntitySchemaServices::getMediaWikiPageUpdaterFactory( $services )
		);
	}

}
