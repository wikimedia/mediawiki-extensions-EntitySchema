<?php

namespace Wikibase\Schema\DataAccess;

use CommentStoreComment;
use InvalidArgumentException;
use Language;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use RuntimeException;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\SchemaConverter\PersistenceSchemaData;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaUpdater implements SchemaUpdater {

	const AUTOCOMMENT_UPDATED_SCHEMATEXT = 'wikibaseschema-summary-update-schema-text';
	const AUTOCOMMENT_UPDATED_NAMEBADGE = 'wikibaseschema-summary-update-schema-namebadge';
	const AUTOCOMMENT_UPDATED_LABEL = 'wikibaseschema-summary-update-schema-label';
	const AUTOCOMMENT_UPDATED_DESCRIPTION = 'wikibaseschema-summary-update-schema-description';
	const AUTOCOMMENT_UPDATED_ALIASES = 'wikibaseschema-summary-update-schema-aliases';
	/* public */ const AUTOCOMMENT_RESTORE = 'wikibaseschema-summary-restore';
	/* public */ const AUTOCOMMENT_UNDO = 'wikibaseschema-summary-undo';

	private $pageUpdaterFactory;
	private $watchListUpdater;
	private $editConflictDetector;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		EditConflictDetector $editConflictDetector = null
	) {
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->editConflictDetector = $editConflictDetector;
	}

	private function truncateSchemaTextForCommentData( $schemaText ) {
		$language = Language::factory( 'en' );
		return $language->truncateForVisual( $schemaText, 5000 );
	}

	/**
	 * Update a Schema with new content. This will remove existing schema content.
	 *
	 * @param SchemaId $id
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param string[] $aliasGroups
	 * @param string $schemaText
	 * @param int $baseRevId
	 * @param CommentStoreComment $summary
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
		$baseRevId,
		CommentStoreComment $summary
	) {
		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$parentRevision = $updater->grabParentRevision();
		$this->checkSchemaExists( $parentRevision );
		if ( $updater->hasEditConflict( $baseRevId ) ) {
			throw new EditConflict();
		}

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
			$summary,
			EDIT_UPDATE | EDIT_INTERNAL
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
		if ( $this->editConflictDetector->isNameBadgeEditConflict(
			$parentRevision,
			$baseRevId,
			$langCode
		) ) {
			throw new EditConflict();
		}
		/** @var WikibaseSchemaContent $content */
		$content = $parentRevision->getContent( SlotRecord::MAIN );

		$converter = new SchemaConverter();
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schemaData = $converter->getPersistenceSchemaData( $content->getText() );
		$autoComment = $this->getUpdateNameBadgeAutocomment(
			$schemaData,
			$langCode,
			$label,
			$description,
			$aliases
		);

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

		$updater->saveRevision( $autoComment, EDIT_UPDATE | EDIT_INTERNAL );
		if ( !$updater->wasSuccessful() ) {
			throw new RuntimeException( 'The revision could not be saved' );
		}

		$this->watchListUpdater->optionallyWatchEditedSchema( $id );
	}

	private function getUpdateNameBadgeAutocomment(
		PersistenceSchemaData $schemaData,
		$langCode,
		$label,
		$description,
		array $aliases
	): CommentStoreComment {

		$label = SchemaCleaner::trimWhitespaceAndControlChars( $label );
		$description = SchemaCleaner::trimWhitespaceAndControlChars( $description );
		$aliases = SchemaCleaner::cleanupArrayOfStrings( $aliases );
		$language = Language::factory( $langCode );

		$typeOfChange = [];
		if ( ( $schemaData->labels[$langCode] ?? '' ) !== $label ) {
			$typeOfChange[self::AUTOCOMMENT_UPDATED_LABEL] = $label;
		}
		if ( ( $schemaData->descriptions[$langCode] ?? '' ) !== $description ) {
			$typeOfChange[self::AUTOCOMMENT_UPDATED_DESCRIPTION] = $description;
		}
		if ( ( $schemaData->aliases[$langCode] ?? [] ) !== $aliases ) {
			$typeOfChange[self::AUTOCOMMENT_UPDATED_ALIASES] = $language->commaList( $aliases );
		}

		if ( count( $typeOfChange ) === 1 ) { // TODO what if it’s 0?
			$autocommentKey = key( $typeOfChange );
			$autosummary = $typeOfChange[$autocommentKey];
		} else {
			$autocommentKey = self::AUTOCOMMENT_UPDATED_NAMEBADGE;
			$autosummary = '';
		}

		$autocomment = $autocommentKey . ':' . $langCode;

		return CommentStoreComment::newUnsavedComment(
			'/* ' . $autocomment . ' */' . $autosummary,
			[
				'key' => $autocommentKey,
				'language' => $langCode,
				'label' => $label,
				'description' => $description,
				'aliases' => $aliases,
			]
		);
	}

	/**
	 * @param SchemaId $id
	 * @param string $schemaText
	 * @param int $baseRevId
	 * @param string|null $userSummary
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws EditConflict if another revision has been saved after $baseRevId
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function updateSchemaText(
		SchemaId $id,
		$schemaText,
		$baseRevId,
		$userSummary = null
	) {
		if ( !is_string( $schemaText ) ) {
			throw new InvalidArgumentException( 'schema text must be a string' );
		}

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$parentRevision = $updater->grabParentRevision();
		$this->checkSchemaExists( $parentRevision );
		if ( $this->editConflictDetector->isSchemaTextEditConflict(
			$parentRevision,
			$baseRevId
		) ) {
			throw new EditConflict();
		}

		/** @var WikibaseSchemaContent $content */
		$content = $parentRevision->getContent( SlotRecord::MAIN );
		$converter = new SchemaConverter();
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schemaData = $converter->getPersistenceSchemaData( $content->getText() );
		$schemaData->schemaText = $schemaText;

		$persistentRepresentation = SchemaEncoder::getPersistentRepresentation(
			$id,
			$schemaData->labels,
			$schemaData->descriptions,
			$schemaData->aliases,
			$schemaData->schemaText
		);

		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent( $persistentRepresentation )
		);

		$commentText = '/* ' . self::AUTOCOMMENT_UPDATED_SCHEMATEXT . ' */' . $userSummary;
		$updater->saveRevision(
				CommentStoreComment::newUnsavedComment(
				$commentText,
				[
					'key' => self::AUTOCOMMENT_UPDATED_SCHEMATEXT,
					'userSummary' => $userSummary,
					'schemaText_truncated' => $this->truncateSchemaTextForCommentData(
						$converter->getSchemaText( $persistentRepresentation )
					),
				]
			),
			EDIT_UPDATE | EDIT_INTERNAL
		);
		if ( !$updater->wasSuccessful() ) {
			throw new RuntimeException( 'The revision could not be saved' );
		}

		$this->watchListUpdater->optionallyWatchEditedSchema( $id );
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
