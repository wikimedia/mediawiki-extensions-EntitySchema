<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase\Search;

use EntitySchema\Wikibase\Search\ESElasticTermResult;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Lib\TermLanguageFallbackChain;
use Wikibase\Repo\Api\ConceptUriSearchHelper;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \EntitySchema\Wikibase\Search\ESElasticTermResult
 * @license GPL-2.0-or-later
 */
class ESElasticTermResultTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );
	}

	public function testGetTermSearchResult_invalidTitle(): void {
		$result = new ESElasticTermResult(
			$this->createNoOpMock( TitleFactory::class ),
			'',
			[ 'en' ],
			$this->createNoOpMock( TermLanguageFallbackChain::class )
		);
		$sourceData = [ 'title' => 'not a valid EntitySchema ID' ];
		$matchedTerm = $this->createMock( Term::class );

		$termSearchResult = TestingAccessWrapper::newFromObject( $result )
			->getTermSearchResult( $sourceData, $matchedTerm, 'label', null, null );

		$this->assertNull( $termSearchResult );
	}

	public function testGetTermSearchResult_missingTitle(): void {
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->expects( $this->once() )
			->method( 'newFromText' )
			->with( 'E12345', NS_ENTITYSCHEMA_JSON )
			->willReturn( null );
		$result = new ESElasticTermResult(
			$titleFactory,
			'',
			[ 'en' ],
			$this->createNoOpMock( TermLanguageFallbackChain::class )
		);
		$sourceData = [ 'title' => 'E12345' ];
		$matchedTerm = $this->createMock( Term::class );

		$termSearchResult = TestingAccessWrapper::newFromObject( $result )
			->getTermSearchResult( $sourceData, $matchedTerm, 'label', null, null );

		$this->assertNull( $termSearchResult );
	}

	public function testGetTermSearchResult_existingTitle(): void {
		$title = $this->createConfiguredMock( Title::class, [
			'getFullText' => 'EntitySchema:E123',
			'getId' => 123,
			'getFullURL' => 'https://entityschema.test/wiki/EntitySchema:E123',
		] );
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->expects( $this->once() )
			->method( 'newFromText' )
			->with( 'E123', NS_ENTITYSCHEMA_JSON )
			->willReturn( $title );
		$result = new ESElasticTermResult(
			$titleFactory,
			'https://entityschema.test/entity/',
			[ 'en' ],
			$this->createNoOpMock( TermLanguageFallbackChain::class )
		);
		$sourceData = [ 'title' => 'E123' ];
		$matchedTerm = $this->createMock( Term::class );
		$matchedTermType = 'label';
		$displayLabel = $this->createMock( Term::class );
		$displayDescription = $this->createMock( Term::class );

		$termSearchResult = TestingAccessWrapper::newFromObject( $result )
			->getTermSearchResult(
				$sourceData,
				$matchedTerm,
				$matchedTermType,
				$displayLabel,
				$displayDescription
			);

		$this->assertInstanceOf( TermSearchResult::class, $termSearchResult );
		$this->assertSame( $matchedTerm, $termSearchResult->getMatchedTerm() );
		$this->assertSame( $matchedTermType, $termSearchResult->getMatchedTermType() );
		$this->assertNull( $termSearchResult->getEntityId() );
		$this->assertSame( $displayLabel, $termSearchResult->getDisplayLabel() );
		$this->assertSame( $displayDescription, $termSearchResult->getDisplayDescription() );
		$this->assertSame( [
			'id' => 'E123',
			'title' => 'EntitySchema:E123',
			'pageid' => 123,
			'url' => 'https://entityschema.test/wiki/EntitySchema:E123',
			ConceptUriSearchHelper::CONCEPTURI_META_DATA_KEY => 'https://entityschema.test/entity/E123',
		], $termSearchResult->getMetaData() );
	}

}
