<?php
declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\ServiceWiring;

use EntitySchema\DataAccess\SqlIdGenerator;
use HashConfig;

/**
 * @covers \EntitySchema
 *
 * @license GPL-2.0-or-later
 */
class ServiceWiringIdGeneratorTest extends ServiceWiringTestCase {
	public function testConstruction(): void {
		$mockSkippedIds = new HashConfig( [ 'EntitySchemaSkippedIDs' => [ 1, 2, 3 ] ] );
		$this->serviceContainer->expects( $this->once() )
			->method( 'getMainConfig' )
			->willReturn( $mockSkippedIds );
		$IdGenerator = $this->getService( 'EntitySchema.IdGenerator' );
		$this->assertInstanceOf( SqlIdGenerator::class, $IdGenerator );
	}
}
