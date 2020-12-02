<?php

namespace EntitySchema\DataAccess;

use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\PageUpdater;
use RecentChange;
use Title;
use User;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactory {

	/** @var User */
	private $user;

	public function __construct( User $user ) {
		$this->user = $user;
	}

	public function getPageUpdater( $pageTitleString ): PageUpdater {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $pageTitleString );
		$wikipage = WikiPage::factory( $title );
		$pageUpdater = $wikipage->newPageUpdater( $this->user );
		$this->setPatrolStatus( $pageUpdater, $title );

		return $pageUpdater;
	}

	private function setPatrolStatus( PageUpdater $pageUpdater, Title $title ) {
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
