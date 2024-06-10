<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Model\EntitySchemaId;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use WatchedItem;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\DataAccess\WatchlistUpdater
 * @group Database
 */
final class WatchListUpdaterTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public static function editPageDataProvider(): iterable {
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
	public function testWatchEditedSchema(
		string $optionKey,
		bool $optionValue,
		string $pageid,
		bool $expectedToBeWatched
	) {
		$testUser = $this->getTestUser()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()->setOption( $testUser, $optionKey, $optionValue );
		$watchlistUpdater = new WatchlistUpdater( $testUser, NS_ENTITYSCHEMA_JSON );

		$watchlistUpdater->optionallyWatchEditedSchema( new EntitySchemaId( $pageid ) );

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

	public static function newPageDataProvider(): iterable {
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
	public function testWatchNewSchema(
		array $optionsToBeSet,
		string $pageid,
		bool $expectedToBeWatched
	) {
		$testUser = $this->getTestUser()->getUser();
		$services = $this->getServiceContainer();
		$userOptionsManager = $services->getUserOptionsManager();
		foreach ( $optionsToBeSet as $optionToBeSet ) {
			$userOptionsManager->setOption( $testUser, $optionToBeSet['key'], $optionToBeSet['value'] );
		}
		$watchlistUpdater = new WatchlistUpdater( $testUser, NS_ENTITYSCHEMA_JSON );

		$watchlistUpdater->optionallyWatchNewSchema( new EntitySchemaId( $pageid ) );

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
