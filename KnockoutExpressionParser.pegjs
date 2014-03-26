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

    function stringifyObject (obj) {
        if (obj.constructor === Object) {
            var res = '{',
                keys = Object.keys(obj);
            for (var i = 0; i < keys.length; i++) {
                var key = keys[i];
                if (i !== 0) {
                    res += ',';
                }
                if (/^[a-z_$][a-z0-9_$]*$/.test(key)) {
                    res += key + ':';
                } else {
                    res += "'" + key.replace(/'/g, "\\'") + "':";
                }
                res += stringifyObject(obj[key]);
            }
            res += '}';
            return res;
        } else {
            return obj.toString();
        }
    }
}

start = '{'? spc kvs:key_values spc '}'? { return kvs; }

key_values = 
    kv:key_value 
    kvs:(spc ',' spc kvv:key_value { return kvv; })* 
    { 
        var res = {};
        [kv].concat(kvs).forEach(function(tuple) {
            res[tuple[0]] = tuple[1];
        });
        return res;
    }

key_value = 
    k:(varname 
            // Unquote string
            / s:string { return s.slice(1,-1).replace(/\\'/g,"'"); } )
    spc ':' 
    spc v:expression spc 
    { return [k,v]; }

object = '{' spc kvs:key_values spc '}'
    { return kvs; }

expression = variable / object / string / number

variable = v:varpart vs:(spc '.' vp:varpart { return vp; })*
    {
        var vars = [v].concat(vs),
            res = vars[0];
        // Rewrite the first path component
        res = res[0] === '$' && ctxMap[res] 
                // local model access
                || 'm.' + res;

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
    { return vn + (rc || ''); }

varname = $([a-z_$]i [a-z0-9_$]i*)

arrayref = '[' spc e:expression spc ']'
    { return '[' + stringifyObject(e) + ']'; }

call = '(' spc p:parameters spc ')'
    { return '(' + p + ')'; }

parameters = p0:expression? ps:(spc ',' spc pn:expression { return pn; })*
    {
        var params = [p0 || ''].concat(ps);
        params = params.map(function(p) {
            return stringifyObject(p);
        });
        return params.join(','); 
    }

string = 
  (["] s:$([^"\\]+ / '\\"')* ["] 
    { return "'" + s.replace(/\\"/g, '"').replace(/'/g, "\\'") + "'"; } )
  / (['] s:$([^'\\]+ / "\\'")* ['] { return "'" + s + "'" } )

number = [0-9]+ ('.' [0-9]+)?
    { return Number(text()); }

spc = [ \t\n]*

/* Tabs do not mix well with the hybrid production syntax */
/* vim: set filetype=javascript expandtab ts=4 sw=4 cindent : */
