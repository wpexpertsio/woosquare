<?php
/*
  Plugin Name: WooSquare
  Plugin URI: https://wpexperts.io/products/woosquare/
  Description: WooSquare purpose is to migrate & synchronize data (sales –customers-invoices-products inventory) between Square system point of sale & Woo commerce plug-in. 
  Version: 1.0.3
  Author: Wpexpertsio
  Author URI: https://wpexperts.io/
  License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('WOO_SQUARE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_SQUARE_PLUGIN_PATH', plugin_dir_path(__FILE__));

define('WOO_SQUARE_TABLE_DELETED_DATA','woo_square_integration_deleted_data');
define('WOO_SQUARE_TABLE_SYNC_LOGS','woo_square_integration_logs');

//max sync running time
define('WOO_SQUARE_MAX_SYNC_TIME',60*20);


add_action('admin_menu', 'woo_square_settings_page');
add_action('admin_enqueue_scripts', 'woo_square_script');
add_action('wp_ajax_manual_sync', "woo_square_manual_sync");
add_action('save_post', 'woo_square_add_edit_product', 10, 3);
add_action('before_delete_post', 'woo_square_delete_product');
add_action('create_product_cat', 'woo_square_add_category');
add_action('edited_product_cat', 'woo_square_edit_category');
add_action('delete_product_cat', 'woo_square_delete_category',10,3);
add_action('woocommerce_order_refunded', 'woo_square_create_refund', 10, 2);
add_action('woocommerce_order_status_completed', 'woo_square_complete_order');
add_action( 'wp_loaded','woo_square_post_savepage_load_admin_notice' );





register_activation_hook(__FILE__, 'square_plugin_activation');

//import classes
require_once WOO_SQUARE_PLUGIN_PATH . '_inc/square.class.php';
require_once WOO_SQUARE_PLUGIN_PATH . '_inc/Helpers.class.php';
require_once WOO_SQUARE_PLUGIN_PATH . '_inc/WooToSquareSynchronizer.class.php';
require_once WOO_SQUARE_PLUGIN_PATH . '_inc/SquareToWooSynchronizer.class.php';
require_once WOO_SQUARE_PLUGIN_PATH . '/_inc/admin/ajax.php';
require_once WOO_SQUARE_PLUGIN_PATH . '/_inc/admin/pages.php';


function woo_square_checkOrAddPluginTables(){
    //create tables
	require_once  ABSPATH . '/wp-admin/includes/upgrade.php' ;
    global $wpdb;
	
    
	
     $del_prod_table = $wpdb->prefix.WOO_SQUARE_TABLE_DELETED_DATA;
	
	
    if ($wpdb->get_var("SHOW TABLES LIKE '$del_prod_table'") != $del_prod_table) {
        // echo '123';
        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";

        
      $sql = "CREATE TABLE " . $del_prod_table . " (
			`square_id` varchar(50) NOT NULL,
                        `target_id` bigint(20) NOT NULL,
                        `target_type` tinyint(2) NULL,
                        `name` varchar(255) NULL,
			PRIMARY KEY (`square_id`)
		) $charset_collate;";
		// echo $sql;
		
		
        dbDelta($sql);
    }
    
    //logs table
    $sync_logs_table = $wpdb->prefix.WOO_SQUARE_TABLE_SYNC_LOGS;
    if ($wpdb->get_var("SHOW TABLES LIKE '$sync_logs_table'") != $sync_logs_table) {
        // echo '456';
        if (!empty($wpdb->charset))
            $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate))
            $charset_collate .= " COLLATE $wpdb->collate";


        $sql = "CREATE TABLE " . $sync_logs_table . " (
                    `id` bigint(20) auto_increment NOT NULL,
                    `target_id` bigint(20) NULL,
                    `target_type` tinyint(2) NULL,
                    `target_status` tinyint(1) NULL,
                    `parent_id` bigint(20) NOT NULL default '0',
                    `square_id` varchar(50) NULL,
                    `action`  tinyint(3) NOT NULL,
                    `date` TIMESTAMP NOT NULL,
                    `sync_type` tinyint(1) NULL,
                    `sync_direction` tinyint(1) NULL,
                    `name` varchar(255) NULL,
                    `message` text NULL,
                    PRIMARY KEY (`id`)
            ) $charset_collate;";
        dbDelta($sql);
    }
}

/*
 * square activation
 */

function square_plugin_activation() {
    $user_id = username_exists('square_user');
    if (!$user_id) {
        $random_password = wp_generate_password(12);
        $user_id = wp_create_user('square_user', $random_password);
        wp_update_user(array('ID' => $user_id, 'first_name' => 'Square', 'last_name' => 'User'));
    }
	
	//create plugin tables when plugin activate..
	woo_square_checkOrAddPluginTables();
    update_option('woo_square_merging_option', 1);
    update_option('sync_on_add_edit', 1);
    
    
}


/**
 * include script
 */
function woo_square_script() {
    
    wp_enqueue_script('woo_square_script', WOO_SQUARE_PLUGIN_URL . '_inc/js/script.js', array('jquery')); 
    wp_localize_script('woo_square_script', 'myAjax', array('ajaxurl' => admin_url('admin-ajax.php')));

    wp_enqueue_style('woo_square_pop-up', WOO_SQUARE_PLUGIN_URL . '_inc/css/pop-up.css');
    wp_enqueue_style('woo_square_synchronization', WOO_SQUARE_PLUGIN_URL . '_inc/css/synchronization.css');
   
}

/*
 * Ajax action to execute manual sync
 */

function woo_square_manual_sync() {
    
    ini_set('max_execution_time', 0);
    
    if(!get_option('woo_square_access_token')){
        return;
    }
    
    if(get_option('woo_square_running_sync') && (time()-(int)get_option('woo_square_running_sync_time')) < (WOO_SQUARE_MAX_SYNC_TIME) ){
        Helpers::debug_log('error',"Manual Sync Request: There is already sync running");
        echo 'There is another Synchronization process running. Please try again later. Or <a href="'. admin_url('admin.php?page=square-settings&terminate_sync=true').'" > terminate now </a>';
        die();
    }
    
    update_option('woo_square_running_sync', true);
    update_option('woo_square_running_sync_time', time());
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

        $sync_direction = sanitize_text_field($_GET['way']);
        $square = new Square(get_option('woo_square_access_token'),get_option('woo_square_location_id'));
        if ($sync_direction == 'wootosqu') {
            $squareSynchronizer = new WooToSquareSynchronizer($square);
            $squareSynchronizer->syncFromWooToSquare();
        } else if ($sync_direction == 'squtowoo') {          
            $squareSynchronizer = new SquareToWooSynchronizer($square);
            $squareSynchronizer->syncFromSquareToWoo();
        }
    }
    update_option('woo_square_running_sync', false);
    update_option('woo_square_running_sync_time', 0);
    die();
}


function woo_square_post_savepage_load_admin_notice() {
	// Use html_compress($html) function to minify html codes.
	
			
		if(!empty($_GET['post'])){
			$Gpost = sanitize_text_field($_GET['post']);
		
			$admin_notice_square = get_post_meta($Gpost, 'admin_notice_square', true);
			if(!empty($admin_notice_square)){
				echo '<div class="notice notice-error"><p>'.$admin_notice_square.'</p></div>';
				delete_post_meta($Gpost, 'admin_notice_square', 'Product enable to sync to Square due to Sku missing ');

			}
		}	
}



/*
 * Adding and editing new product
 */

function woo_square_add_edit_product($post_id, $post, $update) {
	// checking Would you like to synchronize your product on every product edit or update ?   
	$sync_on_add_edit = get_option( 'sync_on_add_edit', $default = false ) ;
	if($sync_on_add_edit == '1'){
			
		
		//Avoid auto save from calling Square APIs.
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		   

		if ($update && $post->post_type == "product" && $post->post_status == "publish") {
			
			update_post_meta($post_id, 'is_square_sync', 0);
			Helpers::debug_log('info',"[add_update_product_hook] Start updating product on Square");

		
			if(!get_option('woo_square_access_token')){
				return;
			}
			

			$product_square_id = get_post_meta($post_id, 'square_id', true);
			$square = new Square(get_option('woo_square_access_token'),get_option('woo_square_location_id'));
			
			$squareSynchronizer = new WooToSquareSynchronizer($square);       
			$result = $squareSynchronizer->addProduct($post, $product_square_id);

			$termid = get_post_meta($post_id, '_termid', true);
			if ($termid == '') {//new product
				$termid = 'update';
			}
			update_post_meta($post_id, '_termid', $termid);
			
			if( $result===TRUE ){
				update_post_meta($post_id, 'is_square_sync', 1);  
			}

			Helpers::debug_log('info',"[add_update_product_hook] End updating product on Square");

			
		}
	} else {
		update_post_meta($post_id, 'is_square_sync', 0);  
	}
}

/*
 * Deleting product 
 */

function woo_square_delete_product($post_id) {
    
    //Avoid auto save from calling Square APIs.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    $product_square_id = get_post_meta($post_id, 'square_id', true);
    $product= get_post($post_id);
    if ($product->post_type == "product" && !empty($product_square_id)) {
        
        Helpers::debug_log('info',"[delete_product_hook] Start deleting product {$post_id} [square:{$product_square_id}] from Square");

        global $wpdb;

        $wpdb->insert($wpdb->prefix.WOO_SQUARE_TABLE_DELETED_DATA,
                [
                    'square_id'  => $product_square_id,
                    'target_id'  => $post_id,
                    'target_type'=> Helpers::TARGET_TYPE_PRODUCT,
                    'name'       => $product->post_title
                ]
        );
                
        if(!get_option('woo_square_access_token')){
            return;
        }

        $square = new Square(get_option('woo_square_access_token'),get_option('woo_square_location_id'));
        $squareSynchronizer = new WooToSquareSynchronizer($square);       
        $result = $squareSynchronizer->deleteProduct($product_square_id);
        
        
        //delete product from plugin delete table
        if($result===TRUE){
            $wpdb->delete($wpdb->prefix.WOO_SQUARE_TABLE_DELETED_DATA,
                ['square_id'=> $product_square_id ]
            );
            Helpers::debug_log('info',"[delete_product_hook] Product {$post_id} deleted successfully from Square");

            
        }
        Helpers::debug_log('info',"[delete_product_hook] End deleting product {$post_id} [square:{$product_square_id}] from Square");


    }
}

/*
 * Adding new Category
 */

function woo_square_add_category($category_id) {
    
    //Avoid auto save from calling Square APIs.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    $category = get_term_by('id', $category_id, 'product_cat');
    update_option("is_square_sync_{$category_id}", 0);
    Helpers::debug_log('info',"[add_category_hook] Start adding category to Square: {$category_id}");
   
    if(!get_option('woo_square_access_token')){
        return;
    }
    

    $square = new Square(get_option('woo_square_access_token'),get_option('woo_square_location_id'));
    
    $squareSynchronizer = new WooToSquareSynchronizer($square);
    $result = $squareSynchronizer->addCategory($category);
    
    if( $result===TRUE ){
        update_option("is_square_sync_{$category_id}", 1);
    }
    Helpers::debug_log('info',"[add_category_hook] End adding category {$category_id} to Square");
}

/*
 * Edit Category
 */

function woo_square_edit_category($category_id) {
    
    //Avoid auto save from calling Square APIs.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
            
    update_option("is_square_sync_{$category_id}", 0);
   
    if(!get_option('woo_square_access_token')){
        return;
    }
    $category = get_term_by('id', $category_id, 'product_cat');
    $categorySquareId = get_option('category_square_id_' . $category->term_id);
    Helpers::debug_log('info',"[edit_category_hook] Start updating category on Square: {$category_id} [square:{$categorySquareId}]");


    $square = new Square(get_option('woo_square_access_token'),get_option('woo_square_location_id'));
    $squareSynchronizer = new WooToSquareSynchronizer($square);
    
    //add category if not already linked to square, else update
    if( empty($categorySquareId )){
        $result = $squareSynchronizer->addCategory($category);
    }else{
        $result = $squareSynchronizer->editCategory($category,$categorySquareId);
    }
    
    
    if( $result===TRUE ){
        update_option("is_square_sync_{$category_id}", 1);
        Helpers::debug_log('info',"[edit_category_hook] category {$category_id} updated successfully");
    }
    Helpers::debug_log('info',"[edit_category_hook] End updating category on square: {$category_id} [square:{$categorySquareId}]");
}

/*
 * Delete Category ( called after the category is deleted )
 */

function woo_square_delete_category($category_id,$term_taxonomy_id, $deleted_category) {
   
    //Avoid auto save from calling Square APIs.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    $category_square_id = get_option('category_square_id_' . $category_id);
    
    //delete category options
    delete_option( "is_square_sync_{$category_id}" );
    delete_option( "category_square_id_{$category_id}" );
    
    //no need to call square
    if(empty($category_square_id)){
        return;
    }

    Helpers::debug_log('info',"[delete_category_hook] Start deleting category {$category_id} [square:{$category_square_id}] from Square");
    global $wpdb;

    $wpdb->insert($wpdb->prefix.WOO_SQUARE_TABLE_DELETED_DATA,
            [
                'square_id'  => $category_square_id,
                'target_id'  => $category_id,
                'target_type'=> Helpers::TARGET_TYPE_CATEGORY,
                'name'       => $deleted_category->name
            ]
    );

    if(!get_option('woo_square_access_token')){
        return;
    }

    $square = new Square(get_option('woo_square_access_token'),get_option('woo_square_location_id'));
    $squareSynchronizer = new WooToSquareSynchronizer($square); 
    $result = $squareSynchronizer->deleteCategory($category_square_id);
    
    //delete product from plugin delete table
    if($result===TRUE){
        $wpdb->delete($wpdb->prefix.WOO_SQUARE_TABLE_DELETED_DATA,
            ['square_id'=> $category_square_id ]
        );
        Helpers::debug_log('info',"[delete_category_hook] Category {$category_id} deleted successfully from Square");

    }
    Helpers::debug_log('info',"[delete_category_hook] End deleting category {$category_id} [square:{$category_square_id}] from Square");
}

/*
 * Create Refund
 */

function woo_square_create_refund($order_id, $refund_id) {
    if(!get_option('woo_square_access_token')){
        return;
    }
    //Avoid auto save from calling Square APIs.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_meta($order_id, 'square_payment_id', true)) {

        $square = new Square(get_option('woo_square_access_token'),get_option('woo_square_location_id'));
        $square->refund($order_id, $refund_id);
    }
}

/*
 * update square inventory on complete order 
 */

function woo_square_complete_order($order_id) {
    if(!get_option('woo_square_access_token')){
        return;
    }
    //Avoid auto save from calling Square APIs.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    $square = new Square(get_option('woo_square_access_token'),get_option('woo_square_location_id'));
    $square->completeOrder($order_id);
}