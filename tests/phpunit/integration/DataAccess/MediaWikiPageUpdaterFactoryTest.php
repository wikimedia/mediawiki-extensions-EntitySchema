<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\MediaWiki\EntitySchemaServices;
use ExtensionRegistry;
use MediaWiki\Context\RequestContext;
use MediaWikiIntegrationTestCase;
use RecentChange;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiPageUpdaterFactory
 * @group Database
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactoryTest extends MediaWikiIntegrationTestCase {

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

}
