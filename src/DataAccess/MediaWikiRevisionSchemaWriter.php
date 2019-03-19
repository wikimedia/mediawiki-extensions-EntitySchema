<?php

namespace Wikibase\Schema\DataAccess;

use CommentStoreComment;
use InvalidArgumentException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use Message;
use MessageLocalizer;
use RuntimeException;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\Domain\Storage\IdGenerator;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaWriter implements SchemaWriter {

	private $pageUpdaterFactory;
	private $idGenerator;
	private $msgLocalizer;
	private $watchListUpdater;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		MessageLocalizer $msgLocalizer,
		WatchlistUpdater $watchListUpdater,
		IdGenerator $idGenerator = null
	) {
		$this->idGenerator = $idGenerator;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->msgLocalizer = $msgLocalizer;
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
		$id = new SchemaId( 'O' . $this->idGenerator->getNewId() );

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent(
				SchemaEncoder::getPersistentRepresentation(
					$id,
					[ $language => $label ],
					[ $language => $description ],
					[ $language => $aliases ],
					$schemaText
				)
			)
		);

		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				$this->msgLocalizer->msg(
					'wikibaseschema-summary-newschema'
				)->plaintextParams( $label )
			)
		);

		$this->watchListUpdater->optionallyWatchNewSchema( $id );

		return $id;
	}

	/**
	 * Update a Schema with new content. This will remove existing schema content.
	 *
	 * @param SchemaId $id
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param string[] $aliasGroups
	 * @param string $schemaText
	 * @param Message|null $message
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function overwriteWholeSchema(
		SchemaId $id,
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText,
		Message $message = null
	) {
		if ( $message === null ) {
			$message = $this->msgLocalizer->msg( 'wikibaseschema-summary-update' );
		}

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$this->checkSchemaExists( $updater->grabParentRevision() );

		// TODO check $updater->hasEditConflict()! (T217338)

		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent(
				SchemaEncoder::getPersistentRepresentation(
					$id,
					$labels,
					$descriptions,
					$aliasGroups,
					$schemaText
				)
			)
		);

		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( $message )
		);
		if ( !$updater->wasSuccessful() ) {
			throw new RuntimeException( 'The revision could not be saved' );
		}

		$this->watchListUpdater->optionallyWatchEditedSchema( $id );
	}

	public function updateSchemaNameBadge(
		SchemaId $id,
		$langCode,
		$label,
		$description,
		array $aliases,
		$baseRevId
	) {

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$parentRevision = $updater->grabParentRevision();
		$this->checkSchemaExists( $parentRevision );
		$this->checkEditConflict( $parentRevision, $baseRevId );
		/** @var WikibaseSchemaContent $content */
		$content = $parentRevision->getContent( SlotRecord::MAIN );

		$converter = new SchemaConverter();
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schemaData = $converter->getPersistenceSchemaData( $content->getText() );
		$schemaData->labels[$langCode] = $label;
		$schemaData->descriptions[$langCode] = $description;
		$schemaData->aliases[$langCode] = $aliases;

		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent(
				SchemaEncoder::getPersistentRepresentation(
					$id,
					$schemaData->labels,
					$schemaData->descriptions,
					$schemaData->aliases,
					$schemaData->schemaText
				)
			)
		);

		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
			// TODO specific message (T214887)
				$this->msgLocalizer->msg( 'wikibaseschema-summary-update' )
			),
			EDIT_UPDATE | EDIT_INTERNAL
		);
		if ( !$updater->wasSuccessful() ) {
			throw new RuntimeException( 'The revision could not be saved' );
		}

		$this->watchListUpdater->optionallyWatchEditedSchema( $id );
	}

	/**
	 * @param SchemaId $id
	 * @param string $schemaText
	 * @param int $baseRevId
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws EditConflict if another revision has been saved after $baseRevId
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function updateSchemaText( SchemaId $id, $schemaText, $baseRevId ) {
		if ( !is_string( $schemaText ) ) {
			throw new InvalidArgumentException( 'schema text must be a string' );
		}

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$parentRevision = $updater->grabParentRevision();
		$this->checkSchemaExists( $parentRevision );
		$this->checkEditConflict( $parentRevision, $baseRevId );

		/** @var WikibaseSchemaContent $content */
		$content = $parentRevision->getContent( SlotRecord::MAIN );
		$converter = new SchemaConverter();
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schemaData = $converter->getPersistenceSchemaData( $content->getText() );
		$schemaData->schemaText = $schemaText;

		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent(
				SchemaEncoder::getPersistentRepresentation(
					$id,
					$schemaData->labels,
					$schemaData->descriptions,
					$schemaData->aliases,
					$schemaData->schemaText
				)
			)
		);

		$updater->saveRevision( CommentStoreComment::newUnsavedComment(
			// TODO specific message (T214887)
			$this->msgLocalizer->msg( 'wikibaseschema-summary-update' )
		), EDIT_UPDATE | EDIT_INTERNAL );

		$this->watchListUpdater->optionallyWatchEditedSchema( $id );
	}

	/**
	 * @param RevisionRecord $parentRevisionRecord
	 * @param int $baseRevId
	 *
	 * @throws EditConflict
	 */
	private function checkEditConflict( RevisionRecord $parentRevisionRecord, $baseRevId ) {
		if ( $parentRevisionRecord->getId() !== $baseRevId ) {
			throw new EditConflict();
		}
	}

	/**
	 * @param RevisionRecord|null $parentRevision if null, an exception will be thrown
	 *
	 * @throws RuntimeException
	 */
	private function checkSchemaExists( RevisionRecord $parentRevision = null ) {
		if ( $parentRevision === null ) {
			throw new RuntimeException( 'Schema to update does not exist' );
		}
	}

}
