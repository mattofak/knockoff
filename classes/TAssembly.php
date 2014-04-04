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

//class TAssembly {
	/**
	 * Render an intermediate representation object into HTML
	 *
	 * @param string[] $ir
	 * @param string[] $model
	 * @param Array $options
	 *
	 * @throws TAssemblyException if a subpart is not a 2-tuple, or if a control function is not known
	 *
	 * @return string HTML
	 */
	function render( array &$ir, array &$model = array(), Array &$options = null ) {
		if ( $options == null ) {
			$options = Array('globals' => array());
		}

		$ctx = Array (
			'rm' => &$model,
			'm' => &$model,
			'pm' => null,
			'pms' => array(),
			'pcs' => array(),
			'g' => isset($options['globals']) ? $options['globals'] : Array(),
			'options' => &$options
		);
		$ctx['rc'] = &$ctx;

		return render_context( $ir, $ctx );
	}


	function render_context( array &$ir, Array &$context ) {
		$bits = '';
		static $builtins = Array (
			'foreach' => true,
			'attr' => true,
			'if' => true,
			'ifnot' => true,
			'with' => true,
			'template' => true,
		);

		foreach ( $ir as $bit ) {
			if ( is_array( $bit ) ) {
				// Control function
				$ctlFn = $bit[0];
				$ctlOpts = $bit[1];
				if ( $ctlFn === 'text' ) {
					$val = evaluate_expression( $ctlOpts, $context );
					if ( ! is_null( $val ) ) {
						$bits .= htmlspecialchars( $val, ENT_NOQUOTES );
					}
				} elseif ( $ctlFn === 'attr' ) {
					$bits .= ctlFn_attr( $ctlOpts, $context );
				} elseif ( isset($builtins[$ctlFn]) ) {
					$ctlFn = 'ctlFn_' . $ctlFn;
					$bits .= $ctlFn( $ctlOpts, $context );
				} else {
					throw new TAssemblyException( "Function '$ctlFn' does not exist in the context.", $bit );
				}
			} else {
				$bits .= $bit;
			}
		}

		return $bits;
	}

	/**
	 * Evaluate an expression in the given context
	 *
	 * Note: This uses php eval(); we are relying on the compiler to
	 * make sure nothing dangerous is passed in.
	 *
	 * @param $expr
	 * @param Array $context
	 * @return mixed|string
	 */
	function evaluate_expression( &$expr, Array &$context ) {
		// Simple variable
		if ( preg_match( '/^m\.([a-zA-Z_$]+)$/', $expr, $matches) ) {
			return $context['m'][$matches[1]];
		}

		// String literal
		if ( preg_match( '/^\'.*\'$/', $expr ) ) {
			return str_replace( '\\\'', '\'', substr( $expr, 1, -1 ) );
		}

		// More complex var
		if ( preg_match( '/^(m|p(?:[cm]s?)?|rm|i|c)(?:\.([a-zA-Z_$]+))?$/', $expr, $matches ) ) {
			$x = $matches[0];
			$member = $matches[1];
			$key = isset($matches[2]) ? $matches[2] : false;
			if ( $key && is_array( $context[$member] ) ) {
				return ( array_key_exists( $key, $context[$member] ) ?
					$context[$member][$key] : '' );
			} else {
				$res = $context[$member];
				return $res ? $res : '';
			}
		}

		// More complex expression which must be rewritten to use PHP style accessors
		$newExpr = rewriteExpression( $expr );
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
	function rewriteExpression( &$expr ) {
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
					&& preg_match( '/(?:p[cm]s?|r[cm]|i)(?:[\.\)\]}]|$)/', $remainingExpr ) )
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

	function createChildCtx ( &$parCtx, &$model ) {
		$ctx = Array(
			'm' => $model,
			'pc' => $parCtx,
			'pm' => $parCtx['m'],
			'pms' => array_merge(Array($model), $parCtx['pms']),
			'rm' => $parCtx['rm'],
			'rc' => $parCtx['rc'],
		);
		$ctx['pcs'] = array_merge(Array($ctx), $parCtx['pcs']);
		return $ctx;
	}

	function getTemplate(&$tpl, &$ctx) {
		if (is_array($tpl)) {
			return $tpl;
		} else {
			// String literal: strip quotes
			$tpl = preg_replace('/^\'(.*)\'$/', '$1', $tpl);
			return $ctx['rc']['options']['partials'][$tpl];
		}
	}

	function ctlFn_foreach (&$opts, &$ctx) {
		$iterable = evaluate_expression($opts['data'], $ctx);
		if (!is_array($iterable)) {
			return '';
		}
		$bits = array();
		$newCtx = createChildCtx($ctx, null);
		$len = count($iterable);
		for ($i = 0; $i < $len; $i++) {
			$newCtx['m'] = $iterable[$i];
			$newCtx['pms'][0] = $iterable[$i];
			$newCtx['i'] = $i;
			$bits[] = render_context($opts['tpl'], $newCtx);
		}
		return join('', $bits);
	}

	function ctlFn_template (&$opts, &$ctx) {
		$model = $opts['data'] ? evaluate_expression($opts['data'], $ctx) : $ctx->m;
		$tpl = getTemplate($opts['tpl'], $ctx);
		$newCtx = createChildCtx($ctx, $model);
		if ($tpl) {
			return render_context($tpl, $newCtx);
		}
	}

	function ctlFn_with (&$opts, &$ctx) {
		$model = $opts['data'] ? evaluate_expression($opts['data'], $ctx) : $ctx->m;
		$tpl = getTemplate($opts['tpl'], $ctx);
		if ($model && $tpl) {
			$newCtx = createChildCtx($ctx, $model);
			return render_context($tpl, $newCtx);
		}
	}

	function ctlFn_if (&$opts, &$ctx) {
		if (evaluate_expression($opts['data'], $ctx)) {
			return render_context($opts['tpl'], $ctx);
		}
	}

	function ctlFn_ifnot (&$opts, &$ctx) {
		if (!evaluate_expression($opts['data'], $ctx)) {
			return render_context($opts['tpl'], $ctx);
		}
	}

	function ctlFn_attr (&$opts, &$ctx) {
		foreach($opts as $name => $val) {
			if (is_string($val)) {
				$attVal = evaluate_expression($val, $ctx);
			} else {
				// must be an object
				$attVal = $val['v'] ? $val['v'] : '';
				if (is_array($val['app'])) {
					foreach ($val['app'] as $appItem) {
						if (array_key_exists('if', $appItem)
							&& evaluate_expression($appItem['if'], $ctx)) {
							$attVal .= $appItem['v'] ? $appItem['v'] : '';
						}
						if (array_key_exists('ifnot', $appItem)
							&& ! evaluate_expression($appItem['ifnot'], $ctx)) {
							$attVal .= $appItem['v'] ? $appItem['v'] : '';
						}
					}
				}
				if (!$attVal && $val['v'] == null) {
					$attVal = null;
				}
			}
			/*
			 * TODO: hook up sanitization to MW sanitizer via options?
			if ($attVal != null) {
				if ($name == 'href' || $name == 'src') {
					$attVal = sanitizeHref($attVal);
				} else if ($name == 'style') {
					$attVal = sanitizeStyle($attVal);
				}
			}
			 */
			if ($attVal != null) {
				$escaped = htmlspecialchars( $attVal, ENT_COMPAT ) . '"';
				return ' ' . $name . '="' . $escaped;
			}
		}
	}
//}

class TAssemblyException extends \Exception {
	public function __construct($message = "", $ir = '', $code = 0, Exception $previous = null) {
		parent::__construct($message,$code,$previous); // TODO: Change the autogenerated stub
	}
}
