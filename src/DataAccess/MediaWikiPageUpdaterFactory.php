<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\TempUser\TempUserCreator;
use MediaWiki\User\User;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactory {

	private PermissionManager $permissionManager;
	private TempUserCreator $tempUserCreator;
	private TitleFactory $titleFactory;
	private WikiPageFactory $wikiPageFactory;

	public function __construct(
		PermissionManager $permissionManager,
		TempUserCreator $tempUserCreator,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->tempUserCreator = $tempUserCreator;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Try to create a new PageUpdater for the given EntitySchema title and context.
	 *
	 * If the returned status is {@link PageUpdaterStatus::isOK() OK},
	 * it will contain the PageUpdater, a context to use for the rest of the edit,
	 * and the temp user if one was created.
	 * The returned context will also have its title set to the EntityScheme title,
	 * so it can directly be used for edit filters.
	 * (The original context is not modified and keeps its original user and title,
	 * but it should generally not be used anymore past this point.)
	 */
	public function getPageUpdater( string $pageTitleString, IContextSource $context ): PageUpdaterStatus {
		$title = $this->titleFactory->makeTitle( NS_ENTITYSCHEMA_JSON, $pageTitleString );
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );

		$context = new DerivativeContext( $context );
		$context->setTitle( $title );

		$savedTempUser = null;
		$user = $context->getUser();
		if ( $this->tempUserCreator->shouldAutoCreate( $user, 'edit' ) ) {
			$status = $this->tempUserCreator->create( null, $context->getRequest() );
			if ( $status->isOK() ) {
				$savedTempUser = $status->getUser();
				$user = $savedTempUser;
				$context->setUser( $savedTempUser );
			} else {
				return PageUpdaterStatus::wrap( $status );
			}
		}

		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$this->setPatrolStatus( $user, $pageUpdater, $title );

		return PageUpdaterStatus::newUpdater( $pageUpdater, $savedTempUser, $context );
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
