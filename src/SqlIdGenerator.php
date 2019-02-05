<?php

namespace Wikibase\Schema;

use Wikibase\Schema\Domain\Storage\IdGenerator;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LoadBalancer;

/**
 * Unique Id generator implemented using an SQL table.
 * The table needs to have the fields id_value
 *
 * @license GPL-2.0-or-later
 * based on
 * @see \Wikibase\SqlIdGenerator
 */
class SqlIdGenerator implements IdGenerator {
	/** @var LoadBalancer */
	private $loadBalancer;

	/** @var string */
	private $tableName;

	/**
	 * @param LoadBalancer $loadBalancer
	 * @param string       $tableName
	 */
	public function __construct( LoadBalancer $loadBalancer, $tableName ) {
		$this->loadBalancer = $loadBalancer;
		$this->tableName = $tableName;
	}

	/**
	 * @return int
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
	 * @throws MWException
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
			throw new MWException( 'Could not generate a reliably unique ID.' );
		}

		return $id;
	}

}
