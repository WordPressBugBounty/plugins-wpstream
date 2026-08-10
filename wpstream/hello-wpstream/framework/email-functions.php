<?php
/**
 * Email functions.
 *
 * Helper wrappers around wp_mail() used by the theme, plus the AJAX handler
 * that backs the front-end contact form (both the classic and Elementor
 * contact widgets). Provides the default "From"/"Reply-To" identity and a
 * single send routine that switches between plain-text and HTML headers.
 *
 * @package wpstream-theme
 */

// Only define once; guards against redeclaration when the theme/plugin overlap.
if ( ! function_exists( 'wpstream_theme_return_sending_email' ) ) {
	/**
	 * Build the default "From" identity used for outgoing theme emails.
	 *
	 * @return string A formatted "Name <email>" sender string.
	 */
	function wpstream_theme_return_sending_email() {
		// Placeholder sender address; intended to be customised per install.
		$from_email = 'noreply@changeme.net';
		// Placeholder display name shown as the sender.
		$name_email = 'changeME';

		// Assemble the RFC-style "Name  <email>" header value.
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
		// When no explicit reply-to is passed, reply-to defaults to the sender identity.
		if ( '' === $reply_to ) {
			$reply_to = wpstream_theme_return_sending_email();
		}

		// Default headers: HTML body with UTF-8 encoding.
		$headers = 'From: ' . wpstream_theme_return_sending_email() . "\r\n" .
			'Reply-To:' . $reply_to . "\r\n" .
			'Content-Type: text/html; charset="UTF-8"' . "\r\n" .
			'Content-Transfer-Encoding: 8bit' . "\r\n" .
			'MIME-Version: 1.0' . "\r\n" .
			'X-Mailer: PHP/' . phpversion();

		// Override the Content-Type with a plain-text variant when requested.
		if ( 'text' === $email_type ) {
			$headers = 'From: ' . wpstream_theme_return_sending_email() . "\r\n" .
				'Reply-To:' . $reply_to . "\r\n" .
				'Content-Type: text/plain ; charset="UTF-8"' . "\r\n" .
				'Content-Transfer-Encoding: 8bit' . "\r\n" .
				'MIME-Version: 1.0' . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
		}

		// Append any caller-supplied extra headers to the base header block.
		$headers = $headers . $extra_headers;

		// Dispatch the email; stripslashes undoes any slashes added by WP on input.
		$sent = wp_mail(
			$user_email,
			stripslashes( $subject ),
			stripslashes( $message ),
			$headers
		);

		// Log a failure so delivery problems are visible in the error log.
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


// Register the contact-form AJAX handler for both logged-out and logged-in users.
add_action( 'wp_ajax_nopriv_wpstream_ajax_contact_function', 'wpstream_ajax_contact_function' );
add_action( 'wp_ajax_wpstream_ajax_contact_function', 'wpstream_ajax_contact_function' );

// Guard against redeclaration before defining the handler.
if( !function_exists('wpstream_ajax_contact_function') ):

    /**
     * AJAX handler for the front-end contact form.
     *
     * Validates the submitted fields, builds a plain message body, and emails it
     * to the site admin. Handles both the classic contact form and the Elementor
     * contact-form builder (which sends a pre-composed comment only).
     * Echoes a JSON payload describing success/failure and exits.
     *
     * @return void
     */
    function wpstream_ajax_contact_function(){


        // check for POST vars
        // Accumulator for the outgoing message body.
        $message        =   '';
        // Error flag (declared but not actively used below).
        $hasError       =   false;
        // Empty allowlist means wp_kses strips all HTML from user input.
        $allowed_html   =   array();
        // Scratch output buffer (declared but not actively used below).
        $to_print       =   '';
        // Reject the request unless the contact-form nonce is valid.
        if ( !wp_verify_nonce( $_POST['nonce'], 'ajax-property-contact')) {
            exit("No naughty business please");
        }
        
        // Flag: 1 when the submission comes from the Elementor contact widget.
        $is_elementor_contact_builder =  intval($_POST['is_elementor']);

        // Classic contact form path: validate name/email/comment individually.
        if($is_elementor_contact_builder==0){
            // Validate the name field.
            if ( isset($_POST['name']) ) {
                // Empty name (or the untouched placeholder) is treated as invalid.
                if( trim($_POST['name']) =='' || trim($_POST['name']) ==esc_html__( 'Your Name','hello-wpstream') ){
                    echo json_encode(array('sent'=>false, 'response'=>esc_html__( 'The name field is empty !','hello-wpstream') ));
                    exit();
                }else {
                    // Sanitize the name (strips all HTML).
                    $name = wp_kses( trim($_POST['name']),$allowed_html );
                }
            }


            //Check email
            if ( isset($_POST['email']) || trim($_POST['email']) ==esc_html__( 'Your Email','hello-wpstream') ) {
                  // Empty email is invalid.
                  if( trim($_POST['email']) ==''){
                        echo json_encode(array('sent'=>false, 'response'=>esc_html__( 'The email field is empty','hello-wpstream' ) ) );
                        exit();
                  // Reject malformed email addresses.
                  } else if( filter_var($_POST['email'],FILTER_VALIDATE_EMAIL) === false) {
                        echo json_encode(array('sent'=>false, 'response'=>esc_html__( 'The email doesn\'t look right !','hello-wpstream') ) );
                        exit();
                  } else {
                        // Sanitize the accepted email address.
                        $email = wp_kses( trim($_POST['email']),$allowed_html );
                  }
            }

            //Check comments
            if ( isset($_POST['comment']) ) {
                  // Empty message (or the untouched placeholder) is invalid.
                  if( trim($_POST['comment']) =='' || trim($_POST['comment']) ==esc_html__( 'Your Message','hello-wpstream')){
                    echo json_encode(array('sent'=>false, 'response'=>esc_html__( 'Your message is empty !','hello-wpstream') ) );
                    exit();
                  }else {
                    // Sanitize the message body.
                    $comment = wp_kses($_POST['comment'] ,$allowed_html );
                  }
            }


          // Prepend the collected name and email to the message body.
          $message    .=  esc_html__('Client Name','hello-wpstream').": " . $name . PHP_EOL;
          $message    .=  esc_html__('Email','hello-wpstream').": " . $email . PHP_EOL;
          // Optional website field, when supplied.
          if(isset($_POST['website'])){
              $website = wp_kses( trim($_POST['website']),$allowed_html );
              $message    .=  esc_html__('Website','hello-wpstream').": " . $website . PHP_EOL;
          }

        }else{
            // Elementor contact-builder path: use its pre-composed comment only.
            if ( isset($_POST['comment']) ) {
                $comment = wp_kses($_POST['comment'] ,$allowed_html );
                // Convert literal "/n" tokens into real line breaks.
                $comment = str_replace('/n',PHP_EOL,$comment);
            
            }
        }


        // Default subject line records the site the form was submitted from.
        $subject =esc_html__( 'Contact form from ','hello-wpstream') . esc_url( home_url('/') ) ;
        // Deliver to the site's configured admin email.
        $receiver_email = esc_html(get_option('admin_email') );
        // Append the message body and a provenance note.
        $message    .=  esc_html__('Message','hello-wpstream').": ".PHP_EOL." " . $comment. PHP_EOL;
        $message    .=  esc_html__('Message sent from contact page','hello-wpstream'). PHP_EOL;


        // Derive the host for a fallback From header (note: unused by the send call below).
        $site_web_url = parse_url(home_url(), PHP_URL_HOST);
        $headers = 'From: No Reply <noreply@' . $site_web_url . '>' . "\r\n";

        // Elementor widgets may supply their own subject line.
        if(isset($_POST['elementor_email_subject'])){
          $subject        = sanitize_text_field( $_POST['elementor_email_subject']);
        }



      
		// Send the composed contact email to the site admin.
		wpstream_theme_send_emails( $receiver_email, $subject, $message, '' );


        // Report success back to the AJAX caller and stop execution.
        echo json_encode(array('sent'=>true,'data'=>true, 'response'=>esc_html__( 'The message was sent !','hello-wpstream') ) );
    die();
}

endif; // end   wpstream_theme_ajax_agent_contact_form

