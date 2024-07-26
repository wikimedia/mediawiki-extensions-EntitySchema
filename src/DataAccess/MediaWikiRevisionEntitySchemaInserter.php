<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;

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

	public function __construct(
		MediaWikiPageUpdaterFactory $pageUpdaterFactory,
		WatchlistUpdater $watchListUpdater,
		IdGenerator $idGenerator,
		IContextSource $context,
		LanguageFactory $languageFactory,
		HookContainer $hookContainer
	) {
		$this->idGenerator = $idGenerator;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
		$this->watchListUpdater = $watchListUpdater;
		$this->context = $context;
		$this->languageFactory = $languageFactory;
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaText
	 *
	 * @return EntitySchemaStatus
	 */
	public function insertSchema(
		string $language,
		string $label = '',
		string $description = '',
		array $aliases = [],
		string $schemaText = ''
	): EntitySchemaStatus {
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

		$updaterStatus = $this->pageUpdaterFactory->getPageUpdater( $id->getId(), $this->context );
		if ( !$updaterStatus->isOK() ) {
			return EntitySchemaStatus::wrap( $updaterStatus );
		}
		$status = EntitySchemaStatus::newEdit(
			$id,
			$updaterStatus->getSavedTempUser(),
			$updaterStatus->getContext()
		);
		$content = new EntitySchemaContent( $persistentRepresentation );
		$this->saveRevision( $status, $updaterStatus->getPageUpdater(), $content, $summary );
		if ( !$status->isOK() ) {
			return $status;
		}

		$this->watchListUpdater->optionallyWatchNewSchema( $this->context->getUser(), $id );

		return $status;
	}

	private function truncateSchemaTextForCommentData( string $schemaText ): string {
		$language = $this->languageFactory->getLanguage( 'en' );
		return $language->truncateForVisual( $schemaText, 5000 );
	}

	private function saveRevision(
		EntitySchemaStatus $status,
		PageUpdater $updater,
		EntitySchemaContent $content,
		CommentStoreComment $summary
	): void {
		$context = $status->getContext();
		if ( !$this->hookContainer->run(
			'EditFilterMergedContent',
			[ $context, $content, $status, $summary->text, $context->getUser(), false ]
		) ) {
			return;
		}

		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->saveRevision(
			$summary,
			EDIT_NEW | EDIT_INTERNAL
		);
		$status->merge( $updater->getStatus() );
	}

}
