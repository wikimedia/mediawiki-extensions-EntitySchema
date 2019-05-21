<?php

namespace EntitySchema\Tests\MediaWiki\Actions;

use MediaWikiTestCase;
use PermissionsError;
use ReadOnlyError;
use ReadOnlyMode;
use RequestContext;
use Title;
use EntitySchema\MediaWiki\Actions\SchemaEditAction;
use EntitySchema\Presentation\InputValidator;
use WikiPage;

/**
 * @covers \EntitySchema\MediaWiki\Actions\SchemaEditAction
 *
 * @license GPL-2.0-or-later
 */
class SchemaEditActionTest extends MediaWikiTestCase {

	/**
	 * @expectedException ReadOnlyError
	 */
	public function testReadOnly() {
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$action = new SchemaEditAction(
			new WikiPage( Title::newFromDBkey( 'E1' ) ),
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			new RequestContext()
		);

		$action->show();
	}

	/**
	 * @expectedException PermissionsError
	 */
	public function testNoRights() {
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions',
			[ '*' => [ 'edit' => false ] ] );
		$action = new SchemaEditAction(
			new WikiPage( Title::newFromDBkey( 'E1' ) ),
			$this->getMockBuilder( InputValidator::class )
				->disableOriginalConstructor()->getMock(),
			new RequestContext()
		);

		$action->show();
	}

}
