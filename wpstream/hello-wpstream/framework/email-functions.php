<?php
/**
 * Email functions
 *
 * @package wpstream-theme
 */

if ( ! function_exists( 'wpstream_theme_return_sending_email' ) ) {
	/**
	 * Return sending email
	 */
	function wpstream_theme_return_sending_email() {
		$from_email = 'noreply@changeme.net';
		$name_email = 'changeME';

		return $name_email . '  <' . $from_email . '>';
	}
}


if ( ! function_exists( 'wpstream_theme_send_emails' ) ) {
	/**
	 * Send emails.
	 *
	 * @param string       $user_email     The email address of the recipient.
	 * @param string       $subject        The subject of the email.
	 * @param string       $message        The message content of the email.
	 * @param string       $email_type     The type of the email (text or HTML).
	 * @param string       $reply_to       The reply-to email address.
	 * @param string|array $extra_headers  Extra headers to include in the email.
	 */
	function wpstream_theme_send_emails( $user_email, $subject, $message, $email_type, $reply_to = '', $extra_headers = '' ) {
		if ( '' === $reply_to ) {
			$reply_to = wpstream_theme_return_sending_email();
		}

		$headers = 'From: ' . wpstream_theme_return_sending_email() . "\r\n" .
			'Reply-To:' . $reply_to . "\r\n" .
			'Content-Type: text/html; charset="UTF-8"' . "\r\n" .
			'Content-Transfer-Encoding: 8bit' . "\r\n" .
			'MIME-Version: 1.0' . "\r\n" .
			'X-Mailer: PHP/' . phpversion();

		if ( 'text' === $email_type ) {
			$headers = 'From: ' . wpstream_theme_return_sending_email() . "\r\n" .
				'Reply-To:' . $reply_to . "\r\n" .
				'Content-Type: text/plain ; charset="UTF-8"' . "\r\n" .
				'Content-Transfer-Encoding: 8bit' . "\r\n" .
				'MIME-Version: 1.0' . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
		}

		$headers = $headers . $extra_headers;

		$sent = wp_mail(
			$user_email,
			stripslashes( $subject ),
			stripslashes( $message ),
			$headers
		);

		if ( ! $sent ) {
			error_log( 'Failed to send email to ' . $user_email ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

/*
*
* Ajax adv search contact function
*
*/


add_action( 'wp_ajax_nopriv_wpstream_ajax_contact_function', 'wpstream_ajax_contact_function' );
add_action( 'wp_ajax_wpstream_ajax_contact_function', 'wpstream_ajax_contact_function' );

if( !function_exists('wpstream_ajax_contact_function') ):

    function wpstream_ajax_contact_function(){


        // check for POST vars
        $message        =   '';
        $hasError       =   false;
        $allowed_html   =   array();
        $to_print       =   '';
        if ( !wp_verify_nonce( $_POST['nonce'], 'ajax-property-contact')) {
            exit("No naughty business please");
        }
        
        $is_elementor_contact_builder =  intval($_POST['is_elementor']);

        if($is_elementor_contact_builder==0){
            if ( isset($_POST['name']) ) {
                if( trim($_POST['name']) =='' || trim($_POST['name']) ==esc_html__( 'Your Name','hello-wpstream') ){
                    echo json_encode(array('sent'=>false, 'response'=>esc_html__( 'The name field is empty !','hello-wpstream') ));
                    exit();
                }else {
                    $name = wp_kses( trim($_POST['name']),$allowed_html );
                }
            }


            //Check email
            if ( isset($_POST['email']) || trim($_POST['email']) ==esc_html__( 'Your Email','hello-wpstream') ) {
                  if( trim($_POST['email']) ==''){
                        echo json_encode(array('sent'=>false, 'response'=>esc_html__( 'The email field is empty','hello-wpstream' ) ) );
                        exit();
                  } else if( filter_var($_POST['email'],FILTER_VALIDATE_EMAIL) === false) {
                        echo json_encode(array('sent'=>false, 'response'=>esc_html__( 'The email doesn\'t look right !','hello-wpstream') ) );
                        exit();
                  } else {
                        $email = wp_kses( trim($_POST['email']),$allowed_html );
                  }
            }

            //Check comments
            if ( isset($_POST['comment']) ) {
                  if( trim($_POST['comment']) =='' || trim($_POST['comment']) ==esc_html__( 'Your Message','hello-wpstream')){
                    echo json_encode(array('sent'=>false, 'response'=>esc_html__( 'Your message is empty !','hello-wpstream') ) );
                    exit();
                  }else {
                    $comment = wp_kses($_POST['comment'] ,$allowed_html );
                  }
            }


          $message    .=  esc_html__('Client Name','hello-wpstream').": " . $name . PHP_EOL;
          $message    .=  esc_html__('Email','hello-wpstream').": " . $email . PHP_EOL;
          if(isset($_POST['website'])){
              $website = wp_kses( trim($_POST['website']),$allowed_html );
              $message    .=  esc_html__('Website','hello-wpstream').": " . $website . PHP_EOL;
          }

        }else{
            if ( isset($_POST['comment']) ) {
                $comment = wp_kses($_POST['comment'] ,$allowed_html );
                $comment = str_replace('/n',PHP_EOL,$comment);
            
            }
        }


        $subject =esc_html__( 'Contact form from ','hello-wpstream') . esc_url( home_url('/') ) ;
        $receiver_email = esc_html(get_option('admin_email') );
        $message    .=  esc_html__('Message','hello-wpstream').": ".PHP_EOL." " . $comment. PHP_EOL;
        $message    .=  esc_html__('Message sent from contact page','hello-wpstream'). PHP_EOL;


        $site_web_url = parse_url(home_url(), PHP_URL_HOST);
        $headers = 'From: No Reply <noreply@' . $site_web_url . '>' . "\r\n";

        if(isset($_POST['elementor_email_subject'])){
          $subject        = sanitize_text_field( $_POST['elementor_email_subject']);
        }



      
		wpstream_theme_send_emails( $receiver_email, $subject, $message, '' );


        echo json_encode(array('sent'=>true,'data'=>true, 'response'=>esc_html__( 'The message was sent !','hello-wpstream') ) );
    die();
}

endif; // end   wpstream_theme_ajax_agent_contact_form

