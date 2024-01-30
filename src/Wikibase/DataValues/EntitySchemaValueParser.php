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
		if ( !is_array( $value ) ) {
			throw new ParseException(
				'The value supplied must be an array',
				$value,
				'entity-schema'
			);
		}
		if ( !array_key_exists( 'value', $value ) ) {
			throw new ParseException(
				'The array must contain the key "value"',
				print_r( $value, true ),
				'entity-schema'
			);
		}
		if ( is_array( $value['value'] ) ) {
			if ( !array_key_exists( 'id', $value['value'] ) ) {
				throw new ParseException(
					'The "value" key must contain an array including an "id" key',
					print_r( $value, true ),
					'entity-schema'
				);
			}
			if ( !is_string( $value['value']['id'] ) ) {
				throw new ParseException(
					'The "id" element of the "value" array must be a string',
					print_r( $value, true ),
					'entity-schema'
				);
			}
			return new EntitySchemaValue( new EntitySchemaId( $value['value']['id'] ) );
		}
		if ( !is_string( $value['value'] ) ) {
			throw new ParseException(
				'The "value" must be a string',
				print_r( $value, true ),
				'entity-schema'
			);
		}
		return new EntitySchemaValue( new EntitySchemaId( $value['value'] ) );
	}
}
