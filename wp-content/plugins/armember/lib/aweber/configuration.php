<?php
require_once("../../../../../wp-load.php");
require_once('aweber_api.php');
global $wpdb, $ARMember, $arm_slugs;
$email_settings_unser = get_option('arm_email_settings');
$email_setttings = maybe_unserialize($email_settings_unser);
$email_tools = (isset($email_setttings['arm_email_tools'])) ? $email_setttings['arm_email_tools'] : array();

$consumerKey = MEMBERSHIP_AWEBER_CONSUMER_KEY;
$consumerSecret = MEMBERSHIP_AWEBER_CONSUMER_SECRET;
$aweber = new AWeberAPI($consumerKey, $consumerSecret);
if (empty($_COOKIE['accessToken']) || empty($_GET['oauth_token'])) {
    if (empty($_GET['oauth_token'])) {
        $callbackUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']; 
        list($requestToken, $requestTokenSecret) = $aweber->getRequestToken($callbackUrl);
        setcookie('requestTokenSecret', $requestTokenSecret);
        setcookie('callbackUrl', $callbackUrl);
        header("Location: {$aweber->getAuthorizeUrl()}");
        exit();
    }
    $aweber->user->tokenSecret = $_COOKIE['requestTokenSecret'];
    $aweber->user->requestToken = $_GET['oauth_token'];
    $aweber->user->verifier = $_GET['oauth_verifier'];
    list($accessToken, $accessTokenSecret) = $aweber->getAccessToken();
    setcookie('accessToken', $accessToken);
    setcookie('accessTokenSecret', $accessTokenSecret);
    header('Location: '.$_COOKIE['callbackUrl']);
    exit();
}

# set this to true to view the actual api request and response
$aweber->adapter->debug = false;
$account = $aweber->getAccount($_COOKIE['accessToken'], $_COOKIE['accessTokenSecret']);
// $account_lists_data = $account->lists->data;
// $account_lists_entries = $account_lists_data['entries'];
$HTTP_METHOD = 'GET';
$URL = $account->url."/lists";
$PARAMETERS = array('ws.start'=>0, 'ws.size'=>100);
$RETURN_FORMAT = array();
$account_lists_entries = array();
get_more_pagination :
$entries = $aweber->adapter->request($HTTP_METHOD, $URL, $PARAMETERS, $RETURN_FORMAT);
if(isset($entries['entries']) && count($entries['entries']) > 0) {
	foreach ($entries['entries'] as $entry) {
		array_push($account_lists_entries, $entry);
	}
	$PARAMETERS['ws.start'] = $PARAMETERS['ws.start'] + $PARAMETERS['ws.size'];
	if(isset($entries['next_collection_link'])) {
		goto get_more_pagination; 
	}
}

$aweberLists = array();
$i = 0;

if (!empty($account_lists_entries)) {
	foreach ($account_lists_entries as $offset => $list) {
		if (!empty($list['id'])) {
			$aweberLists[$i]['id'] = $list['id'];
			$aweberLists[$i]['name'] = $list['name'];
			$i++;
		}
	}
}
if ($consumerKey != "" && $consumerSecret != "" && $_COOKIE['accessToken'] != "" && $_COOKIE['accessTokenSecret'] != "" && $account->id != "") {
	$temp = array('accessToken' => $_COOKIE['accessToken'], 'accessTokenSecret' => $_COOKIE['accessTokenSecret'], 'acc_id' => $account->id);
	$temp_data = serialize($temp);
	$email_tools['aweber'] = array(
		'consumer_key' => $consumerKey,
		'consumer_secret' => $consumerSecret,
		'temp' => $temp,
		'status' => 1,
		'list' => $aweberLists,
		'list_id' => '',
	);
	$email_setttings['arm_email_tools'] = $email_tools;
	update_option('arm_email_settings', $email_setttings);
}
echo "<script>window.opener.location.replace('".admin_url('admin.php?page=' . $arm_slugs->general_settings.'&action=opt_ins_options')."');</script>";
echo '<script>window.close();</script>';
exit;
?>