<?php

namespace Wikibase\Schema\DataAccess;

use CommentStoreComment;
use InvalidArgumentException;
use MediaWiki\Revision\SlotRecord;
use Message;
use MediaWiki\Storage\PageUpdater;
use MessageLocalizer;
use RuntimeException;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\Domain\Storage\IdGenerator;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;
use Wikimedia\Assert\Assert;

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
	 * @param string $schemaContent
	 *
	 * @return SchemaId id of the inserted Schema
	 */
	public function insertSchema(
		$language,
		$label = '',
		$description = '',
		array $aliases = [],
		$schemaContent = ''
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
					$schemaContent
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
	 * @param SchemaId $id
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaContent
	 * @param Message|null $message
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 *
	 * Update a Schema with new content. This will remove existing schema content.
	 */
	public function updateSchema(
		SchemaId $id,
		$language,
		$label,
		$description,
		array $aliases,
		$schemaContent,
		Message $message = null
	) {
		if ( $message === null ) {
			$message = $this->msgLocalizer->msg( 'wikibaseschema-summary-update' );
		}

		// FIXME replace this with a strict type hint when available
		Assert::parameterType( 'string', $language, '$language' );

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$this->checkSchemaExists( $updater );

		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent(
				SchemaEncoder::getPersistentRepresentation(
					$id,
					[ $language => $label ],
					[ $language => $description ],
					[ $language => $aliases ],
					$schemaContent
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

	/**
	 * @param SchemaId $id
	 * @param string $schemaContent
	 *
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 * @throws InvalidArgumentException if bad parameters are passed
	 */
	public function updateSchemaContent( SchemaId $id, $schemaContent ) {
		if ( !is_string( $schemaContent ) ) {
			throw new InvalidArgumentException( 'schema content must be a string' );
		}

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$this->checkSchemaExists( $updater );

		/** @var WikibaseSchemaContent $content */
		$content = $updater->grabParentRevision()->getContent( SlotRecord::MAIN );
		$dispatcher = new SchemaDispatcher();
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schemaData = $dispatcher->getPersistenceSchemaData( $content->getText() );
		$schemaData->schemaText = $schemaContent;

		// TODO check $updater->hasEditConflict()! (T217338)

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
	 * @param PageUpdater $updater
	 *
	 * @throws RuntimeException
	 */
	private function checkSchemaExists( PageUpdater $updater ) {
		if ( $updater->grabParentRevision() === null ) {
			throw new RuntimeException( 'Schema to update does not exist' );
		}
	}

}
