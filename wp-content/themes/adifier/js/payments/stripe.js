jQuery(document).ready(function($){
	"use strict";

	$('#stripecard').on('hidden.bs.modal', function () {
		$('#purchase').modal('show');
		$('#card-element').html('');
		$('.stripe-pay').off('click'); 
		$('.purchase-loader').hide();
	});

	$('#stripecard').on('show.bs.modal', function (e) {
		$('#purchase').modal('hide');
		$('.purchase-loader').show();
	});

	var ajaxing = false;

	$(document).on( 'click', '#stripe-button', function(e){
		e.preventDefault();
		$('.purchase-loader').show();
		$.ajax({
			url: adifier_data.ajaxurl,
			data:{
				action: 'stripe_create_payment',
				order: $('#purchase textarea').val()
			},
			method: 'POST',
			dataType: "JSON",
			success: function(response){
				if( !response.error ){
					$('#stripecard').modal('show');
					var stripe = Stripe( response.key );
					var elements = stripe.elements();
					var elementStyles = {
						base: {
							color: '#32325d',
							fontFamily: '"Open Sans", "Helvetica Neue", Helvetica, sans-serif',
							fontSmoothing: 'antialiased',
							fontSize: '16px',
							'::placeholder': {
							  color: '#aab7c4'
							}
						},
						invalid: {
							color: '#fa755a',
							iconColor: '#fa755a'
						},
					};

					var cardElement = elements.create('card', {
						style: elementStyles,
					});
					cardElement.mount('#card-element');

					$('.stripe-pay').on('click', function(){
						if( ajaxing ){
							return false;
						}
				
						ajaxing = true;

						$('.stripe-pay i ').show();
						stripe.handleCardPayment( 
							response.client_secret,
							cardElement
						).then( function(result){
							if (result.error) {
								alert( result.error.message );
								$('.stripe-pay i ').hide();
								ajaxing = false;
							}
							else{
								$.ajax({
									url: adifier_data.ajaxurl,
									data: {
										action: 'stripe_verify_payment',
										intent_id: result.paymentIntent.id
									},
									method: 'POST',
									dataType: "JSON",
									success: function(res){
										if( !res.error ){
											$('#stripecard').modal('hide');
											$(document).trigger('adifier_payment_completed', [res]);
										}
										else{
											alert( res.error );
										}
									},
									complete: function(){
										$('.stripe-pay i ').hide();
										ajaxing = false;
									}
								})
							}
						});
					})
				}
				else{
					alert( response.error );
				}
			},
			complete: function(){
				$('.purchase-loader').hide();
			}
		});
	});	
});