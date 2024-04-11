<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Storage\IdGenerator;
use RuntimeException;
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

	private ILoadBalancer $loadBalancer;

	private string $tableName;

	/** @var int[] */
	private array $idsToSkip;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param string        $tableName
	 * @param int[]         $idsToSkip
	 */
	public function __construct( ILoadBalancer $loadBalancer, string $tableName, array $idsToSkip = [] ) {
		$this->loadBalancer = $loadBalancer;
		$this->tableName = $tableName;
		$this->idsToSkip = $idsToSkip;
	}

	/**
	 * @throws RuntimeException
	 */
	public function getNewId(): int {
		$database = $this->loadBalancer->getConnection( DB_PRIMARY );

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
	private function generateNewId( IDatabase $database, bool $retry = true ): int {
		$database->startAtomic( __METHOD__ );
		$currentId = $database->newSelectQueryBuilder()
			->select( [ 'id_value' ] )
			->from( $this->tableName )
			->forUpdate()
			->caller( __METHOD__ )
			->fetchRow();

		if ( is_object( $currentId ) ) {
			$id = $currentId->id_value + 1;
			$database->newUpdateQueryBuilder()
				->update( $this->tableName )
				->set( [ 'id_value' => $id ] )
				->where( $database::ALL_ROWS ) // there is only one row
				->caller( __METHOD__ )->execute();
			$success = true; // T339346
		} else {
			$id = 1;

			$database->newInsertQueryBuilder()
				->insertInto( $this->tableName )
				->row( [
					'id_value' => $id,
				] )
				->caller( __METHOD__ )
				->execute();
			$success = true; // T339346

			// Retry once, since a race condition on initial insert can cause one to fail.
			// Race condition is possible due to occurrence of phantom reads is possible
			// at non serializable transaction isolation level.
			// @phan-suppress-next-line PhanImpossibleCondition T339346
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
