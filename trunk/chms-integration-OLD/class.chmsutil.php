<?php
class ChmsUtil {
	const ERROR = 3;
	const INFO = 2;
	const DEBUG = 1;
	
	private static $LEVELS = array (
		self::ERROR => 'ERROR',
		self::INFO => 'INFO ',
		self::DEBUG => 'DEBUG'
	);

	const MAX_LOGROTATE_FILES = 'max_logrotate_files';
	const MAX_LOGFILE_SIZE = 'max_logfile_size';
	const TIMESTAMP_FORMAT = 'timestamp_format';
	const ERROR_LOGFILE = 'error_logfile';
	const INFO_LOGFILE = 'info_logfile';
	const LOG_LEVEL = 'log_level';
	const LOG_DIR = 'log_dir';
	
    private static $OPTIONS = array(
    	self::MAX_LOGROTATE_FILES => 3,
    	self::MAX_LOGFILE_SIZE => 204800, // 200*1024
		self::TIMESTAMP_FORMAT => 'Ymd H:i:s',
		self::ERROR_LOGFILE => 'error.log',
		self::INFO_LOGFILE => 'info.log',
		self::LOG_LEVEL => ChmsUtil::INFO,
		self::LOG_DIR => './logs' 
	);
	/*!
	 \static
	 \public
	 get options
	*/
	public static function getOption($name) {
		return self::$OPTIONS[$name];
	}
	
	public static function setOption($name,$val) {
		self::$OPTIONS[$name] = $val;
	}
	
	public static function getOptions() {
		return self::$OPTIONS;
	}
	
    /*!
     \static
     Rotates logfiles so the current logfile is backed up,
     old rotate logfiles are rotated once more and those that
     exceed maxLogrotateFiles() will be removed.
     Rotated files will get the extension .1, .2 etc.
    */
    static function rotateLog( $fileName )
    {
        $maxLogrotateFiles = self::getOption(self::MAX_LOGROTATE_FILES);
        for ( $i = $maxLogrotateFiles; $i > 0; --$i )
        {
            $logRotateName = $fileName . '.' . $i;
            if ( @file_exists( $logRotateName ) )
            {
                if ( $i == $maxLogrotateFiles )
                {
                    @unlink( $logRotateName );
                }
                else
                {
                    $newLogRotateName = $fileName . '.' . ($i + 1);
                    self::rename( $logRotateName, $newLogRotateName );
                }
            }
        }
        if ( @file_exists( $fileName ) )
        {
            $newLogRotateName = $fileName . '.' . 1;
            self::rename( $fileName, $newLogRotateName );
            return true;
        }
        return false;
    }
    static function rename( $srcFile, $destFile )
    {
        return rename( $srcFile, $destFile );
    }

	public static function error($message)
	{
		self::_write($message,self::ERROR,self::getOption(self::ERROR_LOGFILE));
	}

	public static function info($message)
	{
		if (self::getOption(self::LOG_LEVEL) <= self::INFO)
			self::_write($message,self::INFO);
	}

	public static function debug($message)
	{
		if (self::getOption(self::LOG_LEVEL) <= self::DEBUG)
			self::_write($message,self::DEBUG);
	}

    /*!
     \static
     \private
     Writes a message $message to a given file name $name and directory $dir for logging
    */
    private static function _write( $message, $level = self::INFO, $logName = null, $dir = null )
    {
    	if (!isset($logName)) $logName = self::getOption(self::INFO_LOGFILE);
    	if (!isset($dir)) $dir = self::getOption(self::LOG_DIR);
        $fileName = $dir . '/' . $logName;
        $oldumask = @umask( 0 );
        $fileExisted = @file_exists( $fileName );
        if ( $fileExisted && filesize( $fileName ) > self::getOption(self::MAX_LOGFILE_SIZE) )
        {
            if ( self::rotateLog( $fileName ) )
                $fileExisted = false;
        }
        $logFile = @fopen( $fileName, "a" );
        if ( $logFile )
        {
        	$now = new DateTime();
            $logMessage = self::$LEVELS[$level] ." [" . $now->format( self::getOption(self::TIMESTAMP_FORMAT) ) . "] [" . getmypid() ."] $message\n";
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
    

	/*
	 * transforms SimpleXMLElement tree into an array structure. Nicer to encode as json.
	 */
	public static function xml2array($xml) { 
		if (!isset($xml)) return null;
		
		$array2return = array();
		
		if (!($xml->children())) { 
		    return (string) $xml; 
		} 
		        
		foreach ($xml->children() as $child) { 
		    $name=$child->getName(); 
		    if (count($xml->$name)==1) { //} && !in_array($xml->name,$forceArray)) { 
		        $array2return[$name] = ChmsUtil::xml2array($child); 
		    } else { 
		        $array2return[$name][] = ChmsUtil::xml2array($child); 
		    } 
		} 
		
		return $array2return;    
	} 


}
?>