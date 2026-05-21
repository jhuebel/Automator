// CodeMirror 5 — minimal HCL (HashiCorp Configuration Language) mode
// Supports: block keywords, atoms, strings with ${} interpolation,
//           line comments (# //), block comments (/* */), numbers.
(function (mod) {
    if (typeof exports === 'object' && typeof module === 'object') mod(require('../../lib/codemirror'));
    else if (typeof define === 'function' && define.amd) define(['../../lib/codemirror'], mod);
    else mod(CodeMirror);
})(function (CodeMirror) {
    'use strict';

    var blockKeywords = /^(?:resource|variable|output|locals|local|provider|terraform|data|module|for_each|count|dynamic|lifecycle|connection|provisioner|backend)\b/;
    var atoms        = /^(?:true|false|null)\b/;
    var builtins     = /^(?:var|local|module|data|path|each|count|self)\b/;

    CodeMirror.defineMode('hcl', function () {
        function tokenBase(stream, state) {
            // Block/line comments
            if (stream.match('/*')) { state.tokenize = tokenComment; return tokenComment(stream, state); }
            if (stream.match('#') || stream.match('//')) { stream.skipToEnd(); return 'comment'; }

            // Strings
            if (stream.match('"')) { state.tokenize = tokenString; return tokenString(stream, state); }

            // Heredoc <<EOF / <<-EOF
            if (stream.match(/^<<-?(\w+)/)) { state.heredocEnd = stream.string.match(/^<<-?(\w+)/)[1]; state.tokenize = tokenHeredoc; return 'string'; }

            // Numbers
            if (stream.match(/^-?(?:0x[\da-fA-F]+|\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/)) return 'number';

            // Keywords / atoms / builtins / identifiers
            if (stream.match(blockKeywords)) return 'keyword';
            if (stream.match(atoms))         return 'atom';
            if (stream.match(builtins))      return 'builtin';
            if (stream.match(/^[a-zA-Z_][\w-]*/)) return 'variable';

            // Operators and punctuation
            if (stream.match(/^[={}[\](),.:?]/)) return 'punctuation';
            if (stream.match(/^[+\-*/%<>!&|^~]/)) return 'operator';

            stream.next();
            return null;
        }

        function tokenString(stream, state) {
            var escaped = false, next, inInterp = false;
            while ((next = stream.next()) != null) {
                if (next === '"' && !escaped) { state.tokenize = tokenBase; break; }
                if (next === '$' && stream.peek() === '{') { inInterp = true; break; }
                escaped = !escaped && next === '\\';
            }
            return 'string';
        }

        function tokenComment(stream, state) {
            var prev, next;
            while ((next = stream.next()) != null) {
                if (prev === '*' && next === '/') { state.tokenize = tokenBase; break; }
                prev = next;
            }
            return 'comment';
        }

        function tokenHeredoc(stream, state) {
            if (stream.string.trim() === state.heredocEnd) {
                state.tokenize = tokenBase;
                state.heredocEnd = null;
            }
            stream.skipToEnd();
            return 'string';
        }

        return {
            startState: function () { return { tokenize: tokenBase, heredocEnd: null }; },
            token: function (stream, state) {
                if (stream.eatSpace()) return null;
                return state.tokenize(stream, state);
            },
            lineComment: '#',
            blockCommentStart: '/*',
            blockCommentEnd: '*/',
            fold: 'brace'
        };
    });

    CodeMirror.defineMIME('text/x-hcl', 'hcl');
});
