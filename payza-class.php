<?php
if ('payza-class.php' == basename($_SERVER['SCRIPT_FILENAME']))
     die ('<h2>Direct File Access Prohibited</h2>');
     
/*******************************************************************************
 *                      PHP Payza(AlertPay) IPN Integration Class
 *******************************************************************************
 *      Author:     L.CH.RAJKUMAR
 *      To process an IPN, have your IPN processing file contain:
 *
 *          $p = new extra_class;
 *          if ($p->validate_ipn()) {
 *          ... (IPN is verified.  Details are in the ipn_data() array)
 *          }
 * 
 *******************************************************************************
*/

class payza_class {
    
   var $last_error;                 // holds the last error encountered
   var $ipn_response;               // holds the IPN response from paypal   
   var $ipn_data = array();         // array contains the POST values for IPN
   var $fields = array();           // array holds the fields to submit to paypal
   
   function payza_class() {
       
      // initialization constructor.  Called when class is created.
      $this->last_error = '';
      $this->ipn_response = '';
    
   }
   
   function add_field($field, $value) {
      
      // adds a key=>value pair to the fields array, which is what will be 
      // sent to extra as POST variables.  If the value is already in the 
      // array, it will be overwritten.
      
      $this->fields["$field"] = $value;
   }
	
   function product_post_description($key)
	{
		global $post;
		$custom_field1 = get_post_meta($post->ID, $key, $true);
		if($custom_field1)
		{
			return $key;
		}
		else
		{
		//empty	
		}
	}
   function submit_payza_post() {
      // The user will briefly see a message on the screen that reads:
      // "Please wait, your order is being processed..." and then immediately
      // is redirected to Payza Gateway.
      $echo= "<form method=\"post\" class=\"eshop eshop-confirm\" action=\"".$this->autoredirect."\"><div>\n";
	/*
	*
	* Grab the standard data
	*
	*/
      foreach ($this->fields as $name => $value) {
			$pos = strpos($name, 'amount');
			if ($pos === false) {
			   $echo.= "<input type=\"hidden\" name=\"$name\" value=\"$value\" />\n";
			}else{
				$echo .= eshopTaxCartFields($name,$value);
      	    }
      }
      	/*
	  	* Changes the standard text of the redirect page.
		*/
      $refid=uniqid(rand());
      $echo .= "<input type=\"hidden\" name=\"payzaoption1\" value=\"$refid\" />\n";
      $echo.='<label for="payzasubmit" class="finalize"><small>'.__('<strong>Note:</strong> Submit to finalize order at Payza.','eshop').'</small><br />
      <input class="button submit2" type="submit" id="payzasubmit" name="payzasubmit" value="'.__('Proceed to Checkout &raquo;','eshop').'" /></label>';
	  $echo.="</div></form>\n";
      
      return $echo;
   }
	//function eshop_submit_payza_post($_POST) {
	function eshop_submit_payza_post(){
      // The user will briefly see a message on the screen that reads:
      // "Please wait, your order is being processed..." and then immediately
      // is redirected to Payza.
      
      	/*
	  	*
	  	* Grab the standard data, but adjust for your payment gateway as below
	  	* remember most fields will actually be hidden for POSTing to your gateway
	  	*
		*/
      
      global $eshopoptions, $blog_id;
      $payza = $eshopoptions['payza'];
		$echortn='<div id="process">
         <p><strong>'.__('Please wait, your order is being processed&#8230;','eshop').'</strong></p>
	     <p>'. __('If you are not automatically redirected to Payza, please use the <em>Proceed to Payza</em> button.','eshop').'</p>
  
<form method="post" id="eshopgatpayza" class="eshop" action="'.$payza['gateway_url'].'">
          <p>';
		$replace = array("&#039;","'", "\"","&quot;","&amp;","&");
		$payza = $eshopoptions['payza']; 
		
		/* your changes would replace this section: start*/
		
		$Cost=$_POST['amount'];
		$es_product=get_post_meta( $eshop_product['sku'], '_eshop_product',true );
		$pqty = $_POST['eshop_payzaquantity'];
		$psku = $_POST['eshop_payzasku'];
		$ppdc = $_POST['eshop_payzaproddesc'];
		$payzaURL=$_POST['payzaURL'];
		$refid=$_POST['payzaoption1'];
		
		if($eshopoptions['status']!='live'){
			$payza['id']='87654321';
		}
		if(isset($_POST['tax']))
			//$Cost += $_POST['tax'];
			$Money = $_POST['tax'];
		$echortn.='
				<input type="hidden" name="ap_merchant" value="'.$payza['merchant_email'].'"/>
				<input type="hidden" name="ap_purchasetype" value="'.$payza['purchase_type'].'"/>
				<input type="hidden" name="ap_itemcode" value="'.$psku.'"/>
				<input type="hidden" name="ap_quantity" value="'.$pqty.'"/>
				<input type="hidden" name="ap_taxamount" value="'.$Money.'"/>
				<input type="hidden" name="ap_itemname" value="Item from '.get_bloginfo(name).'" />
				<input type="hidden" name="ap_description" value="'.$ppdc.'"/>
				<input type="hidden" name="ap_amount" value="'.$Cost.'"/>
				<input type="hidden" name="ap_referencenumber" value="'.$refid.'"/>
				<input type="hidden" name="ap_currency" value="'.$eshopoptions['currency'].'"/>
                <input type="hidden" name="ap_returnurl" value="'.$payza['return_url'].'" />
                <input type="hidden" name="ap_cancelurl" value="'.$payza['cancel_url'].'" />    
				<input class="button" type="submit" id="payzasubmit" name="payzasubmit" value="'. __('Proceed to Payza &raquo;','eshop').'" /></p>
			</form>
	  </div>';
	  	/* your changes would replace this section :end	*/
		return $echortn;
   }   
   function validate_ipn() {
      // generate the post string from the _POST vars aswell as load the
      // _POST vars into an arry so we can play with them from the calling
      // script.
      foreach ($_REQUEST as $field=>$value) { 
         $this->ipn_data["$field"] = $value;
      }
     
   }
}  
?>

