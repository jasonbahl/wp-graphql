"use strict";(globalThis.webpackChunkwpgraphql_ide=globalThis.webpackChunkwpgraphql_ide||[]).push([[627,338,669],{3338:(t,e,i)=>{i.r(e),i.d(e,{C:()=>r,c:()=>c});var n=i(90);function o(t,e){for(var i=0;i<e.length;i++){const n=e[i];if("string"!=typeof n&&!Array.isArray(n))for(const e in n)if("default"!==e&&!(e in t)){const i=Object.getOwnPropertyDescriptor(n,e);i&&Object.defineProperty(t,e,i.get?i:{enumerable:!0,get:()=>n[e]})}}return Object.freeze(Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}))}(0,Object.defineProperty)(o,"name",{value:"_mergeNamespaces",configurable:!0});var s=(0,n.r)();const r=(0,n.g)(s),c=o({__proto__:null,default:r},[s])},2627:(t,e,i)=>{i.r(e);var n=i(3338),o=(i(9669),i(4919));i(90),n.C.registerHelper("hint","graphql",((t,e)=>{const{schema:i,externalFragments:s}=e;if(!i)return;const r=t.getCursor(),c=t.getTokenAt(r),l=null!==c.type&&/"|\w/.test(c.string[0])?c.start:c.end,a=new o.yX(r.line,l),h={list:(0,o.CE)(i,t.getValue(),a,c,s).map((t=>({text:t.label,type:t.type,description:t.documentation,isDeprecated:t.isDeprecated,deprecationReason:t.deprecationReason}))),from:{line:r.line,ch:l},to:{line:r.line,ch:c.end}};return null!=h&&h.list&&h.list.length>0&&(h.from=n.C.Pos(h.from.line,h.from.ch),h.to=n.C.Pos(h.to.line,h.to.ch),n.C.signal(t,"hasCompletion",t,h,c)),h}))},9669:(t,e,i)=>{i.r(e),i.d(e,{s:()=>l});var n=i(90),o=Object.defineProperty,s=(t,e)=>o(t,"name",{value:e,configurable:!0});function r(t,e){for(var i=0;i<e.length;i++){const n=e[i];if("string"!=typeof n&&!Array.isArray(n))for(const e in n)if("default"!==e&&!(e in t)){const i=Object.getOwnPropertyDescriptor(n,e);i&&Object.defineProperty(t,e,i.get?i:{enumerable:!0,get:()=>n[e]})}}return Object.freeze(Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}))}s(r,"_mergeNamespaces"),function(t){var e="CodeMirror-hint-active";function i(t,e){if(this.cm=t,this.options=e,this.widget=null,this.debounce=0,this.tick=0,this.startPos=this.cm.getCursor("start"),this.startLen=this.cm.getLine(this.startPos.line).length-this.cm.getSelection().length,this.options.updateOnCursorActivity){var i=this;t.on("cursorActivity",this.activityFunc=function(){i.cursorActivity()})}}t.showHint=function(t,e,i){if(!e)return t.showHint(i);i&&i.async&&(e.async=!0);var n={hint:e};if(i)for(var o in i)n[o]=i[o];return t.showHint(n)},t.defineExtension("showHint",(function(e){e=r(this,this.getCursor("start"),e);var n=this.listSelections();if(!(n.length>1)){if(this.somethingSelected()){if(!e.hint.supportsSelection)return;for(var o=0;o<n.length;o++)if(n[o].head.line!=n[o].anchor.line)return}this.state.completionActive&&this.state.completionActive.close();var s=this.state.completionActive=new i(this,e);s.options.hint&&(t.signal(this,"startCompletion",this),s.update(!0))}})),t.defineExtension("closeHint",(function(){this.state.completionActive&&this.state.completionActive.close()})),s(i,"Completion");var n=window.requestAnimationFrame||function(t){return setTimeout(t,1e3/60)},o=window.cancelAnimationFrame||clearTimeout;function r(t,e,i){var n=t.options.hintOptions,o={};for(var s in d)o[s]=d[s];if(n)for(var s in n)void 0!==n[s]&&(o[s]=n[s]);if(i)for(var s in i)void 0!==i[s]&&(o[s]=i[s]);return o.hint.resolve&&(o.hint=o.hint.resolve(t,e)),o}function c(t){return"string"==typeof t?t:t.text}function l(t,e){var i={Up:function(){e.moveFocus(-1)},Down:function(){e.moveFocus(1)},PageUp:function(){e.moveFocus(1-e.menuSize(),!0)},PageDown:function(){e.moveFocus(e.menuSize()-1,!0)},Home:function(){e.setFocus(0)},End:function(){e.setFocus(e.length-1)},Enter:e.pick,Tab:e.pick,Esc:e.close};/Mac/.test(navigator.platform)&&(i["Ctrl-P"]=function(){e.moveFocus(-1)},i["Ctrl-N"]=function(){e.moveFocus(1)});var n=t.options.customKeys,o=n?{}:i;function r(t,n){var r;r="string"!=typeof n?s((function(t){return n(t,e)}),"bound"):i.hasOwnProperty(n)?i[n]:n,o[t]=r}if(s(r,"addBinding"),n)for(var c in n)n.hasOwnProperty(c)&&r(c,n[c]);var l=t.options.extraKeys;if(l)for(var c in l)l.hasOwnProperty(c)&&r(c,l[c]);return o}function a(t,e){for(;e&&e!=t;){if("LI"===e.nodeName.toUpperCase()&&e.parentNode==t)return e;e=e.parentNode}}function h(i,n){this.id="cm-complete-"+Math.floor(Math.random(1e6)),this.completion=i,this.data=n,this.picked=!1;var o=this,s=i.cm,r=s.getInputField().ownerDocument,h=r.defaultView||r.parentWindow,u=this.hints=r.createElement("ul");u.setAttribute("role","listbox"),u.setAttribute("aria-expanded","true"),u.id=this.id;var p=i.cm.options.theme;u.className="CodeMirror-hints "+p,this.selectedHint=n.selectedHint||0;for(var f=n.list,d=0;d<f.length;++d){var g=u.appendChild(r.createElement("li")),m=f[d],v="CodeMirror-hint"+(d!=this.selectedHint?"":" "+e);null!=m.className&&(v=m.className+" "+v),g.className=v,d==this.selectedHint&&g.setAttribute("aria-selected","true"),g.id=this.id+"-"+d,g.setAttribute("role","option"),m.render?m.render(g,n,m):g.appendChild(r.createTextNode(m.displayText||c(m))),g.hintId=d}var y=i.options.container||r.body,b=s.cursorCoords(i.options.alignWithWord?n.from:null),w=b.left,A=b.bottom,C=!0,H=0,k=0;if(y!==r.body){var O=-1!==["absolute","relative","fixed"].indexOf(h.getComputedStyle(y).position)?y:y.offsetParent,S=O.getBoundingClientRect(),T=r.body.getBoundingClientRect();H=S.left-T.left-O.scrollLeft,k=S.top-T.top-O.scrollTop}u.style.left=w-H+"px",u.style.top=A-k+"px";var x=h.innerWidth||Math.max(r.body.offsetWidth,r.documentElement.offsetWidth),F=h.innerHeight||Math.max(r.body.offsetHeight,r.documentElement.offsetHeight);y.appendChild(u),s.getInputField().setAttribute("aria-autocomplete","list"),s.getInputField().setAttribute("aria-owns",this.id),s.getInputField().setAttribute("aria-activedescendant",this.id+"-"+this.selectedHint);var M,P=i.options.moveOnOverlap?u.getBoundingClientRect():new DOMRect,N=!!i.options.paddingForScrollbar&&u.scrollHeight>u.clientHeight+1;if(setTimeout((function(){M=s.getScrollInfo()})),P.bottom-F>0){var E=P.bottom-P.top;if(b.top-(b.bottom-P.top)-E>0)u.style.top=(A=b.top-E-k)+"px",C=!1;else if(E>F){u.style.height=F-5+"px",u.style.top=(A=b.bottom-P.top-k)+"px";var I=s.getCursor();n.from.ch!=I.ch&&(b=s.cursorCoords(I),u.style.left=(w=b.left-H)+"px",P=u.getBoundingClientRect())}}var R,W=P.right-x;if(N&&(W+=s.display.nativeBarWidth),W>0&&(P.right-P.left>x&&(u.style.width=x-5+"px",W-=P.right-P.left-x),u.style.left=(w=b.left-W-H)+"px"),N)for(var _=u.firstChild;_;_=_.nextSibling)_.style.paddingRight=s.display.nativeBarWidth+"px";s.addKeyMap(this.keyMap=l(i,{moveFocus:function(t,e){o.changeActive(o.selectedHint+t,e)},setFocus:function(t){o.changeActive(t)},menuSize:function(){return o.screenAmount()},length:f.length,close:function(){i.close()},pick:function(){o.pick()},data:n})),i.options.closeOnUnfocus&&(s.on("blur",this.onBlur=function(){R=setTimeout((function(){i.close()}),100)}),s.on("focus",this.onFocus=function(){clearTimeout(R)})),s.on("scroll",this.onScroll=function(){var t=s.getScrollInfo(),e=s.getWrapperElement().getBoundingClientRect();M||(M=s.getScrollInfo());var n=A+M.top-t.top,o=n-(h.pageYOffset||(r.documentElement||r.body).scrollTop);if(C||(o+=u.offsetHeight),o<=e.top||o>=e.bottom)return i.close();u.style.top=n+"px",u.style.left=w+M.left-t.left+"px"}),t.on(u,"dblclick",(function(t){var e=a(u,t.target||t.srcElement);e&&null!=e.hintId&&(o.changeActive(e.hintId),o.pick())})),t.on(u,"click",(function(t){var e=a(u,t.target||t.srcElement);e&&null!=e.hintId&&(o.changeActive(e.hintId),i.options.completeOnSingleClick&&o.pick())})),t.on(u,"mousedown",(function(){setTimeout((function(){s.focus()}),20)}));var j=this.getSelectedHintRange();return(0!==j.from||0!==j.to)&&this.scrollToActive(),t.signal(n,"select",f[this.selectedHint],u.childNodes[this.selectedHint]),!0}function u(t,e){if(!t.somethingSelected())return e;for(var i=[],n=0;n<e.length;n++)e[n].supportsSelection&&i.push(e[n]);return i}function p(t,e,i,n){if(t.async)t(e,n,i);else{var o=t(e,i);o&&o.then?o.then(n):n(o)}}function f(e,i){var n,o=e.getHelpers(i,"hint");if(o.length){var r=s((function(t,e,i){var n=u(t,o);function r(o){if(o==n.length)return e(null);p(n[o],t,i,(function(t){t&&t.list.length>0?e(t):r(o+1)}))}s(r,"run"),r(0)}),"resolved");return r.async=!0,r.supportsSelection=!0,r}return(n=e.getHelper(e.getCursor(),"hintWords"))?function(e){return t.hint.fromList(e,{words:n})}:t.hint.anyword?function(e,i){return t.hint.anyword(e,i)}:function(){}}i.prototype={close:function(){this.active()&&(this.cm.state.completionActive=null,this.tick=null,this.options.updateOnCursorActivity&&this.cm.off("cursorActivity",this.activityFunc),this.widget&&this.data&&t.signal(this.data,"close"),this.widget&&this.widget.close(),t.signal(this.cm,"endCompletion",this.cm))},active:function(){return this.cm.state.completionActive==this},pick:function(e,i){var n=e.list[i],o=this;this.cm.operation((function(){n.hint?n.hint(o.cm,e,n):o.cm.replaceRange(c(n),n.from||e.from,n.to||e.to,"complete"),t.signal(e,"pick",n),o.cm.scrollIntoView()})),this.options.closeOnPick&&this.close()},cursorActivity:function(){this.debounce&&(o(this.debounce),this.debounce=0);var t=this.startPos;this.data&&(t=this.data.from);var e=this.cm.getCursor(),i=this.cm.getLine(e.line);if(e.line!=this.startPos.line||i.length-e.ch!=this.startLen-this.startPos.ch||e.ch<t.ch||this.cm.somethingSelected()||!e.ch||this.options.closeCharacters.test(i.charAt(e.ch-1)))this.close();else{var s=this;this.debounce=n((function(){s.update()})),this.widget&&this.widget.disable()}},update:function(t){if(null!=this.tick){var e=this,i=++this.tick;p(this.options.hint,this.cm,this.options,(function(n){e.tick==i&&e.finishUpdate(n,t)}))}},finishUpdate:function(e,i){this.data&&t.signal(this.data,"update");var n=this.widget&&this.widget.picked||i&&this.options.completeSingle;this.widget&&this.widget.close(),this.data=e,e&&e.list.length&&(n&&1==e.list.length?this.pick(e,0):(this.widget=new h(this,e),t.signal(e,"shown")))}},s(r,"parseOptions"),s(c,"getText"),s(l,"buildKeyMap"),s(a,"getHintElement"),s(h,"Widget"),h.prototype={close:function(){if(this.completion.widget==this){this.completion.widget=null,this.hints.parentNode&&this.hints.parentNode.removeChild(this.hints),this.completion.cm.removeKeyMap(this.keyMap);var t=this.completion.cm.getInputField();t.removeAttribute("aria-activedescendant"),t.removeAttribute("aria-owns");var e=this.completion.cm;this.completion.options.closeOnUnfocus&&(e.off("blur",this.onBlur),e.off("focus",this.onFocus)),e.off("scroll",this.onScroll)}},disable:function(){this.completion.cm.removeKeyMap(this.keyMap);var t=this;this.keyMap={Enter:function(){t.picked=!0}},this.completion.cm.addKeyMap(this.keyMap)},pick:function(){this.completion.pick(this.data,this.selectedHint)},changeActive:function(i,n){if(i>=this.data.list.length?i=n?this.data.list.length-1:0:i<0&&(i=n?0:this.data.list.length-1),this.selectedHint!=i){var o=this.hints.childNodes[this.selectedHint];o&&(o.className=o.className.replace(" "+e,""),o.removeAttribute("aria-selected")),(o=this.hints.childNodes[this.selectedHint=i]).className+=" "+e,o.setAttribute("aria-selected","true"),this.completion.cm.getInputField().setAttribute("aria-activedescendant",o.id),this.scrollToActive(),t.signal(this.data,"select",this.data.list[this.selectedHint],o)}},scrollToActive:function(){var t=this.getSelectedHintRange(),e=this.hints.childNodes[t.from],i=this.hints.childNodes[t.to],n=this.hints.firstChild;e.offsetTop<this.hints.scrollTop?this.hints.scrollTop=e.offsetTop-n.offsetTop:i.offsetTop+i.offsetHeight>this.hints.scrollTop+this.hints.clientHeight&&(this.hints.scrollTop=i.offsetTop+i.offsetHeight-this.hints.clientHeight+n.offsetTop)},screenAmount:function(){return Math.floor(this.hints.clientHeight/this.hints.firstChild.offsetHeight)||1},getSelectedHintRange:function(){var t=this.completion.options.scrollMargin||0;return{from:Math.max(0,this.selectedHint-t),to:Math.min(this.data.list.length-1,this.selectedHint+t)}}},s(u,"applicableHelpers"),s(p,"fetchHints"),s(f,"resolveAutoHints"),t.registerHelper("hint","auto",{resolve:f}),t.registerHelper("hint","fromList",(function(e,i){var n,o=e.getCursor(),s=e.getTokenAt(o),r=t.Pos(o.line,s.start),c=o;s.start<o.ch&&/\w/.test(s.string.charAt(o.ch-s.start-1))?n=s.string.substr(0,o.ch-s.start):(n="",r=o);for(var l=[],a=0;a<i.words.length;a++){var h=i.words[a];h.slice(0,n.length)==n&&l.push(h)}if(l.length)return{list:l,from:r,to:c}})),t.commands.autocomplete=t.showHint;var d={hint:t.hint.auto,completeSingle:!0,alignWithWord:!0,closeCharacters:/[\s()\[\]{};:>,]/,closeOnPick:!0,closeOnUnfocus:!0,updateOnCursorActivity:!0,completeOnSingleClick:!0,container:null,customKeys:null,extraKeys:null,paddingForScrollbar:!0,moveOnOverlap:!0};t.defineOption("hintOptions",null)}((0,n.r)());var c={};const l=r({__proto__:null,default:(0,n.g)(c)},[c])}}]);