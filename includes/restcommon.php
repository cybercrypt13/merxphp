<?php
$dbhost = "";
$db;

//replace with root path to where you installed merxphp
$ROOTPATH = "/var/www/merxphp";

require_once ("$ROOTPATH/vars.php");
require_once ("$ROOTPATH/db/mysql5.php");
require_once ("$ROOTPATH/includes/db.php");
require_once ("$ROOTPATH/db/mysql5.php");
require_once ("$ROOTPATH/includes/restutils.php");

date_default_timezone_set('UTC');
//date_default_timezone_set('America/New_York');

//08.20.2015 ghh -  this function verifies the dealerkey is correct and
//that if it doesn't have one already it will generate one and return
function verifyDealerKey( $dealerkey, $accountnumber )
{
global $db;
global $requestvars;

if ( $db->db_connect_id )
	{
	$query = "select DealerID, DealerKey, IPAddress, Active 
					from DealerCredentials where
					AccountNumber='$accountnumber' ";

	if ( !$result = $db->sql_query( $query ) )
		{
		RestLog( "Error" );
		die(RestUtils::sendResponse(500, "Error: 16535 There was a problem finding dealer record"));
		}

	$row = $db->sql_fetchrow($result);

	//08.20.2015 ghh -  if the query returns nothing,that means that there is not 
	//a valid dealer key for that location and one needs to be created
	if ( $row[ 'IPAddress' ] != '' && $row[ 'IPAddress' ] != $SERVER['REMOVE_ADDR' ] )
		{
		RestLog( "Error Not Authorized From This IP Address" );
		die(RestUtils::sendResponse(401, 'Error 16536 Bad Location'));
		}

	
	//08.20.2015 ghh -  here we deal with an inactive dealer trying to work with 
	//the system
	if ( $row[ 'Active' ] == 0 )
		{
		RestLog( "Error Account Is Inactive" );
		die(RestUtils::sendResponse(401, 'Error 16537 Inactive Account'));
		}


	//08.20.2015 ghh -  now we see if they have a valid key
	if ( isset( $row[ 'DealerKey' ] ) && $row[ 'DealerKey' ] != $dealerkey )
		{
		RestLog( "Error Dealer Key is Invalid Query:".$query."\n $row[DealerKey] != $dealerkey" );
		die(RestUtils::sendResponse(401, 'Error 16538 Bad Dealer Key'));
		}

	//08.20.2015 ghh -  if we got this far and don't have a dealer key at all
	//then we need to generate one here.
	if ( !isset( $row[ 'DealerKey' ] ) )
		{
		$uuid = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		// 32 bits for "time_low"
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

		// 16 bits for "time_mid"
		mt_rand( 0, 0xffff ),

		// 16 bits for "time_hi_and_version",
		// four most significant bits holds version number 4
		mt_rand( 0, 0x0fff ) | 0x4000,

		// 16 bits, 8 bits for "clk_seq_hi_res",
		// 8 bits for "clk_seq_low",
		// two most significant bits holds zero and one for variant DCE1.1
		mt_rand( 0, 0x3fff ) | 0x8000,

		// 48 bits for "node"
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);

		//08.20.2015 ghh -  save the new dealerkey into the dealer table
		$query = "update DealerCredentials set DealerKey='$uuid' 
						where DealerID=$row[DealerID]";

		if (!$result = $db->sql_query($query))
			{
			RestLog("Error in query: $query\n".$db->sql_error());
			RestUtils::sendResponse(500, 'Error 16539 Problem updating key'); //Internal Server Error
			return false;
			}

		
		return $uuid;
		}

	//08.20.2015 ghh -  if the query does return something, we need to evaluate
	//if the dealer key that it returns is a match to the dealer key that is passed
	//in
	$requestvars[ 'DealerID' ] = $row[ 'DealerID' ];
	if ($dealerkey == $row['DealerKey'])
		return $dealerkey;
	}
else
	{
	RestLog( "Error" );
	die(RestUtils::sendResponse(500, 'Error 16540 Internal Database problem.'));
	}

}

//this function looks up the BSV and Dealer keys and verifies they are
//valid before continuing.  It will return the dealer key only if this
//is the first call being made by this dealer and we've verified they
//exist in our tables
function verifyCaller( $vars )
{
global $db;



}


function RestLog($msg)
{
global $ROOTPATH;
error_log(date('r')." REST Request from $_SERVER[REMOTE_ADDR]: $msg\n", 3, "$ROOTPATH/merx.log");
}

function cleanRest($data)
{
if (isset($data) && is_array($data))
	{
	foreach ($data as $key=>$value)
		{
		$key = strtolower($key);
		$data[$key] = addslashes(trim($value));
		}
	return $data;
	}
else
	return null;
}

function cleanJSON($data)
{
//01.23.2013 naj - recursive function for putting addslashes on all json elements
//01.23.2013 naj - can handle json as either an object or an associative array
foreach ($data as $key => $value)
	{
	if (is_object($value) || is_array($value))
		$temp = cleanJSON($value);
	else
		$temp = addslashes($value);

	if (is_object($data))
		$data->$key = $temp;
	else
		$data[$key] = $temp;
	}
return $data;
}


function checkvars($vars, $required)
{
if (is_array($required))
	{
	foreach ($required as $element)
		{
		if (!isset($vars[$element]) || empty($vars[$element]) || is_null($vars[$element]))
			return false;
		}
	}
else
	{
	if (!isset($vars[$required]) || empty($vars[$required]) || is_null($vars[$required]))
		return false;
	}

return true;
}


function stripHTML($data)
{
$find = array("<br>", "&nbsp;");
$replace = array("\n", " ");

if (is_array($data))
	{
	foreach ($data as $key => $value)
		{
		if (is_array($value))
			$data[$key] = stripHTML($value);
		else
			$data[$key] = str_replace($find, $replace, $value);
		}

	return $data;
	}
else
	return str_replace($find, $replace, $data);
}


//08.21.2015 ghh -  this function deals with looking up an item in order to calculate
//its cost for the current dealership
function getItemCost( $itemid, $dealerid, $pricecode, $cost, $list )
{
global $db;

//08.21.2015 ghh -  first we're going to see if there is a dealer specific price for 
//this item and if so we'll just pass it back
$query = "select DealerCost from ItemCost where ItemID=$itemid and 
				DealerID=$dealerid";
if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16527 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500,"16527 - There was a problem attempting find the dealer cost"); //Internal Server Error
	return false;
	}

$row = $db->sql_fetchrow( $result );

//if there is a cost then lets just return it.
if ( $row['DealerCost'] > 0 )
	return $row['DealerCost'];

//if there was no cost then the next step is to see if there is a price code
if ( $pricecode != '' )
	{
	$query = "select Discount from PriceCodesLink where DealerID=$dealerid
					and PriceCode=$pricecode";

	if (!$result = $db->sql_query($query))
		{
		RestLog("Error 16528 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500,"16528 - There was a problem finding your price code"); //Internal Server Error
		return false;
		}

	//08.28.2015 ghh -  if we did not find a dealer specific code then next we're going to 
	//look for a global code to see if we can find that
	if ( $db->sql_numrows( $result ) == 0 )
		{
		$query = "select Discount from PriceCodesLink where DealerID=0
						and PriceCode=$pricecode";

		if (!$result = $db->sql_query($query))
			{
			RestLog("Error 16626 in query: $query\n".$db->sql_error());
			RestUtils::sendResponse(500,"16626 - There was a problem finding your price code"); //Internal Server Error
			return false;
			}

		//if we found a global price code entry then enter here
		if ( $db->sql_numrows( $result ) > 0 )
			{
			$row = $db->sql_fetchrow( $result );
			if ( $row['Discount'] > 0 )
				$cost = bcmul( bcadd(1, $row['Discount']), $cost );
			else
				$cost = bcmul( bcadd( 1, $row['Discount']), $list );
			}
		}
	else
		{
		//if we found a dealer specific code then enter here
		$row = $db->sql_fetchrow( $result );

		if ( $row['Discount'] > 0 )
			$cost = bcmul( bcadd(1, $row['Discount']), $cost );
		else
			$cost = bcmul( bcadd( 1, $row['Discount']), $list );
		}
	}

return $cost;
}




//08.25.2015 ghh -  this function deals with looking up a model in order to calculate
//its cost for the current dealership
function getUnitCost( $modelid, $dealerid, $cost )
{
global $db;

//08.21.2015 ghh -  first we're going to see if there is a dealer specific price for 
//this item and if so we'll just pass it back
$query = "select Cost from UnitModelCost where ModelID=$modelid and 
				DealerID=$dealerid";
if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16562 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500,"16562 - There was a problem attempting find the dealer cost"); //Internal Server Error
	return false;
	}

$row = $db->sql_fetchrow( $result );

//if there is a cost then lets just return it.
if ( $row['Cost'] > 0 )
	return $row['Cost'];

return $cost;
}


//08.26.2015 ghh -  added to make sure nothing nasty can be sent through
//any of the vars given to merx.
function safetycheck( $vars, $responsetype )
{
//08.26.2015 ghh -  first we figure out if we're dealing with get or
//post because we can work directly with get but need to convert post
//from json object
if ( $responsetype == 'get' )
	{
	foreach ( $vars as $v )
		$temp[] = addslashes( $v );
	
	return $temp;
	}
else
	{
	return $vars;
	}
}


//08.26.2015 ghh -  this function retrieves shipvendor name and returns it
function getShipVendorName( $shipvendorid )
{
global $db;

$query = "select ShipVendorName from ShippingVendors where
				ShipVendorID=$shipvendorid";

if (!$tmpresult = $db->sql_query($query))
	{
	RestLog("Error 16601 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16601 - There was a problem getting shipping vendor"); //Internal Server Error
	return false;
	}

$shiprow = $db->sql_fetchrow( $tmpresult );
return $shiprow['ShipVendorName'];

}



?>
