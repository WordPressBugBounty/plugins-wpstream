<?php
/**
 * Social login class
 *
 * @package wpstream-theme
 */

/**
 * Description of tweet_login
 *
 * @author cretu remus
 */
use Abraham\TwitterOAuth\TwitterOAuth;

/**
 * Social login
 */
class wpstream_theme_Social_Login {
	// put your code here.

	/**
	 * Twitter costumer key
	 *
	 * @var string
	 */
	private $twitter_consumer_key;

	/**
	 * Twitter costumer secret
	 *
	 * @var string
	 */
	private $twitter_consumer_secret;

	/**
	 * Twitter access token
	 *
	 * @var string
	 */
	private $twitter_access_token;

	/**
	 * Twitter access secret
	 *
	 * @var string
	 */
	private $twitter_access_secret;

	/**
	 * Redirect
	 *
	 * @var string
	 */
	private $redirect;

	/**
	 * Facebook status
	 *
	 * @var string
	 */
	private $facebook_status;

	/**
	 * Google status
	 *
	 * @var string
	 */
	private $google_status;

	/**
	 * Twitter status
	 *
	 * @var string
	 */
	private $twiter_status;

	/**
	 * Facebook api
	 *
	 * @var string
	 */
	private $facebook_api;

	/**
	 * Facebook secret
	 *
	 * @var string
	 */
	private $facebook_secret;

	/**
	 * Google client id
	 *
	 * @var string
	 */
	private $google_client_id;

	/**
	 * Google client secret
	 *
	 * @var string
	 */
	private $google_client_secret;

	/**
	 * Google developers key
	 *
	 * @var string
	 */
	private $google_developer_key;

	/**
	 * Twitter url
	 *
	 * @var string
	 */
	private $twitter_url;


	/**
	 * Construct
	 */
	public function __construct() {
		if ( session_status() === PHP_SESSION_NONE ) {
			session_start();
		}

		$this->twitter_url             = '';
		$this->twitter_consumer_key    = '';
		$this->twitter_consumer_secret = '';
		$this->twitter_access_token    = '';
		$this->twitter_access_secret   = '';
		if ( function_exists( 'wpstream_theme_get_template_link' ) ) {
			$this->redirect = trim( wpstream_theme_get_template_link( 'user_dashboard_profile.php' ) );
		}
		$this->facebook_status      = '';
		$this->google_status        = '';
		$this->twiter_status        = '';
		$this->facebook_api         = '';
		$this->facebook_secret      ='';
		$this->google_client_id     = '';
		$this->google_client_secret = '';
		$this->google_developer_key = '';

		add_action( 'wp_ajax_wpstream_theme_social_login_generate_link', array( $this, 'wpstream_theme_social_login_generate_link' ) );
		add_action( 'wp_ajax_nopriv_wpstream_theme_social_login_generate_link', array( $this, 'wpstream_theme_social_login_generate_link' ) );
	}

	/**
	 * Social login generate link
	 */
	public function wpstream_theme_social_login_generate_link() {

		check_ajax_referer( 'wpstream_theme_social_login_nonce', 'nonce' );

		if ( isset( $_POST['social_type'] ) ) {
			$social_type = esc_html( sanitize_text_field( wp_unslash( $_POST['social_type'] ) ) );
		}

		if ( 'facebook' === $social_type ) {
			print esc_url( $this->return_facebook_url() );
		} elseif ( 'google' === $social_type ) {
			print esc_url( $this->return_google_url() );
		} elseif ( 'twitter' === $social_type ) {
			print esc_url( $this->return_twiter_url() );
		}
		die();
	}

	/**
	 * Display the social login form based on the specified location.
	 *
	 * @param string $where The location where the form should be displayed.
	 * @return string|void If $return is set to 1, returns the form as a string; otherwise, prints the form.
	 */
	public function display_form( $where ) {
		$to_return = '';
		$appendix  = '';

		if ( 'mobile' === $where ) {
			$appendix = '_mobile';
		} elseif ( 'widget' === $where ) {
			$appendix = '_wd';
		} elseif ( 'short' === $where ) {
			$appendix = '_sh';
		} elseif ( 'short_reg' === $where ) {
			$appendix = '_sh_reg';
		} elseif ( 'register' === $where ) {
			$appendix = '_reg';
		} elseif ( 'topbar' === $where ) {
			$appendix = '_topbar';
		}

		if ( 'yes' === $this->facebook_status ) {
			$to_return .= '<div class="wpstream_theme_social_login" id="facebookloginsidebar' . $appendix . '" data-social="facebook"> ' . esc_html__( 'Login with Facebook', 'hello-wpstream' ) . '</div>';
		}

		if ( 'yes' === $this->google_status ) {
			$to_return .= '<div class="wpstream_theme_social_login"  id="googleloginsidebar' . $appendix . '" data-social="google">' . esc_html__( 'Login with Google', 'hello-wpstream' ) . '</div>';
		}

		if ( 'yes' === $this->twiter_status ) {
			$to_return .= '<div class="wpstream_theme_social_login"  id="twitterloginsidebar' . $appendix . '" data-social="twitter">' . esc_html__( 'Login with Twitter', 'hello-wpstream' ) . '</div>';
		}

		$nonce      = wp_create_nonce( 'wpstream_theme_social_login_nonce' );
		$to_return .= '<input type="hidden" class="wpstream_theme_social_login_nonce" value="' . $nonce . '">';

		$return = '';

		if ( 1 === $return ) {
			return $to_return;
		} else {
			print esc_html( $to_return );
		}
	}

	/**
	 * Twitter url
	 *
	 * @return string
	 */
	public function return_twiter_url() {
		$url = '';
		try {
			$connection = new TwitterOAuth( $this->twitter_consumer_key, $this->twitter_consumer_secret, $this->twitter_access_token, $this->twitter_access_secret );

			$request_token      = $connection->oauth( 'oauth/request_token', array( 'oauth_callback' => $this->redirect ) );
			$oauth_token        = $request_token['oauth_token'];
			$oauth_token_secret = $request_token['oauth_token_secret'];

			$_SESSION['token_tw']         = $oauth_token;
			$_SESSION['token_secret_tw']  = $oauth_token_secret;
			$_SESSION['wpstream_theme_is_twet'] = 'ison';
			$url                          = $connection->url( 'oauth/authorize', array( 'oauth_token' => $request_token['oauth_token'] ) );
			$this->twitter_url            = $url;
		} catch ( Exception $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// The exception is not handled because no specific action is required.
			// Typically, you should document the reason why the exception is not handled here.
		}

			return $url;
	}

	/**
	 * Twitter authentificate user
	 */
	public function twiter_authentificate_user() {

		if ( empty($_SESSION['token_tw']) || empty($_SESSION['token_secret_tw']) ) {
			return;
		}

		$tw_client = new TwitterOAuth( $this->twitter_consumer_key, $this->twitter_consumer_secret, esc_html( sanitize_text_field( $_SESSION['token_tw'] ) ), esc_html( sanitize_text_field( $_SESSION['token_secret_tw'] ) ) );

		if ( isset( $_REQUEST['oauth_verifier'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$params = array(
				'oauth_verifier' => esc_html( sanitize_text_field( wp_unslash( $_REQUEST['oauth_verifier'] ) ) ), //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
		}

		$access_token = $tw_client->oauth( 'oauth/access_token', $params );

		$params = array(
			'include_email'    => 'true',
			'include_entities' => 'false',
			'skip_status'      => 'true',
		);

		$twitter   = new TwitterOAuth( $this->twitter_consumer_key, $this->twitter_consumer_secret, $access_token['oauth_token'], $access_token['oauth_token_secret'] );
		$user_info = $twitter->get( 'account/verify_credentials', $params );

		unset( $_SESSION['token_tw'] );
		unset( $_SESSION['token_secret_tw'] );
		unset( $_SESSION['wpstream_theme_is_twet'] );
		unset( $_SESSION['wpstream_theme_is_fb'] );
		unset( $_SESSION['wpstream_theme_is_google'] );

		$email                = $user_info->email;
		$full_name            = $user_info->screen_name;
		$openid_identity_code = $user_info->id;

		$name     = explode( ' ', $full_name );
		$firsname = isset( $name[0] ) ? $name[0] : '';
		$lastname = isset( $name[1] ) ? $name[1] : '';

		$this->create_or_login_user( $email, $full_name, $openid_identity_code, $firsname, $lastname, 'twitter' );
	}

	/**
	 * Facebook url
	 */
	public function return_facebook_url() {

		$fb = new Facebook\Facebook(
			array(
				'app_id'                => $this->facebook_api,
				'app_secret'            => $this->facebook_secret,
				'default_graph_version' => 'v2.12',
			)
		);

		if ( isset( $_POST['propid'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$prop_id = intval( $_POST['propid'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
		} else {
			$prop_id = 0;
		}

		$helper      = $fb->getRedirectLoginHelper();
		$permissions = array( 'email' ); // optional.

		$login_url                  = $helper->getLoginUrl( $this->redirect, $permissions );
		$_SESSION['wpstream_theme_is_fb'] = 'ison';
		return $login_url;
	}

	/**
	 * Facebook authentificate user
	 */
	public function facebook_authentificate_user() {

		$fb = new Facebook\Facebook(
			array(
				'app_id'                => $this->facebook_api,
				'app_secret'            => $this->facebook_secret,
				'default_graph_version' => 'v2.12',
			)
		);

		$helper = $fb->getRedirectLoginHelper();

		$secret = $this->facebook_secret;
		try {
			$access_token = $helper->getAccessToken();
		} catch ( Facebook\Exceptions\FacebookResponseException $e ) {
			// When Graph returns an error.
			echo 'Graph returned an error: ' . esc_html( $e->getMessage() );
			exit;
		} catch ( Facebook\Exceptions\FacebookSDKException $e ) {
			// When validation fails or other local issues.
			echo 'Facebook SDK returned an error: ' . esc_html( $e->getMessage() );
			exit;
		}

		// Logged in
		// The OAuth 2.0 client handler helps us manage access tokens.
		$o_auth_2_client = $fb->getOAuth2Client();

		// Get the access token metadata from /debug_token.
		$token_meta_data = $o_auth_2_client->debugToken( $access_token );

		// Validation (these will throw FacebookSDKException's when they fail).
		$token_meta_data->validateAppId( $this->facebook_api );

		// If you know the user ID this access token belongs to, you can validate it here.
		$token_meta_data->validateExpiration();

		if ( ! $access_token->isLongLived() ) {
			// Exchanges a short-lived access token for a long-lived one.
			try {
				$access_token = $o_auth_2_client->getLongLivedAccessToken( $access_token );
			} catch ( Facebook\Exceptions\FacebookSDKException $e ) {
				echo '<p>Error getting long-lived access token: ' . esc_html( $helper->getMessage() ) . "</p>\n\n";
				exit;
			}
		}

		$_SESSION['fb_access_token'] = (string) $access_token;

		try {
			// Returns a `Facebook\FacebookResponse` object.
			$response = $fb->get( '/me?fields=id,email,name,first_name,last_name', $access_token );
		} catch ( Facebook\Exceptions\FacebookResponseException $e ) {
			echo 'Graph returned an error: ' . esc_html( $e->getMessage() );
			exit;
		} catch ( Facebook\Exceptions\FacebookSDKException $e ) {
			echo 'Facebook SDK returned an error: ' . esc_html( $e->getMessage() );
			exit;
		}

		$user = $response->getGraphUser();

		if ( isset( $user['name'] ) ) {
			$full_name = $user['name'];
		}
		if ( isset( $user['email'] ) ) {
			$email = $user['email'];
		}
		$identity_code = $secret . $user['id'];

		unset( $_SESSION['wpstream_theme_is_twet'] );
		unset( $_SESSION['wpstream_theme_is_fb'] );
		unset( $_SESSION['wpstream_theme_is_google'] );

		$this->create_or_login_user( $email, $full_name, $identity_code, $user['first_name'], $user['last_name'], 'facebook' );
	}

	/**
	 * Google url
	 */
	public function return_google_url() {
		$include_path = get_template_directory() . '/libs/resources';
		set_include_path( $include_path . PATH_SEPARATOR . get_include_path() ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_include_path

		$g_client = new Google_Client();

		$g_client->setApplicationName( 'Login to WpResidence' );
		$g_client->setClientId( $this->google_client_id );
		$g_client->setClientSecret( $this->google_client_secret );
		$g_client->setRedirectUri( $this->redirect );
		$g_client->setDeveloperKey( $this->google_developer_key );
		$g_client->setScopes( array( 'email', 'profile' ) );

		$google_oauth_v_2               = new Google_Oauth2Service( $g_client );
		$auth_url                       = $g_client->createAuthUrl();
		$_SESSION['wpstream_theme_is_google'] = 'ison';
		return $auth_url;
	}

	/**
	 * Google authentificate user
	 */
	public function google_authentificate_user() {
		$allowed_html = array();

		$g_client = new Google_Client();
		$g_client->setApplicationName( 'Login to WpResidence' );
		$g_client->setClientId( $this->google_client_id );
		$g_client->setClientSecret( $this->google_client_secret );
		$g_client->setRedirectUri( $this->redirect );
		$g_client->setDeveloperKey( $this->google_developer_key );
		$g_client->setScopes( array( 'email', 'profile' ) );

		$google_oauth_v_2 = new Google_Oauth2Service( $g_client );

		if ( isset( $_REQUEST['code'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code = sanitize_text_field( wp_kses( wp_unslash( $_REQUEST['code'] ), $allowed_html ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$g_client->authenticate( $code );
		}

		if ( $g_client->getAccessToken() ) {

			$allowed_html = array();

			$user      = $google_oauth_v_2->userinfo->get();
			$user_id   = $user['id'];
			$full_name = wp_kses( $user['name'], $allowed_html );
			$email     = wp_kses( $user['email'], $allowed_html );
			$full_name = str_replace( ' ', '.', $full_name );

			$first_name = '';
			$last_name  = '';
			if ( isset( $user['family_name'] ) ) {
				$last_name = $user['family_name'];
			}
			if ( isset( $user['given_name'] ) ) {
				$first_name = $user['given_name'];
			}
			if ( isset( $user['picture'] ) ) {
				$picture = $user['picture'];
			}

			unset( $_SESSION['wpstream_theme_is_twet'] );
			unset( $_SESSION['wpstream_theme_is_fb'] );
			unset( $_SESSION['wpstream_theme_is_google'] );

			$this->create_or_login_user( $email, $full_name, $user_id, $first_name, $last_name, 'google' );

		}
	}

	/**
	 * Create or login user based on social platform credentials.
	 *
	 * @param string $email              User's email.
	 * @param string $full_name          User's full name.
	 * @param string $openid_identity_code OpenID identity code.
	 * @param string $firsname           User's first name.
	 * @param string $lastname           User's last name.
	 * @param string $social_type        Type of social platform.
	 */
	public function create_or_login_user( $email, $full_name, $openid_identity_code, $firsname = '', $lastname = '', $social_type = '' ) {
		$social_username_array = explode( '@', $email );
		$social_username       = $social_username_array[0];
		$social_username       = $social_username . '_' . $social_type;

		if ( email_exists( $email ) ) {
			// do nothing - you will be logged in with email account - email being check on social platform.
			return;
		} else {
			if ( username_exists( $social_username ) ) {
				$social_username = $social_username . '-' . time();
			}
			$user_id = wp_create_user( $social_username, $openid_identity_code, $email );
		
			$this->wpstream_theme_register_as_user( $social_username, $user_id, $firsname, $lastname );
		}

		$user = get_user_by( 'email', $email );

		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( esc_url( home_url( '/' ) ) );
			exit();
		} else {
			wp_clear_auth_cookie();
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID );

			$this->wpstream_theme_update_old_users( $user->ID );

			wp_safe_redirect( $this->redirect );
			exit();
		}
	}

	/**
	 * Create or login user based on social platform credentials.
	 *
	 * @param string $email              User's email.
	 * @param string $full_name          User's full name.
	 * @param string $openid_identity_code OpenID identity code.
	 * @param string $firsname           User's first name.
	 * @param string $lastname           User's last name.
	 * @param string $social_type        Type of social platform.
	 */
	public function create_or_login_user_old( $email, $full_name, $openid_identity_code, $firsname = '', $lastname = '', $social_type = '' ) {
		$social_username_array = explode( '@', $email );
		$social_username       = $social_username_array[0];
		$social_username       = $social_username . '_' . $social_type;

		if ( email_exists( $email ) ) {
			if ( username_exists( $social_username ) ) {
				// nothing.
				return;
			} else {
				$user_id = wp_create_user( $social_username, $openid_identity_code, ' ' );

			
				$this->wpstream_theme_register_as_user( $full_name, $user_id, 0, $firsname, $lastname );
			}
		} elseif ( username_exists( $social_username ) ) {
			// nothing.
			return;
		} else {
			$user_id = wp_create_user( $social_username, $openid_identity_code, $email );
		
			$this->wpstream_theme_register_as_user( $full_name, $user_id, 0, $firsname, $lastname );
		}

		$wordpress_user_id = username_exists( $social_username );
		wp_set_password( $openid_identity_code, $wordpress_user_id );

		$info                  = array();
		$info['user_login']    = $social_username;
		$info['user_password'] = $openid_identity_code;
		$info['remember']      = true;
		$user_signon           = wp_signon( $info, true );

		if ( is_wp_error( $user_signon ) ) {
			wp_safe_redirect( esc_url( home_url( '/' ) ) );
			exit();
		} else {
		

			wp_safe_redirect( $this->redirect );
			exit();
		}
	}

	/**
	 * Register user as a specific type (estate agent, agency, developer).
	 *
	 * @param string $user_name      User's name.
	 * @param int    $user_id        User ID.
	 * @param int    $new_user_type  Type of new user.
	 * @param string $first_name     User's first name.
	 * @param string $last_name      User's last name.
	 */
	public function wpstream_theme_register_as_user( $user_name, $user_id, $new_user_type, $first_name = '', $last_name = '' ) {
		$post_type = '';
		$app_type  = '';
		$new_user_type = intval($new_user_type);
		if ( 2 === $new_user_type ) {
			$post_type = 'estate_agent';
			$app_type  = esc_html__( 'Agent', 'hello-wpstream' );
		} elseif ( 3 === $new_user_type ) {
			$post_type = 'estate_agency';
			$app_type  = esc_html__( 'Agency', 'hello-wpstream' );
		} elseif ( 4 === $new_user_type ) {
			$post_type = 'estate_developer';
			$app_type  = esc_html__( 'Developer', 'hello-wpstream' );
		}
		$admin_submission_user_role = '';

		$post_approve = 'publish';
		if ( in_array( $app_type, $admin_submission_user_role, true ) ) {
			$post_approve = 'pending';
		}

		if ( !empty($post_type) ) {
			$post    = array(
				'post_title'  => $user_name,
				'post_status' => $post_approve,
				'post_type'   => $post_type,
			);
			$post_id = wp_insert_post( $post );
			update_post_meta( $post_id, 'user_meda_id', $user_id );
			update_user_meta( $user_id, 'user_agent_id', $post_id );
		}

		$user_email = get_the_author_meta( 'user_email', $user_id );

		if ( !empty($post_type) ) {
			update_post_meta( $post_id, 'agent_email', $user_email );
		}

		if ( !empty($first_name) ) {
			update_user_meta( $user_id, 'first_name', $first_name );
		}
		if ( !empty($last_name) ) {
			update_user_meta( $user_id, 'last_name', $last_name );
		}
	}


}
