<?php

namespace EntitySchema\Tests\Integration\MediaWiki;

use EntitySchema\MediaWiki\SpecificLanguageMessageLocalizer;
use Language;
use MediaWikiIntegrationTestCase;
use Message;

/**
 * @covers \EntitySchema\MediaWiki\SpecificLanguageMessageLocalizer
 *
 * @license GPL-2.0-or-later
 */
class SpecificLanguageMessageLocalizerTest extends MediaWikiIntegrationTestCase {

	public function testMsg() {
		$messageLocalizer = new SpecificLanguageMessageLocalizer( 'qqx' );
		$this->setMwGlobals( 'wgLang', Language::factory( 'en' ) );

		$message = $messageLocalizer->msg( 'parentheses' )
			->plaintextParams( 'param' )
			->text();

		$this->assertSame( '(parentheses: param)', $message );
	}

	public function testMsg_variadicParams() {
		$messageLocalizer = new SpecificLanguageMessageLocalizer( 'qqx' );

		$message = $messageLocalizer->msg(
			'parentheses',
			Message::plaintextParam( 'param' )
		)->text();

		$this->assertSame( '(parentheses: param)', $message );
	}

	public function testMsg_arrayParams() {
		$messageLocalizer = new SpecificLanguageMessageLocalizer( 'qqx' );

		$message = $messageLocalizer->msg(
			'parentheses',
			[ Message::plaintextParam( 'param' ) ]
		)->text();

		$this->assertSame( '(parentheses: param)', $message );
	}

}
