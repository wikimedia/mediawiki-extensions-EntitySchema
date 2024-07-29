<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use RecentChange;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactory {

	private PermissionManager $permissionManager;
	private TitleFactory $titleFactory;
	private WikiPageFactory $wikiPageFactory;

	public function __construct(
		PermissionManager $permissionManager,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	public function getPageUpdater( string $pageTitleString, IContextSource $context ): PageUpdaterStatus {
		$title = $this->titleFactory->makeTitle( NS_ENTITYSCHEMA_JSON, $pageTitleString );
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );

		$user = $context->getUser();

		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$this->setPatrolStatus( $user, $pageUpdater, $title );

		return PageUpdaterStatus::newUpdater( $pageUpdater );
	}

	private function setPatrolStatus( User $user, PageUpdater $pageUpdater, Title $title ): void {
		global $wgUseNPPatrol, $wgUseRCPatrol;
		$needsPatrol = $wgUseRCPatrol || ( $wgUseNPPatrol && !$title->exists() );

		if (
			$needsPatrol
			&& $this->permissionManager->userCan( 'autopatrol', $user, $title )
		) {
			$pageUpdater->setRcPatrolStatus( RecentChange::PRC_AUTOPATROLLED );
		}
	}

}
