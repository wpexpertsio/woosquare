<?php
/**
 * @author Mohammad Mursaleen
 * function to integrate freemius SDK
 */
// Create a helper function for easy SDK access.
function woosquare_fs() {
    global $woosquare_fs;

    if ( ! isset( $woosquare_fs ) ) {
        // Include Freemius SDK.
        require_once dirname(__FILE__) . '/freemius/start.php';

        $woosquare_fs = fs_dynamic_init( array(
            'id'                  => '1378',
            'slug'                => 'woosquare',
            'type'                => 'plugin',
            'public_key'          => 'pk_823382e5b579047e3a8bb6fa6790d',
            'is_premium'          => false,
            'has_addons'          => false,
            'has_paid_plans'      => false,
            'menu'                => array(
                'slug'           => 'square-settings',
                'override_exact' => true,
                'account'        => false,
                'contact'        => false,
            ),
        ) );
    }

    return $woosquare_fs;
}

// Init Freemius.
woosquare_fs();
// Signal that SDK was initiated.
do_action( 'woosquare_fs_loaded' );

function woosquare_fs_settings_url() {
    return admin_url( 'admin.php?page=square-settings' );
}



function woosquare_fs1_custom_connect_message_on_update(
    $message,
    $user_first_name,
    $plugin_title,
    $user_login,
    $site_link,
    $freemius_link
) {
    return sprintf(
        __fs( 'hey-x' ) . '<br>' .
        __( 'Would be great if you can help us improve %2$s! If you are ready! some data about your usage of %2$s will be sent to %5$s. If you skip this, that\'s okay! %2$s will still work just fine.', 'wp-contact-slider' ),
        $user_first_name,
        '<b>' . $plugin_title . '</b>',
        '<b>' . $user_login . '</b>',
        $site_link,
        $freemius_link
    );
}

woosquare_fs()->add_filter('connect_message_on_update', 'woosquare_fs1_custom_connect_message_on_update', 10, 6);


function woosquare_fs2_custom_connect_message_on_update(
    $message,
    $user_first_name,
    $plugin_title,
    $user_login,
    $site_link,
    $freemius_link
) {
    return sprintf(
        __fs( 'hey-x' ) . '<br>' .
        __( 'Would be great if you can help us improve %2$s! If you are ready! some data about your usage of %2$s will be sent to %5$s. If you skip this, that\'s okay! %2$s will still work just fine.', 'wp-contact-slider' ),
        $user_first_name,
        '<b>' . $plugin_title . '</b>',
        '<b>' . $user_login . '</b>',
        $site_link,
        $freemius_link
    );
}

woosquare_fs()->add_filter('connect_message', 'woosquare_fs2_custom_connect_message_on_update', 10, 6);