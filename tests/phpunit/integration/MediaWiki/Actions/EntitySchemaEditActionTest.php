<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Article;
use EntitySchema\MediaWiki\Actions\EntitySchemaEditAction;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Presentation\InputValidator;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use MediaWiki\Block\BlockManager;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MessageCache;
use PermissionsError;
use ReadOnlyError;
use Wikibase\Repo\Specials\SpecialPageCopyrightView;
use Wikimedia\Rdbms\ReadOnlyMode;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\MediaWiki\Actions\EntitySchemaEditAction
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaEditActionTest extends MediaWikiIntegrationTestCase {

	use EntitySchemaIntegrationTestCaseTrait;
	use TempUserTestTrait;

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
		$services = $this->getServiceContainer();
		$action = new EntitySchemaEditAction(
			Article::newFromTitle(
				Title::newFromDBkey( 'E1' ),
				$context
			),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$services->getUserOptionsLookup(),
			'https://example.com/license',
			'license text',
			$services->getTempUserConfig()
		);

		$this->expectException( ReadOnlyError::class );
		$action->show();
	}

	public function testNoRights() {
		$restrictionStore = $this->createMock( RestrictionStore::class );
		$restrictionStore->method( 'getCascadeProtectionSources' )->willReturn( [ [], [] ] );
		$this->setService( 'RestrictionStore', $restrictionStore );
		$this->setService( 'BlockManager', $this->createMock( BlockManager::class ) );
		$this->setGroupPermissions( [ '*' => [ 'edit' => false ] ] );
		$context = RequestContext::getMain();
		$title = Title::makeTitle( NS_MAIN, 'E1' );
		$title->resetArticleID( 0 );
		$services = $this->getServiceContainer();
		$action = new EntitySchemaEditAction(
			Article::newFromTitle(
				$title,
				$context
			),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$services->getUserOptionsLookup(),
			'https://example.com/license',
			'license text',
			$services->getTempUserConfig()
		);

		$this->expectException( PermissionsError::class );
		$action->show();
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
		$services = $this->getServiceContainer();
		$action = new EntitySchemaEditAction(
			Article::newFromTitle( $title, $context	),
			$context,
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			false,
			$services->getUserOptionsLookup(),
			'https://example.com/license',
			'license text',
			$services->getTempUserConfig()
		);
		$action->show();
		return $action->getOutput()->getHTML();
	}

	public function testShowWarningForAnonymousUsers() {
		$this->disableAutoCreateTempUser();
		$html = $this->renderEditAction();
		$this->assertStringContainsString(
			'entityschema-anonymouseditwarning',
			$html,
			'anonymous edit warning is unexpectedly absent'
		);
	}

	public function testShowNoIpLoggingWarningForAnonymousUsersWithTempUser() {
		$this->enableAutoCreateTempUser();
		$html = $this->renderEditAction();
		$this->assertStringNotContainsString(
			'entityschema-anonymouseditwarning',
			$html,
			'anonymous edit warning is unexpectedly present'
		);
	}

	public function testEditAndCreateTempUser(): void {
		$this->enableAutoCreateTempUser();
		$this->addTempUserHook();
		$services = $this->getServiceContainer();
		$id = 'E123';
		$title = $services->getTitleFactory()->makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );
		$baseRevision = $this->saveSchemaPageContent( $wikiPage, [
			'schemaText' => 'old schema text',
			'id' => $id,
		] );
		$request = new FauxRequest( [
			'wp' . EntitySchemaEditAction::FIELD_SCHEMA_TEXT => 'new schema text',
			'wp' . EntitySchemaEditAction::FIELD_EDIT_SUMMARY => 'edit summary',
			'wp' . EntitySchemaEditAction::FIELD_BASE_REV => $baseRevision->getId(),
		], true );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( $title );
		$context->setRequest( $request );
		$context->setUser( $services->getUserFactory()->newAnonymous() );
		$inputValidator = $this->createConfiguredMock( InputValidator::class, [
			'validateSchemaTextLength' => true,
		] );
		$action = new EntitySchemaEditAction(
			Article::newFromWikiPage( $wikiPage, $context ),
			$context,
			$inputValidator,
			false,
			$services->getUserOptionsLookup(),
			'https://example.com/license',
			'license text',
			$services->getTempUserConfig()
		);
		// mock CopyrightView to avoid recursive Parser::parse errors from the real implementation
		$copyrightView = $this->createConfiguredMock( SpecialPageCopyrightView::class, [
			'getHtml' => 'SpecialPageCopyrightView::getHtml',
		] );
		TestingAccessWrapper::newFromObject( $action )->copyrightView = $copyrightView;

		$action->show();

		$revision = $services->getRevisionLookup()->getRevisionByTitle( $title );
		/** @var EntitySchemaContent $content */
		$content = $revision->getContent( SlotRecord::MAIN );
		$schemaText = ( new EntitySchemaConverter() )->getSchemaText( $content->getText() );
		$this->assertSame( 'new schema text', $schemaText );
		$this->assertStringEndsWith( '*/edit summary', $revision->getComment()->text );
		$user = $revision->getUser();
		$this->assertTrue( $services->getUserIdentityUtils()->isTemp( $user ) );
		$redirect = $action->getOutput()->getRedirect();
		$this->assertRedirectToEntitySchema( $title, $redirect );
	}

}
