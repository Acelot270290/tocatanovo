<?php

if (!class_exists('ARM_2checkout')) {

    class ARM_2checkout {

        function __construct() {
            add_action('arm_payment_gateway_validation_from_setup', array(&$this, 'arm_payment_gateway_form_submit_action'), 10, 4);
            add_action('arm_cancel_subscription_gateway_action', array(&$this, 'arm_cancel_2checkout_subscription'), 10, 2);

            //add_action('wp', array(&$this, 'arm_2checkout_ins_handle_response'), 5);
            add_filter('arm_change_pending_gateway_outside', array(&$this, 'arm_change_pending_gateway_outside'), 100, 3);


            add_action('arm_after_cancel_subscription', array(&$this, 'arm_cancel_subscription_instant'), 100, 4);
        }

        function arm_cancel_subscription_instant($user_id, $plan, $cancel_plan_action, $planData)
        {
            global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans, $arm_member_forms, $arm_payment_gateways, $arm_manage_communication;

            
            $plan_id = $plan->ID;

            if(empty($planData))
            {
                $planData = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
            }

            $payment_mode = !empty($planData['arm_payment_mode']) ? $planData['arm_payment_mode'] : '' ;

            $user_subsdata = !empty($planData['arm_2checkout']) ? $planData['arm_2checkout'] : '';
            $hashOrder = isset($user_subsdata['sale_id']) ? $user_subsdata['sale_id'] : '';

            $plan_cycle = isset($planData['arm_payment_cycle']) ? $planData['arm_payment_cycle'] : '';
            $paly_cycle_data = $plan->prepare_recurring_data($plan_cycle);

            $user_payment_gateway = !empty($planData['arm_user_gateway']) ? $planData['arm_user_gateway'] : '';

            if(!empty($hashOrder) && strtolower($user_payment_gateway) == '2checkout' && $payment_mode == "auto_debit_subscription" && $cancel_plan_action == "on_expire" && $paly_cycle_data['rec_time'] == 'infinite')
            {
                $this->arm_immediate_cancel_2checkout_payment($hashOrder, $user_id, $plan_id, $planData);
            }
        }



        function arm_immediate_cancel_2checkout_payment($hashOrder, $user_id, $plan_id, $planData)
        {
            global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans, $arm_member_forms, $arm_payment_gateways, $arm_manage_communication, $arm_subscription_cancel_msg;

            $response = "";

            try{
                $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
                $twoco_options = $all_payment_gateways['2checkout'];
                self::arm_Load2CheckoutLibrary($twoco_options);
                if (class_exists('Twocheckout_Sale')) {
                    $response = Twocheckout_Sale::stop(array('sale_id' => $hashOrder));
                    if ($response['response_code'] == "OK") {
                        $planData['sale_id'] = "";
                        update_user_meta($user_id, 'arm_user_plan_' . $plan_id, $planData);
                    }
                    else{
                        $arm_common_messages = isset($arm_global_settings->common_message) ? $arm_global_settings->common_message : array();
                        $arm_subscription_cancel_msg = isset($arm_common_messages['arm_payment_gateway_subscription_failed_error_msg']) ? $arm_common_messages['arm_payment_gateway_subscription_failed_error_msg'] : __("Membership plan couldn't cancel. Please contact the site administrator.", 'ARMember');
                    }
                }
            }
            catch(Exception $e)
            {
                $arm_common_messages = isset($arm_global_settings->common_message) ? $arm_global_settings->common_message : array();
                $arm_subscription_cancel_msg = isset($arm_common_messages['arm_payment_gateway_subscription_failed_error_msg']) ? $arm_common_messages['arm_payment_gateway_subscription_failed_error_msg'] : __("Membership plan couldn't cancel. Please contact the site administrator.", 'ARMember');
                if(!empty($e->getMessage()))
                {
                    $arm_subscription_cancel_msg = __("Error in cancel subscription from 2Checkout.", "ARMember")." ".$e->getMessage();
                }
                $ARMember->arm_write_response('Exception in Cancel Payment 2checkout => '.maybe_serialize($e->getMessage()));
            }

            return $response;
        }

        function arm_Load2CheckoutLibrary($config = array()) {
            global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans, $arm_member_forms, $arm_payment_gateways;
            if (!empty($config)) {
                if (file_exists(MEMBERSHIP_DIR . "/lib/2checkout/Twocheckout.php")) {
                    require_once (MEMBERSHIP_DIR . "/lib/2checkout/Twocheckout.php"); //Load 2Checkout lib
                    /* Set API Keys & Account */
                    Twocheckout::privateKey($config['private_key']);
                    Twocheckout::sellerId($config['sellerid']);
                    Twocheckout::username($config['username']);
                    Twocheckout::password($config['password']);
                    if ($config['payment_mode'] == 'sandbox') {
                        Twocheckout::verifySSL(false);
                        Twocheckout::sandbox(true);
                    }
                }
                $currency = $arm_payment_gateways->arm_get_global_currency();

                if (!defined('TWOCHECKOUT_SELLERID')) {
                    define("TWOCHECKOUT_SELLERID", $config['sellerid']);
                } else {
                    if (constant("TWOCHECKOUT_SELLERID") != $config['sellerid']) {
                        define("TWOCHECKOUT_SELLERID", $config['sellerid']);
                    }
                }


                if (!defined('TWOCHECKOUT_CURRENCY')) {
                    define("TWOCHECKOUT_CURRENCY", $currency);
                } else {
                    if (constant("TWOCHECKOUT_CURRENCY") != $config['$currency']) {
                        define("TWOCHECKOUT_CURRENCY", $currency);
                    }
                }
            }
        }

        function arm_payment_gateway_form_submit_action($payment_gateway, $payment_gateway_options, $posted_data, $entry_id = 0) {
            global $wpdb, $ARMember, $arm_global_settings, $arm_payment_gateways, $arm_membership_setup, $payment_done, $arm_subscription_plans;

            if ($payment_gateway == '2checkout') {
                $entry_data = $arm_payment_gateways->arm_get_entry_data_by_id($entry_id);
                if (!empty($entry_data)) {

                    
                    $posted_data['entry_email'] = $entry_data['arm_entry_email'];
                    $posted_data['entry_id'] = $entry_id;
                    $user_id = $entry_data['arm_user_id'];
                    $setup_id = $posted_data['setup_id'];
                    $entry_values = maybe_unserialize($entry_data['arm_entry_value']);
                    $payment_cycle = $entry_values['arm_selected_payment_cycle'];
                    $posted_data['tax_percentage'] = $tax_percentage = isset($entry_values['tax_percentage']) ? $entry_values['tax_percentage'] : 0;
                    $plan_id = (!empty($posted_data['subscription_plan'])) ? $posted_data['subscription_plan'] : 0;
                    if ($plan_id == 0) {
                        $plan_id = (!empty($posted_data['_subscription_plan'])) ? $posted_data['_subscription_plan'] : 0;
                    }
                    $plan = new ARM_Plan($plan_id);


                    
                    if ($plan->is_recurring()) {
                        $payment_mode = !empty($posted_data['arm_selected_payment_mode']) ? $posted_data['arm_selected_payment_mode'] : 'manual_subscription';
                    } else {
                        $payment_mode = '';
                    }

                    $plan_action = 'new_subscription';
                    
                    $oldPlanIdArray = (isset($posted_data['old_plan_id']) && !empty($posted_data['old_plan_id'])) ? explode(",", $posted_data['old_plan_id']) : 0;
                    if (!empty($oldPlanIdArray)) {
                        if (in_array($plan_id, $oldPlanIdArray)) {
                            $plan_action = 'renew_subscription';
                            $is_recurring_payment = $arm_subscription_plans->arm_is_recurring_payment_of_user($user_id, $plan_id, $payment_mode);
                            if ($is_recurring_payment) {
                                $planData = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                                $oldPlanDetail = $planData['arm_current_plan_detail'];
                                if (!empty($oldPlanDetail)) {
                                    $plan = new ARM_Plan(0);
                                    $plan->init((object) $oldPlanDetail);
                                }
                            }
                        } else {
                            $plan_action = 'change_subscription';
                        }
                    }
                    

                    $arm_payment_type = $plan->payment_type;
                    
                    if ($plan->is_recurring()) {
                        if ($payment_mode == 'auto_debit_subscription') {
                            if (!($plan->is_support_2checkout($payment_cycle, $plan_action))) {
                                $err_msg = __('Payment through 2Checkout is not supported for selected plan.', 'ARMember');
                                return $payment_done = array('status' => FALSE, 'error' => $err_msg);
                            }
                        }
                    }
                   
                    $charge_form = self::arm_generate_2checkout_form($posted_data, $user_id, $payment_cycle, $setup_id);
                   
                    if (is_array($charge_form)) {
                        $payment_done = $charge_form;
                        return $payment_done;
                    } else if (isset($posted_data['action']) && in_array($posted_data['action'], array('arm_shortcode_form_ajax_action', 'arm_membership_setup_form_ajax_action'))) {
                        $return = array('status' => 'success', 'type' => 'redirect', 'message' => $charge_form);
                        echo json_encode($return);

                        
                        exit;
                    } else {
                        echo $charge_form;
                        exit;
                    }
                    exit;
                }
            }
        }

        function arm_generate_2checkout_form($request_data = array(), $user_id = 0, $payment_cycle = 0, $setup_id = 0) {
            global $wpdb, $ARMember, $arm_global_settings, $arm_payment_gateways, $arm_membership_setup, $arm_manage_coupons, $arm_transaction, $is_free_manual, $arm_subscription_plans;
            $is_free_manual = false;
            $charge_form = $additionalVars = '';
            $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
            $twoco_options = (isset($all_payment_gateways['2checkout'])) ? $all_payment_gateways['2checkout'] : array();
            if (!empty($request_data) && !empty($twoco_options)) {
                $currency = $arm_payment_gateways->arm_get_global_currency();
                $returnURL = $arm_global_settings->add_query_arg("action", "arm_2checkout_api", ARM_HOME_URL . "/");
                $entry_id = $request_data['entry_id'];
                $entry_email = $request_data['entry_email'];
                $form_slug = isset($request_data['arm_action']) ? $request_data['arm_action'] : '';
                $arm_is_trial = '0';
                $form = new ARM_Form('slug', $form_slug);
                $plan_id = (!empty($request_data['subscription_plan'])) ? $request_data['subscription_plan'] : 0;
                $tax_percentage = isset($request_data['tax_percentage']) ? $request_data['tax_percentage'] : 0;
                if ($plan_id == 0) {
                    $plan_id = (!empty($request_data['_subscription_plan'])) ? $request_data['_subscription_plan'] : 0;
                }
                $plan = new ARM_Plan($plan_id);


                if ($plan->is_recurring()) {
                    $payment_mode = !empty($request_data['arm_selected_payment_mode']) ? $request_data['arm_selected_payment_mode'] : 'manual_subscription';
                } else {
                    $payment_mode = '';
                }

                $plan_action = 'new_subscription';

                $arm_user_old_plan = (isset($request_data['old_plan_id']) && !empty($request_data['old_plan_id'])) ? explode(",", $request_data['old_plan_id']) : array();
                if (!empty($arm_user_old_plan)) {
                    if (in_array($plan_id, $arm_user_old_plan)) {
                        $plan_action = 'renew_subscription';
                        $is_recurring_payment = $arm_subscription_plans->arm_is_recurring_payment_of_user($user_id, $plan_id, $payment_mode);
                        if ($is_recurring_payment) {
                            $planData = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                            $oldPlanDetail = $planData['arm_current_plan_detail'];
                            if (!empty($oldPlanDetail)) {
                                $plan = new ARM_Plan(0);
                                $plan->init((object) $oldPlanDetail);
                            }
                        }
                    } else {
                        $plan_action = 'change_subscription';
                    }
                }

                if ($plan->is_recurring()) {
                    $plan_data = $plan->prepare_recurring_data($payment_cycle);
                    $amount = abs(str_replace(',', '', $plan_data['amount']));
                } else {
                    $amount = abs(str_replace(',', '', $plan->amount));
                }


                $tax_amount = 0;

                if ($currency == 'JPY') {
                    $amount = number_format((float) $amount, 0, '', '');
                } else {
                    $amount = number_format((float) $amount, 2, '.', '');
                }


                $custom_var = $entry_id . '|' . $plan->payment_type;

                $couponCode = isset($request_data['arm_coupon_code']) ? $request_data['arm_coupon_code'] : '';

                $discount_amt = $coupon_amount = $arm_coupon_discount = 0;
                $arm_coupon_discount_type = $coupon_code = '';
                $arm_coupon_on_each_subscriptions = 0;
                /* Coupon Details */
                if ($arm_manage_coupons->isCouponFeature && !empty($couponCode)) {
                    $couponApply = $arm_manage_coupons->arm_apply_coupon_code($couponCode, $plan, $setup_id, $payment_cycle, $arm_user_old_plan);
                    $coupon_amount = isset($couponApply['coupon_amt']) ? $couponApply['coupon_amt'] : 0;
                    $discount_amt = isset($couponApply['total_amt']) ? $couponApply['total_amt'] : $amount;
                    $arm_coupon_discount = (isset($couponApply['discount']) && !empty($couponApply['discount'])) ? $couponApply['discount'] : 0;
                    $global_currency = $arm_payment_gateways->arm_get_global_currency();
                    $arm_coupon_discount_type = ($couponApply['discount_type'] != 'percentage') ? $global_currency : "%";
                    $arm_coupon_on_each_subscriptions = isset($couponApply['arm_coupon_on_each_subscriptions']) ? $couponApply['arm_coupon_on_each_subscriptions'] : '0';
                    $coupon_code = $couponCode;
                }

                if ($plan->is_recurring() && $payment_mode == 'auto_debit_subscription') {
                    $recurring_data = $plan->prepare_recurring_data($payment_cycle);
                    //Recurring Options
                    $recur_cycles = $recurring_data['cycles'];
                    $recur_cycles = (!empty($recur_cycles) && $recur_cycles != 'infinite') ? $recur_cycles : 'infinite';
                    $recur_interval = $recurring_data['interval'];
                    $recurring_type = (!empty($recurring_data['period'])) ? $recurring_data['period'] : 'Day';
                    if ($recurring_type == "D" || $recurring_type == 'Day') {
                        $recurring_type = "Day";
                    } else if ($recurring_type == "W") {
                        $recurring_type = "Week";
                    } else if ($recurring_type == "M") {
                        $recurring_type = "Month";
                    } else if ($recurring_type == "Y") {
                        $recurring_type = "Year";
                    }
                    $isTrial = false;
                    //Trial Period Options
                    $trial_amount = 0;
                    if ($plan_action == 'new_subscription') {
                        if (!empty($recurring_data['trial'])) {
                            $trial_amount = $recurring_data['trial']['amount'];
                            $trial_period = $recurring_data['trial']['period'];
                            $trial_interval = $recurring_data['trial']['interval'];
                            $isTrial = true;
                            $arm_is_trial = '1';
                            $extraParam['trial'] = array(
                                'amount' => $trial_amount,
                                'period' => $trial_period,
                                'interval' => $trial_interval,
                            );
                            /* Increase Billing Cycle */
                            $recur_cycles = ($recur_cycles == 'infinite') ? $recur_cycles : $recur_cycles + 1;
                        }
                    }
                    //Apply Coupon Amount
                    if (!empty($coupon_amount) && $coupon_amount > 0) {
                        $trial_amount = $discount_amt;
                        $isTrial = true;
                    }
                    $startup_fee = 0;
                    if ($isTrial) {


                        if($tax_percentage > 0){
                            $tax_amount = ($trial_amount * $tax_percentage)/100;
                            $tax_amount = number_format((float)$tax_amount, 2, '.', '');
                            $trial_amount = $trial_amount + $tax_amount;
                        
                            $tax_amount1 = ($amount * $tax_percentage)/100;
                            $tax_amount1 = number_format((float)$tax_amount1, 2, '.', '');
                            $amount  = $amount + $tax_amount1;
                        }

                        $startup_fee = ($trial_amount < $amount) ? $trial_amount - $amount : -$amount;

                        

                        $additionalVars .= '<input type="hidden" name="li_0_startup_fee" value="' . $startup_fee . '" />';
                    }
                    $recurrence = $recur_interval . ' ' . $recurring_type;
                    $duration = ($recur_cycles == 'infinite') ? 'Forever' : ($recur_cycles * $recur_interval) . ' ' . $recurring_type;
                    $additionalVars .= '<input type="hidden" name="li_0_recurrence" value="' . $recurrence . '" />';
                    $additionalVars .= '<input type="hidden" name="li_0_duration" value="' . $duration . '" />';



                } else if ($plan->is_recurring() && $payment_mode == 'manual_subscription') {
                    $allow_trial = true;
                    if (is_user_logged_in()) {
                        $user_id = get_current_user_id();
                        $user_plans = get_user_meta($user_id, 'arm_user_plan_ids', true);

                        if (!empty($user_plans)) {
                            $allow_trial = false;
                        }
                    }

                    if ($plan->has_trial_period() && $allow_trial) {
                        $trial_amount = $plan->options['trial']['amount'];
                        $trial_period = $plan->options['trial']['period'];
                        $trial_interval = $plan->options['trial']['interval'];
                        $isTrial = true;
                        $arm_is_trial = '1';
                        $extraParam['trial'] = array(
                            'amount' => $trial_amount,
                            'period' => $trial_period,
                            'interval' => $trial_interval,
                        );
                        if (!empty($coupon_amount) && $coupon_amount > 0) {
                            $trial_amount = $discount_amt;
                            $isTrial = true;
                        }
                        $amount = abs(str_replace(',', '', $trial_amount));
                    }
                    if (!empty($coupon_amount) && $coupon_amount > 0) {
                        $amount = abs(str_replace(',', '', $discount_amt));
                    }

                    if($tax_percentage > 0){
                        $tax_amount = ($amount * $tax_percentage)/100;
                        $tax_amount = number_format((float)$tax_amount, 2, '.', '');
                        $amount  = $amount + $tax_amount;
                    }



                    if ($currency == 'JPY') {
                        $amount = number_format((float) $amount, 0, '', '');
                    } else {
                        $amount = number_format((float) $amount, 2, '.', '');
                    }



                    if ($amount == 0 || $amount == '0.00') {
                        $return_array = array();
                        if (is_user_logged_in()) {
                            $current_user_id = get_current_user_id();
                            $return_array['arm_user_id'] = $current_user_id;
                            $arm_user_info = get_userdata($current_user_id);
                            $return_array['arm_first_name']=$arm_user_info->first_name;
                            $return_array['arm_last_name']=$arm_user_info->last_name;
                        }else{
                            $return_array['arm_first_name']=(isset($request_data['first_name']))?$request_data['first_name']:'';
                            $return_array['arm_last_name']=(isset($request_data['last_name']))?$request_data['last_name']:'';
                        }
                        $return_array['arm_plan_id'] = $plan->ID;
                        $return_array['arm_payment_gateway'] = '2checkout';
                        $return_array['arm_payment_type'] = $plan->payment_type;
                        $return_array['arm_token'] = '-';
                        $return_array['arm_payer_email'] = $entry_email;
                        $return_array['arm_receiver_email'] = '';
                        $return_array['arm_transaction_id'] = '-';
                        $return_array['arm_transaction_payment_type'] = $plan->payment_type;
                        $return_array['arm_transaction_status'] = 'completed';
                        $return_array['arm_payment_mode'] = 'manual_subscription';
                        $return_array['arm_payment_date'] = date('Y-m-d H:i:s');
                        $return_array['arm_amount'] = 0;
                        $return_array['arm_currency'] = $currency;
                        $return_array['arm_coupon_code'] = @$coupon_code;
                        $return_array['arm_coupon_discount'] = @$arm_coupon_discount;
                        $return_array['arm_coupon_discount_type'] = @$arm_coupon_discount_type;
                        $return_array['arm_response_text'] = '';
                        $return_array['arm_extra_vars'] = '';
                        $return_array['arm_is_trial'] = $arm_is_trial;
                        $return_array['arm_created_date'] = current_time('mysql');
                        $return_array['arm_coupon_on_each_subscriptions'] = $arm_coupon_on_each_subscriptions;
                        $payment_log_id = $arm_payment_gateways->arm_save_payment_log($return_array);
                        $is_free_manual = true;
                        do_action('arm_after_twocheckout_free_manual_payment', $plan, $payment_log_id, $arm_is_trial, @$coupon_code, $extraParam);
                        return array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);
                    }
                } else {
                    if (!empty($coupon_amount) && $coupon_amount > 0) {
                        $amount = $discount_amt;
                    }
                    $amount = abs(str_replace(',', '', $amount));

                    if($tax_percentage > 0){
                        $tax_amount = ($amount * $tax_percentage)/100;
                        $tax_amount = number_format((float)$tax_amount, 2, '.', '');
                        $amount = $amount + $tax_amount;
                    }

                    if ($currency == 'JPY') {
                        $amount = number_format((float) $amount, 0, '', '');
                    } else {
                        $amount = number_format((float) $amount, 2, '.', '');
                    }


                    if ($amount == 0 || $amount == '0.00') {
                        $return_array = array();
                        if (is_user_logged_in()) {
                            $current_user_id = get_current_user_id();
                            $return_array['arm_user_id'] = $current_user_id;
                            $arm_user_info = get_userdata($current_user_id);
                            $return_array['arm_first_name']=$arm_user_info->first_name;
                            $return_array['arm_last_name']=$arm_user_info->last_name;
                        }else{
                            $return_array['arm_first_name']=(isset($request_data['first_name']))?$request_data['first_name']:'';
                            $return_array['arm_last_name']=(isset($request_data['last_name']))?$request_data['last_name']:'';
                        }
                        
                        $return_array['arm_plan_id'] = $plan->ID;
                        $return_array['arm_payment_gateway'] = '2checkout';
                        $return_array['arm_payment_type'] = $plan->payment_type;
                        $return_array['arm_token'] = '-';
                        $return_array['arm_payer_email'] = $entry_email;
                        $return_array['arm_receiver_email'] = '';
                        $return_array['arm_transaction_id'] = '-';
                        $return_array['arm_transaction_payment_type'] = $plan->payment_type;
                        $return_array['arm_transaction_status'] = 'completed';
                        $return_array['arm_payment_mode'] = '';
                        $return_array['arm_payment_date'] = date('Y-m-d H:i:s');
                        $return_array['arm_amount'] = 0;
                        $return_array['arm_currency'] = $currency;
                        $return_array['arm_coupon_code'] = @$coupon_code;
                        $return_array['arm_coupon_discount'] = @$arm_coupon_discount;
                        $return_array['arm_coupon_discount_type'] = @$arm_coupon_discount_type;
                        $return_array['arm_response_text'] = '';
                        $return_array['arm_extra_vars'] = '';
                        $return_array['arm_is_trial'] = $arm_is_trial;
                        $return_array['arm_created_date'] = current_time('mysql');
                        $return_array['arm_coupon_on_each_subscriptions'] = $arm_coupon_on_each_subscriptions;
                        $payment_log_id = $arm_payment_gateways->arm_save_payment_log($return_array);
                        $is_free_manual = true;
                        do_action('arm_after_twocheckout_free_payment', $plan, $payment_log_id, $arm_is_trial, @$coupon_code, $extraParam);
                        return array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);
                    }
                }
                $arm_2checkout_demo = "";
                if ($twoco_options['payment_mode'] == 'sandbox') {
                    $arm_2checkout_demo = 'Y';
                }
                $reqUrl = 'https://www.2checkout.com/checkout/purchase';
                $arm_2checkout_language = isset($twoco_options['language']) ? $twoco_options['language'] : 'en_US';
                $charge_form .= '<form id="arm_2checkout_form" name="2Checkout" action="' . $reqUrl . '" method="post">';
                $charge_form .= '<input type="hidden" name="sid" value="' . $twoco_options['sellerid'] . '" />';
                $charge_form .= '<input type="hidden" name="mode" value="2CO" />';
                $charge_form .= '<input type="hidden" name="merchant_order_id" value="' . $entry_id . '" />';
                $charge_form .= '<input type="hidden" name="li_0_type" value="product" />';
                $charge_form .= '<input type="hidden" name="li_0_product_id" value="' . $plan->ID . '" />';
                $charge_form .= '<input type="hidden" name="li_0_name" value="' . $plan->name . '" />';
                $charge_form .= '<input type="hidden" name="li_0_description" value="-" />';
                $charge_form .= '<input type="hidden" name="li_0_quantity" value="1" />';
              
                $charge_form .= '<input type="hidden" name="li_0_price" value="' . $amount . '" />';

                $charge_form .= $additionalVars;
                $charge_form .= '<input type="hidden" name="li_0_tangible" value="N" />';
                    $charge_form .= '<input type="hidden" name="li_0_option_0_name" value="custom" />';
                    $charge_form .= '<input type="hidden" name="li_0_option_0_value" value="' . $custom_var . '" />';
                    $charge_form .= '<input type="hidden" name="li_0_option_1_name" value="tax_percentage" />';
                    $charge_form .= '<input type="hidden" name="li_0_option_1_value" value="' . $tax_percentage . '" />';
                    $charge_form .= '<input type="hidden" name="li_0_option_0_surcharge" value="0.00" />';
                $charge_form .= '<input type="hidden" name="currency_code" value="' . $currency . '" />';
                $charge_form .= '<input type="hidden" name="email" value="' . $entry_email . '" />';
                $charge_form .= '<input type="hidden" name="lang" value="' . $arm_2checkout_language . '" />';
                $charge_form .= '<input type="hidden" name="x_receipt_link_url" value="' . $returnURL . '" />';
                if(!empty($arm_2checkout_demo))
                {
                    $charge_form .= '<input type="hidden" name="demo" value="Y" />';
                }

                $charge_form .= '<input type="submit" value="Checkout" style="display:none;"/>';
                $charge_form .= '<script data-cfasync="false" type="text/javascript">document.getElementById("arm_2checkout_form").submit();</script>';
                $charge_form .= '</form>';
            }
            return $charge_form;
        }

        function arm_cancel_2checkout_subscription($user_id, $plan_id) {
            global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans, $arm_member_forms, $arm_payment_gateways, $arm_manage_communication, $arm_subscription_cancel_msg;
            if (!empty($user_id) && $user_id != 0 && !empty($plan_id) && $plan_id != 0) {


                $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
                if (isset($all_payment_gateways['2checkout']) && !empty($all_payment_gateways['2checkout'])) {
                    $twoco_options = $all_payment_gateways['2checkout'];

                    $planData = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);

                    $user_payment_gateway = $planData['arm_user_gateway'];
                    if (strtolower($user_payment_gateway) == '2checkout') {

                        
                        $user_subsdata = $planData['arm_2checkout'];
                        $hashOrder = isset($user_subsdata['sale_id']) ? $user_subsdata['sale_id'] : '';
                        $payment_mode = $planData['arm_payment_mode'];
                        
                       

                        $planDetail = $planData['arm_current_plan_detail'];

                        if (!empty($planDetail)) {
                            $plan = new ARM_Plan(0);
                            $plan->init((object) $planDetail);
                        } else {
                            $plan = new ARM_Plan($plan_id);
                        }    


                        $arm_payment_cycle = $planData['arm_payment_cycle'];
                        $recurring_data = $plan->prepare_recurring_data($arm_payment_cycle);
                        $amount = $recurring_data['amount'];


                        if (!empty($hashOrder)) {
                          
                            $user_detail = get_userdata($user_id);
                            $payer_email = $user_detail->user_email;

                            

                            if ($payment_mode == 'auto_debit_subscription') {                               
                                $response = $this->arm_immediate_cancel_2checkout_payment($hashOrder, $user_id, $plan_id, $planData);

                                if(!empty($arm_subscription_cancel_msg))
                                {
                                    return;
                                }


                                if ($response['response_code'] == "OK" || empty($hashOrder)) {

                                    $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $plan_id, 'user_id' => $user_id, 'action' => 'on_cancel_subscription'));

                                    /**
                                     * Prepare Payment Log
                                     */
                                    $planObj = new ARM_Plan($plan_id);
                                    $payment_data = array(
                                        'arm_user_id' => $user_id,
                                        'arm_first_name' => $user_detail->first_name,
                                        'arm_last_name' => $user_detail->last_name,
                                        'arm_plan_id' => $plan_id,
                                        'arm_payment_gateway' => '2checkout',
                                        'arm_payment_type' => 'subscription',
                                        'arm_token' => $hashOrder,
                                        'arm_payer_email' => $payer_email,
                                        'arm_receiver_email' => '',
                                        'arm_transaction_id' => $hashOrder,
                                        'arm_transaction_payment_type' => 'subscription',
                                        'arm_payment_mode' => $payment_mode,
                                        'arm_transaction_status' => 'canceled',
                                        'arm_payment_date' => current_time('mysql'),
                                        'arm_amount' => $amount,
                                        
                                        'arm_coupon_code' => '',
                                        'arm_response_text' => utf8_encode(maybe_serialize($response)),
                                        'arm_is_trial' => '0',
                                        'arm_created_date' => current_time('mysql')
                                    );
                                    $payment_log_id = $arm_payment_gateways->arm_save_payment_log($payment_data);
                                }
                            } else {

                                $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $plan_id, 'user_id' => $user_id, 'action' => 'on_cancel_subscription'));
                                $planObj = new ARM_Plan($plan_id);
                                $payment_data = array(
                                    'arm_user_id' => $user_id,
                                    'arm_first_name' => $user_detail->first_name,
                                    'arm_last_name' => $user_detail->last_name,
                                    'arm_plan_id' => $plan_id,
                                    'arm_payment_gateway' => '2checkout',
                                    'arm_payment_type' => 'subscription',
                                    'arm_token' => $hashOrder,
                                    'arm_payer_email' => $payer_email,
                                    'arm_receiver_email' => '',
                                    'arm_transaction_id' => $hashOrder,
                                    'arm_transaction_payment_type' => 'subscription',
                                    'arm_payment_mode' => $payment_mode,
                                    'arm_transaction_status' => 'canceled',
                                    'arm_payment_date' => current_time('mysql'),
                                    'arm_amount' => $amount,
                                   
                                    'arm_coupon_code' => '',
                                    'arm_response_text' => '',
                                    'arm_is_trial' => '0',
                                    'arm_created_date' => current_time('mysql')
                                );
                                $payment_log_id = $arm_payment_gateways->arm_save_payment_log($payment_data);
                                return;
                            }
                        }//End `(!empty($subscr_id) && strtolower($user_payment_gateway)=='2checkout')`
                    }
                }
            }/* End `(!empty($user_id) && $user_id != 0 && !empty($plan_id) && $plan_id != 0)` */
        }

        function arm_2checkout_ins_handle_response() {
            global $wpdb, $ARMember, $arm_global_settings, $arm_payment_gateways, $arm_subscription_plans, $arm_membership_setup, $arm_member_forms, $arm_manage_communication, $arm_manage_coupons, $arm_members_class;
            /**
             * Need to set Instant Notification Service (INS) URL like this (ie. http://sitename.com/?action=arm_2checkout_api)
             */

            if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('arm_2checkout_api', 'arm_2checkout_notify')) || isset($_REQUEST['arm-listener']) && in_array($_REQUEST['arm-listener'], array('arm_2checkout_api', 'arm_2checkout_notify'))) {
                $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
                if (isset($all_payment_gateways['2checkout']) && !empty($all_payment_gateways['2checkout'])) {
                    $insMsg = array();
                    $extraVars = array();
                    foreach ($_POST as $k => $v) {
                        $insMsg[$k] = $v;
                    }
                    # Validate the Hash



                    $twoco_options = $all_payment_gateways['2checkout'];
                    $arm_payment_mode = $twoco_options['payment_mode'];
                    $hashSecretWord = $twoco_options['secret_word'];
                    $hashSid = $twoco_options['sellerid'];
                    $hashInvoice = !empty($insMsg['invoice_id']) ? $insMsg['invoice_id'] : '';
                    $arm_is_trial = '0';
                    if (isset($insMsg['message_type'])) {
                        /**
                         * For INS Notifications
                         */
                        if (isset($insMsg['md5_hash'])) {
                            $hashSid = $insMsg['vendor_id'];
                            $hashOrder = $insMsg['sale_id'];
                            $StringToHash = strtoupper(md5($hashOrder . $hashSid . $hashInvoice . $hashSecretWord));
                            if ($StringToHash == $insMsg['md5_hash']) {
                                $payLog = $wpdb->get_row("SELECT `arm_log_id`, `arm_user_id`, `arm_plan_id`, `arm_payment_type`,arm_first_name,arm_last_name FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_token`='$hashOrder' AND `arm_payment_gateway`='2checkout' ORDER BY `arm_log_id` DESC");
                                $user_id = $payLog->arm_user_id;
                                $plan_ids = get_user_meta($user_id, 'arm_user_plan_ids', true);
                                $plan_ids = !empty($plan_ids) ? $plan_ids : array();
                                $plan_id = $payLog->arm_plan_id;
                                $extraVars = $payLog->arm_extra_vars;
                                $tax_percentage = $tax_amount = 0;
                                if(isset($extraVars) && !empty($extraVars)){
                                    $unserialized_extravars = maybe_unserialize($extraVars);
                                    $tax_percentage = (isset($unserialized_extravars['tax_percentage']) && $unserialized_extravars['tax_percentage'] != '' )? $unserialized_extravars['tax_percentage'] : 0;
                                }


                                $planData = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                                $oldPlanDetail = $planData['arm_current_plan_detail'];
                                $payment_cycle = $planData['arm_payment_cycle'];
                                if (!empty($oldPlanDetail)) {
                                    $plan = new ARM_Plan(0);
                                    $plan->init((object) $oldPlanDetail);
                                    $plan_data = $plan->prepare_recurring_data($payment_cycle);
                                    $plan_amount = $plan_data['amount'];

                                    if($tax_percentage > 0 && $plan_amount != ''){
                                        $tax_amount = ($tax_percentage*$plan_amount)/100;
                                        $tax_amount = number_format((float)$tax_amount , 2, '.', '');
                                    }
                                }
                                else{
                                    $plan = new ARM_Plan($plan_id);
                                    $recurring_data = $plan->prepare_recurring_data($payment_cycle);
                                    $plan_amount = $recurring_data['amount']; 
                                    if($tax_percentage > 0 && $plan_amount != ''){
                                        $tax_amount = ($tax_percentage*$plan_amount)/100;
                                        $tax_amount = number_format((float)$tax_amount , 2, '.', '');
                                    }
                                }


                                $user_subsdata = $planData['arm_2checkout'];
                                $payment_mode = $planData['arm_payment_mode'];
                                $user_sale_id = $user_subsdata['sale_id'];
                                $planObj = new ARM_Plan($plan_id);

                                if ($user_sale_id == $hashOrder && in_array($payLog->arm_plan_id, $plan_ids)) {
                                    $is_log = false;
                                    $amount = 0;
                                    $extraVars = array(
                                        'subs_id' => $hashOrder,
                                        'trans_id' => $hashInvoice,
                                        'error' => $insMsg['message_description'],
                                        'date' => $insMsg['timestamp'],
                                        'message_type' => $insMsg['message_type'],
                                    );

                                    $extraVars['tax_percentage']=$tax_percentage;
                                    $extraVars['tax_amount'] = $tax_amount;
                                    $extraVars['plan_amount'] = $plan_amount;
                                   
                                    switch (strtoupper($insMsg['message_type'])) {
                                        case 'ORDER_CREATED':
                                            $insMsg['payment_status'] = 'success';
                                            //$is_log = true;
                                            break;
                                        case 'FRAUD_STATUS_CHANGED':
                                            if (isset($insMsg['fraud_status']) && $insMsg['fraud_status'] == 'fail') {
                                                $arm_subscription_plans->arm_user_plan_status_action(array('plan_id' => $payLog->arm_plan_id, 'user_id' => $user_id, 'action' => 'failed_payment'));
                                                $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $payLog->arm_plan_id, 'user_id' => $user_id, 'action' => 'failed_payment'));
                                                $is_log = true;
                                            }
                                            break;
                                        case 'RECURRING_INSTALLMENT_SUCCESS':
                                            $is_log = true;

                                            $arm_next_due_payment_date = $planData['arm_next_due_payment'];
                                            if (!empty($arm_next_due_payment_date)) {
                                                if (strtotime(current_time('mysql')) >= $arm_next_due_payment_date) {
                                                    $total_completed_recurrence = $planData['arm_completed_recurring'];
                                                    $total_completed_recurrence++;
                                                    $planData['arm_completed_recurring'] = $total_completed_recurrence;
                                                    update_user_meta($user_id, 'arm_user_plan_' . $plan_id, $planData);
                                                    $payment_cycle = $planData['arm_payment_cycle'];
                                                    $arm_next_payment_date = $arm_members_class->arm_get_next_due_date($user_id, $plan_id, false, $payment_cycle);
                                                    $planData['arm_next_due_payment'] = $arm_next_payment_date;
                                                    update_user_meta($user_id, 'arm_user_plan_' . $plan_id, $planData);
                                                }
                                                else{
                                                    $now = current_time('mysql');
                                                    $arm_last_payment_status = $wpdb->get_var($wpdb->prepare("SELECT `arm_transaction_status` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`=%d AND `arm_plan_id`=%d AND `arm_created_date`<=%s ORDER BY `arm_log_id` DESC LIMIT 0,1", $user_id, $plan_id, $now));
                                                    if($arm_last_payment_status == 'success'){
                                                        $total_completed_recurrence = $planData['arm_completed_recurring'];
                                                        $total_completed_recurrence++;
                                                        $planData['arm_completed_recurring'] = $total_completed_recurrence;
                                                        update_user_meta($user_id, 'arm_user_plan_' . $plan_id, $planData);
                                                        $payment_cycle = $planData['arm_payment_cycle'];
                                                        $arm_next_payment_date = $arm_members_class->arm_get_next_due_date($user_id, $plan_id, false, $payment_cycle);
                                                        $planData['arm_next_due_payment'] = $arm_next_payment_date;
                                                        update_user_meta($user_id, 'arm_user_plan_' . $plan_id, $planData);
                                                    }
                                                }
                                            }

                                            $suspended_plan_ids = get_user_meta($user_id, 'arm_user_suspended_plan_ids', true);
                                            $suspended_plan_id = (isset($suspended_plan_ids) && !empty($suspended_plan_ids)) ? $suspended_plan_ids : array();

                                            if (in_array($plan_id, $suspended_plan_id)) {
                                                unset($suspended_plan_id[array_search($plan_id, $suspended_plan_id)]);
                                                update_user_meta($user_id, 'arm_user_suspended_plan_ids', array_values($suspended_plan_id));
                                            }

                                            $insMsg['payment_status'] = 'success';
                                            $amount = $insMsg['item_rec_list_amount_1'];
                                            //do_action('arm_after_recurring_payment_success_outside', $user_id, $plan_id, '2checkout', $payment_mode, $user_subsdata);
                                            break;
                                        case 'INVOICE_STATUS_CHANGED':
                                            //do_action('arm_after_recurring_payment_success_outside', $user_id, $plan_id, '2checkout', $payment_mode, $user_subsdata);
                                            break;
                                        case 'RECURRING_INSTALLMENT_FAILED':
                                            $is_log = true;
                                            $insMsg['payment_status'] = 'failed';
                                            $amount = $insMsg['item_rec_list_amount_1'];
                                            $arm_subscription_plans->arm_user_plan_status_action(array('plan_id' => $payLog->arm_plan_id, 'user_id' => $user_id, 'action' => 'failed_payment'));
                                            $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $payLog->arm_plan_id, 'user_id' => $user_id, 'action' => 'failed_payment'));
                                            do_action('arm_after_recurring_payment_failed_outside', $user_id, $plan_id, '2checkout', $payment_mode, $user_subsdata);
                                            break;
                                        case 'RECURRING_STOPPED':
                                            $is_log = true;
                                            $insMsg['payment_status'] = 'cancelled';
                                            $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $payLog->arm_plan_id, 'user_id' => $user_id, 'action' => 'on_cancel_subscription'));
                                            $entry_plan = $payLog->arm_plan_id;
                                            $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                                            $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                            $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                            $planData = shortcode_atts($defaultPlanData, $userPlanDatameta);

                                            $arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'cancel_subscription');
                                            do_action('arm_cancel_subscription', $user_id, $entry_plan);
                                            $arm_subscription_plans->arm_clear_user_plan_detail($user_id, $entry_plan);

                                            $cancel_plan_act = isset($plan->options['cancel_action']) ? $plan->options['cancel_action'] : 'block';
                                            if ($arm_subscription_plans->isPlanExist($cancel_plan_act)) {
                                                $arm_members_class->arm_new_plan_assigned_by_system($cancel_plan_act, $entry_plan, $user_id);
                                            } else {
                                            }

                                            do_action('arm_after_recurring_payment_cancelled_outside', $user_id, $plan_id, '2checkout', $payment_mode, $user_subsdata);
                                            break;
                                        case 'RECURRING_COMPLETE':
                                            $is_log = true;
                                            $insMsg['payment_status'] = 'expired';
                                            $arm_subscription_plans->arm_user_plan_status_action(array('plan_id' => $payLog->arm_plan_id, 'user_id' => $user_id, 'action' => 'eot'));
                                            do_action('arm_after_recurring_payment_completed_outside', $user_id, $plan_id, '2checkout', $payment_mode, $user_subsdata);
                                            break;
                                        default:
                                            do_action('arm_handle_twocheckout_unknown_error_from_outside', $user_id, $plan_id, $insMsg['message_type']);
                                            return;
                                            break;
                                    }


                                    if ($is_log && !empty($user_id)) {
                                         $extraVars['paid_amount'] = $amount;
                                        $payment_data = array(
                                            'arm_user_id' => $user_id,
                                            'arm_first_name'=> $paylog->arm_first_name,
                                            'arm_last_name'=> $paylog->arm_last_name,
                                            'arm_plan_id' => $payLog->arm_plan_id,
                                            'arm_payment_gateway' => '2checkout',
                                            'arm_payment_type' => $payLog->arm_payment_type,
                                            'arm_token' => $insMsg['sale_id'],
                                            'arm_payer_email' => $insMsg['customer_email'],
                                            'arm_receiver_email' => '',
                                            'arm_transaction_id' => $hashInvoice,
                                            'arm_transaction_payment_type' => $insMsg['message_type'],
                                            'arm_transaction_status' => $insMsg['payment_status'],
                                            'arm_payment_mode' => $payment_mode,
                                            'arm_payment_date' => $insMsg['sale_date_placed'],
                                            'arm_amount' => $amount,
                                            'arm_currency' => $insMsg['list_currency'],
                                            'arm_coupon_code' => '',
                                            'arm_response_text' => utf8_encode(maybe_serialize($insMsg)),
                                            'arm_extra_vars' => maybe_serialize($extraVars),
                                            'arm_is_trial' => $arm_is_trial,
                                            'arm_created_date' => current_time('mysql')
                                        );


                                        $arm_payment_log = $arm_payment_gateways->arm_save_payment_log($payment_data);
                                    } /* End `($is_log && !empty($user_id) && $user_id != 0)` */
                                } /* End `($user_sale_id == $hashOrder)` */
                            }
                        }
                    } else if (isset($_POST['key'])) {
                        /**
                         * For Return Callback From 2Checkout Site
                         */
                        $pgateway = "";
                        global $is_multiple_membership_feature;
                        $hashTotal = $insMsg['total'];
                        $hashOrder = $insMsg['order_number'];

                        if($arm_payment_mode == "sandbox")
                        {
                            $tmphashOrder = 1;
                            $StringToHash = strtoupper(md5($hashSecretWord . $hashSid . $tmphashOrder . $hashTotal));    
                        }
                        else
                        {
                            $StringToHash = strtoupper(md5($hashSecretWord . $hashSid . $hashOrder . $hashTotal));
                        }
                        if ($StringToHash == $insMsg['key']) {
                            $customs = explode('|', $insMsg['li_0_option_0_value']);
                            $tax_percentage = isset($insMsg['li_0_option_1_value']) ? $insMsg['li_0_option_1_value'] : 0 ;
                            $entry_id = $customs[0];
                            $arm_payment_type = $customs[1];
                            $entry_data = $wpdb->get_row("SELECT `arm_entry_id`, `arm_entry_email`, `arm_entry_value`, `arm_form_id`, `arm_user_id`, `arm_plan_id` FROM `" . $ARMember->tbl_arm_entries . "` WHERE `arm_entry_id`='" . $entry_id . "'", ARRAY_A);
                            if (!empty($entry_data)) {
                                $extraVars['paid_amount'] = $insMsg['total'];
                                $user_id = $entry_data['arm_user_id'];
                                $entry_values = maybe_unserialize($entry_data['arm_entry_value']);
                                $payment_mode = $entry_values['arm_selected_payment_mode'];
                                $payment_cycle = $entry_values['arm_selected_payment_cycle'];

                                $arm_user_old_plan = (isset($entry_values['arm_user_old_plan']) && !empty($entry_values['arm_user_old_plan'])) ? explode(",", $entry_values['arm_user_old_plan']) : array();

                                $setup_id = $entry_values['setup_id'];
                                $setup_redirect = $entry_values['setup_redirect'];
                                $entry_email = $entry_data['arm_entry_email'];
                                if (empty($setup_redirect)) {
                                    $setup_redirect = ARM_HOME_URL;
                                }
                                unset($entry_values['setup_redirect']);
                                $entry_plan = $entry_data['arm_plan_id'];
                                $form_id = $entry_data['arm_form_id'];
                                $armform = new ARM_Form('id', $form_id);
                                $new_plan = new ARM_Plan($entry_plan);

                                if ($new_plan->is_recurring()) {
                                    if (in_array($entry_plan, $arm_user_old_plan)) {
                                        $is_recurring_payment = $arm_subscription_plans->arm_is_recurring_payment_of_user($user_id, $entry_plan, $payment_mode);
                                        if ($is_recurring_payment) {

                                            
                                            $planData = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);

                                            $oldPlanDetail = $planData['arm_current_plan_detail'];
                                            if (!empty($oldPlanDetail)) {
                                                $new_plan = new ARM_Plan(0);
                                                $new_plan->init((object) $oldPlanDetail);
                                                $plan_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                $extraVars['plan_amount'] = $plan_data['amount'];
                                            }
                                        } else {
                                            

                                            $plan_data = $new_plan->prepare_recurring_data($payment_cycle);
                                            $extraVars['plan_amount'] = $plan_data['amount'];
                                        }
                                    } else {
                                        
                                        $plan_data = $new_plan->prepare_recurring_data($payment_cycle);
                                        $extraVars['plan_amount'] = $plan_data['amount'];
                                    }
                                } else {
                                    
                                    $extraVars['plan_amount'] = $new_plan->amount;
                                }

                                
                                $user_info = get_user_by('email', $entry_email);

                                $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                                $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                $userPlanData = shortcode_atts($defaultPlanData, $userPlanDatameta);
                                $amount_for_tax = $extraVars['plan_amount'];

                                if (!$user_info && in_array($armform->type, array('registration'))) {
                                    $entry_values['payment_done'] = '1';
                                    $entry_values['arm_entry_id'] = $entry_id;
                                    $entry_values['arm_update_user_from_profile'] = 0;
                                    $user_id = $arm_member_forms->arm_register_new_member($entry_values, $armform);
                                    if (is_numeric($user_id) && !is_array($user_id)) {
                                        update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                        $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                        $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                        $userPlanData = shortcode_atts($defaultPlanData, $userPlanDatameta);
                                        $userPlanData['arm_user_gateway'] = '2checkout';
                                        if ($arm_payment_type == 'subscription') {

                                            $userPlanData['arm_2checkout'] = array('sale_id' => $insMsg['order_number'], 'transaction_id' => $hashInvoice);
                                            update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);

                                            $pgateway = '2checkout';
                                        }

                                        update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                        /**
                                         * Send Email Notification for Successful Payment
                                         */
                                        $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => 'new_subscription'));
                                    } else {
                                        wp_redirect(ARM_HOME_URL);
                                        exit;
                                    }
                                } else {
                                    $user_id = $user_info->ID;
                                    $is_update_plan = true;
                                    $now = current_time('mysql');
                                    $arm_last_payment_status = $wpdb->get_var($wpdb->prepare("SELECT `arm_transaction_status` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`=%d AND `arm_plan_id`=%d AND `arm_created_date`<=%s ORDER BY `arm_log_id` DESC LIMIT 0,1", $user_id, $entry_plan, $now));
                                    $arm_user_old_plan_details = (isset($userPlanData['arm_current_plan_detail']) && !empty($userPlanData['arm_current_plan_detail'])) ? $userPlanData['arm_current_plan_detail'] : array();
                                    $arm_user_old_plan_details['arm_user_old_payment_mode'] = $userPlanData['arm_payment_mode'];

                                    $userPlanData['arm_current_plan_detail'] = $arm_user_old_plan_details;
                                    $userPlanData['arm_payment_mode'] = $entry_values['arm_selected_payment_mode'];
                                    $userPlanData['arm_payment_cycle'] = $entry_values['arm_selected_payment_cycle'];

                                    $arm_is_paid_post = false;
                                    if( !empty( $entry_values['arm_is_post_entry'] ) && !empty( $entry_values['arm_paid_post_id'] ) ){
                                        $arm_is_paid_post = true;
                                    }

                                    if (!$is_multiple_membership_feature->isMultipleMembershipFeature && !$arm_is_paid_post ) {
                                        $old_plan_ids = get_user_meta($user_id, 'arm_user_plan_ids', true);
                                        $old_plan_ids = !empty($old_plan_ids) ? $old_plan_ids : array();
                                        $old_plan_id = isset($old_plan_ids[0]) ? $old_plan_ids[0] : 0;
                                        $oldPlanDetail = array();
                                        if (!empty($old_plan_id)) {
                                            $oldPlanData = get_user_meta($user_id, 'arm_user_plan_' . $old_plan_id, true);
                                            $oldPlanData = !empty($oldPlanData) ? $oldPlanData : array();
                                            $oldPlanData = shortcode_atts($defaultPlanData, $oldPlanData);
                                            $oldPlanDetail = $oldPlanData['arm_current_plan_detail'];
                                            $subscr_effective = $oldPlanData['arm_expire_plan'];
                                        }

                                        if (!empty($oldPlanDetail)) {
                                            $old_plan = new ARM_Plan(0);
                                            $old_plan->init((object) $oldPlanDetail);
                                        } else {
                                            $old_plan = new ARM_Plan($old_plan_id);
                                        }

                                        if ($old_plan->exists()) {
                                            if ($old_plan->is_lifetime() || $old_plan->is_free() || ($old_plan->is_recurring() && $new_plan->is_recurring())) {
                                                $is_update_plan = true;
                                            } else {
                                                $change_act = 'immediate';
                                                if ($old_plan->enable_upgrade_downgrade_action == 1) {
                                                    if (!empty($old_plan->downgrade_plans) && in_array($new_plan->ID, $old_plan->downgrade_plans)) {
                                                        $change_act = $old_plan->downgrade_action;
                                                    }
                                                    if (!empty($old_plan->upgrade_plans) && in_array($new_plan->ID, $old_plan->upgrade_plans)) {
                                                        $change_act = $old_plan->upgrade_action;
                                                    }
                                                }

                                                if ($change_act == 'on_expire' && !empty($subscr_effective)) {
                                                    $is_update_plan = false;
                                                    $oldPlanData['arm_subscr_effective'] = $subscr_effective;
                                                    $oldPlanData['arm_change_plan_to'] = $entry_plan;
                                                    update_user_meta($user_id, 'arm_user_plan_' . $old_plan_id, $oldPlanData);
                                                }
                                            }
                                        }
                                    }

                                    update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                    $userPlanData['arm_user_gateway'] = '2checkout';

                                    
                                    if ($arm_payment_type == 'subscription') {

                                        $userPlanData['arm_2checkout'] = array('sale_id' => $insMsg['order_number'], 'transaction_id' => $hashInvoice);
                                        $pgateway = '2checkout';
                                    }


                                    update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);


                                    if ($is_update_plan) {
                                        $arm_subscription_plans->arm_update_user_subscription($user_id, $entry_plan, '', true, $arm_last_payment_status);
                                    } else {
                                        if (!$is_multiple_membership_feature->isMultipleMembershipFeature && !$arm_is_paid_post) {
                                            $arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'change_subscription');
                                        } else {
                                            $arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'new_subscription');
                                        }
                                    }
                                }
                                if (!empty($user_id) && $user_id != 0) {

                                    /* Coupon Details */
                                   
                                    if($new_plan->is_recurring()){
                                        $recurring_data = $new_plan->prepare_recurring_data($payment_cycle);
                                        if (!empty($recurring_data['trial']) && in_array($armform->type, array('registration'))) {
                                            $arm_is_trial = '1';
                                            $extraVars['trial'] = array(
                                                'amount' => $recurring_data['trial']['amount'],
                                                'period' => $recurring_data['trial']['period'],
                                                'interval' => $recurring_data['trial']['interval'],
                                            );

                                            $amount_for_tax = $recurring_data['trial']['amount'];

                                        }
                                    }


                                    $couponCode = isset($entry_values['arm_coupon_code']) ? $entry_values['arm_coupon_code'] : '';
                                    $arm_coupon_discount = 0;
                                    $arm_coupon_discount_type = '';
                                    $arm_coupon_on_each_subscriptions = 0;
                                    if ($arm_manage_coupons->isCouponFeature && !empty($couponCode)) {
                                        /* Coupon Details */
                                        $couponApply = $arm_manage_coupons->arm_apply_coupon_code($couponCode, $new_plan, $setup_id, $payment_cycle, $arm_user_old_plan);
                                        $coupon_amount = isset($couponApply['coupon_amt']) ? $couponApply['coupon_amt'] : 0;
                                        $total_amount = isset($couponApply['total_amt']) ? $couponApply['total_amt'] : 0;
                                        $arm_coupon_discount = $couponApply['discount'];
                                        $global_currency = $arm_payment_gateways->arm_get_global_currency();
                                        $arm_coupon_discount_type = ($couponApply['discount_type'] != 'percentage') ? $global_currency : "%";
                                        $arm_coupon_on_each_subscriptions = isset($couponApply['arm_coupon_on_each_subscriptions']) ? $couponApply['arm_coupon_on_each_subscriptions'] : '0';


                                        if ($coupon_amount != 0) {
                                            $extraVars['coupon'] = array(
                                                'coupon_code' => $couponCode,
                                                'amount' => $coupon_amount,
                                            );
                                            
                                            $amount_for_tax = $total_amount;
                                        }


                                    }
                                    $tax_amount  = 0;
                                    if($tax_percentage > 0){

                                        
                                        $tax_amount = ($amount_for_tax *$tax_percentage)/100;
                                        $tax_amount = number_format((float)$tax_amount, 2, '.', '');

                                    }

                                    
                                    $extraVars['tax_percentage'] = $tax_percentage;
                                    $extraVars['tax_amount'] = $tax_amount;


                                    $arm_first_name='';
                                    $arm_last_name='';
                                    if($user_id){
                                        $user_detail = get_userdata($user_id);
                                        $arm_first_name=$user_detail->first_name;
                                        $arm_last_name=$user_detail->last_name;
                                    }
                                    $payment_data = array(
                                        'arm_user_id' => $user_id,
                                        'arm_first_name'=> $arm_first_name,
                                        'arm_last_name'=> $arm_last_name,
                                        'arm_plan_id' => $entry_plan,
                                        'arm_payment_gateway' => '2checkout',
                                        'arm_payment_type' => $arm_payment_type,
                                        'arm_token' => $insMsg['order_number'],
                                        'arm_payer_email' => $insMsg['email'],
                                        'arm_receiver_email' => '',
                                        'arm_transaction_id' => $hashInvoice,
                                        'arm_transaction_payment_type' => $arm_payment_type,
                                        'arm_transaction_status' => 'success',
                                        'arm_payment_mode' => $payment_mode,
                                        'arm_payment_date' => current_time('mysql'),
                                        'arm_amount' => $insMsg['total'],
                                        'arm_currency' => $insMsg['currency_code'],
                                        'arm_coupon_code' => @$couponCode,
                                        'arm_coupon_discount' => @$arm_coupon_discount,
                                        'arm_coupon_discount_type' => @$arm_coupon_discount_type,
                                        'arm_response_text' => utf8_encode(maybe_serialize($insMsg)),
                                        'arm_extra_vars' => maybe_serialize($extraVars),
                                        'arm_is_trial' => $arm_is_trial,
                                        'arm_created_date' => current_time('mysql'),
                                        'arm_coupon_on_each_subscriptions' => $arm_coupon_on_each_subscriptions,
                                    );
                                    $payment_log_id = $arm_payment_gateways->arm_save_payment_log($payment_data);
                                    if(!empty($pgateway))
                                    {
                                        $userPlanData = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                        $arm_manage_coupons->arm_coupon_apply_to_subscription($user_id, $payment_log_id, $pgateway, $userPlanData);
                                    }
                                }
                                wp_redirect($setup_redirect);
                                exit;
                            } /* END `($StringToHash == $insMsg['key'])` */
                        } /* END `(!empty($entry_data))` */
                    }
                }
            }
            
        }

        function arm_change_pending_gateway_outside($user_pending_pgway, $plan_ID, $user_id) {
            global $is_free_manual, $ARMember;
            if ($is_free_manual) {
                $key = array_search('2checkout', $user_pending_pgway);
                unset($user_pending_pgway[$key]);
            }
            return $user_pending_pgway;
        }

    }

}
global $arm_2checkout;
$arm_2checkout = new ARM_2checkout();
