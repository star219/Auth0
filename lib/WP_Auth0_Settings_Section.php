<?php

class WP_Auth0_Settings_Section {

    protected $a0_options;
    protected $initial_setup;

    public function __construct(WP_Auth0_Options $a0_options, WP_Auth0_InitialSetup $initial_setup) {
      $this->a0_options = $a0_options;
      $this->initial_setup = $initial_setup;
    }

    public function init(){
        add_action( 'admin_menu', array($this, 'init_menu'), 95.55, 0 );
    }

    public function init_menu(){

        $auth0_app_token = $this->a0_options->get('auth0_app_token');
        $client_id = $this->a0_options->get('client_id');

        $show_initial_setup = ! ( $auth0_app_token && $client_id );

        $main_menu = 'wpa0';

        if ( $show_initial_setup ) {
            $main_menu = 'wpa0-setup';
        }

        add_menu_page( __('Auth0', WPA0_LANG), __('Auth0', WPA0_LANG), 'manage_options', $main_menu,
            ( $show_initial_setup ? array($this->initial_setup, 'render_setup_page') : array('WP_Auth0_Admin', 'render_settings_page') ), WP_Auth0::get_plugin_dir_url() . 'assets/img/a0icon.png', 85.55 );

        if ( $show_initial_setup ) {
            add_submenu_page($main_menu, __('Quick setup', WPA0_LANG), __('Quick setup', WPA0_LANG), 'manage_options', 'wpa0-setup', array($this->initial_setup, 'render_setup_page') );
        }

        add_submenu_page($main_menu, __('Settings', WPA0_LANG), __('Settings', WPA0_LANG), 'manage_options', 'wpa0', array('WP_Auth0_Admin', 'render_settings_page') );
        add_submenu_page($main_menu, __('Users export', WPA0_LANG), __('Users export', WPA0_LANG), 'manage_options', 'wpa0-users-export', array('WP_Auth0_Export_Users', 'render_export_users') );
        add_submenu_page($main_menu, __('Dashboard preferences', WPA0_LANG), __('Dashboard', WPA0_LANG), 'manage_options', 'wpa0-dashboard', array('WP_Auth0_Dashboard_Preferences', 'render_dashboard_preferences_page') );
        add_submenu_page($main_menu, __('Error Log', WPA0_LANG), __('Error Log', WPA0_LANG), 'manage_options', 'wpa0-errors', array('WP_Auth0_ErrorLog', 'render_settings_page') );

        if (WP_Auth0::is_jwt_auth_enabled())
        {
            add_submenu_page($main_menu, __('JWT Auth integration', WPA0_LANG), __('JWT Auth integration', WPA0_LANG), 'manage_options', 'wpa0-jwt-auth', array('WP_Auth0_Configure_JWTAUTH', 'render_settings_page') );
        }
    }
}
