<?php

namespace EntitySchema\Tests\DataAccess;

use PHPUnit4And6Compat;
use User;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;

/**
 * @covers \EntitySchema\DataAccess\MediaWikiPageUpdaterFactory
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactoryTest extends \PHPUnit_Framework_TestCase {
	use PHPUnit4And6Compat;

	public function testGetPageUpdater() {
		$user = $this->createMock( User::class );

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$pageUpdater = $pageUpdaterFactory->getPageUpdater( 'testTitle' );
		$this->assertAttributeEquals( $user, 'user', $pageUpdater );
		$title = $this->readAttribute( $pageUpdater, 'wikiPage' )->getTitle();
		$this->assertEquals( 'testTitle', $title->getText() );
	}

}
