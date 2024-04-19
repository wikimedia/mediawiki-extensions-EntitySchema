<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase\Search;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelper;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Term\Term;
use Wikibase\Repo\Api\ConceptUriSearchHelper;
use WikiPage;

/**
 * @covers \EntitySchema\Wikibase\Search\EntitySchemaSearchHelper
 * @group Database
 * @license GPL-2.0-or-later
 */
class EntitySchemaSearchHelperTest extends MediaWikiIntegrationTestCase {

	public function testGetRankedSearchResults(): void {
		$id = 'E1';
		$services = $this->getServiceContainer();
		$wikiPageFactory = $services->getWikiPageFactory();
		$page = $wikiPageFactory->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id ) );
		$this->saveSchemaPageContent( $page, [
			'labels' => [ 'en' => 'human' ],
			'descriptions' => [ 'en' => 'schema for humans' ],
		] );
		$helper = new EntitySchemaSearchHelper(
			$services->getTitleFactory(),
			$wikiPageFactory,
			'https://wiki.example/entity/',
			EntitySchemaServices::getDescriptionLookup( $services ),
			EntitySchemaServices::getLabelLookup( $services ),
			'en'
		);

		$results = $helper->getRankedSearchResults(
			$id,
			'en',
			EntitySchemaSearchHelper::ENTITY_TYPE,
			10,
			true,
			null
		);

		$this->assertCount( 1, $results );
		$result = $results[0];
		$this->assertTerm( 'qid', $id, $result->getMatchedTerm() );
		$this->assertSame( 'entityId', $result->getMatchedTermType() );
		$this->assertNull( $result->getEntityId() );
		$this->assertTerm( 'en', 'human', $result->getDisplayLabel() );
		$this->assertTerm( 'en', 'schema for humans', $result->getDisplayDescription() );
		$this->assertSame( [
			'id' => $id,
			'title' => "EntitySchema:$id",
			'pageid' => $page->getId(),
			'url' => $page->getTitle()->getFullURL(),
			ConceptUriSearchHelper::CONCEPTURI_META_DATA_KEY => "https://wiki.example/entity/$id",
		], $result->getMetaData() );
	}

	public function testNoResults_otherEntityType(): void {
		$helper = new EntitySchemaSearchHelper(
			$this->createNoOpMock( TitleFactory::class ),
			$this->createNoOpMock( WikiPageFactory::class ),
			'https://wiki.example/entity/',
			$this->createNoOpMock( DescriptionLookup::class ),
			$this->createNoOpMock( LabelLookup::class ),
			'en'
		);

		$results = $helper->getRankedSearchResults(
			'E1',
			'en',
			'item',
			10,
			true,
			null
		);

		$this->assertSame( [], $results );
	}

	public function testNoResults_notEntitySchemaId(): void {
		$helper = new EntitySchemaSearchHelper(
			$this->createNoOpMock( TitleFactory::class ),
			$this->createNoOpMock( WikiPageFactory::class ),
			'https://wiki.example/entity/',
			$this->createNoOpMock( DescriptionLookup::class ),
			$this->createNoOpMock( LabelLookup::class ),
			'en'
		);

		$results = $helper->getRankedSearchResults(
			'Q1',
			'en',
			EntitySchemaSearchHelper::ENTITY_TYPE,
			10,
			true,
			null
		);

		$this->assertSame( [], $results );
	}

	public function testNoResults_noTitle(): void {
		$titleFactory = $this->createConfiguredMock( TitleFactory::class,
			[ 'newFromText' => null ] ); // can’t really happen but test it anyway
		$helper = new EntitySchemaSearchHelper(
			$titleFactory,
			$this->createNoOpMock( WikiPageFactory::class ),
			'https://wiki.example/entity/',
			$this->createNoOpMock( DescriptionLookup::class ),
			$this->createNoOpMock( LabelLookup::class ),
			'en'
		);

		$results = $helper->getRankedSearchResults(
			'E1',
			'en',
			EntitySchemaSearchHelper::ENTITY_TYPE,
			10,
			true,
			null
		);

		$this->assertSame( [], $results );
	}

	public function testNoResults_noWikiPage(): void {
		$wikiPage = $this->createConfiguredMock( WikiPage::class,
			[ 'exists' => false ] );
		$wikiPageFactory = $this->createConfiguredMock( WikiPageFactory::class,
			[ 'newFromTitle' => $wikiPage ] );
		$helper = new EntitySchemaSearchHelper(
			$this->getServiceContainer()->getTitleFactory(),
			$wikiPageFactory,
			'https://wiki.example/entity/',
			$this->createNoOpMock( DescriptionLookup::class ),
			$this->createNoOpMock( LabelLookup::class ),
			'en'
		);

		$results = $helper->getRankedSearchResults(
			'E1000000000',
			'en',
			EntitySchemaSearchHelper::ENTITY_TYPE,
			10,
			true,
			null
		);

		$this->assertSame( [], $results );
	}

	private function assertTerm( string $expectedLanguage, string $expectedText, Term $term ): void {
		$this->assertSame( $expectedLanguage, $term->getLanguageCode() );
		$this->assertSame( $expectedText, $term->getText() );
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ): RevisionRecord {
		$content['serializationVersion'] = '3.0';
		$updater = $page->newPageUpdater( $this->getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new EntitySchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment( static::class )
		);

		return $firstRevRecord;
	}

}
