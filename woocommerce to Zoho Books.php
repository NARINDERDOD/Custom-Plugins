<?php
 /*
Plugin Name: WooCommerce Zoho Books Invoice Creation
Description: Integrates WooCommerce with Zoho Books.
Version: 1.1
Author: Dhruv
 */
 
 
 // Hook the function to update access token when 'update_access_token' action is triggered
add_action('update_books_access_token_1', 'update_books_access_token_function');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
function update_books_access_token_function()
{
    $curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://accounts.zoho.com/oauth/v2/token?refresh_token=1000.959acadffa7f694ce933ae051c31efb9.9c3a308c4345c917b4e9b681d8cb51fb&client_id=1000.IIACUS0D0SHBWA897RLFI4ERNJN3DG&client_secret=27f28917c06bf88c5d13a758148c6c73c991ba358a&grant_type=refresh_token',
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

 
// Hook to update Zoho Books when an order is created
add_action('woocommerce_new_order', 'sync_order_with_zoho_books', 10, 1);

function sync_order_with_zoho_books($order_id) {
    // Get the order data
    $order = wc_get_order($order_id);

    // Get the customer data from the order
    $customer_data = $order->get_data();

    // Call the function to update the access token
    update_books_access_token_function();

    // Get the latest access token
    $zoho_books_auth_token = get_option('donor_profile_access_token');


    // Zoho Books API endpoints
    $customer_api_url = 'https://www.zohoapis.com/books/v3/contacts?organization_id=841566379';
    $invoice_api_url = 'https://www.zohoapis.com/books/v3/invoices?organization_id=841566379';
    $items_api_url = 'https://www.zohoapis.com/books/v3/items?organization_id=841566379'; // New API endpoint for fetching items

    // Check if the customer already exists in Zoho Books
    $existing_customer_id = get_zoho_books_customer_id($customer_data['billing']['email'], $zoho_books_auth_token, $customer_api_url);

    // If customer exists, use the existing customer ID, otherwise create a new customer
    if ($existing_customer_id) {
        $customer_id = $existing_customer_id;
    } else {
        // Prepare data for customer creation API request
        $customer_request_data = array(
            'contact_name' => $customer_data['billing']['first_name'] . ' ' . $customer_data['billing']['last_name'],
            'contact_persons' => array(
                array(
                    "email" => $customer_data['billing']['email'],
                ),
            )
        );

        // Log data before customer creation API request
        error_log('Zoho Books Customer API Request Data: ' . json_encode($customer_request_data));

        // Set up cURL options for customer creation
        $customer_curl = curl_init();
        curl_setopt_array($customer_curl, array(
            CURLOPT_URL => $customer_api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($customer_request_data), // Encode data as JSON
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $zoho_books_auth_token,
                'Content-Type: application/json',
            ),
        ));

        // Execute customer creation cURL request
        $customer_response = curl_exec($customer_curl);

        // Check for cURL errors and HTTP status code
        if ($customer_response === false) {
            // Handle cURL error
            error_log('Zoho Books Customer API Request Error: ' . curl_error($customer_curl));
        } else {
            // Log customer creation response
            error_log('Zoho Books Customer API Response: ' . $customer_response);

            // Parse customer creation response to get the customer ID
            $customer_response_data = json_decode($customer_response, true);
            if (isset($customer_response_data['contact']['contact_id'])) {
                $customer_id = $customer_response_data['contact']['contact_id'];
            }
        }

        // Close customer cURL session
        curl_close($customer_curl);
    }

    // Fetch items from Zoho Books
    $items_response = fetch_items_from_zoho_books($zoho_books_auth_token, $items_api_url);

    // If items fetch is successful, proceed to create invoice
    if ($items_response) {
        // Match WooCommerce product IDs with Zoho Books items
        $line_items = match_items_with_order($order, $items_response);

        // Prepare data for invoice creation API request
        $invoice_request_data = array(
            'customer_id' => $customer_id,
            'line_items' => $line_items,
            // Add other required invoice data here, like taxes, discounts, etc.
        );

        // Log data before invoice creation API request
        error_log('Zoho Books Invoice API Request Data: ' . json_encode($invoice_request_data));

        // Set up cURL options for invoice creation
        $invoice_curl = curl_init();
        curl_setopt_array($invoice_curl, array(
            CURLOPT_URL => $invoice_api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($invoice_request_data), // Encode data as JSON
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $zoho_books_auth_token,
                'Content-Type: application/json',
            ),
        ));

        // Execute invoice creation cURL request
        $invoice_response = curl_exec($invoice_curl);

        // Check for cURL errors and HTTP status code
        if ($invoice_response === false) {
            // Handle cURL error
            error_log('Zoho Books Invoice API Request Error: ' . curl_error($invoice_curl));
        } else {
            // Log invoice creation response
            error_log('Zoho Books Invoice API Response: ' . $invoice_response);

            // Display the output as an admin notice
            add_action('admin_notices', function () use ($invoice_response) {
                echo '<div class="notice notice-success is-dismissible"><pre>' . esc_html(print_r($invoice_response, true)) . '</pre></div>';
            });
        }

        // Close invoice cURL session
        curl_close($invoice_curl);
    }
}

// Function to fetch items from Zoho Books
function fetch_items_from_zoho_books($zoho_books_auth_token, $items_api_url) {
    $items_curl = curl_init();
    curl_setopt_array($items_curl, array(
        CURLOPT_URL => $items_api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $zoho_books_auth_token,
            'Content-Type: application/json',
        ),
    ));

    // Execute items fetch cURL request
    $items_response = curl_exec($items_curl);

    // Check for cURL errors and HTTP status code
    if ($items_response === false) {
        // Handle cURL error
        error_log('Zoho Books Items API Request Error: ' . curl_error($items_curl));
        return false;
    } else {
        // Log items fetch response
        error_log('Zoho Books Items API Response: ' . $items_response);
        return json_decode($items_response, true);
    }

    // Close items cURL session
    curl_close($items_curl);
}

// Function to match WooCommerce product IDs with Zoho Books items
function match_items_with_order($order, $items_response) {
    $items_data = $items_response['items'];
    $line_items = array();

    foreach ($order->get_items() as $item_id => $item_data) {
        $product_id = $item_data->get_product_id();
        foreach ($items_data as $zoho_item) {
            if ($product_id == $zoho_item['cf_woo_product_id']) {
                // Found matching item, add to line items
                $line_items[] = array(
                    'item_id' => $zoho_item['item_id'],
                    'rate' => $zoho_item['rate'],
                    'quantity' => $item_data->get_quantity(),                                                                                               
                );
                break;
            }
        }
    }

    return $line_items;
}

// Function to get Zoho Books customer ID if exists
function get_zoho_books_customer_id($email, $zoho_books_auth_token, $customer_api_url) {
    $customer_id = '';

    // Set up cURL options for fetching customer details
    $customer_details_curl = curl_init();
    curl_setopt_array($customer_details_curl, array(
        CURLOPT_URL => $customer_api_url . '&email=' . $email,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $zoho_books_auth_token,
            'Content-Type: application/json',
        ),
    ));

    // Execute customer details fetch cURL request
    $customer_details_response = curl_exec($customer_details_curl);

    // Check for cURL errors and HTTP status code
    if ($customer_details_response === false) {
        // Handle cURL error
        error_log('Zoho Books Customer Details API Request Error: ' . curl_error($customer_details_curl));
    } else {
        // Parse customer details response
        $customer_details_data = json_decode($customer_details_response, true);
        if (!empty($customer_details_data['contacts'])) {
            $customer_id = $customer_details_data['contacts'][0]['contact_id'];
        }
    }

    // Close customer details cURL session
    curl_close($customer_details_curl);

    return $customer_id;
}
