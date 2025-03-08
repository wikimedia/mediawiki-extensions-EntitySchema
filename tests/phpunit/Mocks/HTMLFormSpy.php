<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Mocks;

use MediaWiki\Context\IContextSource;
use MediaWiki\HTMLForm\HTMLForm;
use PHPUnit\Framework\Assert;

/**
 * @license GPL-2.0-or-later
 */
class HTMLFormSpy {

	private static HTMLForm $form;

	/**
	 * @param string $displayFormat
	 * @param array $descriptor
	 * @param IContextSource $context
	 * @param string $messagePrefix
	 * @return HTMLForm
	 */
	public static function factory(
		$displayFormat,
		$descriptor,
		IContextSource $context,
		$messagePrefix = ''
	): HTMLForm {
		self::$form = HTMLForm::factory( $displayFormat, $descriptor, $context, $messagePrefix );
		return self::$form;
	}

	public static function assertFormFieldData( array $expectedContent ): void {
		Assert::assertSame( $expectedContent, self::$form->mFieldData );
	}

}
