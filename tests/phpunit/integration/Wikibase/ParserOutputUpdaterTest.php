<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Tests\NewItem;
use Wikibase\DataModel\Tests\NewStatement;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Repo\ParserOutput\EntityParserOutputGenerator;
use Wikibase\Repo\WikibaseRepo;

/**
 * Integration test making sure that entity schema links in Wikibase statements
 * are added as links to the ParserOutput.
 *
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoOnParserOutputUpdaterConstructionHookHandler
 * @covers \EntitySchema\Wikibase\ParserOutputUpdater\EntitySchemaStatementDataUpdater
 *
 * @group Database
 * @license GPL-2.0-or-later
 */
class ParserOutputUpdaterTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
	}

	public function testRecordsEntitySchemaLinks(): void {
		$property = new Property( null, null, 'entity-schema' );
		WikibaseRepo::getEntityStore()->saveEntity(
			$property,
			'ParserOutputUpdaterTest',
			$this->getTestUser()->getUser(),
			EDIT_NEW
		);

		$statement = NewStatement::forProperty( $property->getId() )
			->withValue( new EntitySchemaValue( new EntitySchemaId( 'E123' ) ) )
			->build();
		$item = NewItem::withStatement( $statement )->build();

		$parserOutput = $this->getEntityParserOutputGenerator()->getParserOutput(
			new EntityRevision( $item ),
			/* $generateHtml = */ false
		);

		$pageLinks = array_map(
			static fn ( $item ) => ( [ 'link' => $item['link']->getDBkey() ] + $item ),
			$parserOutput->getLinkList( ParserOutputLinkTypes::LOCAL, NS_ENTITYSCHEMA_JSON )
		);
		$this->assertSame(
			[
				[
					'link' => 'E123',
					// 0 means the page does not exist
					'pageid' => 0,
				],
			],
			$pageLinks
		);
	}

	private function getEntityParserOutputGenerator(): EntityParserOutputGenerator {
		return WikibaseRepo::getEntityParserOutputGeneratorFactory()->getEntityParserOutputGenerator(
			$this->getServiceContainer()->getContentLanguage()
		);
	}

}
