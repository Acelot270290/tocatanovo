/*! For license information please see frontend-shared.js.LICENSE.txt */
!function(t){var e={};function r(n){if(e[n])return e[n].exports;var a=e[n]={i:n,l:!1,exports:{}};return t[n].call(a.exports,a,a.exports,r),a.l=!0,a.exports}r.m=t,r.c=e,r.d=function(t,e,n){r.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:n})},r.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},r.t=function(t,e){if(1&e&&(t=r(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var n=Object.create(null);if(r.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var a in t)r.d(n,a,function(e){return t[e]}.bind(null,a));return n},r.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return r.d(e,"a",e),e},r.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},r.p="/dist/",r(r.s=2)}({"./src/frontend/frontend-shared.ts":function(t,e,r){"use strict";r.r(e),r.d(e,"PaypalPayments",(function(){return n}));class n{static scrollTop(){jQuery("html, body").animate({scrollTop:0},300)}static setNotices(t){jQuery(".woocommerce-notices-wrapper:first").html(t)}static makeRequest(t,e){const r={async:!0,crossDomain:!0,url:n.replaceVars(paypal_brasil_settings.paypal_brasil_handler_url,{ACTION:t}),method:"POST",dataType:"json",contentType:"application/json; charset=utf-8",data:JSON.stringify(e)};return jQuery.ajax(r)}static showDefaultButton(){jQuery("#paypal-brasil-button-container .default-submit-button").show(),jQuery("#paypal-brasil-button-container .paypal-submit-button").hide()}static showPaypalButton(){jQuery("#paypal-brasil-button-container .default-submit-button").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button").show()}static isPaypalPaymentsSelected(){return!!jQuery("#payment_method_paypal-brasil-spb-gateway:checked").length}static triggerUpdateCheckout(){jQuery(document.body).trigger("update_checkout")}static triggerUpdateCart(){jQuery(document.body).trigger("wc_update_cart")}static submitForm(){jQuery("form.woocommerce-checkout, form#order_review").submit()}static replaceVars(t,e){let r=t;for(let t in e)e.hasOwnProperty(t)&&(r=r.replace(new RegExp("{"+t+"}","g"),e[t]));return r}}},2:function(t,e,r){t.exports=r("./src/frontend/frontend-shared.ts")}});
//# sourceMappingURL=frontend-shared.js.map