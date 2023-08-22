<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use User;

/**
 * @license GPL-2.0-or-later
 */
class WatchlistUpdater {

	private User $user;
	private int $namespace;

	public function __construct( User $user, int $namespaceID ) {
		$this->user = $user;
		$this->namespace = $namespaceID;
	}

	public function optionallyWatchEditedSchema( EntitySchemaId $entitySchemaId ): void {
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$watchlistManager = $services->getWatchlistManager();
		if ( $userOptionsLookup->getOption( $this->user, 'watchdefault' ) ) {
			$watchlistManager->setWatch(
				true,
				$this->user,
				Title::makeTitle( $this->namespace, $entitySchemaId->getId() )
			);
		}
	}

	public function optionallyWatchNewSchema( EntitySchemaId $entitySchemaId ): void {
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$watchlistManager = $services->getWatchlistManager();
		if ( $userOptionsLookup->getOption( $this->user, 'watchcreations' )
			|| $userOptionsLookup->getOption( $this->user, 'watchdefault' ) ) {
			$watchlistManager->setWatch(
				true,
				$this->user,
				Title::makeTitle( $this->namespace, $entitySchemaId->getId() )
			);
		}
	}

}
