<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\API;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Repo\WikibaseRepo;
use WikiPage;

/**
 * @covers \Wikibase\Repo\Api\FormatSnakValue
 *
 * @group Wikibase
 * @group WikibaseAPI
 * @group Database
 * @group medium
 *
 * @license GPL-2.0-or-later
 */
class FormatSnakValueTest extends ApiTestCase {

	protected string $testingEntitySchemaId;
	protected Property $testingProperty;

	public function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}

		$this->overrideConfigValue( 'EntitySchemaEnableDatatype', true );
	}

	public static function provideApiRequest() {
		return [
			[ static function ( self $test ) {
				$idString = $test->testingEntitySchemaId;

				return [
					new EntitySchemaId( $idString ),
					null,
					SnakFormatter::FORMAT_HTML,
					null,
					$test->testingProperty->getId(),
					'/^<a (title="[^"]*' . $idString . '" href="[^"]+' . $idString .
					'"|href="[^"]+' . $idString . '" title="[^"]*' . $idString . '"' .
					') lang="en">Human<\/a>$/',
				];
			} ],
			[ static function ( self $test ) {
				$idString = $test->testingEntitySchemaId;

				return [
					new EntitySchemaId( $idString ),
					null,
					SnakFormatter::FORMAT_HTML,
					[ 'lang' => 'de-ch' ], // fallback
					$test->testingProperty->getId(),
					'/^<a (title="[^"]*' . $idString . '" href="[^"]+' . $idString .
					'"|href="[^"]+' . $idString . '" title="[^"]*' . $idString . '"' .
					') lang="en">Human<\/a>' . "\u{00A0}" .
					'<sup class="wb-language-fallback-indicator">[^<>]+<\/sup>$/',
				];
			} ],
			[ static function ( self $test ) {
				$idString = $test->testingEntitySchemaId;

				return [
					new EntitySchemaId( $idString ),
					'entity-schema',
					SnakFormatter::FORMAT_HTML,
					null,
					null,
					'/^<a (title="[^"]*' . $idString . '" href="[^"]+' . $idString .
					'"|href="[^"]+' . $idString . '" title="[^"]*' . $idString . '"' .
					') lang="en">Human<\/a>$/',
				];
			} ],
		];
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ): RevisionRecord {
		$content['serializationVersion'] = '3.0';
		$updater = $page->newPageUpdater( self::getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new EntitySchemaContent( json_encode( $content ) ) );
		return $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);
	}

	private function createEntitySchema( $itemId ) {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $itemId );
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$rev1 = $this->saveSchemaPageContent( $page, [
			'id' => $itemId,
			'labels' => [ 'en' => 'Human' ],
		] );
	}

	private function saveEntities() {
		$this->testingEntitySchemaId = 'E457';

		// Set up a Property
		$this->testingProperty = new Property( null, null, 'entity-schema' );

		$store = WikibaseRepo::getEntityStore();

		$this->createEntitySchema( $this->testingEntitySchemaId );
		// Save the property, this will also automatically assign a new ID
		$store->saveEntity( $this->testingProperty, 'testing', $this->getTestUser()->getUser(), EDIT_NEW );
	}

	/**
	 * @dataProvider provideApiRequest
	 */
	public function testApiRequest( $providerCallback ) {
		$this->saveEntities();
		/**
		 * @var EntitySchemaId $value
		 * @var string|null $dataType
		 * @var string $format
		 * @var array $options
		 * @var string $propertyId
		 * @var string $pattern
		 */
		[
			$value,
			$dataType,
			$format,
			$options,
			$propertyId,
			$pattern,
			] = $providerCallback( $this );

		$params = [
			'action' => 'wbformatvalue',
			'generate' => $format,
			'datatype' => $dataType,
			'datavalue' => json_encode(
				[
					'value' => [ 'id' => $value->getId() ],
					'type' => 'wikibase-entityid',
				]
			),
			'property' => $propertyId,
			'options' => $options === null ? null : json_encode( $options ),
		];

		[ $resultArray ] = $this->doApiRequest( $params );

		$this->assertIsArray( $resultArray, 'top level element must be an array' );
		$this->assertArrayHasKey( 'result', $resultArray, 'top level element must have a "result" key' );

		$this->assertMatchesRegularExpression( $pattern, $resultArray['result'] );
	}
}
