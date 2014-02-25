/**
 * Compile Knockout templates to quicktemplate JSON
 */
"use strict";

var DOMCompiler = require('./DOMCompiler.js'),
	KnockoutExpression = require('./KnockoutExpression.js'),
	domino = require('domino');

function handleNode(node, cb, options) {
	var dataBind = node.getAttribute('data-bind');
	if (!dataBind) {
		// let processing continue
		return {};
	}
	// XXX: keep this for client-side re-execution?
	//node.removeAttribute('data-bind');
	var bindObj = KnockoutExpression.parseObjectLiteral(dataBind),
		tpl,
		ret = {};
	// attr
	if (bindObj.attr) {
		// remove same attributes from element
		Object.keys(bindObj.attr).forEach(function(name) {
			// XXX: don't do destructive updates on the DOM
			node.removeAttribute(name);
		});
		ret.attr = ['attr', bindObj.attr];
	}

	if (bindObj.text) {
		// replace content with text directive
		ret.content = ['text', bindObj.text];
		return ret;
	}

	if (bindObj.foreach) {
		tpl = new DOMCompiler().compile(node, options);
		var foreachOptions = {
			data: bindObj.foreach,
			tpl: tpl
		};
		ret.content = ['foreach', foreachOptions];
		return ret;
	}

	if (bindObj['if'] || bindObj.ifnot) {

		var name = bindObj['if'] ? 'if' : 'ifnot';
		tpl = new DOMCompiler().compile(node, options);
		return {
			content: [name, {
				tpl: tpl,
				data: bindObj[name]
			}]
		};
	}
}

/**
 * Compile a Knockout template to QuickTemplate JSON
 *
 * Accepts either a template string or a DOM node.
 */
function compile (nodeOrString) {
	var options = {
		handlers: {
			'element': handleNode
		}
	},
		node = nodeOrString;

	// Build a DOM if string was passed in
	if (nodeOrString.constructor === String) {
		node = domino.createDocument(nodeOrString).body;
		// Include all children, but not <body> itself
		options.innerXML = true;
	}

	return new DOMCompiler().compile(node, options);
}

module.exports = {
	compile: compile
};
