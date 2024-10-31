<?php

namespace BusinessOnline\WPCF7RL;

/**
 * Created by PhpStorm.
 * User: zazell
 * Date: 04/02/2022
 */
class Admin
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_submenu_page(
            'wpcf7',
            'Rate Limit',
            'Rate Limit Settings',
            'wpcf7_edit_contact_form',
            'wpcf7rl_settings',
            array($this, 'create_admin_page')
        );
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'wpcf7rl_option_group', // Option group
            'wpcf7rl_options' // Option name
        );

        add_settings_field(
            'wpcf7rl_activated', // ID
            __('Activate Rate Limit', 'rate-limiting-for-contact-form-7'), // Title
            array($this, 'wpcf7rl_activated_callback'), // Callback
            'wpcf7rl_settings', // Page
            'wpcf7rl_settings_section' // Section
        );

        add_settings_section(
            'wpcf7rl_settings_section', // Code
            __('Rate limit settings', 'rate-limiting-for-contact-form-7'), // Title
            array($this, 'print_section_info'), // Callback
            'wpcf7rl_settings' // Page
        );

        add_settings_field(
            'wpcf7rl_limit_period', // ID
            __('Limit period (in minutes)', 'rate-limiting-for-contact-form-7'), // Title
            array($this, 'wpcf7rl_limit_period_callback'), // Callback
            'wpcf7rl_settings', // Page
            'wpcf7rl_settings_section' // Section
        );

        add_settings_field(
            'wpcf7rl_limit_count', // ID
            __('Max number of submissions per period', 'rate-limiting-for-contact-form-7'), // Title
            array($this, 'wpcf7rl_limit_count_callback'), // Callback
            'wpcf7rl_settings', // Page
            'wpcf7rl_settings_section' // Section
        );

        if ( wpcf7rl_fs()->can_use_premium_code() ) {
            add_settings_field(
                'wpcf7rl_activated_forms', // ID
                __('Contact Forms to listen to', 'rate-limiting-for-contact-form-7'), // Title
                array($this, 'forms_callback'), // Callback
                'wpcf7rl_settings', // Page
                'wpcf7rl_settings_section' // Section
            );
        }

    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option('wpcf7rl_options');

        ?>
        <div class="wrap">
            <h1>Contact Form 7 - Rate limiting settings</h1>
            <?php
            if ( wpcf7rl_fs()->is_not_paying() ) {
	            echo '<section class="notice notice-warning"><h1>' . esc_html__('Upgrade to Premium', 'rate-limiting-for-contact-form-7') . '</h1>';
	            echo '<p>' . esc_html__('Add support for form filtering and auto cleaning your database by updating to Premium', 'rate-limiting-for-contact-form-7') . '</p>';
	            echo '<p><a href="' . wpcf7rl_fs()->get_upgrade_url() . '" class="button">' .
	                 esc_html__('Upgrade Now!', 'rate-limiting-for-contact-form-7') .
	                 '</a></p>';
	            echo '</section>';
            }
            ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpcf7rl_option_group');
                do_settings_sections('wpcf7rl_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        print '<p>' . __('Configure the rate limits per form below. Once the limit is reached for an ip, the user will not be able to submit anymore forms.', 'rate-limiting-for-contact-form-7') . '</p>';
        print '<p>' . __('If no form is selected in the "Contact Forms to listen to" setting, all forms are limited', 'rate-limiting-for-contact-form-7') . '</p>';
    }

    public function wpcf7rl_limit_period_callback()
    {
        printf(
            '<input type="number"  size="4" id="wpcf7rl_limit_period" name="wpcf7rl_options[wpcf7rl_limit_period]" value="%s" />',
            isset($this->options['wpcf7rl_limit_period']) ? esc_attr($this->options['wpcf7rl_limit_period']) : ''
        );
    }

    public function wpcf7rl_limit_count_callback()
    {
        printf(
            '<input type="number" size="4"  id="wpcf7rl_limit_count" name="wpcf7rl_options[wpcf7rl_limit_count]" value="%s" />',
            isset($this->options['wpcf7rl_limit_count']) ? esc_attr($this->options['wpcf7rl_limit_count']) : ''
        );
    }

    public function wpcf7rl_activated_callback()
    {
        printf(
            '<input type="checkbox"  id="wpcf7rl_activated" name="wpcf7rl_options[wpcf7rl_activated]" %s  />',
            isset($this->options['wpcf7rl_activated']) && (bool)$this->options['wpcf7rl_activated'] === true ? 'checked' : ''
        );
    }

    public function forms_callback()
    {
        $args = array('post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1, 'orderby' => 'post_name', 'order' => 'ASC');
        $cf7Forms = get_posts($args);

        $existing = (isset($this->options['wpcf7rl_forms']) ? $this->options['wpcf7rl_forms'] : []);

        ?>
        <select id="wpcf7tl_forms" name="wpcf7rl_options[wpcf7rl_forms][]" name="forms" multiple>
            <?php foreach ($cf7Forms as $form): ?>
                <option value="<?php echo esc_attr($form->ID) ?>" <?php echo esc_attr(in_array($form->ID, $existing) ? 'selected="selected"' : '') ?> >
                    [<?php echo esc_attr($form->ID) ?>] <?php echo esc_attr($form->post_title) ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

}
