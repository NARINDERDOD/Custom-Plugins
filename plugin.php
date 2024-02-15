<?php
/* 

Plugin Name: Tuts+ Register Post Types 

Plugin URI: https://tutsplus.com/ 

Description: Plugin to accompany tutsplus guide to creating plugins, registers a post type. 

Version: 1.0 

Author: Rachel McCollin 

Author URI: https://rachelmccollin.com/ 

License: GPLv2 or later 

Text Domain: tutsplus 

*/
// Hook the function to update access token when 'update_access_token' action is triggered
add_action('update_access_token', 'update_access_token_func');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
function update_access_token_func()
{
    $curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://accounts.zoho.com/oauth/v2/token?refresh_token=1000.a82b1bafe9e0863218f665aab4b706eb.ff94ccef38a29dddfdc0bc4cb2ec68cf&client_id=1000.IIACUS0D0SHBWA897RLFI4ERNJN3DG&client_secret=27f28917c06bf88c5d13a758148c6c73c991ba358a&grant_type=refresh_token',
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'POST',
	  CURLOPT_HTTPHEADER => array(
		'Cookie: _zcsr_tmp=982e2a19-2d4d-42a9-9cef-1105671d8cb9; b266a5bf57=a711b6da0e6cbadb5e254290f114a026; iamcsr=982e2a19-2d4d-42a9-9cef-1105671d8cb9'
	  ),
	));

	$response = curl_exec($curl);

	curl_close($curl);
	
	$res = json_decode($response, true);
	
	$access_token = $res['access_token'];
	
    update_option('donor_profile_access_token', $access_token);
}
add_action('save_post', 'create_zoho_crm_product', 10, 3);





function create_zoho_crm_product($post_id, $post, $update) {
    // Check if it's a valid product
    if ($post->post_type !== 'product' || $update === false) {
        return;
    }
 // Call the function to update the access token
 update_access_token_func();

 // Get the latest access token
 $zoho_crm_auth_token = get_option('donor_profile_access_token');
    // Get WooCommerce product data 
    $product = wc_get_product($post_id);

    // Zoho CRM API endpoint and authentication details
    $zoho_crm_api_endpoint = 'https://www.zohoapis.com/crm/v2/Products';
                                   // access token

                                   $price = (float)$product->get_price();
                                   $salep = (float)$product->get_sale_price();
                                   $regularp = (float)$product->get_regular_price();

                                   $floatValue = $product->get_price();
                                   error_log('Float price: ' .$floatValue);
                                   $sp = "sale_price: ";
                                   error_log($sp . var_export($salep, true));
                                   $rp = "regular price: ";
                                   error_log($rp . var_export($regularp, true));
                                   error_log( var_export($product->get_id(), true));
     
                                
                                
    // Prepare product data for Zoho CRM
    $zoho_product_data = array(
        'data' => array(
            array(
                'Product_Name' => $product->get_name(),
		//		'Unit_Price' => $product->get_sale_price(),
          //   'Unit_Price' =>$product->get_price(),
             'Unit_Price' =>$price,
             'Description' => $product->get_description(),
             'Summary' => $product->get_short_description(),
               'Woocomerce_ID' =>$product->get_id(),

            //  'Unit_Price' =>"3"+$product->get_price(,

            //  'Unit_Price' =>"4"$product->get_price(),


                'Commission_Rate' => $regularp,
            ),
        ),
    );
    $error_message = "Error Message: ";
   error_log($error_message . var_export($product->get_price(), true));


    // Set up cURL options
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $zoho_crm_api_endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($zoho_product_data), // Encode data as JSON
        CURLOPT_HTTPHEADER => array(
            'Authorization: Zoho-oauthtoken ' . $zoho_crm_auth_token,
            'Content-Type: application/json',
        ),
    ));

    // Execute cURL request
    $response = curl_exec($curl);

    // Check for cURL errors and HTTP status code
    if ($response === false) {
        // Handle cURL error
        error_log('Zoho API Request Error: ' . curl_error($curl));
    } else {
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            // Handle HTTP error
            error_log('Zoho API Request Error: ' . $http_code);
        } else {
            // Display the output as an admin notice
            add_action('admin_notices', function () use ($response) {
                echo '<div class="notice notice-success is-dismissible"><pre>' . esc_html(print_r($response, true)) . '</pre></div>';
            });
        }
    }

    // Close cURL session
    curl_close($curl);
}

// Schedule the event to run every hour
add_action('init', 'schedule_zoho_refresh_event');

function schedule_zoho_refresh_event() {
    if (!wp_next_scheduled('zoho_refresh_event')) {
        wp_schedule_event(time(), 'hourly', 'zoho_refresh_event');
    }
}





