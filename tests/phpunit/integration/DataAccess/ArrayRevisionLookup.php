<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use Title;

/**
 * A {@link RevisionLookup} for a static array of {@link RevisionRecord}s, to be used in tests.
 * Only {@link getRevisionbyId()} is supported.
 *
 * @license GPL-2.0-or-later
 */
class ArrayRevisionLookup implements RevisionLookup {

	/** @var RevisionRecord[] */
	private $revisionRecords = [];

	/**
	 * @param RevisionRecord[] $revisionRecords
	 */
	public function __construct( array $revisionRecords = [] ) {
		foreach ( $revisionRecords as $revisionRecord ) {
			$this->revisionRecords[$revisionRecord->getId()] = $revisionRecord;
		}
	}

	public function getRevisionById( $id, $flags = 0 ) {
		if ( !array_key_exists( $id, $this->revisionRecords ) ) {
			return null;
		}
		return $this->revisionRecords[$id];
	}

	public function getRevisionByTitle( LinkTarget $linkTarget, $revId = 0, $flags = 0 ) {
		return null;
	}

	public function getRevisionByPageId( $pageId, $revId = 0, $flags = 0 ) {
		return null;
	}

	public function getPreviousRevision( RevisionRecord $rev, $flags = 0 ) {
		return null;
	}

	public function getNextRevision( RevisionRecord $rev, $flags = 0 ) {
		return null;
	}

	public function getTimestampFromId( $id, $flags = 0 ) {
		return null;
	}

	public function getKnownCurrentRevision( Title $title, $revId = 0 ) {
		return null;
	}

}
