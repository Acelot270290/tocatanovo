!function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="",n(n.s=26)}({1:function(e,t){!function(){e.exports=this.wp.i18n}()},26:function(e,t,n){"use strict";n.r(t);var r=n(7),o=n(1),i=n(4),c=n(6),u=Object(i.getSetting)("cod_data",{}),l=Object(o.__)("Cash on delivery",'woocommerce'),a=Object(c.decodeEntities)(u.title)||l,f=function(){return React.createElement("div",null,Object(c.decodeEntities)(u.description||""))},s=function(e){var t=e.components.PaymentMethodLabel;return React.createElement(t,{text:a})},d={name:"cod",label:React.createElement(s,null),content:React.createElement(f,null),edit:React.createElement(f,null),canMakePayment:function(e){var t=e.cartNeedsShipping,n=e.selectedShippingMethods;if(!u.enableForVirtual&&!t)return!1;if(!u.enableForShippingMethods.length)return!0;var r=Object.values(n);return u.enableForShippingMethods.some((function(e){return r.some((function(t){return t.includes(e)}))}))},ariaLabel:a};Object(r.registerPaymentMethod)(d)},4:function(e,t){!function(){e.exports=this.wc.wcSettings}()},6:function(e,t){!function(){e.exports=this.wp.htmlEntities}()},7:function(e,t){!function(){e.exports=this.wc.wcBlocksRegistry}()}});