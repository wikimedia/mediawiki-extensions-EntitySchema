<?php

namespace Wikibase\Schema\Tests\DataAccess;

use MediaWiki\Storage\PageUpdater;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\Domain\Storage\IdGenerator;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * @covers \Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter
 * @license GPL-2.0-or-later
 */
class MediawikiRevisionSchemaWriterTest extends \PHPUnit_Framework_TestCase {
	use \PHPUnit4And6Compat;

	public function testInsertSchema() {
		$language = 'en';
		$label = 'test_label';
		$description = 'test_description';
		$aliases = [ 'test_alias1', 'testalias_2' ];
		$schemaContent = '#some fake schema {}';
		$id = 'O123';

		$expectedContent = new WikibaseSchemaContent(
			json_encode(
				[
					'id' => $id,
					'serializationVersion' => '2.0',
					'labels' => [
						$language => $label
					],
					'descriptions' => [
						$language => $description
					],
					'aliases' => [
						$language => $aliases
					],
					'schema' => $schemaContent,
					'type' => 'ShExC',
				]
			)
		);

		$pageUpdater = $this->createMock( PageUpdater::class );
		$pageUpdater->expects( $this->once() )
			->method( 'setContent' )
			->with(
				'main',
				$this->equalTo( $expectedContent )
			);

		$pageUpdaterFactory = $this->createMock( MediaWikiPageUpdaterFactory::class );
		$pageUpdaterFactory->method( 'getPageUpdater' )->willReturn( $pageUpdater );

		$idGenerator = $this->createMock( IdGenerator::class );
		$idGenerator->method( 'getNewId' )->willReturn( '123' );

		$writer = new MediaWikiRevisionSchemaWriter( $pageUpdaterFactory, $idGenerator );

		$writer->insertSchema( $language,
			$label,
			$description,
			$aliases,
			$schemaContent
		);
	}

}
