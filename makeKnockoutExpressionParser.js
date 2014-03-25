// Maintenance / build script:
// Re-generate the KnockoutJS expression parser from the grammar
// Called by npm publish and local npm install

var PEG = require('pegjs'),
	fs = require('fs'),
	grammar = fs.readFileSync('./KnockoutExpressionParser.pegjs', 'utf8'),
	parser = PEG.buildParser(grammar, {output:"source"});

console.log('Re-building KnockoutExpressionParser.js '
		+ 'from KnockoutExpressionParser.pegjs');
fs.writeFileSync('KnockoutExpressionParser.js', 'module.exports = ' + parser);
