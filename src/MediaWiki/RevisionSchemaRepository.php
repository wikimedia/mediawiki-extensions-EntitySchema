<?php

namespace Wikibase\Schema\MediaWiki;

use CommentStoreComment;
use MediaWiki\Revision\SlotRecord;
use RuntimeException;
use Title;
use User;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\Domain\Storage\SchemaRepository;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Serialization\DeserializerFactory;
use Wikibase\Schema\Serialization\SerializerFactory;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 */
class RevisionSchemaRepository implements SchemaRepository {

	private $user;

	public function __construct( User $user ) {
		$this->user = $user;
	}

	/**
	 * @param Schema $schema
	 *
	 * @throws \MWException
	 */
	public function storeSchema( Schema $schema ) {
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $schema->getId()->getId() );
		$wikipage = WikiPage::factory( $title );
		$updater = $wikipage->newPageUpdater( $this->user );

		$serializer = SerializerFactory::newSchemaSerializer();
		$dataToSave = $serializer->serialize( $schema );

		$updater->setContent( SlotRecord::MAIN, new WikibaseSchemaContent( json_encode( $dataToSave ) ) );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'FIXME in NewSchema::submitCallback'
			)
		);

		if ( !$updater->wasSuccessful() ) {
			throw new RuntimeException( 'Storing the Schema failed!' );
		}
	}

	public function loadSchema( SchemaId $id ): Schema {
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id->getId() );
		$wikiPage = WikiPage::factory( $title );
		/** @var WikibaseSchemaContent $content */
		$content = $wikiPage->getContent(); // TODO don’t we have to specify SlotRecord::MAIN?

		$deserializer = DeserializerFactory::newSchemaDeserializer(); // TODO inject
		$schema = $deserializer->deserialize( json_decode( $content->getText(), true ) );
		// TODO use $content->getData() instead of decoding the text? but that returns objects
		$schema->setId( $id ); // TODO should this happen during deserialization?
		return $schema;
	}

}
