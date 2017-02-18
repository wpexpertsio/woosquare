<?php

class Square {

    //Class properties.
    protected $accessToken;
    protected $squareURL;
    protected $locationId;
    protected $mainSquareURL;

    /**
     * Constructor
     *
     * @param object $accessToken
     *
     */
    public function __construct($accessToken, $locationId="me") {
        $this->accessToken = $accessToken;
        if(empty($locationId)){ $locationId = 'me'; }
        $this->locationId = $locationId;
        $this->squareURL = "https://connect.squareup.com/v1/" . $this->locationId;
        $this->mainSquareURL = "https://connect.squareup.com/v1/me";
    }

    
    public function getAccessToken(){
        return $this->accessToken;
    }
    
    public function setAccessToken($access_token){
        $this->accessToken = $access_token;
    }
    
    public function getSquareURL(){
        return $this->squareURL;
    }
    

    public function setLocationId($location_id){        
        $this->locationId = $location_id;
        $this->squareURL = "https://connect.squareup.com/v1/".$location_id;
    }
    
    public function getLocationId(){
        return $this->locationId;
    }
    
    /*
     * authoirize the connect to Square with the given token
     */

    public function authorize() {
        Helpers::debug_log('info', "-------------------------------------------------------------------------------");
        Helpers::debug_log('info', "Authorize square account with Token: " . $this->accessToken);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->mainSquareURL );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken)
        );

        $response = curl_exec($curl);
        Helpers::debug_log('info', "The response of authorize curl request" . $response);
        curl_close($curl);
        $response = json_decode($response, true);
        if (isset($response['id'])) {
            update_option('woo_square_access_token', $this->accessToken);
            update_option('woo_square_account_type', $response['account_type']);
            update_option('woo_square_account_currency_code', $response['currency_code']);
            
            if($response['account_type'] == "LOCATION"){
                update_option('woo_square_location_id', 'me');
                update_option('woo_square_locations', '');
                update_option('woo_square_business_name', $response['business_name']);
            }else{
                $result = $this->getAllLocations();
                if($result){
                    $locations = array();
                    foreach ($result as $location) {
                        $locations[$location['id']] = $location['name'];
                    }
                    $location_id = key($locations);
                    update_option('woo_square_locations', $locations);
                    update_option('woo_square_business_name', $locations[$location_id]);
                    if($this->locationId == "me")
                        update_option('woo_square_location_id', $location_id);
                }
            }
            $this->setupWebhook("PAYMENT_UPDATED");
            return true;
        } else {
            return false;
        }
    }

    
    /*
     * get currency code by location id
     */
    public function getCurrencyCode(){
        
        Helpers::debug_log('info', "Getting currency code for square token: {$this->getAccessToken()}, url: {$this->squareURL} "
        . "and location: {$this->locationId}");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->squareURL);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken)
        );

        $response = curl_exec($curl);
        Helpers::debug_log('info', "The response of current location curl request" . $response);
        curl_close($curl);
        $response = json_decode($response, true);
        if (isset($response['id'])) {
            update_option('woo_square_account_currency_code', $response['currency_code']);
        }
    }
    
    
    
    
    /*
     * get all locations if account type is business
     */

    public function getAllLocations() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->mainSquareURL . '/locations');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken)
        );

        $response = curl_exec($curl);
        Helpers::debug_log('info', "The response of get locations request " . $response);
        curl_close($curl);
        
        return json_decode($response, true);
    }

    /*
     * setup webhook with Square
     */

    public function setupWebhook($type) {
        // setup notifications
        $data = array($type);
        $data_json = json_encode($data);
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->squareURL . "/webhooks");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_json),
            'Authorization: Bearer ' . $this->accessToken)
        );

        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_json);

        $response = curl_exec($curl);
        Helpers::debug_log('info', "The response of setup webhook curl request " . $response);
        Helpers::debug_log('info', "-------------------------------------------------------------------------------");
        curl_close($curl);
        return true;
    }

 
    /*
     * Update Square inventory based on this order 
     */

    public function completeOrder($order_id) {
        Helpers::debug_log('info', "Complete Order: " . $order_id);
        $order = new WC_Order($order_id);
        $items = $order->get_items();
        Helpers::debug_log('info', "Order's items" . json_encode($items));

        foreach ($items as $item) {
            if ($item['variation_id']) {
                Helpers::debug_log('info', "Variable item");
                if (get_post_meta($item['variation_id'], '_manage_stock', true) == 'yes') {
                    Helpers::debug_log('info', "Item allow manage stock");
                    $product_variation_id = get_post_meta($item['variation_id'], 'variation_square_id', true);
                    Helpers::debug_log('info', "Item variation square id: " . $product_variation_id);
                    $this->updateInventory($product_variation_id, -1 * $item['qty'], 'SALE');
                }
            } else {
                Helpers::debug_log('info', "Simple item");
                if (get_post_meta($item['product_id'], '_manage_stock', true) == 'yes') {
                    Helpers::debug_log('info', "Item allow manage stock");
                    $product_variation_id = get_post_meta($item['product_id'], 'variation_square_id', true);
                    Helpers::debug_log('info', "Item variation square id: " . $product_variation_id);
                    $this->updateInventory($product_variation_id, -1 * $item['qty'], 'SALE');
                }
            }
        }
    }

    

    /*
     * create a refund to Square
     */

    public function refund($order_id, $refund_id) {
        $request = array(
            "payment_id" => get_post_meta($order_id, 'square_payment_id', true),
            "type" => "PARTIAL",
            "reason" => "Returned Goods",
            "refunded_money" => array(
                "currency_code" => get_post_meta($order_id, '_order_currency', true),
                "amount" => (get_post_meta($refund_id, '_refund_amount', true) * -100 )
            )
        );
        $json = json_encode($request);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->squareURL . "/refunds");
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
            'Authorization: Bearer ' . $this->accessToken)
        );

        $response = curl_exec($curl);
        Helpers::debug_log('info', "The response of refund curl request" . $response);
        curl_close($curl);
        $refund_obj = json_decode($response);
        update_post_meta($order_id, "refund_created_at", $refund_obj->created_at);
    }

   
    
}
