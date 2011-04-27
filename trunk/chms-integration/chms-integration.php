<?php
/*
Plugin Name: ChMS Web Services Integration 
Plugin URI: http://redmine.refreshcache.com/projects/npmiwpintegration
Description: This plugin will provide user authentication and web services calls against your Arena ChMS installation. It was based on CCCEV's original WP authentication plugin.
Version: 1.0.0
Author: North Point Community Church
Author URI: http://northpoint.org
License: GPL2
*/

/*  Copyright 2011 ChMS Web Services Integration  (email : russell.todd@northpoint.org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists("ChmsUtil")) {
	require_once("class.chmsutil.php");
	ChmsUtil::setOption(ChmsUtil::LOG_DIR, dirname(__FILE__) . "/logs" );
}
if (!class_exists("ChmsProfile")) {
	require_once("class.chmsprofile.php");
}

if (!class_exists("ChmsIntegrationPlugin")) {

try {
	/*
	 * invokes the ChMS WS to authenticate a user based on their ChMS credentials
	 */
    abstract class ChmsIntegrationPlugin {
        var $isAuthenticated = false;

        function ChmsIntegrationPlugin() {
        	ChmsUtil::info("instantiate ChmsIntegrationPlugin");
        }

	    /**
	     * Registers plugin options with WP database.
	     * Should only fire on plugin activation
	     */
	    public function init() {
	        $this->load_options();
	    }


        /**
         * Loads the options for the specific ChMS plugin.
         */
        public abstract function load_options();
		        
        /**
         * ChMS-specific plugin settings page.
         */
        public abstract function print_admin_page();
        
        /**
         * ChMS-specific authentication.
         */
        public abstract function do_authenticate();

        /**
         * ChMS-specific web service call - return an XML object.
         */
        public abstract function call_ws($ws_uri, $args);

       
        /**
		 * Wrapper function
		 *
		 * @param $arg1 WP_User or username
		 * @param $arg2 username or password
		 * @param $arg3 passwprd or empty
		 * @return WP_User
		 */
        public function authenticate($arg1 = NULL, $arg2 = NULL, $arg3 = NULL) {
            global $wp_version;
            session_start();
        	ChmsUtil::info("ChmsIntegrationPlugin:authenticate");

            if (version_compare($wp_version, '2.8', '>=')) {
                return $this->do_authenticate($arg1, $arg2, $arg3);
            }

            return $this->do_authenticate(NULL, $arg1, $arg2);
        }

        /**
         * If no user exists in the WP database matching the ChMS user, we'll create one automatically
         */
        public function create_user($username, $password, $email, $first_name, $last_name, $display_name, $default_role = '') {
            global $wp_version;
            require_once(ABSPATH . WPINC . DIRECTORY_SEPARATOR . 'registration.php');
            $return = wp_create_user($username, $password, $email);
            $user_id = username_exists($username);

            if (is_wp_error($return)) {
                echo $return->get_error_message();
                die();
            }

            if ( !$user_id ) {
                die("Error creating user!");
            }
            else {
                if (version_compare($wp_version, '3', '>=')) {
                    // WP 3.0 and above
                    update_user_meta($user_id, 'first_name', $first_name);
                    update_user_meta($user_id, 'last_name', $last_name);
                }
                else {
                    // WP 2.x
                    update_usermeta($user_id, 'first_name', $first_name);
                    update_usermeta($user_id, 'last_name', $last_name);
                }

                // set display_name
                if ($display_name != '') {
                    $return = wp_update_user(array('ID' => $user_id, 'display_name' => $display_name));
                }

                // set role
                if ( $default_role != '' ) {
                    $return = wp_update_user(array("ID" => $user_id, "role" => strtolower($default_role)));
                }
            }

            return $user_id;
        }

        public function override_password_check($check, $password, $hash, $user_id) {
		
            if ($this->isAuthenticated == true) {
                return true;
            }
            else {
                return $check;
            }
		}

        protected function is_admin($username) {
            global $wpdb;
            $admin_role = 'administrator';
            $user = get_userdatabylogin($username);
            $roles = $user->{$wpdb->prefix . 'capabilities'};

            if (array_key_exists($admin_role, $roles)) {
                return true;
            }

            return false;
        }


        protected function display_error($username) {
            ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
    <head>
        <title><?php bloginfo('name'); ?> &rsaquo; <?php echo $title; ?></title>
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
        <?php
	wp_admin_css( 'login', true );
	wp_admin_css( 'colors-fresh', true );
	do_action('login_head'); ?>
    </head>
    <body>
        <div id="login"><h1><a href="<?php echo apply_filters('login_headerurl', 'http://wordpress.org/'); ?>" title="<?php echo apply_filters('login_headertitle', __('Powered by WordPress')); ?>"><?php bloginfo('name'); ?></a></h1>
            <div id="login_error">
                <h2>Oops!</h2>
                <p>Looks like you might not have permission to edit this blog. If you feel your access has been blocked in error, please <a href="mailto:<?php bloginfo('admin_email') ?>">let us know</a>. Thanks!</p>
            </div>
        </div>
    </body>
</html>
            <?php
        }

        public function disable_function() {
            die('Disabled');
        }
        
        public function getChmsProfile() {
			return $_SESSION["ChmsProfile"];
        }
 
    }
    
    } catch (Exception $e) {
    	ChmsUtil::error("ERROR creating class: ".$e->getMessage());
    }
}

$ChMS = getChMS();

function getChMS() {
	global $ChMS;
	$ChmsPluginClass = "ArenaIntegrationPlugin";
	$ChmsPluginFilename = "class.arenaintegrationplugin.php";
	
	try {
	
		if (!class_exists($ChmsPluginClass)) {
			require_once($ChmsPluginFilename);
		}

		if (!isset($ChMS)) $ChMS = instantiate($ChmsPluginClass);	
		
		return $ChMS;
	
	} catch (Exception $e) {
		ChmsUtil::error("ERROR creating class: ".$e->getMessage());
	}
}

function instantiate($classname="") {
	if(!empty($classname)) {
		$reflection = new ReflectionClass($classname);
		if($reflection->isInstantiable()) {
			return new $classname();
		} else {
			return call_user_func(array($classname,"getInstance"));
		}
	} else throw new Exception("Could not instantiate ChMS Plugin Class [".$classname."]");
}


// TODO - make this more generic so that it allows for variable input data from the post
function chms_ws_call() {
	global $ChMS;
	check_ajax_referer('chmsws');

	session_start();
	
	if (!isset($ChMS)) $ChMS = getChMS();
	
	$wsUri = $_POST['chms_ws_uri'];
	
	// args array should be all post vars EXCEPT chms_ws_uri
	$args = array();
	foreach ($_POST as $k=>$v) {
		if ($k != 'chms_ws_uri') $args[$k] = $v;
	}
	
	$rsXml = $ChMS->call_ws($wsUri, $args);

	$newKey = wp_create_nonce('chmsws');
	$rsXml->addChild( 'ajaxkey', $newKey );	
	$ret = ChmsUtil::xml2array($rsXml);

	header("Content-type: application/json");
	echo json_encode($ret);

	exit;

}


/*
 * tricks from http://www.wphardcore.com/2010/5-tips-for-using-ajax-in-wordpress
 */
function chms_ws_scripts() {
	if (!is_admin()) {
		wp_enqueue_script ( 'chms-ws-request', plugin_dir_url( __FILE__ ) . 'js/jquery.chms.js', array('jquery') );
		wp_localize_script ( 'chms-ws-request', 'ChMSWS', array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'ajaxkey' => wp_create_nonce('chmsws')
			)
		);
	}
}

// Inline Scripts
if (isset($ChMS)) {
    // Actions
    add_action('activate_chms-integration/chms-integration.php', array(&$ChMS, 'init'));
    add_action('admin_menu', 'ChMS_ap');
    
    add_action('init','chms_ws_scripts');

    // Support only WP 2.8 and above
    add_filter('authenticate', array(&$ChMS, 'authenticate'), 10, 3);

    // Kill certain authentication behavior, since ChMS will handle authentication
    add_action('lost_password', array(&$ChMS, 'disable_function'));
    add_action('retrieve_password', array(&$ChMS, 'disable_function'));
    add_action('password_reset', array(&$ChMS, 'disable_function'));

    // Override base WP authentication
    add_filter('check_password', array(&$ChMS, 'override_password_check'), 10, 4);
    
    add_action('wp_ajax_chmsws','chms_ws_call');
    add_action('wp_ajax_nopriv_chmsws','chms_ws_call');
    
    
}

if (!function_exists('ChMS_ap')) {
    function ChMS_ap() {
        global $ChMS;

        if (!isset($ChMS)) {
            return;
        }

        if (function_exists('add_options_page')) {
            add_options_page('ChMS Integration', 'ChMS Integration', 9, basename(__FILE__), array(&$ChMS, 'print_admin_page'));
        }

        $myStyleUrl = WP_PLUGIN_URL . '/chms-integration/css/chms-integration.css';
        $myStyleFile = WP_PLUGIN_DIR . '/chms-integration/css/chms-integration.css';
        if ( file_exists($myStyleFile) ) {
            wp_register_style('chms-integration-css', $myStyleUrl);
            wp_enqueue_style( 'chms-integration-css');
        }
    }
}
?>