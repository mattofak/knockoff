// KnockoutJS expression grammar with rewriting to TAssembly expressions

{
    var ctxMap = {
        '$data': 'm',
        '$root': 'rm',
        '$parent': 'p',
        '$parents': 'ps',
        '$parentContext': 'pc',
        '$index': 'i',
        '$context': 'c',
        '$rawData': 'd'
    };
}

start = '{'? spc kvs:key_values spc '}'? { return kvs; }

key_values = 
    kv:key_value 
    kvs:(spc ',' spc kvv:key_value { return kvv; })* 
    { return [kv].concat(kvs).join(','); }

key_value = 
    k:(varname / string) 
    spc ':' 
    spc v:expression spc 
    { return k + ':' + v; }

object = '{' spc kvs:key_values spc '}'
    { return '{' + kvs + '}'; }

expression = variable / string / number / object

variable = v:varpart vs:(spc '.' vp:varpart { return vp; })*
    {
        var vars = [v].concat(vs),
            res = vars[0];
        // Rewrite the first path component
        if (/^\$/.test(res) && ctxMap[res]) {
            res = ctxMap[res];
        } else {
            // local model access
            res = 'm.' + res;
        // XXX: be more selective about rewriting $context vars when not in
        // the first position?
        }
        // remaining path members
        for (var i = 1, l = vars.length; i < l; i++) {
            var v = vars[i];
            if (/^\$/.test(v) 
                    && (vars[i-1] === '$parentContext'
                        || vars[i-1] === '$context') 
                ) 
            {
                // only rewrite if previous path element can be a context
                res += '.' + (ctxMap[v] || v);
            } else {
                res += '.' + v;
            }
        }

        return res; 
    }

varpart = vn:varname rc:(arrayref / call)? 
    { var varName = vn + (rc || ''); return ctxMap['$' + varName] || varName; }

varname = fc:[a-z_$]i cs:$[a-z0-9_$]i*
    { return fc + cs; }

arrayref = '[' spc e:expression spc ']'
    { return '[' + e + ']'; }

call = '(' spc p:parameters ')'
    { return '(' + p + ')'; }

parameters = p0:expression? ps:(spc ',' spc pn:expression { return pn; })*
    { return [p0 || ''].concat(ps).join(','); }

string = 
  (["] s:$([^"\\]+ / '\\"')* ["] 
    { return "'" + s.replace(/\\"/g, '"').replace(/'/g, "\\'") + "'"; } )
  / (['] $([^'\\]+ / "\\'")* ['] { return "'" + s + "'" } )

number = [0-9]+ ('.' [0-9]+)?
    { return text(); }

spc = [ \t\n]*

/* Tabs do not mix well with the hybrid production syntax */
/* vim: set filetype=javascript expandtab ts=4 sw=4 cindent : */
