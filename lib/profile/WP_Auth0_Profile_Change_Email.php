<?php
/**
 * Contains class WP_Auth0_Profile_Change_Email.
 *
 * @package WP-Auth0
 *
 * @since 3.9.0
 */

/**
 * Class WP_Auth0_Profile_Change_Email.
 */
class WP_Auth0_Profile_Change_Email {

	/**
	 * WP_Auth0_Api_Change_Email instance.
	 *
	 * @var WP_Auth0_Api_Change_Email
	 */
	protected $api_change_email;

	/**
	 * WP_Auth0_Profile_Change_Email constructor.
	 *
	 * @param WP_Auth0_Api_Change_Email $api_change_email - WP_Auth0_Api_Change_Email instance.
	 */
	public function __construct( WP_Auth0_Api_Change_Email $api_change_email ) {
		$this->api_change_email = $api_change_email;
	}

	/**
	 * Add actions for the update user process.
	 *
	 * @codeCoverageIgnore - Tested in TestProfileChangeEmail::testInitHooks()
	 */
	public function init() {

		// Used during profile update in wp-admin or email verification link.
		add_action( 'profile_update', array( $this, 'update_email' ), 100, 2 );
	}

	/**
	 * Update the user's email at Auth0
	 * Hooked to: profile_update
	 * IMPORTANT: Internal callback use only, do not call this function directly!
	 *
	 * @param integer $wp_user_id - WP user ID.
	 * @param WP_User $old_user_data - WP user before changes.
	 *
	 * @return boolean
	 */
	public function update_email( $wp_user_id, $old_user_data ) {

		// Exit if this is not an Auth0 user.
		$auth0_id = WP_Auth0_UsersRepo::get_meta( $wp_user_id, 'auth0_id' );
		if ( empty( $auth0_id ) ) {
			return false;
		}

		// Exit if this is not a database strategy user.
		if ( 'auth0' !== WP_Auth0_Users::get_strategy( $auth0_id ) ) {
			return false;
		}

		$wp_user = get_user_by( 'id', $wp_user_id );

		$current_email = $wp_user->data->user_email;
		$old_email     = $old_user_data->data->user_email;

		// No email address changes, exit.
		if ( $old_email === $current_email ) {
			return false;
		}

		// Password change was successful, nothing else to do.
		if ( $this->api_change_email->call( $auth0_id, $current_email ) ) {
			return true;
		}

		// Suppress the notification for email change.
		add_filter( 'email_change_email', array( $this, 'suppress_email_change_notification' ), 100 );

		// Remove this method from profile_update, which is called by wp_update_user, to avoid an infinite loop.
		remove_action( 'profile_update', array( $this, __FUNCTION__ ), 100 );

		// Revert the email address to previous.
		$wp_user->data->user_email = $old_email;
		wp_update_user( $wp_user );

		// Revert hooks.
		add_action( 'profile_update', array( $this, __FUNCTION__ ), 100, 2 );
		remove_filter( 'email_change_email', array( $this, 'suppress_email_change_notification' ), 100 );

		// Remove the pending email address change so it can be tried again.
		delete_user_meta( $wp_user_id, '_new_email' );

		// Can't set a custom message here so redirect with an error for WP to pick up.
		if ( in_array( $GLOBALS['pagenow'], array( 'user-edit.php', 'profile.php' ) ) ) {
			$redirect_url = admin_url( $GLOBALS['pagenow'] );
			$redirect_url = add_query_arg( 'user_id', $wp_user_id, $redirect_url );
			$redirect_url = add_query_arg( 'error', 'new-email', $redirect_url );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		return false;
	}

	/**
	 * Modify the user email change notification when the Auth0 API call fails.
	 *
	 * @param array $email - Email notification data.
	 *
	 * @return array
	 *
	 * @see wp_update_user()
	 */
	public function suppress_email_change_notification( array $email ) {
		$email['to']      = null;
		$email['message'] = null;
		$email['subject'] = __( 'Email suppressed - Auth0 email change failed.', 'wp-auth0' );
		return $email;
	}
}
