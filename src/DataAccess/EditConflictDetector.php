<?php

namespace Wikibase\Schema\DataAccess;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class EditConflictDetector {

	private $revisionStore;

	public function __construct( RevisionStore $revisionStore ) {
		$this->revisionStore = $revisionStore;
	}

	/**
	 * @param RevisionRecord $parentRevisionRecord
	 * @param $baseRevId
	 *
	 * @return bool
	 *
	 * @suppress PhanUndeclaredMethod
	 */
	public function isSchemaTextEditConflict(
		RevisionRecord $parentRevisionRecord,
		$baseRevId
	) {
		if ( $parentRevisionRecord->getId() === $baseRevId ) {
			return false;
		}
		/** @var WikibaseSchemaContent $baseContent */
		$baseJSON = $this->revisionStore
			->getRevisionById( $baseRevId )
			->getContent( SlotRecord::MAIN )
			->getText();

		$parentJSON = $parentRevisionRecord->getContent( SlotRecord::MAIN )->getText();

		$converter = new SchemaConverter();
		$baseSchemaText = $converter->getSchemaText( $baseJSON );
		$parentSchemaText = $converter->getSchemaText( $parentJSON );

		return $baseSchemaText !== $parentSchemaText;
	}

	/**
	 * @param RevisionRecord $parentRevisionRecord
	 * @param $baseRevId
	 * @param $langCode
	 *
	 * @return bool
	 *
	 * @suppress PhanUndeclaredMethod
	 */
	public function isNameBadgeEditConflict(
		RevisionRecord $parentRevisionRecord,
		$baseRevId,
		$langCode
	) {
		if ( $parentRevisionRecord->getId() === $baseRevId ) {
			return false;
		}
		/** @var WikibaseSchemaContent $baseContent */
		$baseJSON = $this->revisionStore
			->getRevisionById( $baseRevId )
			->getContent( SlotRecord::MAIN )
			->getText();
		$parentJSON = $parentRevisionRecord->getContent( SlotRecord::MAIN )->getText();

		$converter = new SchemaConverter();
		$baseNameBadgeData = $converter->getMonolingualNameBadgeData( $baseJSON, $langCode );
		$parentNameBadgeData = $converter->getMonolingualNameBadgeData( $parentJSON, $langCode );

		return $baseNameBadgeData->label !== $parentNameBadgeData->label
			|| $baseNameBadgeData->description !== $parentNameBadgeData->description
			|| implode( '', $baseNameBadgeData->aliases ) !== implode( '', $parentNameBadgeData->aliases );
	}

}
