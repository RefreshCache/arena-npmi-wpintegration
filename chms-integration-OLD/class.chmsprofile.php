<?php
/*
 * create a Profile class to make it easier to rely on these attributes
 * when a user logs in an ArenaProfile is created with the results of
 * the Arena WS call and stored in the user's PHP session
 */
class ChmsProfile {
	private $_data;
	public $Addresses;
	public $Emails;
	public $Phones;
	
	function __construct($arrayFromXml = array()) {
		$this->_data = array();
		$this->Addresses = array();
		$this->Emails = array();
		$this->Phones = array();
		$forceArray = array("Addresses","Emails","Phones");
		foreach($arrayFromXml as $k => $v) {
			// if it's not one of the below arrays just set it
			if (!in_array($k,$forceArray)) {
				$this->_data[$k] = $v;
			} else {
				if ($k == "Addresses") {
					$this->parseIntoArray($arrayFromXml["Addresses"]["Address"], $this->Addresses);
				}
				if ($k == "Emails") {
					$this->parseIntoArray($arrayFromXml["Emails"]["Email"], $this->Emails);
				}
				if ($k == "Phones") {
					$this->parseIntoArray($arrayFromXml["Phones"]["Phone"], $this->Phones);
				}
			}
		}
	}
	 // magic methods!
    public function __set($property, $value){
      return $this->_data[$property] = $value;
    }

    public function __get($property){
      return array_key_exists($property, $this->_data)
        ? $this->_data[$property]
        : null
      ;
    }
    
    private function parseIntoArray($array2parse, &$targetArray) {
		if ( (!is_array($array2parse) || count($array2parse) == 0) || !is_array($targetArray) ) return;

		
		if (!isset($array2parse[0])) {
			$obj = (object)$array2parse;
			array_push($targetArray, $obj);	
		}
		else {
			foreach ($array2parse as $objArr) {
				array_push($targetArray, (object) $objArr);
			}
		}
    	
    }
    
    public function getPhone($type = "Main/Home") {
    	foreach($this->Phones as $phone) {
    		if ($phone->PhoneTypeValue == $type) return $phone;
    	}
    	return null;
    }
    
    public static function createProfile($xmlElement) {
    	return new ChmsProfile ( ChmsUtil::xml2array($xmlElement) );
    }
}
?>