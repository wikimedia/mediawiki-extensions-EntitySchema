<?php

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\SchemaId;
use MediaWiki\MediaWikiServices;
use Title;
use User;

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
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$watchlistManager = $services->getWatchlistManager();
		if ( $userOptionsLookup->getOption( $this->user, 'watchdefault' ) ) {
			$watchlistManager->setWatch(
				true,
				$this->user,
				Title::makeTitle( $this->namespace, $schemaID->getId() )
			);
		}
	}

	public function optionallyWatchNewSchema( SchemaId $schemaID ) {
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$watchlistManager = $services->getWatchlistManager();
		if ( $userOptionsLookup->getOption( $this->user, 'watchcreations' )
			|| $userOptionsLookup->getOption( $this->user, 'watchdefault' ) ) {
			$watchlistManager->setWatch(
				true,
				$this->user,
				Title::makeTitle( $this->namespace, $schemaID->getId() )
			);
		}
	}

}
