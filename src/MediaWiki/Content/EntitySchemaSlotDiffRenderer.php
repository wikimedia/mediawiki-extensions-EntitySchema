<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Content;

use Content;
use Diff\DiffOp\AtomicDiffOp;
use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOp;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Diff\EntitySchemaDiffer;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MessageLocalizer;
use SlotDiffRenderer;
use TextSlotDiffRenderer;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaSlotDiffRenderer extends SlotDiffRenderer {

	private EntitySchemaConverter $schemaConverter;

	private EntitySchemaDiffer $schemaDiffer;

	private TextSlotDiffRenderer $textSlotDiffRenderer;

	private MessageLocalizer $msgLocalizer;

	public function __construct(
		IContextSource $context,
		TextSlotDiffRenderer $textSlotDiffRenderer
	) {
		$this->schemaDiffer = new EntitySchemaDiffer();
		$this->schemaConverter = new EntitySchemaConverter();
		$this->textSlotDiffRenderer = $textSlotDiffRenderer;
		$this->msgLocalizer = $context;
	}

	/**
	 * @param EntitySchemaContent|null $oldContent
	 * @param EntitySchemaContent|null $newContent
	 *
	 * @return string
	 * @suppress PhanParamSignatureMismatch LSP violation
	 */
	public function getDiff( Content $oldContent = null, Content $newContent = null ): string {
		$this->normalizeContents( $oldContent, $newContent, EntitySchemaContent::class );

		$diff = $this->schemaDiffer->diffSchemas(
			$this->schemaConverter->getFullArraySchemaData( $oldContent->getText() ),
			$this->schemaConverter->getFullArraySchemaData( $newContent->getText() )
		);

		return $this->renderSchemaDiffRows( $diff );
	}

	public function localizeDiff( string $diff, array $options = [] ) {
		return $this->textSlotDiffRenderer->localizeDiff( $diff, $options );
	}

	public function renderSchemaDiffRows( Diff $diff ): string {
		// split $diff into labels/descriptions/aliases (renderDiffOp())
		// and schema (renderTextDiff())
		$nameBadgeDiffOps = [];
		if ( isset( $diff['labels'] ) ) {
			$nameBadgeDiffOps[
				$this->msgLocalizer->msg( 'entityschema-diff-label' )->text()
				] = $diff['labels'];
		}
		if ( isset( $diff['descriptions'] ) ) {
			$nameBadgeDiffOps[
				$this->msgLocalizer->msg( 'entityschema-diff-description' )->text()
			] = $diff['descriptions'];
		}
		if ( isset( $diff['aliases'] ) ) {
			$nameBadgeDiffOps[
				$this->msgLocalizer->msg( 'entityschema-diff-aliases' )->text()
			] = $diff['aliases'];
		}
		$nameBadgeDiff = $this->renderDiffOp( [], new Diff( $nameBadgeDiffOps, true ) );

		if ( isset( $diff['schemaText'] ) ) {
			$schemaDiff = $this->renderTextDiff(
				$this->msgLocalizer->msg( 'entityschema-diff-schema' )->text(),
				$diff['schemaText']
			);
		} else {
			$schemaDiff = '';
		}

		return $nameBadgeDiff . $schemaDiff;
	}

	private function renderDiffOp( array $keys, DiffOp $diffOp ): string {
		if ( $diffOp instanceof Diff ) {
			$output = '';
			foreach ( $diffOp->getOperations() as $key => $op ) {
				$moreKeys = $keys;
				$moreKeys[] = $key;
				$output .= $this->renderDiffOp( $moreKeys, $op );
			}
			return $output;
		}

		if ( $diffOp instanceof DiffOpRemove || $diffOp instanceof DiffOpChange ) {
			$leftContext = $this->diffContext( implode( ' / ', $keys ) );
			$leftTds = $this->diffRemovedLine( $diffOp->getOldValue() );
		} else {
			$leftContext = $this->diffContext( '' );
			$leftTds = $this->diffBlankLine();
		}

		if ( $diffOp instanceof DiffOpAdd || $diffOp instanceof DiffOpChange ) {
			$rightContext = $this->diffContext( implode( ' / ', $keys ) );
			$rightTds = $this->diffAddedLine( $diffOp->getNewValue() );
		} else {
			$rightContext = $this->diffContext( '' );
			$rightTds = $this->diffBlankLine();
		}

		$context = $this->diffRow( $leftContext . $rightContext );
		$changes = $this->diffRow( $leftTds . $rightTds );

		return $context . $changes;
	}

	/**
	 * @suppress PhanUndeclaredMethod
	 */
	private function renderTextDiff( string $key, AtomicDiffOp $diffOp ): string {
		if ( $diffOp instanceof DiffOpAdd || $diffOp instanceof DiffOpRemove ) {
			return $this->renderDiffOp( [ $key ], $diffOp );
		}

		/** @var DiffOpChange $diffOp */
		// Line 1 → schema / Line 1
		return preg_replace(
			'/<td[^>]* class="diff-lineno"[^>]*>/',
			'$0' . htmlspecialchars( $key ) . ' / ',
			$this->textSlotDiffRenderer->getTextDiff(
				trim( $diffOp->getOldValue() ),
				trim( $diffOp->getNewValue() )
			)
		);
	}

	private function diffRow( string $content ): string {
		return Html::rawElement(
			'tr',
			[],
			$content
		);
	}

	private function diffContext( string $context ): string {
		return Html::element(
			'td',
			[ 'colspan' => '2', 'class' => 'diff-lineno' ],
			$context
		);
	}

	private function diffBlankLine(): string {
		return Html::element( 'td', [ 'colspan' => '2' ] );
	}

	private function diffMarker( string $marker ): string {
		return Html::element(
			'td',
			[ 'class' => 'diff-marker', 'data-marker' => $marker ]
		);
	}

	private function diffAddedLine( string $line ): string {
		return $this->diffMarker( '+' ) . Html::element(
			'td',
			[ 'class' => 'diff-addedline' ],
			$line
		);
	}

	private function diffRemovedLine( string $line ): string {
		return $this->diffMarker( '−' ) . Html::element(
			'td',
			[ 'class' => 'diff-deletedline' ],
			$line
		);
	}

}
