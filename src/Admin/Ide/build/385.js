"use strict";(globalThis.webpackChunkwpgraphql_ide=globalThis.webpackChunkwpgraphql_ide||[]).push([[385,338],{3338:(e,r,t)=>{t.r(r),t.d(r,{C:()=>o,c:()=>s});var a=t(90);function n(e,r){for(var t=0;t<r.length;t++){const a=r[t];if("string"!=typeof a&&!Array.isArray(a))for(const r in a)if("default"!==r&&!(r in e)){const t=Object.getOwnPropertyDescriptor(a,r);t&&Object.defineProperty(e,r,t.get?t:{enumerable:!0,get:()=>a[r]})}}return Object.freeze(Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}))}(0,Object.defineProperty)(n,"name",{value:"_mergeNamespaces",configurable:!0});var i=(0,a.r)();const o=(0,a.g)(i),s=n({__proto__:null,default:o},[i])},8385:(e,r,t)=>{t.r(r);var a=t(3338),n=t(4919);t(90);const i=["error","warning","information","hint"],o={"GraphQL: Validation":"validation","GraphQL: Deprecation":"deprecation","GraphQL: Syntax":"syntax"};a.C.registerHelper("lint","graphql",((e,r)=>{const{schema:t,validationRules:s,externalFragments:c}=r;return(0,n.VS)(e,t,s,void 0,c).map((e=>({message:e.message,severity:e.severity?i[e.severity-1]:i[0],type:e.source?o[e.source]:void 0,from:a.C.Pos(e.range.start.line,e.range.start.character),to:a.C.Pos(e.range.end.line,e.range.end.character)})))}))}}]);