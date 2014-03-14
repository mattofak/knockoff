"use strict";
var TAssembly = require('tassembly').TAssembly,
	koCompiler = require('./KnockoutCompiler.js');

function KnockOff () {
	this.TA = new TAssembly();
}

/**
 * Compile a Knockout template to a function
 *
 * @param {misc} template HTML string or DOM node
 * @param {object} options
 * @returns {function(model)} function that can be called with a model and
 *							  returns an HTML string
 */
KnockOff.prototype.compile = function(template, options) {
	var templateASM = koCompiler.compile(template, options);
	return this.TA.compile(templateASM, options && options.cb);
};

/**
 * Register a partial (nested template)
 *
 * @param {string} name of the template
 * @param {misc} template HTML string or DOM node
 **/
KnockOff.prototype.registerPartial = function(name, template) {
	this.TA.partials[name] = koCompiler.compile(template);
};

module.exports = {
	KnockOff: KnockOff
};
