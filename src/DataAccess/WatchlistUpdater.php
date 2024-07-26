<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\Watchlist\WatchlistManager;

/**
 * @license GPL-2.0-or-later
 */
class WatchlistUpdater {

	private UserOptionsLookup $userOptionsLookup;
	private WatchlistManager $watchlistManager;

	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		WatchlistManager $watchlistManager
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->watchlistManager = $watchlistManager;
	}

	public function optionallyWatchEditedSchema( User $user, EntitySchemaId $entitySchemaId ): void {
		if ( !$user->isNamed() ) {
			return;
		}
		if ( $this->userOptionsLookup->getOption( $user, 'watchdefault' ) ) {
			$this->watchlistManager->setWatch(
				true,
				$user,
				Title::makeTitle( NS_ENTITYSCHEMA_JSON, $entitySchemaId->getId() )
			);
		}
	}

	public function optionallyWatchNewSchema( User $user, EntitySchemaId $entitySchemaId ): void {
		if ( !$user->isNamed() ) {
			return;
		}
		if ( $this->userOptionsLookup->getOption( $user, 'watchcreations' )
			|| $this->userOptionsLookup->getOption( $user, 'watchdefault' ) ) {
			$this->watchlistManager->setWatch(
				true,
				$user,
				Title::makeTitle( NS_ENTITYSCHEMA_JSON, $entitySchemaId->getId() )
			);
		}
	}

}
