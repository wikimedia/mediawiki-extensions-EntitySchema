<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use MediaWikiIntegrationTestCase;
use RecentChange;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiPageUpdaterFactory
 * @group Database
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactoryTest extends MediaWikiIntegrationTestCase {

	public function testGetPageUpdater() {
		$user = $this->getTestUser()->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'TestTitle' )
		);
		$this->assertEquals( $user, $pageUpdater->author );
		$title = $pageUpdater->wikiPage->getTitle();
		$this->assertEquals( 'TestTitle', $title->getText() );
	}

	public function testAutopatrolledFlagIsSetForSysop() {
		$user = $this->getTestUser( 'sysop' )->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'TestTitle' )
		);
		$this->assertEquals( RecentChange::PRC_AUTOPATROLLED, $pageUpdater->rcPatrolStatus );
	}

	public function testAutopatrolledFlagNotSetForNormalUser() {
		$user = $this->getTestUser()->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'TestTitle' )
		);
		$this->assertEquals( RecentChange::PRC_UNPATROLLED, $pageUpdater->rcPatrolStatus );
	}

}
