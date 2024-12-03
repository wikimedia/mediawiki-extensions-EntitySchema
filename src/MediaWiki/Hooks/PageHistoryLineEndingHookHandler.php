<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\Hook\PageHistoryLineEndingHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 */
class PageHistoryLineEndingHookHandler implements PageHistoryLineEndingHook {

	private bool $entitySchemaIsRepo;
	private ?LinkRenderer $linkRenderer;
	private ?PermissionManager $permissionManager;
	private ?RevisionStore $revisionStore;

	public function __construct(
		bool $entitySchemaIsRepo,
		?LinkRenderer $linkRenderer,
		?PermissionManager $permissionManager,
		?RevisionStore $revisionStore
	) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
		if ( $entitySchemaIsRepo ) {
			Assert::parameterType( LinkRenderer::class, $linkRenderer, '$linkRenderer' );
			Assert::parameterType( PermissionManager::class, $permissionManager, '$permissionManager' );
			Assert::parameterType( RevisionStore::class, $revisionStore, '$revisionStore' );
		}
		$this->linkRenderer = $linkRenderer;
		$this->permissionManager = $permissionManager;
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @inheritDoc
	 */
	public function onPageHistoryLineEnding( $historyAction, &$row, &$html, &$classes, &$attribs ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		$title = $historyAction->getTitle();

		if ( $title->getContentModel() !== EntitySchemaContent::CONTENT_MODEL_ID ) {
			return;
		}

		$rev = $this->revisionStore->newRevisionFromRow( $row );
		$revId = $rev->getId();
		$latestRevId = $title->getLatestRevID();

		if ( $revId === $latestRevId ) {
			return;
		}

		/**
		 * The constant DELETED_TEXT indicates that the content of the revision is hidden,
		 * as opposed to its summary or the user that created the revision.
		 * For more information see:
		 * https://www.mediawiki.org/wiki/Manual:Revision_table#rev_deleted
		 * https://www.mediawiki.org/wiki/Manual:RevisionDelete
		 */
		if ( $rev->isDeleted( RevisionRecord::DELETED_TEXT ) ) {
			return;
		}

		$user = $historyAction->getUser();
		if ( !$this->permissionManager->quickUserCan( 'edit', $user, $title ) ) {
			return;
		}

		$link = $this->linkRenderer->makeKnownLink(
			$title,
			$historyAction->msg( 'entityschema-restoreold' )->text(),
			[],
			[
				'action' => 'edit',
				'restore' => $revId,
			]
		);
		$html .= ' ' . $historyAction->msg( 'parentheses' )->rawParams( $link )->escaped();
	}
}
