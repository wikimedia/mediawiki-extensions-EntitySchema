<?php

namespace EntitySchema\Tests\DataAccess;

use MediaWikiTestCase;
use RecentChange;
use User;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiPageUpdaterFactory
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactoryTest extends MediaWikiTestCase {

	public function testGetPageUpdater() {
		$user = $this->createMock( User::class );

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = $pageUpdaterFactory->getPageUpdater( 'testTitle' );
		$this->assertAttributeEquals( $user, 'user', $pageUpdater );
		$title = $this->readAttribute( $pageUpdater, 'wikiPage' )->getTitle();
		$this->assertEquals( 'testTitle', $title->getText() );
	}

	public function testAutopatrolledFlagIsSetForSysop() {
		$user = self::getTestUser( 'sysop' )->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = $pageUpdaterFactory->getPageUpdater( 'testTitle' );

		$this->assertAttributeEquals( RecentChange::PRC_AUTOPATROLLED, 'rcPatrolStatus', $pageUpdater );
	}

	public function testAutopatrolledFlagNotSetForNormalUser() {
		$user = self::getTestUser()->getUser();

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = $pageUpdaterFactory->getPageUpdater( 'testTitle' );

		$this->assertAttributeEquals( RecentChange::PRC_UNPATROLLED, 'rcPatrolStatus', $pageUpdater );
	}

}
