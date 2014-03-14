var TAssembly = require('tassembly').TAssembly,
	koCompiler = require('./KnockoutCompiler.js');

function KnockOff () {
	this.TA = new TAssembly();
}

KnockOff.prototype.compile = function(template, cb) {
	var templateASM = koCompiler.compile(template);
	return this.TA.compile(templateASM, cb);
};

KnockOff.prototype.registerPartial = function(name, template) {
	this.TA.partials[name] = koCompiler.compile(template);
};

module.exports = {
	KnockOff: KnockOff
};
