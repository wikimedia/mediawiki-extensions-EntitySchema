<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\DataAccess\FullViewSchemaDataLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use EntitySchema\Services\Converter\NameBadge;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers EntitySchema\DataAccess\FullViewSchemaDataLookup
 * @license GPL-2.0-or-later
 * @group Database
 */
class FullViewSchemaDataLookupTest extends MediaWikiIntegrationTestCase {
	use EntitySchemaIntegrationTestCaseTrait;

	public function testGetFullViewSchemaData(): void {
		$services = $this->getServiceContainer();
		$titleFactory = $services->getTitleFactory();
		$title = $titleFactory->makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );
		$wikiPageFactory = $services->getWikiPageFactory();
		$wikiPage = $wikiPageFactory->newFromTitle( $title );
		$this->saveSchemaPageContent( $wikiPage, [
			'labels' => [ 'en' => 'English label' ],
		] );
		$lookup = new FullViewSchemaDataLookup( $titleFactory, $wikiPageFactory );

		$data = $lookup->getFullViewSchemaData( new EntitySchemaId( $title->getText() ) );

		$expected = new FullViewEntitySchemaData( [
			'en' => new NameBadge( 'English label', '', [] ),
		], '' );
		$this->assertEquals( $expected, $data );
	}

	/** @dataProvider provideWithTitleFlags */
	public function testGetFullViewSchemaDataForTitle_withTitle( bool $withTitle ): void {
		$services = $this->getServiceContainer();
		$titleFactory = $services->getTitleFactory();
		$title = $titleFactory->makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );
		$wikiPageFactory = $services->getWikiPageFactory();
		$wikiPage = $wikiPageFactory->newFromTitle( $title );
		$this->saveSchemaPageContent( $wikiPage, [
			'labels' => [ 'en' => 'English label' ],
		] );
		$lookup = new FullViewSchemaDataLookup( $titleFactory, $wikiPageFactory );

		$data = $lookup->getFullViewSchemaDataForTitle( $withTitle ? $title : $wikiPage );

		$expected = new FullViewEntitySchemaData( [
			'en' => new NameBadge( 'English label', '', [] ),
		], '' );
		$this->assertEquals( $expected, $data );
	}

	public static function provideWithTitleFlags(): iterable {
		yield 'called with Title' => [ true ];
		yield 'called with WikiPage' => [ false ];
	}

}
