var KO = require('./knockoff.js'),
	c = require('./KnockoutCompiler');


var ko = new KO.KnockOff();
// Register a partial
ko.registerPartial('testPartial',
		'<span data-bind="text:foo"></span><span data-bind="text:bar"></span>');

var testData = {
	arr: [1,2,3,4,5,6,7],
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
	predFalse: false,
	test: function(i) {
		return i + 'test';
	}
};


function test(input) {
	console.log('=========================');
	console.log('Knockout template:');
	console.log(input);
	console.log('TAssembly JSON:');
	console.log(JSON.stringify(c.compile(input), null, 2));
	console.log('Rendered HTML:');
	var tpl = ko.compile(input);
	console.log(tpl(testData));
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

test('<div data-bind="attr:{id:id},foreach:items"><div data-bind="attr:{id:key},text:value"></div></div>');

test('<div data-bind="foreach:arr"><div data-bind="text:$parent.test($data)"></div></div>');

test('<div data-bind="text: test( { id : &quot;id&quot; } )"></div>');
