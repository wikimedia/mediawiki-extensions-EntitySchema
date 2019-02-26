<?php

namespace phpunit\DataAccess;

use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use WatchedItem;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \Wikibase\Schema\DataAccess\WatchlistUpdater
 */
final class WatchListUpdaterTest extends MediaWikiTestCase {

	public function editPageDataProvider() {
		yield [
			'watchdefault',
			true,
			'O123',
			true,
		];

		yield [
			'watchdefault',
			false,
			'O1234',
			false,
		];
	}

	/**
	 * @dataProvider editPageDataProvider
	 */
	public function testWatchEditedSchema( $optionKey, $optionValue, $pageid, $expectedToBeWatched ) {
		$testUser = self::getTestUser()->getUser();
		$testUser->setOption( $optionKey, $optionValue );
		$watchlistUpdater = new WatchlistUpdater( $testUser, NS_WBSCHEMA_JSON );

		$watchlistUpdater->optionallyWatchEditedSchema( new SchemaId( $pageid ) );

		$watchedItemStore = MediaWikiServices::getInstance()->getWatchedItemStore();
		$actualItems = $watchedItemStore->getWatchedItemsForUser( $testUser );

		$actualItems = array_unique(
			array_map( function( WatchedItem $watchedItem ) {
				return $watchedItem->getLinkTarget()->getText();
			}, $actualItems )
		);

		if ( $expectedToBeWatched ) {
			$this->assertContains( $pageid, $actualItems );
		} else {
			$this->assertNotContains( $pageid, $actualItems );
		}
	}

	public function newPageDataProvider() {
		yield [
			[
				[
					'key' => 'watchcreations',
					'value' => true,
				],
			],
			'O12345',
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
			'O123456',
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
			'O1234567',
			true,
		];
	}

	/**
	 * @dataProvider newPageDataProvider
	 */
	public function testWatchNewSchema( $optionsToBeSet, $pageid, $expectedToBeWatched ) {
		$testUser = self::getTestUser()->getUser();
		foreach ( $optionsToBeSet as $optionToBeSet ) {
			$testUser->setOption( $optionToBeSet['key'], $optionToBeSet['value'] );
		}
		$watchlistUpdater = new WatchlistUpdater( $testUser, NS_WBSCHEMA_JSON );

		$watchlistUpdater->optionallyWatchNewSchema( new SchemaId( $pageid ) );

		$watchedItemStore = MediaWikiServices::getInstance()->getWatchedItemStore();
		$actualItems = $watchedItemStore->getWatchedItemsForUser( $testUser );

		$actualItems = array_unique(
			array_map( function( WatchedItem $watchedItem ) {
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
