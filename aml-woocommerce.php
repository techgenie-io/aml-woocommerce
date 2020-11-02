<?php

/**
 * Plugin Name: AML WooCommerce
 * Author: IKE 
 * Author URI: https://techgenie.io/
 * Version: 0.0.1
 *
 */
if (!class_exists('AML_WooCommerce')) :

    class AML_WooCommerce {

        /**
         * Construct the plugin.
         */
        public function __construct() {

            add_action('plugins_loaded', array($this, 'init'));
        }

        /**
         * Initialize the plugin.
         */
        public function init() {

            // Checks if WooCommerce is installed.
            if (class_exists('WC_Integration')) {

                // Include our integration class.
                include_once 'includes/class-wc-aml-woocommerce.php';

                // Register the integration.
                add_filter('woocommerce_integrations', array($this, 'add_integration'));

                $this->_check_dependencies();
            }
        }

        /**
         * Check dependencies.
         *
         * @throws Exception
         */
        protected function _check_dependencies() {
            if (!function_exists('WC')) {
                throw new Exception(__('AML requires WooCommerce to be activated', 'woocommerce-gateway-paypal-express-checkout'));
            }

            if (version_compare(WC()->version, '2.5', '<')) {
                throw new Exception(__('AML requires WooCommerce version 2.5 or greater', 'woocommerce-gateway-paypal-express-checkout'));
            }
        }

        /**
         * Add a new integration to WooCommerce.
         */
        public function add_integration($integrations) {
            $integrations[] = 'WC_Integration_AML';

            return $integrations;
        }


    }

// Only initialize plugin if WooCommerce is activated
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        $AML_WooCommerce = new AML_WooCommerce(__FILE__);
    }

endif;
?>