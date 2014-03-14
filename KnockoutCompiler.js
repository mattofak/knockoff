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
		bindOpts, ctlFn, ctlOpts,
		ret = {};

	/*
	 * attr
	 */
	if (bindObj.attr) {
		// remove same attributes from element
		Object.keys(bindObj.attr).forEach(function(name) {
			// XXX: don't do destructive updates on the DOM
			node.removeAttribute(name);
		});
		ret.attr = ['attr', bindObj.attr];
	}

	if (bindObj.visible || bindObj['with']) {
		if (!ret.attr) {
			ret.attr = ['attr',{}];
		}
		ret.attr[1].style = {
			// Implement visible as inline style for now; consider moving to
			// class / make this configurable
			v: ret.attr[1].style || null,
			app: [
			{
				'ifnot': bindObj.visible || bindObj['with'],
				v: 'display: none !important;'
			}
			]
		};
		// Don't set ret.content, which lets the compiler descend into it
		if (bindObj.visible) {
			return ret;
		}
	}

	/*
	 * Now for the content
	 */
	if (bindObj.text) {
		// replace content with text directive
		ret.content = ['text', bindObj.text];
		return ret;
	}

	// Special template functionality both inside
	// template: { foreach: dataSource }
	// or stand-alone as in foreach: { data: dataSource }
	var templateTriggers = ['foreach', 'with', 'if', 'ifnot'];
	// Descend into a template member if there is one
	bindOpts = bindObj.template || bindObj;
	ctlOpts = {};
	for (var i = 0; i <= templateTriggers.length; i++) {
		var trigger = templateTriggers[i];
		if (trigger in bindOpts) {
			ctlFn = trigger;
			ctlOpts.data = bindOpts[ctlFn] || bindOpts.data;
			if (trigger === 'foreach' && bindOpts.as) {
				ctlOpts.as = bindOpts.as + '';
			}
			if (!bindOpts.name) {
				ctlOpts.tpl = new DOMCompiler().compile(node, options);
			} else {
				// Only allow statically named templates defined on the model
				ctlOpts.tpl = bindOpts.name + '';
			}
			ret.content = [ctlFn, ctlOpts];
			return ret;
		}
	}

	// Simple template without foreach / with / if / ifnot
	if (bindObj.template) {
		ctlOpts.data = bindOpts.data;
		if (!bindOpts.name) {
			ctlOpts.tpl = new DOMCompiler().compile(node, options);
		} else {
			// Only allow statically named templates defined on the model
			ctlOpts.tpl = bindOpts.name + '';
		}
		ret.content = ['template', ctlOpts];
		return ret;
	}


	return ret;
}

/**
 * Compile a Knockout template to TAssembly JSON
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
