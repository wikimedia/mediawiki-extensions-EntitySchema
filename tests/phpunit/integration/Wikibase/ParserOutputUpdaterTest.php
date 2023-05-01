<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase;

use DataValues\StringValue;
use MediaWiki\MediaWikiServices;
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
 * @covers \EntitySchema\Wikibase\Hooks\ParserOutputUpdaterConstructionHandler
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
			->withValue( new StringValue( 'E123' ) )
			->build();
		$item = NewItem::withStatement( $statement )->build();

		$parserOutput = $this->getEntityParserOutputGenerator()->getParserOutput(
			new EntityRevision( $item ),
			/* $generateHtml = */ false
		);

		$pageLinks = $parserOutput->getLinks();
		$this->assertArrayHasKey( NS_ENTITYSCHEMA_JSON, $pageLinks );
		$this->assertSame(
			// 0 means the page does not exist
			[ 'E123' => 0 ],
			$pageLinks[NS_ENTITYSCHEMA_JSON]
		);
	}

	private function getEntityParserOutputGenerator(): EntityParserOutputGenerator {
		return WikibaseRepo::getEntityParserOutputGeneratorFactory()->getEntityParserOutputGenerator(
			MediaWikiServices::getInstance()->getContentLanguage()
		);
	}

}
