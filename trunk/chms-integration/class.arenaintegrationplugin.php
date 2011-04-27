<?php
/*
 * invokes the Arena WS to authenticate a user based on their Arena credentials
 */
class ArenaIntegrationPlugin extends ChmsIntegrationPlugin {
    var $adminOptionsName = 'ArenaAuthenticationAdminOptions';

    var $auth_service_path_setting = 'arena_authentication_service_path';
    var $org_id_setting = 'arena_org_id';
    var $arena_roles = 'arena_security_roles';
    var $wp_default_role = 'default_wordpress_role';
    var $arena_api_key = 'arena_api_key';
    var $arena_api_secret = 'arena_api_secret';


    function ArenaIntegrationPlugin() {
    	ChmsUtil::info("instantiate ArenaIntegrationPlugin");
		require_once("class.arenawsclient.php");
    }

    /**
     * This is where the real heavy lifting happens. Take user input and attempt to authenticate
     * against Arena Authentication web service.
     */
    public function do_authenticate($user = NULL, $username = '', $password = '') {
        $this->isAuthenticated = false;
        $userID = NULL;
        
        // case insensitive matching on username
        $username = strtolower($username);
        $options = $this->load_options();

        if ($username != '' AND $password != '') {
			$arenaWs = new ArenaWSClient($options[$this->auth_service_path_setting],$options[$this->arena_api_key],$options[$this->arena_api_secret]);

			try {
				$personXml = $arenaWs->login($username,$password);
				
				$chmsProfile = ChmsProfile::createProfile($personXml);
				$_SESSION['ChmsProfile'] = $chmsProfile;
									
                $user = get_userdatabylogin($username);

                // If user does not exist in WP database, create one
                if (!$user) {
                    $email = (string)$chmsProfile->Emails[0]->Address;
                    $first_name = (string)$chmsProfile->NickName;
                    $last_name = (string)$chmsProfile->LastName;
                    $display_name = (string)$chmsProfile->NickName;
                    $user_id = $this->create_user($username, $password, $email, $first_name, $last_name, $display_name, $options[$this->wp_default_role]);
                }

                // load user object
                if (!$user_id) {
                    require_once(ABSPATH . WPINC . DIRECTORY_SEPARATOR .'registration.php');
                    $user_id = username_exists($username);
                } 
                
                $user = new WP_User($user_id);
                $this->isAuthenticated = true;
                return $user;

			} catch (ArenaWSException $e) {
			
                return false;
            }
        }

        return false;
    }

    public function load_options() {

        $options = array(
            $this->auth_service_path_setting => '',
            $this->org_id_setting => '1',
            $this->arena_roles => '',
            $this->wp_default_role => 'Author',
            $this->arena_api_key => '',
            $this->arena_api_secret => ''                
        );

        $devOptions = get_option($this->adminOptionsName);

        if (!empty($devOptions)) {
            foreach ($devOptions as $key => $option) {
                $options[$key] = $option;
            }
        }

        update_option($this->adminOptionsName, $options);
        return $options;
    }

    public function print_admin_page() {

        $options = $this->load_options();

        if (isset($_POST['update_arenaAuthenticationPluginSettings'])) {
            if (isset($_POST['authServicePath'])) {
                $options[$this->auth_service_path_setting] = apply_filters('content_save_pre', $_POST['authServicePath']);
            }
            if (isset($_POST['orgID'])) {
                $options[$this->org_id_setting] = apply_filters('content_save_pre', $_POST['orgID']);
            }

            if (isset($_POST['securityRoles'])) {
                $options[$this->arena_roles] = apply_filters('content_save_pre', $_POST['securityRoles']);
            }

            if (isset($_POST['defaultRole'])) {
                $options[$this->wp_default_role] = apply_filters('content_save_pre', $_POST['defaultRole']);
            }

            if (isset($_POST['arenaApiKey'])) {
                $options[$this->arena_api_key] = apply_filters('content_save_pre', $_POST['arenaApiKey']);
            }

            if (isset($_POST['arenaApiSecret'])) {
                $options[$this->arena_api_secret] = apply_filters('content_save_pre', $_POST['arenaApiSecret']);
            }

            update_option($this->adminOptionsName, $options);
            ?>
        <div class="updated">
            <p><strong><?php _e("Settings Updated.", "ArenaAuthenticationPlugin"); ?></strong></p>
        </div>
        <?php
        } ?>

        <div class="wrap">
            <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                <h2 class="arena">Arena Integration</h2>
                
                <table class="form-table">
                    <tr>
                        <td class="left" scope="row">
                            <label for="authServicePath">Service Path: </label>
                            <span class="small-text">Base Path to Arena Web Services on your Arena server. Don't forget SSL if needed.</span>
                        </td>
                        <td>
                            <input type="text" id="authServicePath" name="authServicePath" class="fullsize" value="<?php echo $options[$this->auth_service_path_setting]; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td class="left" scope="row">
                            <label for="orgID">Organization ID: </label>
                            <span class="small-text">Arena Organization ID (It's probably "1").</span>
                        </td>
                        <td>
                            <input type="text" id="orgID" name="orgID" value="<?php echo $options[$this->org_id_setting] ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td class="left" scope="row">
                            <label for="securityRoles">Security Roles: </label>
                            <span class="small-text">Comma-separated list of Arena security roles required to access WordPress. 
                                Leave blank if you want any Arena user to be able to log in.</span>
                        </td>
                        <td>
                            <textarea id="securityRoles" name="securityRoles" cols="50" rows="10"><?php echo $options[$this->arena_roles]; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="left" scope="row">
                            <label for="defaultRole">Default WordPress Role: </label>
                            <span class="small-text">If the user doesn't have a WordPress account, one is created for them.
                                What role would you like them to be placed in by default?</span>
                        </td>
                        <td>
                            <select id="defaultRole" name="defaultRole">
                                <option value="">-- None Selected --</option>
                            <?php
                                global $wp_roles;
                                $role_setting = $options[$this->wp_default_role];
                                foreach ( $wp_roles->role_names as $role => $name ) :
                            ?> <option value="<?php echo $role; ?>" <?php echo strtolower($role_setting) == $role ? 'selected="selected"' : ''; ?>><?php echo $name; ?></option> <?php
                                endforeach
                            ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="left" scope="row">
                            <label for="arenaApiKey">Arena API Key: </label>
                            <span class="small-text">From Arena API Applications - the WS application has a key and secret.</span>
                        </td>
                        <td>
                            <input type="text" id="arenaApiKey" name="arenaApiKey" class="fullsize" value="<?php echo $options[$this->arena_api_key]; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <td class="left" scope="row">
                            <label for="arenaApiSecret">Arena API Secret: </label>
                            <span class="small-text">From Arena API Applications - the WS application has a key and secret.</span>
                        </td>
                        <td>
                            <input type="text" id="arenaApiSecret" name="arenaApiSecret" class="fullsize" value="<?php echo $options[$this->arena_api_secret]; ?>" />
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="update_arenaAuthenticationPluginSettings" value="<?php _e("Update Settings", "ArenaAuthenticationPlugin"); ?>" class="button-primary" />
                </p>
            </form>
        </div>
        <?php
    }
    
    public function call_ws($ws_uri, $args = null) {
		if (!isset($args)) $args = array();

		// invoke the arena ws
		$user = $this->getChmsProfile();
		$sid = $user->ArenaSessionID;
		if (!isset($sid)) {
			// user is not logged in - disallow
		}		
		$args['api_session'] = $sid;
		
		$options = $this->load_options();
		$key = $options[$this->arena_api_key];
		$secret = $options[$this->arena_api_secret];
		$base = $options[$this->auth_service_path_setting];
		
		try {
			$arenaWs = new ArenaWSClient($base,$key,$secret);	
			error_log("calling URI[".$ws_uri."] with args[".print_r($args,true)."]");
			$rsXml = $arenaWs->_getIt($ws_uri,$args);
			
			return $rsXml;
		
		} catch (ArenaWSException $ae) {
			throw new Exception($ae->getMessage() ."\n\n".$ae->rsXml);
		} 
	}
}
?>