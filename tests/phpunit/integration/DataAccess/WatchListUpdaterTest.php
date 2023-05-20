<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Model\SchemaId;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use WatchedItem;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\DataAccess\WatchlistUpdater
 */
final class WatchListUpdaterTest extends MediaWikiIntegrationTestCase {

	public static function editPageDataProvider() {
		yield [
			'watchdefault',
			true,
			'E123',
			true,
		];

		yield [
			'watchdefault',
			false,
			'E1234',
			false,
		];
	}

	/**
	 * @dataProvider editPageDataProvider
	 */
	public function testWatchEditedSchema( $optionKey, $optionValue, $pageid, $expectedToBeWatched ) {
		$testUser = self::getTestUser()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()->setOption( $testUser, $optionKey, $optionValue );
		$watchlistUpdater = new WatchlistUpdater( $testUser, NS_ENTITYSCHEMA_JSON );

		$watchlistUpdater->optionallyWatchEditedSchema( new SchemaId( $pageid ) );

		$watchedItemStore = MediaWikiServices::getInstance()->getWatchedItemStore();
		$actualItems = $watchedItemStore->getWatchedItemsForUser( $testUser );

		$actualItems = array_unique(
			array_map( static function ( WatchedItem $watchedItem ) {
				return $watchedItem->getLinkTarget()->getText();
			}, $actualItems )
		);

		if ( $expectedToBeWatched ) {
			$this->assertContains( $pageid, $actualItems );
		} else {
			$this->assertNotContains( $pageid, $actualItems );
		}
	}

	public static function newPageDataProvider() {
		yield [
			[
				[
					'key' => 'watchcreations',
					'value' => true,
				],
			],
			'E12345',
			true,
		];

		yield [
			[
				[
					'key' => 'watchcreations',
					'value' => false,
				],
				[
					'key' => 'watchdefault',
					'value' => false,
				],
			],
			'E123456',
			false,
		];

		yield [
			[
				[
					'key' => 'watchcreations',
					'value' => false,
				],
				[
					'key' => 'watchdefault',
					'value' => true,
				],
			],
			'E1234567',
			true,
		];
	}

	/**
	 * @dataProvider newPageDataProvider
	 */
	public function testWatchNewSchema( $optionsToBeSet, $pageid, $expectedToBeWatched ) {
		$testUser = self::getTestUser()->getUser();
		$services = $this->getServiceContainer();
		$userOptionsManager = $services->getUserOptionsManager();
		foreach ( $optionsToBeSet as $optionToBeSet ) {
			$userOptionsManager->setOption( $testUser, $optionToBeSet['key'], $optionToBeSet['value'] );
		}
		$watchlistUpdater = new WatchlistUpdater( $testUser, NS_ENTITYSCHEMA_JSON );

		$watchlistUpdater->optionallyWatchNewSchema( new SchemaId( $pageid ) );

		$watchedItemStore = $services->getWatchedItemStore();
		$actualItems = $watchedItemStore->getWatchedItemsForUser( $testUser );

		$actualItems = array_unique(
			array_map( static function ( WatchedItem $watchedItem ) {
				return $watchedItem->getLinkTarget()->getText();
			}, $actualItems )
		);

		if ( $expectedToBeWatched ) {
			$this->assertContains( $pageid, $actualItems );
		} else {
			$this->assertNotContains( $pageid, $actualItems );
		}
	}

}
