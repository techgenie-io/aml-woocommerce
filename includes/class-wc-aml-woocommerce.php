<?php
/**
 * AML Integration.
 *
 * @package  WC_Integration_AML
 * @category Integration
 * @author   AML
 */
defined('ABSPATH') or die();
if (!class_exists('WC_Integration_AML')) :

    class WC_Integration_AML extends WC_Integration {
        private $enabled_extension = '';
        private $api_key 			= '';
		private $api_rpe_url 		= '';
		private $api_rpf_url 		= '';
		private $status		 		= '';
        
		
		/**
         * Initializes and hook in the integration.
         */
        public function __construct() {
            $this->namespace = 'woocommerce-aml';

            // Define user set variables.
            $this->enabled_extension 	= $this->get_setting( 'api_enabled' );
            $this->api_key 				= $this->get_setting('api_key');
            $this->api_rpe_url 			= $this->get_setting('api_rpe_url');
			$this->api_rpf_url 			= $this->get_setting('api_rpf_url');
			$this->status				= $this->get_setting('api_statuses');

            // Admin Actions.
            add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 70);
            add_action('woocommerce_settings_tabs_' . $this->namespace, array($this, 'tab_content'));
            add_action('woocommerce_update_options_' . $this->namespace, array($this, 'update_settings'));

            //Admin Notices
            add_action('admin_notices', array($this, 'admin_notifications'));
        
			// Comply Advantage
			add_action( 'woocommerce_new_order', array($this, 'send_data_to_api') );
			
			// Redirect to Custom Page
			add_action( 'woocommerce_thankyou', array($this, 'redirect_to_custom_page'),99,1 );
		}
		
		
		
  
		function redirect_to_custom_page( $order_id ){
			$order = wc_get_order( $order_id );			
			$order_api_status = $order->get_meta('ComplyAdvantage_API');
			$url = "";		
			
			$allow_status = $this->status;
			$match_allow_status = in_array( $order_api_status, $allow_status );
			
			if( $match_allow_status != 1 ){
				
				$order->update_status('hold');				
				$currLang = get_bloginfo('language');
				if( $currLang == 'en-CA'){	
					$url = $this->api_rpe_url;	
				} else {
					$url = $this->api_rpf_url;
				}
				
				wp_safe_redirect( $url );
				exit;
			}
		
		}

		public function checkEyeFraudConfig(){
            
			if($this->enabled_extension == 'yes' and !empty($this->api_key)){
                return true;
            }else{
                return false;
            }
        }
		
		public function send_data_to_api( $order_id ){
			
			if(!$this->checkEyeFraudConfig()){
                return false;
            }
			
			$DOB = $_POST['user_dob'];
			$DOB = explode(" ", $DOB);
			if ( !empty ( $DOB ) ){ $year = $DOB[2];} else { return false; }
			
			$first_name = $_POST['billing_first_name']; 
			if(empty($first_name)){ return false; }
			
			$last_name = $_POST['billing_last_name']; 
			if(empty($last_name)){ return false; }
			
			$name = $first_name." ".$last_name;
			
			$curl = curl_init();

			curl_setopt_array($curl, array(
			  CURLOPT_URL => "https://api.complyadvantage.com/searches?api_key=$this->api_key",
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => "",
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 30,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => "POST",
			  CURLOPT_POSTFIELDS => "{\"search_term\": \"$name\",\"exact_match\": true,\"filters\": {\"birth_year\": \"$year\",\"entity_type\": \"person\"}}",
			  CURLOPT_HTTPHEADER => array(),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);

			curl_close($curl);

			if ($err) {
			  echo "cURL Error #:" . $err;
			} else {
				
				$response = json_decode( $response ); 
			    $api_status = $response->content->data->match_status;
			
				$allow_status = $this->status;
				
				$match_allow_status = in_array( $api_status, $allow_status );
				
				if( $match_allow_status == 1 ){
					
					$order = wc_get_order( $order_id );
					$order->update_meta_data( 'ComplyAdvantage_API', $api_status );
					$order->save();
				
				}else{
					
					$order = wc_get_order( $order_id );
					$order->update_meta_data( 'ComplyAdvantage_API', $api_status );
					$order->save();
					WC()->cart->empty_cart();
				}					
			}
		}
		
        /**
         * Add notification in dashboard.
         */
        public function admin_notifications() {
            if (get_user_meta(get_current_user_id(), 'fraudlabspro_woocommerce_admin_notice', true) === 'dismissed') {
                return;
            }

            $currentscr = get_current_screen();

            if ('plugins' == $currentscr->parent_base) {
                if (!$this->api_key) {
                    $settings_url = admin_url('admin.php?page=wc-settings&tab=woocommerce-aml');

                    echo '
				<div id="fraudlabspro-woocommerce-notice" class="error notice is-dismissible">
					<p>
						' . __(' AML WooCommerce setup is not complete. Please go to <a href="' . $settings_url . '">setting page</a> to enter your API key.', 'woocommerce-aml') . '
					</p>
				</div>
				';
                }

                
            }
        }

		/**
         * Add tab into settting page.
         */
        public function add_settings_tab($settings_tabs) {
            $settings_tabs[$this->namespace] = __('AML WooCommerce', 'woocommerce-aml');
            return $settings_tabs;
        }

        /**
         * Add fields into tab content.
         */
        public function tab_content() {
            woocommerce_admin_fields($this->get_fields());
        }

        /**
         * Update settings into WooCommerce
         */
        public function update_settings( $array ) {
			
			
			woocommerce_update_options($this->get_fields());
			
		}
		


        /**
         * Define setting fields.
         */
        private function get_fields() {
            $setting_fields = array(
                'settings_section' => array(
                    'title' => __('AML Settings', 'woocommerce-aml'),
                    'type' => 'title',
                    'desc' => '',
                ),

                'api_enabled' => array(
                    'id' => 'wc_settings_' . $this->namespace . '_api_enabled',
                    'name' => __('Enable / Disable', 'woocommerce-aml'),
                    'type' => 'checkbox',
                    'default' => '',
                    'css' => 'width:50%',
                ),
                'api_key' => array(
                    'id' => 'wc_settings_' . $this->namespace . '_api_key',
                    'name' => __('API Key', 'woocommerce-aml'),
                    'type' => 'text',
                    'default' => '',
                    'css' => 'width:50%',
                ),
               'api_statuses' => array(
                    'id' => 'wc_settings_' . $this->namespace . '_api_statuses',
                    'name' => __('Allowed API Status', 'woocommerce-aml'),
                    'type' => 'multiselect',
                    'css' => 'width:50%',
						'options' => array(

							  'false_positive' => __( 'false_positive' ),
							  'true_positive' => __('true_positive'),
							  'potential_match' => __('potential_match'),
							  'no_match' => __('no_match'),
							  'unknown' => __('unknown')

						),
				),
			   'api_rpe_url' => array(
                    'id' => 'wc_settings_' . $this->namespace . '_api_rpe_url',
                    'name' => __('Return Page English URL', 'woocommerce-aml'),
                    'type' => 'text',
                    'default' => '',
                    'css' => 'width:50%',
                ),
				'api_rpf_url' => array(
                    'id' => 'wc_settings_' . $this->namespace . '_api_rpf_url',
                    'name' => __('Return Page French URL', 'woocommerce-aml'),
                    'type' => 'text',
                    'default' => '',
                    'css' => 'width:50%',
                ),
				'settings_section_end' => array(
                    'type' => 'sectionend',
                ),
            );

            return apply_filters('wc_settings_tab_' . $this->namespace, $setting_fields);
        }

        /**
         * Get setting value by key.
         */
        public function get_setting($key) {
            $fields = $this->get_fields();
            return apply_filters('wc_option_' . $key, get_option('wc_settings_' . $this->namespace . '_' . $key, ( ( isset($fields[$key]) && isset($fields[$key]['default']) ) ? $fields[$key]['default'] : '')));
        }

    }
endif;