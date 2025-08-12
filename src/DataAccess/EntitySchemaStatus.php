<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use MediaWiki\Context\IContextSource;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikibase\Repo\TempUserStatus;
use Wikimedia\Assert\Assert;

/**
 * A Status representing the result of an EntitySchema edit.
 *
 * Note that even an OK status does not necessarily mean that a new edit was made
 * (it might have been a null edit).
 *
 * @inherits TempUserStatus<array{savedTempUser:?UserIdentity,context:IContextSource,id:EntitySchemaId}>
 * @license GPL-2.0-or-later
 */
class EntitySchemaStatus extends TempUserStatus {

	public static function newEdit(
		EntitySchemaId $id,
		?UserIdentity $savedTempUser,
		IContextSource $context
	): self {
		return self::newTempUserStatus( [
			'id' => $id,
		], $savedTempUser, $context );
	}

	/**
	 * @param StatusValue $sv
	 * @return static
	 */
	public static function wrap( $sv ) {
		// This implementation only exists to change the declared return type,
		// from Status to static (i.e. EditEntityStatus);
		// it would become redundant if Ic1a8eccc53 is merged.
		// (Note that the parent *implementation* already returns static,
		// it just isn’t declared as such yet.)
		return parent::wrap( $sv );
	}

	/**
	 * The ID of the EntitySchema touched by this edit.
	 * (It may have been created by the edit with a freshly assigned ID.)
	 * Only meaningful if the status is {@link self::isOK() OK}.
	 */
	public function getEntitySchemaId(): EntitySchemaId {
		Assert::precondition( $this->isOK(), '$this->isOK()' );
		return $this->getValue()['id'];
	}

}
