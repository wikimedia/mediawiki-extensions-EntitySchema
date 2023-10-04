<?php

namespace EntitySchema\DataAccess;

use CommentStoreComment;
use DerivativeContext;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\SchemaConverter\FullArraySchemaData;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use IContextSource;
use InvalidArgumentException;
use Language;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use RuntimeException;
use Status;
use TitleFactory;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaUpdater implements SchemaUpdater {

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
	private HookContainer $hookContainer;
	private TitleFactory $titleFactory;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IContextSource $context,
		RevisionLookup $revisionLookup,
		HookContainer $hookContainer,
		TitleFactory $titleFactory
	) {
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->context = $context;
		$this->revisionLookup = $revisionLookup;
		$this->hookContainer = $hookContainer;
		$this->titleFactory = $titleFactory;
	}

	// TODO this should probably be a service in the service container
	public static function newFromContext( IContextSource $context ): self {
		$services = MediaWikiServices::getInstance();
		return new self(
			new MediaWikiPageUpdaterFactory( $context->getUser() ),
			new WatchlistUpdater( $context->getUser(), NS_ENTITYSCHEMA_JSON ),
			$context,
			$services->getRevisionLookup(),
			$services->getHookContainer(),
			$services->getTitleFactory()
		);
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
	 * @param string[][] $aliasGroups
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
		$this->checkSchemaExists( $updater->grabParentRevision() );
		if ( $updater->hasEditConflict( $baseRevId ) ) {
			throw new EditConflict();
		}

		$content = new EntitySchemaContent(
			SchemaEncoder::getPersistentRepresentation(
				$id,
				$labels,
				$descriptions,
				$aliasGroups,
				$schemaText
			)
		);
		$this->saveRevision( $updater, $content, $summary );

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

		$baseRevision = $this->revisionLookup->getRevisionById( $baseRevId );

		$updateGuard = new SchemaUpdateGuard();
		$schemaData = $updateGuard->guardSchemaUpdate(
			$baseRevision,
			$parentRevision,
			static function ( FullArraySchemaData $schemaData ) use ( $langCode, $label, $description, $aliases ) {
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

		$content = new EntitySchemaContent(
			SchemaEncoder::getPersistentRepresentation(
				$id,
				$schemaData->labels,
				$schemaData->descriptions,
				$schemaData->aliases,
				$schemaData->schemaText
			)
		);
		$this->saveRevision( $updater, $content, $autoComment );

		$this->watchListUpdater->optionallyWatchEditedSchema( $id );
	}

	private function getUpdateNameBadgeAutocomment(
		RevisionRecord $baseRevision,
		$langCode,
		$label,
		$description,
		array $aliases
	): CommentStoreComment {

		$schemaConverter = new SchemaConverter();
		$schemaData = $schemaConverter->getPersistenceSchemaData(
			// @phan-suppress-next-line PhanUndeclaredMethod
			$baseRevision->getContent( SlotRecord::MAIN )->getText()
		);

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

		$baseRevision = $this->revisionLookup->getRevisionById( $baseRevId );

		$updateGuard = new SchemaUpdateGuard();
		$schemaData = $updateGuard->guardSchemaUpdate(
			$baseRevision,
			$parentRevision,
			static function ( FullArraySchemaData $schemaData ) use ( $schemaText ) {
				$schemaData->data['schemaText'] = $schemaText;
			}
		);

		if ( $schemaData === null ) {
			return;
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

		$persistentRepresentation = SchemaEncoder::getPersistentRepresentation(
			$id,
			$schemaData->labels,
			$schemaData->descriptions,
			$schemaData->aliases,
			$schemaData->schemaText
		);

		$content = new EntitySchemaContent( $persistentRepresentation );
		$this->saveRevision( $updater, $content, $summary );

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

	private function saveRevision(
		PageUpdater $updater,
		EntitySchemaContent $content,
		CommentStoreComment $summary
	): void {
		$context = new DerivativeContext( $this->context );
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$context->setTitle( $this->titleFactory->castFromPageIdentity( $updater->getPage() ) );
		$status = Status::newGood();
		if ( !$this->hookContainer->run(
			'EditFilterMergedContent',
			[ $context, $content, &$status, $summary->text, $this->context->getUser(), false ]
		) ) {
			throw new RuntimeException( $status->getWikiText() );
		}

		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->saveRevision(
			$summary,
			EDIT_UPDATE | EDIT_INTERNAL
		);
		if ( !$updater->wasSuccessful() ) {
			throw new RuntimeException( 'The revision could not be saved' );
		}
	}

}
