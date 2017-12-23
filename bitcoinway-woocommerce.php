<?php
/*


Plugin Name: Bitcoin Cash Payments for WooCommerce
Plugin URI: https://github.com/mboyd1/bitcoin-cash-payments-for-woocommerce
Description: Bitcoin Cash Payments for WooCommerce plugin allows you to accept payments in bitcoin cash for physical and digital products at your WooCommerce-powered online store.
Version: 4.11
Author: mboyd1
Author URI: https://github.com/mboyd1/bitcoin-cash-payments-for-woocommerce
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html

*/


// Include everything
include (dirname(__FILE__) . '/bchwc-include-all.php');

//---------------------------------------------------------------------------
// Add hooks and filters

// create custom plugin settings menu
add_action( 'admin_menu',                   'BCHWC_create_menu' );

register_activation_hook(__FILE__,          'BCHWC_activate');
register_deactivation_hook(__FILE__,        'BCHWC_deactivate');
register_uninstall_hook(__FILE__,           'BCHWC_uninstall');

add_filter ('cron_schedules',               'BCHWC__add_custom_scheduled_intervals');
add_action ('BCHWC_cron_action',             'BCHWC_cron_job_worker');     // Multiple functions can be attached to 'BCHWC_cron_action' action

BCHWC_set_lang_file();
//---------------------------------------------------------------------------

//===========================================================================
// activating the default values
function BCHWC_activate()
{
    global  $g_BCHWC__config_defaults;

    $bchwc_default_options = $g_BCHWC__config_defaults;

    // This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
    $bchwc_settings = BCHWC__get_settings ();

    foreach ($bchwc_settings as $key=>$value)
    	$bchwc_default_options[$key] = $value;

    update_option (BCHWC_SETTINGS_NAME, $bchwc_default_options);

    // Re-get new settings.
    $bchwc_settings = BCHWC__get_settings ();

    // Create necessary database tables if not already exists...
    BCHWC__create_database_tables ($bchwc_settings);
    BCHWC__SubIns ();

    //----------------------------------
    // Setup cron jobs

    if ($bchwc_settings['enable_soft_cron_job'] && !wp_next_scheduled('BCHWC_cron_action'))
    {
    	$cron_job_schedule_name = strpos($_SERVER['HTTP_HOST'], 'ttt.com')===FALSE ? $bchwc_settings['soft_cron_job_schedule_name'] : 'seconds_30';
    	wp_schedule_event(time(), $cron_job_schedule_name, 'BCHWC_cron_action');
    }
    //----------------------------------

}
//---------------------------------------------------------------------------
// Cron Subfunctions
function BCHWC__add_custom_scheduled_intervals ($schedules)
{
	$schedules['seconds_30']     = array('interval'=>30,     'display'=>__('Once every 30 seconds'));     // For testing only.
	$schedules['minutes_1']      = array('interval'=>1*60,   'display'=>__('Once every 1 minute'));
	$schedules['minutes_2.5']    = array('interval'=>2.5*60, 'display'=>__('Once every 2.5 minutes'));
	$schedules['minutes_5']      = array('interval'=>5*60,   'display'=>__('Once every 5 minutes'));

	return $schedules;
}
//---------------------------------------------------------------------------
//===========================================================================

//===========================================================================
// deactivating
function BCHWC_deactivate ()
{
    // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...

   //----------------------------------
   // Clear cron jobs
   wp_clear_scheduled_hook ('BCHWC_cron_action');
   //----------------------------------
}
//===========================================================================

//===========================================================================
// uninstalling
function BCHWC_uninstall ()
{
    $bchwc_settings = BCHWC__get_settings();

    if ($bchwc_settings['delete_db_tables_on_uninstall'])
    {
        // delete all settings.
        delete_option(BCHWC_SETTINGS_NAME);

        // delete all DB tables and data.
        BCHWC__delete_database_tables ();
    }
}
//===========================================================================

//===========================================================================
function BCHWC_create_menu()
{

    // create new top-level menu
    // http://www.fileformat.info/info/unicode/char/e3f/index.htm
    add_menu_page (
        __('Woo Bitcoin Cash', BCHWC_I18N_DOMAIN),                    // Page title
        __('Bitcoin Cash', BCHWC_I18N_DOMAIN),                        // Menu Title - lower corner of admin menu
        'administrator',                                        // Capability
        'bchwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'BCHWC__render_general_settings_page',                   // Function

        plugins_url('/images/bitcoin_16x.png', __FILE__)      // Icon URL
        );

    add_submenu_page (
        'bchwc-settings',                                        // Parent
        __("WooCommerce Bitcoin Cash Payments Gateway", BCHWC_I18N_DOMAIN),                   // Page title
        __("General Settings", BCHWC_I18N_DOMAIN),               // Menu Title
        'administrator',                                        // Capability
        'bchwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'BCHWC__render_general_settings_page'                    // Function
        );

    add_submenu_page (
        'bchwc-settings',                                        // Parent
        __("Bitcoin Cash Plugin Advanced Settings", BCHWC_I18N_DOMAIN),       // Page title
        __("Advanced Settings", BCHWC_I18N_DOMAIN),                // Menu title
        'administrator',                                        // Capability
        'bchwc-settings-advanced',                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'BCHWC__render_advanced_settings_page'            // Function
        );
}
//===========================================================================

//===========================================================================
// load language files
function BCHWC_set_lang_file()
{
    # set the language file
    $currentLocale = get_locale();
    if(!empty($currentLocale))
    {
        $moFile = dirname(__FILE__) . "/lang/" . $currentLocale . ".mo";
        if (@file_exists($moFile) && is_readable($moFile))
        {
            load_textdomain(BCHWC_I18N_DOMAIN, $moFile);
        }

    }
}
//===========================================================================

