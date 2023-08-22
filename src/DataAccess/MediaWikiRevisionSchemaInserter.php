<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use CommentStoreComment;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Revision\SlotRecord;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaInserter implements SchemaInserter {
	public const AUTOCOMMENT_NEWSCHEMA = 'entityschema-summary-newschema-nolabel';

	private MediaWikiPageUpdaterFactory $pageUpdaterFactory;
	private IdGenerator $idGenerator;
	private WatchlistUpdater $watchListUpdater;
	private LanguageFactory $languageFactory;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IdGenerator $idGenerator,
		LanguageFactory $languageFactory
	) {
		$this->idGenerator = $idGenerator;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->languageFactory = $languageFactory;
	}

	/**
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaText
	 *
	 * @return EntitySchemaId id of the inserted Schema
	 */
	public function insertSchema(
		string $language,
		string $label = '',
		string $description = '',
		array $aliases = [],
		string $schemaText = ''
	): EntitySchemaId {
		$id = new EntitySchemaId( 'E' . $this->idGenerator->getNewId() );
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

	private function truncateSchemaTextForCommentData( string $schemaText ): string {
		$language = $this->languageFactory->getLanguage( 'en' );
		return $language->truncateForVisual( $schemaText, 5000 );
	}

}
