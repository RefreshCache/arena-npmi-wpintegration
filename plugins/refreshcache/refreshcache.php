<?php
/*
Plugin Name: Arena Web Services Integration 
Description: This plugin will provide user authentication and web services calls against your Arena ChMS installation.
Version: -1.0
Author: North Point Ministries
Author URI: http://northpoint.org
License: GPL2
*/


if ( !function_exists('get_avatar') ) :
/**
 * OVERRIDE: Retrieve the avatar for a user who provided a user ID or email address.
 *
 * @since 2.5
 * @param int|string|object $id_or_email A user ID,  email address, or comment object
 * @param int $size Size of the avatar image
 * @param string $default URL to a default image to use if no avatar is available
 * @param string $alt Alternate text to use in image tag. Defaults to blank
 * @return string <img> tag for the user's avatar
*/
function get_avatar( $id_or_email, $size = '96', $default = '', $alt = false ) {
	//debug('get_avatar(\''.$id_or_email.'\',\''.$size.'\',\''.$default.'\',\''.$alt.'\')');
	// first try to get from Arena, if not use Homer Simpson avatar
	$useHomer = false;
	if ( is_numeric($id_or_email) ) {
		$id = (int) $id_or_email;
		$user = get_userdata($id);
		if ( $user )
			$email = $user->user_email;
		else 
			$useHomer = true;
	} else {
		$email = $id_or_email;
	}
	if (!$useHomer) {
		try {
			$xml = call_arena("get", "person/list", array("email" => $email, "fields" => "BlobLink") );
			$imgUrl = (string)$xml->Persons->Person[0]->BlobLink;
			if ($imgUrl) { ?>
	<img src="<?php echo $imgUrl; ?>" class="avatar avatar-<?php echo $size; ?> photo" alt="<?php echo ($alt ? $alt : "nada"); ?>" />			
		<?php	}
		} catch (Exception $e) {
			echo "<!-- exception getting avatar: ".$e->getMessage()." -->";
			$useHomer = true;
		}	
	}
	if ($useHomer) {
?>
	<img src="http://avatar.hq-picture.com/avatars/img36/homer_simpson_avatar_picture_49656.gif" class="avatar avatar-<?php echo $size; ?> photo" alt="Homer" />
<?php
	} 
}
endif;

if ( !function_exists('wp_authenticate') ) :
/**
 * Override the wp_authenticate function 
 *
 * @param string $username User's username
 * @param string $password User's password
 * @return WP_Error|WP_User WP_User object if login successful, otherwise WP_Error object.
 */
function wp_authenticate($username, $password) {
	$username = sanitize_user($username);
	$password = trim($password);
	$user = null;

	if ( empty($username) || empty($password) ) {
		$user = new WP_Error();

		if ( empty($username) )
			$user->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));

		if ( empty($password) )
			$user->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

		return $user;
	}
	
	// see if this is a super admin: if so, use WP authentication
	$supers = get_super_admins();
	if ($supers && in_array($username,$supers))
	{
		$user = wp_authenticate_username_password($user,$username,$password);
	} else {
		$user = arena_authenticate($user,$username,$password);
	}

	if ( $user == null && !is_wp_error($user)) {
		// TODO what should the error message be? (Or would these even happen?)
		// Only needed if all authentication handlers fail to return anything.
		$user = new WP_Error('authentication_failed', __('<strong>ERROR</strong>: Invalid username or incorrect password.'));
	}

	$ignore_codes = array('empty_username', 'empty_password');

	if (is_wp_error($user) && !in_array($user->get_error_code(), $ignore_codes) ) {
		do_action('wp_login_failed', $username);
	}

	return $user;
}
endif;

function arena_authenticate($user = null, $username = null, $password = null) {
	debug('arena_authenticate for '.$username);

	try {
        $sessionXml = call_arena("post", "login", array("username" => $username, "password" => $password));
		
		debug('got sessionXml from Arena: '.$sessionXml->SessionID);
		
		$sessionID = (string)$sessionXml->SessionID;
		// now call back and get user data
		$personXml = call_arena("get", "person/list", array("api_session" => $sessionID, "loginid" => $username) );
		$person = $personXml->Persons->Person[0];
		// now see if that user has already logged in and has an existing WP User
        $user_id = username_exists($username);
		
	    // If user does not exist in WP database, create one
	    if (!$user_id) {
			debug('no user exists, create one, starting with email address ['.(string)$userXml->Emails->Email[0]->Address.']');
	    	$email = (string)$person->FirstActiveEmail;
	        
            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id))
            {
            	debug('got wp_error on wp_create_user: '.print_r($user_id,true));
            	return $user_id;
            } 
	    } else {
			debug('user exists in WP: id['.$user_id.']');	    
	    }

	    // update their info
        $first_name = (string)$person->FirstName;
        $last_name = (string)$person->LastName;
        $display_name = (string)$person->NickName;
        
        $role = 'subscriber';
	    update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'nickname', $display_name);
        if ($display_name != '') wp_update_user(array('ID' => $user_id, 'display_name' => $display_name));
        wp_update_user(array("ID" => $user_id, "role" => strtolower($role))); 
        wp_update_user(array("ID" => $user_id, "user_email" => (string)$person->FirstActiveEmail));                                            
        $arenaMeta = array (
	        'session' => $sessionID,
	        'familyId' => (int)$person->FamilyID,
	        'personId' => (int)$person->PersonID
        );
        update_user_meta($user_id,'arena_info',$arenaMeta);

        $user = new WP_User($user_id);
        //debug('WP User object: '.print_r($user,true));
        return $user;
		
	} catch (Exception $e) {
		return new WP_Error('loginfailed',$e->getMessage());	
	}
	
}

function call_arena($method = "get", $uri = false, $args = null) {
	debug('call_arena(\''.$method.'\', \''.$uri.'\',\''.implode(',',$args).'\')');
	if (!$uri) throw new Exception("You must pass in a uri");
	if ($args == null) $args = array();
	$settings = $options = get_option('arena_options');
	$args['api_key'] = $settings['arena_api_key'];
	
	if ($uri == 'login') { // no session yet	
	} else {
		if (!array_key_exists("api_session",$args)) { // pull arena sessionID out of the logged in user metadata
			if (!is_user_logged_in()) throw new Exception("you gotta be logged in to call ".$uri);
			$user = wp_get_current_user();
			if (isset($user->arena_info) && array_key_exists('session',$user->arena_info)) {
				$args['api_session'] = $user->arena_info['session'];
			} else {
				throw new Exception("you don't have a session");
			}
			
		}
	}	
	
	$response = null;
	if ($method == 'post') {
		$requestUrl = $options['arena_api_url'] . $uri;
		$postArgs = array ( 'body' => $args, 'timeout' => 30 );	
		debug('gonna post to '.$requestUrl.' with args:: '.print_r($postArgs,true));	
		$response = wp_remote_post($requestUrl,$postArgs);
		
	} else {
		$requestUri = strtolower( $uri . "?" . http_build_query($args) );
		$apiSig = md5($options['arena_api_secret']."_".$requestUri);	
		$requestUrl = $options['arena_api_url'] . $requestUri . "&api_sig=" . $apiSig;
		debug('arena get requestUrl is '.$requestUrl);

		$args['timeout'] = 30;
		$response = wp_remote_get($requestUrl,$args);
	}

	debug('Response from WP $method: '.print_r($response,true));

	if (is_wp_error($response))
		throw new Exception('WP Error doing Arena Call: '.$response->get_error_message());
		
	if ( $response['response']['code'] == 200 ) {
		$xmlRs = $response['body'];
	} else throw new Exception('HTTP Code on Response: '.$response['response']['code']);

	$xml = simplexml_load_string($xmlRs);
	return $xml;
}

/* Custom settings menu */
add_action('admin_menu', 'arena_admin_menu');

function arena_admin_menu() {
	add_options_page('Arena Settings','Arena API Plugin Settings','manage_options',__FILE__,'arena_settings_page'); // TODO - add Arena logo
	add_action('admin_init','arena_register_settings');
}

function arena_register_settings() {
	register_setting('arena_settings_group','arena_options','arena_settings_validate');
	add_settings_section('arena_settings_main','Arena API Settings','arena_settings_page_text','arena_settings');
	add_settings_field('arena_api_url','Arena API Base URL','arena_settings_url','arena_settings','arena_settings_main');
	add_settings_field('arena_api_key','Arena API Key','arena_settings_key','arena_settings','arena_settings_main');
	add_settings_field('arena_api_secret','Arena API Secret','arena_settings_secret','arena_settings','arena_settings_main');
	//register_setting('arena_settings_group','arena_api_secret');
}

function arena_settings_page_text() {
?>
<p>These values are found on the Arena API Application config page.</p>
<?php 
}

function arena_settings_url() { return arena_settings_string_input('arena_api_url'); }
function arena_settings_key() { return arena_settings_string_input('arena_api_key'); }
function arena_settings_secret() { return arena_settings_string_input('arena_api_secret'); }

function arena_settings_string_input($key) { 
	$options = get_option('arena_options');
?>
<input type="text" id="<?php echo $key; ?>" name="arena_options[<?php echo $key; ?>]" size="80" value="<?php echo $options[$key]; ?>" />
<?php
}

function arena_settings_validate($input) {
	return $input;
}

function arena_settings_page() {
?>
    <div class="wrap">
        <h2 class="arena">Arena ChMS Web Services Integration</h2>
        <form method="post" action="options.php">
        	<?php settings_fields('arena_settings_group'); 
        		  do_settings_sections('arena_settings'); ?>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e("Update Settings", "RefreshCache"); ?>" />
            </p>
        </form>
    </div>   
<?php
}
/* End Custom Settings Menu */

function debug( $message )
{
    $fileName = dirname(__FILE__) . '/rc.log';
    $oldumask = @umask( 0 );
    $fileExisted = @file_exists( $fileName );
    $logFile = @fopen( $fileName, "a" );
    if ( $logFile )
    {
        $logMessage = "[" . date( 'Y.m.d H:i:s') . "] [" . getmypid() ."] $message\n";
        @fwrite( $logFile, $logMessage );
        @fclose( $logFile );
        if ( !$fileExisted )
        {
            $permissions = octdec( '0666' );
            @chmod( $fileName, $permissions );
        }
        @umask( $oldumask );
    }
    else
    {
        error_log( 'Couldn\'t create the log file "' . $fileName . '"' );
    }
} 


?>
