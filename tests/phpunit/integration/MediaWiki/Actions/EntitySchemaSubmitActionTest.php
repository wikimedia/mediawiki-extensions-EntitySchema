<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Action;
use Article;
use EntitySchema\MediaWiki\Actions\EntitySchemaSubmitAction;
use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\MediaWiki\Actions\EntitySchemaSubmitAction
 */
final class EntitySchemaSubmitActionTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
	}

	public function testActionName() {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );
		$requestParameters = [ 'action' => 'submit' ];
		$context = RequestContext::newExtraneousContext( $title, $requestParameters );

		$actionName = Action::getActionName( $context );
		$action = Action::factory(
			$actionName,
			Article::newFromTitle( $title, $context ),
			$context
		);

		$this->assertInstanceOf( EntitySchemaSubmitAction::class, $action );
	}

}
