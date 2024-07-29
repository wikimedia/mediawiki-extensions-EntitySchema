<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use Diff\Patcher\PatcherException;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\FullArrayEntitySchemaData;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Title\TitleFactory;

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
	private HookContainer $hookContainer;
	private TitleFactory $titleFactory;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IContextSource $context,
		RevisionLookup $revisionLookup,
		LanguageFactory $languageFactory,
		HookContainer $hookContainer,
		TitleFactory $titleFactory
	) {
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->context = $context;
		$this->revisionLookup = $revisionLookup;
		$this->languageFactory = $languageFactory;
		$this->hookContainer = $hookContainer;
		$this->titleFactory = $titleFactory;
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
			$services->getHookContainer(),
			$services->getTitleFactory()
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
			return EntitySchemaStatus::wrap( $updaterStatus );
		}
		$updater = $updaterStatus->getPageUpdater();
		if ( $updater->grabParentRevision() === null ) {
			return EntitySchemaStatus::newFatal( 'entityschema-error-schemaupdate-failed' );
		}
		if ( $updater->hasEditConflict( $baseRevId ) ) {
			return EntitySchemaStatus::newFatal( 'edit-conflict' );
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
		$status = $this->saveRevision( $id, $updater, $content, $summary );
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
			return EntitySchemaStatus::wrap( $updaterStatus );
		}
		$updater = $updaterStatus->getPageUpdater();
		$parentRevision = $updater->grabParentRevision();
		if ( $parentRevision === null ) {
			return EntitySchemaStatus::newFatal( 'entityschema-error-schemaupdate-failed' );
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
		} catch ( PatcherException $e ) {
			return EntitySchemaStatus::newFatal( 'edit-conflict' );
		}

		if ( $schemaData === null ) {
			return EntitySchemaStatus::newEdit( $id );
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
		$status = $this->saveRevision( $id, $updater, $content, $autoComment );
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
			return EntitySchemaStatus::wrap( $updaterStatus );
		}
		$updater = $updaterStatus->getPageUpdater();
		$parentRevision = $updater->grabParentRevision();
		if ( $parentRevision === null ) {
			return EntitySchemaStatus::newFatal( 'entityschema-error-schemaupdate-failed' );
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
		} catch ( PatcherException $e ) {
			return EntitySchemaStatus::newFatal( 'edit-conflict' );
		}

		if ( $schemaData === null ) {
			return EntitySchemaStatus::newEdit( $id );
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
		$status = $this->saveRevision( $id, $updater, $content, $summary );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->watchListUpdater->optionallyWatchEditedSchema( $this->context->getUser(), $id );

		return $status;
	}

	private function saveRevision(
		EntitySchemaId $id,
		PageUpdater $updater,
		EntitySchemaContent $content,
		CommentStoreComment $summary
	): EntitySchemaStatus {
		$context = new DerivativeContext( $this->context );
		$context->setTitle( $this->titleFactory->newFromPageIdentity( $updater->getPage() ) );
		$status = EntitySchemaStatus::newEdit( $id );
		if ( !$this->hookContainer->run(
			'EditFilterMergedContent',
			[ $context, $content, $status, $summary->text, $this->context->getUser(), false ]
		) ) {
			return $status;
		}

		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->saveRevision(
			$summary,
			EDIT_UPDATE | EDIT_INTERNAL
		);
		$status->merge( $updater->getStatus() );
		return $status;
	}

}
