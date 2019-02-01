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
use Wikimedia\Rdbms\LoadBalancer;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Serializers\SerializerFactory;
use Wikibase\Schema\SqlIdGenerator;

/**
 * @license GPL-2.0-or-later
 */
class RevisionSchemaRepository implements SchemaRepository {

	private $loadBalancer;
	private $user;

	public function __construct( LoadBalancer $loadBalancer, User $user ) {
		$this->loadBalancer = $loadBalancer;
		$this->user = $user;
	}

	/**
	 * @param Schema $schema
	 *
	 * @return string
	 *
	 * @throws \MWException
	 */
	public function storeSchema( Schema $schema ) {
		$idGenerator = new SqlIdGenerator(
			$this->loadBalancer,
			'wbschema_id_counter'
		);
		$id = 'O' . $idGenerator->getNewId();
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id );
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

		return $id;
	}

}
