<?php
//08.20.2015 ghh -  this function receives an order request and processes it into the
//appropriate tables
function getInventory($vars, $responsetype)
{
global $db;
$ar = $vars;
if ( empty( $ar )  || !isset($ar[ 'VendorID' ]) || !isset( $ar[ 'ItemNumber' ] ) ) 
	{
	RestLog("16575 - Insufficient data provided for creating order \n".print_r($vars,true)."\n");
	RestUtils::sendResponse(400, "16575 - Insufficient data provided" ); //Internal Server Error
	return false;
	}

//now we grab inventory records for the requested item and build up our package to return
//to the client
//08.26.2015 rch - Moving ItemStock,Warehouses,DaysToFullfill to left outer joins 
//to account for not stocking an item or not putting in warehouse
//08.28.2015 ghh -  added Weight
$query = "select Items.ItemID, Items.MSRP, NLA, CloseOut,
				PriceCode, Cost, MAP, Category, WarehouseName, 
				WarehouseState, Qty, DaysToArrive, Weight
				ManufItemNumber, ManufName, SupersessionID
				from Items
				left outer join ItemStock on ItemStock.ItemID = Items.ItemID 
				left outer join Warehouses on Warehouses.WarehouseID = ItemStock.WarehouseID
				left outer join DaysToFullfill on DaysToFullfill.WarehouseID = ItemStock.WarehouseID
				where Items.ItemNumber='$ar[ItemNumber]' and
				Items.VendorID=$ar[VendorID] and
				DaysToFullfill.ClientID=$ar[ClientID] order by DaysToArrive";

if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16576 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16576 - There was a problem getting inventory information."); //Internal Server Error
	return false;
	}

$i = 0;
$itemid = 0;
while ( $row = $db->sql_fetchrow( $result ) )
	{
	//grabbing our details on first run through as no sense in grabbing
	//more than once.
	if ( $itemid == 0 )
		{
		$itemid = $row['ItemID'];
		$OrigManufName		= $row['ManufName'];
		$OrigManufNumber	= $row['ManufItemNumber'];
		$NLA					= $row['NLA'];
		$CloseOut			= $row['CloseOut'];
		$MSRP					= $row['MSRP'];
		$Category			= $row['Category'];
		$MAP					= $row['MAP'];
		$Weight				= $row['Weight']; //08.28.2015 ghh -  
		}

	$rst[$i]['WarehouseName']		= $row['WarehouseName'];
	$rst[$i]['WarehouseState']		= $row['WarehouseState'];
	$rst[$i]['Qty']					= $row['Qty'];
	$rst[$i]['DaysToArrive']		= $row['DaysToArrive'];

	$i++;
	}

//09.01.2015 ghh -  if we found the item in question then enter here
//otherwise we're going to return an error
if ( $itemid > 0 )
	{
	$item['Warehouses'] 			= $rst;
	$item['MSRP']					= $MSRP;

	if ( $itemid > 0 )
		$item['Cost']					= getItemCost( $itemid, $ar['ClientID'],
																	$row['PriceCode'],
																	$row['Cost'], $row['MSRP'] );

	//08.25.2015 ghh -  if BSV asked for full detail then we're also going to send back
	//images data and other items of interest
	if ( $row['SupersessionID'] > 0 )
		{
		$query = "select ItemNumber from Items where ItemID=$row[SupersessionID]";
		if (!$tmpresult = $db->sql_query($query))
			{
			RestLog("Error 16578 in query: $query\n".$db->sql_error());
			RestUtils::sendResponse(500, "16578 - There was a problem retrieving the supersession number"); //Internal Server Error
			return false;
			}
		$tmprow = $db->sql_fetchrow( $tmpresult );
		$item['SupersessionNumber']		= $tmprow['ItemNumber'];
		}

	$item['OrigManufName']		= $ManufName;
	$item['OrigManufNumber']	= $ManufItemNumber;
	$item['NLA']					= $NLA;
	$item['Category']				= $Category;
	$item['MAP']					= $MAP;


	//08.25.2015 ghh -  now we're getting a list of images that may exist for this
	//item
	$query = "select * from ItemImages where ItemID=$itemid";
	if (!$result = $db->sql_query($query))
		{
		RestLog("Error 16577 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16577 - There was a problem retrieving a list of images"); //Internal Server Error
		return false;
		}

	$i = 0;
	while ( $row = $db->sql_fetchrow( $result ) )
		{
		$img[$i]['ImageURL']		= $row['ImageURL'];
		$img[$i]['ImageSize']	= $row['ImageSize'];

		$i++;
		}

	$item['Images'] 				= $img;
	}
else
	{
	RestLog("Error 16635 The item number being requested doesn't exist\n");
	RestUtils::sendResponse(500, "16635 - The Item you requested was not found."); //Internal Server Error
	return false;
	}
	

RestLog("Successful Request\n");
//08.10.2012 naj - return code 200 OK.
RestUtils::sendResponse(200,json_encode( stripHTML( $item ) ));
return true;
}




?>
