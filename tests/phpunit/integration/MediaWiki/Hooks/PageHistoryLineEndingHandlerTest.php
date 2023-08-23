<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use Article;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use Language;
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
		$updaterFactory = new MediaWikiPageUpdaterFactory( $this->getTestUser()->getUser() );
		$watchListUpdater = $this->createMock( WatchlistUpdater::class );
		$language = $this->createConfiguredMock( Language::class,
			[ 'truncateForVisual' => '' ] );
		$languageFactory = $this->createConfiguredMock( LanguageFactory::class,
			[ 'getLanguage' => $language ] );
		$schemaInserter = new MediaWikiRevisionEntitySchemaInserter(
			$updaterFactory,
			$watchListUpdater,
			$this->createConfiguredMock( IdGenerator::class, [ 'getNewId' => 1 ] ),
			$languageFactory
		);
		$schemaId = $schemaInserter->insertSchema( 'en' );

		$revisionLookup = $this->getServiceContainer()->getRevisionLookup();
		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$updaterFactory,
			$watchListUpdater,
			$revisionLookup,
			$languageFactory
		);
		$schemaTitle = $this->getServiceContainer()
			->getTitleFactory()
			->makeTitleSafe( NS_ENTITYSCHEMA_JSON, $schemaId->getId() );
		$baseRevId = $revisionLookup->getKnownCurrentRevision( $schemaTitle )->getId();
		$schemaUpdater->updateSchemaText( $schemaId, 'a', $baseRevId );

		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );
		$context->setTitle( $schemaTitle );
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
