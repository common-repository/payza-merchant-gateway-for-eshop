<?php
/*  based on:
 * PHP Payza IPN Integration Class Demonstration File
 *  22-01-2014 - L.CH.RAJKUMAR, l.ch.rajkumar@gmail.com
*/
/*
* default info
*/
global $wpdb,$wp_query,$wp_rewrite,$blog_id,$eshopoptions;
$detailstable=$wpdb->prefix.'eshop_orders';
$derror=__('There appears to have been an error, please contact the site admin','eshop');

//sanitise
include_once(WP_PLUGIN_DIR.'/eshop/cart-functions.php');
$_POST=sanitise_array($_POST);


/*
* reqd info for your gateway
*/
include_once (WP_PLUGIN_DIR.'/payza-merchant-gateway-for-eshop/payza-mg-gateway.php');
// Setup class
require_once(WP_PLUGIN_DIR.'/payza-merchant-gateway-for-eshop/payza-class.php');  // include the class file
$p = new payza_class;             // initiate an instance of the class

//$p->payza_url = 'https://secure.payza.com/checkout';     // payza url
//Payza Sandbox Testing URL
//$p->payza_url = 'https://sandbox.Payza.com/sandbox/payprocess.aspx';

//Defining SANDBOX & LIVE URL(s) for Payza Gateway....df 
//define('SANDBOX','https://sandbox.Payza.com/sandbox/payprocess.aspx');
//define('LIVE','https://secure.payza.com/checkout');
/*
* reqd info /end
*/

$this_script = site_url();
global $wp_rewrite;
if($eshopoptions['checkout']!=''){
	$p->autoredirect=add_query_arg('eshopaction','redirect',get_permalink($eshopoptions['checkout']));
}else{
	die('<p>'.$derror.'</p>');
}

// if there is no action variable, set the default action of 'process'
if(!isset($wp_query->query_vars['eshopaction']))
	$eshopaction='process';
else
	$eshopaction=$wp_query->query_vars['eshopaction'];

switch ($eshopaction) {
    case 'redirect':
    	//auto-redirect bits
		header('Cache-Control: no-cache, no-store, must-revalidate'); //HTTP/1.1
		header('Expires: Sun, 01 Jul 2005 00:00:00 GMT');
		header('Pragma: no-cache'); //HTTP/1.0

		//enters all the data into the database
		/*
		* this works out eShop's security field
		*/
		$Cost=$_POST['amount'];
		if(isset($_POST['tax']))
			//$Cost += $_POST['tax'];
		$Money = $_POST['tax'];
		//if(isset($_SESSION['shipping'.$blog_id]['tax'])) $Cost += $_SESSION['shipping'.$blog_id]['tax'];
		 
		$theid=$eshopoptions['payza']['id'];
		$Cost=number_format($Cost,2);
		$checkid=md5($_POST['payzaoption1'].$theid.'$'.$Cost);
		//debug
			//echo 'check: '.$_POST['extraoption1'].$theid.'$'.$Cost;
		//
		if(isset($_COOKIE['ap_id'])) $_POST['affiliate'] = $_COOKIE['ap_id'];
		orderhandle($_POST,$checkid);
		if(isset($_COOKIE['ap_id'])) unset($_POST['affiliate']);
		$p = new payza_class; 
		/*
		* more reqd info
		*/
		//$p->payza_url = 'https://secure.payza.com/checkout';     // payza url
		//Payza Sandbox Testing URL
		//$p->payza_url = 'https://sandbox.Payza.com/sandbox/payprocess.aspx';

		$echoit.=$p->eshop_submit_payza_post($_POST);
		break;
        
   case 'process':      // Process and order...   
		//goes direct to this script as nothing needs showing on screen.
		if($eshopoptions['cart_success']!=''){
			$ilink=add_query_arg(array('eshopaction'=>'payzaipn'),get_permalink($eshopoptions['cart_success']));
		}else{
			die('<p>'.$derror.'</p>');
		}
		$p->add_field('payzaURL', $ilink);

		$p->add_field('shipping_1',eshopShipTaxAmt());
		$sttable=$wpdb->prefix.'eshop_states';
		$getstate=$eshopoptions['shipping_state'];
		if($eshopoptions['show_allstates'] != '1'){
			$stateList=$wpdb->get_results("SELECT id,code,stateName FROM $sttable WHERE list='$getstate' ORDER BY stateName",ARRAY_A);
		}else{
			$stateList=$wpdb->get_results("SELECT id,code,stateName,list FROM $sttable ORDER BY list,stateName",ARRAY_A);
		}
		foreach($stateList as $code => $value){
			$eshopstatelist[$value['id']]=$value['code'];
		}		
		foreach($_POST as $name=>$value){
			//have to do a discount code check here - otherwise things just don't work - but fine for free shipping codes
			if(strstr($name,'amount_')){
				if(isset($_SESSION['eshop_discount'.$blog_id]) && eshop_discount_codes_check()){
					$chkcode=valid_eshop_discount_code($_SESSION['eshop_discount'.$blog_id]);
					if($chkcode && apply_eshop_discount_code('discount')>0){
						$discount=apply_eshop_discount_code('discount')/100;
						$value = number_format(round($value-($value * $discount), 2),2);
						$vset='yes';
					}
				}
				if(is_discountable(calculate_total())!=0 && !isset($vset)){
					$discount=is_discountable(calculate_total())/100;
					$value = number_format(round($value-($value * $discount), 2),2);
				}
			}
			if(sizeof($stateList)>0 && ($name=='state' || $name=='ship_state')){
				if($value!='')
					$value=$eshopstatelist[$value];
			}
			$p->add_field($name, $value);
		}
		if($eshopoptions['status']!='live' && is_user_logged_in() &&  current_user_can('eShop_admin')||$eshopoptions['status']=='live'){
			$echoit .= $p->submit_payza_post(); // submit the fields to payza
    	}
      	break;
      	
   case 'extraipn':
   		/*
   		* the routine for when the merchant gateway sontacts your site to validate the order.
   		* may need altering to suit your gateway
   		*/
   		$p->validate_ipn();
		$theid=$eshopoptions['payza']['id'];
		$checked=md5($p->ipn_data['payzaoption1'].$theid.$p->ipn_data['payzaReturnAmount']);
		if($eshopoptions['status']=='live'){
			$txn_id = $wpdb->escape($p->ipn_data['payzaTrxnReference']);
			$subject = __('payza IPN -','eshop');
		}else{
			$txn_id = __("TEST-",'eshop').$wpdb->escape($p->ipn_data['payzaTrxnReference']);
			$subject = __('Testing: payza IPN - ','eshop');
		}
		//check txn_id is unique
		$checktrans=$wpdb->get_results("select transid from $detailstable");
		$astatus=$wpdb->get_var("select status from $detailstable where checkid='$checked' limit 1");
		foreach($checktrans as $trans){
			if(strpos($trans->transid, $p->ipn_data['payzaTrxnReference'])===true){
				$astatus='Failed';
				$txn_id .= __(" - Duplicated",'eshop');
				$payzadetails .= __("Duplicated Transaction Id.",'eshop');
			}
		}
		//accepted response codes - all other fail.
		$payzarescodes=array('00','08','10','11','16');
		if(!in_array($p->ipn_data['payzaresponseCode'],$payzarescodes)){
			$astatus='Failed';
			$txn_id .= __(" - Failed",'eshop');
			$payzadetails .= ' '.$p->ipn_data['payzaresponseText'];
		}

		//the magic bit  + creating the subject for our email.
		if($astatus=='Pending' && $p->ipn_data['payzaTrxnStatus']=='True'){
			$subject .=__("Completed Payment",'eshop');	
			$ok='yes';
			eshop_mg_process_product($txn_id,$checked);
		}else{
			$query2=$wpdb->query("UPDATE $detailstable set status='Failed',transid='$txn_id' where checkid='$checked'");
			$subject .=__("A Failed Payment",'eshop');
			$ok='no';
			$payzadetails .= __("The transaction was not completed successfully. eShop could not validate the order.",'eshop');
			$payzadetails .= ' '.$p->ipn_data['payzaresponseText'];
		}
		$subject .=" Ref:".$txn_id;
		$array=eshop_rtn_order_details($checked);
		// email to business a complete copy of the notification from extra to keep!!!!!
		 $body =  __("A Payza payment notification was received",'eshop')."\n";
		 $body .= "\n".__("from ",'eshop').$array['eemail'].__(" on ",'eshop').date('m/d/Y');
		 $body .= __(" at ",'eshop').date('g:i A')."\n\n".__('Details','eshop').":\n";
		 if(isset($array['dbid']))
			$body .= get_option( 'siteurl' ).'/wp-admin/admin.php?page=eshop-orders.php&view='.$array['dbid']."\n";

		if($payzadetails!='') $body .= $payzadetails."\n\n";
		foreach ($p->ipn_data as $key => $value) { $body .= "\n$key: $value"; }
		//debug
		//	$body .= "\n".'check: '.$p->ipn_data['extraoption1'].$theid.$p->ipn_data['extraReturnAmount'];

		$body .= "\n\n".__('Regards, Your friendly automated response.','eshop')."\n\n";
		$headers=eshop_from_address();
		$eshopemailbus=$eshopoptions['payza']['email'];
		$to = apply_filters('eshop_gatpayza_details_email', array($eshopemailbus));
		wp_mail($to, $subject, $body, $headers);

		if($ok=='yes'){
			//only need to send out for the successes!
			//lets make sure this is here and available
			include_once(WP_PLUGIN_DIR.'/eshop/cart-functions.php');
			eshop_send_customer_email($checked, '151');
		}
		$_SESSION = array();
		session_destroy();
		break;
}
?>
