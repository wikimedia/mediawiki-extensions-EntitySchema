<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use MediaWikiIntegrationTestCase;
use RecentChange;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiPageUpdaterFactory
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactoryTest extends MediaWikiIntegrationTestCase {

	public function testGetPageUpdater() {
		$user = self::getTestUser()->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'TestTitle' )
		);
		$this->assertEquals( $user, $pageUpdater->author );
		$title = $pageUpdater->wikiPage->getTitle();
		$this->assertEquals( 'TestTitle', $title->getText() );
	}

	public function testAutopatrolledFlagIsSetForSysop() {
		$user = self::getTestUser( 'sysop' )->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'TestTitle' )
		);
		$this->assertEquals( RecentChange::PRC_AUTOPATROLLED, $pageUpdater->rcPatrolStatus );
	}

	public function testAutopatrolledFlagNotSetForNormalUser() {
		$user = self::getTestUser()->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'TestTitle' )
		);
		$this->assertEquals( RecentChange::PRC_UNPATROLLED, $pageUpdater->rcPatrolStatus );
	}

}
