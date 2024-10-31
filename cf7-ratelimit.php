<?php

/**
 * Plugin Name: Rate limiting for Contact Form 7
 * Plugin URI: https://zazell.net/
 * Description: Adds support to limit the number of Contact Form 7 form submissions per ip per period
 * Author: Zazell Kroeske
 * Version: 1.0.5
 * Author URI: https://zazell.net/
 * Text Domain: rate-limiting-for-contact-form-7
 */
// Create a helper function for easy SDK access.
function wpcf7rl_fs()
{
    global  $cr_fs ;
    
    if ( !isset( $cr_fs ) ) {
        // Include Freemius SDK.
        require_once dirname( __FILE__ ) . '/freemius/start.php';
        $cr_fs = fs_dynamic_init( array(
            'id'             => '9850',
            'slug'           => 'cf7-ratelimit',
            'type'           => 'plugin',
            'public_key'     => 'pk_d98e0f101a08a4e003a9d55e9cd3c',
            'is_premium'     => false,
            'premium_suffix' => 'Full',
            'has_addons'     => false,
            'has_paid_plans' => true,
            'trial'          => array(
            'days'               => 7,
            'is_require_payment' => false,
        ),
            'menu'           => array(
            'slug'    => 'wpcf7rl_settings',
            'support' => false,
            'parent'  => array(
            'slug' => 'wpcf7',
        ),
        ),
            'is_live'        => true,
        ) );
    }
    
    return $cr_fs;
}

// Init Freemius.
wpcf7rl_fs();
// Signal that SDK was initiated.
do_action( 'cr_fs_loaded' );
use  BusinessOnline\WPCF7RL\Admin ;
define( 'WPCF7RL_PLUGIN', __FILE__ );
define( 'WPCF7RL_PLUGIN_DIR', untrailingslashit( dirname( WPCF7RL_PLUGIN ) ) );
global  $wpcf7rl_db_version ;
$wpcf7rl_db_version = '1.0';
/**
 * init the plugin
 */
function wpcf7rl_contact_form_ratelimit_init()
{
    add_filter(
        'wpcf7_validate',
        'wpcf7rl_check_limit_filter',
        10,
        2
    );
    add_action(
        'wpcf7_before_send_mail',
        'wpcf7rl_register_send',
        999,
        2
    );
}

add_action( 'init', 'wpcf7rl_contact_form_ratelimit_init', 9 );
function wpcf7rl_register_send( WPCF7_ContactForm $contact_form )
{
    global  $wpdb ;
    $table_name = $wpdb->prefix . 'wpcf7rl';
    $query = $wpdb->prepare( 'INSERT INTO ' . $table_name . ' (form_id, ip) VALUES (%d, INET_ATON(%s))', $contact_form->id(), $_SERVER['REMOTE_ADDR'] );
    $wpdb->query( $query );
}

function wpcf7rl_check_limit_filter( $result, $tags )
{
    $submission = WPCF7_Submission::get_instance();
    if ( $submission ) {
        return wpcf7rl_check_limit( $submission->get_contact_form(), $result, $tags );
    }
    return $result;
}

function wpcf7rl_check_limit( WPCF7_ContactForm $contact_form, WPCF7_Validation $result, $tags )
{
    $options = get_option( 'wpcf7rl_options' );
    if ( !is_array( $options ) ) {
        return $result;
    }
    if ( !array_key_exists( 'wpcf7rl_limit_period', $options ) || !array_key_exists( 'wpcf7rl_limit_count', $options ) ) {
        return $result;
    }
    if ( !array_key_exists( 'wpcf7rl_activated', $options ) || (bool) $options['wpcf7rl_activated'] !== true ) {
        return $result;
    }
    $wpcf7s_post_id = $contact_form->id();
    if ( wpcf7rl_fs()->can_use_premium_code() ) {
        
        if ( array_key_exists( 'wpcf7rl_forms', $options ) ) {
            $forms = $options['wpcf7rl_forms'];
            if ( !in_array( $wpcf7s_post_id, $forms ) ) {
                return $result;
            }
        }
    
    }
    global  $wpdb ;
    $table_name = $wpdb->prefix . 'wpcf7rl';
    $query = $wpdb->prepare(
        'SELECT count(1) FROM ' . $table_name . ' 
        WHERE form_id = %d AND `ip` = INET_ATON(%s) AND `date` > DATE_SUB(NOW(), INTERVAL %d MINUTE)',
        $wpcf7s_post_id,
        $_SERVER['REMOTE_ADDR'],
        $options['wpcf7rl_limit_period']
    );
    
    if ( $wpdb->get_var( $query ) >= $options['wpcf7rl_limit_count'] ) {
        $tag = array_shift( $tags );
        $result->invalidate( $tag, __( 'You have send to many messages already, please try again later', 'rate-limiting-for-contact-form-7' ) );
    }
    
    return $result;
}

function wpcf7rl_init()
{
    $plugin_rel_path = basename( __DIR__ ) . '/languages';
    load_plugin_textdomain( 'rate-limiting-for-contact-form-7', false, $plugin_rel_path );
}

add_action( 'plugins_loaded', 'wpcf7rl_init' );
register_activation_hook( __FILE__, 'wpcf7rl_create_db' );

if ( wpcf7rl_fs()->can_use_premium_code() ) {
    function wpcf7rl_register_schedule()
    {
        if ( !wp_next_scheduled( 'wpcf7rl_daily' ) ) {
            wp_schedule_event( strtotime( '06:00:00' ), 'daily', 'wpcf7rl_daily' );
        }
    }
    
    register_activation_hook( __FILE__, 'wpcf7rl_register_schedule' );
    function wpcf7rl_cleanup()
    {
        global  $wpdb ;
        $table_name = $wpdb->prefix . 'wpcf7rl';
        $query = $wpdb->prepare( 'DELETE FROM ' . $table_name . ' WHERE date < DATE_SUB(NOW(), INTERVAL 2 DAY)' );
        $wpdb->query( $query );
    }
    
    add_action( 'wpcf7rl_daily', 'wpcf7rl_cleanup' );
    function wpcf7rl_remove_schedule()
    {
        wp_clear_scheduled_hook( 'wpcf7rl_daily' );
    }
    
    register_deactivation_hook( __FILE__, 'wpcf7rl_remove_schedule' );
}

function wpcf7rl_create_db()
{
    if ( !current_user_can( 'activate_plugins' ) ) {
        return;
    }
    // Create DB Here
    wpcf7rl_install();
    add_option( 'wpcf7rl_activated', time() );
}

function wpcf7rl_install()
{
    global  $wpdb ;
    global  $wpcf7rl_db_version ;
    $table_name = $wpdb->prefix . 'wpcf7rl';
    $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );
    if ( $wpdb->get_var( $query ) === $table_name ) {
        return;
    }
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table_name} (\r\n\t\tid MEDIUMINT(9) NOT NULL AUTO_INCREMENT,\r\n\t\tform_id INT(9) NOT NULL,\r\n\t\tdate TIMESTAMP NOT NULL DEFAULT current_timestamp(),\r\n\t\tip BIGINT NOT NULL,\r\n\t\tPRIMARY KEY id (id),\r\n\t\tINDEX `form_id_ip` (`form_id`, `ip`) USING BTREE\r\n\t) {$charset_collate};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    add_option( 'wpcf7rl_db_version', $wpcf7rl_db_version );
}

register_uninstall_hook( __FILE__, 'wpcf7rl_uninstall' );
register_deactivation_hook( __FILE__, 'wpcf7rl_uninstall' );
function wpcf7rl_uninstall()
{
    global  $wpdb ;
    if ( !current_user_can( 'activate_plugins' ) ) {
        return;
    }
    // Deactivation rules here
    delete_option( 'wpcf7rl_activated' );
    delete_option( 'wpcf7rl_options' );
    $table_name = $wpdb->prefix . 'wpcf7rl';
    $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );
    if ( $wpdb->get_var( $query ) === $table_name ) {
        return;
    }
    $sql = "DROP TABLE {$table_name}";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

add_action( 'admin_init', 'wpcf7rl_admin_init' );
function wpcf7rl_admin_init()
{
    global  $wpcf7rl_admin ;
    require_once WPCF7RL_PLUGIN_DIR . '/inc/classes/Admin.php';
    //    ini_set('display_errors', true);
    $wpcf7rl_admin = new Admin();
}


if ( is_admin() ) {
    require_once WPCF7RL_PLUGIN_DIR . '/inc/classes/Admin.php';
    $wpcf7rl_admin = new Admin();
}
