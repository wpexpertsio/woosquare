<?php

// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

//register ajax actions
//woo->square
add_action('wp_ajax_get_non_sync_woo_data', 'woo_square_plugin_get_non_sync_woo_data');
add_action('wp_ajax_start_manual_woo_to_square_sync', 'woo_square_plugin_start_manual_woo_to_square_sync');
add_action('wp_ajax_sync_woo_category_to_square', 'woo_square_plugin_sync_woo_category_to_square');
add_action('wp_ajax_sync_woo_product_to_square', 'woo_square_plugin_sync_woo_product_to_square');
add_action('wp_ajax_terminate_manual_woo_sync', 'woo_square_plugin_terminate_manual_woo_sync');

//square->woo
add_action('wp_ajax_get_non_sync_square_data', 'woo_square_plugin_get_non_sync_square_data');
add_action('wp_ajax_start_manual_square_to_woo_sync', 'woo_square_plugin_start_manual_square_to_woo_sync');
add_action('wp_ajax_sync_square_category_to_woo', 'woo_square_plugin_sync_square_category_to_woo');
add_action('wp_ajax_sync_square_product_to_woo', 'woo_square_plugin_sync_square_product_to_woo');
add_action('wp_ajax_terminate_manual_square_sync', 'woo_square_plugin_terminate_manual_square_sync');


function checkSyncStartConditions(){
    
    if(!get_option('woo_square_access_token')){
        return "Invalid square access token";
    }
    
    if(get_option('woo_square_running_sync') && (time()-(int)get_option('woo_square_running_sync_time')) < (20*60) ){
        Helpers::debug_log('error','Manual Sync Request: There is already sync running');
        return 'There is another Synchronization process running. Please try again later. Or <a href="'. admin_url('admin.php?page=square-settings&terminate_sync=true').'" > terminate now </a>';
    }
    /*if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strcmp(strtolower(filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH')),'xmlhttprequest')) {
        Helpers::debug_log('error', "Manual Sync Request: called without AJAX");
        return 'Error occurred!';
    }*/
    
    return TRUE;

}


//woo -> square
function woo_square_plugin_get_non_sync_woo_data() {

    $checkFlag = checkSyncStartConditions();
    if ($checkFlag !== TRUE){ die(json_encode(['error'=>$checkFlag])); } 
    Helpers::debug_log('info', "Manual sync: [woo->square] start gathering data");

    $square = new Square(get_option('woo_square_access_token'), get_option('woo_square_location_id'));
    $synchronizer = new WooToSquareSynchronizer($square);
    
    //for display
    $addProducts = $updateProducts = $deleteProducts = $addCategories 
            = $updateCategories = $deleteCategories = [];
    //display all products in update
    $oneProductsUpdateCheckbox = FALSE;

    
    //1-get un-syncronized categories ( having is_square_sync = 0 or key not exists )
    $categories = $synchronizer->getUnsynchronizedCategories();
    $squareCategories = $synchronizer->getCategoriesSquareIds($categories);
        
    $targetCategories = $excludedProducts = [];
    
    //merge add and update categories
    foreach ($categories as $cat) {
        if (!isset($squareCategories[$cat->term_id])) {      //add
            $targetCategories[$cat->term_id]['action'] = 'add';
            $targetCategories[$cat->term_id]['square_id'] = NULL;
            $addCategories[] = [
                'woo_id'=> $cat->term_id, 
                'checkbox_val'=> $cat->term_id, 
                'name'=> $cat->name
            ];
        }else{                                          //update
            $targetCategories[$cat->term_id]['action'] = 'update';
            $targetCategories[$cat->term_id]['square_id'] = $squareCategories[$cat->term_id];
            $updateCategories[] = [
                'woo_id'=> $cat->term_id, 
                'checkbox_val'=> $cat->term_id, 
                'name'=> $cat->name
            ];
        }
        
        $targetCategories[$cat->term_id]['name'] = $cat->name;
    }
    

    //2-get un-syncronized products ( having is_square_sync = 0 or key not exists )
    $products = $synchronizer->getUnsynchronizedProducts();
    $squarePoducts = $synchronizer->getProductsSquareIds($products, $excludedProducts);
    $targetProducts = [];
    //merge add and update items
    foreach ($products as $product) {
        
        //skip simple products with empty sku
		
		
	$product_id = $product->ID; // the ID of the product to check
	$_product = wc_get_product( $product_id );
	if( $_product->is_type( 'simple' ) ) {
		// do stuff for simple products
		$sku = get_post_meta( $product->ID , '_sku', true );
		if(empty($sku)){
			$sku_missin_inside_product[] = [
                'woo_id'=> $product->ID, 
                'checkbox_val'=> $product->ID, 
                'name'=> $product->post_title,
                'sku_missin_inside_product'=> 'sku_missin_inside_product'
            ];
			
			
			
		}
	} else if( $_product->is_type( 'variable' )  ) {
		$tickets = new WC_Product_Variable( $product_id );
		$variables = $tickets->get_available_variations();
			
			if(!empty($variables)){
				foreach($variables as $var_checkin){
						
					if(empty($var_checkin['sku'])){
						$sku_missin_inside_product[] = [
							'woo_id'=> $product->ID, 
							'checkbox_val'=> $product->ID, 
							'name'=> $product->post_title.' variations of "'.$var_checkin['attributes']['attribute_var1'].'" sku missing kindly click here update it.',
							'sku_missin_inside_product'=> 'sku_missin_inside_product'
						];
						break;
					}
					
				}
			}
		
		// do stuff for variable
	}
		
		
		
		
		
		
		
        if (in_array($product->ID, $excludedProducts)){
            continue;
        }
        
        if (isset($squarePoducts[$product->ID])) {     //update
            $targetProducts[$product->ID]['action'] = 'update';
            $targetProducts[$product->ID]['square_id'] = $squarePoducts[$product->ID];
            $updateProducts[] = [
                'woo_id'=> $product->ID, 
                'checkbox_val'=> $product->ID, 
                'name'=> $product->post_title
            ];
            
        }else{                                       //add
            $targetProducts[$product->ID]['action'] = 'add';
            $targetProducts[$product->ID]['square_id'] = NULL;
            $addProducts[] = [
                'woo_id'=> $product->ID, 
                'checkbox_val'=> $product->ID, 
                'name'=> $product->post_title
            ];
        }
        
        $targetProducts[$product->ID]['name'] = $product->post_title;
    }
    
	
	
			


    //3-get deleted elements failed to be synchronized
    $deletedElms = $synchronizer->getUnsynchronizedDeletedElements();

    //merge deleted items and categories with their corresponding arrays
    foreach ($deletedElms as $elm) {

        if ($elm->target_type == Helpers::TARGET_TYPE_PRODUCT) {   //PRODUCT
            $targetProducts[$elm->target_id]['square_id'] = $elm->square_id;
            $targetProducts[$elm->target_id]['action'] = 'delete';
            $targetProducts[$elm->target_id]['name'] = $elm->name;
            
            //for display
            $deleteProducts[] = [
                'woo_id'=> NULL, 
                'checkbox_val'=> $elm->target_id, 
                'name'=> $elm->name
            ];
        } else {                                                                  //CATEGORY
            $targetCategories[$elm->target_id]['square_id'] = $elm->square_id;
            $targetCategories[$elm->target_id]['action'] = 'delete';
            $targetCategories[$elm->target_id]['name'] = $elm->name;
            $deleteCategories[] = [
                'woo_id'=> NULL, 
                'checkbox_val'=> $elm->target_id, 
                'name'=> $elm->name
            ];
        }
    }


    //4-get all square items simplified
    $squareItems = $synchronizer->getSquareItems();
    $squareItemsModified = [];
    if ($squareItems){
        $squareItemsModified = $synchronizer->simplifySquareItemsObject($squareItems);
    }
    

    //construct session array
    session_start();
    $_SESSION["woo_to_square"] = []; 
    $_SESSION["woo_to_square"]["target_products"] = $targetProducts;
    $_SESSION["woo_to_square"]["target_categories"] = $targetCategories;
    //add simplified object to session
    $_SESSION["woo_to_square"]["suqare_items"] = $squareItemsModified;

    ob_start();
    include WOO_SQUARE_PLUGIN_PATH . 'views/partials/pop-up.php';
    $data = ob_get_clean();

    echo json_encode(['data' => $data ]);
    Helpers::debug_log('info', "Manual sync: [woo->square] end gathering data");

    die();
}

function woo_square_plugin_start_manual_woo_to_square_sync(){
    
    $checkFlag = checkSyncStartConditions();
    if ($checkFlag !== TRUE){ die($checkFlag); }
    
    update_option('woo_square_running_sync', 'manual');
    update_option('woo_square_running_sync_time', time());
    
    session_start();
    $_SESSION["woo_to_square"]["target_products"]['parent_id'] = 
            $_SESSION["woo_to_square"]["target_categories"]['parent_id']
            = Helpers::sync_db_log(
                    Helpers::ACTION_SYNC_START,
                    date("Y-m-d H:i:s"),
                    Helpers::SYNC_TYPE_MANUAL,
                    Helpers::SYNC_DIRECTION_WOO_TO_SQUARE
            );
    Helpers::debug_log('info', "************************************************************");
    Helpers::debug_log('info', "Start Manual Sync from Woo-commerce to Square: log:".
            $_SESSION["woo_to_square"]["target_categories"]['parent_id']);
    echo '1';
    die();

}

function woo_square_plugin_sync_woo_category_to_square() {

    session_start();

    $catId = sanitize_text_field($_POST['id']);
    if (!isset($_SESSION["woo_to_square"]["target_categories"][$catId])) {
        Helpers::debug_log('error', "Processing ajax unavailable category {$catId}");
        die();
    }
    
    $actionType = $_SESSION["woo_to_square"]["target_categories"][$catId]['action'];

    $square = new Square(get_option('woo_square_access_token'), get_option('woo_square_location_id'));
    $squareSynchronizer = new WooToSquareSynchronizer($square);
    $result = FALSE;
    Helpers::debug_log('info', "Manual sync: processing category:"
            ."{$_SESSION["woo_to_square"]["target_categories"][$catId]['name']}"
            ."[{$catId}] with action = "
            ."{$_SESSION["woo_to_square"]["target_categories"][$catId]['action']} ");

    switch ($actionType) {
        case 'add':
            $category = get_term_by('id', $catId, 'product_cat');
            $result = $squareSynchronizer->addCategory($category);
            if ($result===TRUE) {
                update_option("is_square_sync_{$catId}", 1);
            }
            $action = Helpers::ACTION_ADD;
            break;

        case 'update':
            $category = get_term_by('id', $catId, 'product_cat');
            $result = $squareSynchronizer->editCategory($category, $_SESSION["woo_to_square"]["target_categories"][$catId]['square_id']);
            if ($result===TRUE) {
                update_option("is_square_sync_{$catId}", 1);
            }
            $action = Helpers::ACTION_UPDATE;
            break;

        case 'delete':
            $item_square_id = isset($_SESSION["woo_to_square"]["target_categories"][$catId]['square_id']) ?
                    $_SESSION["woo_to_square"]["target_categories"][$catId]['square_id'] : null;

            if ($item_square_id) {
                $result = $squareSynchronizer->deleteCategory($item_square_id);
                
                //delete category from plugin delete table
                if ($result===TRUE) {
                    global $wpdb;
                    $wpdb->delete($wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA, ['square_id' => $item_square_id]
                    );
                }
            }
            $action = Helpers::ACTION_DELETE;
            break;
    }
    
    //log
    //check if response returned is bool or error response message
    $message = NULL;
    if (!is_bool($result)){
        $message = $result['message'];
        $result = FALSE;
    }
   
    Helpers::sync_db_log(
            $action,
            date("Y-m-d H:i:s"),
            Helpers::SYNC_TYPE_MANUAL,
            Helpers::SYNC_DIRECTION_WOO_TO_SQUARE,
            $catId,
            Helpers::TARGET_TYPE_CATEGORY,
            $result?Helpers::TARGET_STATUS_SUCCESS:Helpers::TARGET_STATUS_FAILURE,
            $_SESSION["woo_to_square"]["target_categories"]['parent_id'],
            $_SESSION["woo_to_square"]["target_categories"][$catId]['name'],
            $_SESSION["woo_to_square"]["target_categories"][$catId]['square_id'],
            $message
    );
    echo $result;
    die();
}

function woo_square_plugin_sync_woo_product_to_square() {

    session_start();
    $productId = sanitize_text_field($_POST['id']);
    if (!isset($_SESSION["woo_to_square"]["target_products"][$productId])) {
        Helpers::debug_log('error', "Processing ajax unavailable product item {$productId}");
        die();
    }
    $actionType = $_SESSION["woo_to_square"]["target_products"][$productId]['action'];

    $square = new Square(get_option('woo_square_access_token'), get_option('woo_square_location_id'));
    $squareSynchronizer = new WooToSquareSynchronizer($square);
    $result = FALSE;
    Helpers::debug_log('info', "Manual sync: processing product:"
            .$_SESSION["woo_to_square"]["target_products"][$productId]['name']
            ."[{$productId}] with action = "
            ."{$_SESSION["woo_to_square"]["target_products"][$productId]['action']} ");
    $product_square_id = NULL;
    if ( !strcmp($actionType, 'delete')) {    //delete

        $item_square_id = isset($_SESSION["woo_to_square"]["target_products"][$productId]['square_id']) ?
                $_SESSION["woo_to_square"]["target_products"][$productId]['square_id'] : null;

        if ($item_square_id) {
            $result = $squareSynchronizer->deleteProduct($item_square_id);
            //delete product from plugin delete table
            if ($result===TRUE) {
                global $wpdb;
                $wpdb->delete($wpdb->prefix . WOO_SQUARE_TABLE_DELETED_DATA, ['square_id' => $item_square_id]
                );
            }
        }
        $action = Helpers::ACTION_DELETE;

               
    } else {                                   //add/update
        
        $post = get_post($productId);       
        $product_square_id = $squareSynchronizer->checkSkuInSquare($post, $_SESSION["woo_to_square"]["suqare_items"]);
        $result = $squareSynchronizer->addProduct($post, $product_square_id);

        //update post meta 
        if ($result===TRUE) {
            update_post_meta($productId, 'is_square_sync', 1);
        } 
        $action = (!strcmp($actionType, 'update'))?Helpers::ACTION_UPDATE:
            Helpers::ACTION_ADD;
    }
    
    ///log the process
    //check if response returned is bool or error response message
    $message = NULL;
    if (!is_bool($result)){
        $message = $result['message'];
        $result = FALSE;
    }
    Helpers::sync_db_log($action,
            date("Y-m-d H:i:s"), 
            Helpers::SYNC_TYPE_MANUAL,
            Helpers::SYNC_DIRECTION_WOO_TO_SQUARE,
            $productId,
            Helpers::TARGET_TYPE_PRODUCT,
            $result?Helpers::TARGET_STATUS_SUCCESS:Helpers::TARGET_STATUS_FAILURE,
            $_SESSION["woo_to_square"]["target_products"]['parent_id'],
            $_SESSION["woo_to_square"]["target_products"][$productId]['name'],
            $product_square_id,
            $message
    );
    echo $result;
    die();
}

function woo_square_plugin_terminate_manual_woo_sync(){
    
    //stop synchronization if only started manually
    if ( !strcmp( get_option('woo_square_running_sync'), 'manual')){
        Helpers::debug_log('info', "Terminating manual sync process by ajax");
        update_option('woo_square_running_sync', false);
        update_option('woo_square_running_sync_time', 0);
    }
    
    session_start();
    
    //ensure function is not called twice
    if (!isset($_SESSION["woo_to_square"])){
        return;
    }  
    Helpers::debug_log('info', "End Manual Sync from Woo-commerce to Square: log:".
            $_SESSION["woo_to_square"]["target_categories"]['parent_id']);
    Helpers::debug_log('info', "************************************************************");
    unset($_SESSION["woo_to_square"]);
  
    echo "1";
    die();

    

}


//square -> woo
function woo_square_plugin_get_non_sync_square_data(){
    
    $checkFlag = checkSyncStartConditions();
    if ($checkFlag !== TRUE){ die(json_encode(['error'=>$checkFlag])); }
    Helpers::debug_log('info', "Manual sync: [square->woo] start gathering data");
    
    $square = new Square(get_option('woo_square_access_token'), get_option('woo_square_location_id'));
    $synchronizer = new SquareToWooSynchronizer($square);
    
    //for display
    $addProducts = $updateProducts = $deleteProducts = $addCategories 
            = $updateCategories = $deleteCategories = [];
    //display only one checkbox in update 
    $oneProductsUpdateCheckbox = TRUE;
    
    //1-get all square categories ( having is_square_sync = 0 or key not exists )
    $squareCategories = $synchronizer->getSquareCategories();
    $synchSquareIds = [];
    if(!empty($squareCategories)){
        //get previously linked categories to woo
        $wooSquareCats = $synchronizer->getUnsyncWooSquareCategoriesIds($squareCategories, $synchSquareIds);
    }else{
        $squareCategories = $wooSquareCats = [];
    }
    
    $targetCategories = [];
    
    //merge add and update categories
    foreach ($squareCategories as $cat) {
        if (!isset($wooSquareCats[$cat->id])) {      //add
            $targetCategories[$cat->id]['action'] = 'add';
            $targetCategories[$cat->id]['woo_id'] = NULL;
            $targetCategories[$cat->id]['name'] = $cat->name;
            
            //for display
            $addCategories[] = [
                'woo_id'=> NULL, 
                'checkbox_val' => $cat->id,
                'name'=> $cat->name
            ];

        }else{                                       //update
            //if category has square id but already synchronized, no need to synch again
            if(in_array($wooSquareCats[$cat->id][0], $synchSquareIds)){
               continue;
            }
            $targetCategories[$cat->id]['action'] = 'update';
            $targetCategories[$cat->id]['woo_id'] = $wooSquareCats[$cat->id][0];        
            $targetCategories[$cat->id]['name'] = $wooSquareCats[$cat->id][1];
            $targetCategories[$cat->id]['new_name'] = $cat->name;
            
            //for display
            $updateCategories[] = [
                'woo_id'=> $wooSquareCats[$cat->id][0], 
                'checkbox_val' => $cat->id, 
                'name'=> $wooSquareCats[$cat->id][1]
            ];

        }   
        
    }
    
    //2-get square products

    $targetProducts = $sessionProducts = [];
    $squareItems = $synchronizer->getSquareItems();
    $skippedProducts = $newSquareProducts = [];
    if ($squareItems){
        
        //get new square products and an array of products skipped from add/update actions
        $newSquareProducts = $synchronizer->getNewProducts($squareItems, $skippedProducts);
    }
    $sessionProducts = [];
	if(!empty($newSquareProducts['sku_misin_squ_woo_pro'])){
		foreach($newSquareProducts['sku_misin_squ_woo_pro'] as $sku_missin){
			$sku_missin_inside_product[] = [
				'woo_id'=> NULL, 
				'name'=> '"'.$sku_missin->name.'" from square',
				'sku_misin_squ_woo_pro_variable'=> 'sku_misin_squ_woo_pro_variable',
				'checkbox_val' => $sku_missin->id
			];
		}
	unset($newSquareProducts['sku_misin_squ_woo_pro']);
	}				
	if(!empty($newSquareProducts['sku_misin_squ_woo_pro_variable'])){
		foreach($newSquareProducts['sku_misin_squ_woo_pro_variable'] as $sku_missin){
			$sku_missin_inside_product[] = [
				'woo_id'=> NULL, 
				'name'=> '"'.$sku_missin->name.'" from square variations',
				'checkbox_val' => $sku_missin->id,
				'sku_misin_squ_woo_pro_variable' => 'sku_misin_squ_woo_pro_variable'
			];
		}
	unset($newSquareProducts['sku_misin_squ_woo_pro_variable']);
	}
	
    foreach ($newSquareProducts as $product){
        $targetProducts[$product->id]['action'] = 'add';
        $targetProducts[$product->id]['woo_id'] = NULL;
        $targetProducts[$product->id]['name'] = $product->name;
        
        //store whole returned response in session
        $sessionProducts[$product->id] = $product;
        
        //for display
        $addProducts[] = [
            'woo_id'=> NULL, 
            'name'=> $product->name,
            'checkbox_val' => $product->id
        ];
    }
    
        
    //construct session array
    session_start();
    $_SESSION["square_to_woo"] = []; 
    $_SESSION["square_to_woo"]["target_categories"] = $targetCategories;
    $_SESSION["square_to_woo"]["target_products"] = $sessionProducts;
    $_SESSION["square_to_woo"]["target_products"]["skipped_products"] = $skippedProducts;
    
    $squareInventoryArray=[];
    $squareInventory = $synchronizer->getSquareInventory();
    if (!empty($squareInventory)){
        $squareInventoryArray = $synchronizer->convertSquareInventoryToAssociative($squareInventory);
    }
    $_SESSION["square_to_woo"]["target_products"]["products_inventory"] = $squareInventoryArray;

    ob_start();
    include WOO_SQUARE_PLUGIN_PATH . 'views/partials/pop-up.php';
    $data = ob_get_clean();
    echo json_encode(['data' => $data ]);
    Helpers::debug_log('info', "Manual sync: [square->woo] start gathering data");

    die();
    
}

function woo_square_plugin_start_manual_square_to_woo_sync(){
    
    $checkFlag = checkSyncStartConditions();
    if ($checkFlag !== TRUE){ die($checkFlag); }
    
    update_option('woo_square_running_sync', 'manual');
    update_option('woo_square_running_sync_time', time());
    
    session_start();
    $_SESSION["square_to_woo"]["target_products"]['parent_id'] = $_SESSION["square_to_woo"]["target_categories"]['parent_id']
            = Helpers::sync_db_log(
                    Helpers::ACTION_SYNC_START,
                    date("Y-m-d H:i:s"),
                    Helpers::SYNC_TYPE_MANUAL,
                    Helpers::SYNC_DIRECTION_SQUARE_TO_WOO
            );
    Helpers::debug_log('info', "************************************************************");
    Helpers::debug_log('info', "Start Manual Sync from Square to Woo-commerce: log:".
            $_SESSION["square_to_woo"]["target_categories"]['parent_id']);
    echo '1';
    die();
}

function woo_square_plugin_sync_square_category_to_woo(){
    
    session_start();

    $catId = sanitize_text_field($_POST['id']);
    if (!isset($_SESSION["square_to_woo"]["target_categories"][$catId])) {
        Helpers::debug_log('error', "Processing ajax unavailable category {$catId}");
        die();
    }
    $actionType = $_SESSION["square_to_woo"]["target_categories"][$catId]['action'];

    $square = new Square(get_option('woo_square_access_token'), get_option('woo_square_location_id'));
    $squareSynchronizer = new SquareToWooSynchronizer($square);
    $result = FALSE;
    Helpers::debug_log('info', "Manual sync: processing category:"
            ."{$_SESSION["square_to_woo"]["target_categories"][$catId]['name']}"
            ."[{$catId}] with action = "
            ."{$_SESSION["square_to_woo"]["target_categories"][$catId]['action']} ");
    switch ($actionType) {
        case 'add':
            $category = new stdClass();
            $category->id = $catId;
            $category->name = $_SESSION["square_to_woo"]["target_categories"][$catId]['name'];
            $result = $squareSynchronizer->addCategoryToWoo($category);
            if ($result!==FALSE) {
                update_option("is_square_sync_{$result}", 1);            
                $target_id = $result;
                $result= TRUE;

            }
            $action = Helpers::ACTION_ADD;
            break;

        case 'update':
            $category = new stdClass();
            $category->id = $catId;
            $category->name = $_SESSION["square_to_woo"]["target_categories"][$catId]['new_name'];
            $result = $squareSynchronizer->updateWooCategory($category,
                    $_SESSION["square_to_woo"]["target_categories"][$catId]['woo_id']);
            if ($result!==FALSE) {
                update_option("is_square_sync_{$result}", 1);            
            }
            $target_id = $_SESSION["square_to_woo"]["target_categories"][$catId]['woo_id'];
            $action = Helpers::ACTION_UPDATE;
            break;        
    }
    
    //log  
    Helpers::sync_db_log(
            $action,
            date("Y-m-d H:i:s"),
            Helpers::SYNC_TYPE_MANUAL,
            Helpers::SYNC_DIRECTION_SQUARE_TO_WOO,
            isset($target_id)?$target_id:NULL,
            Helpers::TARGET_TYPE_CATEGORY,
            $result?Helpers::TARGET_STATUS_SUCCESS:Helpers::TARGET_STATUS_FAILURE,
            $_SESSION["square_to_woo"]["target_categories"]['parent_id'],
            $_SESSION["square_to_woo"]["target_categories"][$catId]['name'],
            $catId
    );
    echo $result;
    die();
}

function woo_square_plugin_sync_square_product_to_woo() {
    session_start();
    $result = FALSE;  //default value for returned response


    $prodSquareId = sanitize_text_field($_POST['id']);

    if (!strcmp($prodSquareId , 'update_products')){
        
        $square = new Square(get_option('woo_square_access_token'), get_option('woo_square_location_id'));
        $synchronizer = new SquareToWooSynchronizer($square);
        $squareItems = $synchronizer->getSquareItems();
        
        Helpers::debug_log('info', "Manual sync: processing update all products:");
        Helpers::debug_log('info', "Manual sync: the skipped products ids (no skus) :".
                json_encode( $_SESSION["square_to_woo"]["target_products"]["skipped_products"]));
        
        $result = TRUE;
        if ($squareItems) {
            foreach ($squareItems as $squareProduct) {
                
                //if not a new product or skipped product (has no skus)
                if ( (!isset($_SESSION["square_to_woo"]["target_products"][$squareProduct->id]) )
                    && (!in_array($squareProduct->id,$_SESSION["square_to_woo"]["target_products"]["skipped_products"]))        
                    ){
                    $id = $synchronizer->addProductToWoo($squareProduct,  $_SESSION["square_to_woo"]["target_products"]["products_inventory"]);
                    
                    if (!empty($id) && is_numeric($id)){
                       update_post_meta($id, 'is_square_sync', 1);
                       $resultStat = Helpers::TARGET_STATUS_SUCCESS;
                    }else{
                        $resultStat = Helpers::TARGET_STATUS_FAILURE;
                        $result = FALSE;
                    }

                    //log  
                    Helpers::sync_db_log(
                        Helpers::ACTION_UPDATE,
                        date("Y-m-d H:i:s"), 
                        Helpers::SYNC_TYPE_MANUAL,
                        Helpers::SYNC_DIRECTION_SQUARE_TO_WOO,
                        is_numeric($id) ? $id : NULL, 
                        Helpers::TARGET_TYPE_PRODUCT, 
                        $resultStat,
                        $_SESSION["square_to_woo"]["target_categories"]['parent_id'],
                        $squareProduct->name,
                        $squareProduct->id
                    );
                }
                
            }
        }
        
    }else{
        
        //add product action
        if (!isset($_SESSION["square_to_woo"]["target_products"][$prodSquareId])) {
            Helpers::debug_log('error', "Processing ajax unavailable product {$prodSquareId}");
            die();
        }
        Helpers::debug_log('info', "Manual sync: processing product:"
            ."{$_SESSION["square_to_woo"]["target_products"][$prodSquareId]->name}"
            ."[{$prodSquareId}] with action = ADD");
            
        $square = new Square(get_option('woo_square_access_token'), get_option('woo_square_location_id'));
        $squareSynchronizer = new SquareToWooSynchronizer($square);

        if (count($_SESSION["square_to_woo"]["target_products"][$prodSquareId]->variations)<=1){  //simple product
            $id = $squareSynchronizer->insertSimpleProductToWoo($_SESSION["square_to_woo"]["target_products"][$prodSquareId], $_SESSION["square_to_woo"]["target_products"]["products_inventory"]);

        }else{
            $id = $squareSynchronizer->insertVariableProductToWoo($_SESSION["square_to_woo"]["target_products"][$prodSquareId], $_SESSION["square_to_woo"]["target_products"]["products_inventory"]);
        }

        $action = Helpers::ACTION_ADD;
        $result = ($id !== FALSE) ? Helpers::TARGET_STATUS_SUCCESS : Helpers::TARGET_STATUS_FAILURE;


        if (!empty($id) && is_numeric($id)){
            update_post_meta($id, 'is_square_sync', 1);
        }
                    
        //log  
        Helpers::sync_db_log(
            $action,
            date("Y-m-d H:i:s"), 
            Helpers::SYNC_TYPE_MANUAL,
            Helpers::SYNC_DIRECTION_SQUARE_TO_WOO,
            is_numeric($id) ? $id : NULL, 
            Helpers::TARGET_TYPE_PRODUCT, 
            $result,
            $_SESSION["square_to_woo"]["target_categories"]['parent_id'],
            $_SESSION["square_to_woo"]["target_products"][$prodSquareId]->name,
            $prodSquareId
        );
    
    }
    echo $result;
    die();
}

function woo_square_plugin_terminate_manual_square_sync(){
    
    //stop synchronization if only started manually
    if ( !strcmp( get_option('woo_square_running_sync'), 'manual')){
        Helpers::debug_log('info', "Terminating manual sync process by ajax");
        update_option('woo_square_running_sync', false);
        update_option('woo_square_running_sync_time', 0);
    }
    
    session_start();
    
    //ensure function is not called twice
    if (!isset($_SESSION["square_to_woo"])){
        return;
    }
    Helpers::debug_log('info', "End Manual Sync from Square to Woo-commerce: log:".
            $_SESSION["square_to_woo"]["target_categories"]['parent_id']);
    Helpers::debug_log('info', "************************************************************");
    unset($_SESSION["square_to_woo"]);
    echo "1";
    die();
}
