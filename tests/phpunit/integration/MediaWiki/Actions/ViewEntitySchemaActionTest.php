<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use EntitySchema\MediaWiki\Actions\ViewEntitySchemaAction;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\Page\Article;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Actions\EntitySchemaEditAction
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class ViewEntitySchemaActionTest extends MediaWikiIntegrationTestCase {

	use EntitySchemaIntegrationTestCaseTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
	}

	public function testSetTextTitleIfPresentInMetadata() {
		$context = RequestContext::getMain();
		$services = $this->getServiceContainer();
		$id = 'E123';
		$title = $services->getTitleFactory()->makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$context->setTitle( $title );
		$wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );
		$this->saveSchemaPageContent( $wikiPage, [
			'schemaText' => 'schema text',
			'id' => $id,
		] );

		$article = Article::newFromTitle(
			Title::newFromDBkey( 'EntitySchema:' . $id ),
			$context
		);
		$action = new ViewEntitySchemaAction( $article, $context );

		$action->show();
		$this->assertEquals(
			'No label defined (E123)',
			$context->getOutput()->getProperty( 'entityschema-meta-tags' )['title'],
			'entityschema-meta-tags title property should be set'
		);
		$this->assertEquals(
			$context->getOutput()->getProperty( 'entityschema-meta-tags' )['title'],
			$action->getOutput()->getHTMLTitle(),
			'HTML title should be set to saved entityschema property'
		);
	}

	public function testDontSetTitleIfAbsentFromMetadata(): void {
		$context = RequestContext::getMain();
		$services = $this->getServiceContainer();
		$id = 'E123';
		$title = $services->getTitleFactory()->makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$context->setTitle( $title );
		$wikiPage = $services->getWikiPageFactory()->newFromTitle( $title );
		$this->saveSchemaPageContent( $wikiPage, [
			'schemaText' => 'schema text',
			'id' => $id,
		] );

		$article = Article::newFromTitle(
			Title::newFromDBkey( 'EntitySchema:' . $id ),
			$context
		);
		$article->getParserOutput()->setExtensionData( 'entityschema-meta-tags', null );
		$action = new ViewEntitySchemaAction( $article, $context );

		$action->show();
		// the main assertion is that nothing crashed (like in T385272)
		$this->assertStringContainsString( $id, $action->getOutput()->getHTMLTitle() );
	}

}
