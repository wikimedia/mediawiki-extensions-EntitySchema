<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Article;
use EntitySchema\MediaWiki\Actions\EntitySchemaEditAction;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Presentation\InputValidator;
use ExtensionRegistry;
use MediaWiki\Block\BlockManager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWikiIntegrationTestCase;
use MessageCache;
use PermissionsError;
use ReadOnlyError;
use RequestContext;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * @covers \EntitySchema\MediaWiki\Actions\EntitySchemaEditAction
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaEditActionTest extends MediaWikiIntegrationTestCase {

	private bool $tempUserEnabled = false;

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public function testReadOnly() {
		$this->setService( 'PermissionManager', $this->createMock( PermissionManager::class ) );
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$context = RequestContext::getMain();
		$action = new EntitySchemaEditAction(
			Article::newFromTitle(
				Title::newFromDBkey( 'E1' ),
				$context
			),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$this->getServiceContainer()->getUserOptionsLookup(),
			'https://example.com/license',
			'license text',
			$this->getMockTempUserConfig()
		);

		$this->expectException( ReadOnlyError::class );
		$action->show();
	}

	public function testNoRights() {
		$restrictionStore = $this->createMock( RestrictionStore::class );
		$restrictionStore->method( 'getCascadeProtectionSources' )->willReturn( [ [], [] ] );
		$this->setService( 'RestrictionStore', $restrictionStore );
		$this->setService( 'BlockManager', $this->createMock( BlockManager::class ) );
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions',
			[ '*' => [ 'edit' => false ] ] );
		$context = RequestContext::getMain();
		$title = Title::makeTitle( NS_MAIN, 'E1' );
		$title->resetArticleID( 0 );
		$action = new EntitySchemaEditAction(
			Article::newFromTitle(
				$title,
				$context
			),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$this->getServiceContainer()->getUserOptionsLookup(),
			'https://example.com/license',
			'license text',
			$this->getMockTempUserConfig()
		);

		$this->expectException( PermissionsError::class );
		$action->show();
	}

	private function getMockTempUserConfig(): TempUserConfig {
		$tempUserConfig = $this->createMock( TempUserConfig::class );
		$tempUserConfig->expects( $this->atMost( 1 ) )
			->method( 'isEnabled' )
			->willReturn( $this->tempUserEnabled );
		return $tempUserConfig;
	}

	private function setupRenderingMocks(): void {
		$content = $this->createMock( EntitySchemaContent::class );
		$content->expects( $this->once() )
			->method( 'getText' )
			->willReturn( '{}' );
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->expects( $this->once() )
			->method( 'getContent' )
			->willReturn( $content );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->expects( $this->once() )
			->method( 'getKnownCurrentRevision' )
			->willReturn( $revisionRecord );
		$messageCache = $this->createMock( MessageCache::class );
		$messageCache->expects( $this->any() )
			->method( 'get' )
			->willReturnCallback( static fn ( $key, $useDB = true, $langcode = true ) => $key );
		$messageCache->expects( $this->any() )
			->method( 'transform' )
			->willReturnCallback(
				static fn ( $message, $interface = false, $language = null, $page = null ) => $message
			);
		$messageCache->expects( $this->any() )
			->method( 'parse' )
			->willReturnCallback(
				static fn ( $text, $page = null, $linestart = true, $interface = false, $language = null ) => $text
			);
		$this->setService( 'MessageCache', $messageCache );
		$this->setService( 'RevisionStore', $revisionStore );
	}

	private function setMocksForEditAction() {
		$this->setupRenderingMocks();
		$this->setService( 'PermissionManager', $this->createMock( PermissionManager::class ) );
		$restrictionStore = $this->createMock( RestrictionStore::class );
		$restrictionStore->method( 'getCascadeProtectionSources' )->willReturn( [ [], [] ] );
		$this->setService( 'RestrictionStore', $restrictionStore );
		$this->setService( 'BlockManager', $this->createMock( BlockManager::class ) );
	}

	private function renderEditAction(): string {
		$this->setMocksForEditAction();
		$context = RequestContext::getMain();
		$title = Title::newFromDBkey( 'E1' );
		$context->setTitle( $title );
		$action = new EntitySchemaEditAction(
			Article::newFromTitle( $title, $context	),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$this->getServiceContainer()->getUserOptionsLookup(),
			'https://example.com/license',
			'license text',
			$this->getMockTempUserConfig()
		);
		$action->show();
		return $action->getOutput()->getHTML();
	}

	public function testShowWarningForAnonymousUsers() {
		$this->tempUserEnabled = false;
		$html = $this->renderEditAction();
		$this->assertStringContainsString(
			'entityschema-anonymouseditwarning',
			$html,
			'anonymous edit warning is unexpectedly absent'
		);
	}

	public function testShowNoIpLoggingWarningForAnonymousUsersWithTempUser() {
		$this->tempUserEnabled = true;
		$html = $this->renderEditAction();
		$this->assertStringNotContainsString(
			'entityschema-anonymouseditwarning',
			$html,
			'anonymous edit warning is unexpectedly present'
		);
	}
}
