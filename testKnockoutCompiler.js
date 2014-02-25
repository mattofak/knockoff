var c = require('./KnockoutCompiler.js'),
	qt = require('./quicktemplate.js');

function test(input) {
		// compile the knockout template to QT JSON
	var json = c.compile(input),
		// now compile the QT JSON to a JS method
		// could also interpret it with qt.render(json, testData);
		tpl = qt.compile(json);

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
		name: 'Some name',
		content: 'Some sample content',
		id: 'mw1234',
		predTrue: true,
		predFalse: false
	};

	console.log('=========================');
	console.log('Knockout template:');
	console.log(input);
	console.log('QT JSON:');
	console.log(JSON.stringify(json, null, 2));
	console.log('Rendered HTML:');
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

test('hello world<span>foo</span><div data-bind="text: content">ipsum</div>')
