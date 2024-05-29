<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Rdf;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\Rdf\EntitySchemaRdfBuilder;
use MediaWikiUnitTestCase;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use Wikimedia\Purtle\RdfWriterFactory;

/**
 * @covers \EntitySchema\Wikibase\Rdf\EntitySchemaRdfBuilder
 * @license GPL-2.0-or-later
 */
class EntitySchemaRdfBuilderTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( WikibaseRepo::class ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	/**
	 * @dataProvider addValueProvider
	 */
	public function testAddValue( string $expected, bool $isPrefixKnown ) {
		$namespaces = [ 'propertyValueNamespace' => 'http://propertyValueNamespace/' ];
		if ( $isPrefixKnown ) {
			$namespaces['mywd'] = 'http://some.wiki/entity/';
		}

		$rdfVocabulary = $this->createMock( RdfVocabulary::class );
		$rdfVocabulary->expects( $this->once() )
			->method( 'getNamespaces' )
			->willReturn( $namespaces );

		$entitySchemaRdfBuilder = new EntitySchemaRdfBuilder(
			$rdfVocabulary,
			'http://some.wiki/entity/'
		);
		$rdfWriter = ( new RdfWriterFactory() )->getWriter( 'turtle' );
		foreach ( $namespaces as $prefix => $namespace ) {
			$rdfWriter->prefix( $prefix, $namespace );
		}
		$rdfWriter->start();

		// We don't care about the output so far
		$rdfWriter->drain();

		$rdfWriter->about( 'something' );

		$entitySchemaRdfBuilder->addValue(
			$rdfWriter,
			'propertyValueNamespace',
			'propertyValueLName',
			'ignored',
			'ignored',
			new PropertyValueSnak(
				new NumericPropertyId( 'P12' ),
				new EntitySchemaValue( new EntitySchemaId( 'E1234' ) )
			),
			'ignored'
		);

		$this->assertSame( $expected, trim( $rdfWriter->drain() ) );
	}

	public static function addValueProvider() {
		yield 'Known prefix' => [
			'expected' => '<something> propertyValueNamespace:propertyValueLName mywd:E1234 .',
			'isPrefixKnown' => true,
		];
		yield 'Unknown prefix, full URI used' => [
			'expected' => '<something> propertyValueNamespace:propertyValueLName <http://some.wiki/entity/E1234> .',
			'isPrefixKnown' => false,
		];
	}

}
