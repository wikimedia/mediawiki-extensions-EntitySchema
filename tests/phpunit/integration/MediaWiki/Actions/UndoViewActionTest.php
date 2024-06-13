<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Article;
use EntitySchema\MediaWiki\Actions\UndoViewAction;
use EntitySchema\MediaWiki\Content\EntitySchemaSlotDiffRenderer;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use ExtensionRegistry;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use TextSlotDiffRenderer;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\MediaWiki\Actions\UndoViewAction
 * @covers \EntitySchema\MediaWiki\Actions\AbstractUndoAction
 * @covers \EntitySchema\MediaWiki\UndoHandler
 * @covers \EntitySchema\Presentation\DiffRenderer
 * @covers \EntitySchema\Presentation\ConfirmationFormRenderer
 */
class UndoViewActionTest extends MediaWikiIntegrationTestCase {
	use EntitySchemaIntegrationTestCaseTrait;

	public function test_UndoView() {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}

		// arrange
		$schemaId = 'E123';
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, $schemaId ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc', 'id' => $schemaId ] )->getId();
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def', 'id' => $schemaId ] )->getId();

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest(
			new FauxRequest(
				[
					'action' => 'edit',
					'undoafter' => $firstID,
					'undo' => $secondId,
					'title' => 'Schema:' . $schemaId,
				],
				false
			)
		);

		$textSlotDiffRenderer = $this->getServiceContainer()
			->getContentHandlerFactory()
			->getContentHandler( CONTENT_MODEL_TEXT )
			->getSlotDiffRenderer( $context );
		$textSlotDiffRenderer->setEngine( TextSlotDiffRenderer::ENGINE_PHP );
		$diffRenderer = new EntitySchemaSlotDiffRenderer(
			$context,
			$textSlotDiffRenderer
		);
		$undoViewAction = new UndoViewAction(
			Article::newFromWikiPage( $page, $context ),
			$context,
			$diffRenderer
		);

		// act
		$undoViewAction->show();

		// assert
		$actualHTML = $undoViewAction->getContext()->getOutput()->getHTML();
		$this->assertStringContainsString(
			'<ins class="diffchange diffchange-inline">abc</ins>',
			$actualHTML
		);
		$this->assertStringContainsString(
			'<del class="diffchange diffchange-inline">def</del>',
			$actualHTML
		);
	}

}
