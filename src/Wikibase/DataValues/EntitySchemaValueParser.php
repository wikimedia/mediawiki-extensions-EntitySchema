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
		/** We handle both String and Array inputs here for calls from `wbparsevalue`
		 * and `wbformatvalue`. This reflects the fact that this class is serving two
		 * distinct purposes with the same function.
		 * TODO: Split this into independent classes / functions
		 * @see T365794
		 */
		if ( is_string( $value ) ) {
			/* For calls from `wbparsevalue` (for example, when the EntitySchema expert
			 * processes a value selected by the user from the property search results
			 * dropdown), we need to handle values passed to us as strings. */
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
		/* For calls from `wbformatvalue` and during the saving of statements, we expect
		 * to receive an array-structured value here */
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
