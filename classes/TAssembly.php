<?php namespace TAssembly;
use Exception;

/**
 * Template assembly language (tassembly) PHP runtime.
 *
 * @file
 * @ingroup Extensions
 * @copyright 2013; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

class TAssembly {
	/**
	 * Render an intermediate representation object into HTML
	 *
	 * @param string[] $ir
	 * @param string[] $model
	 * @param TAssemblyOptions $options
	 *
	 * @throws TAssemblyException if a subpart is not a 2-tuple, or if a control function is not known
	 *
	 * @return string HTML
	 */
	public static function render( array $ir, array $model = array(), TAssemblyOptions $options = null ) {
		if ( $options == null ) {
			$options = new TAssemblyOptions();
		}
		$context = TAssemblyContext::createRootContextFromModel( $model, $options );
		return TAssembly::render_context( $ir, $context );
	}

	protected static function render_context( array $ir, TAssemblyContext $context ) {
		$bits = array();

		foreach( $ir as $bit ) {
			if ( is_string( $bit ) ) {
				$bits[] = $bit;
			} elseif ( is_array( $bit ) && count( $bit ) === 2 ) {
				// Control function
				list( $ctlFn, $ctlOpts ) = $bit;
				if ( $ctlFn === 'text' ) {
					$val = TAssembly::evaluate_expression( $ctlOpts, $context );
					if ( is_null( $val ) ) {
						$val = '';
					}
					$bits[] = htmlspecialchars( $val, ENT_XML1 );
				} elseif ( is_callable( 'self::ctlFn_' . $ctlFn ) ) {
					$bits[] = call_user_func( 'self::ctlFn_' . $ctlFn, $ctlOpts, $context );
				} elseif ( array_key_exists( $ctlFn, $context->f ) ) {
					$bits[] = $context->f[$ctlFn]( $ctlOpts, $context );
				} else {
					throw new TAssemblyException( "Function '$ctlFn' does not exist in the context.", $bit );
				}
			} else {
				throw new TAssemblyException( 'Template operation must be either string or 2-tuple (function, args)', $bit );
			}
		}

		return join('', $bits);
	}

	/**
	 * Evaluate a simple expression.
	 *
	 * Note: This uses php eval(); we are relying on the compiler to
	 * make sure nothing dangerous is passed in.
	 *
	 * @param $expr
	 * @param TAssemblyContext $context
	 * @return mixed|string
	 */
	protected static function evaluate_expression( $expr, TAssemblyContext $context ) {
		// Simple variable
		$matches = array();
		if ( preg_match( '/^(m|p(?:[cm]s?)?|rm|i|c)(?:\.([a-zA-Z_$]+))?$/', $expr, $matches ) ) {
			list( $x, $member ) = $matches;
			$key = count($matches) == 3 ? $matches[2] : false;
			if ( $key && is_array( $context[$member] ) ) {
				return ( array_key_exists( $key, $context[$member] ) ?
					$context[$member][$key] : '' );
			} else {
				$res = $context[$member];
				return $res ? $res : '';
			}
		}

		// String literal
		if ( preg_match( '/^\'.*\'$/', $expr ) ) {
			return str_replace( '\\\'', '\'', substr( $expr, 1, -1 ) );
		}

		// More complex expression which must be rewritten to use PHP style accessors
		$newExpr = self::rewriteExpression( $expr );
		//echo $newExpr . "\n";
		$model = $context['m'];
		return eval('return ' . $newExpr . ';');
	}

	/**
	 * Rewrite a simple expression to be keyed on the context
	 *
	 * Allow objects { foo: 'basf', bar: contextVar.arr[5] }
	 *
	 * TODO: error checking for member access
	 */
	protected static function rewriteExpression( $expr ) {
		$result = '';
		$i = -1;
		$c = '';
		$len = strlen( $expr );
		$inArray = false;

		do {
			if ( preg_match( '/^$|[\[:(]/', $c ) ) {
				// Match the empty string (start of expression), or one of [, :, (
				if ( $inArray ) {
					// close the array reference
					$result .= "']";
					$inArray = false;
				}
				if ($c != ':') {
					$result .= $c;
				}
				$remainingExpr = substr( $expr, $i+1 );
				if ( preg_match( '/[pri]/', $expr[$i+1] )
					&& preg_match( '/(?:p[cm]s?|rm|i)(?:[\.\)\]}]|$)/', $remainingExpr ) )
				{
					// This is an expression referencing the parent, root, or iteration scopes
					$result .= "\$context['";
					$inArray = true;
				} else if ( preg_match( '/^m(\.)?/', $remainingExpr, $matches ) ) {
					if (count($matches) > 1) {
						$result .= "\$model['";
						$i += 2;
						$inArray = true;
					} else {
						$result .= '$model';
						$i++;
					}
				} else if ( $c == ':' ) {
					$result .= '=>';
				} else if ( preg_match('/^([a-zA-Z_$][a-zA-Z0-9_$]*):/',
								$remainingExpr, $match) )
				{
					// unquoted object key
					$result .= "'" . $match[1] . "'";
					$i += strlen($match[1]) + 2;
				}


			} elseif ( $c === "'") {
				// String literal, just skip over it and add it
				$match = array();
				preg_match( '/^(?:[^\\\']+|\\\')*\'/', substr( $expr, $i + 1 ), $match );
				if ( !empty( $match ) ) {
					$result .= $c . $match[0];
					$i += strlen( $match[0] );
				} else {
					throw new TAssemblyException( "Caught truncated string!" . $expr );
				}
			} elseif ( $c === "{" ) {
				// Object
				$result .= 'Array(';

				if ( preg_match('/^([a-zA-Z_$][a-zA-Z0-9_$]*):/',
					substr( $expr, $i+1 ), $match) )
				{
					// unquoted object key
					$result .= "'" . $match[1] . "'";
					$i += strlen($match[1]);
				}

			} elseif ( $c === "}" ) {
				// End of object
				$result .= ')';
			} elseif ( $c === "." ) {
				if ( $inArray ) {
					$result .= "']['";
				} else {
					$inArray = true;
					$result .= "['";
				}
			} else {
				// Anything else is sane as it conforms to the quite
				// restricted TAssembly spec, just pass it through
				$result .= $c;
			}

			$i++;
		} while ( $i < $len && $c = $expr[$i] );
		if ($inArray) {
			// close an open array reference
			$result .= "']";
		}
		return $result;
	}

	protected static function getTemplate($tpl, $ctx) {
		if (is_array($tpl)) {
			return $tpl;
		} else {
			// String literal: strip quotes
			$tpl = preg_replace('/^\'(.*)\'$/', '$1', $tpl);
			return $ctx->rc->options->partials[$tpl];
		}
	}

	protected static function ctlFn_foreach ($opts, $ctx) {
		$iterable = self::evaluate_expression($opts['data'], $ctx);
		if (!is_array($iterable)) {
			return '';
		}
		$bits = [];
		$newCtx = $ctx->createChildCtx(null);
		$len = count($iterable);
		for ($i = 0; $i < $len; $i++) {
			$newCtx->m = $iterable[$i];
			$newCtx->pms[0] = $iterable[$i];
			$newCtx->i = $i;
			$bits[] = self::render_context($opts['tpl'], $newCtx);
		}
		return join('', $bits);
	}

	protected static function ctlFn_template ($opts, $ctx) {
		$model = $opts['data'] ? self::evaluate_expression($opts['data'], $ctx) : $ctx->m;
		$tpl = self::getTemplate($opts['tpl'], $ctx);
		$newCtx = $ctx->createChildCtx($model);
		if ($tpl) {
			return self::render_context($tpl, $newCtx);
		}
	}

	protected static function ctlFn_with ($opts, $ctx) {
		$model = $opts['data'] ? self::evaluate_expression($opts['data'], $ctx) : $ctx->m;
		$tpl = self::getTemplate($opts['tpl'], $ctx);
		if ($model && $tpl) {
			$newCtx = $ctx->createChildCtx($model);
			return self::render_context($tpl, $newCtx);
		}
	}

	protected static function ctlFn_if ($opts, $ctx) {
		if (self::evaluate_expression($opts['data'], $ctx)) {
			return self::render_context($opts['tpl'], $ctx);
		}
	}

	protected static function ctlFn_ifnot ($opts, $ctx) {
		if (!self::evaluate_expression($opts['data'], $ctx)) {
			return self::render_context($opts['tpl'], $ctx);
		}
	}

	protected static function ctlFn_attr ($opts, $ctx) {
		foreach($opts as $name => $val) {
			if (is_string($val)) {
				$attVal = self::evaluate_expression($val, $ctx);
			} else {
				// must be an object
				$attVal = $val['v'] ? $val['v'] : '';
				if (is_array($val['app'])) {
					foreach ($val['app'] as $appItem) {
						if (array_key_exists('if', $appItem)
							&& self::evaluate_expression($appItem['if'], $ctx)) {
							$attVal .= $appItem['v'] ? $appItem['v'] : '';
						}
						if (array_key_exists('ifnot', $appItem)
							&& ! self::evaluate_expression($appItem['ifnot'], $ctx)) {
							$attVal .= $appItem['v'] ? $appItem['v'] : '';
						}
					}
				}
			}
			if (!$attVal && $val['v'] == null) {
				$attVal = null;
			}
			/*
			 * TODO: hook up sanitization to MW sanitizer via options?
			if ($attVal != null) {
				if ($name == 'href' || $name == 'src') {
					$attVal = self::sanitizeHref($attVal);
				} else if ($name == 'style') {
					$attVal = self::sanitizeStyle($attVal);
				}
			}
			 */
			if ($attVal != null) {
				return ' ' . $name . '="' .
					htmlspecialchars( $attVal, ENT_XML1 | ENT_COMPAT ) . '"';
			}
		}
	}
}

class TAssemblyOptions {
	public $partials = array();
	public $globals = array();
}

class TAssemblyException extends \Exception {
	public function __construct($message = "", $ir = '', $code = 0, Exception $previous = null) {
		parent::__construct($message,$code,$previous); // TODO: Change the autogenerated stub
	}
}

class TAssemblyContext implements \ArrayAccess {
	/** @var TAssemblyContext Root context object */
	public $rc;

	/** @var string[] Root model array */
	public $rm;

	/** @var string[] Array of references to parent models, [0] is the immediate parent. */
	public $pms;

	/** @var string[] Reference to the parent model */
	public $pm;

	/** @var TAssemblyContext[] Array of references to parent contexts. [0] is the immediate parent. */
	public $pcs;

	/** @var TAssemblyContext Reference to the parent context object */
	public $pc;

	/** @var string[] Model for the current context (holds locals) */
	public $m;

	/** @var TAssemblyOptions Reference to the global object for function calls */
	public $g;

	/** @var array() Array of functions (accessible only from the root object) */
	public $f;

	/** @var ??? uhh... this is an iterator... not yet gotten there */
	public $i;

	public static function createRootContextFromModel( $model, TAssemblyOptions $options ) {
		$ctx = new TAssemblyContext();
		$ctx->rm = $model;
		$ctx->m = &$ctx->rm;
		$ctx->pms = array();
		$ctx->pcs = array();
		$ctx->g = $options->globals;
		$ctx->options = $options;
		$ctx->f = array(); //&$ctx->g->functions;
		$ctx->rc = &$ctx;

		return $ctx;
	}

	public function createChildCtx ( $model ) {
		$ctx = new TAssemblyContext();
		$ctx->m = $model;
		$ctx->pc = $this;
		$ctx->pm = $this->m;
		$ctx->pms = array_merge(Array($model), $this->pms);
		$ctx->rm = $this->rm;
		$ctx->rc = $this->rc;
		$ctx->pcs = array_merge(Array($ctx), $this->pcs);
		return $ctx;
	}

	public function offsetExists( $offset ) {
		return isset( $this->$offset );
	}

	public function offsetGet( $offset ) {
		return $this->$offset;
	}

	public function offsetSet( $offset, $value ) {
		if ( property_exists( $this, $offset ) ) {
			$this->$offset = $value;
		} else {
			throw new TAssemblyException( "Can not set property '$offset' on TAssemblyContext object" );
		}
	}

	public function offsetUnset( $offset ) {
		$this->$offset = null;
	}

	protected function __construct() {
		// Just making sure we can only use generators to construct this object
	}
}
