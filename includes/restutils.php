<?php
class RestUtils
{
public static function processRequest()
{
// get our verb
$request_method = strtolower($_SERVER['REQUEST_METHOD']);
$return_obj = new RestRequest();

// store the method
$return_obj->setMethod($request_method);


//06.08.2012 naj - parse the uri elements
$urirequest = $_SERVER['REQUEST_URI'];

switch ($request_method)
	{
	case 'get':
		//here we need to strip off only the method call that was
		//tagged at the end of the URL
		$pos = strpos($urirequest, '?');
		$requesttype = strtolower(substr($urirequest, 1, $pos - 1));
		$urirequest = substr($urirequest, $pos + 1);
		parse_str($urirequest, $data);
		break;
	case 'post':
		$pos = strpos($urirequest, '?');
		$requesttype = strtolower(substr($urirequest, 1, $pos - 1));
		$urirequest = substr($urirequest, $pos + 1);
		parse_str($urirequest, $data);
		$data['Data'] = $_POST;
		break;
	}

//06.08.2012 naj - set the request type
$return_obj->setRequestType($requesttype);

//06.08.2012 naj - set the request vars
$return_obj->setRequestVars($data);

return $return_obj;
}

public static function sendResponse($status = 200, $body = '', $content_type = 'text/html')
{
$status_header = 'HTTP/1.1 ' . $status . ' ' . RestUtils::getStatusCodeMessage($status);
// set the status
header($status_header);
// set the content type
header('Content-type: ' . $content_type);
// set the location header if the status is 201
if ($status == '201')
	header('Location: '.$_SERVER['REQUEST_URI'].'/'.$body);
// pages with body are easy
if($body != '')
	{
	// send the body
	header('Content-Length: '.strlen($body));
	echo $body;
	exit;
	}
// we need to create the body if none is passed
else
	{
	// servers don't always have a signature turned on (this is an apache directive "ServerSignature On")
	$signature = $_SERVER['SERVER_SOFTWARE'] . ' Server at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'];

	// this should be templatized in a real-world solution
	$body = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
					<html>
						<head>
							<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
							<title>' . $status . ' ' . RestUtils::getStatusCodeMessage($status) . '</title>
						</head>
						<body>
							<h1>' . RestUtils::getStatusCodeMessage($status) . '</h1>
							<address>' . $signature . '</address>
						</body>
					</html>';

	header('Content-Length: '.strlen($body));
	echo $body;
	exit;
	}
}

public static function getStatusCodeMessage($status)
{
//HTTP Status Codes
$codes = Array(
100 => 'Continue',
101 => 'Switching Protocols',
200 => 'OK',
201 => 'Created',
202 => 'Accepted',
203 => 'Non-Authoritative Information',
204 => 'No Content',
205 => 'Reset Content',
206 => 'Partial Content',
300 => 'Multiple Choices',
301 => 'Moved Permanently',
302 => 'Found',
303 => 'See Other',
304 => 'Not Modified',
305 => 'Use Proxy',
306 => '(Unused)',
307 => 'Temporary Redirect',
400 => 'Bad Request',
401 => 'Unauthorized',
402 => 'Payment Required',
403 => 'Forbidden',
404 => 'Not Found',
405 => 'Method Not Allowed',
406 => 'Not Acceptable',
407 => 'Proxy Authentication Required',
408 => 'Request Timeout',
409 => 'Conflict',
410 => 'Gone',
411 => 'Length Required',
412 => 'Precondition Failed',
413 => 'Request Entity Too Large',
414 => 'Request-URI Too Long',
415 => 'Unsupported Media Type',
416 => 'Requested Range Not Satisfiable',
417 => 'Expectation Failed',
500 => 'Internal Server Error',
501 => 'Not Implemented',
502 => 'Bad Gateway',
503 => 'Service Unavailable',
504 => 'Gateway Timeout',
505 => 'HTTP Version Not Supported'
);

return (isset($codes[$status])) ? $codes[$status] : '';
}
}







class RestRequest
{
private $request_vars;
private $data;
private $http_accept;
private $method;

public function __construct()
	{
	$this->request_vars		= array();
	$this->request_type		= '';
	$this->http_accept		= (strpos($_SERVER['HTTP_ACCEPT'], 'json')) ? 'json' : 'xml';
	$this->method				= '';
	}

public function setMethod($method)
	{
	$this->method = $method;
	}

public function setRequestType($type)
	{
	$this->request_type = $type;
	}

public function setRequestVars($request_vars)
	{
	$this->request_vars = $request_vars;
	}

public function getData()
	{
	return $this->data;
	}

public function getMethod()
	{
	return $this->method;
	}

public function getHttpAccept()
	{
	return $this->http_accept;
	}

public function getRequestType()
	{
	return $this->request_type;
	}

public function getRequestVars()
	{
	return $this->request_vars;
	}
}
?>
