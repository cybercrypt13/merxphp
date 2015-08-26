<?
//08.20.2015 ghh -  this function receives an order request and processes it into the
//appropriate tables
function getModel($vars, $responsetype)
{
global $db;
$ar = $vars;
if ( empty( $ar )  || !isset($ar[ 'VendorID' ]) || !isset( $ar[ 'ModelNumber' ] ) || 
			!isset( $ar[ 'Year' ] ) ) 
	{
	RestLog("16579 - Insufficient data provided for creating order \n".print_r($vars,true)."\n");
	RestUtils::sendResponse(400, "16579 - Insufficient data provided" ); //Internal Server Error
	return false;
	}

$ar['ModelNumberNoFormat'] = preg_replace('/[^a-zA-Z0-9]/', '', $ar['ModelNumber'] ); //strip formatting.

//now we grab inventory records for the requested item and build up our package to return
//to the dealer
$query = "select ModelID, OrderCode, Colors, ModelName, VehicleTypeID, NLA, CloseOut,
					Cost, MSRP, MAP, Description from UnitModel where VendorID=
					$ar[VendorID] and ModelNumberNoFormat='$ar[ModelNumberNoFormat]' and
					Year=$ar[Year]";

if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16581 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16581 - There was a problem getting model information."); //Internal Server Error
	return false;
	}

$row = $db->sql_fetchrow( $result );
$unit['OrderCode'] 			= $row['OrderCode'];
$unit['Colors'] 				= $row['Colors'];
$unit['ModelName']			= $row['ModelName'];
$unit['NLA']					= $row['NLA'];
$unit['CloseOut']				= $row['CloseOut'];
$unit['Cost']					= getUnitCost($row['ModelID'],$ar['DealerID'],$row['Cost'] ); 
$unit['MSRP']					= $row['MSRP'];
$unit['MAP']					= $row['MAP'];
$unit['Description']			= $row['Description'];
$modelid 						= $row['ModelID'];


if ( $modelid > 0 )
	{
	//08.25.2015 ghh -  now we grab unit inventory information
	$query = "select Warehouses.WarehouseName, Warehouses.WarehouseState,
					Qty, DaysToArrive 
					from Warehouses, UnitModelStock, DaysToFullfill
					where Warehouses.WarehouseID=UnitModelStock.WarehouseID and
					UnitModelStock.ModelID=$row[ModelID] and
					UnitModelStock.WarehouseID=DaysToFullfill.WarehouseID and
					DaysToFullfill.DealerID=$ar[DealerID] order by DaysToArrive";

	if (!$result = $db->sql_query($query))
		{
		RestLog("Error 16582 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16582 - There was a problem getting model warehouse information."); //Internal Server Error
		return false;
		}


	$i = 0;
	while ( $row = $db->sql_fetchrow( $result ) )
		{
		$rst[$i]['WarehouseName']		= $row['WarehouseName'];
		$rst[$i]['WarehouseState']		= $row['WarehouseState'];
		$rst[$i]['Qty']					= $row['Qty'];
		$rst[$i]['DaysToArrive']		= $row['DaysToArrive'];

		$i++;
		}

	$unit['Warehouses'] 			= $rst;


	//08.25.2015 ghh -  now we're getting a list of images that may exist for this
	//item
	$query = "select * from UnitModelImages where ModelID=$modelid";
	if (!$result = $db->sql_query($query))
		{
		RestLog("Error 16583 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16583 - There was a problem retrieving a list of images"); //Internal Server Error
		return false;
		}

	$i = 0;
	while ( $row = $db->sql_fetchrow( $result ) )
		{
		$img[$i]['ImageURL']		= $row['ImageURL'];
		$img[$i]['ImageSize']	= $row['ImageSize'];

		$i++;
		}

	$unit['Images'] 				= $img;
	}

RestLog("Successful Request\n");
RestUtils::sendResponse(200,json_encode( stripHTML( $unit ) ));
return true;
}




?>
