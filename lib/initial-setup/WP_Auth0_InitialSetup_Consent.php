<?php

class WP_Auth0_InitialSetup_Consent {

  // protected $domain = 'auth0.auth0.com';
  protected $domain = 'login0.myauth0.com';

  protected $a0_options;

  public function __construct(WP_Auth0_Options $a0_options) {
      $this->a0_options = $a0_options;
  }

  public function render($step) {
    $consent_url = $this->build_consent_url();

    $need_event_track = isset($_REQUEST['activation']);

    $lock_version = null;
    $lock_url = $this->a0_options->get('cdn_url');
    if (preg_match('/^.*lock-([\.0-9]+)(\.min)?\.js/', $lock_url, $matches)) {
      if (isset($matches[1])) {
        $lock_version = $matches[1];
      }
    }

    $site_title = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    $wordpress_url = get_bloginfo('wpurl');
    $plugin_version = WPA0_VERSION;

    include WPA0_PLUGIN_DIR . 'templates/initial-setup/consent-disclaimer.php';
  }

  public function callback() {
    $sucess = $this->store_token_domain();

    if ( ! $sucess ) {
      wp_redirect( admin_url( 'admin.php?page=wpa0-setup&error=cant_exchange_token' ) );
      exit;
    }

    $name = get_bloginfo('name');
    $this->consent_callback($name);
  }

  protected function parse_token_domain($token) {
    $parts = explode('.', $token);
    $payload = json_decode( JWT::urlsafeB64Decode( $parts[1] ) );
    return trim(str_replace( array('/api/v2', 'https://'), '', $payload->aud ), ' /');
  }



  public function exchange_code() {
    if ( ! isset($_REQUEST['code']) ) {
        return null;
    }

    $code = $_REQUEST['code'];
    $callback_url = urlencode( admin_url( 'admin.php?page=wpa0-setup&step=2' ) );

    $client_id = get_bloginfo('wpurl');

    $response = WP_Auth0_Api_Client::get_token( $this->domain, $client_id, null, 'authorization_code', array(
            'redirect_uri' => home_url(),
            'code' => $code,
        ) );

    $obj = json_decode($response['body']);

    if (isset($obj->error)) {
        return null;
    }

    return $obj->access_token;
  }

  public function store_token_domain() {
    $access_token = $this->exchange_code();

    if ($access_token === null) {
        return false;
    }

    $app_domain = $this->parse_token_domain($access_token);

    // $app_token = $this->generate_app_token($app_domain, $access_token);

    // if ($app_token === null) {
    //     return false;
    // }

    $this->a0_options->set( 'auth0_app_token', $app_token );
    $this->a0_options->set( 'domain', $app_domain );

    return true;
  }

  protected function generate_app_token($app_domain, $access_token) {

    $clients = WP_Auth0_Api_Client::search_clients($app_domain, $access_token);

    $token = null;

    foreach ($clients as $client) {

      if ($client->global) {

        $token = JWT::encode(array(
          'aud' => $client->client_id,
          'scope' => 'create:clients update:clients update:connections create:connections read:connections create:rules delete:rules update:users',
          // array(

          //   'clients' => array(
          //     'actions' => array('update', 'create')
          //   ), 

          //   'connections' => array(
          //     'actions' => array('update', 'create', 'read')
          //   ), 

          //   'rules' => array(
          //     'actions' => array('delete', 'create')
          //   ), 

          //   'users' => array(
          //     'actions' => array('update')
          //   )

          // ), 
          'jti' => uniqid('',true)
        ), JWT::urlsafeB64Decode( $client->client_secret ));


      }

    }

    return $token;

  }

  public function consent_callback($name) {

    $app_token = $this->a0_options->get( 'auth0_app_token' );
    $domain = $this->a0_options->get( 'domain' );

    $response = WP_Auth0_Api_Client::create_client($domain, $app_token, $name);

    if ($response === false) {
        wp_redirect( admin_url( 'admin.php?page=wpa0&error=cant_create_client' ) );
        exit;
    }

    $this->a0_options->set( 'client_id', $response->client_id );
    $this->a0_options->set( 'client_secret', $response->client_secret );

    $connections = WP_Auth0_Api_Client::search_connection($domain, $app_token);

    $enabled_connections = $this->a0_options->get_enabled_connections();

    foreach ($connections as $connection) {
        if ( in_array( $connection->name, $enabled_connections ) ) {

            $this->a0_options->set( "social_{$connection->name}" , 1 );
            $this->a0_options->set( "social_{$connection->name}_key" , isset($connection->options->client_id) ? $connection->options->client_id : null );
            $this->a0_options->set( "social_{$connection->name}_secret" , isset($connection->options->client_secret) ? $connection->options->client_secret : null );

        }

        if ( $connection->strategy === 'auth0' && in_array($input['client_id'], $connection->enabled_clients) && isset($connection->options) ) {

          $this->a0_options->set( "brute_force_protection" , isset($connection->options->brute_force_protection) ? $connection->options->brute_force_protection : false );
          $this->a0_options->set( "password_policy" , isset($connection->options->passwordPolicy) ? $connection->options->passwordPolicy : null );

				}
    }

    wp_redirect( admin_url( 'admin.php?page=wpa0-setup&step=2' ) );
    exit();

  }

  public function build_consent_url() {
    $callback_url = urlencode( admin_url( 'admin.php?page=wpa0-setup&callback=1' ) );

    $client_id = urlencode(get_bloginfo('wpurl'));

    $scope = urlencode( implode( ' ', array(
        'read:clients',
        'read:client_keys',
        // 'read:connections',
        // 'create:connections',
        // 'update:connections',
        // 'create:clients'
    ) ) );

    $url = "https://{$this->domain}/i/oauth2/authorize?client_id={$client_id}&response_type=code&redirect_uri={$callback_url}&scope={$scope}&expiration=9999999999";

    return $url;
  }

}
