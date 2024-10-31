<?php
/*
Plugin Name: Payza Merchant Gateway For eShop
Plugin URI: http://wordpress.org/plugins/payza-merchant-gateway-for-eshop/ 
Description: PAYZA Merchant Gateway for eShop plugin acts as an add-on plugin for "eShop WordPress plugin" which adds up the additional merchant gateway namely "PAYZA(AlertPay)" for all eShop powered site merchants.
Version: 0.3
Author: L.Ch.Rajkumar
Author URI: http://profiles.wordpress.org/lchrajkumar

    Copyright 2014 L.CH.RAJKUMAR  (email : l.ch.rajkumar@gmail.com | twitter : http://www.twitter.com/lchrajkumar)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
register_activation_hook(__FILE__,'eshoppayza_activate');

function eshoppayza_activate(){
	/*
	* Activation routines
	*/
	global $wpdb;
	$opts=get_option('active_plugins');
	$eshopthere=false;
	foreach($opts as $opt){
		if($opt=='eshop/eshop.php')
			$eshopthere=true;
	}
	if($eshopthere==false){
		deactivate_plugins('payza-mg-gateway.php'); //Deactivate ourself
		wp_die(__('ERROR! eShop is not active.','eshop')); 
	}
	/*
	* insert email template for use with this merchant gateway, if 151 is changed, then ipn.php needs amending as well 
	*/
	$table = $wpdb->prefix ."eshop_emails";
	$esubject=__('Your order from ','eshop').get_bloginfo('name');
	$wpdb->query("INSERT INTO ".$table." (id,emailType,emailSubject) VALUES ('151','".__('Automatic Payza email','eshop')."','$esubject')"); 
	
}
add_action('eshop_setting_merchant_load','eshopmgpage');
function eshopmgpage($thist){
	/*
	* adding the meta box for this gateway
	*/
	add_meta_box('eshop-m-payza', __('Payza','eshop'), 'payza_box', $thist->pagehook, 'normal', 'core');
}

function payza_box($eshopoptions) {
	/*
	* the meta box content, obviously you have to set up the required fields for your gateway here
	*/
	if(isset($eshopoptions['payza'])){
		$eshoppayza = $eshopoptions['payza']; 
	}else{
		$eshoppayza['merchant_email']='';
		$eshoppayza['purchase_type']='';
                $eshoppayza['return_url']='';
                $eshoppayza['cancel_url']='';
		$eshoppayza['gateway_url']='';
	}
	//add the image
	$eshopmerchantimgpath=WP_PLUGIN_DIR.'/payza-merchant-gateway-for-eshop/payza.png';
	$eshopmerchantimgurl=WP_PLUGIN_URL.'/payza-merchant-gateway-for-eshop/payza.png';
	$dims[3]='';
	if(file_exists($eshopmerchantimgpath))
	$dims=getimagesize($eshopmerchantimgpath);
	echo '<fieldset>';
	echo '<p class="eshopgatpayza"><img src="'.$eshopmerchantimgurl.'" '.$dims[3].' alt="payza" title="payza" /></p>'."\n";
?>
	<p class="cbox"><input id="eshop_methodpayza" name="eshop_method[]" type="checkbox" value="payza"<?php if(in_array('payza',(array)$eshopoptions['method'])) echo ' checked="checked"'; ?> /><label for="eshop_methodpayza" class="eshopmethod"><?php _e('Accept payment by payza','eshop'); ?></label></p>
	<label for="eshop_payzameremail"><?php _e('Merchant Email','eshop'); ?></label><input id="eshop_payzameremail" name="payza[merchant_email]" type="text" value="<?php echo $eshoppayza['merchant_email']; ?>" size="30" maxlength="200" /><br />
	<label for="eshop_payzagaturl"><?php _e('Gateway Url','eshop'); ?></label>
		<input id="eshop_payzagaturl" name="payza[gateway_url]" type="text" value="<?php echo $eshoppayza['gateway_url']; ?>" 
		size="30" maxlength="200" /><br/>
	<label for="eshop_payzapurtype"><?php _e('Purchase Type','eshop'); ?></label>
	<select id="eshop_payzapurtype" name="payza[purchase_type]" value="<?php echo $eshoppayza['purchase_type']; ?>">
		<option value="ITEM-GOODS">ITEM-GOODS</option>
	</select><br />
        <label for="eshop_payzareturl"><?php _e('Return/Success URL','eshop'); ?></label><input id="eshop_payzareturl" name="payza[return_url]" type="text" value="<?php echo $eshoppayza['return_url']; ?>" size="30" maxlength="200"/><br/>
        <label for="eshop_payzacanurl"><?php _e('Cancel URL','eshop'); ?></label><input id="eshop_payzacanurl" name="payza[cancel_url]" type="text" value="<?php echo $eshoppayza['cancel_url']; ?>" size="30" maxlength="200"/><br/><hr/>
<div>
	<p style="color:#DC143C;">To make this plugin work perfectly and to get the checkout function properly, go to <a href="http://www.skrilleshopplugin.biz/shop/eshop-add-on-plugins-2/eshop-payza-checkout/" target="_blank"><span style="color:#00C957;"><b>eShop Payza Checkout</b></span></a> and purchase the plugin for just $25 and use it. Its an add-on plugin that gives dynamic checkout fields for end-user to perform smooth checkout.</p>
</div>
        </fieldset>
<?php
}

add_filter('eshop_setting_merchant_save','payzasave',10,3);
function payzasave($eshopoptions,$posted){
	/*
	* save routine for the fields you added above
	*/
	global $wpdb;
	$payzapost['merchant_email']=$wpdb->escape($posted['payza']['merchant_email']);
	$payzapost['purchase_type']=$wpdb->escape($posted['payza']['purchase_type']);
	$payzapost['gateway_url']=$wpdb->escape($posted['payza']['gateway_url']);
        $payzapost['return_url']=$wpdb->escape($posted['payza']['return_url']);
        $payzapost['cancel_url']=$wpdb->escape($posted['payza']['cancel_url']);
	$eshopoptions['payza']=$payzapost;
	return $eshopoptions;
}

add_action('eshop_include_mg_ipn','eshoppayza');
function eshoppayza($eshopaction){
	/*
	* adding the necessary link for the instant payment notification of your gateway
	*/
	if($eshopaction=='payzaipn'){
		include_once WP_PLUGIN_DIR.'/payza-merchant-gateway-for-eshop/ipn.php';
	}
}

add_filter('eshop_merchant_img_payza','payzaimg');
function payzaimg($array){
	/*
	* adding the image for this gateway, for use on the front end of the site
	*/
	$array['path']=WP_PLUGIN_DIR.'/payza-merchant-gateway-for-eshop/payza.png';
	$array['url']=WP_PLUGIN_URL.'/payza-merchant-gateway-for-eshop/payza.png';
	return $array;
}
add_filter('eshop_mg_inc_path','payzapath',10,3);
function payzapath($path,$paymentmethod){
	/*
	* adding another necessary link for the instant payment notification of your gateway
	*/
	if($paymentmethod=='payza')
		return WP_PLUGIN_DIR.'/payza-merchant-gateway-for-eshop/ipn.php';
	return $path;
}
add_filter('eshop_mg_inc_idx_path','payzaidxpath',10,3);
function payzaidxpath($path,$paymentmethod){
	/*
	* adding the necessary link to the class for this gateway
	*/
	if($paymentmethod=='payza')
		return WP_PLUGIN_DIR.'/payza-merchant-gateway-for-eshop/payza-class.php';
	return $path;
}
//message on fail.
add_filter('eshop_show_success', 'eshop_payza_return_fail',10,3);
function eshop_payza_return_fail($echo, $eshopaction, $postit){
	/*
	* failed payment, you can add in details for this, will need tweaking for your gateway
	*/
	//these are the successful codes, all others fail
	$payzarescodes=array('00','08','10','11','16');
	if($eshopaction=='payzaipn'){
		if($postit['payzaTrxnStatus']=='False' && !in_array($postit['payzaresponseCode'],$payzarescodes))
			$echo .= '<p>There was a problem with your order, please contact admin@ ... quoting Error Code '.$postit['payzaresponseCode']."</p>\n";
	}
	return $echo;
}
?>
