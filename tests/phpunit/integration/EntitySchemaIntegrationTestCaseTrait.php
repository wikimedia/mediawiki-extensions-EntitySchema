<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 */
trait EntitySchemaIntegrationTestCaseTrait {

	private function saveSchemaPageContent( WikiPage $page, array $content ): RevisionRecord {
		$content['serializationVersion'] = '3.0';
		$updater = $page->newPageUpdater( $this->getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new EntitySchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment( static::class )
		);

		return $firstRevRecord;
	}

	private function getCurrentSchemaContent( string $pageName ): array {
		/** @var EntitySchemaContent $content */
		$rev = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $this->getCurrentSchemaRevisionId( $pageName ) );
		return json_decode( $rev->getContent( SlotRecord::MAIN )->getText(), true );
	}

	private function getCurrentSchemaRevisionId( string $pageName ): int {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $pageName );
		return $title->getLatestRevID();
	}

	/** @return \TestUser */
	abstract protected function getTestUser( $groups = [] );

}
