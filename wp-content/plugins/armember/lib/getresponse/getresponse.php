<?php

require_once dirname(__FILE__) . '/jsonRPCClient.php';

global $wpdb, $ARMember, $armemail, $armfname, $armlname, $form_id, $arm_social_feature, $arm_is_social_signup;
$armemail_settings_unser = get_option('arm_email_settings');
$arm_optins_email_settings = maybe_unserialize($armemail_settings_unser);
$getresponseOpt = (isset($arm_optins_email_settings['arm_email_tools']['getresponse'])) ? $arm_optins_email_settings['arm_email_tools']['getresponse'] : array();
$api_key = (isset($getresponseOpt['api_key'])) ? $getresponseOpt['api_key'] : '';
$list_id = (isset($getresponseOpt['list_id'])) ? $getresponseOpt['list_id'] : '';
$responder_list_id = '';
if($arm_is_social_signup){
    $social_settings = $arm_social_feature->arm_get_social_settings();
    if(isset($social_settings['options']['optins_name']) && $social_settings['options']['optins_name'] == 'getresponse') {
        $etool_name = isset($social_settings['options']['optins_name']) ? $social_settings['options']['optins_name'] : '';
        $status = 1;
        $responder_list_id = isset($social_settings['options'][$etool_name]['list_id']) ? $social_settings['options'][$etool_name]['list_id'] : $list_id ;
    }
}
else
{
    $form_settings = $wpdb->get_var("SELECT `arm_form_settings` FROM `" . $ARMember->tbl_arm_forms . "` WHERE `arm_form_id`='" . $form_id . "'");
    $form_settings = (!empty($form_settings)) ? maybe_unserialize($form_settings) : array();
    $status = (isset($form_settings['email']['getresponse']['status'])) ? $form_settings['email']['getresponse']['status'] : 0;
    $responder_list_id = (isset($form_settings['email']['getresponse']['list_id'])) ? $form_settings['email']['getresponse']['list_id'] : $list_id;
}	
if ( !empty($responder_list_id) && !empty($api_key) ) {
	if ($status == '1' && !empty($responder_list_id))
	{
            $subscriberEmail = $armemail;
            $subscriberName =  $armfname . ' ' . $armlname;
            $api_url = 'http://api2.getresponse.com';

            # initialize JSON-RPC client
            $client = new jsonRPCClient($api_url);
            
            $result2 = $client->get_campaigns(
                    $api_key, array(
                'name' => array('EQUALS' => $responder_list_id)
                    )
            );

            $res = array_keys($result2);
            $CAMPAIGN_IDs = array_pop($res);
            
            try {
                $response = $client->get_messages(
                        $api_key, array(
                    'campaigns' => array($CAMPAIGN_IDs)
                        )
                );
                $day_of_cycle = '';
                
                if (!empty($response)) {
                    $get_smallest_cycle_day = "999999"; //for check lowest number, assigned largest number.
                    foreach ($response as $res) {

                        if ($res['campaign'] == $CAMPAIGN_IDs and $res['based_on'] == 'time') {
                            if($res['day_of_cycle']<$get_smallest_cycle_day)
                            {
                                $get_smallest_cycle_day = $day_of_cycle = (int)$res['day_of_cycle'];
                            }
                        }
                    }
                }
                if ($day_of_cycle >= 0) {
                    $add_to_contact_array = array(
                        'campaign' => $CAMPAIGN_IDs,
                        'name' => $subscriberName,
                        'email' => $subscriberEmail,
                        'cycle_day' => $day_of_cycle,
                    );
                } else {
                    $add_to_contact_array = array(
                        'campaign' => $CAMPAIGN_IDs,
                        'name' => $subscriberName,
                        'email' => $subscriberEmail,
                    );
                }
            } catch (Exception $e) {
                //echo $e->getMessage();
                //exit;
                $add_to_contact_array = array(
                    'campaign' => $CAMPAIGN_IDs,
                    'name' => $subscriberName,
                    'email' => $subscriberEmail,
                );
            }



            //exit;
            // Add contact to selected campaign id
            try {
                $result_contact = $client->add_contact(
                        $api_key, $add_to_contact_array
                );
                //echo "<pre>";print_r($result_contact);
                //echo "<p style='color: blue; font-size:24px;'> Contact Added </p>";
                //exit;
            } catch (Exception $e) {

                //echo $e->getMessage();
                //exit;
            }
        }
}