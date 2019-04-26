<?php

namespace EntitySchema\DataAccess;

use Title;
use User;
use WatchAction;
use EntitySchema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 */
class WatchlistUpdater {

	private $user;
	private $namespace;

	/**
	 * @param User $user
	 * @param int  $namespaceID
	 */
	public function __construct( User $user, $namespaceID ) {
		$this->user = $user;
		$this->namespace = $namespaceID;
	}

	public function optionallyWatchEditedSchema( SchemaId $schemaID ) {
		if ( $this->user->getOption( 'watchdefault' ) ) {
			WatchAction::doWatchOrUnwatch(
				true,
				Title::makeTitle( $this->namespace, $schemaID->getId() ),
				$this->user
			);
		}
	}

	public function optionallyWatchNewSchema( SchemaId $schemaID ) {
		if ( $this->user->getOption( 'watchcreations' ) || $this->user->getOption( 'watchdefault' ) ) {
			WatchAction::doWatchOrUnwatch(
				true,
				Title::makeTitle( $this->namespace, $schemaID->getId() ),
				$this->user
			);
		}
	}

}
