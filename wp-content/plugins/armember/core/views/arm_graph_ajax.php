<?php
global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans,$arm_payment_gateways, $arm_report_analytics, $arm_pay_per_post_feature;

$new_day = $new_month = $new_month_year = $new_year = $new_day_month = $new_day_year = '';

$plan_type = isset($_REQUEST['plan_type']) && !empty($_REQUEST['plan_type']) ? $_REQUEST['plan_type'] : '';

$current_month = date('m');
$current_year = date('Y');
$current_date = date('Y-m-d');

$global_currency = $arm_payment_gateways->arm_get_global_currency();
$all_currency = $arm_payment_gateways->arm_get_all_currencies();
$currency_symbol = $all_currency[strtoupper($global_currency)];


if (empty($plan_type)) return;
$graph_type = isset($_REQUEST['graph_type']) ? $_REQUEST['graph_type'] : '';
$type       = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
$filter_plan_id    = isset($_REQUEST['plan_id']) ? $_REQUEST['plan_id'] : '';
$filter_year    = isset($_REQUEST['year_filter']) ? $_REQUEST['year_filter'] : $current_year;
$filter_month    = isset($_REQUEST['month_filter']) ? $_REQUEST['month_filter'] : $current_month;
$filter_date    = isset($_REQUEST['date_filter']) ? $_REQUEST['date_filter'] : '';
$gateway_filter    = isset($_REQUEST['gateway_filter']) ? $_REQUEST['gateway_filter'] : "";
$current_page = isset($_REQUEST['current_page']) ? $_REQUEST['current_page'] : 1;


if (isset($_REQUEST['calculate']) && $_REQUEST['calculate'] != '' && ($_REQUEST['calculate'] == 'next' || $_REQUEST['calculate'] == 'pre')) {
    if (isset($_REQUEST['new_year']) && $_REQUEST['new_year'] != '') {
        $new_year = $_REQUEST['new_year'];
    } elseif (isset($_REQUEST['new_month']) && $_REQUEST['new_month'] != '') {
        $new_month      = $_REQUEST['new_month'];
        $new_month_year = $_REQUEST['new_month_year'];
    } elseif (isset($_REQUEST['new_day']) && $_REQUEST['new_day'] != '') {
        $new_day       = $_REQUEST['new_day'];
        $new_day_month = $_REQUEST['new_day_month'];
        $new_day_year  = $_REQUEST['new_day_year'];
    }
} else {
    $new_year       = date('Y', current_time('timestamp'));
    $new_month      = date('m', current_time('timestamp'));
    $new_month_year = date('Y', current_time('timestamp'));
    $new_day        = date('d');
    $new_day_month  = date('m');
    $new_day_year   = date('Y');
}


if(!empty($filter_date) && (!isset($_REQUEST['new_day']))) {
    $filter_date_exp = explode("-", $filter_date);
    $new_day        = $filter_date_exp[2];
    $new_day_month  = $filter_date_exp[1];
    $new_day_year   = $filter_date_exp[0];
} else {
    if(!empty($filter_month) && !isset($_REQUEST['new_month'])) {
        $monthName = $filter_month;
        $new_month = $filter_month;
    }

    if(!empty($filter_year) && !isset($_REQUEST['new_year'])) {
    //if(!empty($filter_year) && !isset($_REQUEST['new_year'])) {
        $new_month_year = $filter_year;
        $new_year = $filter_year;
        //$new_day_year = $filter_year;
    }    
}


$min_year  = date('Y', current_time('timestamp'));
$min_month = date('m', current_time('timestamp'));
$min_date  = date('d', current_time('timestamp'));

$user_table     = $wpdb->users;
$usermeta_table = $wpdb->usermeta;


if ($plan_type == "members") {
    $wp_date_time_format = "";
    if (is_multisite()) {
        $wp_date_time_format = get_option('date_format') . " " . get_option('time_format');
    } else {
        $wp_date_time_format = get_site_option('date_format') . " " . get_site_option('time_format');
    }

    $min_full_date = $wpdb->get_var("SELECT min(user_registered) FROM `{$user_table}`");
    if (!empty($min_full_date)) {
        $min_month = date('m', strtotime($min_full_date));
        $min_date  = date('d', strtotime($min_full_date));
    }
    
    $capability_column = $wpdb->get_blog_prefix($GLOBALS['blog_id']) . 'capabilities';
    $super_admin_ids   = array();
    if (is_multisite()) {
        $super_admin   = get_super_admins();
        if (!empty($super_admin)) {
            foreach ($super_admin as $skey => $sadmin) {
                if ($sadmin != '') {
                    $user_obj = get_user_by('login', $sadmin);
                    if ($user_obj->ID != '') {
                        $super_admin_ids[] = $user_obj->ID;
                    }
                }
            }
        }
    }

    $join_clause = "";
    $user_where = " WHERE 1=1";
    if (!empty($super_admin_ids)) {
        $user_where .= " AND u.ID NOT IN (" . implode(',', $super_admin_ids) . ")";
    }
   

    if(!empty($filter_plan_id)) {
        $join_clause = " LEFT JOIN `{$usermeta_table}` um1 ON u.ID = um1.user_id";
        $user_where .= " AND (um1.meta_key='arm_user_plan_ids' AND ( um1.meta_value LIKE '%:\"".$filter_plan_id."\";%' OR um1.meta_value LIKE '%i:".$filter_plan_id.";%') )";
    }

    $operator = " AND ";
    $user_where .= " {$operator} um.meta_key = '{$capability_column}' AND um.meta_value NOT LIKE '%administrator%' ";

    if ($type == "yearly") 
    {

        $from_year = "{$new_year}-01-01 00:00:00";
        $end_year  = "{$new_year}-12-31 23:59:59";

        $day_arr = $wpdb->get_results("SELECT YEAR(u.user_registered) AS Year, MONTH(u.user_registered) AS Month,COUNT(*) AS total FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered >= '{$from_year}' AND u.user_registered <= '{$end_year}' Group By YEAR(u.user_registered),  MONTH(u.user_registered) ORDER BY u.user_registered DESC", 'ARRAY_A');

        $arm_max_year_entry = 0;
        if (count($day_arr) > 0) {
            $totalRec = count($day_arr);
            foreach ($day_arr as $arr_month) {
                $month[$arr_month['Month']] = $arr_month['total'];
            }
            $arm_max_year_entry = 0;
            foreach ($month as $key => $val) {
                $arm_max_year_entry = max($arm_max_year_entry, $val);
            }
            if ($arm_max_year_entry < 5)
                $arm_max_year_entry = $arm_max_year_entry;
        }

        $arm_max_year = 0;
        if ($arm_max_year_entry < 5) {
            $arm_max_year = 5;
        }

        $arm_disable_class_next = '';
        $arm_enable_next = 1;
        if ($new_year >= date('Y', current_time('timestamp'))) {
            $arm_disable_class_next = 'arm_disabled_class_next';
            $arm_enable_next = 0;
        }

        $arm_disable_class_prev = '';
        $arm_enable_prev = 1;
        
        /*if ($new_year <= $min_year) {
            $arm_disable_class_prev = 'arm_disabled_class_prev';
            $arm_enable_prev = 0;
        }*/
        
        $monthToDisplay = '';
        for ($i = 1; $i <= 12; $i++) {
            if (empty($month[$i])) {
                if ($i == 12)
                    $monthToDisplay .= 0;
                else
                    $monthToDisplay .= "0,";
            }else {
                if ($i == 12)
                    $monthToDisplay .= $month[$i];
                else
                    $monthToDisplay .= $month[$i] . ",";
            }
        } 

        /*for data table*/
        $table_data_arr = array();
        $table_data_cnt = 0;
        $perPage = 10;
        $offset = ($current_page - 1) * $perPage;
        if($type == "yearly") 
        {
            $first_day = $from_year;
            $last_day = $end_year;

            $table_data_cnt = $wpdb->get_results("SELECT count(u.ID) FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered BETWEEN '".$first_day."' AND '".$last_day."' GROUP BY u.ID ORDER BY u.user_registered DESC", 'ARRAY_A');

            if($is_export_to_csv == true || $is_export_to_csv == '1') {
                $table_data_arr = $wpdb->get_results("SELECT u.ID,u.user_registered,u.user_login,u.user_email FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered BETWEEN '".$first_day."' AND '".$last_day."' GROUP BY u.ID ORDER BY u.user_registered DESC", 'ARRAY_A');    
            } else {
                $table_data_arr = $wpdb->get_results("SELECT u.ID,u.user_registered,u.user_login,u.user_email FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered BETWEEN '".$first_day."' AND '".$last_day."' GROUP BY u.ID ORDER BY u.user_registered DESC LIMIT ".$perPage." OFFSET ".$offset, 'ARRAY_A');        
            }
            
            $table_data_cnt = count($table_data_cnt);
            $dataPaging = $arm_global_settings->arm_get_paging_links($current_page, $table_data_cnt, $perPage, '');
        } 
        

        $table_content_html = "";
        $tab_row_cnt = 0;
        $arm_charts_plan_data=array();
        $table_data_cnt_arr = $wpdb->get_results("SELECT u.ID,u.user_registered,u.user_login,u.user_email FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered BETWEEN '".$first_day."' AND '".$last_day."' GROUP BY u.ID ORDER BY u.user_registered DESC", 'ARRAY_A');

        if(!empty($table_data_cnt_arr))
        {
            foreach($table_data_cnt_arr as $key => $table_data_cnt_val)
            {
                $arm_registered_date = date_i18n($wp_date_time_format, strtotime($table_data_cnt_val['user_registered']));
                $plan_name = "";
                $plan_arr = get_user_meta($table_data_cnt_val['ID'], "arm_user_plan_ids", true);
                $paid_post_ids = get_user_meta($table_data_cnt_val['ID'], 'arm_user_post_ids', true);
                
                if(!empty($paid_post_ids))
                {
                    foreach($plan_arr as $key => $val)
                    {
                        if(!empty($paid_post_ids[$val]))
                        {
                            unset($plan_arr[$key]);
                        }
                    }
                }
                if(!empty($plan_arr)) {

                    foreach ($plan_arr as $key => $plan) {
                        $arm_plan_name = $arm_subscription_plans->arm_get_plan_name_by_id($plan); 
                        if(!empty($arm_plan_name) && isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts"){
                            $arm_charts_plan_data[$plan]['plan_name']=$arm_plan_name;
                            $arm_charts_plan_data[$plan]['plan_users'][]=$table_data_cnt_val['ID'];
                        }
                        $plan_name .=$arm_plan_name;
                        $plan_name .= ", ";
                    }
                } else {
                    $plan_name = "-";
                }
            }
        }



        if(!empty($table_data_arr)) {
            if($is_export_to_csv == true || $is_export_to_csv == '1') {
                $arm_report_analytics->arm_export_report_to_csv($table_data_arr, $arm_report_type);
                exit;
            }
            foreach ($table_data_arr as $key => $table_data) {
                $arm_registered_date = date_i18n($wp_date_time_format, strtotime($table_data['user_registered']));
                $plan_name = "";
                $plan_arr = get_user_meta($table_data['ID'], "arm_user_plan_ids", true);
	            $paid_post_ids = get_user_meta($table_data['ID'], 'arm_user_post_ids', true);
                
		        if(!empty($paid_post_ids))
                {
                    foreach($plan_arr as $key => $val)
                    {
                        if(!empty($paid_post_ids[$val]))
                        {
                            unset($plan_arr[$key]);
                        }
                    }
                }
                if(!empty($plan_arr)) {

                    foreach ($plan_arr as $key => $plan) {
                        $arm_plan_name = $arm_subscription_plans->arm_get_plan_name_by_id($plan); 
                        $plan_name .=$arm_plan_name;
                        $plan_name .= ", ";
                    }
                } else {
                    $plan_name = "-";
                }

                $plan_name = rtrim($plan_name, ", ");

                $table_content_html .= "<tr class='arm_member_last_subscriptions_data'>";
                    $table_content_html .= "<td>".$table_data['user_login']."</td>"; 
                    $table_content_html .= "<td><a class='arm_openpreview_popup' href='javascript:void(0)' data-id='".$table_data['ID']."'>".$table_data['user_email']."</a></td>"; 
                    $table_content_html .= "<td>".$plan_name."</td>";  
                    $table_content_html .= "<td>".$arm_registered_date."</td>";
                $table_content_html .= "</tr>";
                $tab_row_cnt++;
            }
        } else {
            $table_content_html .= "<tr class='arm_member_last_subscriptions_data'>";
                $table_content_html .= "<th class='arm_report_grid_no_data' colspan='4'>".esc_html__( 'No records found', 'ARMember')."</th>";
            $table_content_html .= "</tr>";
        }
        if( $is_pagination ){
            echo $table_content_html.'[ARM_REPORT_SEPARATOR]'.str_replace("\n", "", $dataPaging); 
            die;
        } else {
            ?>
            <script type="text/javascript" data-cfasync="false">

                jQuery(".arm_members_table_body_content").html("<?php echo $table_content_html; ?>");
                jQuery("#arm_members_table_paging").html('<?php echo str_replace("\n","", $dataPaging); ?>');

                jQuery.noConflict();
                jQuery(document).ready(function($){
                    var chart_type = '';
                    var graph_type = "<?php echo $graph_type;?>";
                    if(graph_type == 'bar'){
                        chart_type ='column';
                    } else if(graph_type == 'line'){
                        chart_type ='areaspline';
                    }
                    var ticks_year = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    var year1 = [<?php echo $monthToDisplay; ?>];
                    if(graph_type == 'bar' || graph_type == 'line') {
                        if(graph_type =='bar') {
                            var armChart = Highcharts.chart('chart3_members', {
                                chart: {
                                    type: chart_type,
                                },
                                credits: {
                                    enabled : false
                                },
                                title: {
                                    text: ''
                                },
                                subtitle: {
                                    text: ''
                                },
                                xAxis: {
                                    categories: ticks_year,
                                    crosshair: true
                                },
                                legend : {
                                    enabled : false
                                },
                                yAxis: {
                                    min: 0,
                                    allowDecimals: false,
                                    title: {
                                        text: 'Members'
                                    }
                                    <?php if (isset($arm_max_year) and $arm_max_year == 5) {
                                        echo ',max : 6';
                                    } ?>
                                },
                                tooltip: {
                                    headerFormat: '<span style="font-size:10px">Month : {point.key}</span><table>',
                                    pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                                    '<td style="padding:0"><b>{point.y:1f}</b></td></tr>',
                                    footerFormat: '</table>',
                                    shared: true,
                                    useHTML: true
                                },
                                plotOptions: {
                                    column: {
                                        pointPadding: 0,
                                        borderWidth: 1,
                                        pointWidth: 27
                                    }
                                },
                                series: [{
                                    name: 'Members',
                                    data: year1
                                }]
                            });
                        } else {
                            var armChart = Highcharts.chart('chart3_members', {
                                chart: {
                                    type: chart_type,
                                },
                                credits: {
                                    enabled : false
                                },
                                title: {
                                    text: ''
                                },
                                subtitle: {
                                    text: ''
                                },
                                xAxis: {
                                    categories: ticks_year,
                                    crosshair: true,
                                },
                                yAxis: {
                                    min: 0,
                                    allowDecimals: false,
                                    title: {
                                        text: 'Members'
                                    }
                                },
                                legend: {enabled: false},
                                plotOptions: {
                                    column: {
                                        pointPadding: 0,
                                        borderWidth: 1,
                                        pointWidth: 27,
                                    },
                                    areaspline: {
                                        fillOpacity: 0.5,
                                        dataLabels: {
                                            enabled: false,
                                            format: '{point.y}'
                                        },
                                        lineColor: '#4da4fe',
                                    },
                                },
                                tooltip: {
                                    headerFormat: '<span style="font-size:10px">Month : {point.key}</span><table>',
                                    pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                                        '<td style="padding:0"><b>{point.y:1f}</b></td></tr>',
                                    footerFormat: '</table>',
                                    shared: true,
                                    useHTML: true
                                },
                                colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                                series: [{
                                    color: 'rgb(77,164,254)',
                                    colorByPoint: true,
                                    lineWidth: 2,
                                    name: "Members",
                                    data: year1
                                }]
                            });
                        }
                        var normalState = new Object();
                        normalState.stroke_width1 = 1;
                        normalState.stroke = '#afcaff';
                        normalState.fill = 'rgba(255,255,255,0.9)';
                        normalState.padding = 10;
                        normalState.r = 6;
                        normalState.width = 16;
                        normalState.height = 16;
                        normalState.align = 'center';
                        var hoverState = new Object();
                        hoverState = normalState;
                        var pressedState = new Object();
                        pressedState = normalState;
                        armChart.renderer.button('', 56, 70, function(){arm_change_graph_pre('<?php echo $type; ?>','<?php echo $arm_enable_prev;?>', 'members')}, normalState, hoverState, pressedState).attr({id:'arm_prev_button',class:'<?php echo $arm_disable_class_prev;?>'}).add().toFront();
                        armChart.renderer.button('', (armChart.chartWidth - 30), 70, function(){arm_change_graph_next('<?php echo $type; ?>','<?php echo $arm_enable_next;?>', 'members')}, normalState, hoverState, pressedState).attr({id:'arm_next_button',class:'<?php echo $arm_disable_class_next;?>'}).add().toFront();
                        jQuery('.highcharts-container').find('#arm_prev_button').find('text').remove();
                        jQuery('.highcharts-container').find('#arm_prev_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M1.221,8.318l8.002,8.002l2.001-2L5.221,8.316l6.003-6.003  l-2.001-2L1.221,8.315l0.001,0.001L1.221,8.318z"/></svg>');
                        jQuery('.highcharts-container').find('#arm_next_button').find('text').remove();
                        jQuery('.highcharts-container').find('#arm_next_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M11.431,8.601l-8.002,8.002l-2.001-2l6.003-6.003L1.428,2.596 l2.001-2l8.002,8.002L11.43,8.599L11.431,8.601z"/></svg>');
                    }
                });
            </script>
            <?php
        }
    }
    else 
    {
        if ($type == "monthly") 
        {
            $date = $new_month_year.'-'.$new_month.'-'.date('m', current_time('timestamp'));
        } else {
            $date = $new_day_year.'-'.$new_day_month.'-'.date('m', current_time('timestamp'));
            if(isset($_REQUEST['new_day']) && $_REQUEST['new_day'] != '') {
                $date = $new_day_year.'-'.$new_day_month."-".$new_day;
            }
        }

        if ($type == "monthly") {
            $day_first = date('01', strtotime($date));
            $day_last  = date('t', strtotime($date));
            $first = "{$new_month_year}-{$new_month}-{$day_first}";
            $last  = "{$new_month_year}-{$new_month}-{$day_last}";
        } else {
            
            $first = "{$current_date}";
            $last  = "{$current_date}";
            if(!empty($filter_date)) {
                $first = "{$filter_date}";
                $last  = "{$filter_date}";
            } else {
                $first = "{$new_day_year}-{$new_day_month}-{$new_day}";
                $last  = "{$new_day_year}-{$new_day_month}-{$new_day}";
            }
        }
        
        $day_array = $arm_report_analytics->arm_makeDayArray($first, $last);
           
        foreach ($day_array as $day) {
            $day_arr[$day] = $wpdb->get_results("SELECT u.ID,u.user_registered,u.user_login,u.user_email FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered LIKE '".date("Y-m-d",strtotime("{$day}")). "%' GROUP BY u.ID ORDER BY u.user_registered DESC", 'ARRAY_A');
        }

        if( 'daily' == $type ){

            $hour_array =array(' ', '00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00');

            $time_arr = array();
            $n = 0;

            foreach( $day_arr[$day] as $k => $darr ){
                $timeFormat = date('H:i',strtotime($darr['user_registered']));
                if( array_key_exists( $timeFormat, $time_arr ) ){
                    $time_arr[$timeFormat] = $time_arr[$timeFormat] + 1;
                } else {
                    $time_arr[$timeFormat] = 1;
                    $n++;
                }
            }
            
            $final_time_arr = array(0);
            $uk = 1;
            
            for( $i = 0; $i <= 24; $i++ ){
                $hour = mktime($i,0);
                $current_hour = date('H:i', $hour);
                $next_hour    = date('H:i', strtotime("+1 hour", $hour));
                $begin = DateTime::createFromFormat( 'H:i', $current_hour );
                $end = DateTime::createFromFormat( 'H:i', $next_hour );
                foreach( $time_arr as $time_key => $time ){
                    $now = DateTime::createFromFormat( 'H:i', $time_key );
                    if( $now > $begin && $now < $end ){
                        $final_time_arr[$i + 1][] = $time;
                    }
                }
                $uk++;
            }
        }

        $formatted_time_arr = array(0);
        if( 'daily' == $type ){
            for( $i = 0; $i <= 24; $i++ ){
                if( !isset( $final_time_arr[$i] ) ){
                    $formatted_time_arr[$i] = 0;
                } else {
                    if( is_array( $final_time_arr[$i] ) && count( $final_time_arr[$i] ) == 1 ){
                        $formatted_time_arr[$i] = $final_time_arr[$i][0];
                    } else if( count( $final_time_arr[$i] ) > 0 ){
                        $t = 0;
                        if(!empty($final_time_arr[$i])) {
                            foreach( $final_time_arr[$i] as $tk => $tv ){
                                $formatted_time_arr[$i] = $tv + $t;
                                $t++;
                            }
                        }
                    }
                }
            }
        }
        

        /*for data table*/
        $table_data_arr = array();
        $table_data_cnt = 0;
        $perPage = 10;
        $offset = ($current_page - 1) * $perPage;
        if($type == "monthly" || $type == "daily") {
            $first_day = $first." 00:00:00";
            $last_day = $last." 23:59:59";
            $table_data_cnt = $wpdb->get_results("SELECT count(u.ID) FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered BETWEEN '".$first_day."' AND '".$last_day."' GROUP BY u.ID ORDER BY u.user_registered DESC", 'ARRAY_A');

            if($is_export_to_csv == true || $is_export_to_csv == '1') {
                $table_data_arr = $wpdb->get_results("SELECT u.ID,u.user_registered,u.user_login,u.user_email FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered BETWEEN '".$first_day."' AND '".$last_day."' GROUP BY u.ID ORDER BY u.user_registered DESC", 'ARRAY_A');    
            } else {
                $table_data_arr = $wpdb->get_results("SELECT u.ID,u.user_registered,u.user_login,u.user_email FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered BETWEEN '".$first_day."' AND '".$last_day."' GROUP BY u.ID ORDER BY u.user_registered DESC LIMIT ".$perPage." OFFSET ".$offset, 'ARRAY_A');    
            }

            $table_data_cnt = count($table_data_cnt);
            $dataPaging = $arm_global_settings->arm_get_paging_links($current_page, $table_data_cnt, $perPage, '');
        } 
                
        $arm_charts_plan_data=array();
        $table_data_cnt_arr = $wpdb->get_results("SELECT u.ID,u.user_registered,u.user_login,u.user_email FROM `{$user_table}` u LEFT JOIN `{$usermeta_table}` um ON u.ID = um.user_id {$join_clause} {$user_where} AND u.user_registered BETWEEN '".$first_day."' AND '".$last_day."' GROUP BY u.ID ORDER BY u.user_registered DESC", 'ARRAY_A');
        if(!empty($table_data_cnt_arr))
        {
            foreach($table_data_cnt_arr as $key => $table_data_cnt_val)
            {
                $arm_registered_date = date_i18n($wp_date_time_format, strtotime($table_data_cnt_val['user_registered']));
                $plan_name = "";
                $plan_arr = get_user_meta($table_data_cnt_val['ID'], "arm_user_plan_ids", true);
                
                $paid_post_ids = get_user_meta($table_data_cnt_val['ID'], 'arm_user_post_ids', true);
                if(!empty($paid_post_ids))
                {
                    foreach($plan_arr as $key => $val)
                    {
                        if(!empty($paid_post_ids[$val]))
                        {
                            unset($plan_arr[$key]);
                        }
                    }
                }


                if(!empty($plan_arr)) {

                    foreach ($plan_arr as $key => $plan) {
                        $arm_plan_name=$arm_subscription_plans->arm_get_plan_name_by_id($plan);
                        if(!empty($arm_plan_name) && isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts"){
                            $arm_charts_plan_data[$plan]['plan_name']=$arm_plan_name;
                            $arm_charts_plan_data[$plan]['plan_users'][]=$table_data_cnt_val['ID'];
                        }
                        $plan_name .= $arm_plan_name;
                        $plan_name .= ", ";
                    }
                } else {
                    $plan_name = "-";
                }
            }
        }

        $table_content_html = "";
        $tab_row_cnt = 0;
        if(!empty($table_data_arr)) {

            if($is_export_to_csv == true || $is_export_to_csv == '1') {
                $arm_report_analytics->arm_export_report_to_csv($table_data_arr, $arm_report_type);
                exit;
            }
            
            foreach ($table_data_arr as $key => $table_data) {
                $arm_registered_date = date_i18n($wp_date_time_format, strtotime($table_data['user_registered']));
                $plan_name = "";
                $plan_arr = get_user_meta($table_data['ID'], "arm_user_plan_ids", true);
                
                $paid_post_ids = get_user_meta($table_data['ID'], 'arm_user_post_ids', true);
                if(!empty($paid_post_ids))
                {
                    foreach($plan_arr as $key => $val)
                    {
                        if(!empty($paid_post_ids[$val]))
                        {
                            unset($plan_arr[$key]);
                        }
                    }
                }

                if(!empty($plan_arr)) {

                    foreach ($plan_arr as $key => $plan) {
                        $arm_plan_name=$arm_subscription_plans->arm_get_plan_name_by_id($plan);
                        $plan_name .= $arm_plan_name;
                        $plan_name .= ", ";
                    }
                } else {
                    $plan_name = "-";
                }

                $plan_name = rtrim($plan_name, ", ");

                $table_content_html .= "<tr class='arm_member_last_subscriptions_data'>";
                    $table_content_html .= "<td>".$table_data['user_login']."</td>"; 
                    $table_content_html .= "<td><a class='arm_openpreview_popup' href='javascript:void(0)' data-id='".$table_data['ID']."'>".$table_data['user_email']."</a></td>"; 
                    $table_content_html .= "<td>".$plan_name."</td>";  
                    $table_content_html .= "<td>".$arm_registered_date."</td>";
                $table_content_html .= "</tr>";
                $tab_row_cnt++;
            }
            
        } else {
            $table_content_html .= "<tr class='arm_member_last_subscriptions_data'>";
                $table_content_html .= "<th class='arm_report_grid_no_data' colspan='4'>".esc_html__( 'No records found', 'ARMember')."</th>";
            $table_content_html .= "</tr>";
        }
        
        if (!empty($day_arr) && $type == 'monthly') {
            $day_var = $val_var = '[';
            $max_day = 0;
            

            foreach ($day_arr as $key => $value) {
                $total_users = intval(count($value));
                $day = date("d-M", strtotime($key));

                $day_var .= "'{$day}', ";
                $val_var .= $total_users. ', ';
                
                $max_day = max($max_day, $total_users);
            }
            $day_var .= ']';
            $val_var .= ']';
        }

        if ($type == "monthly") {
            $chart = "chart2_members";
            
            $max_month_limit = date('Y-m',mktime(0, 0, 0, $new_month,1 , $new_month_year));
            $arm_disable_class_next = '';
            $arm_enable_next = 1;
            if ($max_month_limit >= date('Y-m')) {
                $arm_disable_class_next = 'arm_disabled_class_next';
                $arm_enable_next = 0;
            }
            $arm_disable_class_prev = '';
            $arm_enable_prev = 1;
            $month_limit     = date('Y-m-d',mktime(0, 0, 0, $new_month, 1, $new_month_year));
            $min_month_limit = date('Y-m-d',mktime(0, 0, 0, $min_month, 1, $min_year));

        } else {

            $chart = "chart1_members";

            $day_var = json_encode( $hour_array );
            $val_var = json_encode( $formatted_time_arr );

            $max_date_limit = date('Y-m-d',mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));    
            $arm_disable_class_next = '';
            $arm_enable_next = 1;
            if ($max_date_limit >= date('Y-m-d')) {
                $arm_disable_class_next = 'arm_disabled_class_next';
                $arm_enable_next = 0;
            }
            $arm_disable_class_prev = '';
            $arm_enable_prev = 1;
            $date_limit     = date('Y-m-d',mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
            $min_date_limit = date('Y-m-d',mktime(0, 0, 0, $min_month, $min_date, $min_year));
        }
        if( $is_pagination ){
            echo $table_content_html.'[ARM_REPORT_SEPARATOR]'.str_replace("\n", "", $dataPaging);    
            die;
        } else {
            ?>
            <script type="text/javascript">

                jQuery(".arm_members_table_body_content").html("<?php echo $table_content_html; ?>");
                jQuery("#arm_members_table_paging").html('<?php echo str_replace("\n","", $dataPaging); ?>');

                jQuery.noConflict();
                jQuery(document).ready(function($){
                    var buttonOptions = {};
                    var chart_type = '';
                    var graph_type = "<?php echo $graph_type;?>";
                    if (graph_type == 'bar') {
                        chart_type ='column';
                    } else if (graph_type == 'line') {
                        chart_type ='areaspline';
                    }
                    var s1 = <?php echo $val_var; ?>;
                    if(graph_type == 'bar' || graph_type == 'line'){
                        var tooltipHeader;
                        tooltipHeader = '<?php echo ('monthly' == $type) ? 'Date' : 'Hour'; ?>';
                        if(graph_type == 'bar'){
                            var gbarOpt = {
                                chart : {
                                    type : chart_type
                                },
                                credits: {
                                    enabled : false
                                },
                                title: {
                                    text: ''
                                },
                                subtitle: {
                                    text: ''
                                },
                                legend: {
                                    enabled: false
                                },
                                xAxis: {
                                    categories: <?php echo $day_var; ?>,
                                    crosshair: true
                                },
                                yAxis: {
                                    min: 0,
                                    allowDecimals: false,
                                    title: {
                                        text: 'Members'
                                    }
                                    <?php if (!empty($max_day) && $max_day != 0) {
                                        echo ',max : '.($max_day+1);
                                    } ?>
                                },
                                tooltip: {
                                    headerFormat: '<span style="font-size:10px">'+tooltipHeader+' : {point.key}</span><table>',
                                    pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                                    '<td style="padding-left:30px;"><b>{point.y:1f}</b></td></tr>',
                                    footerFormat: '</table>',
                                    shared: true,
                                    useHTML: true
                                },
                                colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                                plotOptions: {
                                    column: {
                                        pointPadding: 0.2,
                                        borderWidth: 1,
                                        pointWidth: 10, //width of the column
                                        lineColor: '#4da4fe',
                                        color: 'rgb(77,164,254)',
                                        colorByPoint: true,
                                        lineWidth:2
                                    }
                                },
                                series: [{
                                    name: 'Members',
                                    data: s1
                                }],
                            };
                            var armChart = Highcharts.chart("<?php echo $chart; ?>", gbarOpt);    
                        } else {
                            

                            var opt2 = {
                                chart : {
                                    type : chart_type
                                },
                                title: {
                                    text: ''
                                },
                                subtitle: {
                                    text: ''
                                },
                                credits: {
                                    enabled : false
                                },
                                legend : {
                                    enabled: false
                                },
                                xAxis: {
                                    categories: <?php echo $day_var; ?>,
                                    crosshair: true
                                },
                                yAxis: {
                                    min: 0,
                                    allowDecimals: false,
                                    title: {
                                        text: 'Members'
                                    }
                                    <?php if (!empty($max_day) && $max_day != 0) {
                                        echo ',max : '.($max_day+1);
                                    } ?>
                                },
                                tooltip: {
                                    headerFormat: '<span style="font-size:10px">'+tooltipHeader+' : {point.key}</span><table>',
                                    pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' + '<td style="padding-left:30px;"><b>{point.y:1f}</b></td></tr>',
                                    footerFormat: '</table>',
                                    shared: true,
                                    useHTML: true
                                },
                                plotOptions: {
                                    areaspline: {
                                        fillOpacity: 0.05,
                                        dataLabels: {
                                            enabled: false,
                                            format: '{point.y}'
                                        },
                                        lineColor: '#4da4fe',
                                    }
                                },
                                colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                                series: [{
                                    color: 'rgb(77,164,254)',
                                    colorByPoint: true,
                                    lineWidth: 2,
                                    name: "Members",
                                    data: s1
                                }],
                            };
                            var armChart = Highcharts.chart("<?php echo $chart; ?>", opt2);
                        }
                        var normalState = new Object();
                        normalState.stroke_width = 1;
                        normalState.stroke = '#afcaff';
                        normalState.fill = 'rgba(255,255,255,0.9)';
                        normalState.padding = 10;
                        normalState.r = 6;
                        normalState.width = 16;
                        normalState.height = 16;
                        normalState.align = 'center';
                        var hoverState = new Object();
                        hoverState = normalState;
                        var pressedState = new Object();
                        pressedState = normalState;
                    <?php 
                        if(empty($arm_disable_next_prev_btn))
                        {
                    ?>
                        armChart.renderer.button('', 56, 70, function(){arm_change_graph_pre('<?php echo $type; ?>','<?php echo $arm_enable_prev;?>', 'members')}, normalState, hoverState, pressedState).attr({id:'arm_prev_button',class:'<?php echo $arm_disable_class_prev;?>'}).add().toFront();
                        armChart.renderer.button('', (armChart.chartWidth - 30), 70, function(){arm_change_graph_next('<?php echo $type; ?>','<?php echo $arm_enable_next;?>', 'members')}, normalState, hoverState, pressedState).attr({id:'arm_next_button',class:'<?php echo $arm_disable_class_next;?>'}).add().toFront();
                    <?php } ?>
                        jQuery('.highcharts-container').find('#arm_prev_button').find('text').remove();
                        jQuery('.highcharts-container').find('#arm_prev_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M1.221,8.318l8.002,8.002l2.001-2L5.221,8.316l6.003-6.003  l-2.001-2L1.221,8.315l0.001,0.001L1.221,8.318z"/></svg>');
                        jQuery('.highcharts-container').find('#arm_next_button').find('text').remove();
                        jQuery('.highcharts-container').find('#arm_next_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M11.431,8.601l-8.002,8.002l-2.001-2l6.003-6.003L1.428,2.596  l2.001-2l8.002,8.002L11.43,8.599L11.431,8.601z"/></svg>');
                    } else {

                    }
                });
            </script>
            <?php
        }
    } ?>
 
    <?php if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts"){?>
        <?php if($type == 'daily'){?>
            <div class="armchart_display_title arm_ml_5">
                <label class="armcharttitle">
                    <?php echo date(get_option('date_format'), strtotime($new_day . '-' . $new_day_month . '-' . $new_day_year)); ?>
                </label>
            </div>
        <?php }?>
        <?php if($type == 'monthly'){?>
            <?php $monthName = date("F", mktime(0, 0, 0, (int)$new_month, 10)); ?>
            <div class="armchart_display_title arm_ml_5">
                <label class="armcharttitle"><?php echo $monthName . "-" . $new_month_year; ?></label>
            </div>
        <?php }?>
        <?php if($type == 'yearly'){?>
            <div class="armchart_display_title arm_ml_5">
                <label class="armcharttitle"><?php echo $new_year; ?></label>
            </div>
        <?php }?>
        <div class="armchart_plan_section">
            <?php if(isset($arm_charts_plan_data) && count($arm_charts_plan_data)>0){
                foreach ($arm_charts_plan_data as $arm_charts_plan_key => $arm_charts_plan_row) {
                ?>
                <div class="armchart_plan_item">
                    <label class="armchart_plan_title"><?php echo $arm_charts_plan_row['plan_name']; ?></label>
                    <p class="armchart_plan_item_desc"><?php echo __("Members:", "ARMember");?> <?php echo count($arm_charts_plan_row['plan_users']); ?></p>
                </div>
            <?php 
                }
            }else{
                    echo '<div class="armchart_plan_no_item">'. __("Recently No Membership Plan purchased", "ARMember").'</div>';
                } ?>  
        </div>
        <div class="armchart_view_section">
    <?php }?>    
        <div id="daily_members" style="padding:15px;<?php echo ($type == 'daily') ? 'display:block;' : 'display:none'; ?>">
            <?php if(!isset($_REQUEST['action'])){?>
            <div class="armchart_display_title">
                <label class="armcharttitle">
                    <?php echo date(get_option('date_format'), strtotime($new_day . '-' . $new_day_month . '-' . $new_day_year)); ?>
                </label>
            </div>
            <?php }?>
            <div id="chart1_members" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            
            <input type="hidden" value="<?php echo $new_day; ?>" name="current_day" id="current_day" />
            <input type="hidden" value="<?php echo $new_day_month; ?>" name="current_day_month" id="current_day_month" />
            <input type="hidden" value="<?php echo $new_day_year; ?>" name="current_day_year" id="current_day_year" />
        </div>

        <div id="monthly_members" style="padding:15px; <?php echo ($type == 'monthly') ? 'display:block;' : 'display:none'; ?>">
            <?php if(!isset($_REQUEST['action'])){?>
            <?php $monthName = date("F", mktime(0, 0, 0, (int)$new_month, 10)); ?>
            <div class="armchart_display_title">
                <label class="armcharttitle"><?php echo $monthName . "-" . $new_month_year; ?></label>
            </div>
            <?php }?>
            <div id="chart2_members" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            <input type="hidden" value="<?php echo $new_month; ?>" name="current_month" id="current_month" />
            <input type="hidden" value="<?php echo $new_month_year; ?>" name="current_month_year" id="current_month_year" />
        </div>

        <div id="yearly_members" style="padding:15px; <?php echo ($type == 'yearly') ? 'display:block;' : 'display:none'; ?>">
            <div class="arlinks link_align"></div>
            <?php if(!isset($_REQUEST['action'])){?>
            <div class="armchart_display_title">
                <label class="armcharttitle"><?php echo $new_year; ?></label>
            </div>
            <?php }?>
            <div id="chart3_members" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            <input type="hidden" value="<?php echo $new_year; ?>" name="current_year" id="current_year" />
        </div>

    <?php if(isset($_REQUEST['action']) && isset($_REQUEST['member_report'])){?>
    </div>    
    <?php }?>
    <?php
} else if ($plan_type == 'members_plan') {
    $plans_info = $wpdb->get_results("SELECT `arm_subscription_plan_id` as id, `arm_subscription_plan_name` as name FROM `{$ARMember->tbl_arm_subscription_plans}` WHERE `arm_subscription_plan_is_delete` = '0'");
    if (!empty($plans_info)) {
        $chart = "chart2_members_plan";
        $plan_name   = $plan_users = "[";
        $plan_name  .= "' ', ";
        $plan_users .= "0, ";
        $activity = $ARMember->tbl_arm_activity;

        $min_full_date = $wpdb->get_var("SELECT min(arm_date_recorded) FROM `{$activity}`");
        if (!empty($min_full_date)) {
            $min_year  = date('Y', strtotime($min_full_date));
            $min_month = date('m', strtotime($min_full_date));
            $min_date  = date('d', strtotime($min_full_date));
        }

        $arm_disable_class_next = '';
        $arm_disable_class_prev = '';
        $arm_enable_next = 1;
        $arm_enable_prev = 1;
        if ($type == 'yearly') {
            if ($new_year >= date('Y', current_time('timestamp'))) {
                $arm_disable_class_next = 'arm_disabled_class_next';
                $arm_enable_next = 0;
            }

            if ($new_year <= $min_year) {
                $arm_disable_class_prev = 'arm_disabled_class_prev';
                $arm_enable_prev = 0;
            }

            $chart = "chart3_members_plan";
            $from_year = "{$new_year}-01-01 00:00:00";
            $end_year  = "{$new_year}-12-31 23:59:59";

            $members_activity = $wpdb->get_results("SELECT DISTINCT arm_user_id, arm_content, arm_date_recorded FROM `{$activity}` WHERE arm_date_recorded BETWEEN '{$from_year}' AND '{$end_year}' AND arm_action = 'new_subscription' ORDER BY arm_date_recorded DESC", 'ARRAY_A');
        } else if ($type == 'monthly') {
            $chart = "chart2_members_plan";
            $date      = $new_month_year.'-'.$new_month.'-'.date('m', current_time('timestamp'));
            $day_first = date('01', strtotime($date));
            $day_last  = date('t', strtotime($date));

            $first = "{$new_month_year}-{$new_month}-{$day_first}";
            $last  = "{$new_month_year}-{$new_month}-{$day_last}";

            $max_month_limit = date('Y-m',mktime(0, 0, 0, $new_month,1 , $new_month_year));
            if ($max_month_limit >= date('Y-m')) {
                $arm_disable_class_next = 'arm_disabled_class_next';
                $arm_enable_next = 0;
            }
            $month_limit     = date('Y-m-d',mktime(0, 0, 0, $new_month, 1, $new_month_year));
            $min_month_limit = date('Y-m-d',mktime(0, 0, 0, $min_month, 1, $min_year));
            if ($min_month_limit >= $month_limit) {
                $arm_disable_class_prev = 'arm_disabled_class_prev';
                $arm_enable_prev = 0;
            }

            $members_activity = $wpdb->get_results("SELECT DISTINCT arm_user_id, arm_content, arm_date_recorded FROM `{$activity}` WHERE arm_date_recorded BETWEEN '{$first}' AND '{$last}' AND arm_action = 'new_subscription' ORDER BY arm_date_recorded DESC", 'ARRAY_A');
        } else if ($type == 'daily') {
            $chart = "chart1_members_plan";
            $date = "{$new_day_year}-{$new_day_month}-{$new_day}";

            $date = date("Y-m-d", mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
            $max_date_limit = date('Y-m-d',mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
            if ($max_date_limit >= date('Y-m-d')) {
                $arm_disable_class_next = 'arm_disabled_class_next';
                $arm_enable_next = 0;
            }
            $date_limit     = date('Y-m-d', mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
            $min_date_limit = date('Y-m-d', mktime(0, 0, 0, $min_month, $min_date, $min_year));
            if ($min_date_limit >= $date_limit) {
                $arm_disable_class_prev = 'arm_disabled_class_prev';
                $arm_enable_prev = 0;
            }

            $members_activity = $wpdb->get_results("SELECT DISTINCT arm_user_id, arm_content, arm_date_recorded FROM `{$activity}` WHERE arm_date_recorded LIKE '{$date}%' AND arm_action = 'new_subscription' ORDER BY arm_date_recorded DESC", 'ARRAY_A');
        }
        $users = array();
        $count = "";
        if (!empty($members_activity)) {
            foreach ($members_activity as $member_users_id) {
                $user_id = $member_users_id['arm_user_id'];
                $arm_content = maybe_unserialize($member_users_id['arm_content']);
                $arm_date_recorded = $member_users_id['arm_date_recorded'];
                if (in_array($user_id, $users)) {
                    continue;
                }
                $users[] = $user_id;
                $arm_user_plans = get_user_meta($user_id, 'arm_user_plan_ids', true);
                if (empty($arm_user_plans)) {
                    continue;
                }
                $plan_id = $arm_content['plan_id'];
                $count[$plan_id] = $count[$plan_id] + 1;
            }
        }
        foreach ($plans_info as $plan) {
            $plan_name  .= "'".stripslashes($plan->name) . "', ";
            $plan_users .= isset($count[$plan->id]) && !empty($count[$plan->id]) ? intval($count[$plan->id]).", " : intval(0).", ";
        }
        $plan_name  .= "]";
        $plan_users .= "]";
        if (!empty($plan_name) && !empty($plan_users)) { ?>
            <script type="text/javascript" data-cfasync="false">
                jQuery.noConflict();
                jQuery(document).ready(function($){
                    var graph_type = "<?php echo $graph_type;?>";
                    var plan_users = <?php echo $plan_users; ?>;
                    var plan_names = <?php echo $plan_name; ?>;

                    if(graph_type == 'bar'){
                        var armChart = Highcharts.chart(<?php echo $chart; ?>, {
                            chart: {
                                type: 'column',
                            },
                            title: {
                                text: ''
                            },
                            subtitle: {
                                text: ''
                            },
                            credits : {
                                enabled : false
                            },
                            xAxis: {
                                categories: plan_names,
                                crosshair: true,
                                labels: {
                                    rotation: - 60
                                },
                                min : 0.5
                            },
                            yAxis: {
                                min: 0,
                                allowDecimals: false,
                                title: {text: 'Members'}
                            },
                            legend: {
                                enabled: false
                            },
                            plotOptions: {
                                areaspline: {
                                    fillOpacity: 0.05,
                                    dataLabels: {enabled: false, format: '{point.y}'},
                                    lineColor: '#4da4fe',
                                }
                            },
                            tooltip: {
                                formatter: function() {
                                    var tooltip = "";
                                    var index = this.point.index;
                                    var name  = plan_names[index];
                                    if (index == 0) {
                                        name = '0';
                                    }
                                    tooltip   = '<span style="font-size:12px">' + name + ':</span>';
                                    tooltip   += '<div style="color:' + this.series.color + '">(</div><b>' + this.y + '</b><div style="color:' + this.series.color + '">)</div>';
                                    return tooltip;
                                }
                            },
                            colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                            series: [{
                                name: "Membership",
                                color: 'rgb(77,164,254)',
                                colorByPoint: true,
                                lineWidth: 2,
                                data: plan_users,
                            }],
                        });
                    } else if(graph_type == 'line'){
                        var armChart = Highcharts.chart(<?php echo $chart; ?>, {
                            chart: {
                                type: 'areaspline',
                            },
                            credits : {
                                enabled : false
                            },
                            title: {
                                text: ''
                            },
                            subtitle: {
                                text: ''
                            },
                            xAxis: {
                                categories: plan_names,
                                crosshair: true,
                                labels: {
                                    rotation: - 60
                                },
                                min : 0.5
                            },
                            yAxis: {
                                min: 0,
                                allowDecimals: false,
                                title: {
                                    text: 'Members'
                                }
                            },
                            legend: {enabled: false},
                            plotOptions: {
                                areaspline: {
                                    fillOpacity: 0.05,
                                    dataLabels: {
                                        enabled: false,
                                        format: '{point.y}'
                                    },
                                    lineColor: '#4da4fe',
                                }
                            },
                            tooltip: {
                                formatter: function() {
                                    var tooltip = "";
                                    var index = this.point.index;
                                    var name  = plan_names[index];
                                    if (index == 0) {
                                        name = '0';
                                    }
                                    tooltip  = '<span style="font-size:12px">' + name + ':</span>';
                                    tooltip += '<div style="color:' + this.series.color + '">(</div><b>' + this.y + '</b><div style="color:' + this.series.color + '">)</div>';
                                    return tooltip;
                                }
                            },
                            colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                            series: [{
                                color: 'rgb(77,164,254)',
                                colorByPoint: true,
                                lineWidth: 2,
                                data: plan_users,
                            }],
                        });
                    }
                    var normalState = new Object();
                    normalState.stroke_width1 = 1;
                    normalState.stroke = '#afcaff';
                    normalState.fill = 'rgba(255,255,255,0.9)';
                    normalState.padding = 10;
                    normalState.r = 6;
                    normalState.width = 16;
                    normalState.height = 16;
                    normalState.align = 'center';
                    var hoverState = new Object();
                    hoverState = normalState;
                    var pressedState = new Object();
                    pressedState = normalState;
                    armChart.renderer.button('', 56, 70, function(){arm_change_graph_pre('<?php echo $type; ?>','<?php echo $arm_enable_prev;?>', 'members_plan')}, normalState, hoverState, pressedState).attr({id:'arm_prev_button',class:'<?php echo $arm_disable_class_prev;?>'}).add().toFront();
                    armChart.renderer.button('', (armChart.chartWidth - 30), 70, function(){arm_change_graph_next('<?php echo $type; ?>','<?php echo $arm_enable_next;?>', 'members_plan')}, normalState, hoverState, pressedState).attr({id:'arm_next_button',class:'<?php echo $arm_disable_class_next;?>'}).add().toFront();
                    jQuery('.highcharts-container').find('#arm_prev_button').find('text').remove();
                    jQuery('.highcharts-container').find('#arm_prev_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M1.221,8.318l8.002,8.002l2.001-2L5.221,8.316l6.003-6.003  l-2.001-2L1.221,8.315l0.001,0.001L1.221,8.318z"/></svg>');
                    jQuery('.highcharts-container').find('#arm_next_button').find('text').remove();
                    jQuery('.highcharts-container').find('#arm_next_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M11.431,8.601l-8.002,8.002l-2.001-2l6.003-6.003L1.428,2.596 l2.001-2l8.002,8.002L11.43,8.599L11.431,8.601z"/></svg>');
                });
            </script>
        <?php }
    } ?>

    <div id="daily_members_plan" style="padding:15px;<?php echo ($type == 'daily') ? 'display:block;' : 'display:none'; ?>">
        <div class="armchart_display_title">
            <label class="armcharttitle">
                <?php echo date(get_option('date_format'), strtotime($new_day . '-' . $new_day_month . '-' . $new_day_year)); ?>
            </label>
        </div>
        <div id="chart1_members_plan" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
        
        <input type="hidden" value="<?php echo $new_day; ?>" name="current_day" id="current_day" />
        <input type="hidden" value="<?php echo $new_day_month; ?>" name="current_day_month" id="current_day_month" />
        <input type="hidden" value="<?php echo $new_day_year; ?>" name="current_day_year" id="current_day_year" />
    </div>

    <div id="monthly_members_plan" style="padding:15px; <?php echo ($type == 'monthly') ? 'display:block;' : 'display:none'; ?>">
        <?php $monthName = date("F", mktime(0, 0, 0, (int)$new_month, 10)); ?>
        <div class="armchart_display_title">
            <label class="armcharttitle"><?php echo $monthName . "-" . $new_month_year; ?></label>
        </div>
        <div id="chart2_members_plan" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
        <input type="hidden" value="<?php echo $new_month; ?>" name="current_month" id="current_month" />
        <input type="hidden" value="<?php echo $new_month_year; ?>" name="current_month_year" id="current_month_year" />
    </div>

    <div id="yearly_members_plan" style="padding:15px; <?php echo ($type == 'yearly') ? 'display:block;' : 'display:none'; ?>">
        <div class="arlinks link_align"></div>
        <div class="armchart_display_title">
            <label class="armcharttitle"><?php echo $new_year; ?></label>
        </div>
        <div id="chart3_members_plan" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
        <input type="hidden" value="<?php echo $new_year; ?>" name="current_year" id="current_year" />
    </div>
    <?php    
} else if ($plan_type == 'payment_history') {
    $chart     = "chart2_payment_history";
    $gateway_name  = $total_amount = $tool_tip = "[";
    $gateway_name .= "' ', ";
    $tool_tip     .= "' ', ";
    $total_amount .= "0, ";
    $ptquery = $wpdb->get_var("SELECT min(arm_created_date) FROM `{$ARMember->tbl_arm_payment_log}`");
    
    $ctquery = "";
    $cbquery = "";
    $ctquery_where = "";


    if(!empty($filter_plan_id)) {
        $ctquery_where .= " AND pt.arm_plan_id=".$filter_plan_id." ";
        
    }
    if(!empty($gateway_filter)) {
        $ctquery_where .= " AND pt.arm_payment_gateway='".$gateway_filter."'";
    }

    if (!empty($ptquery)) {
        $min_year  = date('Y', strtotime($ptquery));
        $min_month = date('m', strtotime($ptquery));
        $min_date  = date('d', strtotime($ptquery));
    }

    $arm_disable_class_next = $arm_disable_class_prev = '';
    $arm_enable_next = $arm_enable_prev = 1;
    $chart     = "chart3_payment_history";
    if ($type == 'yearly') {
        if ($new_year >= date('Y', current_time('timestamp'))) {
            $arm_disable_class_next = 'arm_disabled_class_next';
            $arm_enable_next = 0;
        }

        if ($new_year <= $min_year) {
            $arm_disable_class_prev = 'arm_disabled_class_prev';
            $arm_enable_prev = 0;
        }

        $chart     = "chart3_payment_history";
        $from_year = "{$new_year}-01-01 00:00:00";
        $end_year  = "{$new_year}-12-31 23:59:59";

        $ctquery = "SELECT pt.arm_log_id, pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_amount, pt.arm_created_date, pt.arm_payment_gateway, MONTH(pt.arm_created_date) AS time, SUM(pt.arm_amount) as Total FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 0 {$ctquery_where} AND pt.arm_created_date BETWEEN '{$from_year}' AND '{$end_year}' Group By pt.arm_payment_gateway, MONTH(pt.arm_created_date)";
        
        $ctgquery = "SELECT pt.arm_log_id, pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_currency, pt.arm_amount AS arm_amount, pt.arm_created_date, pt.arm_payment_gateway FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 0 {$ctquery_where} AND pt.arm_created_date BETWEEN '{$from_year}' AND '{$end_year}'";

        
    } else if ($type == 'monthly') {
        $chart     = "chart2_payment_history";
        $date      = $new_month_year.'-'.$new_month.'-'.date('m', current_time('timestamp'));
        $day_first = date('01', strtotime($date));
        $day_last  = date('t', strtotime($date));

        $first = "{$new_month_year}-{$new_month}-{$day_first}";
        $last  = "{$new_month_year}-{$new_month}-{$day_last}";

        $max_month_limit = date('Y-m',mktime(0, 0, 0, $new_month,1 , $new_month_year));
        if ($max_month_limit >= date('Y-m')) {
            $arm_disable_class_next = 'arm_disabled_class_next';
            $arm_enable_next = 0;
        }
        $month_limit     = date('Y-m-d',mktime(0, 0, 0, $new_month, 1, $new_month_year));
        $min_month_limit = date('Y-m-d',mktime(0, 0, 0, $min_month, 1, $min_year));
        if ($min_month_limit >= $month_limit) {
            $arm_disable_class_prev = 'arm_disabled_class_prev';
            $arm_enable_prev = 0;
        }

        $ctquery = "SELECT pt.arm_log_id, pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_amount, pt.arm_created_date, pt.arm_payment_gateway, DAY(pt.arm_created_date) as time, SUM(pt.arm_amount) as Total FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 0 {$ctquery_where} AND pt.arm_created_date BETWEEN '{$first}' AND '{$last}' Group By pt.arm_payment_gateway, DAY(pt.arm_created_date)";
        
        $ctgquery = "SELECT pt.arm_log_id, pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_currency, pt.arm_amount AS arm_amount, pt.arm_created_date, pt.arm_payment_gateway FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE 1=1 AND pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 0 {$ctquery_where} AND pt.arm_created_date BETWEEN '{$first}' AND '{$last}'";
        
        
    } else if ($type == 'daily') {
        //echo "<br>reputelog == 1";
        $chart = "chart1_payment_history";
        $date = "{$new_day_year}-{$new_day_month}-{$new_day}";

        $date = date("Y-m-d", mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
        $max_date_limit = date('Y-m-d',mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
        if ($max_date_limit >= date('Y-m-d')) {
            $arm_disable_class_next = 'arm_disabled_class_next';
            $arm_enable_next = 0;
        }
        $date_limit     = date('Y-m-d', mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
        $min_date_limit = date('Y-m-d', mktime(0, 0, 0, $min_month, $min_date, $min_year));
        if ($min_date_limit >= $date_limit) {
            $arm_disable_class_prev = 'arm_disabled_class_prev';
            $arm_enable_prev = 0;
        }

        $ctquery = "SELECT pt.arm_log_id, pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_amount, pt.arm_created_date, pt.arm_payment_gateway, TIME(pt.arm_created_date) as time, SUM(pt.arm_amount) as Total FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 0 {$ctquery_where} AND pt.arm_created_date LIKE '{$date}%'";
        
        $ctgquery = "SELECT pt.arm_log_id, pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_currency, pt.arm_amount AS arm_amount, pt.arm_created_date, pt.arm_payment_gateway FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE 1=1 AND pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 0 {$ctquery_where} AND pt.arm_created_date LIKE '{$date}%'";
      
    }

    //echo "<br>reputelog == 2";
    /*for data table*/
    $table_data_arr = array();
    $table_data_cnt = 0;
    $table_data_cnt_arr = $wpdb->get_results("SELECT arm_log_id, arm_created_date, arm_payment_gateway, arm_user_id FROM ({$ctgquery}) AS arm_payment_history_log_data", 'ARRAY_A');
    $perPage = 10;
    $offset = ($current_page - 1) * $perPage;

    if(!empty($gateway_filter)) {
        
        $payment_grid_query = $wpdb->get_results("SELECT arm_payment_gateway, time, Total FROM ({$ctquery}) AS arm_payment_history_log", 'ARRAY_A');
        
        /*for data table*/
        $table_data_cnt = count($payment_grid_query);
        //$table_data_arr = $wpdb->get_results("SELECT * FROM ({$ctquery}) AS arm_payment_history_log LIMIT ".$perPage." OFFSET ".$offset, 'ARRAY_A');

        if($is_export_to_csv == true || $is_export_to_csv == '1') { 
            $payment_data_query = $wpdb->get_results("SELECT * FROM ({$ctgquery}) as arm_payment_history_log_data", 'ARRAY_A');
        } else {
            $payment_data_query = $wpdb->get_results("SELECT arm_log_id, arm_created_date, arm_payment_gateway, arm_user_id, arm_payer_email, arm_invoice_id, arm_amount, arm_currency FROM ({$ctgquery}) as arm_payment_history_log_data LIMIT {$perPage} OFFSET {$offset}", 'ARRAY_A');           
        }
        

        $table_data_cnt2 = $wpdb->get_var( "SELECT COUNT(*) as total FROM ({$ctgquery}) AS arm_payment_history_log_data" );

    } else if(empty($gateway_filter)) {

        $payment_grid_query = $wpdb->get_results("SELECT arm_payment_gateway, time, Total FROM ({$ctquery}) AS arm_payment_history_log", 'ARRAY_A');

        /*for data table*/
        $table_data_cnt = count($payment_grid_query);
        //$table_data_arr = $wpdb->get_results("SELECT * FROM ({$ctquery}) AS arm_payment_history_log LIMIT ".$perPage." OFFSET ".$offset, 'ARRAY_A');

        if($is_export_to_csv == true || $is_export_to_csv == '1') { 
            $payment_data_query = $wpdb->get_results("SELECT * FROM ({$ctgquery}) as arm_payment_history_log_data", 'ARRAY_A');
        } else {
            $payment_data_query = $wpdb->get_results("SELECT arm_log_id, arm_created_date, arm_payment_gateway, arm_user_id, arm_payer_email, arm_invoice_id, arm_amount, arm_currency FROM ({$ctgquery}) as arm_payment_history_log_data LIMIT {$perPage} OFFSET {$offset}", 'ARRAY_A');    
        }
        

        $table_data_cnt2 = $wpdb->get_var( "SELECT COUNT(*) as total FROM ({$ctgquery}) AS arm_payment_history_log_data" );
    }
    
    
    $wp_date_time_format = "";
    if (is_multisite()) {
        $wp_date_time_format = get_option('date_format') . " " . get_option('time_format');
    } else {
        $wp_date_time_format = get_site_option('date_format') . " " . get_site_option('time_format');
    }
    $dataPaging = $arm_global_settings->arm_get_paging_links($current_page, $table_data_cnt2, $perPage, '');
    
    $table_content_html = "";
    $tab_row_cnt = 0;
    $arm_charts_plan_data=array();
    $arm_charts_plan_cnt = 0;
    if(!empty($table_data_cnt_arr))
    {
        foreach($table_data_cnt_arr as $key => $table_data_cnt_val)
        {
             $arm_created_date = date_i18n($wp_date_time_format, strtotime($table_data_cnt_val['arm_created_date']));
            $payment_gateway = ucfirst( str_replace('_', ' ', $table_data_cnt_val['arm_payment_gateway'] ) );
            
            if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts" && !empty($table_data_cnt_val['arm_user_id'])){
                $plan_arr = get_user_meta($table_data_cnt_val['arm_user_id'], "arm_user_plan_ids", true);
                    $paid_post_ids = get_user_meta($table_data_cnt_val['arm_user_id'], 'arm_user_post_ids', true);
                if(!empty($paid_post_ids))
                {
                    foreach($plan_arr as $key => $val)
                    {
                        if(!empty($paid_post_ids[$val]))
                        {
                            unset($plan_arr[$key]);
                        }
                    }
                }
                    
                if(!empty($plan_arr)) {

                    foreach ($plan_arr as $key => $plan) {
                        
                        $arm_plan_name=$arm_subscription_plans->arm_get_plan_name_by_id($plan);
                        if(!empty($arm_plan_name)){
                            $arm_charts_plan_data[$plan]['plan_name']=$arm_plan_name;
                            $arm_charts_plan_data[$plan]['plan_users'][]=$table_data_cnt_val['arm_user_id'];
                            $arm_charts_plan_cnt++;
                        }
                        
                    }
                }
            }
        }
    }

    
    if(!empty($payment_data_query)) {
        if($is_export_to_csv == true || $is_export_to_csv == '1') {
            //echo "<br>reputelog == 5";die;
            $arm_report_analytics->arm_export_report_to_csv($payment_data_query, $arm_report_type);
            exit;
        }
        foreach ($payment_data_query as $key => $table_data) {
            $arm_created_date = date_i18n($wp_date_time_format, strtotime($table_data['arm_created_date']));
            $payment_gateway = ucfirst( str_replace('_', ' ', $table_data['arm_payment_gateway'] ) );
            
            if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts" && !empty($table_data['arm_user_id'])){
                $plan_arr = get_user_meta($table_data['arm_user_id'], "arm_user_plan_ids", true);
                    $paid_post_ids = get_user_meta($table_data['arm_user_id'], 'arm_user_post_ids', true);
                if(!empty($paid_post_ids))
                {
                    foreach($plan_arr as $key => $val)
                    {
                        if(!empty($paid_post_ids[$val]))
                        {
                            unset($plan_arr[$key]);
                        }
                    }
                }
                    
                if(!empty($plan_arr)) {

                    foreach ($plan_arr as $key => $plan) {
                        
                        $arm_plan_name=$arm_subscription_plans->arm_get_plan_name_by_id($plan);
                        
                    }
                }
            }    
            $paid_by = !empty($table_data['arm_payer_email']) ? "<a class='arm_openpreview_popup' href='javascript:void(0)' data-id='".$table_data['arm_user_id']."'>".$table_data['arm_payer_email']."</a>" : '-';
            $table_content_html .= "<tr class='arm_member_last_subscriptions_data'>";

                $arm_invoice_id = $arm_global_settings->arm_manipulate_invoice_id($table_data['arm_invoice_id']);

                $arm_log_type = ($table_data['arm_payment_gateway'] == 'bank_transfer') ? 'bt_log' : 'other';

                $arm_invoice_id = "<a class='armhelptip arm_invoice_detail tipso_style' href='javascript:void(0)' data-log_type='".$arm_log_type."' data-log_id='".$table_data['arm_log_id']."'>".$arm_invoice_id."</a>";
                
                $table_content_html .= "<td>".$arm_invoice_id."</td>"; 
                $table_content_html .= "<td>".$paid_by."</td>"; 
                $table_content_html .= "<td>".number_format($table_data['arm_amount'],2)." ".$table_data['arm_currency']."</td>"; 
                $table_content_html .= "<td>".$payment_gateway."</td>"; 
                $table_content_html .= "<td>".$arm_created_date."</td>";
            $table_content_html .= "</tr>";
            $tab_row_cnt++;
        }
    } else {
        $table_content_html .= "<tr class='arm_member_last_subscriptions_data'>";
            $table_content_html .= "<th class='arm_report_grid_no_data' colspan='5'>".esc_html__( 'No records found', 'ARMember')."</th>";
        $table_content_html .= "</tr>";
    }


    $payment_gt_data = array();
    if (!empty($payment_grid_query)) {
        $j = 0;
        
        foreach ($payment_grid_query as $key => $payment_gt) {
            if ($type == "daily") {
                $time = date("H:i", mktime($payment_gt['time'], 0));
            } else {
                $time = $payment_gt['time'];
            }
            $payment_gt_data[$time][] = array(
                "name"  => $payment_gt['arm_payment_gateway'],
                "total" => number_format( $payment_gt['Total'], 2, '.', ''),
            );
        }
    }

    $tooltip = "";
    if ($type == "yearly") {
        for ($i = 1; $i <= 12; $i++) {
            $amount = 0;
            $new_month_year = empty($new_month_year) ? $new_year : $new_month_year;

            $gateway_name .= "'".date('M',mktime(0, 0, 0, $i, 1, $new_month_year))."', ";
            if (isset($payment_gt_data[$i]) && !empty($payment_gt_data[$i])) {
                $name = "";
                
                foreach ($payment_gt_data[$i] as $key => $value) {
                    if ($value['name'] == "manual") {
                        $name .= __("Paid By Admin", "ARMember")." : ".number_format($value['total'],2,'.','')."<br>";
                    } else {
                        $title = ucwords(str_replace("_", " ", $value['name']));
                        $name .= "{$title} : ".number_format($value['total'],2,'.','')."<br>";
                    }
                    $amount += $value['total'];
                }
                $tool_tip .= "'{$name}', ";
            } else {
                $tool_tip .= "' ', ";
            }
            $amount = number_format($amount,2,'.','');
            $total_amount .= "{$amount}, ";
        }
    } else if ($type == "monthly") {
        $month_name = date("M", mktime(0, 0, 0, $new_month, 1));
        for ($i = 1; $i <= $day_last; $i++) {
            $amount = 0;
            $gateway_name .= "'{$i}-{$month_name}', ";
            if (isset($payment_gt_data[$i]) && !empty($payment_gt_data[$i])) {
                $name = "";
                foreach ($payment_gt_data[$i] as $key => $value) {
                    if ($value['name'] == "manual") {
                        $name .= __("Paid By Admin", "ARMember")." : ".number_format($value['total'],2,'.','')."<br>";
                    } else {
                        $title = ucwords(str_replace("_", " ", $value['name']));
                        $name .= "{$title} : ".number_format($value['total'],2,'.','')."<br>";
                    }
                    $amount += number_format($value['total'],2,'.','');
                }
                $tool_tip .= "'{$name}', ";
            } else {
                $tool_tip .= "' ', ";
            }
            $amount = number_format($amount,2,'.','');
            $total_amount .= "{$amount}, ";
        }
    } else {
        for ($i = 0; $i <= 23; $i++) {
            $amount = 0;
            $hour = mktime("{$i}", 0);
            $current_hour = date('H:i', $hour);
            $next_hour    = date('H:i', strtotime("+1 hour", $hour));
            $gateway_name .= "'{$current_hour}', ";
            $name = "";
            if (isset($payment_gt_data[$current_hour]) && !empty($payment_gt_data[$current_hour])) {
                foreach ($payment_gt_data[$current_hour] as $key => $value) {
                    $amount += $value['total'];
                    $value['name'] = str_replace("manual", __("Paid By Admin", "ARMember"), $value['name']);
                    $value['name'] = ucwords(str_replace("_", " ", $value['name']));
                    if (!empty($name) && strpos($name, $value['name']) === false) {
                        $total = number_format( $value['total'], 2,'.','' );
                        $name .= "{$value['name']} : ".number_format($value['total'],2,'.','')."<br>";
                    } else {
                        $total = number_format( $previous_amt, 2,'.','' ) + number_format( $value['total'], 2,'.','' );
                        $name = str_replace($previous_amt, $total, $name);
                    }
                    $previous_amt = number_format($total,2,'.','');
                }
                $tool_tip .= "'{$name}', ";
            } else {
                $tool_tip .= "' ', ";
            }
            $amount = number_format($amount,2,'.','');
            $total_amount .= "{$amount}, ";
        }
    }

    $gateway_name .= "]";
    $tool_tip .= "]";
    $total_amount .= "]";


    if( $is_pagination ){
        echo $table_content_html.'[ARM_REPORT_SEPARATOR]'.str_replace("\n", "", $dataPaging);    
        die;
    }
    ?>

    <script type="text/javascript" data-cfasync="false">
        
        jQuery(".arm_payments_table_body_content").html("<?php echo $table_content_html; ?>");
        jQuery("#arm_payments_table_paging").html('<?php echo str_replace("\n","", $dataPaging); ?>');

        jQuery.noConflict();
        jQuery(document).ready(function($){
            var graph_type   = "<?php echo $graph_type;?>";
            var gateway_name = <?php echo $gateway_name; ?>;
            var total_amount = <?php echo $total_amount; ?>;
            var tool_tip     = <?php echo $tool_tip; ?>;
            var currency_symbol = '<?php echo html_entity_decode($currency_symbol); ?>';
            if(graph_type == 'bar'){
                var armChart = Highcharts.chart(<?php echo $chart; ?>, {
                    chart: {
                        type: 'column',
                    },
                    title: {
                        text: ''
                    },
                    subtitle: {
                        text: ''
                    },
                    credits : {
                        enabled : false
                    },
                    xAxis: {
                        categories: gateway_name,
                        crosshair: true,
                        labels: {
                            rotation: - 60
                        },
                        min : 0.5
                    },
                    yAxis: {
                        min: 0,
                        allowDecimals: false,
                        title: {text: 'Amount'}
                    },
                    legend: {
                        enabled: false
                    },
                    plotOptions: {
                        areaspline: {
                            fillOpacity: 0.05,
                            dataLabels: {enabled: false, format: '{point.y}'},
                            lineColor: '#4da4fe',
                        }
                    },
                    tooltip: {
                        formatter: function() {
                            var tooltip = "";
                            var index = this.point.index;
                            var name  = tool_tip[index];
                            if (name != ' ') {
                                tooltip  = '<span style="font-size:12px">' + name + '</span>';
                                tooltip += '<div style="color:' + this.series.color + '">Total : </div><b>' + currency_symbol + ' ' + this.y.toFixed(2) + '</b><div style="color:' + this.series.color + '"></div>';
                            } else {
                                tooltip = '<div style="color:' + this.series.color + '">Total : </div><b>0.00' + currency_symbol + ' ' +'</b><div style="color:' + this.series.color + '"></div>';
                            }
                            return tooltip;
                        }
                    },
                    colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                    series: [{
                        name: "Membership",
                        color: 'rgb(77,164,254)',
                        colorByPoint: true,
                        lineWidth: 2,
                        data: total_amount,
                    }],
                });
            } else if(graph_type == 'line'){
                var armChart = Highcharts.chart(<?php echo $chart; ?>, {
                    chart: {
                        type: 'areaspline',
                    },
                    credits : {
                        enabled : false
                    },
                    title: {
                        text: ''
                    },
                    subtitle: {
                        text: ''
                    },
                    xAxis: {
                        categories: gateway_name,
                        crosshair: true,
                        labels: {
                            rotation: - 60
                        },
                        min : 0.5
                    },
                    yAxis: {
                        min: 0,
                        allowDecimals: false,
                        title: {
                            text: 'Amount'
                        }
                    },
                    legend: {enabled: false},
                    plotOptions: {
                        areaspline: {
                            fillOpacity: 0.05,
                            dataLabels: {
                                enabled: false,
                                format: '{point.y}'
                            },
                            lineColor: '#4da4fe',
                        }
                    },
                    tooltip: {
                        formatter: function() {
                            var tooltip = "";
                            var index = this.point.index;
                            var name  = tool_tip[index];
                            if (name != ' ') {
                                tooltip  = '<span style="font-size:12px">' + name + '</span>';
                                tooltip += '<div style="color:' + this.series.color + '">Total : </div><b>'  + currency_symbol + ' ' + this.y.toFixed(2)  + '</b><div style="color:' + this.series.color + '"></div>';
                            } else {
                                tooltip = '<div style="color:' + this.series.color + '">Total : </div><b>'  + currency_symbol + ' ' + '0.00</b><div style="color:' + this.series.color + '"></div>';
                            }
                            return tooltip;
                        }
                    },
                    colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                    series: [{
                        color: 'rgb(77,164,254)',
                        colorByPoint: true,
                        lineWidth: 2,
                        data: total_amount,
                    }],
                });
            }
            var normalState = new Object();
            normalState.stroke_width1 = 1;
            normalState.stroke = '#afcaff';
            normalState.fill = 'rgba(255,255,255,0.9)';
            normalState.padding = 10;
            normalState.r = 6;
            normalState.width = 16;
            normalState.height = 16;
            normalState.align = 'center';
            var hoverState = new Object();
            hoverState = normalState;
            var pressedState = new Object();
            pressedState = normalState;
        <?php 
            if(empty($arm_disable_next_prev_btn))
            {
        ?>
            armChart.renderer.button('', 56, 70, function(){arm_change_graph_pre('<?php echo $type; ?>','<?php echo $arm_enable_prev;?>', 'payment_history')}, normalState, hoverState, pressedState).attr({id:'arm_prev_button',class:'<?php echo $arm_disable_class_prev;?>'}).add().toFront();
            armChart.renderer.button('', (armChart.chartWidth - 30), 70, function(){arm_change_graph_next('<?php echo $type; ?>','<?php echo $arm_enable_next;?>', 'payment_history')}, normalState, hoverState, pressedState).attr({id:'arm_next_button',class:'<?php echo $arm_disable_class_next;?>'}).add().toFront();
        <?php
            }
        ?>
            jQuery('.highcharts-container').find('#arm_prev_button').find('text').remove();
            jQuery('.highcharts-container').find('#arm_prev_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M1.221,8.318l8.002,8.002l2.001-2L5.221,8.316l6.003-6.003  l-2.001-2L1.221,8.315l0.001,0.001L1.221,8.318z"/></svg>');
            jQuery('.highcharts-container').find('#arm_next_button').find('text').remove();
            jQuery('.highcharts-container').find('#arm_next_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M11.431,8.601l-8.002,8.002l-2.001-2l6.003-6.003L1.428,2.596 l2.001-2l8.002,8.002L11.43,8.599L11.431,8.601z"/></svg>');
        });
    </script>
    <?php if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts"){?>
        <?php if($type == 'daily'){?>
            <div class="armchart_display_title arm_ml_5">
            <label class="armcharttitle">
                <?php echo date(get_option('date_format'), strtotime($new_day . '-' . $new_day_month . '-' . $new_day_year)); ?>
            </label>
        </div>
        <?php }?>
        <?php if($type == 'monthly'){?>
            <?php $monthName = date("F", mktime(0, 0, 0, (int)$new_month, 10)); ?>
            <div class="armchart_display_title arm_ml_5">
                <label class="armcharttitle"><?php echo $monthName . "-" . $new_month_year; ?></label>
            </div>
        <?php }?>
        <?php if($type == 'yearly'){?>
            <div class="armchart_display_title arm_ml_5">
                <label class="armcharttitle"><?php echo $new_year; ?></label>
            </div>
        <?php }?>
        <div class="armchart_plan_section">
            <?php if(isset($arm_charts_plan_data) && count($arm_charts_plan_data)>0){
                foreach ($arm_charts_plan_data as $arm_charts_plan_key => $arm_charts_plan_row) {
                ?>
                <div class="armchart_plan_item">
                    <label class="armchart_plan_title"><?php echo $arm_charts_plan_row['plan_name']; ?></label>
                    <p class="armchart_plan_item_desc"><?php echo __("Members:", "ARMember");?> <?php echo count($arm_charts_plan_row['plan_users']); ?></p>
                </div>
            <?php 
                }
            }else{
                    echo '<div class="armchart_plan_no_item">'. __("Recently No Membership Plan purchased", "ARMember").'</div>';
                } ?>  
        </div>
        <div class="armchart_view_section">
    <?php }?>
        <div id="daily_payment_history" style="padding:15px;<?php echo ($type == 'daily') ? 'display:block;' : 'display:none'; ?>">
            <?php if(!isset($_REQUEST['action'])){?>
            <div class="armchart_display_title">
                <label class="armcharttitle">
                    <?php echo date(get_option('date_format'), strtotime($new_day . '-' . $new_day_month . '-' . $new_day_year)); ?>
                </label>
            </div>
            <?php }?>
            <div id="chart1_payment_history" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            
            <input type="hidden" value="<?php echo $new_day; ?>" name="current_day" id="current_day" />
            <input type="hidden" value="<?php echo $new_day_month; ?>" name="current_day_month" id="current_day_month" />
            <input type="hidden" value="<?php echo $new_day_year; ?>" name="current_day_year" id="current_day_year" />
        </div>

        <div id="monthly_payment_history" style="padding:15px; <?php echo ($type == 'monthly') ? 'display:block;' : 'display:none'; ?>">
            <?php if(!isset($_REQUEST['action'])){?>
            <?php $monthName = date("F", mktime(0, 0, 0, (int)$new_month, 10)); ?>
            <div class="armchart_display_title">
                <label class="armcharttitle"><?php echo $monthName . "-" . $new_month_year; ?></label>
            </div>
            <?php }?>
            <div id="chart2_payment_history" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            <input type="hidden" value="<?php echo $new_month; ?>" name="current_month" id="current_month" />
            <input type="hidden" value="<?php echo $new_month_year; ?>" name="current_month_year" id="current_month_year" />
        </div>

        <div id="yearly_payment_history" style="padding:15px; <?php echo ($type == 'yearly') ? 'display:block;' : 'display:none'; ?>">
            <div class="arlinks link_align"></div>
            <?php if(!isset($_REQUEST['action'])){?>
            <div class="armchart_display_title">
                <label class="armcharttitle"><?php echo $new_year; ?></label>
            </div>
            <?php }?>
            <div id="chart3_payment_history" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            <input type="hidden" value="<?php echo $new_year; ?>" name="current_year" id="current_year" />
        </div>
    <?php if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts"){?>
    </div>
    <?php }?>    
    <?php
} else if ($plan_type == 'pay_per_post_report') {
    $chart     = "chart2_pay_per_post_report";
    $gateway_name  = $total_amount = $tool_tip = "[";
    $gateway_name .= "' ', ";
    $tool_tip     .= "' ', ";
    $total_amount .= "0, ";
    $ptquery = $wpdb->get_var("SELECT min(arm_created_date) FROM `{$ARMember->tbl_arm_payment_log}`");
    
    $ctquery = "";
    $cbquery = "";
    $ctquery_where = "";


    if(!empty($filter_plan_id)) {
        $ctquery_where .= " AND pt.arm_plan_id=".$filter_plan_id." ";
        
    }
    if(!empty($gateway_filter)) {
        $ctquery_where .= " AND pt.arm_payment_gateway='".$gateway_filter."'";
    }

    if (!empty($ptquery)) {
        $min_year  = date('Y', strtotime($ptquery));
        $min_month = date('m', strtotime($ptquery));
        $min_date  = date('d', strtotime($ptquery));
    }

    $arm_disable_class_next = $arm_disable_class_prev = '';
    $arm_enable_next = $arm_enable_prev = 1;
    $chart     = "chart3_pay_per_post_report";
    if ($type == 'yearly') {
        if ($new_year >= date('Y', current_time('timestamp'))) {
            $arm_disable_class_next = 'arm_disabled_class_next';
            $arm_enable_next = 0;
        }

        if ($new_year <= $min_year) {
            $arm_disable_class_prev = 'arm_disabled_class_prev';
            $arm_enable_prev = 0;
        }

        $chart     = "chart3_pay_per_post_report";
        $from_year = "{$new_year}-01-01 00:00:00";
        $end_year  = "{$new_year}-12-31 23:59:59";

        $ctquery = "SELECT pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_amount, pt.arm_created_date, pt.arm_payment_gateway, MONTH(pt.arm_created_date) AS time, SUM(pt.arm_amount) as Total FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 1 {$ctquery_where} AND pt.arm_created_date BETWEEN '{$from_year}' AND '{$end_year}' Group By pt.arm_payment_gateway, MONTH(pt.arm_created_date)";
        
        $ctgquery = "SELECT pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_currency, pt.arm_amount AS arm_amount, pt.arm_created_date, pt.arm_payment_gateway FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 1 {$ctquery_where} AND pt.arm_created_date BETWEEN '{$from_year}' AND '{$end_year}'";

        
    } else if ($type == 'monthly') {
        $chart     = "chart2_pay_per_post_report";
        $date      = $new_month_year.'-'.$new_month.'-'.date('m', current_time('timestamp'));
        $day_first = date('01', strtotime($date));
        $day_last  = date('t', strtotime($date));

        $first = "{$new_month_year}-{$new_month}-{$day_first}";
        $last  = "{$new_month_year}-{$new_month}-{$day_last}";

        $max_month_limit = date('Y-m',mktime(0, 0, 0, $new_month,1 , $new_month_year));
        if ($max_month_limit >= date('Y-m')) {
            $arm_disable_class_next = 'arm_disabled_class_next';
            $arm_enable_next = 0;
        }
        $month_limit     = date('Y-m-d',mktime(0, 0, 0, $new_month, 1, $new_month_year));
        $min_month_limit = date('Y-m-d',mktime(0, 0, 0, $min_month, 1, $min_year));
        if ($min_month_limit >= $month_limit) {
            $arm_disable_class_prev = 'arm_disabled_class_prev';
            $arm_enable_prev = 0;
        }

        $ctquery = "SELECT pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_amount, pt.arm_created_date, pt.arm_payment_gateway, DAY(pt.arm_created_date) as time, SUM(pt.arm_amount) as Total FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 1 {$ctquery_where} AND pt.arm_created_date BETWEEN '{$first}' AND '{$last}' Group By pt.arm_payment_gateway, DAY(pt.arm_created_date)";
        
        $ctgquery = "SELECT pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_currency, pt.arm_amount AS arm_amount, pt.arm_created_date, pt.arm_payment_gateway FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE 1=1 AND pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 1 {$ctquery_where} AND pt.arm_created_date BETWEEN '{$first}' AND '{$last}'";
        
        
    } else if ($type == 'daily') {
        //echo "<br>reputelog == 1";
        $chart = "chart1_pay_per_post_report";
        $date = "{$new_day_year}-{$new_day_month}-{$new_day}";

        $date = date("Y-m-d", mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
        $max_date_limit = date('Y-m-d',mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
        if ($max_date_limit >= date('Y-m-d')) {
            $arm_disable_class_next = 'arm_disabled_class_next';
            $arm_enable_next = 0;
        }
        $date_limit     = date('Y-m-d', mktime(0, 0, 0, $new_day_month, $new_day, $new_day_year));
        $min_date_limit = date('Y-m-d', mktime(0, 0, 0, $min_month, $min_date, $min_year));
        if ($min_date_limit >= $date_limit) {
            $arm_disable_class_prev = 'arm_disabled_class_prev';
            $arm_enable_prev = 0;
        }

        $ctquery = "SELECT pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_amount, pt.arm_created_date, pt.arm_payment_gateway, TIME(pt.arm_created_date) as time, SUM(pt.arm_amount) as Total FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 1 {$ctquery_where} AND pt.arm_created_date LIKE '{$date}%'";
        
        $ctgquery = "SELECT pt.arm_user_id, pt.arm_invoice_id, pt.arm_payer_email, pt.arm_currency, pt.arm_amount AS arm_amount, pt.arm_created_date, pt.arm_payment_gateway FROM `{$ARMember->tbl_arm_payment_log}` pt WHERE 1=1 AND pt.arm_display_log = 1 AND (pt.arm_transaction_status = 'success' || pt.arm_transaction_status = '1') AND arm_is_post_payment = 1 {$ctquery_where} AND pt.arm_created_date LIKE '{$date}%'";
      
    }

    //echo "<br>reputelog == 2";
    /*for data table*/
    $table_data_arr = array();
    $table_data_cnt = 0;
    $table_data_cnt_arr = $wpdb->get_results("SELECT arm_created_date, arm_payment_gateway, arm_user_id FROM ({$ctquery}) AS arm_pay_per_post_report_log", 'ARRAY_A');
    $perPage = 10;
    $offset = ($current_page - 1) * $perPage;

    if(!empty($gateway_filter)) {
        
        $payment_grid_query = $wpdb->get_results("SELECT arm_payment_gateway, time, Total FROM ({$ctquery}) AS arm_pay_per_post_report_log", 'ARRAY_A');
        /*for data table*/
        $table_data_cnt = count($payment_grid_query);
        //$table_data_arr = $wpdb->get_results("SELECT * FROM ({$ctquery}) AS arm_pay_per_post_report_log LIMIT ".$perPage." OFFSET ".$offset, 'ARRAY_A');

        if($is_export_to_csv == true || $is_export_to_csv == '1') { 
            $payment_data_query = $wpdb->get_results("SELECT * FROM ({$ctgquery}) as arm_pay_per_post_report_log_data", 'ARRAY_A');
        } else {
            $payment_data_query = $wpdb->get_results("SELECT arm_created_date, arm_payment_gateway, arm_user_id, arm_payer_email, arm_invoice_id, arm_amount, arm_currency FROM ({$ctgquery}) as arm_pay_per_post_report_log_data LIMIT {$perPage} OFFSET {$offset}", 'ARRAY_A');           
        }
        

        $table_data_cnt2 = $wpdb->get_var( "SELECT COUNT(*) as total FROM ({$ctgquery}) AS arm_pay_per_post_report_log_data" );

    } else if(empty($gateway_filter)) {

        $payment_grid_query = $wpdb->get_results("SELECT arm_payment_gateway, time, Total FROM ({$ctquery}) AS arm_pay_per_post_report_log", 'ARRAY_A');        

        /*for data table*/
        $table_data_cnt = count($payment_grid_query);
        //$table_data_arr = $wpdb->get_results("SELECT * FROM ({$ctquery}) AS arm_pay_per_post_report_log LIMIT ".$perPage." OFFSET ".$offset, 'ARRAY_A');

        if($is_export_to_csv == true || $is_export_to_csv == '1') { 
            $payment_data_query = $wpdb->get_results("SELECT * FROM ({$ctgquery}) as arm_pay_per_post_report_log_data", 'ARRAY_A');
        } else {
            $payment_data_query = $wpdb->get_results("SELECT arm_created_date, arm_payment_gateway, arm_user_id, arm_payer_email, arm_invoice_id, arm_amount, arm_currency FROM ({$ctgquery}) as arm_pay_per_post_report_log_data LIMIT {$perPage} OFFSET {$offset}", 'ARRAY_A');    
        }
        

        $table_data_cnt2 = $wpdb->get_var( "SELECT COUNT(*) as total FROM ({$ctgquery}) AS arm_pay_per_post_report_log_data" );
        
        
    }


    $wp_date_time_format = "";
    if (is_multisite()) {
        $wp_date_time_format = get_option('date_format') . " " . get_option('time_format');
    } else {
        $wp_date_time_format = get_site_option('date_format') . " " . get_site_option('time_format');
    }
    $dataPaging = $arm_global_settings->arm_get_paging_links($current_page, $table_data_cnt2, $perPage, '');
    
    $table_content_html = "";
    $tab_row_cnt = 0;
    $arm_charts_plan_data=array();
    if(!empty($table_data_cnt_arr))
    {
        foreach($table_data_cnt_arr as $key => $table_data_cnt_val)
        {
            $arm_created_date = date_i18n($wp_date_time_format, strtotime($table_data_cnt_val['arm_created_date']));
            $payment_gateway = ucfirst( str_replace('_', ' ', $table_data_cnt_val['arm_payment_gateway'] ) );
            
            if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts" && !empty($table_data_cnt_val['arm_user_id'])){
                $plan_arr = get_user_meta($table_data_cnt_val['arm_user_id'], "arm_user_plan_ids", true);
                $paid_post_ids = get_user_meta($table_data_cnt_val['arm_user_id'], 'arm_user_post_ids', true);

                if(!empty($paid_post_ids))
                {
                    foreach($plan_arr as $key => $val)
                    {
                        if(empty($paid_post_ids[$val]))
                        {
                            unset($plan_arr[$key]);
                        }
                    }
                }
                    
                if(!empty($plan_arr)) {

                    foreach ($plan_arr as $key => $plan) {
                        
                        $arm_plan_name=$arm_subscription_plans->arm_get_plan_name_by_id($plan);
                        if(!empty($arm_plan_name)){
                            $arm_charts_plan_data[$plan]['plan_name']=$arm_plan_name;
                            $arm_charts_plan_data[$plan]['plan_users'][]=$table_data_cnt_val['arm_user_id'];
                        }
                        
                    }
                }
            }
        }
    }




    if(!empty($payment_data_query)) {
        if($is_export_to_csv == true || $is_export_to_csv == '1') {
            //echo "<br>reputelog == 5";die;
            $arm_report_analytics->arm_export_report_to_csv($payment_data_query, $arm_report_type);
            exit;
        }
        foreach ($payment_data_query as $key => $table_data) {
            $arm_created_date = date_i18n($wp_date_time_format, strtotime($table_data['arm_created_date']));
            $payment_gateway = ucfirst( str_replace('_', ' ', $table_data['arm_payment_gateway'] ) );
            
            if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts" && !empty($table_data['arm_user_id'])){
                $plan_arr = get_user_meta($table_data['arm_user_id'], "arm_user_plan_ids", true);
                $paid_post_ids = get_user_meta($table_data['arm_user_id'], 'arm_user_post_ids', true);

                if(!empty($paid_post_ids))
                {
                    foreach($plan_arr as $key => $val)
                    {
                        if(empty($paid_post_ids[$val]))
                        {
                            unset($plan_arr[$key]);
                        }
                    }
                }
                    
                if(!empty($plan_arr)) {

                    foreach ($plan_arr as $key => $plan) {
                        
                        $arm_plan_name=$arm_subscription_plans->arm_get_plan_name_by_id($plan);
                        
                    }
                }
            }    
            $paid_by = !empty($table_data['arm_payer_email']) ? "<a class='arm_openpreview_popup' href='javascript:void(0)' data-id='".$table_data['arm_user_id']."'>".$table_data['arm_payer_email']."</a>" : '-';
            $table_content_html .= "<tr class='arm_member_last_subscriptions_data'>";
                $table_content_html .= "<td>".$table_data['arm_invoice_id']."</td>"; 
                $table_content_html .= "<td>".$paid_by."</td>"; 
                $table_content_html .= "<td>".number_format($table_data['arm_amount'],2)." ".$table_data['arm_currency']."</td>"; 
                $table_content_html .= "<td>".$payment_gateway."</td>"; 
                $table_content_html .= "<td>".$arm_created_date."</td>";
            $table_content_html .= "</tr>";
            $tab_row_cnt++;
        }
    } else {
        $table_content_html .= "<tr class='arm_member_last_subscriptions_data'>";
            $table_content_html .= "<th class='arm_report_grid_no_data' colspan='5'>".esc_html__( 'No records found', 'ARMember')."</th>";
        $table_content_html .= "</tr>";
    }

    $payment_gt_data = array();
    if (!empty($payment_grid_query)) {
        $j = 0;
        
        foreach ($payment_grid_query as $key => $payment_gt) {
            if ($type == "daily") {
                $time = date("H:i", mktime($payment_gt['time'], 0));
            } else {
                $time = $payment_gt['time'];
            }
            $payment_gt_data[$time][] = array(
                "name"  => $payment_gt['arm_payment_gateway'],
                "total" => number_format( $payment_gt['Total'], 2, '.', ''),
            );
        }
    }

    $tooltip = "";
    if ($type == "yearly") {
        for ($i = 1; $i <= 12; $i++) {
            $amount = 0;
            $new_month_year = empty($new_month_year) ? $new_year : $new_month_year;

            $gateway_name .= "'".date('M',mktime(0, 0, 0, $i, 1, $new_month_year))."', ";
            if (isset($payment_gt_data[$i]) && !empty($payment_gt_data[$i])) {
                $name = "";
                
                foreach ($payment_gt_data[$i] as $key => $value) {
                    if ($value['name'] == "manual") {
                        $name .= __("Paid By Admin", "ARMember")." : ".number_format($value['total'],2,'.','')."<br>";
                    } else {
                        $title = ucwords(str_replace("_", " ", $value['name']));
                        $name .= "{$title} : ".number_format($value['total'],2,'.','')."<br>";
                    }
                    $amount += $value['total'];
                }
                $tool_tip .= "'{$name}', ";
            } else {
                $tool_tip .= "' ', ";
            }
            $amount = number_format($amount,2,'.','');
            $total_amount .= "{$amount}, ";
        }
    } else if ($type == "monthly") {
        $month_name = date("M", mktime(0, 0, 0, $new_month, 1));
        for ($i = 1; $i <= $day_last; $i++) {
            $amount = 0;
            $gateway_name .= "'{$i}-{$month_name}', ";
            if (isset($payment_gt_data[$i]) && !empty($payment_gt_data[$i])) {
                $name = "";
                foreach ($payment_gt_data[$i] as $key => $value) {
                    if ($value['name'] == "manual") {
                        $name .= __("Paid By Admin", "ARMember")." : ".number_format($value['total'],2,'.','')."<br>";
                    } else {
                        $title = ucwords(str_replace("_", " ", $value['name']));
                        $name .= "{$title} : ".number_format($value['total'],2,'.','')."<br>";
                    }
                    $amount += number_format($value['total'],2,'.','');
                }
                $tool_tip .= "'{$name}', ";
            } else {
                $tool_tip .= "' ', ";
            }
            $amount = number_format($amount,2,'.','');
            $total_amount .= "{$amount}, ";
        }
    } else {
        for ($i = 0; $i <= 23; $i++) {
            $amount = 0;
            $hour = mktime("{$i}", 0);
            $current_hour = date('H:i', $hour);
            $next_hour    = date('H:i', strtotime("+1 hour", $hour));
            $gateway_name .= "'{$current_hour}', ";
            $name = "";
            if (isset($payment_gt_data[$current_hour]) && !empty($payment_gt_data[$current_hour])) {
                foreach ($payment_gt_data[$current_hour] as $key => $value) {
                    $amount += $value['total'];
                    $value['name'] = str_replace("manual", __("Paid By Admin", "ARMember"), $value['name']);
                    $value['name'] = ucwords(str_replace("_", " ", $value['name']));
                    if (!empty($name) && strpos($name, $value['name']) === false) {
                        $total = number_format( $value['total'], 2,'.','' );
                        $name .= "{$value['name']} : ".number_format($value['total'],2,'.','')."<br>";
                    } else {
                        $total = number_format( $previous_amt, 2,'.','' ) + number_format( $value['total'], 2,'.','' );
                        $name = str_replace($previous_amt, $total, $name);
                    }
                    $previous_amt = number_format($total,2,'.','');
                }
                $tool_tip .= "'{$name}', ";
            } else {
                $tool_tip .= "' ', ";
            }
            $amount = number_format($amount,2,'.','');
            $total_amount .= "{$amount}, ";
        }
    }

    $gateway_name .= "]";
    $tool_tip .= "]";
    $total_amount .= "]";


    if( $is_pagination ){
        echo $table_content_html.'[ARM_REPORT_SEPARATOR]'.str_replace("\n", "", $dataPaging);    
        die;
    }
    ?>

    <script type="text/javascript" data-cfasync="false">
        
        jQuery(".arm_pay_per_post_report_table_body_content").html("<?php echo $table_content_html; ?>");
        jQuery("#arm_payments_table_paging").html('<?php echo str_replace("\n","", $dataPaging); ?>');

        jQuery.noConflict();
        jQuery(document).ready(function($){
            var graph_type   = "<?php echo $graph_type;?>";
            var gateway_name = <?php echo $gateway_name; ?>;
            var total_amount = <?php echo $total_amount; ?>;
            var tool_tip     = <?php echo $tool_tip; ?>;
            var currency_symbol = '<?php echo html_entity_decode($currency_symbol); ?>';
            if(graph_type == 'bar'){
                var armChart = Highcharts.chart(<?php echo $chart; ?>, {
                    chart: {
                        type: 'column',
                    },
                    title: {
                        text: ''
                    },
                    subtitle: {
                        text: ''
                    },
                    credits : {
                        enabled : false
                    },
                    xAxis: {
                        categories: gateway_name,
                        crosshair: true,
                        labels: {
                            rotation: - 60
                        },
                        min : 0.5
                    },
                    yAxis: {
                        min: 0,
                        allowDecimals: false,
                        title: {text: 'Amount'}
                    },
                    legend: {
                        enabled: false
                    },
                    plotOptions: {
                        areaspline: {
                            fillOpacity: 0.05,
                            dataLabels: {enabled: false, format: '{point.y}'},
                            lineColor: '#4da4fe',
                        }
                    },
                    tooltip: {
                        formatter: function() {
                            var tooltip = "";
                            var index = this.point.index;
                            var name  = tool_tip[index];
                            if (name != ' ') {
                                tooltip  = '<span style="font-size:12px">' + name + '</span>';
                                tooltip += '<div style="color:' + this.series.color + '">Total : </div><b>' + currency_symbol + ' ' + this.y.toFixed(2) + '</b><div style="color:' + this.series.color + '"></div>';
                            } else {
                                tooltip = '<div style="color:' + this.series.color + '">Total : </div><b>0.00' + currency_symbol + ' ' +'</b><div style="color:' + this.series.color + '"></div>';
                            }
                            return tooltip;
                        }
                    },
                    colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                    series: [{
                        name: "Membership",
                        color: 'rgb(77,164,254)',
                        colorByPoint: true,
                        lineWidth: 2,
                        data: total_amount,
                    }],
                });
            } else if(graph_type == 'line'){
                var armChart = Highcharts.chart(<?php echo $chart; ?>, {
                    chart: {
                        type: 'areaspline',
                    },
                    credits : {
                        enabled : false
                    },
                    title: {
                        text: ''
                    },
                    subtitle: {
                        text: ''
                    },
                    xAxis: {
                        categories: gateway_name,
                        crosshair: true,
                        labels: {
                            rotation: - 60
                        },
                        min : 0.5
                    },
                    yAxis: {
                        min: 0,
                        allowDecimals: false,
                        title: {
                            text: 'Amount'
                        }
                    },
                    legend: {enabled: false},
                    plotOptions: {
                        areaspline: {
                            fillOpacity: 0.05,
                            dataLabels: {
                                enabled: false,
                                format: '{point.y}'
                            },
                            lineColor: '#4da4fe',
                        }
                    },
                    tooltip: {
                        formatter: function() {
                            var tooltip = "";
                            var index = this.point.index;
                            var name  = tool_tip[index];
                            if (name != ' ') {
                                tooltip  = '<span style="font-size:12px">' + name + '</span>';
                                tooltip += '<div style="color:' + this.series.color + '">Total : </div><b>'  + currency_symbol + ' ' + this.y.toFixed(2)  + '</b><div style="color:' + this.series.color + '"></div>';
                            } else {
                                tooltip = '<div style="color:' + this.series.color + '">Total : </div><b>'  + currency_symbol + ' ' + '0.00</b><div style="color:' + this.series.color + '"></div>';
                            }
                            return tooltip;
                        }
                    },
                    colors: ['#766ed2;', '#fbc32b', '#fc6458', '#a7db1b', '#20d381', '#27ddfe', '#4da4fe'],
                    series: [{
                        color: 'rgb(77,164,254)',
                        colorByPoint: true,
                        lineWidth: 2,
                        data: total_amount,
                    }],
                });
            }
            var normalState = new Object();
            normalState.stroke_width1 = 1;
            normalState.stroke = '#afcaff';
            normalState.fill = 'rgba(255,255,255,0.9)';
            normalState.padding = 10;
            normalState.r = 6;
            normalState.width = 16;
            normalState.height = 16;
            normalState.align = 'center';
            var hoverState = new Object();
            hoverState = normalState;
            var pressedState = new Object();
            pressedState = normalState;
        <?php 
            if(empty($arm_disable_next_prev_btn))
            {
        ?>
            armChart.renderer.button('', 56, 70, function(){arm_change_graph_pre('<?php echo $type; ?>','<?php echo $arm_enable_prev;?>', 'pay_per_post_report')}, normalState, hoverState, pressedState).attr({id:'arm_prev_button',class:'<?php echo $arm_disable_class_prev;?>'}).add().toFront();
            armChart.renderer.button('', (armChart.chartWidth - 30), 70, function(){arm_change_graph_next('<?php echo $type; ?>','<?php echo $arm_enable_next;?>', 'pay_per_post_report')}, normalState, hoverState, pressedState).attr({id:'arm_next_button',class:'<?php echo $arm_disable_class_next;?>'}).add().toFront();
        <?php
            }
        ?>
            jQuery('.highcharts-container').find('#arm_prev_button').find('text').remove();
            jQuery('.highcharts-container').find('#arm_prev_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M1.221,8.318l8.002,8.002l2.001-2L5.221,8.316l6.003-6.003  l-2.001-2L1.221,8.315l0.001,0.001L1.221,8.318z"/></svg>');
            jQuery('.highcharts-container').find('#arm_next_button').find('text').remove();
            jQuery('.highcharts-container').find('#arm_next_button').append('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="12" y="10"><path xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" fill="#4786ff" d="M11.431,8.601l-8.002,8.002l-2.001-2l6.003-6.003L1.428,2.596 l2.001-2l8.002,8.002L11.43,8.599L11.431,8.601z"/></svg>');
        });
    </script>
    <?php if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts"){?>
        <?php if($type == 'daily'){?>
            <div class="armchart_display_title arm_ml_5">
            <label class="armcharttitle">
                <?php echo date(get_option('date_format'), strtotime($new_day . '-' . $new_day_month . '-' . $new_day_year)); ?>
            </label>
        </div>
        <?php }?>
        <?php if($type == 'monthly'){?>
            <?php $monthName = date("F", mktime(0, 0, 0, (int)$new_month, 10)); ?>
            <div class="armchart_display_title arm_ml_5">
                <label class="armcharttitle"><?php echo $monthName . "-" . $new_month_year; ?></label>
            </div>
        <?php }?>
        <?php if($type == 'yearly'){?>
            <div class="armchart_display_title arm_ml_5">
                <label class="armcharttitle"><?php echo $new_year; ?></label>
            </div>
        <?php }?>
        <div class="armchart_plan_section">
            <?php if(isset($arm_charts_plan_data) && count($arm_charts_plan_data)>0){
                foreach ($arm_charts_plan_data as $arm_charts_plan_key => $arm_charts_plan_row) {
                ?>
                <div class="armchart_plan_item">
                    <label class="armchart_plan_title"><?php echo $arm_charts_plan_row['plan_name']; ?></label>
                    <p class="armchart_plan_item_desc"><?php echo __("Members:", "ARMember");?> <?php echo count($arm_charts_plan_row['plan_users']); ?></p>
                </div>
            <?php 
                }
            }else{
                    echo '<div class="armchart_plan_no_item">'. __("Recently No Membership Plan purchased", "ARMember").'</div>';
                } ?>  
        </div>
        <div class="armchart_view_section">
    <?php }?>
        <div id="daily_pay_per_post_report" style="padding:15px;<?php echo ($type == 'daily') ? 'display:block;' : 'display:none'; ?>">
            <?php if(!isset($_REQUEST['action'])){?>
            <div class="armchart_display_title">
                <label class="armcharttitle">
                    <?php echo date(get_option('date_format'), strtotime($new_day . '-' . $new_day_month . '-' . $new_day_year)); ?>
                </label>
            </div>
            <?php }?>
            <div id="chart1_pay_per_post_report" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            
            <input type="hidden" value="<?php echo $new_day; ?>" name="current_day" id="current_day" />
            <input type="hidden" value="<?php echo $new_day_month; ?>" name="current_day_month" id="current_day_month" />
            <input type="hidden" value="<?php echo $new_day_year; ?>" name="current_day_year" id="current_day_year" />
        </div>

        <div id="monthly_pay_per_post_report" style="padding:15px; <?php echo ($type == 'monthly') ? 'display:block;' : 'display:none'; ?>">
            <?php if(!isset($_REQUEST['action'])){?>
            <?php $monthName = date("F", mktime(0, 0, 0, (int)$new_month, 10)); ?>
            <div class="armchart_display_title">
                <label class="armcharttitle"><?php echo $monthName . "-" . $new_month_year; ?></label>
            </div>
            <?php }?>
            <div id="chart2_pay_per_post_report" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            <input type="hidden" value="<?php echo $new_month; ?>" name="current_month" id="current_month" />
            <input type="hidden" value="<?php echo $new_month_year; ?>" name="current_month_year" id="current_month_year" />
        </div>

        <div id="yearly_pay_per_post_report" style="padding:15px; <?php echo ($type == 'yearly') ? 'display:block;' : 'display:none'; ?>">
            <div class="arlinks link_align"></div>
            <?php if(!isset($_REQUEST['action'])){?>
            <div class="armchart_display_title">
                <label class="armcharttitle"><?php echo $new_year; ?></label>
            </div>
            <?php }?>
            <div id="chart3_pay_per_post_report" style="width:100%;<?php echo ($graph_type == 'countries') ? 'height:400px;' : 'height:300px;';?>" ></div>
            <input type="hidden" value="<?php echo $new_year; ?>" name="current_year" id="current_year" />
        </div>
    <?php if(isset($_REQUEST['action']) && $_REQUEST['action']=="armupdatecharts"){?>
    </div>
    <?php }?>    
    <?php
}
