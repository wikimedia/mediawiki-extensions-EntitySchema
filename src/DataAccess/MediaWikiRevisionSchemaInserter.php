<?php

namespace EntitySchema\DataAccess;

use CommentStoreComment;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use Language;
use MediaWiki\Revision\SlotRecord;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaInserter implements SchemaInserter {
	public const AUTOCOMMENT_NEWSCHEMA = 'entityschema-summary-newschema-nolabel';

	/** @var MediaWikiPageUpdaterFactory */
	private $pageUpdaterFactory;
	/** @var IdGenerator */
	private $idGenerator;
	/** @var WatchlistUpdater */
	private $watchListUpdater;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IdGenerator $idGenerator
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
			new EntitySchemaContent( $persistentRepresentation )
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
					'key' => 'entityschema-summary-newschema-nolabel',
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
