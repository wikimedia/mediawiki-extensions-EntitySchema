<?php

namespace EntitySchema\DataAccess;

use CommentStoreComment;
use DerivativeContext;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use IContextSource;
use Language;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use RuntimeException;
use Status;
use TitleFactory;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionSchemaInserter implements SchemaInserter {
	public const AUTOCOMMENT_NEWSCHEMA = 'entityschema-summary-newschema-nolabel';

	private MediaWikiPageUpdaterFactory $pageUpdaterFactory;
	private IdGenerator $idGenerator;
	private WatchlistUpdater $watchListUpdater;
	private IContextSource $context;
	private HookContainer $hookContainer;
	private TitleFactory $titleFactory;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IdGenerator $idGenerator,
		IContextSource $context,
		HookContainer $hookContainer,
		TitleFactory $titleFactory
	) {
		$this->idGenerator = $idGenerator;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->context = $context;
		$this->hookContainer = $hookContainer;
		$this->titleFactory = $titleFactory;
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

		$schemaConverter = new SchemaConverter();
		$schemaData = $schemaConverter->getMonolingualNameBadgeData(
			$persistentRepresentation,
			$language
		);
		$summary = CommentStoreComment::newUnsavedComment(
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
		);

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$content = new EntitySchemaContent( $persistentRepresentation );
		$this->saveRevision( $updater, $content, $summary );

		$this->watchListUpdater->optionallyWatchNewSchema( $id );

		return $id;
	}

	private function truncateSchemaTextForCommentData( $schemaText ) {
		$language = Language::factory( 'en' );
		return $language->truncateForVisual( $schemaText, 5000 );
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
			EDIT_NEW | EDIT_INTERNAL
		);
		if ( !$updater->wasSuccessful() ) {
			throw new RuntimeException( 'The revision could not be saved' );
		}
	}

}
