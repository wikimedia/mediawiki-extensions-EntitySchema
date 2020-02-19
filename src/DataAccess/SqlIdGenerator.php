<?php

namespace EntitySchema\DataAccess;

use RuntimeException;
use EntitySchema\Domain\Storage\IdGenerator;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Unique Id generator implemented using an SQL table.
 * The table needs to have the fields id_value
 *
 * @license GPL-2.0-or-later
 * based on
 * @see \Wikibase\Repo\Store\Sql\SqlIdGenerator
 */
class SqlIdGenerator implements IdGenerator {
	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var string */
	private $tableName;

	/** @var array */
	private $idsToSkip;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param string        $tableName
	 * @param int[]         $idsToSkip
	 */
	public function __construct( ILoadBalancer $loadBalancer, $tableName, array $idsToSkip = [] ) {
		$this->loadBalancer = $loadBalancer;
		$this->tableName = $tableName;
		$this->idsToSkip = $idsToSkip;
	}

	/**
	 * @return int
	 *
	 * @throws RuntimeException
	 */
	public function getNewId() {
		$database = $this->loadBalancer->getConnection( DB_MASTER );

		$id = $this->generateNewId( $database );
		$this->loadBalancer->reuseConnection( $database );

		return $id;
	}

	/**
	 * Generates and returns a new ID.
	 *
	 * @param IDatabase $database
	 * @param bool $retry Retry once in case of e.g. race conditions. Defaults to true.
	 *
	 * @throws RuntimeException
	 * @return int
	 */
	private function generateNewId( IDatabase $database, $retry = true ) {
		$database->startAtomic( __METHOD__ );

		$currentId = $database->selectRow(
			$this->tableName,
			'id_value',
			[],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);

		if ( is_object( $currentId ) ) {
			$id = $currentId->id_value + 1;
			$success = $database->update(
				$this->tableName,
				[ 'id_value' => $id ],
				[]
			);
		} else {
			$id = 1;

			$success = $database->insert(
				$this->tableName,
				[
					'id_value' => $id,
				]
			);

			// Retry once, since a race condition on initial insert can cause one to fail.
			// Race condition is possible due to occurrence of phantom reads is possible
			// at non serializable transaction isolation level.
			if ( !$success && $retry ) {
				$id = $this->generateNewId( $database, false );
				$success = true;
			}
		}

		$database->endAtomic( __METHOD__ );

		if ( !$success ) {
			throw new RuntimeException( 'Could not generate a reliably unique ID.' );
		}

		if ( in_array( $id, $this->idsToSkip ) ) {
			$id = $this->generateNewId( $database, $retry );
		}

		return $id;
	}

}
