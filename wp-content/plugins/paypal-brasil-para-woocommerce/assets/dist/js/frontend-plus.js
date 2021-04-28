/*! For license information please see frontend-plus.js.LICENSE.txt */
!function(e){var t={};function o(i){if(t[i])return t[i].exports;var r=t[i]={i:i,l:!1,exports:{}};return e[i].call(r.exports,r,r.exports,o),r.l=!0,r.exports}o.m=e,o.c=t,o.d=function(e,t,i){o.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:i})},o.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},o.t=function(e,t){if(1&t&&(e=o(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var i=Object.create(null);if(o.r(i),Object.defineProperty(i,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var r in e)o.d(i,r,function(t){return e[t]}.bind(null,r));return i},o.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return o.d(t,"a",t),t},o.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},o.p="/dist/",o(o.s=3)}({"./src/frontend/frontend-plus/frontend-plus.scss":function(e,t,o){},"./src/frontend/frontend-plus/frontend-plus.ts":function(e,t){new class{constructor(){this.onSubmitForm=e=>{const t=jQuery("#payment_method_"+wc_ppp_brasil_data.id+":checked");this.log("info","Checking if PayPal Payment method is checked..."),this.log("data",!!t.length),jQuery("#payment_method_"+wc_ppp_brasil_data.id).length||this.log("error","PayPal Plus check button wasn't detected. Should have an element #payment_method_"+wc_ppp_brasil_data.id),wc_ppp_brasil_data.order_pay&&this.$form.block({message:null,overlayCSS:{background:"#fff",opacity:.6}}),this.forceSubmit&&t.length?this.log("info","Form will be forced to submit."):t.length&&this.log("info","Form won't be forced to submit, will try to contact PayPal iframe first."),!this.forceSubmit&&t.length&&(e.preventDefault(),e.stopImmediatePropagation(),this.instance?this.instance.doContinue():this.log("error","We don't have the iframe instance, something wrong may have occurred. May be the fields isn't fulfilled."))},this.updateCheckout=(e=null)=>{e&&e.preventDefault(),this.triggerUpdateCheckout()},this.forceUpdateCheckout=(e=null)=>{e&&e.preventDefault(),this.log("info","Updating checkout..."),this.$body.trigger("update_checkout")},this.triggerUpdateCheckout=this.debounce(()=>{this.forceUpdateCheckout()},1e3),this.onUpdatedCheckout=()=>{this.$inputData=jQuery("#wc-ppp-brasil-data"),this.$inputResponse=jQuery("#wc-ppp-brasil-response"),this.$inputError=jQuery("#wc-ppp-brasil-error"),this.$inputSubmit=jQuery("#place_order"),this.$inputSubmit.length||this.log("error","Input submit wasn't found. Should have the #place_order element in the form."),this.$overlay=jQuery("#wc-ppb-brasil-container-overlay"),this.$loading=jQuery("#wc-ppp-brasil-container-loading"),this.$containerDummy=jQuery("#wc-ppp-brasil-container-dummy"),this.$overlay.on("click","[data-action=update-checkout]",this.updateCheckout),this.showOverlay();const e=this.$inputData.val(),t=jQuery("#wc-ppp-brasil-api-error-data").val();t&&(this.log("error","There was an error with following data:"),this.log("data",JSON.parse(t)));try{if(e){const t=JSON.parse(e);if(this.log("info","Creating iframe with data:"),this.log("data",t),0!==t.invalid.length){this.log("error","There's some invalid data. Iframe will render dummy version:"),this.log("data",t.invalid);let e="("+Object.values(t.invalid).join(", ")+")";this.$overlay.find("div.missing-items").html(e)}this.createIframe(t)}}catch(t){this.log("error","There was some error creating the iframe."),this.log("info","Data received:"),this.log("data",e),this.log("info","Error:"),this.log("data",t)}},this.messageListener=e=>{try{const t=JSON.parse(e.data);this.log("info","Received a message:"),this.log("data",t),void 0!==t.cause?(this.log("error","This message is an iframe error!"),this.treatIframeError(t)):this.treatIframeAction(t)}catch(e){}},this.forceSubmit=!1,this.log("heading","PayPal Plus logging enabled\n"),this.log("info","Backend data:"),this.log("data",wc_ppp_brasil_data),this.$body=jQuery(document.body),this.$body.length?this.log("info","HTML body detected."):this.log("error","HTML body didn't detected."),this.$form=wc_ppp_brasil_data.order_pay?jQuery("form#order_review"):jQuery("form.checkout.woocommerce-checkout"),wc_ppp_brasil_data.order_pay?this.log("info","Running script as order pay."):this.log("info","Running script as order review."),this.$form.length?(this.log("info","Detected form.checkout.woocommerce-checkout element."),this.log("data",this.$form)):this.log("error","Didn't detect form.checkout.woocommerce-checkout element."),this.listenInputChanges(),this.$body.on("updated_checkout",this.onUpdatedCheckout),this.$form.on("submit",this.onSubmitForm),this.$form.on("change","[name=payment_method]",this.forceUpdateCheckout),window.addEventListener("message",this.messageListener,!1),wc_ppp_brasil_data.order_pay&&jQuery((function(e){jQuery("body").trigger("updated_checkout")}))}listenInputChanges(){const e=["[name=billing_first_name]","[name=billing_last_name]","[name=billing_cpf]","[name=billing_cnpj]","[name=billing_phone]","[name=billing_address_1]","[name=billing_number]","[name=billing_address_2]","[name=billing_neighborhood]","[name=billing_city]","[name=billing_state]","[name=billing_country]","[name=billing_email]"],t=["[name=billing_persontype]"];jQuery(e.join(",")).on("keyup",()=>this.updateCheckout()),this.log("info","Listening for keyup to following elements:"),this.log("data",e),jQuery(t.join(",")).on("change",()=>this.updateCheckout()),this.log("info","Listening for change to following elements:"),this.log("data",t)}createIframe(e){if(e.dummy)this.$containerDummy.removeClass("hidden");else{this.hideOverlay(),this.showLoading();let t={approvalUrl:e.approval_url,placeholder:"wc-ppp-brasil-container",mode:wc_ppp_brasil_data.mode,payerFirstName:e.first_name,payerLastName:e.last_name,payerPhone:e.phone,language:wc_ppp_brasil_data.language,country:wc_ppp_brasil_data.country,payerEmail:e.email,rememberedCards:e.remembered_cards};wc_ppp_brasil_data.form_height&&(t.iframeHeight=wc_ppp_brasil_data.form_height),wc_ppp_brasil_data.show_payer_tax_id?(t.payerTaxId="1"===e.person_type?e.cpf:e.cnpj,t.payerTaxIdType="1"===e.person_type?"BR_CPF":"BR_CNPJ"):t.payerTaxId="",this.log("info","Settings for iframe:"),this.log("data",t),this.instance=PAYPAL.apps.PPP(t),this.$inputError.val(""),this.$inputResponse.val(""),this.forceSubmit=!1}}hideOverlay(){this.$overlay.addClass("hidden")}showOverlay(){this.$overlay.removeClass("hidden")}hideLoading(){}showLoading(){}treatIframeAction(e){switch(e.action){case"enableContinueButton":this.enableSubmitButton();break;case"disableContinueButton":this.disableSubmitButton();break;case"checkout":const t={payer_id:e.result.payer.payer_info.payer_id,remembered_cards_token:e.result.rememberedCards};this.log("info",["Continue allowed:",t]),this.log("info","Success message received from iframe:"),this.$inputResponse.val(JSON.stringify(t)),this.forceSubmitForm();break;case"onError":this.$inputResponse.val("");break;case"loaded":this.hideLoading()}}treatIframeError(e){const t=e.replace(/[^\sA-Za-z0-9_]+/g,"");switch(t){case"CHECK_ENTRY":this.showMessage('<div class="woocommerce-error">'+wc_ppp_brasil_data.messages.check_entry+"</div>");break;default:this.log("This message won't be treated, so form will be submitted."),this.$inputError.val(t),this.forceSubmitForm()}}disableSubmitButton(){this.$inputSubmit.prop("disabled",!0)}enableSubmitButton(){this.$inputSubmit.prop("disabled",!1)}forceSubmitForm(){this.forceSubmit=!0,this.$form.submit()}showMessage(e){const t=jQuery("form.checkout");t.length||this.log("error","Isn't possible to find the form.checkout element."),jQuery(".woocommerce-error, .woocommerce-message").remove(),e&&(t.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-updateOrderReview">'+e+"</div>"),t.find(".input-text, select, input:checkbox").blur(),jQuery("html, body").animate({scrollTop:t.offset().top-100},1e3))}debounce(e,t,o=!1){let i;return function(){const r=this,a=arguments,n=function(){i=null,o||e.apply(r,a)},s=o&&!i;clearTimeout(i),i=setTimeout(n,t),s&&e.apply(r,a)}}log(e,...t){if(wc_ppp_brasil_data.debug_mode)switch(e){case"heading":pwc().color("#003087").size(25).bold().log(t);break;case"log":pwc().log(t);break;case"info":pwc().bold().italic().color("#009cde").info(t);break;case"warn":pwc().warn(t);break;case"error":pwc().error(t);break;case"data":t.forEach(e=>console.log(e));break;case"custom-message":pwc().color("#012169").bold().italic().log(t)}}}},3:function(e,t,o){o("./src/frontend/frontend-plus/frontend-plus.ts"),e.exports=o("./src/frontend/frontend-plus/frontend-plus.scss")}});
//# sourceMappingURL=frontend-plus.js.map