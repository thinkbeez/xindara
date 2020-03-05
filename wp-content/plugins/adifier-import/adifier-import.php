<?php
/*
Plugin Name: Adifier Import
Description: Make sure that you have installed WP All Import in order to use this functionality
Version: 1.2
Author: SpoonThemes
*/

include "rapid-addon.php";

class Adifier_Advert_Import{

	private $addon;

	public function __construct(){
		$this->addon = new RapidAddon( 'Adifier Addon', 'adifier' );
		$this->addon->add_title( __( 'Ad Details', 'adifier' ) );	

		$this->addon->admin_notice( __( 'Adifier recommends that you install WP All Import In order to use import functionality', 'adifier' ) );	

		$this->addon->run(
			array(
				"post_types" => array( "advert" )
			)
		);

		add_filter( 'wp_all_import_images_uploads_dir', array( $this, 'clear_gallery' ), 10, 4 );

		$this->addon->set_import_function( array( $this, 'process_import' ) );
		add_action( 'init', array( $this, '_add_fields' ) );
		//$this->_add_fields();
	}

	public function clear_gallery( $uploads, $articleData, $current_xml_node, $post_id ){
		delete_post_meta( $articleData['ID'], 'advert_gallery' );
		return $uploads;
	}

	public function process_import( $post_id, $data, $import_options ){
		if ( $this->addon->can_update_meta( 'advert_type', $import_options ) ) {
			if( !empty( $data['advert_type'] ) ){
				global $wpdb;

				if( !is_numeric( $data['advert_expire'] ) ){
					$data['advert_expire'] = strtotime( $data['advert_expire'] );
				}

				if( empty( $data['advert_expire'] ) ){
					if( $data['advert_type'] == '2' ){
						$expire_time = adifier_get_option( 'auction_expires' );
					}
					else{
						$expire_time = adifier_get_option( 'regular_expires' );
					}
					$data['advert_expire'] = current_time('timestamp') + ( $expire_time * 86400 );
				}

				$update_data = array(
					'latitude'		=> $data['gmap_latitude'],
					'longitude'		=> $data['gmap_longitude'],
					'price'			=> $data['advert_price'],
					'sale_price'	=> $data['advert_sale_price'],
					'expire'		=> $data['advert_expire'],
					'sold'			=> $data['advert_sold'],
					'type'			=> $data['advert_type'],
					'start_price'	=> $data['advert_start_price'],
					'cond'			=> $data['advert_cond'],
					'views'			=> $data['advert_views'],
					'currency'		=> !empty( $data['advert_currency'] ) ? $data['advert_currency'] : '',
				);

				if( $data['advert_type'] == '2' ){
					update_post_meta( $post_id, 'advert_reserved_price', $data['advert_reserved_price'] );
				}

				if( $data['advert_type'] == '6' ){
					update_post_meta( $post_id, 'advert_rent_period', $data['advert_rent_period'] );
				}

				update_post_meta( $post_id, 'advert_negotiable', $data['advert_negotiable'] );

				
				foreach( $update_data as $key => $value ){
					if( $value != '' ){
						adifier_save_advert_meta( $post_id, str_replace( 'advert_', '', $key ), $value );
					}
				}

				if( !empty( $data['advert_videos'] ) ){
					delete_post_meta( $post_id, 'advert_videos' );
					$videos = explode( '|', $data['advert_videos'] );
					if( !empty( $videos ) ){
						foreach( $videos as $video ){
							if( !empty( $video ) ){
								add_post_meta( $post_id, 'advert_videos', $video );
							}
						}
					}
				}

				if( !empty( $data['advert_phone'] ) ){
					update_post_meta( $post_id, 'advert_phone', $data['advert_phone'] );
				}

				if( !empty( $data['gmap_latitude'] ) ){
					$location = array(
						'lat' 		=> $data['gmap_latitude'],
						'long' 		=> $data['gmap_longitude'],
						'country' 	=> $data['gmap_country'],
						'state' 	=> $data['gmap_state'],
						'city' 		=> $data['gmap_city'],
						'street' 	=> $data['gmap_street'],
					);
					update_post_meta( $post_id, 'advert_location', $location );
				}
			}
			else{
				$this->addon->log( __( 'Adifier - missing ad type, skipping this ad', 'adifier' ) );
			}
		}
	}

	public function _add_fields(){
		$this->addon->add_field( 
			'advert_type', 
			__( 'Ad Type', 'adifier' ), 
			'radio',
			array(
				'1' => esc_html__( 'Sell (For mapping value 1)', 'adifier' ),
				'2' => esc_html__( 'Auction (For mapping value 2)', 'adifier' ),
				'3' => esc_html__( 'Buy (For mapping value 3)', 'adifier' ),
				'4' => esc_html__( 'Exchange (For mapping value 4)', 'adifier' ),
				'5' => esc_html__( 'Gift (For mapping value 5)', 'adifier' ),
				'6' => esc_html__( 'Rent (For mapping value 6)', 'adifier' ),
				'7' => esc_html__( 'Job - Offer (For mapping value 7)', 'adifier' ),
				'8' => esc_html__( 'Job - Wanted (For mapping value 8)', 'adifier' ),
			)
		);

		if( function_exists( 'adifier_get_currencies' ) ){
			$currencies = adifier_get_currencies();
			if( count( $currencies ) > 1 ){
				foreach( $currencies as $key => $data ){
					$list[$key] = $key. '(For mapping value '.$key.')';				}
				$this->addon->add_field( 
					'advert_currency', 
					__( 'Ad Currency', 'adifier' ), 
					'radio',
					$list
				);	
			}
		}

		$this->addon->disable_default_images();

		$this->addon->add_field( 'advert_videos', __( 'Videos', 'adifier' ), 'text', null, __( 'For multiple values divide with pile "|"', 'adifier' ) );

		$this->addon->add_field( 'advert_price', __( 'Price', 'adifier' ), 'text' );
		$this->addon->add_field( 'advert_sale_price', __( 'Sale Price', 'adifier' ), 'text' );
		$this->addon->add_field( 'advert_start_price', __( 'Start Price', 'adifier' ), 'text', null, __( 'If the type is Auction', 'adifier' ) );
		$this->addon->add_field( 'advert_reserved_price', __( 'Reserved Price', 'adifier' ), 'text', null, __( 'If the type is Auction', 'adifier' ) );

		if( function_exists( 'adifier_get_rent_periods' ) ){
			$rent_periods = adifier_get_rent_periods();
			$list = array(
				'' => esc_html__( 'None', 'adifier' )
			);
			foreach( $rent_periods as $key => $rent_period ){
				$list[$key] = $rent_period. '(For mapping value '.$key.')';
			}
			$this->addon->add_field( 
				'advert_rent_period', 
				__( 'Rent Period', 'adifier' ), 
				'radio',
				$list
			);
		}

		$this->addon->add_field( 'advert_expire', __( 'Expire', 'adifier' ), 'text', null, __( 'If this is empty system will apply values from theme settings', 'adifier' ) );

		$this->addon->add_field( 'advert_views', __( 'Views', 'adifier' ), 'text' );

		$this->addon->add_field( 
			'advert_sold', 
			__( 'Is Sold', 'adifier' ), 
			'radio',
			array(
				'0' => __( 'No (For mapping value 0)', 'adifier' ),
				'1' => __( 'Yes (For mapping value 1)', 'adifier' ),
			)
		);

		$this->addon->add_field( 
			'advert_negotiable', 
			__( 'Is Negotiable', 'adifier' ), 
			'radio',
			array(
				'0' => __( 'No (For mapping value 0)', 'adifier' ),
				'1' => __( 'Yes (For mapping value 1)', 'adifier' ),
			)
		);		

		$this->addon->add_field( 
			'advert_cond', 
			__( 'Condition', 'adifier' ), 
			'radio',
			array(
				'0' => __( 'None (For mapping value 0)', 'adifier' ),
				'1' => __( 'New (For mapping value 1)', 'adifier' ),
				'2' => __( 'Manufacturer Refurbished (For mapping value 2)', 'adifier' ),
				'3' => __( 'Used (For mapping value 3)', 'adifier' ),
				'4' => __( 'For Parts Or Not Working (For mapping value 4)', 'adifier' ),
			)
		);


		$this->addon->add_field( 'gmap_latitude', __( 'Gmap Latitude Location', 'adifier' ), 'text' );
		$this->addon->add_field( 'gmap_longitude', __( 'Gmap Longitude Location', 'adifier' ), 'text' );
		$this->addon->add_field( 'gmap_country', __( 'Gmap Country', 'adifier' ), 'text' );
		$this->addon->add_field( 'gmap_state', __( 'Gmap State', 'adifier' ), 'text' );
		$this->addon->add_field( 'gmap_city', __( 'Gmap City', 'adifier' ), 'text' );
		$this->addon->add_field( 'gmap_street', __( 'Gmap Street', 'adifier' ), 'text' );

		$this->addon->add_field( 'advert_phone', __( 'Phone', 'adifier' ), 'text' );

		$this->addon->import_images( 'advert_gallery', __( 'Ad Images', 'adifier' ) );
		
	}
}

function advert_gallery( $post_id, $data, $import_options ){
	if( get_post_thumbnail_id( $post_id ) !== $data ){
		add_post_meta( $post_id, 'advert_gallery', $data );
	}
}

$adf_import = new Adifier_Advert_Import();
?>