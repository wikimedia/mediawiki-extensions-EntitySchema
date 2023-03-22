<?php
declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\ServiceWiring;

use Generator;
use LogicException;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * @license GPL-2.0-or-later
 */
class ServiceWiringTestCase extends TestCase {

	/**
	 * @var array
	 */
	private $wiring;

	/** @var mixed[] */
	private $mockedServices;

	/** @var null[] */
	private $accessedServices;

	/**
	 * @var MockObject|MediaWikiServices
	 */
	protected $serviceContainer;

	protected function setUp(): void {
		parent::setUp();

		$this->wiring = $this->loadWiring();
		$this->mockedServices = [];
		$this->accessedServices = [];
		$this->serviceContainer = $this->createMock( MediaWikiServices::class );
		$this->serviceContainer->method( 'get' )
			->willReturnCallback( function ( string $name ) {
				$this->assertArrayNotHasKey( $name, $this->accessedServices,
					"Service $name must not be accessed more than once" );
				$this->accessedServices[$name] = null;
				$this->assertArrayHasKey( $name, $this->mockedServices,
					"Service $name must be mocked" );
				return $this->mockedServices[$name];
			} );
		$this->serviceContainer->expects( $this->never() )
			->method( 'getService' ); // get() should be used instead
		// Service getters should never access the database or do http requests
		// https://phabricator.wikimedia.org/T243729
		$this->disallowDbAccess();
		$this->disallowHttpAccess();
	}

	protected function tearDown(): void {
		$this->assertEqualsCanonicalizing( array_keys( $this->mockedServices ), array_keys( $this->accessedServices ),
			'Expected every mocked service to be used' );

		parent::tearDown();
	}

	private function getDefinition( $name ): callable {
		if ( !array_key_exists( $name, $this->wiring ) ) {
			throw new LogicException( "Service wiring '$name' does not exist" );
		}
		return $this->wiring[ $name ];
	}

	/**
	 * Get an EntitySchema service by calling its wiring function.
	 *
	 * @param string $name full service name (including "EntitySchema" prefix)
	 * @return mixed service (typically an object)
	 */
	protected function getService( string $name ) {
		return $this->getDefinition( $name )( $this->serviceContainer );
	}

	public function provideWiring(): Generator {
		$wiring = $this->loadWiring();
		foreach ( $wiring as $name => $definition ) {
			yield $name => [ $name, $definition ];
		}
	}

	private function loadWiring(): array {
		return require __DIR__ . '/../../../../src/EntitySchema.ServiceWiring.php';
	}

	private function disallowDbAccess() {
		$lb = $this->createMock( ILoadBalancer::class );
		$lb->expects( $this->never() )
			->method( 'getConnection' );
		$lb->expects( $this->never() )
			->method( 'getConnectionRef' );
		$lb->expects( $this->never() )
			->method( 'getMaintenanceConnectionRef' );
		$lb->method( 'getLocalDomainID' )
			->willReturn( 'banana' );

		$this->serviceContainer->method( 'getDBLoadBalancer' )
			->willReturn( $lb );

		$this->serviceContainer->method( 'getDBLoadBalancerFactory' )
			->willReturnCallback( function () use ( $lb ) {
				$lbFactory = $this->createMock( LBFactory::class );
				$lbFactory->method( 'getMainLB' )
					->willReturn( $lb );
				$lbFactory->method( 'getLocalDomainID' )
					->willReturn( 'repoDbDomain' );

				return $lbFactory;
			} );
	}

	private function disallowHttpAccess() {
		$this->serviceContainer->method( 'getHttpRequestFactory' )
			->willReturnCallback( function () {
				$factory = $this->createMock( HttpRequestFactory::class );
				$factory->expects( $this->never() )
					->method( 'create' );
				$factory->expects( $this->never() )
					->method( 'request' );
				$factory->expects( $this->never() )
					->method( 'get' );
				$factory->expects( $this->never() )
					->method( 'post' );
				return $factory;
			} );
	}
}
