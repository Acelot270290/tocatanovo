<?php

if (!class_exists('ARM_Stripe')) {

    class ARM_Stripe {

        var $arm_stripe_api_version;
        function __construct() {
            $this->arm_stripe_api_version = "2020-08-27";
            add_action('arm_payment_gateway_validation_from_setup', array(&$this, 'arm_payment_gateway_form_submit_action'), 10, 4);
            //add_action('wp', array(&$this, 'arm_StripeEventListener'), 5);
            add_action('arm_cancel_subscription_gateway_action', array(&$this, 'arm_cancel_stripe_subscription'), 10, 2);

            add_action('arm_saved_subscription_plan', array(&$this, 'arm_update_stripe_configuration_setup'), 10, 2);
        }

        function arm_prepare_stripe_charge_details($request_data = array(), $plan = object, $setup_id = 0, $payment_cycle = 0, $plan_action = '') {
            global $wpdb, $ARMember, $arm_global_settings, $payment_done, $arm_payment_gateways, $arm_membership_setup, $arm_manage_coupons, $arm_transaction;
            $charge_details = array();
           
            if (!empty($request_data)) {
                $currency = $arm_payment_gateways->arm_get_global_currency();
                $zero_demial_currencies = $arm_payment_gateways->arm_stripe_zero_decimal_currency_array();
                $entry_id = $request_data['entry_id'];
                $entry_email = $request_data['entry_email'];
                $stripe_card_detail = isset( $request_data['stripe'] ) ? $request_data['stripe'] : array();
                $card_holder_name = isset( $stripe_card_detail['card_holder_name'] ) ? $stripe_card_detail['card_holder_name'] : '';
                $card_number = isset( $stripe_card_detail['card_number'] ) ? $stripe_card_detail['card_number'] : '';
                $exp_month = isset( $stripe_card_detail['exp_month'] ) ? $stripe_card_detail['exp_month'] : '';
                $exp_year = isset( $stripe_card_detail['exp_year'] ) ? $stripe_card_detail['exp_year'] : '';
                $cvc = isset( $stripe_card_detail['cvc'] ) ? $stripe_card_detail['cvc'] : '';
                $arm_is_trial = '0';
                $formSlug = isset($request_data['arm_action']) ? $request_data['arm_action'] : '';
                $arm_user_old_plan = $request_data['arm_user_old_plan_ids'];

                $form = new ARM_Form('slug', $formSlug);
                if ($plan->is_recurring()) {
                   
                    $recurring_data = $plan->prepare_recurring_data($payment_cycle);
                    $amount = str_replace(",", "", $recurring_data['amount']);

                    $payment_mode_ = !empty($request_data['arm_selected_payment_mode']) ? $request_data['arm_selected_payment_mode'] : 'manual_subscription';
                    if(isset($request_data['arm_payment_mode']['stripe'])){
                        $payment_mode_ = !empty($request_data['arm_payment_mode']['stripe']) ? $request_data['arm_payment_mode']['stripe'] : 'manual_subscription';
                    }
                    else{
                        $setup_data = $arm_membership_setup->arm_get_membership_setup($setup_id);
                        if (!empty($setup_data) && !empty($setup_data['setup_modules']['modules'])) {
                            $setup_modules = $setup_data['setup_modules'];
                            $modules = $setup_modules['modules'];
                            $payment_mode_ = $modules['payment_mode']['stripe'];
                        }
                    }
                    $plan_id = (!empty($request_data['subscription_plan'])) ? $request_data['subscription_plan'] : 0;
                    if ($plan_id == 0) {
                        $plan_id = (!empty($request_data['_subscription_plan'])) ? $request_data['_subscription_plan'] : 0;
                    }
                    $payment_mode = 'manual_subscription';
                    $c_mpayment_mode = "";
                    if(isset($request_data['arm_pay_thgough_mpayment']) && $request_data['arm_plan_type']=='recurring' && is_user_logged_in())
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
                            $payment_mode = !empty($request_data['arm_selected_payment_mode']) ? $request_data['arm_selected_payment_mode'] : 'manual_subscription';
                        } else {
                            $payment_mode = $payment_mode_;
                        }
                    }
                } else {
                    
                    $amount = str_replace(",", "", $plan->amount);
                    $payment_mode = '';
                }

                $amount = number_format((float) $amount, 2, '.', '');

                $maskCCNum = $arm_transaction->arm_mask_credit_card_number($card_number);
                $extraParam = array('card_number' => $maskCCNum, 'plan_amount' => $amount, 'paid_amount' => $amount);
                /* Coupon Details */
                $discount_amt = $coupon_amount = $arm_coupon_on_each_subscriptions = $arm_coupon_discount = 0;
                $coupon_code = $arm_coupon_discount_type_default = $arm_coupon_discount_type = '';

                if ($arm_manage_coupons->isCouponFeature && isset($request_data['arm_coupon_code']) && !empty($request_data['arm_coupon_code'])) {
                    $couponApply = $arm_manage_coupons->arm_apply_coupon_code($request_data['arm_coupon_code'], $plan, $setup_id, $payment_cycle, $arm_user_old_plan);

                    if($couponApply["status"] == "success") {
                        $coupon_code = $request_data['arm_coupon_code'];
                        $coupon_amount = isset($couponApply['coupon_amt']) ? $couponApply['coupon_amt'] : 0;
                        $coupon_amount = str_replace(",", "", $coupon_amount);

                        $discount_amt = isset($couponApply['total_amt']) ? $couponApply['total_amt'] : $amount;
                        $discount_amt = str_replace(",", "", $discount_amt);

                        $arm_coupon_on_each_subscriptions = isset($couponApply['arm_coupon_on_each_subscriptions']) ? $couponApply['arm_coupon_on_each_subscriptions'] : '0';
                        $arm_coupon_discount_type_default = isset($couponApply['discount_type']) ? $couponApply['discount_type'] : "";

                        $arm_coupon_discount = (isset($couponApply['discount']) && !empty($couponApply['discount'])) ? $couponApply['discount'] : 0;
                        $global_currency = $arm_payment_gateways->arm_get_global_currency();
                        $arm_coupon_discount_type = ($couponApply['discount_type'] != 'percentage') ? $global_currency : "%";
                        $extraParam['coupon'] = array(
                            'coupon_code' => $request_data['arm_coupon_code'],
                            'amount' => $coupon_amount,
                        );

                        $charge_details['coupon_details'] = array (
                            'coupon_code' => $request_data['arm_coupon_code'],
                            'arm_coupon_discount' => $arm_coupon_discount,
                            'arm_coupon_discount_type' => $arm_coupon_discount_type,
                            'arm_coupon_discount_type_default' => $arm_coupon_discount_type_default,
                            'arm_coupon_on_each_subscriptions' => $arm_coupon_on_each_subscriptions
                        );
                    }
                }
                else {
                    $request_data['arm_coupon_code'] = '';
                }

                if ($payment_mode == 'auto_debit_subscription') {
                    /* Recurring Options */
                    
                    $stripePlanID = $request_data['stripePlanID'];

                    if (!empty($stripePlanID)) {
                        $charge_details['plan'] = $stripePlanID;
                    }

                    if( $amount > 0 && $arm_coupon_on_each_subscriptions == 1 ) {                       

                        if( $couponApply['discount_type'] == "fixed" ) {
                            $amount = $amount - $couponApply['discount'];
                        }
                        else if($couponApply['discount_type'] == "percentage") {
                            $arm_amount_to_minus = ( $amount * $couponApply['discount'] ) / 100;
                            $amount = $amount - $arm_amount_to_minus;
                        }

                        if( $amount < 0 ) {
                            $amount = 0;
                        }
                    }

                    if($request_data['tax_percentage'] > 0) {
                        $tax_amount = ($request_data['tax_percentage'] * $amount)/100;
                        $tax_amount= number_format((float) $tax_amount, 2, '.', '');
                        $amount = $amount + $tax_amount;
                        $tax_amount = $arm_payment_gateways->arm_amount_set_separator($currency, $tax_amount);
                        $charge_details['tax_amount'] = $tax_amount;
                    }

                    $amount = number_format((float) $amount, 2, '.', '');

                    $extraParam['paid_amount'] = $amount;

                    if ($plan->has_trial_period() && $plan_action == 'new_subscription') {
                        if(empty($arm_coupon_on_each_subscriptions))
                        {
                            unset($charge_details['coupon_details']);
                        }
                    }
                } else if ($payment_mode == 'manual_subscription') {
                    $rec_opt = $plan->prepare_recurring_data($payment_cycle);
                    $allow_trial = true;
                    if (is_user_logged_in()) {
                        $user_id = get_current_user_id();
                        $user_plan = get_user_meta($user_id, 'arm_user_plan_ids', true);

                        if (!empty($user_plan)) {
                            $allow_trial = false;
                        }
                    }
                    if ($plan->has_trial_period() && $allow_trial) {
                        $arm_is_trial = '1';
                        $amount = $rec_opt['trial']['amount'];

                        $trial_amount = $rec_opt['trial']['amount'];
                        $trial_period = $rec_opt['trial']['period'];
                        $trial_interval = $rec_opt['trial']['interval'];
                        $extraParam['trial'] = array(
                            'amount' => $trial_amount,
                            'period' => $trial_period,
                            'interval' => $trial_interval,
                        );
                    }
                    if (!empty($coupon_amount) && $coupon_amount > 0) {
                        $amount = $discount_amt;
                    }

                    $amount = str_replace(",", "", $amount);

                    if($request_data['tax_percentage'] > 0){

                        $tax_amount = ($request_data['tax_percentage'] * $amount)/100;
                        $tax_amount= number_format((float) $tax_amount, 2, '.', '');
                        $amount = $amount + $tax_amount;
                        $tax_amount = $arm_payment_gateways->arm_amount_set_separator($currency, $tax_amount);
                        $charge_details['tax_amount'] = $tax_amount;
                    }

                    $amount = number_format((float) $amount, 2, '.', '');

                    $extraParam['paid_amount'] = $amount;

                    if (!empty($amount)) {
                        if (!in_array($currency, $zero_demial_currencies)) {
                            $amount = $amount * 100;
                        }
                        else{
                            $amount = number_format((float) $amount, 0);
                            $amount = str_replace(",", "", $amount);
                        }
                    }
                    else {
                        $amount = "0.00";
                    }

                    $charge_details['amount'] = $amount;
                    $charge_details['currency'] = $currency;

                    if ($amount == 0 || $amount == '0.00') {
                        $return_array = array();
                        unset($extraParam['card_number']);
                        if (is_user_logged_in()) {
                            $current_user_id = get_current_user_id();
                            $return_array['arm_user_id'] = $current_user_id;
                            $arm_user_info = get_userdata($current_user_id);
                            $return_array['arm_first_name']=$arm_user_info->first_name;
                            $return_array['arm_last_name']= $arm_user_info->last_name;
                        }else{
                            $return_array['arm_first_name']=(isset($request_data['first_name']))?$request_data['first_name']:'';
                            $return_array['arm_last_name']=(isset($request_data['last_name']))?$request_data['last_name']:'';
                        }
                        $return_array['arm_plan_id'] = $plan->ID;
                        $return_array['arm_payment_gateway'] = 'stripe';
                        $return_array['arm_payment_type'] = $plan->payment_type;
                        $return_array['arm_token'] = '-';
                        $return_array['arm_payer_email'] = $entry_email;
                        $return_array['arm_receiver_email'] = '';
                        $return_array['arm_transaction_id'] = '-';
                        $return_array['arm_transaction_payment_type'] = $plan->payment_type;
                        $return_array['arm_transaction_status'] = 'completed';
                        $return_array['arm_payment_mode'] = $payment_mode;
                        $return_array['arm_payment_date'] = date('Y-m-d H:i:s');
                        $return_array['arm_amount'] = 0;
                        $return_array['arm_currency'] = $currency;
                        $return_array['arm_coupon_code'] = @$coupon_code;
                        $return_array['arm_coupon_discount'] = @$arm_coupon_discount;
                        $return_array['arm_coupon_discount_type'] = @$arm_coupon_discount_type;
                        $return_array['arm_response_text'] = '';
                        $return_array['arm_extra_vars'] = maybe_serialize($extraParam);
                        $return_array['arm_is_trial'] = $arm_is_trial;
                        $return_array['arm_created_date'] = current_time('mysql');
                        $return_array['arm_coupon_on_each_subscriptions'] = $arm_coupon_on_each_subscriptions;
                        $payment_log_id = $arm_payment_gateways->arm_save_payment_log($return_array);
                        do_action('arm_after_stripe_free_manual_payment', $plan, $payment_log_id, $arm_is_trial, @$coupon_code, $extraParam);
                        return array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);
                    }
                } else {
                    /* Coupon Details */
                    if (!empty($coupon_amount) && $coupon_amount > 0) {
                        $amount = $discount_amt;
                    }

                    $amount = str_replace(",", "", $amount);
                    
                    /* Make Amount in Cent */

                    if($request_data['tax_percentage'] > 0){

                        $tax_amount = ($request_data['tax_percentage'] * $amount)/100;
                        $tax_amount= number_format((float) $tax_amount, 2, '.', '');
                        $amount = $amount + $tax_amount;
                        $tax_amount = $arm_payment_gateways->arm_amount_set_separator($currency, $tax_amount);
                        $charge_details['tax_amount'] = $tax_amount;
                    }

                    $amount = number_format((float) $amount, 2, '.', '');

                    $extraParam['paid_amount'] = $amount;

                    if (!empty($amount)) {
                        if (!in_array($currency, $zero_demial_currencies)) {
                            $amount = $amount * 100;
                        }
                        else{
                            $amount = number_format((float) $amount, 0);
                            $amount = str_replace(",", "", $amount);
                        }
                    } else {
                        $amount = "0";
                    }
                    $charge_details['amount'] = $amount;
                    $charge_details['currency'] = $currency;
                    if ($amount == 0 || $amount == '0.00') {
                        $return_array = array();
                        unset($extraParam['card_number']);
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
                        $return_array['arm_payment_gateway'] = 'stripe';
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
                        $return_array['arm_extra_vars'] = maybe_serialize($extraParam);
                        $return_array['arm_is_trial'] = $arm_is_trial;
                        $return_array['arm_created_date'] = current_time('mysql');
                        $return_array['arm_coupon_on_each_subscriptions'] = $arm_coupon_on_each_subscriptions;
                        $payment_log_id = $arm_payment_gateways->arm_save_payment_log($return_array);
                        do_action('arm_after_stripe_free_payment', $plan, $payment_log_id, $arm_is_trial, @$coupon_code, $extraParam);
                        return array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);
                    }
                    if (isset($request_data['user_email']) && $request_data['user_email'] != "") {
                        $charge_details['receipt_email'] = $request_data['user_email'];
                    }
                    if ($plan->name != "") {
                        $charge_details['description'] = $plan->name;
                    }
                }
                $custom_var = $entry_id . '|' . $entry_email . '|' . $form->ID . '|' . $plan->payment_type . '|' . $payment_mode;
                $charge_details['metadata'] = array('custom' => $custom_var, 'tax_percentage' => $request_data['tax_percentage'].'%','customer_email' => $entry_email);

                /*if (!empty($request_data['stripeToken'])) {
                    $charge_details['source'] = $request_data['stripeToken'];
                } else {*/

                    $first_name = (isset($request_data['first_name']) && !empty($request_data['first_name'])) ? $request_data['first_name'] : '';
                    $last_name = (isset($request_data['last_name']) && !empty($request_data['last_name'])) ? $request_data['last_name'] : '';
                    if($card_holder_name != '') {
                        $stripe_display_name = $card_holder_name;
                    }
                    else if ($first_name != '' && $last_name != '') {
                        $stripe_display_name = $first_name . " " . $last_name;
                    } else {
                        $stripe_display_name = sanitize_user($entry_email);
                    }
                    $charge_details['card'] = array(
                        "number" => $card_number,
                        "exp_month" => $exp_month,
                        "exp_year" => $exp_year,
                        "cvc" => $cvc,
                        'name' => $stripe_display_name,
                        'email' => sanitize_user($entry_email),
                        'address_line1' => '',
                        'address_line2' => '',
                        'address_city' => '',
                        'address_zip' => '',
                        'address_state' => '',
                        'address_country' => '',
                    );
                //}

                $charge_details['email'] = sanitize_user($entry_email);
                $extraParam['arm_is_trial'] = $arm_is_trial;
                $charge_details['extraVars'] = $extraParam;
            }
            return $charge_details;
        }

        function arm_prepare_stripe_charge_details_for_single_payment($request_data = array(), $setup_id = 0, $payment_cycle = 0) {
            global $wpdb, $ARMember, $arm_global_settings, $payment_done, $arm_payment_gateways, $arm_membership_setup, $arm_manage_coupons, $arm_transaction;
         
            $charge_details = array();
            if (!empty($request_data)) {
                $currency = $arm_payment_gateways->arm_get_global_currency();
                $entry_id = $request_data['entry_id'];
                $entry_email = $request_data['entry_email'];
                $stripe_card_detail = $request_data['stripe'];
                $card_holder_name = $stripe_card_detail['card_holder_name'];
                $card_number = $stripe_card_detail['card_number'];
                $exp_month = $stripe_card_detail['exp_month'];
                $exp_year = $stripe_card_detail['exp_year'];
                $cvc = $stripe_card_detail['cvc'];
                $formSlug = isset($request_data['arm_action']) ? $request_data['arm_action'] : '';
                $arm_user_old_plan = $request_data['arm_user_old_plan_ids'];
                $subscriptionPlanID = (!empty($request_data['subscription_plan'])) ? $request_data['subscription_plan'] : 0;
                if ($subscriptionPlanID == 0) {
                    $subscriptionPlanID = (!empty($request_data['_subscription_plan'])) ? $request_data['_subscription_plan'] : 0;
                }
                $form = new ARM_Form('slug', $formSlug);
                $plan = new ARM_Plan($subscriptionPlanID);
                $recurring_data = $plan->prepare_recurring_data($payment_cycle);

                $amount = $recurring_data['trial']['amount'];
                $amount = str_replace(",", "", $amount);

                $trial_period = $recurring_data['trial']['period'];
                $trial_interval = $recurring_data['trial']['interval'];
                
                $plan_amount = $recurring_data['amount'];
                $plan_amount = str_replace(",", "", $plan_amount);

                $maskCCNum = $arm_transaction->arm_mask_credit_card_number($card_number);
                $extraParam = array('card_number' => $maskCCNum, 'plan_amount' => $amount, 'paid_amount' => $amount);
                $discount_amt = $coupon_amount = 0;
                /* Coupon Details */
                if ($arm_manage_coupons->isCouponFeature && isset($request_data['arm_coupon_code']) && !empty($request_data['arm_coupon_code'])) {
                    $couponApply = $arm_manage_coupons->arm_apply_coupon_code($request_data['arm_coupon_code'], $plan, $setup_id, $payment_cycle, $arm_user_old_plan);
                    $coupon_amount = isset($couponApply['coupon_amt']) ? $couponApply['coupon_amt'] : 0;
                    $coupon_amount = str_replace(",", "", $coupon_amount);

                    $discount_amt = isset($couponApply['total_amt']) ? $couponApply['total_amt'] : $amount;
                    $discount_amt = str_replace(",", "", $discount_amt);

                    $arm_coupon_on_each_subscriptions = isset($couponApply['arm_coupon_on_each_subscriptions']) ? $couponApply['arm_coupon_on_each_subscriptions'] : '0';
                    $arm_coupon_discount_type_default = isset($couponApply['discount_type']) ? $couponApply['discount_type'] : "";

                    $arm_coupon_discount = (isset($couponApply['discount']) && !empty($couponApply['discount'])) ? $couponApply['discount'] : 0;
                    $global_currency = $arm_payment_gateways->arm_get_global_currency();
                    $arm_coupon_discount_type = ($couponApply['discount_type'] != 'percentage') ? $global_currency : "%";
                    if (!empty($coupon_amount) && $coupon_amount > 0) {
                        $extraParam['coupon'] = array(
                            'coupon_code' => $request_data['arm_coupon_code'],
                            'amount' => $coupon_amount,
                        );

                        $charge_details['coupon_details'] = array(
                            'coupon_code' => $request_data['arm_coupon_code'],
                            'arm_coupon_discount' => $arm_coupon_discount,
                            'arm_coupon_discount_type' => $arm_coupon_discount_type,
                            'arm_coupon_discount_type_default' => $arm_coupon_discount_type_default,
                            'arm_coupon_on_each_subscriptions' => $arm_coupon_on_each_subscriptions
                        );
                    }
                } else {
                    $request_data['arm_coupon_code'] = '';
                }

                $extraParam['trial'] = array(
                    'amount' => $amount,
                    'period' => $trial_period,
                    'interval' => $trial_interval,
                );

                /* Coupon Details */
                if (!empty($coupon_amount) && $coupon_amount > 0) {
                    $amount = $discount_amt;
                }

                $amount = str_replace(",", "", $amount);

                if($request_data['tax_percentage'] > 0) {
                    $tax_amount = ($request_data['tax_percentage'] * $amount)/100;
                    $tax_amount= number_format((float) $tax_amount, 2, '.', '');
                    $tax_amount = $arm_payment_gateways->arm_amount_set_separator($currency, $tax_amount);
                    $amount = $amount + $tax_amount;
                    $charge_details['tax_amount'] = $tax_amount;
                }

                $amount = number_format((float) $amount, 2, '.', '');
                  $extraParam['paid_amount'] = $amount;

                $zero_demial_currencies = $arm_payment_gateways->arm_stripe_zero_decimal_currency_array();
                if (!empty($amount)) {
                    if (!in_array($currency, $zero_demial_currencies)) {
                        $amount = $amount * 100;
                    }
                    else{
                        $amount = number_format((float) $amount, 0, '');
                        $amount = str_replace(",", "", $amount);
                    }
                } else {
                    $amount = "0.00";
                }
                /* Make Amount in Cent */
              
                $charge_details['amount'] = $amount;
                $charge_details['currency'] = $currency;

                if (isset($request_data['user_email']) && $request_data['user_email'] != "") {
                    $charge_details['receipt_email'] = $request_data['user_email'];
                }
                if ($plan->name != "") {
                    $charge_details['description'] = $plan->name;
                }

                $custom_var = $entry_id . '|' . $entry_email . '|' . $form->ID . '|' . $plan->payment_type;
                $charge_details['metadata'] = array('custom' => $custom_var, 'tax_percentage' => $request_data['tax_percentage'].'%','customer_email' => $entry_email);

                // if (!empty($request_data['stripeToken'])) {
                //     $charge_details['source'] = $request_data['stripeToken'];
                // } else {

                    $first_name = (isset($request_data['first_name']) && !empty($request_data['first_name'])) ? $request_data['first_name'] : '';
                    $last_name = (isset($request_data['last_name']) && !empty($request_data['last_name'])) ? $request_data['last_name'] : '';

                    if($card_holder_name != '') {
                        $stripe_display_name = $card_holder_name;
                    }
                    else if ($first_name != '' && $last_name != '') {
                        $stripe_display_name = $first_name . " " . $last_name;
                    } else {
                        $stripe_display_name = sanitize_user($entry_email);
                    }

                    $charge_details['card'] = array(
                        "number" => $card_number,
                        "exp_month" => $exp_month,
                        "exp_year" => $exp_year,
                        "cvc" => $cvc,
                        'name' => $stripe_display_name,
                        'email' => sanitize_user($entry_email),
                        'address_line1' => '',
                        'address_line2' => '',
                        'address_city' => '',
                        'address_zip' => '',
                        'address_state' => '',
                        'address_country' => '',
                    );
                //}
                $charge_details['email'] = sanitize_user($entry_email);
                $extraParam['arm_is_trial'] = '1';
                $charge_details['extraVars'] = $extraParam;
            }

         
            return $charge_details;
        }

        function arm_payment_gateway_form_submit_action($payment_gateway, $payment_gateway_options, $posted_data, $entry_id = 0) {
            global $wpdb, $ARMember, $arm_global_settings, $payment_done, $paid_trial_stripe_payment_done, $arm_payment_gateways, $arm_membership_setup, $arm_subscription_plans, $arm_manage_communication;

            if ($payment_gateway == 'stripe') {
                $entry_data = $arm_payment_gateways->arm_get_entry_data_by_id($entry_id);
                if (!empty($entry_data)) {

                    if( isset( $payment_gateway_options['stripe_payment_method'] ) && 'popup' == $payment_gateway_options['stripe_payment_method'] ){

                        //Stripe SCA Validation

                            if(!empty($posted_data['payment_gateway']) && $posted_data['payment_gateway'] == "stripe" && !empty($posted_data['arm_coupon_code']) && ($posted_data['arm_selected_payment_mode'] == "auto_debit_subscription"))
                            {
                                $arm_total_paid_amount = !empty($posted_data['arm_total_payable_amount']) ? $posted_data['arm_total_payable_amount'] : '';
                                
                                if($arm_total_paid_amount<=0)
                                {
                                    $arm_stripe_error_msg = __('Stripe SCA does not support 100% coupon discount or zero amount for auto debit payment method.', 'ARMember');
                                    return $payment_done = array('status' => FALSE, 'error' => $arm_stripe_error_msg);
                                }
                            }
                        //---------------------

                        global $arm_stripe_sca;
                        $arm_stripe_sca->arm_stripe_sca_form_render( $payment_gateway, $payment_gateway_options, $posted_data, $entry_id );
                        global $payment_done;
                        if( false == $payment_done['status'] || (isset( $payment_done['status2']) && true == $payment_done['status2'] ) ){
                            return $payment_done;
                        }
                        die;
                    }

                    $posted_data['entry_email'] = $entry_data['arm_entry_email'];
                    $posted_data['entry_id'] = $entry_id;
                    $user_id = $entry_data['arm_user_id'];
                    $setup_id = $posted_data['setup_id'];
                    $entry_values = maybe_unserialize($entry_data['arm_entry_value']);
                    $arm_user_old_plan = (isset($entry_values['arm_user_old_plan']) && !empty($entry_values['arm_user_old_plan'])) ? explode(",", $entry_values['arm_user_old_plan']) : array();
                    $posted_data['tax_percentage'] = $tax_percentage = isset($entry_values['tax_percentage']) ? $entry_values['tax_percentage'] : 0 ;
                    
                    $posted_data['arm_user_old_plan_ids'] = $arm_user_old_plan;
                    $payment_cycle = $entry_values['arm_selected_payment_cycle'];
                    $setup_detail = $arm_membership_setup->arm_get_membership_setup($setup_id);
                    $plan_id = (!empty($posted_data['subscription_plan'])) ? $posted_data['subscription_plan'] : 0;
                    if ($plan_id == 0) {
                        $plan_id = (!empty($posted_data['_subscription_plan'])) ? $posted_data['_subscription_plan'] : 0;
                    }
                    $plan = new ARM_Plan($plan_id);

                    if (isset($plan->options['payment_cycles']) && !empty($plan->options['payment_cycles'])) {
                        $payment_cycle_key = $plan->options['payment_cycles'][$payment_cycle]['cycle_key'];
                        if (!empty($setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id])) {
                            if (is_array($setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id])) {
                                if (isset($setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id][$payment_cycle_key])) {
                                    $stripePlanID = $setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id][$payment_cycle_key];
                                } else {
                                    $stripePlanID = '';
                                }
                            } else {
                                $stripePlanID = $setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id];
                            }
                            $posted_data['stripePlanID'] = $stripePlanID;
                        } else {
                            $posted_data['stripePlanID'] = '';
                        }
                    } else {
                        if (!empty($setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id])) {
                            if (is_array($setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id])) {
                                $stripePlanID = $setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id]['arm0'];
                            } else {
                                $stripePlanID = $setup_detail['setup_modules']['modules']['stripe_plans'][$plan_id];
                            }
                            $posted_data['stripePlanID'] = $stripePlanID;
                        } else {
                            $posted_data['stripePlanID'] = '';
                        }
                    }

                    if ($plan->is_recurring()) {
                        $payment_mode = !empty($posted_data['arm_selected_payment_mode']) ? $posted_data['arm_selected_payment_mode'] : 'manual_subscription';
                    } else {
                        $payment_mode = '';
                    }

                    $plan_action = 'new_subscription';
                    $plan_expiry_date = "now";

                    if (!empty($arm_user_old_plan)) {
                        if (in_array($plan_id, $arm_user_old_plan)) {

                            $user_plan_data = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                            $user_plan_data = !empty($user_plan_data) ? $user_plan_data : array();
                            $plan_expiry_date = (isset($user_plan_data['arm_expire_plan']) && !empty($user_plan_data['arm_expire_plan'])) ? $user_plan_data['arm_expire_plan'] : "now";
                            $plan_action = 'renew_subscription';
                            $is_recurring_payment = $arm_subscription_plans->arm_is_recurring_payment_of_user($user_id, $plan_id, $payment_mode);
                            if ($is_recurring_payment) {
                                $plan_action = 'recurring_payment';
                                $planData = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                                $oldPlanDetail = $planData['arm_current_plan_detail'];
                                $user_subsdata = $planData['arm_stripe'];
                                if (!empty($oldPlanDetail)) {
                                    $plan = new ARM_Plan(0);
                                    $plan->init((object) $oldPlanDetail);
                                }
                            }
                        } else {
                            if( !$plan->isPaidPost ){
                                $plan_action = 'change_subscription';
                            }
                        }
                    }

                    if ($payment_mode == 'auto_debit_subscription') {
                        if ($plan->is_recurring()) {
                            if ($plan_action == 'new_subscription') {
                                if (!($plan->is_support_stripe($payment_cycle))) {

                                    $err_msg = __('Payment through Stripe is not supported for selected plan.', 'ARMember');
                                    return $payment_done = array('status' => FALSE, 'error' => $err_msg);
                                }
                            } else {
                                if (!($plan->is_support_stripe_without_trial($payment_cycle))) {
                                    $err_msg = __('Payment through Stripe is not supported for selected plan.', 'ARMember');
                                    return $payment_done = array('status' => FALSE, 'error' => $err_msg);
                                }
                            }
                        }
                    }

                    $charge_details = self::arm_prepare_stripe_charge_details($posted_data, $plan, $setup_id, $payment_cycle, $plan_action);

                   $stripe_response = array();
                   $stripe_error_msg = (isset($arm_global_settings->common_message['arm_payment_fail_stripe'])) ? $arm_global_settings->common_message['arm_payment_fail_stripe'] : __('Sorry something went wrong while processing payment with Stripe.', 'ARMember');
                   $payment_done = array('status' => FALSE, 'error' => $stripe_error_msg, 'gateway' => 'stripe');

                    if (!empty($charge_details)) {
                        $extraVars = array();
                        if (isset($charge_details['status']) && $charge_details['status'] == TRUE) {

                            $payment_done = $charge_details;
                            return $payment_done;
                        }
                        if (isset($charge_details['extraVars'])) {
                            $extraVars = $charge_details['extraVars'];
                            unset($charge_details['extraVars']);
                        }
                        $coupon_details = array();
                        if (isset($charge_details['coupon_details'])) {
                            $coupon_details = $charge_details['coupon_details'];
                        }
                        $charge_details['plan_action'] = $plan_action;
                        $charge_details['expire_date'] = $plan_expiry_date;

                        $charge_details['tax_percentage'] = $tax_percentage; 
                        $extraVars['tax_percentage'] = $tax_percentage;
                        $extraVars['tax_amount'] =  isset($charge_details['tax_amount'])? $charge_details['tax_amount']  : 0; 
                        unset($charge_details['tax_amount']);
                        $plan = new ARM_Plan($plan_id);
                        $update_payment = false;
                        $stripe_response1 = $stripe_response = array();
                        if ($plan_action == 'new_subscription' && $plan->is_recurring() && $payment_mode == 'auto_debit_subscription' && $plan->has_trial_period()) {
                                $opt_trial = $plan->options['trial'];
                                $stripe_response1['message_error'] = array();

                                if ($opt_trial['amount'] > 0) {
                                    $charge_details1 = self::arm_prepare_stripe_charge_details_for_single_payment($posted_data, $setup_id, $payment_cycle);

                                    if (!empty($charge_details1)) {
                                        $extraVars1 = array();
                                        if (isset($charge_details1['extraVars'])) {
                                            $extraVars1 = $charge_details1['extraVars'];
                                            unset($charge_details1['extraVars']);
                                        }

                                        $coupon_details = array();
                                        if (isset($charge_details1['coupon_details'])) {
                                            $coupon_details = $charge_details1['coupon_details'];
                                            if(empty($charge_details1['coupon_details']['arm_coupon_on_each_subscriptions']))
                                            {
                                                unset($charge_details1['coupon_details']);
                                            }
                                        }
                                        unset($charge_details1['source']);
                                        //$charge_details1['customer'] = $stripe_response->customer;
                                        $extraVars1['tax_percentage'] = $charge_details1['tax_percentage'] = $tax_percentage;
                                        $extraVars1['tax_amount'] =  isset($charge_details1['tax_amount'])? $charge_details1['tax_amount']  : 0; 
                                        unset($charge_details1['tax_amount']);
                                        
                                        if($charge_details1['amount'] > 0)
                                        {
                                            $stripe_response1 = self::arm_StripePayment($charge_details1, $plan, $payment_mode);
                                            $update_payment = true;
                                        }
                                    } else {
                                       $stripe_response1['message_error'] = (isset($arm_global_settings->common_message['arm_payment_fail_stripe'])) ? $arm_global_settings->common_message['arm_payment_fail_stripe'] : __('Sorry something went wrong while processing payment with Stripe.', 'ARMember');
                                        
                                    }
                                }
                                if (!empty($stripe_response1['message_error'])) {
                                
                                    $payment_done = array('status' => FALSE, 'error' => $stripe_response1['message_error'], 'gateway' => 'stripe');
                                    return $payment_done;
                                } else {

                                    if(!empty($stripe_response1)){


                                        $stripe_response = self::arm_StripePayment($charge_details, $plan, $payment_mode);

                                            if (!empty($stripe_response['message_error'])) {

                                                $payment_done = array('status' => FALSE, 'error' => $stripe_response['message_error'], 'gateway' => 'stripe');
                                            }
                                            else{
                                                $payment_log_id = self::arm_store_stripe_log($stripe_response, $plan_id, $user_id, $posted_data['entry_email'], $extraVars, $payment_mode, $coupon_details);
                                                sleep(2);
                                                $payment_done = array();
                                                if ($payment_log_id) {
                                                    if( $update_payment ){
                                                        self::arm_update_stripe_charge( $stripe_response1->id, $stripe_response->customer );
                                                    }
                                                    $payment_done = array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);

                                                    if(!empty($coupon_details) && !empty($coupon_details["arm_coupon_on_each_subscriptions"])) {
                                                    $payment_done["coupon_on_each"] = TRUE;
                                                    $payment_done["trans_log_id"] = $payment_log_id;
                                                    }
                                                }

                                                $payment_log_id1 = self::arm_store_stripe_log($stripe_response1, $plan_id, $user_id, $posted_data['entry_email'], $extraVars1, $payment_mode);
                               
                                                $paid_trial_stripe_payment_done = array();
                                                if ($payment_log_id1) {
                                                    $paid_trial_stripe_payment_done = array('status' => TRUE, 'log_id' => $payment_log_id1, 'entry_id' => $entry_id, 'gateway' => 'stripe');
                                                    
                                                }
                                            }
                                        
                                    }
                                }
                        }
                        else{

                            if($plan->is_recurring() && $payment_mode == 'auto_debit_subscription') {
                                $arm_selected_payment_plan = $posted_data['subscription_plan'];
                                $arm_selected_payment_cycle = $posted_data['payment_cycle_'.$arm_selected_payment_plan];
                                
                                $arm_subscribe_plan_cycles = array();
                                array_push($arm_subscribe_plan_cycles, $plan->options['payment_cycles'][$arm_selected_payment_cycle]);

                                $arm_new_stripe_subscribe_plans = $this->arm_stripe_get_stripe_plan($arm_selected_payment_plan, $arm_subscribe_plan_cycles);

                                $charge_details['plan'] = $arm_new_stripe_subscribe_plans[0];
                            }

                            $stripe_response = self::arm_StripePayment($charge_details, $plan, $payment_mode);

                            if (empty($stripe_response['message_error'])) {

                                if($plan->is_recurring() && $payment_mode == 'auto_debit_subscription') 
                                {
                                    $arm_subscription_plan_options['arm_subscription_plan_options'] = $plan->arm_subscription_plan_options;
                                
                                    //Update Plan Setup Configuration
                                    $this->arm_update_stripe_configuration_setup($arm_selected_payment_plan, $arm_subscription_plan_options);
                                }

                            }
                            else
                            {
                                $payment_done = array('status' => FALSE, 'error' => $stripe_response['message_error'], 'gateway' => 'stripe');
                                return $payment_done;
                            }
                            
                                $payment_log_id = self::arm_store_stripe_log($stripe_response, $plan_id, $user_id, $posted_data['entry_email'], $extraVars, $payment_mode, $coupon_details);
                                sleep(2);
                                $payment_done = array();
                                if ($payment_log_id) {
                                    $payment_done = array('status' => TRUE, 'log_id' => $payment_log_id, 'entry_id' => $entry_id);

                                    if($plan_action=='renew_subscription' && !empty($coupon_details) && !empty($coupon_details["arm_coupon_on_each_subscriptions"]))
                                    {
                                        $payment_done["coupon_on_each"] = TRUE;
                                        $payment_done["trans_log_id"] = $payment_log_id;
                                    }
                                    /*
                                    else if($plan_action=='recurring_payment')
                                    {
                                        do_action('arm_after_recurring_payment_success_outside', $user_id, $plan_id, 'stripe', $payment_mode, $user_subsdata);
                                    }
                                    */
                                }
                            
                        }
                    }
                }
            }
        }

        function arm_cancel_stripe_subscription($user_id, $plan_id) {
            global $wpdb, $ARMember, $arm_global_settings, $arm_subscription_plans, $arm_member_forms, $arm_payment_gateways, $arm_manage_communication, $arm_subscription_cancel_msg;
            if (!empty($user_id) && $user_id != 0 && !empty($plan_id) && $plan_id != 0) {
                $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
                $apikey = "";
                if (isset($all_payment_gateways['stripe']) && !empty($all_payment_gateways['stripe'])) {

                    $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                    $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $plan_id, true);
                    $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                    $planData = shortcode_atts($defaultPlanData, $userPlanDatameta);

                    $user_payment_gateway = $planData['arm_user_gateway'];
                    if (strtolower($user_payment_gateway) == 'stripe') {
                        $user_stripe_data = $planData['arm_stripe'];
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

                        $customer_id = isset($user_stripe_data['customer_id']) ? $user_stripe_data['customer_id'] : '';
                        $subscr_id = isset($user_stripe_data['transaction_id']) ? $user_stripe_data['transaction_id'] : '';

                        $user_detail = get_userdata($user_id);
                        $payer_email = $user_detail->user_email;

                        $stripe_options = $all_payment_gateways['stripe'];
                        if( isset( $stripe_options['stripe_payment_method'] ) && 'popup' == $stripe_options['stripe_payment_method'] ){
                            global $arm_stripe_sca;
                            $arm_stripe_sca->arm_cancel_stripe_sca_subscription( $plan_id, $user_id );
                            return;
                        }
                        else
                        {
                            if ($payment_mode == 'auto_debit_subscription') {
                                if (!empty($subscr_id)) {
                                    
                                if (file_exists(MEMBERSHIP_DIR . "/lib/Stripe/vendor/autoload.php")) {
                                    require_once (MEMBERSHIP_DIR . "/lib/Stripe/vendor/autoload.php");
                                }
                                    if ($stripe_options['stripe_payment_mode'] == 'live') {
                                        $apikey = $stripe_options['stripe_secret_key'];
                                    } else {
                                        $apikey = $stripe_options['stripe_test_secret_key'];
                                    }
                                    Stripe\Stripe::setApiKey($apikey);
				    Stripe\Stripe::setApiVersion($this->arm_stripe_api_version);

                                    try {                                   
					$customer_obj = new \Stripe\StripeClient($apikey);
                                        $customer = $customer_obj->customers->retrieve($customer_id);

                                        $subscription = $customer_obj->subscriptions->retrieve($subscr_id);

                                        $StripeAcion = $subscription->cancel();

                                        if((!empty($StripeAcion) && (empty($StripeAcion->status) || ($StripeAcion->status != "canceled"))) || empty($StripeAcion))
                                        {
                                            $common_messages = isset($arm_global_settings->common_message) ? $arm_global_settings->common_message : array();
                                            $arm_subscription_cancel_msg = isset($common_messages['arm_payment_gateway_subscription_failed_error_msg']) ? $common_messages['arm_payment_gateway_subscription_failed_error_msg'] : __("Membership plan couldn't cancel. Please contact the site administrator.", 'ARMember');
                                            return;
                                        }

                                        if ($StripeAcion->status == 'canceled') {

                                            $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $plan_id, 'user_id' => $user_id, 'action' => 'on_cancel_subscription'));
                                            $payment_data = array(
                                                'arm_user_id' => $user_id,
                                                'arm_first_name'=>$user_detail->first_name,
                                                'arm_last_name'=> $user_detail->last_name,
                                                'arm_plan_id' => $plan_id,
                                                'arm_payment_gateway' => 'stripe',
                                                'arm_payment_type' => 'subscription',
                                                'arm_token' => $StripeAcion->customer,
                                                'arm_payer_email' => $payer_email,
                                                'arm_receiver_email' => '',
                                                'arm_transaction_id' => $StripeAcion->id,
                                                'arm_transaction_payment_type' => $StripeAcion->object,
                                                'arm_payment_mode' => $payment_mode,
                                                'arm_transaction_status' => $StripeAcion->status,
                                                'arm_payment_date' => current_time('mysql'),
                                                'arm_amount' => $amount,
                                                'arm_coupon_code' => '',
                                                'arm_response_text' => utf8_encode(maybe_serialize($StripeAcion)),
                                                'arm_is_trial' => '0',
                                                'arm_created_date' => current_time('mysql')
                                            );
                                            $payment_log_id = $arm_payment_gateways->arm_save_payment_log($payment_data);
                                            return;
                                        } else {
                                            
                                            /* For Writing Response */
                                        }
                                    } catch (Exception $e) {
                                        $arm_enable_debug_mode = isset($stripe_options['enable_debug_mode']) ? $stripe_options['enable_debug_mode'] : 0;
                                        if($arm_enable_debug_mode)
                                        {
                                            $arm_subscription_cancel_msg = __("Error in subscription cancel from Payment Gateway:", "ARMember")." ".$e->getMessage();
                                        }
                                        else
                                        {
                                            $common_messages = isset($arm_global_settings->common_message) ? $arm_global_settings->common_message : array();
                                            $arm_subscription_cancel_msg = isset($common_messages['arm_payment_gateway_subscription_failed_error_msg']) ? $common_messages['arm_payment_gateway_subscription_failed_error_msg'] : __("Membership plan couldn't cancel. Please contact the site administrator.", 'ARMember');
                                        }
                                    }
                                }
                            } else {

                                $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $plan_id, 'user_id' => $user_id, 'action' => 'on_cancel_subscription'));
                                $payment_data = array(
                                    'arm_user_id' => $user_id,
                                    'arm_first_name'=> $user_detail->first_name,
                                    'arm_last_name'=> $user_detail->last_name,
                                    'arm_plan_id' => $plan_id,
                                    'arm_payment_gateway' => 'stripe',
                                    'arm_payment_type' => 'subscription',
                                    'arm_token' => $subscr_id,
                                    'arm_payer_email' => $payer_email,
                                    'arm_receiver_email' => '',
                                    'arm_transaction_id' => $subscr_id,
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
                        }
                        /* End `(!empty($subscr_id) && strtolower($user_payment_gateway)=='stripe')` */
                    }
                }
            } /* End `(!empty($user_id) && $user_id != 0 && !empty($plan_id) && $plan_id != 0)` */
        }

        function arm_update_stripe_charge( $charge_id, $customer_id ){
            global $wpdb, $ARMember, $arm_payment_gateways;
            $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
            if( isset( $all_payment_gateways['stripe'] ) && !empty( $all_payment_gateways['stripe'] ) ){
                if( file_exists( MEMBERSHIP_DIR . "/lib/Stripe/vendor/autoload.php" ) ){
                    require_once( MEMBERSHIP_DIR . "/lib/Stripe/vendor/autoload.php" );
                }

                $apikey = "";
                $stripe_options = $all_payment_gateways['stripe'];
                if( "live" == $stripe_options['stripe_payment_mode'] ){
                    $apikey = $stripe_options['stripe_secret_key'];
                } else {
                    $apikey = $stripe_options['stripe_test_secret_key'];
                }
                
                

                $stripe_client = new \Stripe\StripeClient($apikey);
                Stripe\Stripe::setApiVersion($this->arm_stripe_api_version);

                $update_charge = $stripe_client->charges->update($charge_id, array(
                    'customer'=>$customer_id
                ));
            }
        }

        function arm_StripePayment($charge_details, $plan='', $payment_mode='manual_subscription') {
            global $wpdb, $ARMember, $arm_global_settings, $arm_member_forms, $arm_payment_gateways;
            $errors = array();
            $err_msg = (isset($arm_global_settings->common_message['arm_payment_fail_stripe'])) ? $arm_global_settings->common_message['arm_payment_fail_stripe'] : __('Sorry something went wrong while processing payment with Stripe.', 'ARMember');
            $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
            if (isset($all_payment_gateways['stripe']) && !empty($all_payment_gateways['stripe'])) {
                if (file_exists(MEMBERSHIP_DIR . "/lib/Stripe/vendor/autoload.php")) {
                    require_once (MEMBERSHIP_DIR . "/lib/Stripe/vendor/autoload.php");
                }
                $apikey = "";
                $stripe_options = $all_payment_gateways['stripe'];
                $arm_stripe_enable_debug_mode = isset($stripe_options['enable_debug_mode']) ? $stripe_options['enable_debug_mode'] : 0;
                $arm_help_link = '<a href="https://stripe.com/docs/error-codes" target="_blank">'.__('Click Here', 'ARMember').'</a>';
                if ($stripe_options['stripe_payment_mode'] == 'live') {
                    $apikey = $stripe_options['stripe_secret_key'];
                } else {
                    $apikey = $stripe_options['stripe_test_secret_key'];
                }
                Stripe\Stripe::setApiKey($apikey);
                Stripe\Stripe::setApiVersion($this->arm_stripe_api_version);

                $plan_action = isset($charge_details['plan_action']) ? $charge_details['plan_action'] : 'new_subscription';
                $expire_date = isset($charge_details['expire_date']) ? $charge_details['expire_date'] : "now";
                $coupon_details = isset($charge_details['coupon_details']) ? $charge_details['coupon_details'] : array();
                $customer_email = isset($charge_details['email']) ? $charge_details['email'] : '';
                $tax_percentage = isset($charge_details['tax_percentage']) ? number_format((float)$charge_details['tax_percentage'], 2, '.',''): 0;
                unset($charge_details['expire_date']);
                unset($charge_details['plan_action']);
                unset($charge_details['coupon_details']);
                unset($charge_details['email']);
                unset($charge_details['tax_percentage']);

               try {
                    if (isset($charge_details['plan']) && !empty($charge_details['plan'])) {
                        if (isset($charge_details['source']) && !empty($charge_details['source'])) {
                            $cust_detail = array('source' => $charge_details['source']);
                            unset($charge_details['source']);
                        } else {
                            $cust_detail = array('card' => $charge_details['card']);
                            unset($charge_details['card']);
                        }

                        $cust_detail['email'] = $customer_email;
                        if (class_exists('Stripe\Customer')) {
                            if (!empty($coupon_details)) {
                             
                                $coupon_code = $coupon_details['coupon_code'];
                                $coupon_discount_type = $coupon_details['arm_coupon_discount_type'];
                                $arm_coupon_on_each_subscriptions = isset($coupon_details['arm_coupon_on_each_subscriptions']) ? $coupon_details['arm_coupon_on_each_subscriptions'] : '0';
                                $coupon_duration = "once";
                                if(!empty($arm_coupon_on_each_subscriptions))
                                {
                                    $coupon_duration = "forever";
                                }
                                $stripe_coupon_details = array();
                                try {
                                    $stripe_coupon_details = Stripe\Coupon::retrieve($coupon_code);
                                } catch (Exception $ex) {
                                }

                                
                                if (!empty($stripe_coupon_details)) {


                                    $coupon_created_date = $stripe_coupon_details->created;
                                    $coupon_updated_date = $wpdb->get_var($wpdb->prepare("SELECT `arm_coupon_added_date` FROM  `$ARMember->tbl_arm_coupons` WHERE `arm_coupon_code` = %s", $coupon_code));
                                    if (strtotime($coupon_updated_date) > $coupon_created_date) {
                                        $stripe_coupon_details->delete();
                                        $stripe_coupon_details = array();
                                    }
                                }

                               
                                if (empty($stripe_coupon_details)) {
                                   
                                    $currency = $arm_payment_gateways->arm_get_global_currency();
                                    $create_coupon = array();
                                    try {
                                        if ($coupon_discount_type == '%') {
                                            $coupon_amount = str_replace(",", "", $coupon_details['arm_coupon_discount']);
                                            $coupon_amount = number_format((float) $coupon_amount, 0, '', '');
                                            $create_coupon = Stripe\Coupon::create(array(
                                                        "percent_off" => $coupon_amount,
                                                        "duration" => $coupon_duration,
                                                        "id" => $coupon_code)
                                            );
                                        } else {
                                            $coupon_amount = str_replace(",", "", $coupon_details['arm_coupon_discount']);
                                            $coupon_amount = number_format((float) $coupon_amount, 2, '.', '');
                                            if (!empty($coupon_amount)) {

                                                $zero_demial_currencies = $arm_payment_gateways->arm_stripe_zero_decimal_currency_array();
                                                if (!in_array($currency, $zero_demial_currencies)) {
                                                    $coupon_amount = $coupon_amount * 100;
                                                }
                                                else{
                                                    $coupon_amount = number_format((float) $coupon_amount, 0);
                                                    $coupon_amount = str_replace(",", "", $coupon_amount);
                                                }
                                            }
                                            $create_coupon = Stripe\Coupon::create(array(
                                                        "amount_off" => $coupon_amount,
                                                        "duration" => $coupon_duration,
                                                        "id" => $coupon_code,
                                                        "currency" => $currency)
                                            );
                                        }
                                    } catch (Exception $ex) {
                                        
                                        if($arm_stripe_enable_debug_mode == '1')
                                        {
                                            $error_msg = $ex->getJsonBody();
                                            $actual_error = isset($error_msg['error']['message']) ? $error_msg['error']['message'] : '';
                                            $actual_error = !empty($actual_error) ? $actual_error.' '.$arm_help_link : __('coupon could not be applied', 'ARMember');
                                            $ARMember->arm_write_response( " Stripe Event Data Log 55 == coupon not applied == " . maybe_serialize($error_msg) );
                                            $errors['message_error'] = $actualmsg;
                                        } else {
                                            $errors['message_error'] = __('coupon could not be applied', 'ARMember');    
                                        }
                                        
                                        return $errors;
                                        $create_coupon = array();
                                    }
                                } else {
                                    $create_coupon = $stripe_coupon_details;
                                }
                            }

                            if(isset($cust_detail['card']['email'])) {
                                unset($cust_detail['card']['email']);
                            }

                            if ($plan_action == 'new_subscription' && $plan->is_recurring() && $payment_mode == 'auto_debit_subscription' && $plan->has_trial_period()) {
                                $opt_trial_days = $plan->options['trial']['days'];
                                $charge_details["trial_period_days"] = $opt_trial_days;
                            }
                            
                            $token = Stripe\Token::create(array("card" => $cust_detail['card']));
                            $request_token = $token->id;

                            $cust_detail['card'] = $request_token;

                            $new_cust = new \Stripe\StripeClient($apikey);
                            $customer_obj = $new_cust->customers->create($cust_detail);
                            $charge_details['customer'] = $customer_obj->id;

                            if ($plan_action == 'change_subscription') {
                                $charge_details["trial_end"] = "now";
                            } else if ($plan_action == 'renew_subscription') {
                                $charge_details["trial_end"] = $expire_date;
                            }

                            if (!empty($coupon_details) && !empty($create_coupon)) {
                                $charge_details['coupon'] = $coupon_code;
                            }

                            $charge = $new_cust->subscriptions->create($charge_details);

                            if(!empty($tax_percentage))
                            {
                                //Create Tax Rate
                                $armCreateTax = $new_cust->taxRates->create([
                                    'display_name' => 'Tax',
                                    'inclusive' => false,
                                    'percentage' => $tax_percentage
                                ]);

                                $armTaxID = $armCreateTax->id;

                                //Update Subscription with tax
                                $armUpdateSubs = \Stripe\Subscription::update(
                                    $charge->id,
                                    [
                                        'default_tax_rates' => [$armTaxID],
                                    ]
                                );
                            }
                        } else {
                            $errors['message_error'] = $err_msg;
                        }
                    } else {
                        if (class_exists('Stripe\Customer')) {
                            if(isset($charge_details['card']['email'])) {
                                unset($charge_details['card']['email']);
                            }

                            if( $plan->type != 'recurring' ){
                                if (isset($charge_details['source']) && !empty($charge_details['source'])) {
                                    $cust_detail = array('source' => $charge_details['source']);
                                    unset($charge_details['source']);
                                } else {
                                    $cust_detail = array('card' => $charge_details['card']);
                                    unset($charge_details['card']);
                                }
                                $token = Stripe\Token::create(array("card" => $cust_detail['card']));
                                $request_token = $token->id;

                                $cust_detail['card'] = $request_token;

                                $cust_detail['email'] = $customer_email;

                                $new_cust = new \Stripe\StripeClient($apikey);
                                $customer_obj = $new_cust->customers->create($cust_detail);
                                $charge_details['customer'] = $customer_obj->id;

                            } else {
                                $token = Stripe\Token::create(array("card" => $charge_details['card']));
                                $request_token = $token->id;
                                $charge_details['card'] = $request_token;
                            }

                            $new_cust = new \Stripe\StripeClient($apikey);
                            $charge = $new_cust->charges->create($charge_details);
                            
                        } else {
                          
                            $errors['message_error'] = $err_msg;
                        }
                    }
                } catch (Stripe\Error\Stripe_Card $e) {
                    $actual = $e->getJsonBody();
                    $ARMember->arm_write_response("error1 ".maybe_serialize($actual));

                    $card_err_msg = '';
                    /* Since it's a decline, Stripe_CardError will be caught $body = $e->getJsonBody(); */
                    if (!empty($actual)) {
                        $actual_error = isset($actual['error']['message']) ? $actual['error']['message'] : '';
                        $actual_error = !empty($actual_error) ? $actual_error.' '.$arm_help_link : '';
                        if (isset($actual['error']['code']) && $actual['error']['code'] == 'card_declined') {
                            $card_err_msg = $arm_global_settings->common_message['arm_credit_card_declined'];
                            $card_err_msg = (!empty($card_err_msg)) ? $card_err_msg : __('Your Card is declined.', 'ARMember');
                        } else {
                            $card_err_msg = $arm_global_settings->common_message['arm_invalid_credit_card'];
                            $card_err_msg = (!empty($card_err_msg)) ? $card_err_msg : __('Please enter correct card details.', 'ARMember');
                        }
                    } else {
                        $card_err_msg = $arm_global_settings->common_message['arm_credit_card_declined'];
                        $card_err_msg = (!empty($card_err_msg)) ? $card_err_msg : __('Your Card is declined.', 'ARMember');
                    }
                    if($arm_stripe_enable_debug_mode == '1')
                    {
                        $errors['message_error'] = $actual_error;
                        $ARMember->arm_write_response( " Stripe Event Data Log 1 == " . maybe_serialize($actual) );
                    }
                    else
                    {
                        $errors['message_error'] = $card_err_msg;
                    }
                } catch (Stripe\Error\Stripe_InvalidRequest $e) {
                    $error_msg = $e->getJsonBody();
                    $actual_error = isset($error_msg['error']['message']) ? $error_msg['error']['message'] : '';
                    $actual_error = !empty($actual_error) ? $actual_error.' '.$arm_help_link : '';

                    $actualmsg = ($arm_stripe_enable_debug_mode == '1') ? $actual_error : $err_msg;
                    if($arm_stripe_enable_debug_mode == '1') {
                        $ARMember->arm_write_response( " Stripe Event Data Log 2 == " . maybe_serialize($error_msg) );
                    }

                    /* Invalid parameters were supplied to Stripe's API  */
                    $errors['message_error'] = $actualmsg;
                } catch (Stripe\Error\Stripe_Authentication $e) {
                    $error_msg = $e->getJsonBody();
                    $actual_error = isset($error_msg['error']['message']) ? $error_msg['error']['message'] : '' ;
                    $actual_error = !empty($actual_error) ? $actual_error.' '.$arm_help_link : '';

                    $actualmsg = ($arm_stripe_enable_debug_mode == '1') ? $actual_error : $err_msg;
                    if($arm_stripe_enable_debug_mode == '1') {
                        $ARMember->arm_write_response( " Stripe Event Data Log 3 == " . maybe_serialize($error_msg) );
                    }
                 
                    /* Authentication with Stripe's API failed  */
                    /* (maybe you changed API keys recently) */
                    $errors['message_error'] = $actualmsg;
                } catch (Stripe\Error\Stripe_ApiConnection $e) {
                    $error_msg = $e->getJsonBody();
                    $actual_error = isset($error_msg['error']['message']) ? $error_msg['error']['message'] : '';
                    $actual_error = !empty($actual_error) ? $actual_error.' '.$arm_help_link : '';

                    $actualmsg = ($arm_stripe_enable_debug_mode == '1') ? $actual_error : $err_msg;
                    if($arm_stripe_enable_debug_mode == '1') {
                        $ARMember->arm_write_response( " Stripe Event Data Log 4 == " . maybe_serialize($error_msg) );
                    }

                    /* Network communication with Stripe failed */
                    $errors['message_error'] = $actualmsg;
                } catch (Exception $e) {
                    $error_msg = $e->getJsonBody();
                    $actual_error = isset($error_msg['error']['message']) ? $error_msg['error']['message'] : '';
                    $actual_error = !empty($actual_error) ? $actual_error.' '.$arm_help_link : '';
                    
                    $actualmsg = ($arm_stripe_enable_debug_mode == '1') ? $actual_error : $err_msg;
                    if($arm_stripe_enable_debug_mode == '1') {
                        $ARMember->arm_write_response( " Stripe Event Data Log 5 == " . maybe_serialize($error_msg) );
                    }
                    /* Something else happened, completely unrelated to Stripe */
                    $errors['message_error'] = $actualmsg;
                }
            } else {
                $errors['message_error'] = $err_msg;
            }
            if (!empty($errors)) {
                return $errors;
            } else {
                if(isset($charge->status) && $charge->status=='incomplete')
                {
                    $errors['message_error'] = __('Your card required Stripe 3D authentication. Recommend to use Stripe SCA Method', 'ARMember');
                    return $errors;
                }
                else {
                    return $charge;
                }
                
            }
        }

        function arm_StripeEventListener() {
            global $wpdb, $ARMember, $arm_global_settings, $arm_member_forms, $arm_payment_gateways, $arm_subscription_plans, $arm_manage_communication, $arm_members_class;
            /**
             * Need to set web-hook url like this (ie. http://sitename.com/?arm-listener=arm_stripe_api)
             */
            if (isset($_REQUEST['arm-listener']) && in_array($_REQUEST['arm-listener'], array('arm_stripe_api', 'arm_stripe_notify', 'stripe'))) {
                 
                $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
                if (isset($all_payment_gateways['stripe']) && !empty($all_payment_gateways['stripe'])) {

                    if (file_exists(MEMBERSHIP_DIR . "/lib/Stripe/vendor/autoload.php")) {
                        require_once (MEMBERSHIP_DIR . "/lib/Stripe/vendor/autoload.php");
                    }

                    $secret_key = "";
                    $stripe_options = $all_payment_gateways['stripe'];
                    if ($stripe_options['stripe_payment_mode'] == 'live') {
                        $secret_key = $stripe_options['stripe_secret_key'];
                    } else {
                        $secret_key = $stripe_options['stripe_test_secret_key'];
                    }
                    Stripe\Stripe::setApiVersion($this->arm_stripe_api_version);
                    $errors = array();

                    /* Retrieve the request's body and parse it as JSON */
                    $body = @file_get_contents('php://input');
                    /* Grab the event information */
                    $event_json = json_decode($body);
                    /* Retrieve the event from Stripe request body */
                    $event_id = $event_json->id;
                    if (isset($event_json->id)) {
                        $payment_data = array();
                        try {
                            /* to verify this is a real event, we re-retrieve the event from Stripe  */
                            //New API Request
                            $stripe_client_obj = new \Stripe\StripeClient($secret_key);
                            $event = $stripe_client_obj->events->retrieve($event_id);
                            $invoice = $event->data->object;
                            $customs = explode('|', $invoice->metadata->custom);
                            $entry_id = isset($customs[0]) ? $customs[0] : '' ;
                            $entry_email = isset($customs[1]) ? $customs[1] : '';
                            $subscription_id = $invoice->id;
                           
                            //$entry_data = $wpdb->get_row("SELECT `arm_entry_id`, `arm_entry_email`, `arm_entry_value`, `arm_form_id`, `arm_user_id`, `arm_plan_id` FROM `" . $ARMember->tbl_arm_entries . "` WHERE `arm_entry_id`='" . $entry_id . "' AND `arm_entry_email`='" . $entry_email . "'", ARRAY_A);

                            $payLog_data = $wpdb->get_row("SELECT `arm_user_id`, `arm_plan_id`, `arm_extra_vars` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_transaction_id`='".$subscription_id."' AND `arm_payment_gateway`='stripe' ORDER BY `arm_log_id` DESC", ARRAY_A);
                             
                            if (!empty($subscription_id) && !empty($payLog_data)) {
                                $payment_log_user_id = $payLog_data['arm_user_id'];
                                if(empty($payment_log_user_id))
                                {
                                    $get_user_id_by_subscription = $wpdb->get_row("SELECT * FROM ".$wpdb->usermeta." WHERE meta_value like '%".$subscription_id."%'", ARRAY_A);
                                    if(!empty($get_user_id_by_subscription))
                                    {
                                        $payment_log_user_id = $get_user_id_by_subscription['user_id'];
                                    }
                                }
                                //$entry_values = maybe_unserialize($entry_data['arm_entry_value']);
                                //$tax_percentage =  isset($entry_values['tax_percentage']) ? $entry_values['tax_percentage'] : 0;
                                //$entry_plan = $entry_data['arm_plan_id'];
                                $user_info = get_user_by('ID', $payment_log_user_id);

                                $entry_plan = $payLog_data['arm_plan_id'];
                                $extraVars = $payLog_data['arm_extra_vars'];
                                $tax_percentage = $tax_amount = 0;
                                if(isset($extraVars) && !empty($extraVars)){
                                    $unserialized_extravars = maybe_unserialize($extraVars);
                                    $tax_percentage = (isset($unserialized_extravars['tax_percentage']) && $unserialized_extravars['tax_percentage'] != '' )? $unserialized_extravars['tax_percentage'] : 0;
                                }

                                if (!empty($user_info)) {
                                    $user_id = $user_info->ID;
                                    $userPlan = get_user_meta($user_id, 'arm_user_plan_ids', true);
                                    $userPlan = !empty($userPlan) ? $userPlan : array();

                                    $defaultPlanData = $arm_subscription_plans->arm_default_plan_array();
                                    $userPlanDatameta = get_user_meta($user_id, 'arm_user_plan_' . $entry_plan, true);
                                    $userPlanDatameta = !empty($userPlanDatameta) ? $userPlanDatameta : array();
                                    $planData = shortcode_atts($defaultPlanData, $userPlanDatameta);

                                    $payment_cycle = $planData['arm_payment_cycle'];
                                    $planDetail = $planData['arm_current_plan_detail'];
                                    $tax_amount = 0;

                                    if (!empty($planDetail)) {
                                        $plan = new ARM_Plan(0);
                                        $plan->init((object) $planDetail);
                                        $plan_data = $plan->prepare_recurring_data($payment_cycle);
                                        $plan_amount = $plan_data['amount'];
                                        
                                        if($tax_percentage > 0 && $plan_amount != '') {
                                            $tax_amount = ($tax_percentage*$plan_amount)/100;
                                            $tax_amount = number_format((float)$tax_amount , 2, '.', '');
                                        }

                                    } else {
                                        $plan = new ARM_Plan($entry_plan);
                                        $recurring_data = $plan->prepare_recurring_data($payment_cycle);
                                        $plan_amount = $recurring_data['amount']; 
                                      
                                        if($tax_percentage > 0 && $plan_amount != ''){
                                            $tax_amount = ($tax_percentage*$plan_amount)/100;
                                            $tax_amount = number_format((float)$tax_amount , 2, '.', '');
                                        }
                                    }

                                    $payment_mode = $planData['arm_payment_mode'];
                                    $user_subsdata = $planData['arm_stripe'];
                                    if (in_array($entry_plan, $userPlan)) {
                                        $extraVars = array(
                                            'subs_id' => $invoice->customer,
                                            'trans_id' => $invoice->id,
                                            'error' => $invoice->failure_message,
                                            'date' => current_time('mysql'),
                                            'message_type' => $event->type . '-' . $invoice->failure_code,
                                        );
                                        $extraVars['tax_percentage'] = $tax_percentage;
                                        $extraVars['tax_amount'] = $tax_amount;
                                        switch ($event->type) {
                                            case 'invoice.payment_succeeded':
                                            case 'customer.subscription.updated':
                                                /* below action is need only if last payment is not failed payment */
                                                $arm_next_due_payment_date = $planData['arm_next_due_payment'];
                                                if (!empty($arm_next_due_payment_date)) {
                                                    if (strtotime(current_time('mysql')) >= $arm_next_due_payment_date) {
                                                        $total_completed_recurrence = $planData['arm_completed_recurring'];
                                                        $total_completed_recurrence++;
                                                        $planData['arm_completed_recurring'] = $total_completed_recurrence;

                                                        update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $planData);
                                                        $payment_cycle = $planData['arm_payment_cycle'];

                                                        $arm_next_payment_date = $arm_members_class->arm_get_next_due_date($user_id, $entry_plan, false, $payment_cycle);
                                                        $planData['arm_next_due_payment'] = $arm_next_payment_date;
                                                        update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $planData);
                                                    }
                                                }
                                                else{

                                                  
                                                    $now = current_time('mysql');

                                                    $arm_last_payment_status = $wpdb->get_var($wpdb->prepare("SELECT `arm_transaction_status` FROM `" . $ARMember->tbl_arm_payment_log . "` WHERE `arm_user_id`=%d AND `arm_plan_id`=%d AND `arm_created_date`<=%s ORDER BY `arm_log_id` DESC LIMIT 0,1", $user_id, $entry_plan, $now));

                                                    if($arm_last_payment_status == 'success') {
                                                        $total_completed_recurrence = $planData['arm_completed_recurring'];
                                                        $total_completed_recurrence++;
                                                        $planData['arm_completed_recurring'] = $total_completed_recurrence;

                                                        update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $planData);
                                                        $payment_cycle = $planData['arm_payment_cycle'];

                                                        $arm_next_payment_date = $arm_members_class->arm_get_next_due_date($user_id, $entry_plan, false, $payment_cycle);
                                                        $planData['arm_next_due_payment'] = $arm_next_payment_date;
                                                        update_user_meta($user_id, 'arm_user_plan_' . $entry_plan, $planData);
                                                    }
                                                }

                                                $suspended_plan_ids = get_user_meta($user_id, 'arm_user_suspended_plan_ids', true);
                                                $suspended_plan_id = (isset($suspended_plan_ids) && !empty($suspended_plan_ids)) ? $suspended_plan_ids : array();

                                                if (in_array($entry_plan, $suspended_plan_id)) {
                                                    unset($suspended_plan_id[array_search($entry_plan, $suspended_plan_id)]);
                                                    update_user_meta($user_id, 'arm_user_suspended_plan_ids', array_values($suspended_plan_id));
                                                }

                                                $payment_log_id = self::arm_store_stripe_log($invoice, $entry_plan, $user_id, $entry_email, $extraVars, $payment_mode);
                                                do_action('arm_after_recurring_payment_success_outside', $user_id, $entry_plan, 'stripe', $payment_mode, $user_subsdata);
                                                break;
                                            case 'customer.subscription.deleted':
                                                

                                                $plan_cycle = isset($planData['arm_payment_cycle']) ? $planData['arm_payment_cycle'] : '';
                                                $paly_cycle_data = $plan->prepare_recurring_data($plan_cycle);


                                                if($paly_cycle_data['rec_time'] != 'infinite' || $plan->options['cancel_plan_action'] != "on_expire")
                                                {
							$arm_subscription_plans->arm_add_membership_history($user_id, $entry_plan, 'cancel_subscription');
							
                                                    	do_action('arm_cancel_subscription', $user_id, $entry_plan);
                                                    	$arm_subscription_plans->arm_clear_user_plan_detail($user_id, $entry_plan);
                                                

	                                                $cancel_plan_act = isset($plan->options['cancel_action']) ? $plan->options['cancel_action'] : 'block';
	                                                if ($arm_subscription_plans->isPlanExist($cancel_plan_act)) {
	                                                    $arm_members_class->arm_new_plan_assigned_by_system($cancel_plan_act, $entry_plan, $user_id);
	                                                } else {
	                                                }
	                                                $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => 'on_cancel_subscription'));
                                                	$payment_log_id = self::arm_store_stripe_log($invoice, $entry_plan, $user_id, $entry_email, $extraVars, $payment_mode);
						}
                                                break;
                                            case 'invoice.payment_failed':
                                            case 'order.payment_failed':
                                                $arm_subscription_plans->arm_user_plan_status_action(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => 'failed_payment'));
                                                $invoice->status = 'failed';
                                                $arm_manage_communication->arm_user_plan_status_action_mail(array('plan_id' => $entry_plan, 'user_id' => $user_id, 'action' => 'failed_payment'));
                                                $payment_log_id = self::arm_store_stripe_log($invoice, $entry_plan, $user_id, $entry_email, $extraVars, $payment_mode);
                                                do_action('arm_after_recurring_payment_failed_outside', $user_id, $entry_plan, 'stripe', $payment_mode, $user_subsdata);
                                                break;
                                            default:
                                                do_action('arm_handle_stripe_unknown_error_from_outside', $user_id, $entry_plan, $extraVars['message_type']);
                                                break;
                                        }
                                    }
                                }
                            }
                        } catch (Exception $e) {
                        }
                    }
                }
                exit;
            }
            return;
        }

        function arm_store_stripe_log($stripe_response = '', $plan_id = 0, $user_id = 0, $payer_email = '', $extraVars = array(), $payment_mode = '', $coupon_details = array()) {
            global $wpdb, $ARMember, $arm_global_settings, $arm_member_forms, $arm_payment_gateways;
            if (!empty($stripe_response)) {
                $custom_var = $stripe_response->metadata->custom;
                $customs = explode('|', $custom_var);
                $entry_id = $customs[0];
                $entry_email = $customs[1];
                $form_id = $customs[2];
                $arm_payment_type = $customs[3];
                $tax_percentage = isset($stripe_response->metadata->tax_percentage) ? $stripe_response->metadata->tax_percentage : 0;
                $zero_demial_currencies = $arm_payment_gateways->arm_stripe_zero_decimal_currency_array();
                
                if (!empty($stripe_response->plan) && $stripe_response->object == 'subscription') {
    
                    $amount = $stripe_response->plan->amount;
                    $currency = strtoupper($stripe_response->plan->currency);
                    if (!in_array($currency, $zero_demial_currencies)) {
                         $amount = $stripe_response->plan->amount / 100; /* amount comes in as amount in cents, so we need to convert to dollars */
                    }

                    $arm_payment_date = date('Y-m-d H:i:s', $stripe_response->current_period_start);
                    $arm_token = $stripe_response->customer;
                    $arm_payment_type = 'subscription';

                    if( $stripe_response->discount != null  && $stripe_response->discount != 'null') {
                        if( isset($stripe_response->discount->coupon)) {
                            if($stripe_response->discount->coupon->amount_off != null && $stripe_response->discount->coupon->amount_off != 'null') {

                                $amount_off = $stripe_response->discount->coupon->amount_off;
                              
                                if($amount_off > 0) {

                                    if (!in_array($currency, $zero_demial_currencies)) {
                                        $amount_off = $amount_off/100;
                                    }

                                    $amount = $amount - $amount_off;
                                }
                            }
                            else if($stripe_response->discount->coupon->percent_off != null && $stripe_response->discount->coupon->percent_off != 'null') {
                                $percent_off = $stripe_response->discount->coupon->percent_off;
                                    
                                if($percent_off > 0) {

                                    $coupon_amount = ($amount*$percent_off)/100;
                                    $coupon_amount = number_format((float)$coupon_amount, 2, '.', '');
                                    $amount = $amount - $coupon_amount;
                                }
                            }
                        }
                    }

                    if($tax_percentage > 0) {
                        $tax_amount = ($amount*$tax_percentage)/100;
                        $tax_amount = number_format((float)$tax_amount, 2, '.', '');
                        $amount = $tax_amount + $amount;
                    }
                } else {
                    $currency = strtoupper($stripe_response->currency);
                    $amount = $stripe_response->amount;
                    if (!in_array($currency, $zero_demial_currencies)) {
                        $amount = $stripe_response->amount / 100;
                    }
                    /* amount comes in as amount in cents, so we need to convert to dollars */

                    if( !empty($stripe_response->created) ) {
                        $arm_payment_date = date('Y-m-d H:i:s', $stripe_response->created);
                    }
                    else {
                        $arm_payment_date = date('Y-m-d H:i:s');
                    }

                    $arm_token = $stripe_response->source->id;
                    $arm_payment_type = 'one_time';
                }

                $coupon_code = '';
                $coupon_discount = 0;
                $coupon_discount_type = '';
                $arm_coupon_on_each_subscriptions = '0';
                if (isset($coupon_details) && !empty($coupon_details)) {
                    $coupon_code = $coupon_details['coupon_code'];
                    $coupon_discount = $coupon_details['arm_coupon_discount'];
                    $coupon_discount_type = $coupon_details['arm_coupon_discount_type'];
                    $arm_coupon_on_each_subscriptions = isset($coupon_details['arm_coupon_on_each_subscriptions']) ? $coupon_details['arm_coupon_on_each_subscriptions'] : '0';
                }

                if($amount < 0) {
                    $amount = 0;
                }

                if(($stripe_response->discount == null || $stripe_response->discount == 'null') && !empty($coupon_code) && !empty($coupon_discount) && !empty($coupon_discount_type))
                {
                    if($coupon_discount_type == '%'){
                        $amount = $amount - (($amount * $coupon_discount)/100);
                    }else{
                        $amount = $amount - $coupon_discount;
                    }
                }

                
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
                    'arm_plan_id' => $plan_id,
                    'arm_payment_gateway' => 'stripe',
                    'arm_payment_type' => $arm_payment_type,
                    'arm_token' => $arm_token,
                    'arm_payer_email' => $payer_email,
                    'arm_receiver_email' => '',
                    'arm_transaction_id' => $stripe_response->id,
                    'arm_transaction_payment_type' => $stripe_response->object,
                    'arm_transaction_status' => $stripe_response->status,
                    'arm_payment_mode' => $payment_mode,
                    'arm_payment_date' => $arm_payment_date,
                    'arm_amount' => $amount,
                    'arm_currency' => $currency,
                    'arm_coupon_code' => $coupon_code,
                    'arm_coupon_discount' => $coupon_discount,
                    'arm_coupon_discount_type' => $coupon_discount_type,
                    'arm_response_text' => utf8_encode(maybe_serialize((array) $stripe_response)),
                    'arm_extra_vars' => maybe_serialize($extraVars),
                    'arm_is_trial' => isset($extraVars['arm_is_trial']) ? $extraVars['arm_is_trial'] : '0',
                    'arm_created_date' => current_time('mysql'),
                    'arm_coupon_on_each_subscriptions' => $arm_coupon_on_each_subscriptions,
                );

                $payment_log_id = $arm_payment_gateways->arm_save_payment_log($payment_data);

                return $payment_log_id;
            }
            return false;
        }

        function arm_stripe_get_stripe_plan($arm_subscribe_plan_id, $arm_subscribe_plan_cycles = array())
        {
            global $ARMember, $arm_global_settings, $arm_payment_gateways;
            $currency = $arm_payment_gateways->arm_get_global_currency();

            $arm_stripe_api_url = "https://api.stripe.com/v1/";
            $arm_stripe_product_plan_id = '';
            $arm_stripe_product_plan_arr = array();

            if(empty($arm_subscribe_plan_cycles))
            {
                $arm_subscribe_plan_data = new ARM_Plan($arm_subscribe_plan_id);
                $arm_subscribe_plan_cycles = $arm_subscribe_plan_data->options['payment_cycles'];
            }


            $zero_demial_currencies = $arm_payment_gateways->arm_stripe_zero_decimal_currency_array();
                


            foreach($arm_subscribe_plan_cycles as $arm_subscribe_plan_keys => $arm_subscribe_plan_vals)
            {
                $arm_stripe_product_plan_id = '';

                $arm_subscribe_plan_amount = str_replace(',', '', $arm_subscribe_plan_vals['cycle_amount']);
                $arm_subscribe_plan_interval = $arm_subscribe_plan_vals['billing_type'];
                $arm_subscribe_plan_interval_duration = $arm_subscribe_plan_vals['billing_cycle'];
                $arm_subscribe_plan_currency = $currency;

                if($arm_subscribe_plan_interval == 'D')
                {
                    $arm_subscribe_plan_interval = "day";
                }
                else if($arm_subscribe_plan_interval == 'M')
                {
                    $arm_subscribe_plan_interval = "month";
                }
                else if($arm_subscribe_plan_interval == 'Y')
                {
                    $arm_subscribe_plan_interval = "year";
                }

                $arm_subscribe_plan_details = array(
                    'arm_subs_plan_amount'            => $arm_subscribe_plan_amount,
                    'arm_subs_plan_interval'          => $arm_subscribe_plan_interval,
                    'arm_subs_plan_interval_duration' => $arm_subscribe_plan_interval_duration,
                    'arm_subs_plan_currency'          => $arm_subscribe_plan_currency
                );

                $all_payment_gateways = $arm_payment_gateways->arm_get_active_payment_gateways();
                $stripe_options = $all_payment_gateways['stripe'];
                $arm_stripe_secret_key = "";
                if( "live" == $stripe_options['stripe_payment_mode'] )
                {
                    $arm_stripe_secret_key = $stripe_options['stripe_secret_key'];
                }
                else 
                {
                    $arm_stripe_secret_key = $stripe_options['stripe_test_secret_key'];
                }

                if(!empty($arm_subscribe_plan_details))
                {
                    $arm_subscribe_plan_amount = $arm_subscribe_plan_details['arm_subs_plan_amount'];
                    if (!empty($arm_subscribe_plan_amount)) {
                        if (!in_array(strtoupper($currency), $zero_demial_currencies)) {
                            $arm_subscribe_plan_amount = number_format(str_replace(',', '', $arm_subscribe_plan_amount), 2, '.', '');
                            $arm_subscribe_plan_amount = $arm_subscribe_plan_amount * 100;
                        }
                        else{
                            $arm_subscribe_plan_amount = str_replace(",", "", $arm_subscribe_plan_amount);
                            $arm_subscribe_plan_amount = (int)$arm_subscribe_plan_amount;
                        }
                    } else {
                        $amount = "0.00";
                    }
                    $arm_subscribe_plan_interval = $arm_subscribe_plan_details['arm_subs_plan_interval'];
                    $arm_subscribe_plan_interval_duration = $arm_subscribe_plan_details['arm_subs_plan_interval_duration'];
                    $arm_subscribe_plan_currency = $arm_subscribe_plan_details['arm_subs_plan_currency'];

                    $arm_get_stripe_plan_list_url = $arm_stripe_api_url."plans";

                    $arm_stripe_plan_headers = array(
                        'Authorization' => 'Bearer '.$arm_stripe_secret_key
                    );

                    $arm_stripe_plan_data = array('method' => 'GET', 'headers' => $arm_stripe_plan_headers, 'timeout' => '10');

                    $arm_get_stripe_plan_list_response = wp_remote_request($arm_get_stripe_plan_list_url, $arm_stripe_plan_data);

                    if(!is_wp_error($arm_get_stripe_plan_list_response))
                    {
                        $arm_stripe_plan_list_body = wp_remote_retrieve_body($arm_get_stripe_plan_list_response);
                        $arm_stripe_plan_list_data = json_decode($arm_stripe_plan_list_body, true);
                        
                        foreach($arm_stripe_plan_list_data['data'] as $arm_stripe_plan_key => $arm_stripe_plan_val)
                        {
                            $arm_stripe_exist_plan_amount = $arm_stripe_plan_val['amount'];
                            $arm_stripe_exist_plan_interval = $arm_stripe_plan_val['interval'];
                            $arm_stripe_exist_plan_interval_duration = $arm_stripe_plan_val['interval_count'];
                            $arm_stripe_exist_plan_currency = $arm_stripe_plan_val['currency'];

                            if($arm_stripe_exist_plan_amount == $arm_subscribe_plan_amount && $arm_stripe_exist_plan_interval == $arm_subscribe_plan_interval && $arm_subscribe_plan_interval_duration == $arm_stripe_exist_plan_interval_duration && $arm_subscribe_plan_currency == strtoupper($arm_stripe_exist_plan_currency))
                            {
                                $arm_stripe_product_plan_id = $arm_stripe_plan_val['product'];
                                break;
                            }

                        }

                        //Condition for check that if plan exist or not at stripe side.
                        if(!empty($arm_stripe_product_plan_id))
                        {
                            $arm_stripe_list_product_url = $arm_stripe_api_url."products/".$arm_stripe_product_plan_id;
                                
                            $arm_stripe_product_data = array('method' => 'POST', 'headers' => $arm_stripe_plan_headers);

                            $arm_list_stripe_product_response = wp_remote_request($arm_stripe_list_product_url, $arm_stripe_product_data);

                            if(!is_wp_error($arm_list_stripe_product_response))
                            {
                                $arm_stripe_list_product_body = wp_remote_retrieve_body($arm_list_stripe_product_response);
                                $arm_stripe_list_product_body = json_decode($arm_stripe_list_product_body, true);

                                if(!empty($arm_stripe_list_product_body['id']))
                                {
                                    $arm_stripe_product_plan_id = $arm_stripe_list_product_body['id'];
                                }
                                else
                                {
                                    $arm_stripe_product_plan_id = '';    
                                }
                            }


                            //Fetch All Price List and Match Product Name
                            $arm_stripe_list_price_url = $arm_stripe_api_url."prices";
                            $arm_stripe_price_data = array('method' => 'GET', 'headers' => $arm_stripe_plan_headers);

                            $arm_list_stripe_price_response = wp_remote_request($arm_stripe_list_price_url, $arm_stripe_price_data);

                            if(!is_wp_error($arm_list_stripe_price_response))
                            {
                                $arm_stripe_list_price_body = wp_remote_retrieve_body($arm_list_stripe_price_response);
                                $arm_stripe_list_price_body = json_decode($arm_stripe_list_price_body, true);

                                foreach($arm_stripe_list_price_body['data'] as $arm_stripe_price_key => $arm_stripe_price_val)
                                {
                                    if($arm_stripe_price_val['product'] == $arm_stripe_product_plan_id)
                                    {
                                        $arm_stripe_product_plan_id = $arm_stripe_price_val['id'];
                                        break;
                                    }
                                    else
                                    {
                                        continue;
                                    }
                                }
                            } 
                        }
                        else
                        {
                            //Code For Create Product and Plan
                            $arm_stripe_product_id = "";
                            $arm_stripe_product_name = 'prod_'.uniqid();

                            // Create Plan From Product
                            $arm_stripe_plan_create_url = $arm_stripe_api_url."plans";


                            $arm_stripe_plan_body_data = array(
                                'currency' => $arm_subscribe_plan_currency,
                                'interval' => $arm_subscribe_plan_interval,
                                'interval_count' => $arm_subscribe_plan_interval_duration,
                                'product' => array(
                                    'name' => $arm_stripe_product_name,
                                ),
                            );

                            if (!in_array(strtoupper($currency), $zero_demial_currencies)) {
                                $arm_stripe_plan_body_data['amount_decimal'] = $arm_subscribe_plan_amount;
                            }else{
                                $arm_stripe_plan_body_data['amount'] = (int)(str_replace(',', '', $arm_subscribe_plan_amount));
                            }

                            $arm_stripe_plan_data = array(
                                'method' => 'POST', 
                                'headers' => $arm_stripe_plan_headers, 
                                'body' => $arm_stripe_plan_body_data
                            );

                            $arm_create_stripe_plan_response = wp_remote_request($arm_stripe_plan_create_url, $arm_stripe_plan_data);

                            if(!is_wp_error($arm_create_stripe_plan_response))
                            {
                                $arm_stripe_created_plan_body = wp_remote_retrieve_body($arm_create_stripe_plan_response);
                                $arm_stripe_created_plan_body = json_decode($arm_stripe_created_plan_body, true);
                                $arm_stripe_product_plan_id = $arm_stripe_created_plan_body['product'];

                                //Fetch All Price List and Match Product Name
                                $arm_stripe_list_price_url = $arm_stripe_api_url."prices";
                                $arm_stripe_price_data = array('method' => 'GET', 'headers' => $arm_stripe_plan_headers);

                                $arm_list_stripe_price_response = wp_remote_request($arm_stripe_list_price_url, $arm_stripe_price_data);

                                if(!is_wp_error($arm_list_stripe_price_response))
                                {
                                    $arm_stripe_list_price_body = wp_remote_retrieve_body($arm_list_stripe_price_response);
                                    $arm_stripe_list_price_body = json_decode($arm_stripe_list_price_body, true);

                                    foreach($arm_stripe_list_price_body['data'] as $arm_stripe_price_key => $arm_stripe_price_val)
                                    {
                                        if($arm_stripe_price_val['product'] == $arm_stripe_product_plan_id)
                                        {
                                            $arm_stripe_product_plan_id = $arm_stripe_price_val['id'];
                                            break;
                                        }
                                    }
                                }
                            }    
                        }
                    }
                    else
                    {
                        $ARMember->arm_write_response("Reputelog Plan List Response Error => ".maybe_serialize($arm_get_stripe_plan_list_response->get_error_message()));
                    }
                }
                array_push($arm_stripe_product_plan_arr, $arm_stripe_product_plan_id);
            }

            return $arm_stripe_product_plan_arr;
        }



        function arm_update_stripe_configuration_setup($update_plan_id, $subscription_plans_data)
        {
            global $wp, $wpdb, $arm_slugs, $ARMember, $arm_global_settings, $arm_access_rules;

                // Code For Update plan setup configuration Data.
                $arm_plan_recurring = new ARM_Plan($update_plan_id);
                if($arm_plan_recurring->is_recurring())
                {
                    //Fetch Plan Cycle Keys
                    $arm_stripe_plan_keys = array();
                    $plan_options = maybe_unserialize($subscription_plans_data['arm_subscription_plan_options']);
                    $arm_plan_keys = $plan_options['payment_cycles'];
                    foreach($arm_plan_keys as $arm_keys => $arm_values)
                    {
                        array_push($arm_stripe_plan_keys, $arm_values['cycle_key']);
                    }

                    //Table name variable
                    $arm_membership_setup_tbl = $ARMember->tbl_arm_membership_setup;

                    //Fetch New Stripe Plan Array from Plan Id
                    $arm_fetch_new_stripe_plan_arr = $this->arm_stripe_get_stripe_plan($update_plan_id);

                    $arm_plan_setup_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$arm_membership_setup_tbl} WHERE arm_status = %d", 1));

                    foreach($arm_plan_setup_data as $arm_setup_data_key => $arm_setup_data_val)
                    {
                        $arm_setup_modules_data = maybe_unserialize($arm_setup_data_val->arm_setup_modules);
                        if((!empty($arm_setup_modules_data['modules']['plans']) && !empty($arm_setup_modules_data['modules']['gateways']) && !empty($arm_setup_modules_data['modules']['payment_mode']['stripe'])) && (in_array($update_plan_id, $arm_setup_modules_data['modules']['plans']) && in_array('stripe', $arm_setup_modules_data['modules']['gateways']) && $arm_setup_modules_data['modules']['payment_mode']['stripe'] != "manual_subscription"))
                        {
                            if(!empty($arm_setup_modules_data['modules']['stripe_plans'][$update_plan_id]))
                            {
                                $arm_new_setup_data = array();
                                for($arm_new_plans = 0;$arm_new_plans < count($arm_plan_keys);$arm_new_plans++)
                                {
                                    //Modify plan cycle data with stripe plan id.
                                    $arm_new_setup_data[$arm_stripe_plan_keys[$arm_new_plans]] = $arm_fetch_new_stripe_plan_arr[$arm_new_plans];
                                }

                                $arm_setup_modules_data['modules']['stripe_plans'][$update_plan_id] = $arm_new_setup_data;

                                $arm_setup_new_modules_data = maybe_serialize($arm_setup_modules_data);
                                $arm_setup_modules_data_update = $wpdb->query($wpdb->prepare("UPDATE {$arm_membership_setup_tbl} SET `arm_setup_modules` = '{$arm_setup_new_modules_data}' WHERE arm_setup_id = %d", $arm_setup_data_val->arm_setup_id));
                            }
                        }
                    }
                }
        }
    }
}
global $arm_stripe;
$arm_stripe = new ARM_Stripe();