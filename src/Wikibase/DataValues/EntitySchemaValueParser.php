<?php declare( strict_types=1 );

namespace EntitySchema\Wikibase\DataValues;

use EntitySchema\Domain\Model\EntitySchemaId;
use ValueParsers\ParseException;
use ValueParsers\ValueParser;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaValueParser implements ValueParser {

	/** @inheritDoc */
	public function parse( $value ) {
		if ( !is_string( $value ) ) {
			throw new ParseException(
				'The value supplied must be a string',
				print_r( $value, true ),
				'entity-schema'
			);
		}

		try {
			return new EntitySchemaValue( new EntitySchemaId( $value ) );
		} catch ( \InvalidArgumentException $e ) {
			throw new ParseException(
				'Unexpected id format: ' . $e->getMessage(),
				$value,
				'entity-schema'
			);
		}
	}
}
