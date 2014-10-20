<?php
/*

Maurina connector for PHP.
Version 1.1
Álvaro Calleja (alvaro.calleja at gmail.com)
http://www.maurina.org

When instantiated this class sends to the Maurina console the contents of
the $_REQUEST and $_SESSION (if defined) global variables.

It also captures any errors and send its contents to the console. You can 
configure the error reporting level below.

Any user defined messages are sent via the log method.

Simple usage:

include ('Maurina.php');
$M = new Maurina();
$M->log('This is a user defined message');

-- Changelog --

1.1 Fixed a notice when the calling script does not use sessions.
1.0 Initial release.
*/
class Maurina
{
	/* CONFIGURE HERE THE ERROR REPORTING LEVEL */

	private $_ERROR             = true;
	private $_WARNING           = true;
	private $_PARSE             = true;
	private $_NOTICE            = true;
	private $_CORE_ERROR        = true;
	private $_CORE_WARNING      = true;
	private $_COMPILE_ERROR     = true;
	private $_COMPILE_WARNING   = true;
	private $_USER_ERROR        = true;
	private $_USER_WARNING      = true;
	private $_USER_NOTICE       = true;
	private $_STRICT            = true;
	private $_RECOVERABLE_ERROR = true;
	private $_DEPRECATED        = true;
	private $_USER_DEPRECATED   = true;

	/********************************************/
	
	const TYPE_USER    = 1;
	const TYPE_ERRORS  = 2;
	const TYPE_REQUEST = 3;
	const TYPE_SESSION = 4;

	public $serverIp = '127.0.0.1';
	public $serverPort = 1947;
	public $tabCaptions = array('&User', '&Errors', '&Request', '&Session');

	function __construct($serverIp = '', $serverPort = '', $tabCaptions = '')
	{
		if ($serverIp != '')
			$this->serverIp = $serverIp;
		if ($serverPort != '')
			$this->serverPort = $serverPort;
		if (count($tabCaptions) > 1)
			$this->tabCaptions = $tabCaptions;

		set_error_handler(array($this, "errorHandler"));
		register_shutdown_function(array($this, "shutdown"));

		if (count($_REQUEST) > 0)
			$this->sendLog(Maurina::TYPE_REQUEST, print_r($_REQUEST, true));
		if (isset($_SESSION))
			if (count($_SESSION) > 0)
				$this->sendLog(Maurina::TYPE_SESSION, print_r($_SESSION, true));
	}

	public function log($message)
	{
		if (gettype($message) == 'array' || gettype($message) == 'object')
			$message = '<br />' . $this->formatDump(print_r($message, true));
		if (gettype($message) == 'boolean')
			$message = ($message) ? 'true' : 'false';
		if (gettype($message) == 'NULL')
			$message = 'NULL';
		$message = nl2br($message);
		$this->sendLog(Maurina::TYPE_USER, $message, true);
	}

	public function errorHandler($errorNumber, $errorMsg, $errorFile,
								 $errorLine)
	{
		$type = '';
		switch ($errorNumber)
		{
			case 1     : $type = 'E_ERROR'; 
						 if (!$this->_ERROR) return;
						 break;
			case 2     : $type = 'E_WARNING';
						 if (!$this->_WARNING) return;
						 break;
			case 4     : $type = 'E_PARSE';
						 if (!$this->_PARSE) return;
						 break;
			case 8     : $type = 'E_NOTICE';
						 if (!$this->_NOTICE) return;
						 break;
			case 16    : $type = 'E_CORE_ERROR';
						 if (!$this->_CORE_ERROR) return;
						 break;
			case 32    : $type = 'E_CORE_WARNING';
						 if (!$this->_CORE_WARNING) return;
						 break;
			case 64    : $type = 'E_COMPILE_ERROR';
						 if (!$this->_COMPILE_ERROR) return;
						 break;
			case 128   : $type = 'E_COMPILE_WARNING';
						 if (!$this->_COMPILE_WARNING) return;
						 break;
			case 256   : $type = 'E_USER_ERROR';
						 if (!$this->_USER_ERROR) return;
						 break;
			case 512   : $type = 'E_USER_WARNING';
						 if (!$this->_USER_WARNING) return;
						 break;
			case 1024  : $type = 'E_USER_NOTICE';
						 if (!$this->_USER_NOTICE) return;
						 break;
			case 2048  : $type = 'E_STRICT';
						 if (!$this->_STRICT) return;
						 break;
			case 4096  : $type = 'E_RECOVERABLE_ERROR';
						 if (!$this->_RECOVERABLE_ERROR) return;
						 break;
			case 8192  : $type = 'E_DEPRECATED';
						 if (!$this->_DEPRECATED) return;
						 break;
			case 16384 : $type = 'E_USER_DEPRECATED';
						 if (!$this->_USER_DEPRECATED) return;
						 break;
			case 32767 : $type = 'E_ALL'; break;
		}
		$message  = "<span style='color:red'>[$type] ";
		$message .= "Line $errorLine in $errorFile";
		$message .= "</span><br /><span style='color:#DE2500'><em>";
		$message .= $this->getLineFromFile($errorFile, $errorLine);
		$message .= "</em></span><br />$errorMsg";

		$this->sendLog(Maurina::TYPE_ERRORS, $message);
	}

	public function shutdown()
	{
		if ($error = error_get_last())
        {
        	$this->errorHandler($error['type'], $error['message'],
        						$error['file'], $error['line']);
		}
	}

	public function sendLog($type, $message, $showTime = false)
	{
		$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		$data = array('tabs' => $this->tabCaptions,
					  'log1' => '',
					  'log2' =>'',
					  'log3' => '',
					  'log4' => '');
		
		if ($showTime)
		{
			$time = "<span style='color:#aaa'>[".date('H:i:s') . ']</span> ';
			$message = $time . $message;
		}

		switch ($type)
		{
			case Maurina::TYPE_USER    : $data['log1'] = $message; break;
			case Maurina::TYPE_ERRORS  : $data['log2'] = $message; break;
			case Maurina::TYPE_REQUEST : $data['log3'] = $message; break;
			case Maurina::TYPE_SESSION : $data['log4'] = $message; break;
		}

		$data = json_encode($data);
		
		socket_sendto($socket, $data, strlen($data), 0, $this->serverIp,
					  $this->serverPort);
		socket_close($socket);
	}

	public function getLineFromFile($file, $lineNumber)
	{
		$f = fopen($file, 'r');
		$count = 1;
		$line = null;
		while (($line = fgets($f)) !== false)
		{
			if ($count == $lineNumber)
				break;
			++$count;
		}
		return $line;
	}

	protected function formatDump($data)
	{
		$data = htmlentities($data);
		$data = str_replace("  ", "<span style='color:#fff'>__</span>", $data);

		return $data;
	}
}