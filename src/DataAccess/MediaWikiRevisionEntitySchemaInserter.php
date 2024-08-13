<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Title\TitleFactory;

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
		$content = new EntitySchemaContent( $persistentRepresentation );
		$status = $this->saveRevision( $id, $updaterStatus->getPageUpdater(), $content, $summary );
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
			EDIT_NEW | EDIT_INTERNAL
		);
		$status->merge( $updater->getStatus() );
		return $status;
	}

}
