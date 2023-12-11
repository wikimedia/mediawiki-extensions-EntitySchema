<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use RecentChange;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactory {

	private User $user;

	public function __construct( User $user ) {
		$this->user = $user;
	}

	public function getPageUpdater( string $pageTitleString ): PageUpdater {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $pageTitleString );
		$wikipage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$pageUpdater = $wikipage->newPageUpdater( $this->user );
		$this->setPatrolStatus( $pageUpdater, $title );

		return $pageUpdater;
	}

	private function setPatrolStatus( PageUpdater $pageUpdater, Title $title ): void {
		global $wgUseNPPatrol, $wgUseRCPatrol;
		$needsPatrol = $wgUseRCPatrol || ( $wgUseNPPatrol && !$title->exists() );
		$permissionsManager = MediaWikiServices::getInstance()->getPermissionManager();

		if (
			$needsPatrol
			&& $permissionsManager->userCan( 'autopatrol', $this->user, $title )
		) {
			$pageUpdater->setRcPatrolStatus( RecentChange::PRC_AUTOPATROLLED );
		}
	}

}
