<?php

namespace Wikibase\Schema\Tests\MediaWiki;

use MediaWiki\MediaWikiServices;
use RequestContext;
use Wikibase\Schema\Domain\Model\Schema;
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
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			RequestContext::getMain()->getUser()
		);
		$testSchema = new Schema();
		$testLabel = uniqid( 'testLabel_' . __FUNCTION__ . '_' );
		$testSchema->setLabel( 'en', $testLabel );

		$actualId = $repository->storeSchema( $testSchema );

		$this->assertRegExp( '/^O\d+$/', $actualId );

		$textOfNewestPage = $this->getLastCreatedPageText();
		$this->assertContains( $testLabel, $textOfNewestPage );
	}

	protected function getLastCreatedPageText() {
		$row = $this->db->select(
			[ 'page', 'revision', 'text' ],
			[
				'page_namespace',
				'page_title',
				'old_text',
			],
			[],
			__METHOD__,
			[
				'ORDER BY' => [
					'page_id DESC',
				],
				'LIMIT' => 1,
			],
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
		$data = $row->fetchRow();

		return $data['old_text'];
	}

}
