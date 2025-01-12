<?php
/*
Plugin Name: Japan Post EMS for WooCommerce
Plugin URI: https://github.com/raphaelsuzuki/japan-post-ems-for-woocommerce/
Description: Adds Japan Post's EMS rates for WooCommerce
Version: 0.1
Author: Raphael Suzuki
Author URI: https://github.com/raphaelsuzuki/
License: GPLv3
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'initialize_japan_post_ems');

function initialize_japan_post_ems() {
    // Check if WooCommerce is active
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        return;
    }

    // Include WooCommerce abstract classes
    if (!class_exists('WC_Shipping_Method')) {
        include_once WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-shipping-method.php';
    }

    add_filter('woocommerce_shipping_methods', 'add_japan_post_ems_methods');
    add_action('plugins_loaded', 'japan_post_ems_load_textdomain');

    class Japan_Post_EMS extends WC_Shipping_Method
    {
        protected $shipping_table = [];
        protected $available_countries = array(
            'First Zone' => array( 
                'KR', 'TW', 'CN', 
            ),
            'Second Zone' => array( 
                'IN', 'ID', 'KH', 'SG', 'LK', 'TH', 'NP', 'PK', 'BD', 'PH', 'BT', 'BN', 'VN', 'HK', 'MO', 'MY', 'MM', 'MV', 'MN', 'LA', 
            ),
            'Third Zone' => array(
                'AU', 'CK', 'SB', 'NC', 'NZ', 'PG', 'FJ', 
                'CA', 'PM', 'MX', 'AE', 'IL', 'IQ', 'IR', 'OM', 'QA', 'KW', 'SA', 'SY', 'TR', 'BH', 'JO', 'LB', 
                'IS', 'IE', 'AZ', 'AD', 'IT', 'UA', 'GB', 'EE', 'AT', 'NL', 'GG', 'MK', 'CY', 'GR', 'HR', 'SM', 'JE', 'CH', 'SE', 'ES', 'SK', 'SI', 'CZ', 'DK', 'DE', 'NO', 'HU', 'FI', 'FR', 
                'BG', 'BY', 'BE', 'PL', 'PT', 'MT', 'MC', 'LV', 'LT', 'LI', 'LU', 'RO', 'RU', 
            ),
            'Fourth Zone' => array( 
                'US', 
            ),
            'Fifth Zone' => array( 
                'AR', 'UY', 'EC', 'SV', 'GP', 'CU', 'CR', 'CO', 'JM', 'CL', 'TT', 'PA', 'PY', 'BB', 'GF', 'BR', 'VE', 'PE', 'HN', 'MQ', 
                'DZ', 'UG', 'EG', 'ET', 'GH', 'GA', 'KE', 'CI', 'SL', 
                'DJ', 'ZW', 'SD', 'SN', 'TZ', 'TN', 'TG', 'NG', 'BW', 'MG', 'ZA', 'MU', 'MA', 'RW', 'RE', 
            )
        );

        public function __construct($id, $title, $description)
        {
            $this->id = $id;
            $this->method_title = __($title, 'japan-post-ems');
            $this->method_description = __($description, 'japan-post-ems');

            $this->init_form_fields();
            $this->init_settings();

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'japan-post-ems'),
                    'type' => 'checkbox',
                    'description' => __('Enable the Japan Post EMS shipping method.', 'japan-post-ems'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'japan-post-ems'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'japan-post-ems'),
                    'default' => $this->method_title
                )
            );
        }

        public function calculate_shipping($package = array())
        {
            $shipping_rates = array();
            $country = sanitize_text_field($package['destination']['country']);
        
            foreach ($this->available_countries as $zone => $countries) {
                if (in_array($country, $countries)) {
                    $this->shipping_table = $this->get_zone_rates($zone); 
        
                    // Calculate total weight of items in the cart
                    $total_weight = 0;
                    foreach ($package['contents'] as $item) {
                        $item_weight = $item['data']->get_weight(); 
                        $item_weight = is_numeric($item_weight) ? floatval($item_weight) : 0; 
                        $total_weight += $item['quantity'] * $item_weight; 
                    }
        
                    // Find the applicable rate or determine if overweight
                    $overweight = false;
                    foreach ($this->shipping_table as $row) {
                        if ($total_weight >= $row['min'] && $total_weight <= $row['max']) {
                            $rate = array(
                                'id' => $this->id . '_' . $zone, 
                                'label' => $this->get_option('title') . ' (' . $zone . ')',
                                'cost' => $row['cost'], 
                                'taxes' => false
                            );
                            $shipping_rates[] = $rate;
                            break; 
                        } elseif ($total_weight > $row['max']) {
                            // If weight exceeds the current row's max, continue to check the next row
                            continue; 
                        } else {
                            // If weight is below the current row's min, it's definitely overweight
                            $overweight = true; 
                            break; 
                        }
                    }
        
                    // If overweight, display an error message and disable the method
                    if ($overweight) {
                        wc_add_notice(__('The total weight of your order exceeds the maximum limit for this shipping method.', 'japan-post-ems'), 'error');
                        return;
                    }
        
                    // Break out of the loop after finding a match or determining overweight
                    break; 
                }
            }
        
            // Only add rates if a match was found and no error occurred
            if (!empty($shipping_rates)) {
                foreach ($shipping_rates as $rate) {
                    $this->add_rate($rate);
                }
            }
        }

        private function get_zone_rates($zone) {
            // Define shipping rates for each zone here
            switch ($zone) {
                case 'First Zone':
                    return array(
                        array( 'min' => 0, 'max' => 500, 'cost' => 1450 ),
                        array( 'min' => 501, 'max' => 600, 'cost' => 1600 ),
                        array( 'min' => 601, 'max' => 700, 'cost' => 1750 ),
                        array( 'min' => 701, 'max' => 800, 'cost' => 1900 ),
                        array( 'min' => 801, 'max' => 900, 'cost' => 2050 ),
                        array( 'min' => 901, 'max' => 1000, 'cost' => 2200 ),
                        array( 'min' => 1001, 'max' => 1250, 'cost' => 2500 ),
                        array( 'min' => 1251, 'max' => 1500, 'cost' => 2800 ),
                        array( 'min' => 1501, 'max' => 1750, 'cost' => 3100 ),
                        array( 'min' => 1751, 'max' => 2000, 'cost' => 3400 ),
                        array( 'min' => 2001, 'max' => 2500, 'cost' => 3900 ),
                        array( 'min' => 2501, 'max' => 3000, 'cost' => 4400 ),
                        array( 'min' => 3001, 'max' => 3500, 'cost' => 4900 ),
                        array( 'min' => 3501, 'max' => 4000, 'cost' => 5400 ),
                        array( 'min' => 4001, 'max' => 4500, 'cost' => 5900 ),
                        array( 'min' => 4501, 'max' => 5000, 'cost' => 6400 ),
                        array( 'min' => 5001, 'max' => 5500, 'cost' => 6900 ),
                        array( 'min' => 5501, 'max' => 6000, 'cost' => 7400 ),
                        array( 'min' => 6001, 'max' => 7000, 'cost' => 8200 ),
                        array( 'min' => 7001, 'max' => 8000, 'cost' => 9000 ),
                        array( 'min' => 8001, 'max' => 9000, 'cost' => 9800 ),
                        array( 'min' => 9001, 'max' => 10000, 'cost' => 10600 ),
                        array( 'min' => 10001, 'max' => 11000, 'cost' => 11400 ),
                        array( 'min' => 11001, 'max' => 12000, 'cost' => 12200 ),
                        array( 'min' => 12001, 'max' => 13000, 'cost' => 13000 ),
                        array( 'min' => 13001, 'max' => 14000, 'cost' => 13800 ),
                        array( 'min' => 14001, 'max' => 15000, 'cost' => 14600 ),
                        array( 'min' => 15001, 'max' => 16000, 'cost' => 15400 ),
                        array( 'min' => 16001, 'max' => 17000, 'cost' => 16200 ),
                        array( 'min' => 17001, 'max' => 18000, 'cost' => 17000 ),
                        array( 'min' => 18001, 'max' => 19000, 'cost' => 17800 ),
                        array( 'min' => 19001, 'max' => 20000, 'cost' => 18600 ),
                        array( 'min' => 20001, 'max' => 21000, 'cost' => 19400 ),
                        array( 'min' => 21001, 'max' => 22000, 'cost' => 20200 ),
                        array( 'min' => 22001, 'max' => 23000, 'cost' => 21000 ),
                        array( 'min' => 23001, 'max' => 24000, 'cost' => 21800 ),
                        array( 'min' => 24001, 'max' => 25000, 'cost' => 22600 ),
                        array( 'min' => 25001, 'max' => 26000, 'cost' => 23400 ),
                        array( 'min' => 26001, 'max' => 27000, 'cost' => 24200 ),
                        array( 'min' => 27001, 'max' => 28000, 'cost' => 25000 ),
                        array( 'min' => 28001, 'max' => 29000, 'cost' => 25800 ),
                        array( 'min' => 29001, 'max' => 30000, 'cost' => 26600 )
                    );
                case 'Second Zone':
                    return array(
                        array( 'min' => 0, 'max' => 500, 'cost' => 1900 ),
                        array( 'min' => 501, 'max' => 600, 'cost' => 2150 ),
                        array( 'min' => 601, 'max' => 700, 'cost' => 2400 ),
                        array( 'min' => 701, 'max' => 800, 'cost' => 2650 ),
                        array( 'min' => 801, 'max' => 900, 'cost' => 2900 ),
                        array( 'min' => 901, 'max' => 1000, 'cost' => 3150 ),
                        array( 'min' => 1001, 'max' => 1250, 'cost' => 3500 ),
                        array( 'min' => 1251, 'max' => 1500, 'cost' => 3850 ),
                        array( 'min' => 1501, 'max' => 1750, 'cost' => 4200 ),
                        array( 'min' => 1751, 'max' => 2000, 'cost' => 4550 ),
                        array( 'min' => 2001, 'max' => 2500, 'cost' => 5150 ),
                        array( 'min' => 2501, 'max' => 3000, 'cost' => 5750 ),
                        array( 'min' => 3001, 'max' => 3500, 'cost' => 6350 ),
                        array( 'min' => 3501, 'max' => 4000, 'cost' => 6950 ),
                        array( 'min' => 4001, 'max' => 4500, 'cost' => 7550 ),
                        array( 'min' => 4501, 'max' => 5000, 'cost' => 8150 ),
                        array( 'min' => 5001, 'max' => 5500, 'cost' => 8750 ),
                        array( 'min' => 5501, 'max' => 6000, 'cost' => 9350 ),
                        array( 'min' => 6001, 'max' => 7000, 'cost' => 10350 ),
                        array( 'min' => 7001, 'max' => 8000, 'cost' => 11350 ),
                        array( 'min' => 8001, 'max' => 9000, 'cost' => 12350 ),
                        array( 'min' => 9001, 'max' => 10000, 'cost' => 13350 ),
                        array( 'min' => 10001, 'max' => 11000, 'cost' => 14350 ),
                        array( 'min' => 11001, 'max' => 12000, 'cost' => 15350 ),
                        array( 'min' => 12001, 'max' => 13000, 'cost' => 16350 ),
                        array( 'min' => 13001, 'max' => 14000, 'cost' => 17350 ),
                        array( 'min' => 14001, 'max' => 15000, 'cost' => 18350 ),
                        array( 'min' => 15001, 'max' => 16000, 'cost' => 19350 ),
                        array( 'min' => 16001, 'max' => 17000, 'cost' => 20350 ),
                        array( 'min' => 17001, 'max' => 18000, 'cost' => 21350 ),
                        array( 'min' => 18001, 'max' => 19000, 'cost' => 22350 ),
                        array( 'min' => 19001, 'max' => 20000, 'cost' => 23350 ),
                        array( 'min' => 20001, 'max' => 21000, 'cost' => 24350 ),
                        array( 'min' => 21001, 'max' => 22000, 'cost' => 25350 ),
                        array( 'min' => 22001, 'max' => 23000, 'cost' => 26350 ),
                        array( 'min' => 23001, 'max' => 24000, 'cost' => 27350 ),
                        array( 'min' => 24001, 'max' => 25000, 'cost' => 28350 ),
                        array( 'min' => 25001, 'max' => 26000, 'cost' => 29350 ),
                        array( 'min' => 26001, 'max' => 27000, 'cost' => 30350 ),
                        array( 'min' => 27001, 'max' => 28000, 'cost' => 31350 ),
                        array( 'min' => 28001, 'max' => 29000, 'cost' => 32350 ),
                        array( 'min' => 29001, 'max' => 30000, 'cost' => 33350 )
                    );
                case 'Third Zone':
                    return array(
                        array( 'min' => 0, 'max' => 500, 'cost' => 3150 ),
                        array( 'min' => 501, 'max' => 600, 'cost' => 3400 ),
                        array( 'min' => 601, 'max' => 700, 'cost' => 3650 ),
                        array( 'min' => 701, 'max' => 800, 'cost' => 3900 ),
                        array( 'min' => 801, 'max' => 900, 'cost' => 4150 ),
                        array( 'min' => 901, 'max' => 1000, 'cost' => 4400 ),
                        array( 'min' => 1001, 'max' => 1250, 'cost' => 5000 ),
                        array( 'min' => 1251, 'max' => 1500, 'cost' => 5550 ),
                        array( 'min' => 1501, 'max' => 1750, 'cost' => 6150 ),
                        array( 'min' => 1751, 'max' => 2000, 'cost' => 6700 ),
                        array( 'min' => 2001, 'max' => 2500, 'cost' => 7750 ),
                        array( 'min' => 2501, 'max' => 3000, 'cost' => 8800 ),
                        array( 'min' => 3001, 'max' => 3500, 'cost' => 9850 ),
                        array( 'min' => 3501, 'max' => 4000, 'cost' => 10900 ),
                        array( 'min' => 4001, 'max' => 4500, 'cost' => 11950 ),
                        array( 'min' => 4501, 'max' => 5000, 'cost' => 13000 ),
                        array( 'min' => 5001, 'max' => 5500, 'cost' => 14050 ),
                        array( 'min' => 5501, 'max' => 6000, 'cost' => 15100 ),
                        array( 'min' => 6001, 'max' => 7000, 'cost' => 17200 ),
                        array( 'min' => 7001, 'max' => 8000, 'cost' => 19300 ),
                        array( 'min' => 8001, 'max' => 9000, 'cost' => 21400 ),
                        array( 'min' => 9001, 'max' => 10000, 'cost' => 23500 ),
                        array( 'min' => 10001, 'max' => 11000, 'cost' => 25600 ),
                        array( 'min' => 11001, 'max' => 12000, 'cost' => 27700 ),
                        array( 'min' => 12001, 'max' => 13000, 'cost' => 29800 ),
                        array( 'min' => 13001, 'max' => 14000, 'cost' => 31900 ),
                        array( 'min' => 14001, 'max' => 15000, 'cost' => 34000 ),
                        array( 'min' => 15001, 'max' => 16000, 'cost' => 36100 ),
                        array( 'min' => 16001, 'max' => 17000, 'cost' => 38200 ),
                        array( 'min' => 17001, 'max' => 18000, 'cost' => 40300 ),
                        array( 'min' => 18001, 'max' => 19000, 'cost' => 42400 ),
                        array( 'min' => 19001, 'max' => 20000, 'cost' => 44500 ),
                        array( 'min' => 20001, 'max' => 21000, 'cost' => 46600 ),
                        array( 'min' => 21001, 'max' => 22000, 'cost' => 48700 ),
                        array( 'min' => 22001, 'max' => 23000, 'cost' => 50800 ),
                        array( 'min' => 23001, 'max' => 24000, 'cost' => 52900 ),
                        array( 'min' => 24001, 'max' => 25000, 'cost' => 55000 ),
                        array( 'min' => 25001, 'max' => 26000, 'cost' => 57100 ),
                        array( 'min' => 26001, 'max' => 27000, 'cost' => 59200 ),
                        array( 'min' => 27001, 'max' => 28000, 'cost' => 61300 ),
                        array( 'min' => 28001, 'max' => 29000, 'cost' => 63400 ),
                        array( 'min' => 29001, 'max' => 30000, 'cost' => 65500 )
                    );
                case 'Fourth Zone':
                    return array(
                        array( 'min' => 0, 'max' => 500, 'cost' => 3900 ),
                        array( 'min' => 501, 'max' => 600, 'cost' => 4180 ),
                        array( 'min' => 601, 'max' => 700, 'cost' => 4460 ),
                        array( 'min' => 701, 'max' => 800, 'cost' => 4740 ),
                        array( 'min' => 801, 'max' => 900, 'cost' => 5020 ),
                        array( 'min' => 901, 'max' => 1000, 'cost' => 5300 ),
                        array( 'min' => 1001, 'max' => 1250, 'cost' => 5990 ),
                        array( 'min' => 1251, 'max' => 1500, 'cost' => 6600 ),
                        array( 'min' => 1501, 'max' => 1750, 'cost' => 7290 ),
                        array( 'min' => 1751, 'max' => 2000, 'cost' => 7900 ),
                        array( 'min' => 2001, 'max' => 2500, 'cost' => 9100 ),
                        array( 'min' => 2501, 'max' => 3000, 'cost' => 10300 ),
                        array( 'min' => 3001, 'max' => 3500, 'cost' => 11500 ),
                        array( 'min' => 3501, 'max' => 4000, 'cost' => 12700 ),
                        array( 'min' => 4001, 'max' => 4500, 'cost' => 13900 ),
                        array( 'min' => 4501, 'max' => 5000, 'cost' => 15100 ),
                        array( 'min' => 5001, 'max' => 5500, 'cost' => 16300 ),
                        array( 'min' => 5501, 'max' => 6000, 'cost' => 17500 ),
                        array( 'min' => 6001, 'max' => 7000, 'cost' => 19900 ),
                        array( 'min' => 7001, 'max' => 8000, 'cost' => 22300 ),
                        array( 'min' => 8001, 'max' => 9000, 'cost' => 24700 ),
                        array( 'min' => 9001, 'max' => 10000, 'cost' => 27100 ),
                        array( 'min' => 10001, 'max' => 11000, 'cost' => 29500 ),
                        array( 'min' => 11001, 'max' => 12000, 'cost' => 31900 ),
                        array( 'min' => 12001, 'max' => 13000, 'cost' => 34300 ),
                        array( 'min' => 13001, 'max' => 14000, 'cost' => 36700 ),
                        array( 'min' => 14001, 'max' => 15000, 'cost' => 39100 ),
                        array( 'min' => 15001, 'max' => 16000, 'cost' => 41500 ),
                        array( 'min' => 16001, 'max' => 17000, 'cost' => 43900 ),
                        array( 'min' => 17001, 'max' => 18000, 'cost' => 46300 ),
                        array( 'min' => 18001, 'max' => 19000, 'cost' => 48700 ),
                        array( 'min' => 19001, 'max' => 20000, 'cost' => 51100 ),
                        array( 'min' => 20001, 'max' => 21000, 'cost' => 53500 ),
                        array( 'min' => 21001, 'max' => 22000, 'cost' => 55900 ),
                        array( 'min' => 22001, 'max' => 23000, 'cost' => 58300 ),
                        array( 'min' => 23001, 'max' => 24000, 'cost' => 60700 ),
                        array( 'min' => 24001, 'max' => 25000, 'cost' => 63100 ),
                        array( 'min' => 25001, 'max' => 26000, 'cost' => 65500 ),
                        array( 'min' => 26001, 'max' => 27000, 'cost' => 67900 ),
                        array( 'min' => 27001, 'max' => 28000, 'cost' => 70300 ),
                        array( 'min' => 28001, 'max' => 29000, 'cost' => 72700 ),
                        array( 'min' => 29001, 'max' => 30000, 'cost' => 75100 )
                    );
                case 'Fifth Zone':
                    return array(
                        array( 'min' => 0, 'max' => 500, 'cost' => 3600 ),
                        array( 'min' => 501, 'max' => 600, 'cost' => 3900 ),
                        array( 'min' => 601, 'max' => 700, 'cost' => 4200 ),
                        array( 'min' => 701, 'max' => 800, 'cost' => 4500 ),
                        array( 'min' => 801, 'max' => 900, 'cost' => 4800 ),
                        array( 'min' => 901, 'max' => 1000, 'cost' => 5100 ),
                        array( 'min' => 1001, 'max' => 1250, 'cost' => 5850 ),
                        array( 'min' => 1251, 'max' => 1500, 'cost' => 6600 ),
                        array( 'min' => 1501, 'max' => 1750, 'cost' => 7350 ),
                        array( 'min' => 1751, 'max' => 2000, 'cost' => 8100 ),
                        array( 'min' => 2001, 'max' => 2500, 'cost' => 9600 ),
                        array( 'min' => 2501, 'max' => 3000, 'cost' => 11100 ),
                        array( 'min' => 3001, 'max' => 3500, 'cost' => 12600 ),
                        array( 'min' => 3501, 'max' => 4000, 'cost' => 14100 ),
                        array( 'min' => 4001, 'max' => 4500, 'cost' => 15600 ),
                        array( 'min' => 4501, 'max' => 5000, 'cost' => 17100 ),
                        array( 'min' => 5001, 'max' => 5500, 'cost' => 18600 ),
                        array( 'min' => 5501, 'max' => 6000, 'cost' => 20100 ),
                        array( 'min' => 6001, 'max' => 7000, 'cost' => 22500 ),
                        array( 'min' => 7001, 'max' => 8000, 'cost' => 24900 ),
                        array( 'min' => 8001, 'max' => 9000, 'cost' => 27300 ),
                        array( 'min' => 9001, 'max' => 10000, 'cost' => 29700 ),
                        array( 'min' => 10001, 'max' => 11000, 'cost' => 32100 ),
                        array( 'min' => 11001, 'max' => 12000, 'cost' => 34500 ),
                        array( 'min' => 12001, 'max' => 13000, 'cost' => 36900 ),
                        array( 'min' => 13001, 'max' => 14000, 'cost' => 39300 ),
                        array( 'min' => 14001, 'max' => 15000, 'cost' => 41700 ),
                        array( 'min' => 15001, 'max' => 16000, 'cost' => 44100 ),
                        array( 'min' => 16001, 'max' => 17000, 'cost' => 46500 ),
                        array( 'min' => 17001, 'max' => 18000, 'cost' => 48900 ),
                        array( 'min' => 18001, 'max' => 19000, 'cost' => 51300 ),
                        array( 'min' => 19001, 'max' => 20000, 'cost' => 53700 ),
                        array( 'min' => 20001, 'max' => 21000, 'cost' => 56100 ),
                        array( 'min' => 21001, 'max' => 22000, 'cost' => 58500 ),
                        array( 'min' => 22001, 'max' => 23000, 'cost' => 60900 ),
                        array( 'min' => 23001, 'max' => 24000, 'cost' => 63300 ),
                        array( 'min' => 24001, 'max' => 25000, 'cost' => 65700 ),
                        array( 'min' => 25001, 'max' => 26000, 'cost' => 68100 ),
                        array( 'min' => 26001, 'max' => 27000, 'cost' => 70500 ),
                        array( 'min' => 27001, 'max' => 28000, 'cost' => 72900 ),
                        array( 'min' => 28001, 'max' => 29000, 'cost' => 75300 ),
                        array( 'min' => 29001, 'max' => 30000, 'cost' => 77700 )
                    );
                default:
                    return array();
            }
        }

        public function process_admin_options()
        {
            parent::process_admin_options();
            $this->init_settings();
        }
    }

    class Japan_Post_EMS_Shipping extends Japan_Post_EMS
    {
        public function __construct()
        {
            parent::__construct(
                'japan_post_ems',
                'Japan Post EMS',
                'Adds Japan Post EMS shipping method for WooCommerce'
            );
        }
    }

    function add_japan_post_ems_methods($methods)
    {
        $methods['japan_post_ems'] = 'Japan_Post_EMS_Shipping';
        return $methods;
    }

    function japan_post_ems_load_textdomain()
    {
        load_plugin_textdomain('japan-post-ems', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}
