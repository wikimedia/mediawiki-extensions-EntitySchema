<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use CommentStoreComment;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\FullArrayEntitySchemaData;
use InvalidArgumentException;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionEntitySchemaUpdater implements EntitySchemaUpdater {

	public const AUTOCOMMENT_UPDATED_SCHEMATEXT = 'entityschema-summary-update-schema-text';
	public const AUTOCOMMENT_UPDATED_NAMEBADGE = 'entityschema-summary-update-schema-namebadge';
	public const AUTOCOMMENT_UPDATED_LABEL = 'entityschema-summary-update-schema-label';
	public const AUTOCOMMENT_UPDATED_DESCRIPTION = 'entityschema-summary-update-schema-description';
	public const AUTOCOMMENT_UPDATED_ALIASES = 'entityschema-summary-update-schema-aliases';
	public const AUTOCOMMENT_RESTORE = 'entityschema-summary-restore';
	public const AUTOCOMMENT_UNDO = 'entityschema-summary-undo';

	private MediaWikiPageUpdaterFactory $pageUpdaterFactory;
	private WatchlistUpdater $watchListUpdater;
	private RevisionLookup $revisionLookup;
	private LanguageFactory $languageFactory;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		RevisionLookup $revisionLookup,
		LanguageFactory $languageFactory
	) {
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->revisionLookup = $revisionLookup;
		$this->languageFactory = $languageFactory;
	}

	private function truncateSchemaTextForCommentData( string $schemaText ): string {
		$language = $this->languageFactory->getLanguage( 'en' );
		return $language->truncateForVisual( $schemaText, 5000 );
	}

	/**
	 * Update a Schema with new content. This will remove existing schema content.
	 *
	 * @param EntitySchemaId $id
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param string[][] $aliasGroups
	 * @param string $schemaText
	 * @param int $baseRevId
	 * @param CommentStoreComment $summary
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function overwriteWholeSchema(
		EntitySchemaId $id,
		array $labels,
		array $descriptions,
		array $aliasGroups,
		string $schemaText,
		int $baseRevId,
		CommentStoreComment $summary
	): void {
		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$this->checkSchemaExists( $updater->grabParentRevision() );
		if ( $updater->hasEditConflict( $baseRevId ) ) {
			throw new EditConflict();
		}

		$updater->setContent(
			SlotRecord::MAIN,
			new EntitySchemaContent(
				EntitySchemaEncoder::getPersistentRepresentation(
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
		EntitySchemaId $id,
		string $langCode,
		string $label,
		string $description,
		array $aliases,
		int $baseRevId
	): void {

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$parentRevision = $updater->grabParentRevision();
		$this->checkSchemaExists( $parentRevision );

		$baseRevision = $this->revisionLookup->getRevisionById( $baseRevId );

		$updateGuard = new EntitySchemaUpdateGuard();
		$schemaData = $updateGuard->guardSchemaUpdate(
			$baseRevision,
			$parentRevision,
			static function ( FullArrayEntitySchemaData $schemaData ) use (
				$langCode,
				$label,
				$description,
				$aliases
			) {
				$schemaData->data['labels'][$langCode] = $label;
				$schemaData->data['descriptions'][$langCode] = $description;
				$schemaData->data['aliases'][$langCode] = $aliases;
			}
		);

		if ( $schemaData === null ) {
			return;
		}

		$autoComment = $this->getUpdateNameBadgeAutocomment(
			$baseRevision,
			$langCode,
			$label,
			$description,
			$aliases
		);

		$updater->setContent(
			SlotRecord::MAIN,
			new EntitySchemaContent(
				EntitySchemaEncoder::getPersistentRepresentation(
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
		RevisionRecord $baseRevision,
		string $langCode,
		string $label,
		string $description,
		array $aliases
	): CommentStoreComment {

		$schemaConverter = new EntitySchemaConverter();
		$schemaData = $schemaConverter->getPersistenceSchemaData(
			// @phan-suppress-next-line PhanUndeclaredMethod
			$baseRevision->getContent( SlotRecord::MAIN )->getText()
		);

		$label = EntitySchemaCleaner::trimWhitespaceAndControlChars( $label );
		$description = EntitySchemaCleaner::trimWhitespaceAndControlChars( $description );
		$aliases = EntitySchemaCleaner::cleanupArrayOfStrings( $aliases );
		$language = $this->languageFactory->getLanguage( $langCode );

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
	 * @param EntitySchemaId $id
	 * @param string $schemaText
	 * @param int $baseRevId
	 * @param string|null $userSummary
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws EditConflict if another revision has been saved after $baseRevId
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function updateSchemaText(
		EntitySchemaId $id,
		string $schemaText,
		int $baseRevId,
		?string $userSummary = null
	): void {
		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$parentRevision = $updater->grabParentRevision();
		$this->checkSchemaExists( $parentRevision );

		$baseRevision = $this->revisionLookup->getRevisionById( $baseRevId );

		$updateGuard = new EntitySchemaUpdateGuard();
		$schemaData = $updateGuard->guardSchemaUpdate(
			$baseRevision,
			$parentRevision,
			static function ( FullArrayEntitySchemaData $schemaData ) use ( $schemaText ) {
				$schemaData->data['schemaText'] = $schemaText;
			}
		);

		if ( $schemaData === null ) {
			return;
		}

		$persistentRepresentation = EntitySchemaEncoder::getPersistentRepresentation(
			$id,
			$schemaData->labels,
			$schemaData->descriptions,
			$schemaData->aliases,
			$schemaData->schemaText
		);

		$updater->setContent(
			SlotRecord::MAIN,
			new EntitySchemaContent( $persistentRepresentation )
		);

		$commentText = '/* ' . self::AUTOCOMMENT_UPDATED_SCHEMATEXT . ' */' . $userSummary;
		$updater->saveRevision(
				CommentStoreComment::newUnsavedComment(
				$commentText,
				[
					'key' => self::AUTOCOMMENT_UPDATED_SCHEMATEXT,
					'userSummary' => $userSummary,
					'schemaText_truncated' => $this->truncateSchemaTextForCommentData(
						// TODO use unpatched $schemaText or patched $schemaData->schemaText here?
						$schemaData->schemaText
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
	private function checkSchemaExists( RevisionRecord $parentRevision = null ): void {
		if ( $parentRevision === null ) {
			throw new RuntimeException( 'Schema to update does not exist' );
		}
	}

}
