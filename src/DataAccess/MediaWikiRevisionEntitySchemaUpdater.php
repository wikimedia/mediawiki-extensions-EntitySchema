<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use Diff\Patcher\PatcherException;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\MediaWiki\HookRunner;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\FullArrayEntitySchemaData;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\IContextSource;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;

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
	private IContextSource $context;
	private RevisionLookup $revisionLookup;
	private LanguageFactory $languageFactory;
	private HookRunner $hookRunner;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IContextSource $context,
		RevisionLookup $revisionLookup,
		LanguageFactory $languageFactory,
		HookRunner $hookRunner
	) {
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->context = $context;
		$this->revisionLookup = $revisionLookup;
		$this->languageFactory = $languageFactory;
		$this->hookRunner = $hookRunner;
	}

	// TODO this should probably be a service in the service container
	public static function newFromContext( IContextSource $context ): self {
		$services = MediaWikiServices::getInstance();
		return new self(
			EntitySchemaServices::getMediaWikiPageUpdaterFactory( $services ),
			EntitySchemaServices::getWatchlistUpdater( $services ),
			$context,
			$services->getRevisionLookup(),
			$services->getLanguageFactory(),
			EntitySchemaServices::getHookRunner( $services )
		);
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
	 */
	public function overwriteWholeSchema(
		EntitySchemaId $id,
		array $labels,
		array $descriptions,
		array $aliasGroups,
		string $schemaText,
		int $baseRevId,
		CommentStoreComment $summary
	): EntitySchemaStatus {
		$updaterStatus = $this->pageUpdaterFactory->getPageUpdater( $id->getId(), $this->context );
		if ( !$updaterStatus->isOK() ) {
			return EntitySchemaStatus::cast( $updaterStatus );
		}
		$status = EntitySchemaStatus::newEdit(
			$id,
			$updaterStatus->getSavedTempUser(),
			$updaterStatus->getContext()
		);
		$updater = $updaterStatus->getPageUpdater();
		if ( $updater->grabParentRevision() === null ) {
			$status->fatal( 'entityschema-error-schemaupdate-failed' );
			return $status;
		}
		if ( $updater->hasEditConflict( $baseRevId ) ) {
			$status->fatal( 'edit-conflict' );
			return $status;
		}

		$content = new EntitySchemaContent(
			EntitySchemaEncoder::getPersistentRepresentation(
				$id,
				$labels,
				$descriptions,
				$aliasGroups,
				$schemaText
			)
		);
		$this->saveRevision( $status, $updater, $content, $summary );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->watchListUpdater->optionallyWatchEditedSchema( $this->context->getUser(), $id );

		return $status;
	}

	public function updateSchemaNameBadge(
		EntitySchemaId $id,
		string $langCode,
		string $label,
		string $description,
		array $aliases,
		int $baseRevId
	): EntitySchemaStatus {
		$updaterStatus = $this->pageUpdaterFactory->getPageUpdater( $id->getId(), $this->context );
		if ( !$updaterStatus->isOK() ) {
			return EntitySchemaStatus::cast( $updaterStatus );
		}
		$status = EntitySchemaStatus::newEdit(
			$id,
			$updaterStatus->getSavedTempUser(),
			$updaterStatus->getContext()
		);
		$updater = $updaterStatus->getPageUpdater();
		$parentRevision = $updater->grabParentRevision();
		if ( $parentRevision === null ) {
			$status->fatal( 'entityschema-error-schemaupdate-failed' );
			return $status;
		}

		$baseRevision = $this->revisionLookup->getRevisionById( $baseRevId );

		$updateGuard = new EntitySchemaUpdateGuard();
		try {
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
		} catch ( PatcherException ) {
			return EntitySchemaStatus::newFatal( 'edit-conflict' );
		}

		if ( $schemaData === null ) {
			return $status;
		}

		$autoComment = $this->getUpdateNameBadgeAutocomment(
			$baseRevision,
			$langCode,
			$label,
			$description,
			$aliases
		);

		$content = new EntitySchemaContent(
			EntitySchemaEncoder::getPersistentRepresentation(
				$id,
				$schemaData->labels,
				$schemaData->descriptions,
				$schemaData->aliases,
				$schemaData->schemaText
			)
		);
		$this->saveRevision( $status, $updater, $content, $autoComment );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->watchListUpdater->optionallyWatchEditedSchema( $this->context->getUser(), $id );

		return $status;
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
	 */
	public function updateSchemaText(
		EntitySchemaId $id,
		string $schemaText,
		int $baseRevId,
		?string $userSummary = null
	): EntitySchemaStatus {
		$updaterStatus = $this->pageUpdaterFactory->getPageUpdater( $id->getId(), $this->context );
		if ( !$updaterStatus->isOK() ) {
			return EntitySchemaStatus::cast( $updaterStatus );
		}
		$status = EntitySchemaStatus::newEdit(
			$id,
			$updaterStatus->getSavedTempUser(),
			$updaterStatus->getContext()
		);
		$updater = $updaterStatus->getPageUpdater();
		$parentRevision = $updater->grabParentRevision();
		if ( $parentRevision === null ) {
			$status->fatal( 'entityschema-error-schemaupdate-failed' );
			return $status;
		}

		$baseRevision = $this->revisionLookup->getRevisionById( $baseRevId );

		$updateGuard = new EntitySchemaUpdateGuard();
		try {
			$schemaData = $updateGuard->guardSchemaUpdate(
				$baseRevision,
				$parentRevision,
				static function ( FullArrayEntitySchemaData $schemaData ) use ( $schemaText ) {
					$schemaData->data['schemaText'] = $schemaText;
				}
			);
		} catch ( PatcherException ) {
			$status->fatal( 'edit-conflict' );
			return $status;
		}

		if ( $schemaData === null ) {
			return $status;
		}

		$commentText = '/* ' . self::AUTOCOMMENT_UPDATED_SCHEMATEXT . ' */' . $userSummary;
		$summary = CommentStoreComment::newUnsavedComment(
			$commentText,
			[
				'key' => self::AUTOCOMMENT_UPDATED_SCHEMATEXT,
				'userSummary' => $userSummary,
				'schemaText_truncated' => $this->truncateSchemaTextForCommentData(
					// TODO use unpatched $schemaText or patched $schemaData->schemaText here?
					$schemaData->schemaText
				),
			]
		);

		$persistentRepresentation = EntitySchemaEncoder::getPersistentRepresentation(
			$id,
			$schemaData->labels,
			$schemaData->descriptions,
			$schemaData->aliases,
			$schemaData->schemaText
		);

		$content = new EntitySchemaContent( $persistentRepresentation );
		$this->saveRevision( $status, $updater, $content, $summary );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->watchListUpdater->optionallyWatchEditedSchema( $this->context->getUser(), $id );

		return $status;
	}

	private function saveRevision(
		EntitySchemaStatus $status,
		PageUpdater $updater,
		EntitySchemaContent $content,
		CommentStoreComment $summary
	): void {
		$context = $status->getContext();
		if ( !$this->hookRunner->onEditFilterMergedContent(
			$context, $content, $status, $summary->text, $context->getUser(), false
		) ) {
			return;
		}

		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->saveRevision(
			$summary,
			EDIT_UPDATE | EDIT_INTERNAL
		);
		$status->merge( $updater->getStatus() );
	}

}
