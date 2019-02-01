<?php

	/*
	 * Plugin Name: Cuepay - WooCommerce
	 * Plugin URI: https://cuepay.com/developer/
	 * Description: This payment gateway accepts local ( Nigeria ) and international cards - Mastercard, Visa, and Verve cards on your Woocommerce store.
	 * Version: 1.0.0
	 * Author: Freeman
	 * Author URI: https://freeman.com.ng
	 * Release Date: September 11, 2017.
	*/


	if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	add_action('plugins_loaded', 'woocommerce_cuepay_init', 0);


	function woocommerce_cuepay_init() {


		if (!class_exists('WC_Payment_Gateway')) return;

		if ( class_exists('WC_Cuepay'         )) return;


		class WC_Cuepay extends WC_Payment_Gateway {
				

			public function __construct() {

				
				$this->id		          = 'cuepay';

				$this->icon 			  = apply_filters('woocommerce_cuepay_icon', 'https://cuepay.com/assets/images/wp_logo.png');

				$this->has_fields 	      =  false;

				$this->request_url        = 'https://cuepay.com/secure/?pay=invoice';

				$this->query_url          = 'https://cuepay.com/secure/query/';

				$this->method_title 	  = __('Cuepay ©', 'woocommerce' );

				$this->method_description = 'MasterCard, Visa and Verve Card accepted';


				
				// Load the form fields.
				
				$this->init_form_fields();

				
				// Load the settings.
				
				$this->init_settings();

				
				// Define user set variables
	
				$this->title 			= $this->get_option( 'title' 		   );
				$this->description 		= $this->get_option( 'description'     );
				$this->merchant_id		= $this->get_option( 'merchant_id'     );
				$this->secret_key		= $this->get_option( 'secret_key'      );
				$this->success_message	= $this->get_option( 'success_message' );
				$this->decline_message	= $this->get_option( 'decline_message' );

				
				// Actions

				add_action( 'woocommerce_update_options_payment_gateways_'. $this->id, array($this, 'process_admin_options'));

				add_action( 'woocommerce_receipt_' . $this->id, array($this, 'order_placed'    ));

				add_action( 'woocommerce_api_wc_'  . $this->id, array($this, 'cuepay_response' ));


				if ( !$this->is_valid_for_use() ) { $this->enabled = false; }


				/* Only add the naira currency and symbol if WC versions is less than 2.1 */

				if ( version_compare( WOOCOMMERCE_VERSION, '2.1') <= 0 )
				{

					add_filter('woocommerce_currencies', array($this, 'add_ngn_currency'));

					add_filter('woocommerce_currency_symbol', array($this, 'add_ngn_currency_symbol'), 10, 2);


					function add_ngn_currency($currencies)
					{
					     $currencies['NGN'] = __( 'Nigerian Naira (NGN)', 'woocommerce' );

					     return $currencies;
					}

					
					function add_ngn_currency_symbol($symbol, $currency)
					{
						switch( $currency )
						{
							case 'NGN': $symbol = '₦'; break;
						}

						return $currency_symbol;
					}
				}


				/* Handle cuepay response here */

				$this->cuepay_response();

			}
		    
		    
			function is_valid_for_use() {

				if (!in_array(get_woocommerce_currency(), array('NGN')))
				{
				    return false;
				}
			
				return true;
			}
		    
			function admin_options() {
		

				echo '<p>&nbsp;</p>';
				echo '<hr>';
				echo '<h3>' . __('Cuepay ©', 'woocommerce') . '</h3>';
				echo '<p>' .  __('The payment gateway desiged to accept local ( Nigeria ) and international cards - Mastercard, Visa, and Verve cards on your Woocommerce store.', 'woocommerce') . '</p>';
				
				echo '</br>';
				echo '<hr>';

				echo '<table class="form-table">';
					
				if ( $this->is_valid_for_use() ) {

					$this->generate_settings_html();

				} else {
					echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', 'woocommerce' ) . '</strong>: ' . __( 'Cuepay does not support your store currency.', 'woocommerce' ) . '</p></div>';
				}
					
				echo '</table>';
			}
		   

		    
		    function init_form_fields() {


				$this->form_fields = array(

					'enabled' => array(
									'title' => __( 'Status', 'woocommerce' ), 
									'type' => 'checkbox', 
									'label' => __( 'Enable', 'woocommerce' ), 
									'default' => 'yes'
								), 
					'merchant_id' => array(
									'title' => __( 'Merchant Identity', 'woocommerce' ), 
									'type' => 'text', 
									'description' => __( 'The cuepay account number ( merchant id ).', 'woocommerce' ), 
									'default' => ''
								),
					'secret_key' => array(
									'title' => __( 'Merchant Secret', 'woocommerce' ), 
									'type'  => 'text', 
									'description' => __( 'The secret required for querying transaction status', 'woocommerce' ),
									'default' => ''
								),
					'title' => array(
									'title' => __( 'Gateway Title', 'woocommerce' ), 
									'type' => 'text', 
									'description' => __( 'The gateway title users would see at checkout.', 'woocommerce' ), 
									'default' => __( 'Cuepay', 'woocommerce' )
								),
					'description' => array(
									'title' => __( 'Gateway Label', 'woocommerce' ), 
									'type' => 'textarea', 
									'description' => __( 'The description users would see at checkout.', 'woocommerce' ), 
									'default' => __('Mastercard, Visa, Verve and other International cards accepted.', 'woocommerce')
								),
					'success_message' => array(
									'title' => __( 'Success Message', 'woocommerce' ), 
									'type' => 'textarea', 
									'description' => __( 'The message to show when a payment is successful.', 'woocommerce' ), 
									'default' => __("Payment completed successfully. Thank you.", 'woocommerce')
								),
					'decline_message' => array(
									'title' => __( 'Decline Message', 'woocommerce' ), 
									'type' => 'textarea',
									'description' => __( 'The message to show when a payment is declined.', 'woocommerce' ), 
									'default' => __("Transaction Failed.", 'woocommerce')
								)
				);
		    
			}


			
			function process_payment( $order_id )
			{

				$order = new WC_Order( $order_id );

				return array('result'=>'success', 'redirect'=> $order->get_checkout_payment_url(true) );

			}


			function order_placed($order_id)
			{

				echo $this->submit_cuepay_form($order_id);

			}


			function submit_cuepay_form($order_id)
			{
							
				$order = new WC_Order( $order_id ); $data  = $this->fetch_cuepay_data( $order );


				if($this->merchant_id == '' || $this->secret_key == '')
				{
					return '<p>The Cuepay Merchant ID and Secret is required to process payments.</p>'; 
				}


				$form  = "<form id=\"cuepay_form\" action=\"{$this->request_url}\" method=\"post\">";

				foreach ($data as $key => $value)
				{
					$form .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$value}\">";
				}

				$form .= "</form/>";

				wc_enqueue_js( 'jQuery(document).ready(function(){ jQuery("#cuepay_form").submit(); });' );

				echo '<p>Redirecting to payment gateway ...</p>';

				return $form;

			}

			
			function fetch_cuepay_data( $order ) {

			
				$order_total     = (number_format($order->get_total(), 2, '.', ''));

				$data            = array
				(
					'merchant'   => $this->merchant_id,
					'amount' 	 => $order_total,
					'reference'  => str_replace('.', '', uniqid( $order->id.'-', true )),
					'customer'	 => $order->billing_email,
					'data-order' => $order->id,
					'data-phone' => $order->billing_phone,
					'redirect'	 => $this->get_return_url($order)
				);
				
				$data = apply_filters('woocommerce_cuepay_args', $data);
				
				return $data;

			}


			function find_order( $token )
			{

				/* find order by id or key */

				if(!is_numeric($token))
				{
				  	$data_store = WC_Data_Store::load( 'order' ); $token = $data_store->get_order_id_by_order_key( $token );				
				}

				return new WC_Order($token);

			}


			function cuepay_response()
			{


				global $woocommerce; if(!isset($_POST['reference'])) { return false; }



				$reference = $_POST['reference']; $order_id = strtok($reference, '-'); $order = new WC_Order($order_id);


				$query_url = $this->query_url.'?'.http_build_query(array('merchant'=> $this->merchant_id, 'reference'=> $reference, 'amount'=>$order->get_total(), 'token'=> hash('sha512', $this->merchant_id.$reference.$this->secret_key)));


				$query     = json_decode(file_get_contents($query_url));  $cookie = " - #: {$reference}";



				if($query == null)
				{

					$order->add_order_note('The status of this payment could not be determined'.$cookie);

					$order->update_status('on-hold'); $order->reduce_order_stock(); $woocommerce->cart->empty_cart();

					return false;
				}



				if($query->status === 'success' && $query->code === '00')
				{

		            $order->payment_complete(); $order->add_order_note('Payment completed successfully'.$cookie);

		            wc_add_notice($this->success_message, 'success');


				}
				else if($query->status === 'in-doubt')
				{

					$order->add_order_note('The transaction amount is not consistent - double check'.$cookie);

					$order->update_status('on-hold'); $order->reduce_order_stock(); $woocommerce->cart->empty_cart();

					wc_add_notice('Payment received but would require verification.', 'notice');


				}
				else if($query->status === 'cancelled')
				{

					$order->add_order_note('Customer cancelled payment'.$cookie); $order->update_status('cancelled');

					wc_add_notice('Payment for this order was cancelled.', 'notice');

				}
				else
				{

					$order->add_order_note('Transaction declined'.$cookie); $order->update_status('failed');

					wc_add_notice($this->decline_message, 'error');

				}

			}

	 	}
		

		/* Add the gateway to WooCommerce */

		function create_cuepay_gateway( $methods ) {

			$methods[] = 'WC_Cuepay'; return $methods;
		}
		
		add_filter('woocommerce_payment_gateways', 'create_cuepay_gateway' );

	
	}


?>
