var c = require('./KnockoutCompiler.js'),
	ta = require('tassembly');

// Register a partial
ta.partials.testPartial = c.compile('<span data-bind="text:foo"></span><span data-bind="text:bar"></span>');

function test(input) {
		// compile the knockout template to TAssembly JSON
	var json = c.compile(input);
		// now compile the TAssembly JSON to a JS method
		// could also interpret it with ta.render(json, testData);
		//tpl = ta.compile(json);

	var testData = {
		items: [
			{
				key: 'key1',
				value: 'value1'
			},
			{
				key: 'key2',
				value: 'value2'
			}
		],
		obj: {
			foo: "foo",
			bar: "bar"
		},
		name: 'Some name',
		content: 'Some sample content',
		id: 'mw1234',
		predTrue: true,
		predFalse: false
	};

	console.log('=========================');
	console.log('Knockout template:');
	console.log(input);
	console.log('TAssembly JSON:');
	console.log(JSON.stringify(json, null, 2));
	console.log('Rendered HTML:');
	console.log(ta.render(json, testData));
}

test('<div data-bind="attr: {title: name}, foreach: items">'
				+ '<span data-bind="attr: {title: key}, text: value"></span></div>');

test('<div data-bind="if: predTrue">Hello world</div>');
test('<div data-bind="if: predFalse">Hello world</div>');
// constant string
test('<div data-bind="text: &quot;constant stri\'ng expression&quot;">Hello world</div>');

// constant number
test('<div data-bind="text: 2">Hello world</div>');

// arithmetic expression
test('<div data-bind="text: 2 + 2 &#x22;">Hello world</div>');

test('hello world<span>foo</span><div data-bind="text: content">ipsum</div>');

test('hello world<span>foo</span><div data-bind="with: obj"><span data-bind="text: foo">hopefully foo</span><span data-bind="text:bar">hopefully bar</span></div>');

test('hello world<div data-bind="template:{name:' + "'testPartial'" + ', data: obj}"></div>');

test('<div data-bind="visible:predFalse"><span data-bind="text:name"></span></div>');

test('<div data-bind="with:predFalse"><span data-bind="text:name"></span></div>');

test('<div data-bind="with:obj"><span data-bind="text:foo"></span></div>');
