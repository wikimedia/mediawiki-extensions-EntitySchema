<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\MediaWiki\EntitySchemaServices;
use MediaWiki\Context\RequestContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\TempUser\CreateStatus;
use MediaWiki\User\TempUser\TempUserCreator;
use MediaWikiIntegrationTestCase;
use RecentChange;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiPageUpdaterFactory
 * @group Database
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactoryTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public function testGetPageUpdater() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );

		$pageUpdaterFactory = EntitySchemaServices::getMediaWikiPageUpdaterFactory();
		$pageUpdaterStatus = $pageUpdaterFactory->getPageUpdater( 'TestTitle', $context );
		$pageUpdater = TestingAccessWrapper::newFromObject( $pageUpdaterStatus->getPageUpdater() );
		$this->assertEquals( $user, $pageUpdater->author );
		$title = $pageUpdater->wikiPage->getTitle();
		$this->assertEquals( 'TestTitle', $title->getText() );
	}

	public function testAutopatrolledFlagIsSetForSysop() {
		$user = $this->getTestUser( 'sysop' )->getUser();
		$context = new RequestContext();
		$context->setUser( $user );

		$pageUpdaterFactory = EntitySchemaServices::getMediaWikiPageUpdaterFactory();
		$pageUpdaterStatus = $pageUpdaterFactory->getPageUpdater( 'TestTitle', $context );
		$pageUpdater = TestingAccessWrapper::newFromObject( $pageUpdaterStatus->getPageUpdater() );
		$this->assertEquals( RecentChange::PRC_AUTOPATROLLED, $pageUpdater->rcPatrolStatus );
	}

	public function testAutopatrolledFlagNotSetForNormalUser() {
		$user = $this->getTestUser()->getUser();
		$context = new RequestContext();
		$context->setUser( $user );

		$pageUpdaterFactory = EntitySchemaServices::getMediaWikiPageUpdaterFactory();
		$pageUpdaterStatus = $pageUpdaterFactory->getPageUpdater( 'TestTitle', $context );
		$pageUpdater = TestingAccessWrapper::newFromObject( $pageUpdaterStatus->getPageUpdater() );
		$this->assertEquals( RecentChange::PRC_UNPATROLLED, $pageUpdater->rcPatrolStatus );
	}

	public function testContextTitleSet(): void {
		$this->disableAutoCreateTempUser();
		$services = $this->getServiceContainer();
		$context = new RequestContext();
		$anonUser = $services->getUserFactory()->newAnonymous();
		$context->setUser( $anonUser );
		$title = $services->getTitleFactory()->newMainPage(); // arbitrary
		$context->setTitle( $title );
		$pageUpdaterFactory = EntitySchemaServices::getMediaWikiPageUpdaterFactory();

		$pageUpdaterStatus = $pageUpdaterFactory->getPageUpdater( 'TestTitle', $context );
		$this->assertStatusGood( $pageUpdaterStatus );
		$newContext = $pageUpdaterStatus->getContext();
		$newTitle = $newContext->getTitle();

		$this->assertNotSame( $context, $newContext, 'new context created' );
		$this->assertNotSame( $title, $newTitle, 'new title set' );
		$this->assertSame( NS_ENTITYSCHEMA_JSON, $newTitle->getNamespace(), 'new title namespace' );
		$this->assertSame( 'TestTitle', $newTitle->getDBkey(), 'new title db-key' );
	}

	public function testTempAccountCreated(): void {
		$this->enableAutoCreateTempUser();
		$services = $this->getServiceContainer();
		$context = new RequestContext();
		$anonUser = $services->getUserFactory()->newAnonymous();
		$context->setUser( $anonUser );
		$pageUpdaterFactory = EntitySchemaServices::getMediaWikiPageUpdaterFactory();

		$pageUpdaterStatus = $pageUpdaterFactory->getPageUpdater( 'TestTitle', $context );
		$this->assertStatusGood( $pageUpdaterStatus );
		$savedTempUser = $pageUpdaterStatus->getSavedTempUser();
		$newContext = $pageUpdaterStatus->getContext();

		$this->assertNotSame( $anonUser, $savedTempUser, 'does not use anon user' );
		$this->assertNotNull( $savedTempUser, 'saved temp user created' );
		$this->assertTrue( $savedTempUser->isRegistered(), 'saved temp user registered' );
		$this->assertTrue( $services->getUserIdentityUtils()->isTemp( $savedTempUser ), 'saved temp user is temp' );
		$this->assertNotSame( $context, $newContext, 'new context created' );
		$this->assertSame( $savedTempUser, $newContext->getUser(), 'new context has temp user' );
		$this->assertSame( $anonUser, $context->getUser(), 'old context not modified' );
		$this->assertSame( $context->getRequest(), $newContext->getRequest(), 'new context derives from old context' );
	}

	public function testTempAccountCreationFailed(): void {
		$tempUseCreator = $this->createConfiguredMock( TempUserCreator::class, [
			'shouldAutoCreate' => true,
			'create' => CreateStatus::newFatal( __CLASS__ ),
		] );
		$this->setService( 'TempUserCreator', $tempUseCreator );
		$pageUpdaterFactory = EntitySchemaServices::getMediaWikiPageUpdaterFactory();

		$pageUpdaterStatus = $pageUpdaterFactory->getPageUpdater( 'TestTitle', new RequestContext() );

		$this->assertStatusError( __CLASS__, $pageUpdaterStatus );
	}

}
