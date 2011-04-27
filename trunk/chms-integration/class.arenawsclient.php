<?php


class ArenaWSClient {

	function ArenaWSClient($_baseUrl, $_apiKey, $_apiSecret) {
		$this->baseUrl = $_baseUrl; // make sure it has a trailing slash
		$this->apiKey = $_apiKey;
		$this->apiSecret = $_apiSecret;
		ChmsUtil::info("ArenaWSClient instantiated");
	}
	
	function login($_user,$_pass) {
		try {
			ChmsUtil::debug("attempting login for ".$_user);
			$loginRs = $this->_postIt("login", array("username" => $_user, "password" => $_pass, "api_key" => $this->apiKey));
			$sId = $loginRs->SessionID[0];
			ChmsUtil::info("successful login for ".$_user.", session is ".$sId);
			
			// get and return the basic user info in the PHP session
			$requestUri =  'person/list';
			$args = array ( 'loginId' => $_user, 'api_session' => $sId, 'fields' => 'PersonID');
			
			$xmlRs = $this->_getIt($requestUri,$args);
			$personID = $xmlRs->Persons->Person[0]->PersonID;
			
			$requestUri =  'person/'.$personID;
			$args = array ( 'api_session' => $sId);
			
			$personProfileXml = $this->_getIt($requestUri,$args);
			$personProfileXml->addChild('ArenaSessionID', $sId);
			return $personProfileXml;

		} catch (ArenaWSException $e) {
			ChmsUtil::error("exception logging in for ".$_user.": ".$e->getMessage());
			echo "EXCEPTION LOGGING IN: ".$e->getMessage()."\nXML[".$e->xmlRs."]\n";
		}
	}
	
	/*
	 * svcUri = REST uri of the web service, e.g. person/1715 or family/1234, or attribute/list
	 * args = array of name value pairs to send as post arguments
	 */
	function _postIt($_svcUri, $_args = null) {
		// build Url
		$requestUrl = $this->baseUrl . $_svcUri;
		
		// Get the curl session object
		$session = curl_init($requestUrl);
		
		// Set the POST options.
		curl_setopt ($session, CURLOPT_POST, true);
		curl_setopt ($session, CURLOPT_POSTFIELDS, $this->array2querystring($_args));
		curl_setopt($session, CURLOPT_HEADER, true);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		
		// Do the POST and then close the session
		$response = curl_exec($session);
		curl_close($session);
	
		return $this->evaluateResponse($response);
			
	}
	
	function _getIt($_svcUri, $_args = null) {
		$requestUri = strtolower( $_svcUri . ( ($_args == null || count($_args) == 0) ? "" : "?" . $this->array2querystring($_args) ) );
		$apiSig = md5($this->apiSecret."_".$requestUri);	
		$requestUrl = $this->baseUrl . $requestUri . "&api_sig=" . $apiSig;

		// Initialize the session
		$session = curl_init($requestUrl);
		
		// Set curl options
		curl_setopt($session, CURLOPT_HEADER, true);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		
		// Make the request and then close the curl session
		$response = curl_exec($session);		
		curl_close($session);
		
		return $this->evaluateResponse($response);
				
	}
	
	function array2queryString($_a = null) {
		if ($_a == null || count($_a) == 0) return '';
		$first = true;
		$queryString = '';
		foreach($_a as $key => $value) {
	        if ($first) {
	            $queryString = $key.'='.$value;
	            $first = false;
	        } else {
	            $queryString .= '&'.$key.'='.$value;    
	        }
	    }
	    return $queryString;
	}
	
	function evaluateResponse($_response) {
		// xml starts with first < character
		$rsXml = substr( $_response, strpos($_response,"<") );

		// Get HTTP Status code from the response
		$status_code = array();
		preg_match('/\d\d\d/', $_response, $status_code);
	
		//??how to get the Message from the error
		switch( $status_code[0] ) {
			case 200:
				// Success
				break;
			case 503:
				throw new ArenaWSException('Your call to Arena Web Services failed and returned an HTTP status of 503. That means: Service unavailable. An internal problem prevented us from returning data to you.',$rsXml);
				break;
			case 403:
				throw new ArenaWSException('Your call to Arena Web Services failed and returned an HTTP status of 403. That means: Forbidden. You do not have permission to access this resource, or are over your rate limit.',$rsXml);
				break;
			case 400:
				// You may want to fall through here and read the specific XML error
				throw new ArenaWSException('Your call to Arena Web Services failed and returned an HTTP status of 400. That means:  Bad request. The parameters passed to the service did not match as expected. The exact error is returned in the XML response.',$rsXml);
				break;
			default:
				throw new ArenaWSException('Your call to Arena Web Services returned an unexpected HTTP status of:' . $status_code[0],$rsXml);
		}
			
		
		return new SimpleXMLElement($rsXml);
	}	

}

class ArenaWSException extends Exception 
{
	function ArenaWSException($msg,$xmlRs) 
	{
		parent::__construct($msg);
		$this->xmlRs = $xmlRs;	
	}
}
?>
