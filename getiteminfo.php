<?
//08.25.2015 ghh -  this function is responsible for returning pricing and other
//data about a specific part but no inventory or image data
function getItemInfo($vars, $responsetype)
{
global $db;
$ar = $vars;
if ( empty( $ar )  || !isset($ar[ 'VendorID' ]) || !isset( $ar[ 'ItemNumber' ] ) ) 
	{
	RestLog("16584 - Insufficient data provided for creating order \n".print_r($vars,true)."\n");
	RestUtils::sendResponse(400, "16584 - Insufficient data provided" ); //Internal Server Error
	return false;
	}

//now we grab inventory records for the requested item and build up our package to return
//to the dealer
$query = "select Items.ItemID, Items.MSRP, NLA, CloseOut,
				PriceCode, Cost, MAP, Category, 
				ManufItemNumber, ManufName, SupersessionID
				from Items
				where 
				ItemNumber='$ar[ItemNumber]' and
				VendorID=$ar[VendorID]";

if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16585 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16585 - There was a problem getting item information."); //Internal Server Error
	return false;
	}

$row = $db->sql_fetchrow( $result );

$item['OrigManufName']		= $row['ManufName'];
$item['OrigManufNumber']	= $row['ManufItemNumber'];
$item['NLA']					= $row['NLA'];
$item['CloseOut']				= $row['CloseOut'];
$item['MSRP']					= $row['MSRP'];
$item['Category']				= $row['Category'];
$item['MAP']					= $row['MAP'];

if ( $row['ItemID'] > 0 )
	$item['Cost']					= getItemCost( $row['ItemID'], $ar['DealerID'],
																	$row['PriceCode'],
																	$row['Cost'], $row['MSRP'] );

//08.25.2015 ghh -  if BSV asked for full detail then we're also going to send back
//images data and other items of interest
if ( $row['SupersessionID'] > 0 )
	{
	$query = "select ItemNumber from Items where ItemID=$row[SupersessionID]";
	if (!$tmpresult = $db->sql_query($query))
		{
		RestLog("Error 16586 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16586 - There was a problem retrieving the supersession number"); //Internal Server Error
		return false;
		}
	$tmprow = $db->sql_fetchrow( $tmpresult );
	$item['SupersessionNumber']		= $tmprow['ItemNumber'];
	}



RestLog("Successful Request\n");
//08.10.2012 naj - return code 200 OK.
RestUtils::sendResponse(200,json_encode( stripHTML( $item ) ));
return true;
}




?>
