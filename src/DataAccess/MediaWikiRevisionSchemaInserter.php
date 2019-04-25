<?php

namespace Wikibase\Schema\DataAccess;

use CommentStoreComment;
use Language;
use MediaWiki\Revision\SlotRecord;
use RuntimeException;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\Domain\Storage\IdGenerator;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaInserter implements SchemaInserter {
	const AUTOCOMMENT_NEWSCHEMA = 'wikibaseschema-summary-newschema-nolabel';

	private $pageUpdaterFactory;
	private $idGenerator;
	private $watchListUpdater;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IdGenerator $idGenerator = null
	) {
		$this->idGenerator = $idGenerator;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
	}

	/**
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaText
	 *
	 * @return SchemaId id of the inserted Schema
	 */
	public function insertSchema(
		$language,
		$label = '',
		$description = '',
		array $aliases = [],
		$schemaText = ''
	): SchemaId {
		$id = new SchemaId( 'E' . $this->idGenerator->getNewId() );
		$persistentRepresentation = SchemaEncoder::getPersistentRepresentation(
			$id,
			[ $language => $label ],
			[ $language => $description ],
			[ $language => $aliases ],
			$schemaText
		);

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent( $persistentRepresentation )
		);

		$schemaConverter = new SchemaConverter();
		$schemaData = $schemaConverter->getMonolingualNameBadgeData(
			$persistentRepresentation,
			$language
		);
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'/* ' . self::AUTOCOMMENT_NEWSCHEMA . ' */' . $schemaData->label,
				[
					'key' => 'wikibaseschema-summary-newschema-nolabel',
					'language' => $language,
					'label' => $schemaData->label,
					'description' => $schemaData->description,
					'aliases' => $schemaData->aliases,
					'schemaText_truncated' => $this->truncateSchemaTextForCommentData(
						$schemaConverter->getSchemaText( $persistentRepresentation )
					),
				]
			),
			EDIT_NEW | EDIT_INTERNAL
		);

		if ( !$updater->wasSuccessful() ) {
			throw new RuntimeException( 'The revision could not be saved' );
		}

		$this->watchListUpdater->optionallyWatchNewSchema( $id );

		return $id;
	}

	private function truncateSchemaTextForCommentData( $schemaText ) {
		$language = Language::factory( 'en' );
		return $language->truncateForVisual( $schemaText, 5000 );
	}

}
