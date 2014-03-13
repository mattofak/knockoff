/*
 * Prototype JSON template IR evaluator
 *
 * Motto: Fast but safe!
 *
 * A string-based template representation that can be compiled from DOM-based
 * templates (knockoutjs syntax for example) and can statically enforce
 * balancing and contextual sanitization to prevent XSS, for example in href
 * and src attributes. The JSON format is compact, can easily be persisted and
 * can be evaluated with a tiny library (this file).
 *
 * Performance is on par with compiled handlebars templates, the fastest
 * string-based library in our tests.
 *
 * Input examples:
 * ['<div',['attr',{id:'id'}],'>',['text','body'],'</div>']
 * ['<div',['attr',{id:'id'}],'>',
 *	['foreach',{data:'m_items',tpl:['<div',['attr',{id:'key'}],'>',['text','val'],'</div>']}],
 * '</div>']
 */
"use strict";


function TAssembly () {
	this.uid = 0;
	// Cache for sub-structure parameters. Storing them globally keyed on uid
	// makes it possible to reuse compilations.
	this.cache = {};
	// Partials: tassembly objects
	this.partials = {};
}

TAssembly.prototype._getUID = function() {
	this.uid++;
	return this.uid;
};

/**
 * Call a callable, or return a plain value.
 *
 * If support for IE <= 8 is not needed we could also use
 * Object.defineProperty to define callables as getters instead for lower
 * overhead.
 */
TAssembly.prototype._maybeCall = function(val) {
	if (!val || val.constructor !== Function) {
		return val;
	} else {
		return val();
	}
};


TAssembly.prototype._evalExpr = function (expression, scope) {
	// Simple variable / fast path
	if (/^[a-zA-Z_]+$/.test(expression)) {
		return this._maybeCall(scope[expression]);
	}

	// String literal
	if (/^'.*'$/.test(expression)) {
		return this._maybeCall(expression.slice(1,-1).replace(/\\'/g, "'"));
	}

	// Dot notation
	if (/^[a-zA-Z_]+(?:[.][a-zA-Z_])+$/.test(expression)) {
		try {
			return this._maybeCall(new Function('scope', 'return scope.' + expression)(scope));
		} catch (e) {
			return '';
		}
	}

	// Don't want to allow full JS expressions for PHP compat & general
	// sanity. We could do the heavy sanitization work in the compiler & just
	// eval simple JS-compatible expressions here (possibly using 'with',
	// although that is deprecated & disabled in strict mode). For now we play
	// it safe & don't eval the expression. Can relax this later.
	return expression;
};

/*
 * Optimized _evalExpr stub for the code generator
 *
 * Directly dereference the scope for simple expressions (the common case),
 * and fall back to the full method otherwise.
 */
function evalExprStub(expr) {
	if (/^[a-zA-Z_]+$/.test(expr)) {
		// simple variable, the fast and common case
		// XXX: Omit this._maybeCall if not on IE (defineProperty available)
		return 'this._maybeCall(scope[' + JSON.stringify(expr) + '])';
	} else {
		return 'this._evalExpr(' + JSON.stringify(expr) + ', scope)';
	}
}

TAssembly.prototype._getTemplate = function (tpl, cb) {
	if (Array.isArray(tpl)) {
		return tpl;
	} else {
		// String literal: strip quotes
		if (/^'.*'$/.test(tpl)) {
			tpl = tpl.slice(1,-1).replace(/\\'/g, "'");
		}
		return this.partials[tpl];
	}
};

TAssembly.prototype.ctlFn_foreach = function(options, scope, cb) {
	// deal with options
	var iterable = this._evalExpr(options.data, scope),
		// worth compiling the nested template
		tpl = this.compile(this._getTemplate(options.tpl), cb),
		l = iterable.length;
	for(var i = 0; i < l; i++) {
		tpl(iterable[i]);
	}
};
TAssembly.prototype.ctlFn_template = function(options, scope, cb) {
	// deal with options
	var data = this._evalExpr(options.data, scope);
	this.render(this._getTemplate(options.tpl), data, cb);
};

TAssembly.prototype.ctlFn_with = function(options, scope, cb) {
	var val = this._evalExpr(options.data, scope);
	if (val) {
		this.render(this._getTemplate(options.tpl), val, cb);
	} else {
		// TODO: hide the parent element similar to visible
	}
};

TAssembly.prototype.ctlFn_if = function(options, scope, cb) {
	if (this._evalExpr(options.data, scope)) {
		this.render(options.tpl, scope, cb);
	}
};

TAssembly.prototype.ctlFn_ifnot = function(options, scope, cb) {
	if (!this._evalExpr(options.data, scope)) {
		this.render(options.tpl, scope, cb);
	}
};

TAssembly.prototype.ctlFn_attr = function(options, scope, cb) {
	var self = this;
	Object.keys(options).forEach(function(name) {
		var attVal = self._evalExpr(options[name], scope);
		if (attVal !== null) {
			cb(' ' + name + '="'
				// TODO: context-sensitive sanitization on href / src / style
				// (also in compiled version at end)
				+ attVal.toString().replace(/"/g, '&quot;')
				+ '"');
		}
	});
};

// Actually handled inline for performance
//TAssembly.prototype.ctlFn_text = function(options, scope, cb) {
//	cb(this._evalExpr(options, scope));
//};

TAssembly.prototype._xmlEncoder = function(c){
	switch(c) {
		case '<': return '&lt;';
		case '>': return '&gt;';
		case '&': return '&amp;';
		case '"': return '&quot;';
		default: return '&#' + c.charCodeAt() + ';';
	}
};

TAssembly.prototype._assemble = function(template, cb) {
	var code = [];
	code.push('var val;');
	if (!cb) {
		code.push('var res = "", cb = function(bit) { res += bit; };');
	}

	var self = this,
		l = template.length;
	for(var i = 0; i < l; i++) {
		var bit = template[i],
			c = bit.constructor;
		if (c === String) {
			// static string
			code.push('cb(' + JSON.stringify(bit) + ');');
		} else if (c === Array) {
			// control structure
			var fnName = bit[0];

			// Inline text and attr handlers for speed
			if (fnName === 'text') {
				code.push('val = ' + evalExprStub(bit[1]) + ';'
					+ 'val = !val && val !== 0 ? "" : "" + val;'
					+ 'if(!/[<&]/.test(val)) { cb(val); }'
					+ 'else { cb(val.replace(/[<&]/g,this._xmlEncoder)); };');
			} else if ( fnName === 'attr' ) {
				var names = Object.keys(bit[1]);
				for(var j = 0; j < names.length; j++) {
					var name = names[j];
					code.push('val = ' + evalExprStub(bit[1][name]) + ';');
					code.push("if (val !== null) { "
						// escape the attribute value
						// TODO: hook up context-sensitive sanitization for href,
						// src, style
						+ 'val = !val && val !== 0 ? "" : "" + val;'
						+ 'if(/[<&"]/.test(val)) { val = val.replace(/[<&"]/g,this._xmlEncoder); }'
						+ "cb(" + JSON.stringify(' ' + name + '="')
						+ " + val "
						+ "+ '\"');}");
				}
			} else {
				// Generic control function call

				// Store the args in the cache to a) keep the compiled code
				// small, and b) share compilations of sub-blocks between
				// repeated calls
				var uid = this._getUID();
				this.cache[uid] = bit[1];

				code.push('try {');
				// call the method
				code.push('this[' + JSON.stringify('ctlFn_' + bit[0])
						// store in cache / unique key rather than here
						+ '](this.cache["' + uid + '"], scope, cb);');
				code.push('} catch(e) {');
				code.push("console.error('Unsupported control function:', "
						+ JSON.stringify(bit[0]) + ", e.stack);");
				code.push('}');
			}
		} else {
			console.error('Unsupported type:', bit);
		}
	}
	if (!cb) {
		code.push("return res;");
	}
	return code.join('\n');
};

/**
 * Interpreted template expansion entry point
 *
 * @param {array} template The tassembly template in JSON IR
 * @param {object} scope the model
 * @param {function} cb (optional) chunk callback for bits of text (instead of
 * return)
 * @return {string} Rendered template string
 */
TAssembly.prototype.render = function(template, scope, cb) {
	var res;
	if (!cb) {
		res = [];
		cb = function(bit) {
			res.push(bit);
		};
	}

	// Just call a cached compiled version if available
	if (template.__cachedFn) {
		return template.__cachedFn.call(this, scope, cb);
	}

	var self = this,
		l = template.length;
	for(var i = 0; i < l; i++) {
		var bit = template[i],
			c = bit.constructor,
			val;
		if (c === String) {
			cb(bit);
		} else if (c === Array) {
			// control structure
			var fnName = bit[0];
			if (fnName === 'text') {
				val = this._evalExpr(bit[1], scope);
				if (!val && val !== 0) {
					val = '';
				}
				cb( ('' + val) // convert to string
						.replace(/[<&]/g, this._xmlEncoder)); // and escape
			} else if ( fnName === 'attr' ) {
				var keys = Object.keys(bit[1]),
					options = bit[1];
				for (var j = 0; j < keys.length; j++) {
					var name = keys[j];
					val = self._evalExpr(options[name], scope);
					if (val !== null) {
						if (!val && val !== 0) {
							val = '';
						}
						cb(' ' + name + '="'
							+ (''+val).replace(/[<&"]/g, this._xmlEncoder)
							+ '"');
					}
				}
			} else {

				try {
					self['ctlFn_' + bit[0]](bit[1], scope, cb);
				} catch(e) {
					console.error('Unsupported control function:', bit, e);
				}
			}
		} else {
			console.error('Unsupported type:', bit);
		}
	}
	if(res) {
		return res.join('');
	}
};


/**
 * Compile a template to a function
 *
 * @param {array} template The tassembly template in JSON IR
 * @param {function} cb (optional) chunk callback for bits of text (instead of
 * return)
 * @return {function} template function(model)
 */
TAssembly.prototype.compile = function(template, cb) {
	var self = this;
	if (template.__cachedFn) {
		//
		return function(scope) {
			return template.__cachedFn.call(self, scope, cb);
		};
	}
	var code = this._assemble(template, cb);
	//console.log(code);
	var fn = new Function('scope', 'cb', code);
	template.__cachedFn = fn;
	// bind this and cb
	var res = function (scope) {
		return fn.call(self, scope, cb);
	};
	return res;
};

module.exports = new TAssembly();
