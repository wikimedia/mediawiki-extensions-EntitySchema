<?php

namespace Wikibase\Schema\DataAccess;

use CommentStoreComment;
use MediaWiki\Revision\SlotRecord;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\Domain\Storage\IdGenerator;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaWriter implements SchemaWriter {

	private $pageUpdaterFactory;
	private $idGenerator;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		IdGenerator $idGenerator
	) {
		$this->idGenerator = $idGenerator;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
	}

	/**
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaContent
	 *
	 * @return SchemaId id of the inserted Schema
	 */
	public function insertSchema(
		$language,
		$label = '',
		$description = '',
		array $aliases = [],
		$schemaContent = ''
	): SchemaId {
		$id = new SchemaId( 'O' . $this->idGenerator->getNewId() );

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );

		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent(
				json_encode(
					[
						'id' => $id->getId(),
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
					]
				)
			)
		);
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'FIXME: there should be a translatable comment here.'
			)
		);

		return $id;
	}

}
