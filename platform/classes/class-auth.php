<?php
/**
 * Authentication and Session Management for SoftProjects Invoice Helper.
 */

class SoftProjects_Auth {

	const USERNAME = 'SoftProjects';
	const PASSWORD = '7Gq`W~<Bd82A';

	/**
	 * Start secure session.
	 */
	public static function init_session() {
		if ( session_status() === PHP_SESSION_NONE ) {
			if ( ! headers_sent() ) {
				@ini_set( 'session.cookie_httponly', 1 );
				@ini_set( 'session.use_only_cookies', 1 );
			}
			@session_start();
		}
	}

	/**
	 * Check if user is logged in.
	 *
	 * @return bool
	 */
	public static function is_authenticated() {
		self::init_session();
		return ! empty( $_SESSION['softprojects_auth_user'] ) && $_SESSION['softprojects_auth_user'] === self::USERNAME;
	}

	/**
	 * Attempt login with username and password.
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	public static function attempt_login( $username, $password ) {
		self::init_session();
		$username = trim( (string) $username );
		$password = (string) $password;

		if ( hash_equals( self::USERNAME, $username ) && hash_equals( self::PASSWORD, $password ) ) {
			$_SESSION['softprojects_auth_user'] = self::USERNAME;
			$_SESSION['softprojects_login_time'] = time();
			return true;
		}

		return false;
	}

	/**
	 * Destroy session and log out.
	 */
	public static function logout() {
		self::init_session();
		$_SESSION = array();
		if ( ! headers_sent() && ini_get( 'session.use_cookies' ) ) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}
		@session_destroy();
	}
}
