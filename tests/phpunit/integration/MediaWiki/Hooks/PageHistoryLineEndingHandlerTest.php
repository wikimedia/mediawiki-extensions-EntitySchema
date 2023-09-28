<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use Article;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use Language;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\PageHistoryLineEndingHandler
 *
 * @license GPL-2.0-or-later
 * @group Database
 */
class PageHistoryLineEndingHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOneRestoreLink(): void {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );

		$updaterFactory = new MediaWikiPageUpdaterFactory( $this->getTestUser()->getUser() );
		$watchListUpdater = $this->createMock( WatchlistUpdater::class );
		$language = $this->createConfiguredMock( Language::class,
			[ 'truncateForVisual' => '' ] );
		$languageFactory = $this->createConfiguredMock( LanguageFactory::class,
			[ 'getLanguage' => $language ] );
		$hookContainer = $this->createConfiguredMock( HookContainer::class,
			[ 'run' => true ] );
		$titleFactory = $this->getServiceContainer()->getTitleFactory();
		$schemaInserter = new MediaWikiRevisionEntitySchemaInserter(
			$updaterFactory,
			$watchListUpdater,
			$this->createConfiguredMock( IdGenerator::class, [ 'getNewId' => 1 ] ),
			$context,
			$languageFactory,
			$hookContainer,
			$titleFactory
		);
		$schemaId = $schemaInserter->insertSchema( 'en' );

		$revisionLookup = $this->getServiceContainer()->getRevisionLookup();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$updaterFactory,
			$watchListUpdater,
			$context,
			$revisionLookup,
			$languageFactory,
			$hookContainer,
			$titleFactory
		);
		$schemaTitle = $this->getServiceContainer()
			->getTitleFactory()
			->makeTitleSafe( NS_ENTITYSCHEMA_JSON, $schemaId->getId() );
		$context->setTitle( $schemaTitle );
		$baseRevId = $revisionLookup->getKnownCurrentRevision( $schemaTitle )->getId();
		$schemaUpdater->updateSchemaText( $schemaId, 'a', $baseRevId );

		$action = $this->getServiceContainer()
			->getActionFactory()
			->getAction(
				'history',
				Article::newFromTitle( $schemaTitle, $context ),
				$context
			);
		$action->show();
		$html = $action->getOutput()->getHTML();

		$this->assertSame( 1, substr_count( $html, 'restore=' ),
			'expecting exactly one restore= link in HTML: ' . $html );
	}

}
