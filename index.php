<?php
require ('includes/restcommon.php');
error_reporting(E_ERROR | E_WARNING | E_PARSE);

//Create REST object
$rest = RestUtils::processRequest();

//06.08.2012 naj - for now we only support json
$responsetype = 'application/json';

//Trim the leading /rest off the request uri and then parse the uri
$requesttype= $rest->getRequestType();
if (empty($requesttype))
	die(RestUtils::sendResponse(400, 'Error 16532 Bad Request'));

//01.23.2013 naj - get the request vars
$requestvars = cleanRest($rest->getRequestVars());

//08.20.2015 ghh -  first we need to make sure we have a valid
//bsvkey to work with.  This insures the request is coming from a
//tested BSV system and not just from anywhere
if (isset($requestvars["bsvkey"]))
	$bsvkey = $requestvars["bsvkey"];
else
	//return error
	die(RestUtils::sendResponse(400, 'Error 16533: Missing BSVKey'));

//08.20.2015 ghh -  now validate the bsv key is valid and that it exists within
//the vendors database records
if ( !verifyBSV( $bsvkey ) )
	die(RestUtils::sendResponse(400, 'Error 16534: Bad BSVKey'));

RestLog("USER AGENT: $_SERVER[HTTP_USER_AGENT]");

//08.20.2015 ghh -  now we need to verify the dealerkey that was sent in and
//build a new one if we don't already have it.  In the event we don't have one
//one will be build on the first every call and returned to the BSV to store for
//future calls.  If they fail to store it then the dealer will be down until 
//someone manually resets the dealership on the server.
$dealerkey = verifyDealerKey( $requestvars["dealerkey"], $requestvars["accountnumber"] );

$requestvars['DealerKey'] = $dealerkey;

switch($rest->getMethod())
	{
	//08.20.2015 ghh -  get requests enter here
	case 'get':
		switch ($requesttype)
			{
			case 'getvendors':
				RestLog("Getting List of Vendors");
				require_once("getvendors.php");
				getVendors( );
				break;
			case 'getinventory'://08.25.2015 ghh -  added getinventory request
				RestLog("Getting Inventory");
				require_once("getinventory.php");
				getInventory($requestvars, $responsetype);
				break;
			case 'getiteminfo'://08.25.2015 ghh -  added getinventory request
				RestLog("Getting Item Information");
				require_once("getiteminfo.php");
				getItemInfo($requestvars, $responsetype);
				break;
			case 'getmodel'://08.25.2015 ghh -  added getinventory request
				RestLog("Getting Model Info");
				require_once("getmodel.php");
				getModel($requestvars, $responsetype);
				break;
			case 'getorderstatus'://08.25.2015 ghh -  added getinventory request
				RestLog("Getting Order Status");
				require_once("getorderstatus.php");
				getOrderStatus($requestvars, $responsetype);
				break;
			default:
				die(RestUtils::sendResponse(400, 'Error 16542: Bad Request')); //Bad Request
				break;
			}
		break;

	//08.20.2015 ghh -  send requests enter here
	case 'post':
		switch ($requesttype)
			{
			case 'sendorder':
				RestLog("Send Order Called");
				require_once("sendorder.php");
				sendOrder($requestvars, $responsetype);
				break;

			default:
				die(RestUtils::sendResponse(400, 'Error 16543 Bad Request')); //Bad Request
				break;
			}
		break;
	default:
		die(RestUtils::sendResponse(400, 'Error 16544 Bad Post/Get Request')); //Bad Request
	break;
	}
?>
