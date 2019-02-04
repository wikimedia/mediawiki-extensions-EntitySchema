<?php

namespace Wikibase\Schema\Tests\MediaWiki;

use RequestContext;
use TitleValue;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\MediaWiki\RevisionSchemaRepository;

/**
 * @group Database
 * @covers \Wikibase\Schema\MediaWiki\RevisionSchemaRepository
 *
 * @license GPL-2.0-or-later
 */
class RevisionSchemaRepositoryTest extends \MediaWikiTestCase {

	/**
	 * todo: add more testcases as parametrized test
	 */
	public function testStoreValidSchema() {
		$repository = new RevisionSchemaRepository(
			RequestContext::getMain()->getUser()
		);
		$testSchema = new Schema();
		$testLabel = uniqid( 'testLabel_' . __FUNCTION__ . '_' );
		$testSchema->setLabel( 'en', $testLabel );
		$testId = new SchemaId( 'O' . rand() );
		$testSchema->setId( $testId );

		$repository->storeSchema( $testSchema );

		$text = $this->getLatestPageText(
			new TitleValue( NS_WBSCHEMA_JSON, $testId->getId() )
		);
		$this->assertContains( $testLabel, $text );
	}

	private function getLatestPageText( TitleValue $title ) {
		return $this->db->selectField(
			[ 'page', 'revision', 'text' ],
			'old_text',
			[
				'page_namespace' => $title->getNamespace(),
				'page_title' => $title->getDBkey(),
			],
			__METHOD__,
			[],
			[
				'revision' => [
					'INNER JOIN',
					'page_latest=rev_id',
				],
				'text' => [
					'INNER JOIN',
					'rev_text_id=old_id',
				],
			]
		);
	}

}
