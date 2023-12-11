<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use DerivativeContext;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use IContextSource;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Title\TitleFactory;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiRevisionEntitySchemaInserter implements EntitySchemaInserter {
	public const AUTOCOMMENT_NEWSCHEMA = 'entityschema-summary-newschema-nolabel';

	private MediaWikiPageUpdaterFactory $pageUpdaterFactory;
	private IdGenerator $idGenerator;
	private WatchlistUpdater $watchListUpdater;
	private IContextSource $context;
	private LanguageFactory $languageFactory;
	private HookContainer $hookContainer;
	private TitleFactory $titleFactory;

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IdGenerator $idGenerator,
		IContextSource $context,
		LanguageFactory $languageFactory,
		HookContainer $hookContainer,
		TitleFactory $titleFactory
	) {
		$this->idGenerator = $idGenerator;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->context = $context;
		$this->languageFactory = $languageFactory;
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
		$persistentRepresentation = EntitySchemaEncoder::getPersistentRepresentation(
			$id,
			[ $language => $label ],
			[ $language => $description ],
			[ $language => $aliases ],
			$schemaText
		);

		$schemaConverter = new EntitySchemaConverter();
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

	private function truncateSchemaTextForCommentData( string $schemaText ): string {
		$language = $this->languageFactory->getLanguage( 'en' );
		return $language->truncateForVisual( $schemaText, 5000 );
	}

	private function saveRevision(
		PageUpdater $updater,
		EntitySchemaContent $content,
		CommentStoreComment $summary
	): void {
		$context = new DerivativeContext( $this->context );
		$context->setTitle( $this->titleFactory->newFromPageIdentity( $updater->getPage() ) );
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
