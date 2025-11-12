<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\EntitySchemaServices;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\Watchlist\WatchedItem;
use MediaWikiIntegrationTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\DataAccess\WatchlistUpdater
 * @group Database
 */
final class WatchListUpdaterTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
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
		$watchlistUpdater = EntitySchemaServices::getWatchlistUpdater();

		$watchlistUpdater->optionallyWatchEditedSchema( $testUser, new EntitySchemaId( $pageid ) );

		$watchedItemStore = $this->getServiceContainer()->getWatchedItemStore();
		$actualItems = $watchedItemStore->getWatchedItemsForUser( $testUser );

		$actualItems = array_unique(
			array_map( static function ( WatchedItem $watchedItem ) {
				return $watchedItem->getTarget()->getDBkey();
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
		$watchlistUpdater = EntitySchemaServices::getWatchlistUpdater();

		$watchlistUpdater->optionallyWatchNewSchema( $testUser, new EntitySchemaId( $pageid ) );

		$watchedItemStore = $services->getWatchedItemStore();
		$actualItems = $watchedItemStore->getWatchedItemsForUser( $testUser );

		$actualItems = array_unique(
			array_map( static function ( WatchedItem $watchedItem ) {
				return $watchedItem->getTarget()->getDBkey();
			}, $actualItems )
		);

		if ( $expectedToBeWatched ) {
			$this->assertContains( $pageid, $actualItems );
		} else {
			$this->assertNotContains( $pageid, $actualItems );
		}
	}

	public function testDoesNothingForUnnamedUser(): void {
		$user = $this->createMock( User::class );
		$user->method( 'isNamed' )->willReturn( false );
		$this->setService( 'UserOptionsLookup',
			$this->createNoOpMock( UserOptionsLookup::class ) );
		$watchlistUpdater = EntitySchemaServices::getWatchlistUpdater();
		$id = new EntitySchemaId( 'E1' );

		$watchlistUpdater->optionallyWatchNewSchema( $user, $id );
		$watchlistUpdater->optionallyWatchEditedSchema( $user, $id );
	}

}
