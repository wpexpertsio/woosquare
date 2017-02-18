<?php

/**
 * Synchronize From Square To WooCommerce Class
 */
class SquareToWooSynchronizer {
    /*
     * @var square square class instance
     */

    protected $square;

    /**
     * 
     * @param object $square object of square class
     */
    public function __construct($square) {

        require_once WOO_SQUARE_PLUGIN_PATH . '_inc/Helpers.class.php';
        $this->square = $square;
    }

    /*
     * Sync All products, categories from Square to Woo-Commerce
     */

    public function syncFromSquareToWoo() {

        Helpers::debug_log('info', "-------------------------------------------------------------------------------");
        Helpers::debug_log('info', "Start Auto Sync from Square to Woo-commerce");
        $syncType = Helpers::SYNC_TYPE_AUTOMATIC;
        $syncDirection = Helpers::SYNC_DIRECTION_SQUARE_TO_WOO;
        //add start sync log record
        $logId = Helpers::sync_db_log(Helpers::ACTION_SYNC_START,
                date("Y-m-d H:i:s"), $syncType, $syncDirection);
                
        
        /* get all categories */
        $squareCategories = $this->getSquareCategories();
        
        /* get all items */      
        $squareItems = $this->getSquareItems();

        /* get Inventory of all items */
        $squareInventory = $this->getSquareInventory();
        $squareInventoryArray = [];
        if (!empty($squareInventory)){
            $squareInventoryArray = $this->convertSquareInventoryToAssociative($squareInventory);
        }


        //1- Update WooCommerce with categories from Square
        Helpers::debug_log('info', "1- Synchronizing categories (add/update)");
        $synchSquareIds = [];
        if(!empty($squareCategories)){
            //get previously linked categories to woo
            $wooSquareCats = $this->getUnsyncWooSquareCategoriesIds($squareCategories, $synchSquareIds);
        }else{
            $squareCategories = $wooSquareCats = [];
        }
        
        //add/update square categories
        foreach ($squareCategories as $cat){
            if (isset( $wooSquareCats[$cat->id] )) {  //update
                
                //do not update if it is already updated ( its id was returned 
                //in $synchSquareIds array )
                if(in_array($wooSquareCats[$cat->id][0], $synchSquareIds)){
                    continue;
                }                

                $result = $this->updateWooCategory($cat,
                                              $wooSquareCats[$cat->id][0]);
                if ($result!==FALSE) {
                    update_option("is_square_sync_{$result}", 1);            
                }
                $target_id = $wooSquareCats[$cat->id][0];
                $action = Helpers::ACTION_UPDATE;

            }else{          //add
                $result = $this->addCategoryToWoo($cat);
                if ($result!==FALSE) {
                    update_option("is_square_sync_{$result}", 1);           
                    $target_id = $result;
                    $result= TRUE;

                }
                $action = Helpers::ACTION_ADD;
            }
            //log category action
            Helpers::sync_db_log(
                    $action,
                    date("Y-m-d H:i:s"),
                    $syncType,
                    $syncDirection,
                    $target_id,
                    Helpers::TARGET_TYPE_CATEGORY,
                    $result?Helpers::TARGET_STATUS_SUCCESS:Helpers::TARGET_STATUS_FAILURE,
                    $logId,
                    $cat->name,
                    $cat->id
            );
        }
        
        // 2-Update WooCommerce with products from Square
        Helpers::debug_log('info', "2- Synchronizing products (add/update)");
        if ($squareItems) {
            foreach ($squareItems as $squareProduct) {
                $action = NULL;
                $id = $this->addProductToWoo($squareProduct, $squareInventoryArray, $action);
            
                if(is_null($action)){
                    continue;
                }
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
                    $logId,
                    $squareProduct->name,
                    $squareProduct->id
                );
            }
        }
        Helpers::debug_log('info', "End Auto Sync from Square to Woo-commerce");
        Helpers::debug_log('info', "-------------------------------------------------------------------------------");
    }

    /*
     * update WooCommerce with categoreis from Square
     */

    public function insertCategoryToWoo($category) {
        $product_categories = get_terms('product_cat', 'hide_empty=0');
        foreach ($product_categories as $categoryw) {
            $wooCategories[] = array('square_id' => get_option('category_square_id_' . $categoryw->term_id), 'name' => $categoryw->name, 'term_id' => $categoryw->term_id);
        }

        $wooCategory = Helpers::searchInMultiDimensionArray($wooCategories, 'square_id', $category->id);
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $category->name);
        remove_action('edited_product_cat', 'woo_square_edit_category');
        remove_action('create_product_cat', 'woo_square_add_category');

        if ($wooCategory) {
            wp_update_term($wooCategory['term_id'], 'product_cat', array('name' => $category->name, 'slug' => $slug));
            update_option('category_square_id_' . $wooCategory['term_id'], $category->id);
        } else {
            $result = wp_insert_term($category->name, 'product_cat', array('slug' => $slug));
            if (!is_wp_error($result) && isset($result['term_id'])) {
                update_option('category_square_id_' . $result['term_id'], $category->id);
            }
        }
        add_action('edited_product_cat', 'woo_square_edit_category');
        add_action('create_product_cat', 'woo_square_add_category');
    }
    
    
    /**
     * Add WooCommerce category from Square
     * @param object $category category square object
     * @return int|false created category id, false in case of error
     */

    public function addCategoryToWoo($category) {
        
        $retVal = FALSE;
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $category->name);
        remove_action('edited_product_cat', 'woo_square_edit_category');
        remove_action('create_product_cat', 'woo_square_add_category');

        $result = wp_insert_term($category->name, 'product_cat', array('slug' => $slug));
        if (!is_wp_error($result) && isset($result['term_id'])) {
            update_option('category_square_id_' . $result['term_id'], $category->id);
            $retVal = $result['term_id'];
        }
        add_action('edited_product_cat', 'woo_square_edit_category');
        add_action('create_product_cat', 'woo_square_add_category');
        
        return $retVal;
    }
    
    /*
     * update WooCommerce with categoreis from Square
     */

    public function updateWooCategory($category, $catId) {
        
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $category->name);
        remove_action('edited_product_cat', 'woo_square_edit_category');
        remove_action('create_product_cat', 'woo_square_add_category');

        wp_update_term($catId, 'product_cat', array('name' => $category->name, 'slug' => $slug));
        update_option('category_square_id_' .$catId, $category->id);

        add_action('edited_product_cat', 'woo_square_edit_category');
        add_action('create_product_cat', 'woo_square_add_category');
        
        return TRUE;
    }

    /*
     * update WooCommerce with products from Square
     */

    public function addProductToWoo($squareProduct, $squareInventory, &$action = FALSE) {

        Helpers::debug_log('info', "Adding Product '" . $squareProduct->name . "' to woo-commerce : " . json_encode($squareProduct));
        //Simple square product
        if (count($squareProduct->variations) <= 1) {
            Helpers::debug_log('info', "Product '{$squareProduct->name}' is simple");
            if (isset($squareProduct->variations[0]) && isset($squareProduct->variations[0]->sku) && $squareProduct->variations[0]->sku) {
                $square_product_sku = $squareProduct->variations[0]->sku;
                Helpers::debug_log('info', "Product SKU: " . $square_product_sku);
                $product_id_with_sku_exists = $this->checkIfProductWithSkuExists($square_product_sku, array("product", "product_variation"));
                if ($product_id_with_sku_exists) { // SKU already exists in other product
                    Helpers::debug_log('info', "Product SKU already exists");
                    $product = get_post($product_id_with_sku_exists[0]);
                    $parent_id = $product->post_parent;
                    $id = $this->insertSimpleProductToWoo($squareProduct, $squareInventory, $product_id_with_sku_exists[0]);
                    if ($parent_id) {
                        $this->deleteProductFromWoo($product->post_parent);
                    }
                    $action = Helpers::ACTION_UPDATE;
                } else {
                    $id = $this->insertSimpleProductToWoo($squareProduct, $squareInventory);
                    $action = Helpers::ACTION_ADD;
                }
            } else {

                Helpers::debug_log('notice', "Simple product $squareProduct->id ['$squareProduct->name'] skipped from synch ( square->woo ): no SKU found");
                $id = FALSE;
                $action = NULL;
				
            }
        }
        //Variable square product
        else {
            Helpers::debug_log('info', "Product '{$squareProduct->name}' is variable");
            $id = $this->insertVariableProductToWoo($squareProduct, $squareInventory, $action);
        }
        return $id;
    }

    function create_variable_woo_product($title, $desc, $cats = array(), $variations, $variations_key, $product_square_id = null,$master_image = NULL, $parent_id = null) {

        $post = array(
            'post_title' => $title,
            'post_content' => $desc,
            'post_status' => "publish",
            'post_name' => sanitize_title($title), //name/slug
            'post_type' => "product"
        );
        if ($parent_id) {
            $post['ID'] = $parent_id;
        }

        //Create product/post:
        remove_action('save_post', 'woo_square_add_edit_product');
        $new_prod_id = wp_insert_post($post);
        add_action('save_post', 'woo_square_add_edit_product', 10, 3);

        //make product type be variable:
        wp_set_object_terms($new_prod_id, 'variable', 'product_type');

        //add category to product:
        wp_set_object_terms($new_prod_id, $cats, 'product_cat');

        //################### Add size attributes to main product: ####################
        //Array for setting attributes
        $var_keys = array();
        $total_qty = 0;
        foreach ($variations as $variation) {
            $total_qty += (int) isset($variation["qty"]) ? $variation["qty"] : 0;
            $var_keys[] = sanitize_title($variation['name']);
            wp_insert_term(
                    $variation['name'], // the term
                    $variations_key, // the taxonomy
                    array(
                'slug' => sanitize_title($variation['name'])
                    )
            );
        }
        wp_set_object_terms($new_prod_id, $var_keys, $variations_key);

        $thedata = Array($variations_key => Array(
                'name' => $variations_key,
                'value' => implode(' | ', $var_keys),
                'is_visible' => 1,
                'is_variation' => 1,
                'position' => '0',
                'is_taxonomy' => 0
        ));
        update_post_meta($new_prod_id, '_product_attributes', $thedata);
        //########################## Done adding attributes to product #################
        //set product values:
        //update_post_meta($new_prod_id, '_stock_status', ( (int) $total_qty > 0) ? 'instock' : 'outofstock');
        update_post_meta($new_prod_id, '_stock_status', 'instock');

        update_post_meta($new_prod_id, '_stock', $total_qty);
        update_post_meta($new_prod_id, '_visibility', 'visible');
        update_post_meta($new_prod_id, 'square_id', $product_square_id);
        update_post_meta($new_prod_id, '_default_attributes', array());

        //###################### Add Variation post types for sizes #############################
        $i = 1;
        $var_prices = array();
        //set IDs for product_variation posts:
        foreach ($variations as $variation) {
            $my_post = array(
                'post_title' => 'Variation #' . $i . ' of ' . count($variations) . ' for product#' . $new_prod_id,
                'post_name' => 'product-' . $new_prod_id . '-variation-' . $i,
                'post_status' => 'publish',
                'post_parent' => $new_prod_id, //post is a child post of product post
                'post_type' => 'product_variation', //set post type to product_variation
                'guid' => home_url() . '/?product_variation=product-' . $new_prod_id . '-variation-' . $i
            );

            if (isset($variation['product_id'])) {
                $my_post['ID'] = $variation['product_id'];
            }

            //Insert ea. post/variation into database:
            remove_action('save_post', 'woo_square_add_edit_product');
            $attID = wp_insert_post($my_post);
            add_action('save_post', 'woo_square_add_edit_product', 10, 3);

            //Create 2xl variation for ea product_variation:
            update_post_meta($attID, 'attribute_' . $variations_key, sanitize_title($variation['name']));
            update_post_meta($attID, '_regular_price', (int) $variation["price"]);
            update_post_meta($attID, '_price', (int) $variation["price"]);
            $var_prices[$i - 1]['id'] = $attID;
            $var_prices[$i - 1]['regular_price'] = sanitize_title($variation['price']);

            //add size attributes to this variation:
            wp_set_object_terms($attID, $var_keys, 'pa_' . sanitize_title($variation['name']));

            update_post_meta($attID, '_sku', $variation["sku"]);
            update_post_meta($attID, '_manage_stock', isset($variation["qty"]) ? 'yes' : 'no');
            update_post_meta($attID, 'variation_square_id', $variation["variation_id"]);
            if (isset($variation["qty"])) {
                update_post_meta($attID, '_stock_status', ( (int) $variation["qty"] > 0) ? 'instock' : 'outofstock');
                update_post_meta($attID, '_stock', $variation["qty"]);
            } else {
                update_post_meta($attID, '_stock_status', 'instock');
            }
            $i++;
        }

        $i = 0;

        Helpers::debug_log('info', "The product prices are: " . json_encode($var_prices));
        foreach ($var_prices as $var_price) {
            $regular_prices[] = $var_price['regular_price'];
            $sale_prices[] = $var_price['regular_price'];
        }
        update_post_meta($new_prod_id, '_price', min($sale_prices));
        update_post_meta($new_prod_id, '_min_variation_price', min($sale_prices));
        update_post_meta($new_prod_id, '_max_variation_price', max($sale_prices));
        update_post_meta($new_prod_id, '_min_variation_regular_price', min($regular_prices));
        update_post_meta($new_prod_id, '_max_variation_regular_price', max($regular_prices));

        update_post_meta($new_prod_id, '_min_price_variation_id', $var_prices[array_search(min($regular_prices), $regular_prices)]['id']);
        update_post_meta($new_prod_id, '_max_price_variation_id', $var_prices[array_search(max($regular_prices), $regular_prices)]['id']);
        update_post_meta($new_prod_id, '_min_regular_price_variation_id', $var_prices[array_search(min($regular_prices), $regular_prices)]['id']);
        update_post_meta($new_prod_id, '_max_regular_price_variation_id', $var_prices[array_search(max($regular_prices), $regular_prices)]['id']);
        
        if (isset($master_image) && !empty($master_image->url)){
               
            //if square img id not found, download new image
            if (strcmp(get_post_meta( $new_prod_id, 'square_master_img_id',TRUE),$master_image->url)){
                Helpers::debug_log('info', "uploading product feature image");
                $this->uploadFeaturedImage($new_prod_id, $master_image);
            }
        }
        
        return $new_prod_id;
    }

    /*
     * Insert variable product to woo-commerece
     */

    public function insertVariableProductToWoo($squareProduct, $squareInventory, &$action= FALSE) {
        
        $term_id = 0;
        if (isset ($squareProduct->category)){
            $wp_category = get_term_by('name', $squareProduct->category->name, 'product_cat');
            $term_id = isset($wp_category->term_id) ? $wp_category->term_id : 0;
        }        

        //Try to get the product id from the SKU if set.
        $productIds = array();
        $product_id_with_sku_exists = false;
        foreach ($squareProduct->variations as $variation) {
            $square_product_sku = $variation->sku;
            if ($square_product_sku) {
                $product_id_with_sku_exists = $this->checkIfProductWithSkuExists($square_product_sku, array("product", "product_variation"));
            }
            if ($product_id_with_sku_exists) {
                $productIds[$square_product_sku] = $product_id_with_sku_exists[0];
            }
        }
        Helpers::debug_log('info', "The Product '$squareProduct->name' sku array:".  json_encode($productIds));

        if ($productIds) { //SKU already exits
            $product = get_post(reset($productIds));
            $parent_id = $product->post_parent;
            Helpers::debug_log('info', "The Product '$squareProduct->name' sku parent: { ".$parent_id." }");
            if ($parent_id) { // woo product is variable
                $variations = array();
                foreach ($squareProduct->variations as $variation) {


                    //don't add product variaton that doesn't have SKU
                    if (empty($variation->sku)) {
                        Helpers::debug_log('notice', "Variable square product ['{$squareProduct->name}'] variation '{$variation->name}' skipped from synch ( square->woo ): no SKU found");
                        continue;
                    }
                    $price = isset($variation->price_money)?number_format(($variation->price_money->amount / 100), 2):'';
                    $data = array('variation_id' => $variation->id, 'sku' => $variation->sku, 'name' => $variation->name, 'price' => $price );
                    
                    //put variation product id in variation data to be updated 
                    //instead of created
                    if (isset($productIds[$variation->sku] )){
                        $data['product_id'] = $productIds[$variation->sku];
                    }

                    if (isset($variation->track_inventory) && $variation->track_inventory) {
                        if (isset($squareInventory[$variation->id])){
                            $data['qty'] = $squareInventory[$variation->id];
                        }
                    }
                    $variations[] = $data;
                }
                Helpers::debug_log('info', "constructed variation array" . json_encode($variations));
                $prodDescription = isset($squareProduct->description)?$squareProduct->description:' ';
                $prodImg = isset($squareProduct->master_image)?$squareProduct->master_image:NULL;
                $id = $this->create_variable_woo_product($squareProduct->name, $prodDescription, array($term_id), $variations, "variation", $squareProduct->id,$prodImg, $parent_id);
            } else { // woo product is simple
                $variations = array();
                Helpers::debug_log('info', "The Product '{$squareProduct->name}' has no parent in Woo");

                foreach ($squareProduct->variations as $variation) {

                    //don't add product variaton that doesn't have SKU
                    if (empty($variation->sku)) {
                        Helpers::debug_log('notice', "Variable square product ['{$squareProduct->name}'] variation '{$variation->name}' skipped from synch ( square->woo ): no SKU found");
                        continue;
                    }
                    $price = isset($variation->price_money)?number_format(($variation->price_money->amount / 100), 2):'';
                    $data = array('variation_id' => $variation->id, 'sku' => $variation->sku, 'name' => $variation->name, 'price' => $price);
                    if (isset($productIds[$variation->sku] )){
                        Helpers::debug_log('info', "------->" . $productIds[$variation->sku] . '====' . $variation->sku);
                        $data['product_id'] = $productIds[$variation->sku];
                    }
                    if (isset($variation->track_inventory) && $variation->track_inventory) {
                        if (isset($squareInventory[$variation->id])){
                            $data['qty'] = $squareInventory[$variation->id];
                        }
                    }
                    $variations[] = $data;
                }
                Helpers::debug_log('info', "constructed variation array" . json_encode($variations));
                $prodDescription = isset($squareProduct->description)?$squareProduct->description:' ';
                $prodImg = isset($squareProduct->master_image)?$squareProduct->master_image:NULL;
                $id = $this->create_variable_woo_product($squareProduct->name, $prodDescription, array($term_id), $variations, "variation", $squareProduct->id, $prodImg);
            }
            $action = Helpers::ACTION_UPDATE;
        } else { //SKU not exists
            $variations = array();
            $noSkuCount = 0;
            foreach ($squareProduct->variations as $variation) {

                //don't add product variaton that doesn't have SKU
                if (empty($variation->sku)) {
                    Helpers::debug_log('notice', "Variable square product ['{$squareProduct->name}'] variation '{$variation->name}' skipped from synch ( square->woo ): no SKU found");
                    $noSkuCount ++;
                    continue;
                }
                $price = isset($variation->price_money)?number_format(($variation->price_money->amount / 100), 2):'';
                $data = array('variation_id' => $variation->id, 'sku' => $variation->sku, 'name' => $variation->name, 'price' => $price);
                if (isset($variation->track_inventory) && $variation->track_inventory) {
                    if (isset($squareInventory[$variation->id])){
                        $data['qty'] = $squareInventory[$variation->id];
                    }
                }
                $variations[] = $data;
            }
            if ($noSkuCount == count($squareProduct->variations)){
                Helpers::debug_log('notice', "Product '{$squareProduct->name}'[{$squareProduct->id}] skipped: none of the variations has SKU");
                return FALSE;
            }
            Helpers::debug_log('info', "constructed variation array" . json_encode($variations));
            $prodDescription = isset($squareProduct->description)?$squareProduct->description:' ';
            $prodImg = isset($squareProduct->master_image)?$squareProduct->master_image:NULL;
            $id = $this->create_variable_woo_product($squareProduct->name, $prodDescription, array($term_id), $variations, "variation", $squareProduct->id, $prodImg);
            $action = Helpers::ACTION_ADD;
        }
        return $id;
    }

    /*
     * insert simple product to woo-commerce
     */

    public function insertSimpleProductToWoo($squareProduct, $squareInventory, $productId = null) {


        $term_id = 0;
        if (isset($squareProduct->category)){
            $wp_category = get_term_by('name', $squareProduct->category->name, 'product_cat');
            $term_id = $wp_category->term_id ? $wp_category->term_id : 0;   
        }
        
        $post_title = $squareProduct->name;
        $post_content = isset($squareProduct->description) ? $squareProduct->description : '';

        $my_post = array(
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'product',
            'tax_input' => array('product_cat' => $term_id)
        );

        //check if product id provided to the function
        if ($productId) {
            $my_post['ID'] = $productId;
            Helpers::debug_log('info', "Inserting product to database with ID : " . $productId);
        } else {
            Helpers::debug_log('info', "Inserting product to database");
        }

        // Insert the post into the database
        remove_action('save_post', 'woo_square_add_edit_product');
        $id = wp_insert_post($my_post, true);
        add_action('save_post', 'woo_square_add_edit_product', 10, 3);
        Helpers::debug_log('info', "Product inserted to databse with ID: " . json_encode($id));
        if ($id) {
            $variation = $squareProduct->variations[0];
            $price = isset($variation->price_money)?number_format(($variation->price_money->amount / 100), 2):'';
            update_post_meta($id, '_visibility', 'visible');
            update_post_meta($id, '_stock_status', 'instock');
            update_post_meta($id, '_regular_price', $price );
            update_post_meta($id, '_price', $price);
            update_post_meta($id, '_sku', isset($variation->sku) ? $variation->sku : '');

            if (isset($squareProduct->variations[0]->track_inventory) && $squareProduct->variations[0]->track_inventory) {
                update_post_meta($id, '_manage_stock', 'yes');
            } else {
                update_post_meta($id, '_manage_stock', 'no');
            }

            Helpers::debug_log('info', "updating product variation with quantity");
            $this->addInventoryToWoo($id, $variation, $squareInventory);

            update_post_meta($id, 'square_id', $squareProduct->id);
            update_post_meta($id, 'variation_square_id', $variation->id);
            update_post_meta($id, '_termid', 'update');
            if (isset($squareProduct->master_image) && !empty($squareProduct->master_image->url)){
               
                //if square img id not found, download new image
                if (strcmp(get_post_meta( $id, 'square_master_img_id',TRUE),$squareProduct->master_image->url)){
                    Helpers::debug_log('info', "uploading product feature image");
                    $this->uploadFeaturedImage($id, $squareProduct->master_image);
                }
            }
        return $id;
        }
        return FALSE;
    }

    public function deleteProductFromWoo($product_id) {
        Helpers::debug_log('info', "Deleting product id: " . $product_id);
        remove_action('before_delete_post', 'woo_square_delete_product');
        wp_delete_post($product_id, true);
        add_action('before_delete_post', 'woo_square_delete_product');
    }

    public function checkIfProductWithSkuExists($square_product_sku, $productType = 'product') {
        $args = array(
            'post_type' => $productType,
            'meta_query' => array(
                array(
                    'key' => '_sku',
                    'value' => $square_product_sku
                )
            ),
            'fields' => 'ids'
        );
        // perform the query
        $query = new WP_Query($args);

        $ids = $query->posts;

        // do something if the meta-key-value-pair exists in another post
        if (!empty($ids)) {
            Helpers::debug_log('info', "Product with SKU [{$square_product_sku}] exists prod ids:" . json_encode($ids));
            return $ids;
        } else {
            Helpers::debug_log('info', "Product with SKU [{$square_product_sku}] does not exist");
            return false;
        }
    }

    function uploadFeaturedImage($product_id, $master_image) {

        

        // Add Featured Image to Post
        $image = $master_image->url; // Define the image URL here
        // magic sideload image returns an HTML image, not an ID
        $media = media_sideload_image($image, $product_id);

        // therefore we must find it so we can set it as featured ID
        if (!empty($media) && !is_wp_error($media)) {
            $args = array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'post_parent' => $product_id
            );

            $attachments = get_posts($args);

            if (isset($attachments) && is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    // grab source of full size images (so no 300x150 nonsense in path)
                    $image = wp_get_attachment_image_src($attachment->ID, 'full');
                    // determine if in the $media image we created, the string of the URL exists
                    if (strpos($media, $image[0]) !== false) {
                        // if so, we found our image. set it as thumbnail
                        set_post_thumbnail($product_id, $attachment->ID);
                        
                        //update square img id to prevent downloading it again each synch
                        update_post_meta($product_id,'square_master_img_id',$master_image->url);
                        // only want one image
                        break;
                    }
                }
            }
        }
    }

    function addInventoryToWoo($productId, $variation, $inventoryArray) {

        if(isset($inventoryArray[$variation->id])){
            update_post_meta($productId, '_stock', $inventoryArray[$variation->id]);
        }
    }
    
    /**
     * Get all square categories
     * @return object|false the square response object, false if error occurs
     */    
    public function getSquareCategories(){
        /* get all categories */
        $url = $this->square->getSquareURL() . '/categories';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $this->square->getAccessToken(), 'Accept: application/json'));
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $squareCategories = json_decode($response);
        if($http_status!==200){
            Helpers::debug_log('error', "Error in getting all categories curl request " . $response);
            return false;
        }        
        return $squareCategories;
    }
    
    
    /**
     * Get categories ids linked to square if found from the given square 
     * categories, and an array of the synchronized ones from those linked 
     * categories
     * @global object $wpdb
     * @param object $squareCategories square categories object
     * @param array $syncSquareCats synchronized category ids 
     * @return array Associative array with key: category square id , 
     *               value: array(category_id, category old name), and the 
     *               square synchronized categories ids in the passed array
     */
    
    public function getUnsyncWooSquareCategoriesIds($squareCategories, &$syncSquareCats){

        global $wpdb;
        $wooSquareCategories = [];
        
        //return if empty square categories
        if (empty($squareCategories)){
            return $wooSquareCategories;
        }
        
        
        //get all square ids
        $optionValues =  ' ( ';
        foreach ($squareCategories as $squareCategory){
            $optionValues.= "'{$squareCategory->id}',";
            $originalSquareCategoriesArray[$squareCategory->id] = $squareCategory->name;
        }
        $optionValues = substr($optionValues, 0, strlen($optionValues) - 1);
        $optionValues .= " ) ";


        //get option keys for the given square id values
        $categoriesSquareIdsQuery = "
            SELECT option_name, option_value
            FROM {$wpdb->prefix}options 
            WHERE option_value in {$optionValues}";

        $results = $wpdb->get_results($categoriesSquareIdsQuery, OBJECT);
        
        //select categories again to see if they need update
        $syncQuery = "
            SELECT term_id, name
            FROM {$wpdb->terms}
            WHERE term_id in ( ";
        $parameters = [];
        $addCondition = " %d ,";

        
        
        if (!is_wp_error($results)){
            foreach ($results as $row) {

                //get id from string
                preg_match('#category_square_id_(\d+)#is', $row->option_name, $matches);
                if (!isset($matches[1])) {
                    continue;
                }            
                //add square id to array
                $wooSquareCategories[$row->option_value] = $matches[1];
               
            }
            if(!empty($wooSquareCategories)){
                foreach ($squareCategories as $sqCat){
                    
                    if(isset($wooSquareCategories[$sqCat->id])){
                        //add id and name to be used in select synchronized categries query
                        $syncQuery.= $addCondition;
                        $parameters[] = $wooSquareCategories[$sqCat->id];
                    }
                }
            }
            
            
            if(!empty($parameters)){
                
                $syncQuery = substr($syncQuery, 0, strlen($syncQuery) - 1);
                $syncQuery.= ")";
                $sql =$wpdb->prepare($syncQuery, $parameters);
                $results = $wpdb->get_results($sql);
                foreach ($results as $row){
                    
                    $key = array_search($row->term_id, $wooSquareCategories);

                    if ($key){
                        $wooSquareCategories[$key] = [ $row->term_id, $row->name];
                        if (!strcmp($row->name, $originalSquareCategoriesArray[$key])){
                            $syncSquareCats[] = $row->term_id;
                        }
                        
                    }
                    
                }

            }  
        }
        return $wooSquareCategories;
        
    }
    
    public function getNewProducts($squareItems, &$skippedProducts) {

        Helpers::debug_log('info', 'Searching for new products in all items response');
        $newProducts = [];

        foreach ($squareItems as $squareProduct) {
            //Simple square product
            if (count($squareProduct->variations) <= 1) {
  
                Helpers::debug_log('info', "Product '{$squareProduct->name}' is simple");
                if (isset($squareProduct->variations[0]) && isset($squareProduct->variations[0]->sku) && $squareProduct->variations[0]->sku) {
                    $square_product_sku = $squareProduct->variations[0]->sku;
                    Helpers::debug_log('info', "Product SKU: " . $square_product_sku);
                    $product_id_with_sku_exists = $this->checkIfProductWithSkuExists($square_product_sku, array("product", "product_variation"));
                    if (!$product_id_with_sku_exists) { // SKU already exists in other product
                        Helpers::debug_log('info', "Product SKU not exists");
                        $newProducts[] = $squareProduct;
                    }
                } else {
					
					$newProducts['sku_misin_squ_woo_pro'][] = $squareProduct;
					$skippedProducts[] = $squareProduct->id;
                    Helpers::debug_log('notice', "Simple product ['$squareProduct->name'] skipped from synch ( square->woo ): no SKU found");
                }
            } else {//Variable square product
                Helpers::debug_log('info', "Product '{$squareProduct->name}' is variable");
                
                //if any sku was found linked to a woo product-> skip this product
                //as it's considered old
                $addFlag = TRUE; $noSkuCount = 0;
                foreach ($squareProduct->variations as $variation) {
					
                    if (isset($variation->sku) && (!empty($variation->sku))){
                        if($this->checkIfProductWithSkuExists($variation->sku, array("product", "product_variation"))){
                            //break loop as this product is not new
                            $addFlag = FALSE;
                            break;
                        }
                    }else{
                          $noSkuCount++;
                    }
                }
				
				
				//return skipped product array 
				foreach ($squareProduct->variations as $variation) {
					if ((empty($variation->sku))){
						$newProducts['sku_misin_squ_woo_pro_variable'][] = $squareProduct;
						//if one sku missing break the loop
						break;
					 }
				}
				
				
				
                //skip whole product if none of the variation has sku
                if ($noSkuCount == count($squareProduct->variations)){
                    Helpers::debug_log('notice', "Product '{$squareProduct->name}'[{$squareProduct->id}] skipped: none of the variations has SKU");
                    $skippedProducts[] = $squareProduct->id;
                }elseif ($addFlag){             //sku exists but not found in woo
                    $newProducts[] = $squareProduct;
                }
            }
        }
        return $newProducts;
    }

    
    
    /**
     * 
     * @return object|false the square response object, false if error occurs
     */
    public function getSquareItems() {

        /* get all items from square */
        $url = $this->square->getSquareURL() . '/items';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $this->square->getAccessToken(), 'Accept: application/json'));
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $squareItems = json_decode($response);
        if ($http_status !== 200) {
            Helpers::debug_log('error', "Error in getting all products curl request " . $response);
            return false;
        }

        return $squareItems;
    }
    
    
    public function getSquareInventory(){
        /* get Inventory of all items */
        $url = $this->square->getSquareURL() . '/inventory';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $this->square->getAccessToken(), 'Accept: application/json'));
        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $squareInventory = json_decode($response);
        if ($http_status !== 200) {
            Helpers::debug_log('error', "Error in getting all product inventory curl request " . $response);
            return false;
        }
        return $squareInventory;
    }
    
    
    /**
     * Convert square inventory objects to associative array
     * @return array key: inventory variation id, value: quantity_on_hand
     */
    public function convertSquareInventoryToAssociative($squareInventory) {

        $squareInventoryArray = [];
        foreach ($squareInventory as $inventory) {
            $squareInventoryArray[$inventory->variation_id] 
                    = $inventory->quantity_on_hand;
        }
        Helpers::debug_log('info', "The Simplified inventory curl object" . json_encode($squareInventoryArray));


        return $squareInventoryArray;
    }

}
