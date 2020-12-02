<?php

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\SchemaId;
use Title;
use User;
use WatchAction;

/**
 * @license GPL-2.0-or-later
 */
class WatchlistUpdater {

	/** @var User */
	private $user;
	/** @var int */
	private $namespace;

	public function __construct( User $user, int $namespaceID ) {
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
