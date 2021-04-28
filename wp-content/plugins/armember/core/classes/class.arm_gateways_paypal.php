<?php

if (!class_exists('ARM_Paypal')) {

    class ARM_Paypal {

        function __construct() {
            add_action('arm_payment_gateway_validation_from_setup', array(&$this, 'arm_payment_gateway_form_submit_action'), 10, 4);
            //add_action('wp', array(&$this, 'arm_paypal_api_handle_response'), 5);
            add_action('arm_cancel_subscription_gateway_action', array(&$this, 'arm_cancel_paypal_subscription'), 10, 2);
            add_filter('arm_update_new_subscr_gateway_outside', array(&$this, 'arm_update_new_subscr_gateway_outside_func'), 10);
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
            $subscr_id = !empty($planData['arm_subscr_id']) ? $planData['arm_subscr_id'] : '';

            $plan_cycle = isset($planData['arm_payment_cycle']) ? $planData['arm_payment_cycle'] : '';
            $paly_cycle_data = $plan->prepare_recurring_data($plan_cycle);

            $user_payment_gateway = !empty($planData['arm_user_gateway']) ? $planData['arm_user_gateway'] : '';

            if(!empty($subscr_id) && strtolower($user_payment_gateway) == 'paypal' && $payment_mode == "auto_debit_subscription" && $cancel_plan_action == "on_expire" && $paly_cycle_data['rec_time'] == 'infinite')
            {
                $this->arm_immediate_cancel_paypal_payment($subscr_id, $user_id, $plan_id, $planData);
            }
        }


        function arm_immediate_cancel_paypal_payment($subscr_id, $user_id, $plan_id, $planData)
        {
            global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans, $arm_member_forms, $arm_payment_gateways, $arm_manage_communication, $arm_subscription_cancel_msg;

            try{
                $PayPal = self::arm_init_paypal();
                
                $PayPalCancelRequestData = array(
                    'MRPPSFields' => array(
                        'profileid' => $subscr_id,
                        'action' => urlencode('Cancel'),
                        'note' => __("Cancel User's Subscription.", 'ARMember')
                    )
                );
                $PayPalResult = $PayPal->ManageRecurringPaymentsProfileStatus($PayPalCancelRequestData);
                if (!is_wp_error($PayPalResult) && isset($PayPalResult['ACK']) && strtolower($PayPalResult['ACK']) == 'success') {
                    $planData['arm_subscr_id'] = '';
                    update_user_meta($user_id, 'arm_user_plan_' . $plan_id, $planData);
                }
            }
            catch(Exception $e)
            {
                if(!empty($e->getMessage()))
                {
                    $arm_subscription_cancel_msg = __("Error in cancel subscription from Paypal.", "ARMember")." ".$e->getMessage();
                }
                else
                {
                    $common_messages = isset($arm_global_settings->common_message) ? $arm_global_settings->common_message : array();
                    $arm_subscription_cancel_msg = isset($common_messages['arm_payment_gateway_subscription_failed_error_msg']) ? $common_messages['arm_payment_gateway_subscription_failed_error_msg'] : __("Membership plan couldn't cancel. Please contact the site administrator.", 'ARMember');
                }
            }
        }

        function arm_init_paypal() {
            global $wpdb, $ARMember, $arm_global_settings, $arm_payment_gateways;
            if (file_exists(MEMBERSHIP_DIR . "/lib/paypal/paypal.class.php")) {
                require_once (MEMBERSHIP_DIR . "/lib/paypal/paypal.class.php");
            }
            /* ---------------------------------------------------------------------------- */
            $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
            if (isset($all_payment_gateways['paypal']) && !empty($all_payment_gateways['paypal'])) {
                $paypal_options = $all_payment_gateways['paypal'];
                //Set Paypal Currency
                $currency = $arm_payment_gateways->arm_get_global_currency();
                $sandbox = (isset($paypal_options['paypal_payment_mode']) && $paypal_options['paypal_payment_mode'] == 'sandbox') ? TRUE : FALSE;
                /** Set API Credentials */
                $developer_account_email = $paypal_options['paypal_merchant_email'];
                $api_username = $sandbox ? $paypal_options['sandbox_api_username'] : $paypal_options['live_api_username'];
                $api_password = $sandbox ? $paypal_options['sandbox_api_password'] : $paypal_options['live_api_password'];
                $api_signature = $sandbox ? $paypal_options['sandbox_api_signature'] : $paypal_options['live_api_signature'];
                /* ---------------------------------------------------------------------------- */
                $PayPalConfig = array(
                    'Sandbox' => $sandbox,
                    'APIUsername' => $api_username,
                    'APIPassword' => $api_password,
                    'APISignature' => $api_signature
                );
                $PayPal = new PayPal($PayPalConfig);
                $PayPal->ARMcurrency = $currency;
                $PayPal->ARMsandbox = $sandbox;
            } else {
                $PayPal = false;
            }
            return $PayPal;
        }

        function arm_generate_paypal_form($plan_action = 'new_subscription', $plan_id = 0, $entry_id = 0, $coupon_code = '', $form_type = 'new', $setup_id = 0, $payment_mode = 'manual_subscription') {
            global $wpdb, $ARMember, $arm_slugs, $arm_global_settings, $arm_subscription_plans, $arm_manage_coupons, $arm_payment_gateways, $arm_membership_setup, $is_free_manual;
            $paypal_form = '';
            $is_free_manual = false;
            if (!empty($plan_id) && $plan_id != 0 && !empty($entry_id) && $entry_id != 0) {

                
                $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
                if (isset($all_payment_gateways['paypal']) && !empty($all_payment_gateways['paypal'])) {
                    $paypal_options = $all_payment_gateways['paypal'];
                    //Set Paypal Callback URLs


                $arf_pyapal_home_url = ARM_HOME_URL . "/";

                if (strstr($arf_pyapal_home_url, '?')) {
                    $notify_url = $arf_pyapal_home_url . '&arm-listener=arm_paypal_api';
                    
                } else {
                   $notify_url = $arf_pyapal_home_url . '?arm-listener=arm_paypal_api';
                }


                    $globalSettings = $arm_global_settings->global_settings;
                    $cp_page_id = isset($globalSettings['cancel_payment_page_id']) ? $globalSettings['cancel_payment_page_id'] : 0;
                    
                    $default_cancel_url = $arm_global_settings->arm_get_permalink('', $cp_page_id);
                    
                    $cancel_url = (!empty($paypal_options['cancel_url'])) ? $paypal_options['cancel_url'] : $default_cancel_url;
                    if ($cancel_url == '' || empty($cancel_url)) {
                        $cancel_url = ARM_HOME_URL;
                    }
                    //Get Entry Detail
                    $entry_data = $arm_payment_gateways->arm_get_entry_data_by_id($entry_id);
                    if (!empty($entry_data)) {
                        $user_email = $entry_data['arm_entry_email'];
                        $form_id = $entry_data['arm_form_id'];
                        $user_id = $entry_data['arm_user_id'];
                        $entry_values = maybe_unserialize($entry_data['arm_entry_value']);
                        $return_url = $entry_values['setup_redirect'];
                        
                        if (empty($return_url)) {
                            $return_url = ARM_HOME_URL;
                        }
                        
                        $arm_user_selected_payment_cycle = $entry_values['arm_selected_payment_cycle'];
                        $tax_percentage =  isset($entry_values['tax_percentage']) ? $entry_values['tax_percentage'] : 0;

                        
                       
                        $arm_user_old_plan = (isset($entry_values['arm_user_old_plan']) && !empty($entry_values['arm_user_old_plan'])) ? explode(",", $entry_values['arm_user_old_plan']) : array();
                        $arm_is_trial = '0';

                        $sandbox = (isset($paypal_options['paypal_payment_mode']) && $paypal_options['paypal_payment_mode'] == 'sandbox') ? 'sandbox.' : '';
                        //Set Paypal Currency
                        $currency = $arm_payment_gateways->arm_get_global_currency();
                        $plan = new ARM_Plan($plan_id);


                        $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                        $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                        $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                        $planData = shortcode_atts($defaultPlanData, $userPlanDatameta);


                        if ($plan_action == 'renew_subscription' && $plan->is_recurring()) {
                            $is_recurring_payment = $arm_subscription_plans->arm_is_recurring_payment_of_user($user_id, $plan_id, $payment_mode);
                            if ($is_recurring_payment) {
                                $plan_action = 'recurring_payment';
                                $oldPlanDetail = $planData['arm_current_plan_detail'];
                                if (!empty($oldPlanDetail)) {
                                    $plan = new ARM_Plan(0);
                                    $plan->init((object) $oldPlanDetail);
                                }
                            }
                        }

                        $plan_payment_type = $plan->payment_type;
                        //Set Custom Variable.
                       
                        //Set Amount to be paid

                        if ($plan->is_recurring()) {
                            $plan_data = $plan->prepare_recurring_data($arm_user_selected_payment_cycle);
                            $amount = $plan_data['amount'];
                        } else {
                            $amount = $plan->amount;
                        }


                          
                       
                         $amount = str_replace(",", "", $amount);
                         $main_amount = $amount;
                        

                        
                        



                        $discount_amt = $coupon_amount = $arm_coupon_discount = 0;$trial_amount = 0;
                        $arm_coupon_discount_type = '';
                        $arm_coupon_discount_type_default ='';
                        $arm_coupon_on_each_subscriptions = '0';
                        /* Coupon Details */


                        if ($arm_manage_coupons->isCouponFeature && !empty($coupon_code)) {
                            $couponApply = $arm_manage_coupons->arm_apply_coupon_code($coupon_code, $plan, $setup_id, $arm_user_selected_payment_cycle, $arm_user_old_plan);
                            $coupon_amount = isset($couponApply['coupon_amt']) ? $couponApply['coupon_amt'] : 0;
                            $discount_amt = isset($couponApply['total_amt']) ? $couponApply['total_amt'] : $amount;
                            $arm_coupon_on_each_subscriptions = isset($couponApply['arm_coupon_on_each_subscriptions']) ? $couponApply['arm_coupon_on_each_subscriptions'] : '0';
                            $arm_coupon_discount_type_default = isset($couponApply['discount_type']) ? $couponApply['discount_type'] : "";

                            $arm_coupon_discount = isset($couponApply['discount']) ? $couponApply['discount'] : 0;
                            $global_currency = $arm_payment_gateways->arm_get_global_currency();
                            if (isset($couponApply['discount_type'])) {
                                $arm_coupon_discount_type = ($couponApply['discount_type'] != 'percentage') ? $global_currency : "%";
                            } else {
                                $arm_coupon_discount_type = '';
                            }
                        }
                        $plan_form_data = '';

                        $discount_amt_next = $amount;
                        if ($plan->is_recurring()){

                            if ($discount_amt>0 && isset($arm_coupon_on_each_subscriptions) && !empty($arm_coupon_on_each_subscriptions)) {
                                if($arm_coupon_discount_type_default=='percentage')
                                {
                                    $discount_amt_next = ($amount * $arm_coupon_discount) / 100;
                                    $discount_amt_next = $amount-$discount_amt_next;
                                }
                                else if($arm_coupon_discount_type_default=='fixed')
                                {
                                    $discount_amt_next = $amount - $arm_coupon_discount;
                                }

                                if($discount_amt_next<0)
                                {
                                   $discount_amt_next = 0; 
                                }
                                
                            }

                            $recurring_data = $plan->prepare_recurring_data($arm_user_selected_payment_cycle);
                            $recur_period = $recurring_data['period'];
                            $recur_interval = $recurring_data['interval'];
                            $recur_cycles = $recurring_data['cycles'];

                            $is_trial = false;
                            $allow_trial = true;
                            if (is_user_logged_in()) {
                                $user_id = get_current_user_id();
                                $user_plan = get_user_meta($user_id, 'arm_user_plan_ids', true);
                                if (!empty($user_plan)) {
                                    $allow_trial = false;
                                }
                            }


                            

                            if($payment_mode == 'auto_debit_subscription'){

                                

                                if ($plan->has_trial_period() && $allow_trial) {
                                    $is_trial = true;
                                    $arm_is_trial = '1';
                                    $trial_amount = $recurring_data['trial']['amount'];
                                    $trial_period = $recurring_data['trial']['period'];
                                    $trial_interval = $recurring_data['trial']['interval'];
                                    $trial_amount = str_replace(",", "", $trial_amount);
                                   
                                }

                                if (!empty($coupon_amount) && $coupon_amount > 0) {
                                    $trial_amount = $discount_amt;
                                    if (!$is_trial) {
                                        $recur_cycles = ($recur_cycles > 1) ? $recur_cycles - 1 : 1;
                                        $is_trial = true;
                                        $plan_action = 'new_subscription';
                                        $trial_interval = $recur_interval;
                                        $trial_period = $recur_period;
                                    }
                                    $trial_amount = str_replace(",", "", $trial_amount);
                                    
                                }

                                $remained_days = 0;
                                if ($plan_action == 'renew_subscription') {
                                    $user_plan_data = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                                    $plan_expiry_date = $user_plan_data['arm_expire_plan'];
                                    $now = strtotime(current_time('mysql'));

                                    $remained_days = ceil(abs($plan_expiry_date - $now) / 86400);

                                    if ($remained_days > 0) {
                                        $trial_amount = 0;
                                        $trial_interval = $remained_days;
                                        $trial_period = 'D';
                                        
                                    }
                                }
                            }
                            else if($payment_mode == 'manual_subscription'){
                                if ($plan->has_trial_period() && $allow_trial) {
                                    $is_trial = true;
                                    $arm_is_trial = '1';
                                    $trial_amount = $recurring_data['trial']['amount'];
                                    $trial_period = $recurring_data['trial']['period'];
                                    $trial_interval = $recurring_data['trial']['interval'];
                                } else {
                                    $trial_amount = $amount;
                                }
                                if (!empty($coupon_amount) && $coupon_amount > 0) {
                                    $trial_amount = $discount_amt;
                                    if (!$is_trial) {
                                        $recur_cycles = ($recur_cycles > 1) ? $recur_cycles - 1 : 1;
                                        $is_trial = true;
                                        $plan_action = 'new_subscription';
                                        $trial_interval = $recur_interval;
                                        $trial_period = $recur_period;
                                    }
                                }
                                $trial_amount = str_replace(",", "", $trial_amount);
                              
                            }
                        }
                        else{
                            
                            if (!empty($coupon_amount) && $coupon_amount > 0) {
                                $amount = $discount_amt_next = $discount_amt;
                              
                            }
                        }

                        $amount = str_replace(",", "", $discount_amt_next);

                       
                        $final_trial_amount = $trial_amount;
                        $final_amount = $amount;
                        if($tax_percentage > 0){
                            $trial_tax_amount =($trial_amount*$tax_percentage)/100;
                            $trial_tax_amount = number_format((float)$trial_tax_amount, 2, '.','');
                            $final_trial_amount = $trial_amount+$trial_tax_amount;

                            $tax_amount =($amount*$tax_percentage)/100;
                            $tax_amount = number_format((float)$tax_amount, 2, '.','');
                            $final_amount = $amount+$tax_amount;
                          
                        }


                         $custom_var = $entry_id . '|' . $user_email . '|' . $plan_payment_type.'|'.$tax_amount.'|'.$trial_tax_amount;

                        

                        if ($currency == 'HUF' || $currency == 'JPY' || $currency == 'TWD') {
                            $final_trial_amount= number_format((float) $final_trial_amount, 0, '', '');
                        }
                        else{
                            $final_trial_amount = number_format((float)$final_trial_amount, 2, '.','');
                        }


                        if ($currency == 'HUF' || $currency == 'JPY' || $currency == 'TWD') {
                            $final_amount = number_format((float) $final_amount, 0, '', '');
                        }
                        else{
                            $final_amount = number_format((float)$final_amount, 2, '.','');
                        }








                        if ($plan->is_recurring() && $payment_mode == 'auto_debit_subscription') {
                            $cmd = "_xclick-subscriptions";
                            
                            $plan_form_data .= '<input type="hidden" name="a3" value="' . $final_amount . '" />';
                            $plan_form_data .= '<input type="hidden" name="p3" value="' . $recur_interval . '" />';
                            $plan_form_data .= '<input type="hidden" name="t3" value="' . $recur_period . '" />';
                            // PayPal re-attempts failed recurring payments
                            $plan_form_data .= '<input type="hidden" name="sra" value="1" />';
                            // Set recurring payments until cancelled.
                            $plan_form_data .= '<input type="hidden" name="src" value="1" />';
                            $plan_form_data .= '<input type="hidden" name="no_note" value="1" />';
                            $modify_val = ($form_type == 'modify') ? '1' : '0';
                            $plan_form_data .= '<input type="hidden" name="modify" value="' . $modify_val . '" />';
                            if ($recur_cycles > 1) {
                                //Set recurring payments to stop after X billing cycles
                                $plan_form_data .= '<input type="hidden" name="srt" value="' . $recur_cycles . '" />';
                            }
                            if ($is_trial && $plan_action == 'new_subscription' || $remained_days > 0) {
                                $plan_form_data .= '<input type="hidden" name="a1" value="' . $final_trial_amount . '" />';
                                $plan_form_data .= '<input type="hidden" name="p1" value="' . $trial_interval . '" />';
                                $plan_form_data .= '<input type="hidden" name="t1" value="' . $trial_period . '" />';
                            }
                        } else if ($plan->is_recurring() && $payment_mode == 'manual_subscription') {
                            $cmd = "_xclick";
                            if ($final_trial_amount == 0 || $final_trial_amount == '0.00') {
                                $return_array = array();
                                if (is_user_logged_in()) {
                                    $current_user_id = get_current_user_id();
                                    $return_array['arm_user_id'] = $current_user_id;
                                }
				$user_detail = get_userdata($user_id);
	                        $arm_first_name = $user_detail->first_name;
	                        $arm_last_name = $user_detail->last_name;
				
                                $return_array['arm_first_name']=$arm_first_name;
                                $return_array['arm_last_name']=$arm_last_name;
                                $return_array['arm_plan_id'] = $plan->ID;
                                $return_array['arm_payment_gateway'] = 'paypal';
                                $return_array['arm_payment_type'] = $plan->payment_type;
                                $return_array['arm_token'] = '-';
                                $return_array['arm_payer_email'] = $user_email;
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
                                do_action('arm_after_paypal_free_manual_payment', $plan, $payment_log_id, $arm_is_trial, @$coupon_code, $extraParam);
                                return array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);
                            }
                            $plan_form_data .= "<input type='hidden' name='amount' value='" . $final_trial_amount . "' />";
                        } else {
                            $cmd = "_xclick";
                            
                            if ($final_amount == 0 || $final_amount == '0.00') {
                                $return_array = array();
                                if (is_user_logged_in()) {
                                    $current_user_id = get_current_user_id();
                                    $return_array['arm_user_id'] = $current_user_id;
                                }
				$user_detail = get_userdata($user_id);
	                        $arm_first_name = $user_detail->first_name;
	                        $arm_last_name = $user_detail->last_name;
			
                                $return_array['arm_first_name']=$arm_first_name;
                                $return_array['arm_last_name']=$arm_last_name;
                                $return_array['arm_plan_id'] = $plan->ID;
                                $return_array['arm_payment_gateway'] = 'paypal';
                                $return_array['arm_payment_type'] = $plan->payment_type;
                                $return_array['arm_token'] = '-';
                                $return_array['arm_payer_email'] = $user_email;
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
                                do_action('arm_after_paypal_free_payment', $plan, $payment_log_id, $arm_is_trial, @$coupon_code, $extraParam);
                                return array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);
                            }
                            $plan_form_data .= '<input type="hidden" name="amount" value="' . $final_amount . '" />';
                        }

                        $arm_paypal_language = isset($paypal_options['language']) ? $paypal_options['language'] : 'en_US';
                        $paypal_form = '<form name="_xclick" id="arm_paypal_form" action="https://www.' . $sandbox . 'paypal.com/cgi-bin/webscr" method="post">';
                        $paypal_form .= '<input type="hidden" name="cmd" value="' . $cmd . '" />';
                        $paypal_form .= '<input type="hidden" name="business" value="' . $paypal_options['paypal_merchant_email'] . '" />';
                        $paypal_form .= '<input type="hidden" name="notify_url" value="' . esc_url($notify_url) . '" />';
                        $paypal_form .= '<input type="hidden" name="cancel_return" value="' . esc_url($cancel_url) . '" />';
                        $paypal_form .= '<input type="hidden" name="return" value="' . esc_url($return_url) . '" />';
                        $paypal_form .= '<input type="hidden" name="rm" value="2" />';
                        $paypal_form .= '<input type="hidden" name="lc" value="' . $arm_paypal_language . '" />';
                        $paypal_form .= '<input type="hidden" name="no_shipping" value="1" />';
                        $paypal_form .= '<input type="hidden" name="custom" value="' . $custom_var . '" />';
                        $paypal_form .= '<input type="hidden" name="on0" value="user_email" />';
                        $paypal_form .= '<input type="hidden" name="os0" value="' . $user_email . '" />';
                        //$paypal_form .= '<input type="hidden" name="on1" value="user_plan">';
                        //$paypal_form .= '<input type="hidden" name="os1" value="' . $plan_id . '">';
                        $paypal_form .= '<input type="hidden" name="currency_code" value="' . $currency . '" />';
                        $paypal_form .= '<input type="hidden" name="page_style" value="primary" />';
                        $paypal_form .= '<input type="hidden" name="charset" value="UTF-8" />';
                        $paypal_form .= '<input type="hidden" name="item_name" value="' . $plan->name . '" />';
                        $paypal_form .= '<input type="hidden" name="item_number" value="1" />';
                        $paypal_form .= '<input type="submit" style="display:none;" name="cbt" value="' . __("Click here to continue", 'ARMember') . '" />';
                        $paypal_form .= $plan_form_data;
                        $paypal_form .= '<input type="submit" value="Pay with PayPal!" style="display:none;" />';
                        $paypal_form .= '</form>';
                        $paypal_form .= '<script data-cfasync="false" type="text/javascript" language="javascript">document.getElementById("arm_paypal_form").submit();</script>';
                    }
                }
            }
            return $paypal_form;
        }

        function arm_payment_gateway_form_submit_action($payment_gateway, $payment_gateway_options, $posted_data, $entry_id = 0) {
            global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans, $arm_member_forms, $arm_manage_coupons, $payment_done, $arm_payment_gateways, $arm_membership_setup;

            if ($payment_gateway == 'paypal') {
                $plan_id = (!empty($posted_data['subscription_plan'])) ? $posted_data['subscription_plan'] : 0;
                if ($plan_id == 0) {
                    $plan_id = (!empty($posted_data['_subscription_plan'])) ? $posted_data['_subscription_plan'] : 0;
                }

                $plan = new ARM_Plan($plan_id);

                $plan_action = 'new_subscription';

                $oldPlanIdArray = (isset($posted_data['old_plan_id']) && !empty($posted_data['old_plan_id'])) ? explode(",", $posted_data['old_plan_id']) : 0;
                if (!empty($oldPlanIdArray)) {
                    if (in_array($plan_id, $oldPlanIdArray)) {
                        $plan_action = 'renew_subscription';
                    } else {
                        $plan_action = 'change_subscription';
                    }
                }


            $trial_not_allowed = 0;
            if ($plan->is_recurring()) {
                 $setup_id = $posted_data['setup_id'];
                $payment_mode_ = !empty($posted_data['arm_selected_payment_mode']) ? $posted_data['arm_selected_payment_mode'] : 'manual_subscription';
                    if(isset($posted_data['arm_payment_mode']['paypal'])){
                        $payment_mode_ = !empty($posted_data['arm_payment_mode']['paypal']) ? $posted_data['arm_payment_mode']['paypal'] : 'manual_subscription';
                    }
                    else{
                        $setup_data = $arm_membership_setup->arm_get_membership_setup($setup_id);
                        if (!empty($setup_data) && !empty($setup_data['setup_modules']['modules'])) {
                            $setup_modules = $setup_data['setup_modules'];
                            $modules = $setup_modules['modules'];
                            $payment_mode_ = $modules['payment_mode']['paypal'];
                        }
                    }

                    $subscription_plan_detail = $arm_subscription_plans->arm_get_subscription_plan($plan_id);

                    if( !empty($subscription_plan_detail['arm_subscription_plan_options']['trial']) ) {
                        $subscr_trial_detail = $subscription_plan_detail['arm_subscription_plan_options']['trial'];
                        if(!empty($subscr_trial_detail['is_trial_period']) && $subscr_trial_detail['is_trial_period']==1) {
                            $trial_period = isset($subscr_trial_detail['days']) ? $subscr_trial_detail['days'] : 0;
                            $trial_type = isset($subscr_trial_detail['type']) ? $subscr_trial_detail['type'] : 'D';

                            if( $trial_type=='D' && $trial_period > 90 ) {
                                $trial_not_allowed = 1;
                            }
                        }
                    }


                    $payment_mode = 'manual_subscription';
                    $c_mpayment_mode = "";
                    if(isset($posted_data['arm_pay_thgough_mpayment']) && $posted_data['arm_plan_type']=='recurring' && is_user_logged_in())
                    {
                        $current_user_id = get_current_user_id();
                        $current_user_plan_ids = get_user_meta($current_user_id, 'arm_user_plan_ids', true);
                        $current_user_plan_ids = !empty($current_user_plan_ids) ? $current_user_plan_ids : array();
                        $Current_M_PlanData = get_user_meta($current_user_id, 'arm_user_plan_' . $plan_id, true);
                        $Current_M_PlanDetails = $Current_M_PlanData['arm_current_plan_detail'];
                        if (!empty($current_user_plan_ids)) {
                            if(in_array($plan_id, $current_user_plan_ids) && !empty($Current_M_PlanDetails))
                            {
                                $arm_cmember_paymentcycle = $Current_M_PlanData['arm_payment_cycle'];
                                $arm_cmember_completed_recurrence = $Current_M_PlanData['arm_completed_recurring'];
                                $arm_cmember_plan = new ARM_Plan(0);
                                $arm_cmember_plan->init((object) $Current_M_PlanDetails);
                                $arm_cmember_plan_data = $arm_cmember_plan->prepare_recurring_data($arm_cmember_paymentcycle);
                                $arm_cmember_TotalRecurring = $arm_cmember_plan_data['rec_time'];
                                if ($arm_cmember_TotalRecurring == 'infinite' || ($arm_cmember_completed_recurrence !== '' && $arm_cmember_completed_recurrence != $arm_cmember_TotalRecurring)) {
                                    $c_mpayment_mode = 1;
                                }
                            }
                        }
                    }
                    if(empty($c_mpayment_mode))
                    {
                        if ($payment_mode_ == 'both') {
                            $payment_mode = !empty($posted_data['arm_selected_payment_mode']) ? $posted_data['arm_selected_payment_mode'] : 'manual_subscription';
                        } else {
                            $payment_mode = $payment_mode_;
                        }
                    }
                }
                else{
                    $payment_mode = '';
                }


               
                if( $trial_not_allowed == 1 ) {
                    $err_msg = esc_html__('The Trial Period you have selected for this plan is not supported with PayPal','ARMember');
                    return $payment_done = array('status' => FALSE, 'error' => $err_msg);
                } else {
                    $coupon_code = (!empty($posted_data['arm_coupon_code'])) ? $posted_data['arm_coupon_code'] : '';
                    $setup_id = $posted_data['setup_id'];

                    $paypal_form = self::arm_generate_paypal_form($plan_action, $plan_id, $entry_id, $coupon_code, 'new', $setup_id, $payment_mode);
                    if (is_array($paypal_form)) {

                        
                        global $payment_done;
                        $payment_done = $paypal_form;
                        $payment_done['zero_amount_paid'] = true;
                        return $payment_done;
                    } else if (isset($posted_data['action']) && in_array($posted_data['action'], array('arm_shortcode_form_ajax_action', 'arm_membership_setup_form_ajax_action'))) {

                        
                        $return = array('status' => 'success', 'type' => 'redirect', 'message' => $paypal_form);
                        echo json_encode($return);
                        exit;
                    } else {

                        
                        echo $paypal_form;
                        exit;
                    }    
                }
            }
        }

        function arm_paypal_api_handle_response() {
            global $wpdb, $ARMember, $arm_global_settings, $arm_members_class, $arm_subscription_plans, $arm_member_forms, $arm_payment_gateways, $arm_manage_communication, $arm_manage_coupons, $payment_done, $is_multiple_membership_feature;

            if (isset($_REQUEST['arm-listener']) && in_array($_REQUEST['arm-listener'], array('arm_paypal_api', 'arm_paypal_notify'))) {
                if (!empty($_POST['txn_id']) || !empty($_POST['subscr_id'])) {
                    $req = 'cmd=_notify-validate';
                    foreach ($_POST as $key => $value) {
                        $value = urlencode(stripslashes($value));
                        $req .= "&$key=$value";
                    }
                    $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
                    if (isset($all_payment_gateways['paypal']) && !empty($all_payment_gateways['paypal'])) {
                        $options = $all_payment_gateways['paypal'];
                        $request = new WP_Http();
                        /* For HTTP1.0 Request */
                        $requestArr = array(
                            "sslverify" => false,
                            "ssl" => true,
                            "body" => $req,
                            "timeout" => 20,
                        );
                        /* For HTTP1.1 Request */
                        $requestArr_1_1 = array(
                            "httpversion" => '1.1',
                            "sslverify" => false,
                            "ssl" => true,
                            "body" => $req,
                            "timeout" => 20,
                        );
                        $response = array();
                        if (isset($options['paypal_payment_mode']) && $options['paypal_payment_mode'] == 'sandbox') {
                            $url = "https://www.sandbox.paypal.com/cgi-bin/webscr/";
                            $response_1_1 = $request->post($url, $requestArr_1_1);
                            if (!is_wp_error($response_1_1) && $response_1_1['body'] == 'VERIFIED') {
                                $response = $response_1_1;
                            } else {
                                $response = $request->post($url, $requestArr);
                            }
                        } else {
                            $url = "https://www.paypal.com/cgi-bin/webscr/";
                            $response_1_0 = $request->post($url, $requestArr);
                            if (!is_wp_error($response_1_0) && $response_1_0['body'] == 'VERIFIED') {
                                $response = $response_1_0;
                            } else {
                                $response = $request->post($url, $requestArr_1_1);
                            }
                        }
                        if (!is_wp_error($response) && $response['body'] == 'VERIFIED') {
                            $paypalLog = $_POST;
                            $customs = explode('|', $_POST['custom']);
                            $entry_id = $customs[0];
                            $entry_email = $customs[1];
                            $arm_payment_type = $customs[2];
                            $arm_tax_amount = (isset($customs[3]) && $customs[3] !='') ? $customs[3] : 0;
                            $arm_trial_tax_amount = (isset($customs[4]) && $customs[4] !='') ? $customs[4] : 0;
                            $txn_id = isset($_POST['txn_id']) ? $_POST['txn_id'] : '';
                            $arm_token = isset($_POST['subscr_id']) ? $_POST['subscr_id'] : '';
                            $txn_type = isset($_POST['txn_type']) ? $_POST['txn_type'] : '';
                            /**                             * ***********************************************
                             * Do Member Form Action After Successfull Payment
                             * ************************************************ */
                            $user_id = 0;

                           

                            $entry_data = $wpdb->get_row("SELECT `arm_entry_id`, `arm_entry_email`, `arm_entry_value`, `arm_form_id`, `arm_user_id`, `arm_plan_id` FROM `" . $ARMember->tbl_arm_entries . "` WHERE `arm_entry_id`='" . $entry_id . "' AND `arm_entry_email`='" . $entry_email . "'", ARRAY_A);


                            if (!empty($entry_data)) {
                                $is_log = false;
                                $extraParam = array('plan_amount' => $_POST['mc_gross'], 'paid_amount' => $_POST['mc_gross']);
                                $entry_values = maybe_unserialize($entry_data['arm_entry_value']);
                                $payment_mode = $entry_values['arm_selected_payment_mode'];
                                $payment_cycle = $entry_values['arm_selected_payment_cycle'];
                                $arm_user_old_plan = (isset($entry_values['arm_user_old_plan']) && !empty($entry_values['arm_user_old_plan'])) ? explode(",", $entry_values['arm_user_old_plan']) : array();
                                $setup_id = $entry_values['setup_id'];
                                $tax_percentage = $entry_values['tax_percentage'];



                                $entry_plan = $entry_data['arm_plan_id'];
                                $paypalLog['arm_coupon_code'] = $entry_values['arm_coupon_code'];
                                $paypalLog['arm_payment_type'] = $arm_payment_type;
                                $extraParam['arm_is_trial'] = '0';
                                $extraParam['tax_percentage'] = (isset($tax_percentage) && $tax_percentage > 0) ? $tax_percentage : 0; 
                                $extraParam['tax_amount'] = $arm_tax_amount;
                                $extraParam['subs_id'] = $arm_token;
                                $extraParam['trans_id'] = isset($_POST['txn_id']) ? $_POST['txn_id'] : '';
                                $extraParam['error'] = isset($_POST['txn_type']) ? $_POST['txn_type'] : '';
                                $extraParam['date'] = current_time('mysql');
                                $extraParam['message_type'] = isset($_POST['txn_type']) ? $_POST['txn_type'] : '';

                                $user_info = get_user_by('email', $entry_email);
                                $do_not_update_user = true;
                                if ($user_info) {
                                    $user_id = $user_info->ID;

                                    $trxn_success_log_id = $wpdb->get_var("SELECT `arm_log_id` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`='" . $user_id . "' AND `arm_transaction_id`='" . $txn_id . "' AND `arm_transaction_status` = 'success' AND `arm_payment_gateway` = 'paypal'");
                                    if($trxn_success_log_id!='')
                                    {
                                        $do_not_update_user = false;
                                    }

                                    if($do_not_update_user)
                                    {
                                        $log_id = $wpdb->get_var("SELECT `arm_log_id` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`='" . $user_id . "' AND `arm_transaction_id`='" . $txn_id . "' AND `arm_transaction_status` = 'pending' AND `arm_payment_gateway` = 'paypal'");

                                        if ($log_id != '') {
                                            $payment_history_data = array();
                                            $payment_history_data['arm_transaction_status'] = 'success';
                                            $field_update = $wpdb->update($ARMember->tbl_arm_payment_log, $payment_history_data, array('arm_log_id' => $log_id));
                                            $do_not_update_user = false;
                                        }
                                    }
                                }

                                if ($do_not_update_user) {
                                    
                                    switch ($_POST['txn_type']) {
                                        case 'subscr_signup':

                                          
                                            /*
                                             * Only Create user or update membership when trial period option is enable
                                             */
                                            if (isset($_POST['mc_amount1']) && $_POST['mc_amount1'] == 0) {
                                               
                                                $extraParam = array('plan_amount' => $_POST['mc_amount3'], 'paid_amount' => $_POST['mc_amount1']);
                                                $form_id = $entry_data['arm_form_id'];


                                                $armform = new ARM_Form('id', $form_id);
                                                $user_info = get_user_by('email', $entry_email);
                                                $new_plan = new ARM_Plan($entry_plan);
                                                $couponCode = isset($entry_values['arm_coupon_code']) ? $entry_values['arm_coupon_code'] : '';
                                                /* Coupon Details */


                                                if ($new_plan->is_recurring()) {
                                                    if (in_array($entry_plan, $arm_user_old_plan)) {
                                                        $is_recurring_payment = $arm_subscription_plans->arm_is_recurring_payment_of_user($user_id, $entry_plan, $payment_mode);
                                                        if ($is_recurring_payment) {
                                                            $planData = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                                            $oldPlanDetail = $planData['arm_current_plan_detail'];
                                                            if (!empty($oldPlanDetail)) {
                                                                $plan = new ARM_Plan(0);
                                                                $plan->init((object) $oldPlanDetail);
                                                                $plan_data = $plan->prepare_recurring_data($payment_cycle);
                                                                $extraParam['plan_amount'] = $plan_data['amount'];
                                                            }
                                                        } else {
                                                            $plan_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                            $extraParam['plan_amount'] = $plan_data['amount'];
                                                        }
                                                    } else {
                                                        $plan_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                        $extraParam['plan_amount'] = $plan_data['amount'];
                                                    }
                                                } else {
                                                    $extraParam['plan_amount'] = $new_plan->amount;
                                                }
                                                 $arm_coupon_discount = 0;
                                                if (!empty($couponCode)) {
                                                    $couponApply = $arm_manage_coupons->arm_apply_coupon_code($couponCode, $new_plan, $setup_id, $payment_cycle, $arm_user_old_plan);
                                                    $coupon_amount = isset($couponApply['coupon_amt']) ? $couponApply['coupon_amt'] : 0;
                                                    $arm_coupon_on_each_subscriptions = isset($couponApply['arm_coupon_on_each_subscriptions']) ? $couponApply['arm_coupon_on_each_subscriptions'] : 0;
                                                    
                                                    if ($coupon_amount != 0) {
                                                        $extraParam['coupon'] = array(
                                                            'coupon_code' => $couponCode,
                                                            'amount' => $coupon_amount,
                                                            'arm_coupon_on_each_subscriptions' => $arm_coupon_on_each_subscriptions
                                                        );

                                                        $arm_coupon_discount = $couponApply['discount'];
                                                        $global_currency = $arm_payment_gateways->arm_get_global_currency();
                                                        $arm_coupon_discount_type = ($couponApply['discount_type'] != 'percentage') ? $global_currency : "%";
                                                        $paypalLog['coupon_code'] = $couponCode;
                                                        $paypalLog['arm_coupon_discount'] = $arm_coupon_discount;
                                                        $paypalLog['arm_coupon_discount_type'] = $arm_coupon_discount_type;
                                                        $paypalLog['arm_coupon_on_each_subscriptions'] = $arm_coupon_on_each_subscriptions;
                                                        
                                                    }
                                                }
                                                $recurring_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                if (!empty($recurring_data['trial']) && empty($arm_user_old_plan)) {
                                                    $extraParam['trial'] = array(
                                                        'amount' => $recurring_data['trial']['amount'],
                                                        'period' => $recurring_data['trial']['period'],
                                                        'interval' => $recurring_data['trial']['interval'],
                                                       
                                                    );
                                                    $extraParam['arm_is_trial'] = '1';
                                                   $extraParam['tax_amount'] = $arm_trial_tax_amount; 
                                                }
                                                if( $arm_coupon_discount > 0){
                                                                    $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                                }

                                                $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                                                $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                                $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                                $userPlanData = shortcode_atts($defaultPlanData, $userPlanDatameta);


                                                if (!$user_info && in_array($armform->type, array('registration'))) {

                                                    if (!empty($recurring_data['trial']) && empty($arm_user_old_plan)) {
                                                        $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                    }

                                                    if($new_plan->is_recurring()){

                                                    if( $arm_coupon_discount > 0){
                                                        $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                    }
                                                }
                                                    
                                                    $payment_log_id = self::arm_store_paypal_log($paypalLog, 0, $entry_plan, $extraParam, $payment_mode);
                                                    $payment_done = array();
                                                    if ($payment_log_id) {
                                                        $payment_done = array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);
                                                    }
                                                    $entry_values['payment_done'] = '1';
                                                    $entry_values['arm_entry_id'] = $entry_id;
                                                    $entry_values['arm_update_user_from_profile'] = 0;
                                                    $user_id = $arm_member_forms->arm_register_new_member($entry_values, $armform);
                                                    if (is_numeric($user_id) && !is_array($user_id)) {
                                                        if ($arm_payment_type == 'subscription') {


                                                            $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                                                            $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                                            $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                                            $userPlanData = shortcode_atts($defaultPlanData, $userPlanDatameta);

                                                            $userPlanData['arm_subscr_id'] = $arm_token;
                                                            update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                            $pgateway = 'paypal';
                                                            $arm_manage_coupons->arm_coupon_apply_to_subscription($user_id, $payment_log_id, $pgateway, $userPlanData);
                                                        }
                                                        update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                                        /**
                                                         * Send Email Notification for Successful Payment
                                                         */
                                                        //$arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => 'new_subscription'));
                                                    }
                                                } else {

                                                    
                                                    $user_id = $user_info->ID;
                                                    if (!empty($user_id)) {

                                                        $userPlanData['arm_payment_mode'] = $entry_values['arm_selected_payment_mode'];
                                                        $userPlanData['arm_payment_cycle'] = $entry_values['arm_selected_payment_cycle'];
                                                        $is_update_plan = true;

                                                        $arm_is_paid_post = false;
                                                        if( !empty( $entry_values['arm_is_post_entry'] ) && !empty( $entry_values['arm_paid_post_id'] ) ){
                                                            $arm_is_paid_post = true;
                                                        }

                                                        if (!$is_multiple_membership_feature->isMultipleMembershipFeature && !$arm_is_paid_post ) {

                                                            $now = current_time('mysql');
                                                            $arm_last_payment_status = $wpdb->get_var($wpdb->prepare("SELECT `arm_transaction_status` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`=%d AND `arm_plan_id`=%d AND `arm_created_date`<=%s ORDER BY `arm_log_id` DESC LIMIT 0,1", $user_id, $entry_plan, $now));

                                                            
                                                            $old_plan_ids = get_user_meta($user_id, 'arm_user_plan_ids', true);
                                                            $old_plan_ids = !empty($old_plan_ids) ? $old_plan_ids : array();
                                                            if (!empty($old_plan_ids)) {
                                                                $old_plan_id = isset($old_plan_ids[0]) ? $old_plan_id[0] : 0;
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
                                                        }

                                                        update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                                        $userPlanData['arm_user_gateway'] = 'paypal';

                                                        if (!empty($arm_token)) {
                                                            $userPlanData['arm_subscr_id'] = $arm_token;
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
                                                        $is_log = true;
                                                    }
                                                }
                                                $paypalLog['txn_id'] = '-';
                                                $paypalLog['payment_status'] = 'success';
                                                $paypalLog['payment_type'] = 'subscr_signup';
                                                $paypalLog['mc_gross'] = $_POST['mc_amount1'];
                                                $paypalLog['payment_date'] = $_POST['subscr_date'];
                                            }
                                            break;
                                        case 'subscr_payment':
                                        case 'recurring_payment':
                                        case 'web_accept':
                                          
                                            $form_id = $entry_data['arm_form_id'];
                                            $armform = new ARM_Form('id', $form_id);
                                            $user_info = get_user_by('email', $entry_email);
                                            $new_plan = new ARM_Plan($entry_plan);
                                            $plan_action = "new_subscription";
                                            if ($new_plan->is_recurring()) {
                                                $plan_action = "renew_subscription";
                                                if (in_array($entry_plan, $arm_user_old_plan)) {
                                                    $is_recurring_payment = $arm_subscription_plans->arm_is_recurring_payment_of_user($user_id, $entry_plan, $payment_mode);
                                                    if ($is_recurring_payment) {
                                                        $plan_action = 'recurring_payment';
                                                        $planData = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                                        $oldPlanDetail = $planData['arm_current_plan_detail'];
                                                        if (!empty($oldPlanDetail)) {
                                                            $plan = new ARM_Plan(0);
                                                            $plan->init((object) $oldPlanDetail);
                                                            $plan_data = $plan->prepare_recurring_data($payment_cycle);
                                                            $extraParam['plan_amount'] = $plan_data['amount'];
                                                        }
                                                    } else {
                                                        $plan_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                        $extraParam['plan_amount'] = $plan_data['amount'];
                                                    }
                                                } else {
                                                    $plan_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                    $extraParam['plan_amount'] = $plan_data['amount'];
                                                }
                                            } else {
                                               
                                                $extraParam['plan_amount'] = $new_plan->amount;
                                            }
                                            $couponCode = isset($entry_values['arm_coupon_code']) ? $entry_values['arm_coupon_code'] : '';
                                            $arm_coupon_discount = 0;
                                            if (!empty($couponCode)) {
                                                $couponApply = $arm_manage_coupons->arm_apply_coupon_code($couponCode, $new_plan, $setup_id, $payment_cycle, $arm_user_old_plan);
                                                $coupon_amount = isset($couponApply['coupon_amt']) ? $couponApply['coupon_amt'] : 0;
                                                $arm_coupon_on_each_subscriptions = isset($couponApply['arm_coupon_on_each_subscriptions']) ? $couponApply['arm_coupon_on_each_subscriptions'] : 0;


                                                if ($coupon_amount != 0) {
                                                    $extraParam['coupon'] = array(
                                                        'coupon_code' => $couponCode,
                                                        'amount' => $coupon_amount,
                                                        'arm_coupon_on_each_subscriptions' => $arm_coupon_on_each_subscriptions,
                                                    );

                                                    $arm_coupon_discount = $couponApply['discount'];
                                                    $global_currency = $arm_payment_gateways->arm_get_global_currency();
                                                    $arm_coupon_discount_type = ($couponApply['discount_type'] != 'percentage') ? $global_currency : "%";
                                                    $paypalLog['coupon_code'] = $couponCode;
                                                    $paypalLog['arm_coupon_discount'] = $arm_coupon_discount;
                                                    $paypalLog['arm_coupon_discount_type'] = $arm_coupon_discount_type;
                                                    $paypalLog['arm_coupon_on_each_subscriptions'] = $arm_coupon_on_each_subscriptions;
                                                }
                                            }

                                            $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                                            $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                            $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                            $userPlanData = shortcode_atts($defaultPlanData, $userPlanDatameta);

                                            if (!$user_info && in_array($armform->type, array('registration'))) {
                                                
                                                /* Coupon Details */

                                                if($new_plan->is_recurring()){
                                                    $recurring_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                    if (!empty($recurring_data['trial'])) {
                                                        $extraParam['trial'] = array(
                                                            'amount' => $recurring_data['trial']['amount'],
                                                            'period' => $recurring_data['trial']['period'],
                                                            'interval' => $recurring_data['trial']['interval'],
                                                          
                                                        );
                                                        $extraParam['arm_is_trial'] = '1';
                                                        $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                        

                                                    }

                                                    if( $arm_coupon_discount > 0){
                                                        $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                    }
                                                }

                                                $payment_log_id = self::arm_store_paypal_log($paypalLog, 0, $entry_plan, $extraParam, $payment_mode);
                                                $payment_done = array();
                                                if ($payment_log_id) {
                                                    $payment_done = array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);
                                                }
                                                $entry_values['payment_done'] = '1';
                                                $entry_values['arm_entry_id'] = $entry_id;
                                                $entry_values['arm_update_user_from_profile'] = 0;
                                                $user_id = $arm_member_forms->arm_register_new_member($entry_values, $armform);

                                                if (is_numeric($user_id) && !is_array($user_id)) {
                                                    if ($arm_payment_type == 'subscription') {

                                                        $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                                        $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                                        $userPlanData = shortcode_atts($defaultPlanData, $userPlanDatameta);

                                                        $userPlanData['arm_subscr_id'] = $arm_token;
                                                        update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);

                                                        $pgateway = 'paypal';
                                                        $arm_manage_coupons->arm_coupon_apply_to_subscription($user_id, $payment_log_id, $pgateway, $userPlanData);
                                                    }
                                                    update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                                    /**
                                                     * Send Email Notification for Successful Payment
                                                     */
                                                    //$arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => 'new_subscription'));
                                                }
                                            } else {

                                                $user_id = $user_info->ID;
                                                if (!empty($user_id)) {
                                                    $arm_is_paid_post = false;
                                                    if( !empty( $entry_values['arm_is_post_entry'] ) && !empty( $entry_values['arm_paid_post_id'] ) ){
                                                        $arm_is_paid_post = true;
                                                    }
                                                    if (!$is_multiple_membership_feature->isMultipleMembershipFeature && !$arm_is_paid_post) {
                                                        
                                                        $old_plan_ids = get_user_meta($user_id, 'arm_user_plan_ids', true);
                                                        $old_plan_id = isset($old_plan_ids[0]) ? $old_plan_ids[0] : 0;
                                                        $oldPlanDetail = array();
                                                        $old_subscription_id = '';
                                                        if (!empty($old_plan_id)) {
                                                            $oldPlanData = get_user_meta($user_id, 'arm_user_plan_' . $old_plan_id, true);
                                                            $oldPlanData = !empty($oldPlanData) ? $oldPlanData : array();
                                                            $oldPlanData = shortcode_atts($defaultPlanData, $oldPlanData);
                                                            $oldPlanDetail = $oldPlanData['arm_current_plan_detail'];
                                                            $subscr_effective = $oldPlanData['arm_expire_plan'];
                                                            $old_subscription_id = $oldPlanData['arm_subscr_id'];
                                                        }
                                                        
                                                        $arm_user_old_plan_details = (isset($userPlanData['arm_current_plan_detail']) && !empty($userPlanData['arm_current_plan_detail'])) ? $userPlanData['arm_current_plan_detail'] : array();
                                                        $arm_user_old_plan_details['arm_user_old_payment_mode'] = $userPlanData['arm_payment_mode'];

                                                        if (!empty($old_subscription_id) && $entry_values['arm_selected_payment_mode'] == 'auto_debit_subscription' && $arm_token == $old_subscription_id) {

                                                            
                                                            $arm_next_due_payment_date = $userPlanData['arm_next_due_payment'];
                                                            if (!empty($arm_next_due_payment_date)) {
                                                                if (strtotime(current_time('mysql')) >= $arm_next_due_payment_date) {
                                                                    $arm_user_completed_recurrence = $userPlanData['arm_completed_recurring'];
                                                                    $arm_user_completed_recurrence++;
                                                                    $userPlanData['arm_completed_recurring'] = $arm_user_completed_recurrence;
                                                                    update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                                    $arm_next_payment_date = $arm_members_class->arm_get_next_due_date($user_id, $entry_plan, false, $payment_cycle);
                                                                    if ($arm_next_payment_date != '') {
                                                                        $userPlanData['arm_next_due_payment'] = $arm_next_payment_date;
                                                                        update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                                    }

                                                                   
                                                                }
                                                                else{

                                                                        $now = current_time('mysql');
                                                                        $arm_last_payment_status = $wpdb->get_var($wpdb->prepare("SELECT `arm_transaction_status` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`=%d AND `arm_plan_id`=%d AND `arm_created_date`<=%s ORDER BY `arm_log_id` DESC LIMIT 0,1", $user_id, $entry_plan, $now));

                                                                           if(in_array($arm_last_payment_status, array('success','pending'))){
                                                                            $arm_user_completed_recurrence = $userPlanData['arm_completed_recurring'];
                                                                                $arm_user_completed_recurrence++;
                                                                                $userPlanData['arm_completed_recurring'] = $arm_user_completed_recurrence;
                                                                                update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                                                $arm_next_payment_date = $arm_members_class->arm_get_next_due_date($user_id, $entry_plan, false, $payment_cycle);
                                                                                if ($arm_next_payment_date != '') {
                                                                                    $userPlanData['arm_next_due_payment'] = $arm_next_payment_date;
                                                                                    update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                                                }
                                                                            
                                                                        }
                                                                    }
                                                            }

                                                            $suspended_plan_ids = get_user_meta($user_id, 'arm_user_suspended_plan_ids', true);
                                                            $suspended_plan_id = (isset($suspended_plan_ids) && !empty($suspended_plan_ids)) ? $suspended_plan_ids : array();

                                                            if (in_array($entry_plan, $suspended_plan_id)) {
                                                                unset($suspended_plan_id[array_search($entry_plan, $suspended_plan_id)]);
                                                                update_user_meta($user_id, 'arm_user_suspended_plan_ids', array_values($suspended_plan_id));
                                                            }
                                                        } else {

                                                            $now = current_time('mysql');
                                                            $arm_last_payment_status = $wpdb->get_var($wpdb->prepare("SELECT `arm_transaction_status` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`=%d AND `arm_plan_id`=%d AND `arm_created_date`<=%s ORDER BY `arm_log_id` DESC LIMIT 0,1", $user_id, $entry_plan, $now));
                                                            

                                                            $userPlanData['arm_current_plan_detail'] = $arm_user_old_plan_details;

                                                            $userPlanData['arm_payment_mode'] = $entry_values['arm_selected_payment_mode'];
                                                            $userPlanData['arm_payment_cycle'] = $entry_values['arm_selected_payment_cycle'];



                                                            if (!empty($oldPlanDetail)) {
                                                                $old_plan = new ARM_Plan(0);
                                                                $old_plan->init((object) $oldPlanDetail);
                                                            } else {
                                                                $old_plan = new ARM_Plan($old_plan_id);
                                                            }
                                                            $is_update_plan = true;
                                                            /* Coupon Details */

                                                            $recurring_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                            if (!empty($recurring_data['trial']) && empty($arm_user_old_plan)) {
                                                                $extraParam['trial'] = array(
                                                                    'amount' => $recurring_data['trial']['amount'],
                                                                    'period' => $recurring_data['trial']['period'],
                                                                    'interval' => $recurring_data['trial']['interval'],
                                                                   
                                                                );
                                                                $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                            }
                                                            if( $arm_coupon_discount > 0){
                                                                $extraParam['tax_amount'] = $arm_trial_tax_amount;
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

                                                            update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                                            $userPlanData['arm_user_gateway'] = 'paypal';

                                                            if (!empty($arm_token)) {
                                                                $userPlanData['arm_subscr_id'] = $arm_token;
                                                            }
                                                            update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                            if ($is_update_plan) {
                                                               
                                                                $arm_subscription_plans->arm_update_user_subscription($user_id, $entry_plan, '', true, $arm_last_payment_status);
                                                            } else {
                                                                
                                                                $arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'change_subscription');
                                                            }
                                                        }
                                                    } else {
                                                        
                                                        $old_plan_ids = get_user_meta($user_id, 'arm_user_plan_ids', true);

                                                        $oldPlanDetail = array();
                                                        $old_subscription_id = '';
                                                        
                                                        if (in_array($entry_plan, $old_plan_ids)) {

                                                           
                                                            $oldPlanData = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                                            $oldPlanDetail = $oldPlanData['arm_current_plan_detail'];
                                                            $subscr_effective = $oldPlanData['arm_expire_plan'];
                                                            $old_subscription_id = $oldPlanData['arm_subscr_id'];
                                                            
                                                            $arm_user_old_plan_details = (isset($userPlanData['arm_current_plan_detail']) && !empty($userPlanData['arm_current_plan_detail'])) ? $userPlanData['arm_current_plan_detail'] : array();
                                                            $arm_user_old_plan_details['arm_user_old_payment_mode'] = $userPlanData['arm_payment_mode'];
                                                            if (!empty($old_subscription_id) && $entry_values['arm_selected_payment_mode'] == 'auto_debit_subscription' && $arm_token == $old_subscription_id) {
                                                               
                                                                $userPlanData['arm_payment_mode'] = $entry_values['arm_selected_payment_mode'];
                                                                $userPlanData['arm_payment_cycle'] = $entry_values['arm_selected_payment_cycle'];

                                                                $is_update_plan = true;
                                                                /* Coupon Details */

                                                                $recurring_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                                if (!empty($recurring_data['trial']) && empty($arm_user_old_plan)) {
                                                                    $extraParam['trial'] = array(
                                                                        'amount' => $recurring_data['trial']['amount'],
                                                                        'period' => $recurring_data['trial']['period'],
                                                                        'interval' => $recurring_data['trial']['interval'],
                                                                    );
                                                                    $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                                }

                                                                if( $arm_coupon_discount > 0){
                                                                    $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                                }

                                                                update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                                                $userPlanData['arm_user_gateway'] = 'paypal';

                                                                if (!empty($arm_token)) {
                                                                    $userPlanData['arm_subscr_id'] = $arm_token;
                                                                }
                                                                update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                                if ($is_update_plan) {
                                                                    $arm_subscription_plans->arm_update_user_subscription($user_id, $entry_plan);
                                                                } else {
                                                                    $arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'new_subscription');
                                                                }
                                                            } else {
                                                                $now = current_time('mysql');
                                                                $arm_last_payment_status = $wpdb->get_var($wpdb->prepare("SELECT `arm_transaction_status` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`=%d AND `arm_plan_id`=%d AND `arm_created_date`<=%s ORDER BY `arm_log_id` DESC LIMIT 0,1", $user_id, $entry_plan, $now));
                                                                

                                                                $userPlanData['arm_current_plan_detail'] = $arm_user_old_plan_details;

                                                                $userPlanData['arm_payment_mode'] = $entry_values['arm_selected_payment_mode'];
                                                                $userPlanData['arm_payment_cycle'] = $entry_values['arm_selected_payment_cycle'];



                                                                if (!empty($oldPlanDetail)) {
                                                                    $old_plan = new ARM_Plan(0);
                                                                    $old_plan->init((object) $oldPlanDetail);
                                                                } else {
                                                                    $old_plan = new ARM_Plan($old_plan_id);
                                                                }
                                                                $is_update_plan = true;
                                                                /* Coupon Details */

                                                                $recurring_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                                if (!empty($recurring_data['trial']) && empty($arm_user_old_plan)) {
                                                                    $extraParam['trial'] = array(
                                                                        'amount' => $recurring_data['trial']['amount'],
                                                                        'period' => $recurring_data['trial']['period'],
                                                                        'interval' => $recurring_data['trial']['interval'],
                                                                       
                                                                    );
                                                                    $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                                }
                                                                if( $arm_coupon_discount > 0){
                                                                    $extraParam['tax_amount'] = $arm_trial_tax_amount;
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

                                                                update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                                                $userPlanData['arm_user_gateway'] = 'paypal';

                                                                if (!empty($arm_token)) {
                                                                    $userPlanData['arm_subscr_id'] = $arm_token;
                                                                }
                                                                update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                                if ($is_update_plan) {
                                                                   
                                                                    $arm_subscription_plans->arm_update_user_subscription($user_id, $entry_plan, '', true, $arm_last_payment_status);
                                                                } else {
                                                                    
                                                                    $arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'change_subscription');
                                                                }
                                                                $suspended_plan_ids = get_user_meta($user_id, 'arm_user_suspended_plan_ids', true);
                                                                $suspended_plan_id = (isset($suspended_plan_ids) && !empty($suspended_plan_ids)) ? $suspended_plan_ids : array();

                                                                if (in_array($entry_plan, $suspended_plan_id)) {
                                                                    unset($suspended_plan_id[array_search($entry_plan, $suspended_plan_id)]);
                                                                    update_user_meta($user_id, 'arm_user_suspended_plan_ids', array_values($suspended_plan_id));
                                                                }
                                                            }
                                                        } else {

                                                            
                                                            $userPlanData['arm_payment_mode'] = $entry_values['arm_selected_payment_mode'];
                                                            $userPlanData['arm_payment_cycle'] = $entry_values['arm_selected_payment_cycle'];
                                                            $is_update_plan = true;
                                                            /* Coupon Details */
                                                            $recurring_data = $new_plan->prepare_recurring_data($payment_cycle);
                                                            if (!empty($recurring_data['trial']) && empty($arm_user_old_plan)) {
                                                                $extraParam['trial'] = array(
                                                                    'amount' => $recurring_data['trial']['amount'],
                                                                    'period' => $recurring_data['trial']['period'],
                                                                    'interval' => $recurring_data['trial']['interval'],
                                                                   
                                                                );
                                                                $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                            }
                                                            if( $arm_coupon_discount > 0){
                                                                    $extraParam['tax_amount'] = $arm_trial_tax_amount;
                                                                }
                                                            update_user_meta($user_id, 'arm_entry_id', $entry_id);
                                                            $userPlanData['arm_user_gateway'] = 'paypal';

                                                            if (!empty($arm_token)) {
                                                                $userPlanData['arm_subscr_id'] = $arm_token;
                                                            }
                                                            update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $userPlanData);
                                                            if ($is_update_plan) {
                                                               
                                                                $arm_subscription_plans->arm_update_user_subscription($user_id, $entry_plan);
                                                            } else {
                                                                
                                                                $arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'new_subscription');
                                                            }
                                                        }
                                                    }
                                                    $is_log = true;
                                                }
                                            }
                                            /*
                                            if($_POST['txn_type'] == 'subscr_payment' || $_POST['txn_type'] ==  'recurring_payment' || $plan_action=="recurring_payment")
                                            {
                                                do_action('arm_after_recurring_payment_success_outside', $user_id, $entry_plan, 'paypal', $entry_values['arm_selected_payment_mode']);
                                            }
                                            */
                                            break;
                                        case 'subscr_cancel':
                                        case 'recurring_payment_profile_cancel':
                                           
                                            $user_info = get_user_by('email', $entry_email);
                                            $user_id = $user_info->ID;

                                            $is_log = true;
                                            $paypalLog['mc_gross'] = (isset($_POST['amount3']) && !empty($_POST['amount3'])) ? $_POST['amount3'] : 0;
                                            $arm_transaction_id = $wpdb->get_var("SELECT `arm_transaction_id` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`='" . $user_id . "' AND `arm_token`='" . $arm_token . "' AND `arm_payment_gateway` = 'paypal'");
                                            $paypalLog['txn_id'] = $arm_transaction_id;
                                            $entry_values['arm_coupon_code'] = '';
                                            $paypalLog['arm_coupon_code'] = '';
                                            $paypalLog['payment_status'] = 'cancelled';
                                            $paypalLog['payment_type'] = 'subscr_cancel';


                                            $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                                            $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                            $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                            $planData = shortcode_atts($defaultPlanData, $userPlanDatameta);

                                            $planDetail = $planData['arm_current_plan_detail'];
                                            if (!empty($planDetail)) {
                                                $plan = new ARM_Plan(0);
                                                $plan->init((object) $planDetail);
                                            } else {
                                                $plan = new ARM_Plan($entry_plan);
                                            }
					    
					    $plan_cycle = isset($planData['arm_payment_cycle']) ? $planData['arm_payment_cycle'] : '';
	                                    $paly_cycle_data = $plan->prepare_recurring_data($plan_cycle);

	                                    if($paly_cycle_data['rec_time'] != 'infinite' || $plan->options['cancel_plan_action'] != "on_expire")
	                                    {
                                            	if (!empty($planData['arm_subscr_id'])) 
						{

                                                	$arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'cancel_subscription');

                                                
                                                    do_action('arm_cancel_subscription', $user_id, $entry_plan);
                                                    $arm_subscription_plans->arm_clear_user_plan_detail($user_id, $entry_plan);
                                                

                                                	$cancel_plan_act = isset($plan->options['cancel_action']) ? $plan->options['cancel_action'] : 'block';
                                                	if ($arm_subscription_plans->isPlanExist($cancel_plan_act)) {
                                                    		$arm_members_class->arm_new_plan_assigned_by_system($cancel_plan_act, $entry_plan, $user_id);
                                                	} else {
                                                	}
                                            	}
                                            	$arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => 'on_cancel_subscription'));

                                            	do_action('arm_after_recurring_payment_cancelled_outside', $user_id, $entry_plan, 'paypal');
					    }
                                            break;
                                        case 'subscr_eot':
                                        case 'recurring_payment_expired':
                                        case 'subscr_failed':
                                        case 'recurring_payment_failed':
                                        case 'recurring_payment_suspended':
                                        case 'recurring_payment_suspended_due_to_max_failed_payment':
                                            
                                            $entry_values['arm_coupon_code'] = '';
                                            $paypalLog['arm_coupon_code'] = '';
                                            $user_info = get_user_by('email', $entry_email);
                                            $user_id = $user_info->ID;
                                            $plan_ids = get_user_meta($user_id, 'arm_user_plan_ids', true);
                                            if (!empty($plan_ids) && is_array($plan_ids)) {
                                                foreach ($plan_ids as $plan_id) {
                                                    $planData = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                                                    if (!empty($planData)) {
                                                        $subscr_id = $planData['arm_subscr_id'];
                                                        if ($plan_id == $entry_plan && $subscr_id == $arm_token) {
                                                            if (in_array($_POST['txn_type'], array('subscr_eot', 'recurring_payment_expired'))) {/*
                                                              $action = "eot";
                                                              $is_log = true;
                                                              $paypalLog['txn_id'] = '-';
                                                              $paypalLog['payment_status'] = 'expired';
                                                              $paypalLog['payment_type'] = 'subscr_eot';
                                                              $paypalLog['payment_date'] = current_time('mysql');
                                                              $arm_subscription_plans->arm_user_plan_status_action(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => "eot"));
                                                              do_action('arm_after_recurring_payment_completed_outside', $user_id, $plan_id, 'paypal'); */
                                                            } else {

                                                                $action = "failed_payment";
                                                                $is_log = true;
                                                                $extraParam['error'] = isset($_POST['txn_type']) ? $_POST['txn_type'] : '';
                                                                $paypalLog['mc_gross'] = 0;
                                                                $paypalLog['txn_id'] = '-';
                                                                $paypalLog['payment_status'] = 'failed';
                                                                $paypalLog['payment_type'] = 'subscr_failed';
                                                                $paypalLog['payment_date'] = current_time('mysql');
                                                                $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => 'failed_payment'));
                                                                $arm_subscription_plans->arm_user_plan_status_action(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => "failed_payment"));
                                                                do_action('arm_after_recurring_payment_stopped_outside', $user_id, $plan_id, 'paypal');
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            break;
                                        default:
                                            
                                            do_action('arm_handle_paypal_unknown_error_from_outside', $entry_data['arm_user_id'], $entry_data['arm_plan_id'], $_POST['txn_type']);
                                            break;
                                    }
                                    if ($is_log && !empty($user_id) && $user_id != 0) {

                                        

                                        $payment_log_id = self::arm_store_paypal_log($paypalLog, $user_id, $entry_plan, $extraParam, $payment_mode);
                                        
                                        $userPlanData = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                        $pgateway = 'paypal';
                                        $arm_manage_coupons->arm_coupon_apply_to_subscription($user_id, $payment_log_id, $pgateway, $userPlanData);

                                    } //-->End `($is_log && !empty($user_id) && $user_id != 0)`
                                }//For Writing Response
                               
                            }//-->End `(!empty($entry_data))`
                        }//-->End `(!is_wp_error($response) and $response['body'] == 'VERIFIED')`
                    }
                }//-->End `(!empty($_POST['txn_id']) || !empty($_POST['subscr_id']))`
            }
            return;
        }

        function arm_cancel_paypal_subscription($user_id, $plan_id) {
            global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans, $arm_member_forms, $arm_payment_gateways, $arm_manage_communication, $arm_subscription_cancel_msg;
            if (!empty($user_id) && $user_id != 0 && !empty($plan_id) && $plan_id != 0) {
                $user_detail = get_userdata($user_id);
                $payer_email = $user_detail->user_email;

                $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                $planData = shortcode_atts($defaultPlanData, $userPlanDatameta);

                $subscr_id = '';
                $user_payment_gateway = '';
                if (!empty($planData)) {
                    $user_payment_gateway = $planData['arm_user_gateway'];
                    $subscr_id = $planData['arm_subscr_id'];
                }
                $payment_mode = $planData['arm_payment_mode'];
                if (!empty($subscr_id) && strtolower($user_payment_gateway) == 'paypal') {

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



                    if ($payment_mode == 'auto_debit_subscription') {
                        $this->arm_immediate_cancel_paypal_payment($subscr_id, $user_id, $plan_id, $planData);

                        if(!empty($arm_subscription_cancel_msg))
                        {
                            return;
                        }

                    } else {

                        $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $plan_id, 'user_id' => $user_id, 'action' => 'on_cancel_subscription'));
                        
                        $payment_data = array(
                            'arm_user_id' => $user_id,
                            'arm_first_name' => $user_detail->first_name,
                            'arm_last_name' => $user_detail->last_name,
                            'arm_plan_id' => (!empty($plan_id) ? $plan_id : 0),
                            'arm_payment_gateway' => 'paypal',
                            'arm_payment_type' => 'subscription',
                            'arm_token' => $subscr_id,
                            'arm_payer_email' => $payer_email,
                            'arm_receiver_email' => '',
                            'arm_transaction_id' => $subscr_id,
                            'arm_transaction_payment_type' => 'subscription',
                            'arm_transaction_status' => 'canceled',
                            'arm_payment_mode' => $payment_mode,
                            'arm_payment_date' => current_time('mysql'),
                            'arm_amount' => $amount,
                            
                            'arm_coupon_code' => '',
                            'arm_coupon_discount' => 0,
                            'arm_coupon_discount_type' => '',
                            'arm_response_text' => '',
                            'arm_is_trial' => '0',
                            'arm_created_date' => current_time('mysql')
                        );
                        $payment_log_id = $arm_payment_gateways->arm_save_payment_log($payment_data);
                        return;
                    }
                }//End `(!empty($subscr_id) && strtolower($user_payment_gateway)=='paypal')`
            }//End `(!empty($user_id) && $user_id != 0 && !empty($plan_id) && $plan_id != 0)`
        }

        function arm_store_paypal_log($paypal_response = '', $user_id = 0, $plan_id = 0, $extraVars = array(), $payment_mode = 'manual_subscription') {
            global $wpdb, $ARMember, $arm_global_settings, $arm_member_forms, $arm_payment_gateways;


            if (!empty($paypal_response)) {
                $arm_first_name=(isset($paypal_response['first_name']))?$paypal_response['first_name']:'';
                $arm_last_name=(isset($paypal_response['last_name']))?$paypal_response['last_name']:'';
                if($user_id){
                    $user_detail = get_userdata($user_id);
                    $arm_first_name=$user_detail->first_name;
                    $arm_last_name=$user_detail->last_name;
                }
                $payment_data = array(
                    'arm_user_id' => $user_id,
                    'arm_first_name' => $arm_first_name,
                    'arm_last_name' => $arm_last_name,
                    'arm_plan_id' => (!empty($plan_id) ? $plan_id : 0),
                    'arm_payment_gateway' => 'paypal',
                    'arm_payment_type' => $paypal_response['arm_payment_type'],
                    'arm_token' => $paypal_response['subscr_id'],
                    'arm_payer_email' => $paypal_response['payer_email'],
                    'arm_receiver_email' => $paypal_response['receiver_email'],
                    'arm_transaction_id' => $paypal_response['txn_id'],
                    'arm_transaction_payment_type' => $paypal_response['payment_type'],
                    'arm_transaction_status' => $paypal_response['payment_status'],
                    'arm_payment_mode' => $payment_mode,
                    'arm_payment_date' => date('Y-m-d H:i:s', strtotime($paypal_response['payment_date'])),
                    'arm_amount' => $paypal_response['mc_gross'],
                    'arm_currency' => $paypal_response['mc_currency'],
                    'arm_coupon_code' => $paypal_response['arm_coupon_code'],
                    'arm_coupon_discount' => (isset($paypal_response['arm_coupon_discount']) && !empty($paypal_response['arm_coupon_discount'])) ? $paypal_response['arm_coupon_discount'] : 0,
                    'arm_coupon_discount_type' => isset($paypal_response['arm_coupon_discount_type']) ? $paypal_response['arm_coupon_discount_type'] : '',
                    'arm_response_text' => utf8_encode(maybe_serialize($paypal_response)),
                    'arm_extra_vars' => maybe_serialize($extraVars),
                    'arm_is_trial' => $extraVars['arm_is_trial'],
                    'arm_created_date' => current_time('mysql'),
                    'arm_coupon_on_each_subscriptions' => @$paypal_response['arm_coupon_on_each_subscriptions'],
                );

                $payment_log_id = $arm_payment_gateways->arm_save_payment_log($payment_data);
                return $payment_log_id;
            }
            return false;
        }

        function arm_update_new_subscr_gateway_outside_func($payment_gateways = array()) {
            global $payment_done;
            if (isset($payment_done['zero_amount_paid']) && $payment_done['zero_amount_paid'] == true) {
                array_push($payment_gateways, 'paypal');
            }
            return $payment_gateways;
        }

        function arm_update_user_meta_after_renew_outside_func($user_id, $log_detail, $plan_id, $payment_gateway) {
            global $payment_done;
            if (isset($payment_don['zero_amount_paid']) && $payment_done['zero_amount_paid'] == true) {
                
            }
        }

        function arm_change_pending_gateway_outside($user_pending_pgway, $plan_ID, $user_id) {
            global $is_free_manual, $ARMember;
            /*if ($is_free_manual) {
                $key = array_search('paypal', $user_pending_pgway);
                unset($user_pending_pgway[$key]);
            }*/
            $key = array_search('paypal', $user_pending_pgway);
            unset($user_pending_pgway[$key]);
            return $user_pending_pgway;
        }

    }

}
global $arm_paypal;
$arm_paypal = new ARM_Paypal();
