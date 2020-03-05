<?php
if( !class_exists('Adifier_Stripe') ) {
class Adifier_Stripe{
	/*
	Add stripe options to the theme options
	*/
	static public function register_in_options( $sections ){
        $sections[] = array(
            'title'     => esc_html__('Stripe', 'adifier') ,
            'icon'      => '',
            'subsection'=> true,
            'desc'      => esc_html__('Configure Stripe payment.', 'adifier'),
            'fields'    => array(
                array(
                    'id'        => 'enable_stripe',
                    'type'      => 'select',
                    'options'   => array(
                        'yes'       => esc_html__( 'Yes', 'adifier' ),
                        'no'        => esc_html__( 'No', 'adifier' )
                    ),
                    'title'     => esc_html__('Enable Stripe', 'adifier') ,
                    'desc'      => esc_html__('Enable or disable payment via Stripe', 'adifier'),
                    'default'   => 'no'
                ),
                array(
                    'id'        => 'pk_client_id',
                    'type'      => 'text',
                    'title'     => esc_html__('Public Client ID', 'adifier') ,
                    'desc'      => esc_html__('Input your stripe public client ID', 'adifier'),
                ),
                array(
                    'id'        => 'sk_client_id',
                    'type'      => 'text',
                    'title'     => esc_html__('Secret Client ID', 'adifier') ,
                    'desc'      => esc_html__('Input your stripe secret client ID', 'adifier'),
				),
            )
        );

        return $sections;
    }

	/*
	Check payment method
	*/
	static public function start_payment(){
		if( self::is_enabled() ){
			if( !empty( $_GET['screen'] ) && in_array( $_GET['screen'], array( 'ads', 'acc_pay' ) ) ){
				add_action( 'wp_footer', 'Adifier_Stripe::add_modal' );
				add_action( 'wp_enqueue_scripts', 'Adifier_Stripe::enqueue_scripts' );
				add_action( 'adifier_payment_methods', 'Adifier_Stripe::render' );
			}

			/* this is executed fro the backgriound */
			add_action( 'adifier_refund_stripe', 'Adifier_Stripe::refund', 10, 2 );
			add_filter( 'adifier_payments_dropdown', 'Adifier_Stripe::select_dropdown' );

			add_action('wp_ajax_stripe_create_payment', 'Adifier_Stripe::create_payment');
			add_action('wp_ajax_stripe_verify_payment', 'Adifier_Stripe::verify_payment');
		}
	}  

	static public function select_dropdown( $dropdown ){
		$dropdown['stripe'] = esc_html__( 'Stripe', 'adifier' );
		return $dropdown;
	}


	/*
	First checn if stripe is configured and enabled
	*/
	static public function is_enabled(){
		$enable_stripe = adifier_get_option( 'enable_stripe' );
		$pk_client_id = adifier_get_option( 'pk_client_id' );
		$sk_client_id = adifier_get_option( 'sk_client_id' );
		if( $enable_stripe == 'yes' && !empty( $pk_client_id ) && !empty( $sk_client_id ) ){
			return true;
		}
		else{
			return false;
		}
	}

	/*
	Add required scripts and styles
	*/
	static public function enqueue_scripts(){		
		wp_enqueue_script('adifier-stripe-checkout', 'https://js.stripe.com/v3/', false, false, true);
		wp_enqueue_script('adifier-stripe', get_theme_file_uri( '/js/payments/stripe.js' ), array('jquery', 'adifier-purchase'), false, true);

		wp_enqueue_style( 'adifier-stripe', get_theme_file_uri( '/css/payments/stripe.css' ) );
	}

	/*
	Add stripe to the list of the available payments in the frontend
	*/
	static public function render(){
		?>	
		<li>
			<a href="javascript:void(0);" id="stripe-button">
				<img src="<?php echo esc_url( get_theme_file_uri( '/images/stripe.png' ) ); ?>" alt="stripe" width="148" height="42">
			</a>
		</li>
		<?php
	}

	/*
	Execute refund
	*/
	static public function refund( $order, $order_transaction_id ){
	    $data = self::http( 'refunds', array(
		    'charge' => $order_transaction_id,
		));

		if( !empty( $data->status ) && $data->status == 'succeeded' ){
			Adifier_Order::mark_as_refunded( $order );
		}
	}

	/*
	Create price
	*/
	static public function check_price( $price, $abbr ){
		$no_decimals = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
		if( in_array( $abbr, $no_decimals ) ){
			return $price;
		}
		else{
			return $price * 100;
		}
	}

	/*
	Add modal
	*/
	static public function add_modal(){
		?>
		<div class="modal in" id="stripecard" tabindex="-1" role="dialog">
			<div class="modal-dialog">

				<!-- Modal content-->
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title"><?php esc_html_e( 'Card Details', 'adifier' ) ?></h5>
						<a href="#" data-dismiss="modal"><i class="aficon-times"></i></a>
					</div>
					<div class="modal-body">
						<div id="card-element"></div>
					</div>
					<div class="modal-footer">
						<div class="flex-right">
							<button type="button" class="af-button stripe-pay"><?php esc_html_e( 'Pay Now', 'adifier' ) ?> <i class="aficon-spin aficon-circle-notch"></i></button>
						</div>
					</div>
				</div>

			</div>
		</div>		
		<?php
	}

	/*
	Return data for payment initiation
	*/
	static public function create_payment(){
		$order = Adifier_Order::get_order();
		$order_id = Adifier_Order::create_transient( $order );

		if( !empty( $order['price'] ) ){

			$currency_abbr = adifier_get_option( 'currency_abbr' );

			$data = self::http( 'payment_intents', array(
				'amount' 					=> self::check_price( $order['price'], $currency_abbr ),
				'currency' 					=> $currency_abbr,
				'metadata'  				=> array(
					'order_id' => $order_id
				)
			));

			if( !empty( $data->client_secret ) ){
				$response = array(
					'client_secret'  => $data->client_secret,
					'key'		  	 => adifier_get_option( 'pk_client_id' )
				);
			}
			else{
				$response['error'] = esc_html__( 'Could not generate session ID', 'adifier' );
			}
		}
		else{
			$response['error'] = esc_html__( 'Can not process your request, contact administration', 'adifier' );
		}

		echo json_encode( $response );
		die();		
	}

	/*
	First we create payment which will user approve and send ID to JS script
	*/
	static public function verify_payment(){
		$intent_id = $_POST['intent_id'];

		$data = self::http('payment_intents/'.$intent_id, array(), 'GET');
		if( !empty( $data->id ) && $data->status == 'succeeded' ){
			$order = get_transient( $data->metadata->order_id );
			if( !empty( $order ) ){
				$response = Adifier_Order::create_order(array(
					'order_payment_type' 	=> 'stripe',
					'order_transaction_id' 	=> $data->id,
					'order_id'				=> $data->metadata->order_id,
					'order_paid'			=> 'yes'
				));
			}
			else{
				$response = array( 'error' => esc_html__( 'Your order has expired', 'adifier' ) );
			}			
		}
		else{
			$response = array( 'error' => esc_html__( 'Wrong intent ID', 'adifier' ) );
		}
	    
		echo json_encode( $response );
		die();    
	}

	/*
	To http post for stripe
	*/
	static public function http( $checkpoint, $data, $method = 'POST' ){
	    $response = wp_remote_post( 'https://api.stripe.com/v1/'.$checkpoint, array(
	        'method' => $method,
	        'timeout' => 45,
	        'redirection' => 5,
	        'httpversion' => '1.0',
	        'blocking' => true,
	        'headers' => array(
	            'Authorization' => 'Bearer '.adifier_get_option( 'sk_client_id' )
	        ),
	        'body' => $data,
	        'cookies' => array()
	    ));

		if ( is_wp_error( $response ) ) {
		} 
		else{			
		   	return json_decode( $response['body']);		   	
		}
	}

}
add_filter( 'init', 'Adifier_Stripe::start_payment' );
add_filter( 'adifier_payment_options', 'Adifier_Stripe::register_in_options' );
}
?>