<?php

namespace Wikibase\Schema\MediaWiki;

use CommentStoreComment;
use MediaWiki\Revision\SlotRecord;
use RuntimeException;
use Title;
use User;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Domain\Storage\SchemaRepository;
use WikiPage;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Serializers\SerializerFactory;

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

}
