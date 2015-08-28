<?php
//08.25.2015 ghh -  this function is responsible for returning a list of
//vendor names and ID's for this specific server
function getVendors( )
{
global $db;

$query = "select * from Vendors";

if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16522 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16522 - There was a problem attempting to locate the PO"); //Internal Server Error
	return false;
	}

$i = 0;
while ( $row = $db->sql_fetchrow( $result ) )
	{
	$vendors[$i]['VendorID'] 	= $row['VendorID'];
	$vendors[$i]['VendorName']	= $row['VendorName'];
	$i++;
	}



RestLog("Successful Request\n");
//08.10.2012 naj - return code 200 OK.
RestUtils::sendResponse(200,json_encode( stripHTML( $vendors ) ));
return true;
}


?>
