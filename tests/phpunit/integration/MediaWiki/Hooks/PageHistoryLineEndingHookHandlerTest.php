<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\MediaWiki\HookRunner;
use MediaWiki\Context\RequestContext;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Page\Article;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\PageHistoryLineEndingHookHandler
 *
 * @license GPL-2.0-or-later
 * @group Database
 */
class PageHistoryLineEndingHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOneRestoreLink(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );

		$context = new RequestContext();
		$context->setRequest( new FauxRequest() );

		$services = $this->getServiceContainer();
		$updaterFactory = EntitySchemaServices::getMediaWikiPageUpdaterFactory( $services );
		$watchListUpdater = $this->createMock( WatchlistUpdater::class );
		$language = $this->createConfiguredMock( Language::class,
			[ 'truncateForVisual' => '' ] );
		$languageFactory = $this->createConfiguredMock( LanguageFactory::class,
			[ 'getLanguage' => $language ] );
		$hookRunner = $this->createConfiguredMock( HookRunner::class,
			[ 'onEditFilterMergedContent' => true ] );
		$schemaInserter = new MediaWikiRevisionEntitySchemaInserter(
			$updaterFactory,
			$watchListUpdater,
			$this->createConfiguredMock( IdGenerator::class, [ 'getNewId' => 1 ] ),
			$context,
			$languageFactory,
			$hookRunner
		);
		$status = $schemaInserter->insertSchema( 'en' );
		$this->assertStatusGood( $status );
		$schemaId = $status->getEntitySchemaId();

		$revisionLookup = $services->getRevisionLookup();
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$updaterFactory,
			$watchListUpdater,
			$context,
			$revisionLookup,
			$languageFactory,
			$hookRunner
		);
		$schemaTitle = $services
			->getTitleFactory()
			->makeTitleSafe( NS_ENTITYSCHEMA_JSON, $schemaId->getId() );
		$context->setTitle( $schemaTitle );
		$baseRevId = $revisionLookup->getKnownCurrentRevision( $schemaTitle )->getId();
		$schemaUpdater->updateSchemaText( $schemaId, 'a', $baseRevId );

		$action = $services
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
