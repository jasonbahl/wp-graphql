"use strict";(globalThis.webpackChunkwpgraphql_ide=globalThis.webpackChunkwpgraphql_ide||[]).push([[382],{2382:(t,e,r)=>{r.r(e),r.d(e,{m:()=>o});var n=r(90),a=r(294);function c(t,e){for(var r=0;r<e.length;r++){const n=e[r];if("string"!=typeof n&&!Array.isArray(n))for(const e in n)if("default"!==e&&!(e in t)){const r=Object.getOwnPropertyDescriptor(n,e);r&&Object.defineProperty(t,e,r.get?r:{enumerable:!0,get:()=>n[e]})}}return Object.freeze(Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}))}(0,Object.defineProperty)(c,"name",{value:"_mergeNamespaces",configurable:!0});var i=(0,a.r)();const o=c({__proto__:null,default:(0,n.g)(i)},[i])},294:(t,e,r)=>{r.d(e,{r:()=>l});var n,a=r(90),c=Object.defineProperty,i=(t,e)=>c(t,"name",{value:e,configurable:!0}),o={exports:{}};function l(){return n||(n=1,function(t){var e=/MSIE \d/.test(navigator.userAgent)&&(null==document.documentMode||document.documentMode<8),r=t.Pos,n={"(":")>",")":"(<","[":"]>","]":"[<","{":"}>","}":"{<","<":">>",">":"<<"};function a(t){return t&&t.bracketRegex||/[(){}[\]]/}function c(t,e,c){var i=t.getLineHandle(e.line),l=e.ch-1,s=c&&c.afterCursor;null==s&&(s=/(^| )cm-fat-cursor($| )/.test(t.getWrapperElement().className));var h=a(c),u=!s&&l>=0&&h.test(i.text.charAt(l))&&n[i.text.charAt(l)]||h.test(i.text.charAt(l+1))&&n[i.text.charAt(++l)];if(!u)return null;var f=">"==u.charAt(1)?1:-1;if(c&&c.strict&&f>0!=(l==e.ch))return null;var g=t.getTokenTypeAt(r(e.line,l+1)),m=o(t,r(e.line,l+(f>0?1:0)),f,g,c);return null==m?null:{from:r(e.line,l),to:m&&m.pos,match:m&&m.ch==u.charAt(0),forward:f>0}}function o(t,e,c,i,o){for(var l=o&&o.maxScanLineLength||1e4,s=o&&o.maxScanLines||1e3,h=[],u=a(o),f=c>0?Math.min(e.line+s,t.lastLine()+1):Math.max(t.firstLine()-1,e.line-s),g=e.line;g!=f;g+=c){var m=t.getLine(g);if(m){var d=c>0?0:m.length-1,p=c>0?m.length:-1;if(!(m.length>l))for(g==e.line&&(d=e.ch-(c<0?1:0));d!=p;d+=c){var k=m.charAt(d);if(u.test(k)&&(void 0===i||(t.getTokenTypeAt(r(g,d+1))||"")==(i||""))){var v=n[k];if(v&&">"==v.charAt(1)==c>0)h.push(k);else{if(!h.length)return{pos:r(g,d),ch:k};h.pop()}}}}}return g-c!=(c>0?t.lastLine():t.firstLine())&&null}function l(t,n,a){for(var o=t.state.matchBrackets.maxHighlightLineLength||1e3,l=a&&a.highlightNonMatching,s=[],h=t.listSelections(),u=0;u<h.length;u++){var f=h[u].empty()&&c(t,h[u].head,a);if(f&&(f.match||!1!==l)&&t.getLine(f.from.line).length<=o){var g=f.match?"CodeMirror-matchingbracket":"CodeMirror-nonmatchingbracket";s.push(t.markText(f.from,r(f.from.line,f.from.ch+1),{className:g})),f.to&&t.getLine(f.to.line).length<=o&&s.push(t.markText(f.to,r(f.to.line,f.to.ch+1),{className:g}))}}if(s.length){e&&t.state.focused&&t.focus();var m=i((function(){t.operation((function(){for(var t=0;t<s.length;t++)s[t].clear()}))}),"clear");if(!n)return m;setTimeout(m,800)}}function s(t){t.operation((function(){t.state.matchBrackets.currentlyHighlighted&&(t.state.matchBrackets.currentlyHighlighted(),t.state.matchBrackets.currentlyHighlighted=null),t.state.matchBrackets.currentlyHighlighted=l(t,!1,t.state.matchBrackets)}))}function h(t){t.state.matchBrackets&&t.state.matchBrackets.currentlyHighlighted&&(t.state.matchBrackets.currentlyHighlighted(),t.state.matchBrackets.currentlyHighlighted=null)}i(a,"bracketRegex"),i(c,"findMatchingBracket"),i(o,"scanForBracket"),i(l,"matchBrackets"),i(s,"doMatchBrackets"),i(h,"clearHighlighted"),t.defineOption("matchBrackets",!1,(function(e,r,n){n&&n!=t.Init&&(e.off("cursorActivity",s),e.off("focus",s),e.off("blur",h),h(e)),r&&(e.state.matchBrackets="object"==typeof r?r:{},e.on("cursorActivity",s),e.on("focus",s),e.on("blur",h))})),t.defineExtension("matchBrackets",(function(){l(this,!0)})),t.defineExtension("findMatchingBracket",(function(t,e,r){return(r||"boolean"==typeof e)&&(r?(r.strict=e,e=r):e=e?{strict:!0}:null),c(this,t,e)})),t.defineExtension("scanForBracket",(function(t,e,r,n){return o(this,t,e,r,n)}))}((0,a.r)())),o.exports}i(l,"requireMatchbrackets")}}]);