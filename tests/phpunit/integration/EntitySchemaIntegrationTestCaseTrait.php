<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Session\Session;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
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

	/**
	 * Register a custom TempUserCreatedRedirect handler to test that the hook is being used correctly.
	 * Call this in the “arrange” phase,
	 * then later call {@link self::assertRedirectToEntitySchema()} in the “assert” phase.
	 */
	private function addTempUserHook(): void {
		$this->setTemporaryHook( 'TempUserCreatedRedirect', function (
			Session $session,
			UserIdentity $user,
			string $returnTo,
			string $returnToQuery,
			string $returnToAnchor,
			&$redirectUrl
		) {
			$userIdentityUtils = $this->getServiceContainer()->getUserIdentityUtils();
			$this->assertTrue( $userIdentityUtils->isTemp( $user ) );
			$redirectUrl = 'http://centralwiki.test?returnto=' . $returnTo;
		} );
	}

	/**
	 * Assert that the given redirect URL matches how the TempUserCreatedRedirect hook was set up.
	 * Call this in a test that previously used {@link self::addTempUserHook()} and then made an edit.
	 *
	 * @param LinkTarget|PageReference $title The expected title of the EntitySchema the redirect should point to.
	 * @param string $redirect The actual redirect URL.
	 */
	private function assertRedirectToEntitySchema( $title, string $redirect ): void {
		$titleString = $this->getServiceContainer()->getTitleFormatter()
			->getPrefixedDBkey( $title );
		$this->assertSame( "http://centralwiki.test?returnto=$titleString", $redirect );
	}

	/** @return MediaWikiServices */
	abstract protected function getServiceContainer();

	/**
	 * @param string|string[] $groups
	 * @return \TestUser
	 */
	abstract protected function getTestUser( $groups = [] );

}
