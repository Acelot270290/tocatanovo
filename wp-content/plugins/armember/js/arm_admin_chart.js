function arm_change_graph_type(r,e){jQuery(".armgraphtype_"+e).removeClass("selected"),jQuery("#armgraphtype_"+e+"_div_"+r).addClass("selected"),jQuery("#armgraphtype_"+e+"_"+r).prop("checked",!0),arm_change_graph(jQuery("#armgraphval_"+e).val(),e)}function arm_change_graph(r,a,e,t,_){(void 0===e||""==e||null==e||e<1)&&(e=1),void 0!==t&&""!=t&&null!=t||(t=!1),void 0!==_&&""!=_&&null!=_||(_="0"),jQuery("#armgraphval_"+a).val(r);var y=r,n=jQuery("input[name=armgraphtype_"+a+"]:checked").val(),m=jQuery("#arm_plan_filter").val(),l=jQuery("#arm_year_filter").val(),p="",u="",i="";"payment_report"==jQuery("#arm_report_type").val()&&(i=jQuery("#arm_gateway_filter").val()),"daily"==r?(u=jQuery("#arm_date_filter").val(),jQuery("#monthly_"+a+", #yearly_"+a).removeClass("active"),jQuery("#daily_"+a).addClass("active"),jQuery("#arm_year_filter_item").hide(),jQuery("#arm_month_filter_item").hide(),jQuery("#arm_date_filter_item").show()):"monthly"==r?(p=jQuery("#arm_month_filter").val(),jQuery("#daily_"+a+", #yearly_"+a).removeClass("active"),jQuery("#monthly_"+a).addClass("active"),jQuery("#arm_year_filter_item").show(),jQuery("#arm_month_filter_item").show(),jQuery("#arm_date_filter_item").hide()):"yearly"==r&&(jQuery("#monthly_"+a+", #daily_"+a).removeClass("active"),jQuery("#yearly_"+a).addClass("active"),jQuery("#arm_year_filter_item").show(),jQuery("#arm_month_filter_item").hide(),jQuery("#arm_date_filter_item").hide());var o=1==t?"armupdatereportgrid":"armupdatecharts";if("1"==_)return 0<jQuery(".arm_members_table_container .arm_all_loginhistory_wrapper .form-table.arm_member_last_subscriptions_table tbody tr").length&&(jQuery(".arm_loading").show(),jQuery("#arm_report_analytics_form").attr("onsubmit","").attr("action","#").attr("method","post"),jQuery("input[name='is_export_to_csv']").val("1"),jQuery("input[name='current_page']").val(e),jQuery("input[name='gateway_filter']").val(i),jQuery("input[name='date_filter']").val(u),jQuery("input[name='month_filter']").val(p),jQuery("input[name='year_filter']").val(l),jQuery("input[name='plan_id']").val(m),jQuery("input[name='plan_type']").val(a),jQuery("input[name='graph_type']").val(n),jQuery("input[name='type']").val(y),jQuery("input[name='action']").val(o),jQuery("input[name='arm_export_report_data']").val("1"),jQuery("#arm_report_analytics_form").submit(),jQuery("input[name='is_export_to_csv']").val("0"),jQuery("input[name='current_page']").val(""),jQuery("input[name='gateway_filter']").val(""),jQuery("input[name='date_filter']").val(""),jQuery("input[name='month_filter']").val(""),jQuery("input[name='year_filter']").val(""),jQuery("input[name='plan_id']").val(""),jQuery("input[name='plan_type']").val(""),jQuery("input[name='graph_type']").val(""),jQuery("input[name='type']").val(""),jQuery("input[name='action']").val(""),jQuery("input[name='arm_export_report_data']").val("0"),jQuery(".arm_loading").hide()),!1;jQuery.ajax({type:"POST",url:ajaxurl,beforeSend:function(){jQuery(".arm_loading").show()},data:"action="+o+"&type="+y+"&graph_type="+n+"&plan_type="+a+"&plan_id="+m+"&year_filter="+l+"&month_filter="+p+"&date_filter="+u+"&gateway_filter="+i+"&current_page="+e,success:function(r){var e;jQuery(".arm_loading").hide(),t?(e=r.split("[ARM_REPORT_SEPARATOR]"),"payment_history"==a?(jQuery(".arm_payments_table_body_content").html(e[0]),jQuery("#arm_payments_table_paging").html(e[1])):"pay_per_post_report"==a?(jQuery(".arm_pay_per_post_report_table_body_content").html(e[0]),jQuery("#arm_payments_table_paging").html(e[1])):(jQuery(".arm_members_table_body_content").html(e[0]),jQuery("#arm_members_table_paging").html(e[1]))):jQuery("#chart_container_"+a).html(r)}})}function arm_change_graph_pre(r,e,a){if(0==e)return!1;var t,_,y,n,m,l,p,u,i,o,h,j,Q,v=jQuery("input[name=armgraphtype_"+a+"]:checked").val(),d=jQuery("#arm_plan_filter").val(),c=jQuery("#arm_year_filter").val(),s="",g="";"payment_report"==jQuery("#arm_report_type").val()&&(g=jQuery("#arm_gateway_filter").val()),"yearly"==r?(c="",t=(p=jQuery("#"+r+"_"+a+" #current_year").val())-1,jQuery("#arm_year_filter_item .arm_year_filter:input").val(t),l=jQuery("#arm_year_filter_item").find('[data-value="'+t+'"]').html(),jQuery("#arm_year_filter_item").find("span").html(l),jQuery("#current_year").val(t),Q="&new_year="+t):"monthly"==r?(s=jQuery("#arm_month_filter").val(),p=jQuery("#"+r+"_"+a+" #current_month").val(),_=jQuery("#"+r+"_"+a+" #current_month_year").val(),y=parseInt(p),n=parseInt(_),1==y?(--n,y=12):--y,jQuery("#arm_month_filter_item .arm_month_filter:input").val(y),m=jQuery("#arm_month_filter_item").find('[data-value="'+y+'"]').html(),jQuery("#arm_month_filter_item").find("span").html(m),jQuery("#arm_year_filter_item .arm_year_filter:input").val(n),l=jQuery("#arm_year_filter_item").find('[data-value="'+n+'"]').html(),jQuery("#arm_year_filter_item").find("span").html(l),jQuery("#"+r+"_"+a+" #current_month").val(y),jQuery("#"+r+"_"+a+" #current_month_year").val(n),Q="&new_month="+y+"&new_month_year="+n):"daily"==r&&(p=jQuery("#"+r+"_"+a+" #current_day").val(),u=jQuery("#"+r+"_"+a+" #current_day_month").val(),i=jQuery("#"+r+"_"+a+" #current_day_year").val(),j=parseInt(p),o=parseInt(u),h=parseInt(i),j=1==p?(0==--o&&(o=12),new Date(h,o,0).getDate()):parseInt(p)-1,1==u&&1==p&&--h,jQuery("#"+r+"_"+a+" #current_day").val(j),jQuery("#"+r+"_"+a+" #current_day_month").val(o),jQuery("#"+r+"_"+a+" #current_day_year").val(h),Q="&new_day="+j+"&new_day_month="+o+"&new_day_year="+h),jQuery.ajax({type:"POST",url:ajaxurl,beforeSend:function(){jQuery(".arm_loading").show()},data:"action=armupdatecharts&type="+r+"&calculate=pre"+Q+"&graph_type="+v+"&plan_type="+a+"&plan_id="+d+"&year_filter="+c+"&month_filter="+s+"&date_filter=&gateway_filter="+g,success:function(r){jQuery(".arm_loading").hide(),jQuery("#chart_container_"+a).html(r)}})}function arm_change_graph_next(r,e,a){if(0==e)return!1;var t,_,y,n,m,l,p,u,i,o,h,j,Q,v,d=jQuery("input[name=armgraphtype_"+a+"]:checked").val(),c=jQuery("#arm_plan_filter").val(),s=jQuery("#arm_year_filter").val(),g="",f="";"payment_report"==jQuery("#arm_report_type").val()&&(f=jQuery("#arm_gateway_filter").val()),"yearly"==r?(s="",p=jQuery("#"+r+"_"+a+" #current_year").val(),t=parseInt(p)+1,jQuery("#arm_year_filter_item .arm_year_filter:input").val(t),l=jQuery("#arm_year_filter_item").find('[data-value="'+t+'"]').html(),jQuery("#arm_year_filter_item").find("span").html(l),jQuery("#"+r+"_"+a+" #current_year").val(t),v="&new_year="+t):"monthly"==r?(g=jQuery("#arm_month_filter").val(),p=jQuery("#"+r+"_"+a+" #current_month").val(),_=jQuery("#"+r+"_"+a+" #current_month_year").val(),y=parseInt(p),n=parseInt(_),12==y?(n+=1,y=1):y+=1,jQuery("#arm_month_filter_item .arm_month_filter:input").val(y),m=jQuery("#arm_month_filter_item").find('[data-value="'+y+'"]').html(),jQuery("#arm_month_filter_item").find("span").html(m),jQuery("#arm_year_filter_item .arm_year_filter:input").val(n),l=jQuery("#arm_year_filter_item").find('[data-value="'+n+'"]').html(),jQuery("#arm_year_filter_item").find("span").html(l),jQuery("#"+r+"_"+a+" #current_month").val(y),jQuery("#"+r+"_"+a+" #current_month_year").val(n),v="&new_month="+y+"&new_month_year="+n):"daily"==r&&(p=jQuery("#"+r+"_"+a+" #current_day").val(),u=jQuery("#"+r+"_"+a+" #current_day_month").val(),i=jQuery("#"+r+"_"+a+" #current_day_year").val(),j=parseInt(p),Q=parseInt(u),h=parseInt(i),j=p==(o=new Date(h,Q,0).getDate())?(Q+=1,1):parseInt(p)+1,12==u&&p==o&&(h+=1,Q=j=1),jQuery("#"+r+"_"+a+" #current_day").val(j),jQuery("#"+r+"_"+a+" #current_day_month").val(Q),jQuery("#"+r+"_"+a+" #current_day_year").val(h),v="&new_day="+j+"&new_day_month="+Q+"&new_day_year="+h),jQuery.ajax({type:"POST",url:ajaxurl,beforeSend:function(){jQuery(".arm_loading").show()},data:"action=armupdatecharts&type="+r+"&calculate=next"+v+"&graph_type="+d+"&plan_type="+a+"&plan_id="+c+"&year_filter="+s+"&month_filter="+g+"&date_filter=&gateway_filter="+f,success:function(r){jQuery(".arm_loading").hide(),jQuery("#chart_container_"+a).html(r)}})}function arm_change_login_hisotry_report(r){jQuery("#arm_login_history_type").val(r),jQuery(".btn_chart_type").removeClass("active"),jQuery("#"+r).addClass("active"),jQuery(".arm_login_history_page_search_btn").trigger("click")}jQuery(document).ready(function(){var r,e=jQuery("#arm_report_type").val();jQuery("#armgraphval_members").val(),jQuery("#armgraphval_members_plan").val(),jQuery("#armgraphval_payment_history").val();"member_report"==e?arm_change_graph(jQuery("#armgraphval_members").val(),"members"):"payment_report"==e?arm_change_graph(jQuery("#armgraphval_payment_history").val(),"payment_history"):"pay_per_post_report"==e&&arm_change_graph(jQuery("#armgraphval_pay_per_post_report").val(),"pay_per_post_report"),jQuery.isFunction(jQuery().datetimepicker)&&(r=new Date,jQuery(".arm_datepicker_filter").datetimepicker({defaultDate:r,useCurrent:!1,format:"YYYY-MM-DD"})),jQuery("#arm_report_export_button").click(function(){"member_report"==e?0<jQuery("tbody.arm_members_table_body_content tr").length&&jQuery("tbody.arm_members_table_body_content tr .arm_report_grid_no_data").length<=0&&arm_change_graph(jQuery("#armgraphval_members").val(),"members","","","1"):"payment_report"==e?0<jQuery("tbody.arm_payments_table_body_content tr").length&&jQuery("tbody.arm_payments_table_body_content tr .arm_report_grid_no_data").length<=0&&arm_change_graph(jQuery("#armgraphval_payment_history").val(),"payment_history","","","1"):"pay_per_post_report"==e&&0<jQuery("tbody.arm_pay_per_post_report_table_body_content tr").length&&jQuery("tbody.arm_pay_per_post_report_table_body_content tr .arm_report_grid_no_data").length<=0&&arm_change_graph(jQuery("#armgraphval_pay_per_post_report").val(),"pay_per_post_report","","","1")})}),jQuery(document).on("click","#arm_report_apply_filter_button",function(){var r=jQuery("#arm_report_type").val();jQuery("#armgraphval_members").val(),jQuery("#armgraphval_members_plan").val(),jQuery("#armgraphval_payment_history").val();"member_report"==r?arm_change_graph(jQuery("#armgraphval_members").val(),"members"):"payment_report"==r?arm_change_graph(jQuery("#armgraphval_payment_history").val(),"payment_history"):"pay_per_post_report"==r&&arm_change_graph(jQuery("#armgraphval_pay_per_post_report").val(),"pay_per_post_report")}),jQuery(document).on("click",".arm_report_analytics_content .arm_page_numbers:not(.dots)",function(){var r=jQuery("#arm_report_type").val(),e=jQuery(this).attr("data-page");"member_report"==r?(arm_graph_type=jQuery("#armgraphval_members").val(),arm_change_graph(arm_graph_type,"members",e,!0)):"payment_report"==r?(arm_graph_type=jQuery("#armgraphval_payment_history").val(),arm_change_graph(arm_graph_type,"payment_history",e,!0)):"pay_per_post_report"==r&&(arm_graph_type=jQuery("#armgraphval_pay_per_post_report").val(),arm_change_graph(arm_graph_type,"pay_per_post_report"))});