<?php

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
	 * @param Array $options
	 *
	 * @throws TAssemblyException if a subpart is not a 2-tuple, or if a control function is not known
	 *
	 * @return string HTML
	 */
	public static function render( array &$ir, array &$model = array(), Array &$options = Array() ) {

		$ctx = Array (
			'rm' => &$model,
			'm' => &$model,
			'pm' => null,
			'pms' => array(),
			'g' => isset($options['globals']) ? $options['globals'] : Array(),
			'options' => &$options
		);
		$ctx['rc'] = &$ctx;

		return TAssembly::render_context( $ir, $ctx );
	}


	protected static function render_context( array &$ir, Array &$ctx ) {
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
					if ( preg_match( '/^m\.([a-zA-Z_$]+)$/', $ctlOpts, $matches) ) {
						$val = @$ctx['m'][$matches[1]];
					} else {
						$val = TAssembly::evaluate_expression( $ctlOpts, $ctx );
					}
					if ( ! is_null( $val ) ) {
						$bits .= htmlspecialchars( $val, ENT_NOQUOTES );
					}
				} elseif ( $ctlFn === 'attr' ) {
					foreach($ctlOpts as $name => &$val) {
						if (is_string($val)) {
							if ( preg_match( '/^m\.([a-zA-Z_$]+)$/', $val, $matches) ) {
								$attVal = @$ctx['m'][$matches[1]];
							} else {
								$attVal = TAssembly::evaluate_expression( $val, $ctx );
							}
						} else {
							// must be an object
							$attVal = $val['v'] ? $val['v'] : '';
							if (is_array($val['app'])) {
								foreach ($val['app'] as $appItem) {
									if (isset($appItem['if'])
										&& self::evaluate_expression($appItem['if'], $ctx)) {
											$attVal .= $appItem['v'] ? $appItem['v'] : '';
										}
									if (isset($appItem['ifnot'])
										&& ! self::evaluate_expression($appItem['ifnot'], $ctx)) {
											$attVal .= $appItem['v'] ? $appItem['v'] : '';
										}
								}
							}
							if (!$attVal && $val['v'] === null) {
								$attVal = null;
							}
						}
						/*
						 * TODO: hook up sanitization to MW sanitizer via options?
						 if ($attVal != null) {
							 if ($name === 'href' || $name === 'src') {
								 $attVal = self::sanitizeHref($attVal);
							 } else if ($name === 'style') {
								 $attVal = self::sanitizeStyle($attVal);
							 }
						 }
						 */
						if ($attVal != null) {
							$escaped = htmlspecialchars( $attVal, ENT_COMPAT ) . '"';
							$bits .= ' ' . $name . '="' . $escaped;
						}
					}
				} elseif ( isset($builtins[$ctlFn]) ) {
					$ctlFn = 'ctlFn_' . $ctlFn;
					$bits .= self::$ctlFn( $ctlOpts, $ctx );
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
	protected static function evaluate_expression( &$expr, Array &$context ) {
		// Simple variable
		if ( preg_match( '/^m\.([a-zA-Z_$]+)$/', $expr, $matches) ) {
			return @$context['m'][$matches[1]];
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
		$newExpr = self::rewriteExpression( $expr );
		//echo "$expr\n$newExpr\n";
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
	protected static function rewriteExpression( &$expr ) {
		$result = '';
		$i = -1;
		$c = '';
		$len = strlen( $expr );
		$inArray = false;

		do {
			if ( preg_match( '/^$|[\[:(,]/', $c ) ) {
				// Match the empty string (start of expression), or one of '[:(,'
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
				} else if ( $c === ':' ) {
					$result .= '=>';
				} else if ( ( $c === '{' || $c === ',' )
					&& preg_match('/^([a-zA-Z_$][a-zA-Z0-9_$]*):/',
								$remainingExpr, $match) )
				{
					// unquoted object key
					$result .= "'" . $match[1] . "'";
					$i += strlen($match[1]);
				}


			} elseif ( $c === "'") {
				// String literal, just skip over it and add it
				$match = array();
				$remainingExpr = substr( $expr, $i+1 );
				preg_match( '/^(?:[^\\\\\']+|\\\\\'|[\\\\])*\'/', $remainingExpr, $match );
				if ( !empty( $match ) ) {
					$result .= $c . $match[0];
					$i += strlen( $match[0] );
				} else {
					throw new TAssemblyException( "Caught truncated string in " .
						json_encode($expr) );
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
			} else if ( $c === ')' && $inArray ) {
				if ( $inArray ) {
					$result .= "']";
					$inArray = false;
				}
				$result .= $c;
			} else {
				// Anything else is sane as it conforms to the quite
				// restricted TAssembly spec, just pass it through
				$result .= $c;
			}

			$i++;
			$c = @$expr[$i];
		} while ( $i < $len);
		if ($inArray) {
			// close an open array reference
			$result .= "']";
		}
		return $result;
	}

	protected static function createChildCtx ( &$parCtx, &$model ) {
		$ctx = Array(
			'm' => &$model,
			'pc' => &$parCtx,
			'pm' => &$parCtx['m'],
			'pms' => array_merge(Array($model), $parCtx['pms']),
			'rm' => &$parCtx['rm'],
			'rc' => &$parCtx['rc'],
		);
		return $ctx;
	}

	protected static function getTemplate(&$tpl, &$ctx) {
		if (is_array($tpl)) {
			return $tpl;
		} else {
			// String literal: strip quotes
			$tpl = preg_replace('/^\'(.*)\'$/', '$1', $tpl);
			return $ctx['rc']['options']['partials'][$tpl];
		}
	}

	protected static function ctlFn_foreach (&$opts, &$ctx) {
		$iterable = self::evaluate_expression($opts['data'], $ctx);
		if (!is_array($iterable)) {
			return '';
		}
		$bits = array();
		$newCtx = self::createChildCtx($ctx, $ctx);
		$len = count($iterable);
		for ($i = 0; $i < $len; $i++) {
			$newCtx['m'] = &$iterable[$i];
			$newCtx['pms'][0] = &$iterable[$i];
			$newCtx['i'] = $i;
			$bits[] = self::render_context($opts['tpl'], $newCtx);
		}
		return join('', $bits);
	}

	protected static function ctlFn_template (&$opts, &$ctx) {
		$model = $opts['data'] ? self::evaluate_expression($opts['data'], $ctx) : $ctx->m;
		$tpl = self::getTemplate($opts['tpl'], $ctx);
		$newCtx = self::createChildCtx($ctx, $model);
		if ($tpl) {
			return self::render_context($tpl, $newCtx);
		}
	}

	protected static function ctlFn_with (&$opts, &$ctx) {
		$model = $opts['data'] ? self::evaluate_expression($opts['data'], $ctx) : $ctx->m;
		$tpl = self::getTemplate($opts['tpl'], $ctx);
		if ($model && $tpl) {
			$newCtx = self::createChildCtx($ctx, $model);
			return self::render_context($tpl, $newCtx);
		}
	}

	protected static function ctlFn_if (&$opts, &$ctx) {
		if (self::evaluate_expression($opts['data'], $ctx)) {
			return self::render_context($opts['tpl'], $ctx);
		}
	}

	protected static function ctlFn_ifnot (&$opts, &$ctx) {
		if (!self::evaluate_expression($opts['data'], $ctx)) {
			return self::render_context($opts['tpl'], $ctx);
		}
	}
}

class TAssemblyException extends \Exception {
	public function __construct($message = "", $ir = '', $code = 0, Exception $previous = null) {
		parent::__construct($message,$code,$previous); // TODO: Change the autogenerated stub
	}
}
