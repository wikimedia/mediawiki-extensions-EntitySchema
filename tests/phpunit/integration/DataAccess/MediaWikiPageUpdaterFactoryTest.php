<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use MediaWikiTestCase;
use RecentChange;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiPageUpdaterFactory
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactoryTest extends MediaWikiTestCase {

	public function testGetPageUpdater() {
		$user = self::getTestUser()->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'testTitle' )
		);
		$this->assertEquals( $user, $pageUpdater->user );
		$title = $pageUpdater->wikiPage->getTitle();
		$this->assertEquals( 'testTitle', $title->getText() );
	}

	public function testAutopatrolledFlagIsSetForSysop() {
		$user = self::getTestUser( 'sysop' )->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'testTitle' )
		);
		$this->assertEquals( RecentChange::PRC_AUTOPATROLLED, $pageUpdater->rcPatrolStatus );
	}

	public function testAutopatrolledFlagNotSetForNormalUser() {
		$user = self::getTestUser()->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = TestingAccessWrapper::newFromObject(
			$pageUpdaterFactory->getPageUpdater( 'testTitle' )
		);
		$this->assertEquals( RecentChange::PRC_UNPATROLLED, $pageUpdater->rcPatrolStatus );
	}

}
