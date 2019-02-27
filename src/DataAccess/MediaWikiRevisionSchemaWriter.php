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
				json_encode(
					[
						'id' => $id->getId(),
						'serializationVersion' => '2.0',
						'labels' => [
							$language => $label
						],
						'descriptions' => [
							$language => $description
						],
						'aliases' => [
							$language => $aliases
						],
						'schema' => $schemaContent,
						'type' => 'ShExC'
					]
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
	public function updateSchema( SchemaId $id,
		$language,
		$label,
		$description,
		array $aliases,
		$schemaContent,
		Message $message = null
	) {
		$this->validateParameters(
			$language,
			$label,
			$description,
			$aliases,
			$schemaContent
		);

		if ( $message === null ) {
			$message = $this->msgLocalizer->msg( 'wikibaseschema-summary-update' );
		}

		$updater = $this->pageUpdaterFactory->getPageUpdater( $id->getId() );
		$this->checkSchemaExists( $updater );

		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent(
				json_encode(
					[
						'id' => $id->getId(),
						'serializationVersion' => '2.0',
						'labels' => [
							$language => $label
						],
						'descriptions' => [
							$language => $description
						],
						'aliases' => [
							$language => $aliases
						],
						'schema' => $schemaContent,
						'type' => 'ShExC',
					]
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

	private function validateParameters(
		$language,
		$label,
		$description,
		array $aliases,
		$schemaContent
	) {
		if ( !( is_string( $language ) &&
			is_string( $label ) &&
			is_string( $description ) &&
			is_string( $schemaContent ) &&
			$this->isSequentialArrayOfStrings( $aliases )
		) ) {
			throw new RuntimeException(
				'language, label, description and schemaContent must be strings '
				. 'and aliases must be an array of strings'
			);
		}
	}

	private function isSequentialArrayOfStrings( array $array ) {
		$values = array_values( $array );
		if ( $array !== $values ) {
			return false; // array is associative - fast solution see: https://stackoverflow.com/questions/173400/how-to-check-if-php-array-is-associative-or-sequential
		}
		foreach ( $values as $value ) {
			if ( !is_string( $value ) ) {
				return false;
			}
		}
		return true;
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
		$data = json_decode( $content->getText(), true );
		if ( !array_key_exists( 'serializationVersion', $data ) || (
			$data['serializationVersion'] !== '1.0' &&
			$data['serializationVersion'] !== '2.0' ) ) {
			throw new RuntimeException( 'Unknown or missing serialization version' );
		}

		// TODO check $updater->hasEditConflict()! (T217338)

		// in serialization version 1.0 or 2.0, the schema content is stored the same way,
		// so just update that and leave the rest unchanged
		$data['schema'] = $schemaContent;
		$updater->setContent(
			SlotRecord::MAIN,
			new WikibaseSchemaContent( json_encode( $data ) )
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
