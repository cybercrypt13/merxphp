<?php
//08.25.2015 ghh -  this function is responsible for returning a list of
//vendor names and ID's for this specific server
function getOrderStatus( $vars, $responsetype )
{
global $db;

$ar = safetycheck( $vars, $responsetype );

if ( !isset( $ar ) || !$ar['InternalID'] > 0 )
	{
	RestLog("16587 - Insufficient data provided for creating order \n".print_r($vars,true)."\n");
	RestUtils::sendResponse(400, "16587 - Insufficient data provided" ); //Internal Server Error
	return false;
	}


//08.26.2015 ghh -  to insure a dealer can't get a status on another dealers
//orders we need to make sure we include their internal id plus their dealerid
$query = "select * from PurchaseOrders where POID=$ar[InternalID] and
				DealerID=$ar[DealerID]";

if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16588 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16588 - There was a problem locating the order"); //Internal Server Error
	return false;
	}

//08.26.2015 ghh -  if no order was found then return
if ($db->sql_numrows($result) == 0 )
	{
	RestLog("Error 16589 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16589 - There was a problem locating the order"); //Internal Server Error
	return false;
	}

//08.26.2015 ghh -  now we grab what we need from the PO in order to return it
//to the caller
$row = $db->sql_fetchrow( $result );
$rst['InternalID'] = $row['POID'];
$rst['PONumber'] = $row['PONumber'];
$rst['Discount'] = $row['Discount'];
$rst['ExpectedDelivery'] = $row['ExpectedDeliveryDate'];
$rst['PayByDiscAmt'] = $row['PaybyDiscountAmount'];
$rst['PayByDiscPercent'] = $row['PaybyDiscountPercent'];
$rst['PayByDiscDate'] = $row['PaybyDiscountDate'];
$rst['Status'] = $row['Status'];

//08.26.2015 ghh -  now we're going to start grabbing shipping information
$query = "select distinct( BoxID )
			from PurchaseOrderItems a, PurchaseOrderShipped b 
			where b.POItemID=a.POItemID and a.POID=$ar[InternalID]";
if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16590 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16590 - There was a problem locating the order"); //Internal Server Error
	return false;
	}

//now we loop through our boxes and grab related items
$i = 0;
while( $row = $db->sql_fetchrow( $result ) )
	{
	//as we loop through each item, we need to gra
	$query = "select a.POItemID, a.BoxID, a.QtyShipped, a.Cost, b.ItemNumber, 
					b.VendorID, b.Quantity, b.SupersessionID, b.CrossreferenceID,
					c.WarehouseID, c.TrackingNumber, c.VendorInvoiceNumber,
					c.DueDate, c.ShipVendorID, c.ShipDate, c.ShipCost, c.BoxNumber
					from PurchaseOrderShipped a, PurchaseOrderItems b, ShippedBoxes c
					where a.POItemID=b.POItemID and b.POID=$ar[InternalID] and
					a.BoxID=$row[BoxID] order by BoxID, ItemNumber";

	if (!$boxresult = $db->sql_query($query))
		{
		RestLog("Error 16591 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16591 - There was a problem getting list of shipped boxes"); //Internal Server Error
		return false;
		}

	//now loop through boxes and their items and lets build up our box
	//array
	$shipvendorid = 0;
	$boxid = 0;
	$j = 0;
	$items = array();
	while ( $boxrow = $db->sql_fetchrow( $boxresult ) )
		{
		//08.26.2015 ghh -  we only enter the main box section when
		//we actually change boxes since we don't want to repeat this
		if ( $boxid != $boxrow['BoxID'] )
			{
			$boxid = $boxrow['BoxID'];

			$box[$i]['BoxNumber'] 		= $boxrow['BoxNumber'];
			$box[$i]['ShipVendor']		= getShipVendorName( $boxrow['ShipVendorID'] );
			$box[$i]['ShipVendor'] = $shippingvendor;
			$box[$i]['TrackingNumber'] = $boxrow['TrackingNumber'];
			$box[$i]['VendorInvoice'] = $boxrow['VendorInvoice'];
			$box[$i]['DueDate'] = $boxrow['DueDate'];
			$box[$i]['ShipCost'] = $boxrow['ShipCost'];
			$box[$i]['ShipDate'] = $boxrow['ShipDate'];
			}

		//now we build up our list of items
		$items[$j]['VendorID'] = $boxrow['VendorID'];
		$items[$j]['ItemNumber'] = $boxrow['ItemNumber'];
		$items[$j]['QtyShipped'] = $boxrow['QtyShipped'];
		$items[$j]['Cost']			= $boxrow['Cost'];

		//this deals with supersession data and would only be supplied if the supplier
		//elected to ship the super part instead of the original one ordered.
		if ( $boxrow['SupersessionID'] > 0 )
			{
			$query = "select ItemNumber from Items where ItemID=$boxrow[SupersessionID]";
			if (!$superresult = $db->sql_query($query))
				{
				RestLog("Error 16597 in query: $query\n".$db->sql_error());
				RestUtils::sendResponse(500, "16597 - There was a problem getting supersession number"); //Internal Server Error
				return false;
				}

			$superrow = $db->sql_fetchrow( $superresult );
			$items[$j]['SuppersessionNumber'] = $superrow['ItemNumber'];
			}

		//this grabs crossreference information if it was entered and would only be
		//entered if the supplier elected to ship a different vendors part than what 
		//was ordered
		if ( $boxrow['CrossReferenceID'] > 0 )
			{
			$query = "select ItemNumber, VendorID from Items 
							where ItemID=$boxrow[CrossreferenceID]";
			if (!$crossresult = $db->sql_query($query))
				{
				RestLog("Error 16598 in query: $query\n".$db->sql_error());
				RestUtils::sendResponse(500, "16598 - There was a problem getting supersession number"); //Internal Server Error
				return false;
				}

			$crossrow = $db->sql_fetchrow( $crossresult );
			$items[$j]['CrossRefNumber'] = $crossrow['ItemNumber'];
			$items[$j]['CrossRefVendorID'] = $crossrow['VendorID'];
			}

		$j++;
		}

	//08.26.2015 ghh -  now we need to save our items into our box
	$box[$i]['Items'] = $items;

	$i++;
	}

//now that we're done looping through boxes we need to save them as part of the return
//array
$rst['Boxes'] = $box;

##########################################UNITS###############################################################

//now we're going to grab a list of units that may have been shipped so we can send that
//information back as well.
$query = "select * from PurchaseOrderUnits where POID=$ar[InternalID] and
				ShipDate is not null";
if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16599 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16599 - There was a problem getting supersession number"); //Internal Server Error
	return false;
	}

$i = 0;
while ( $row = $db->sql_fetchrow( $result ) )
	{
	$units[$i]['VendorID']			= $row['VendorID'];
	$units[$i]['ModelNumber']		= $row['ModelNumber'];

	//need to lookup up ship vendor name to send back
	$units[$i]['ShipVendor']		= getShipVendorName( $row['ShipVendorID'] );
	$units[$i]['TrackingNumber']	= $row['TrackingNumber'];
	$units[$i]['OrderCode']			= $row['OrderCode'];
	$units[$i]['Year']				= $row['Year'];
	$units[$i]['Colors']				= $row['Colors'];
	$units[$i]['Details']			= $row['Details'];
	$units[$i]['Serial-VIN']		= $row['SerialVIN'];
	$units[$i]['Cost']				= $row['Cost'];
	$units[$i]['ShipCharge']		= $row['ShipCharge'];
	$units[$i]['ShipDate']			= $row['ShipDate'];
	$units[$i]['EstShipDate']		= $row['EstShipDate'];

	$i++;
	}

$rst['Units'] = $units;


###############################BACKORDERS##############################

//lastly we're going to go grab the list of backorders that might exist so that we
//can return them as well.
$query = "select b.*, a.ItemNumber, a.VendorID
				from PurchaseOrderItems a, PurchaseOrderBackOrder b
				where a.POID=$ar[InternalID] and
				a.POItemID=b.POItemID";
if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16602 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16602 - There was a problem getting backorder information"); //Internal Server Error
	return false;
	}

$i = 0;
while ( $row = $db->sql_fetchrow( $result ) )
	{
	$back[$i]['ItemNumber']			= $row['ItemID'];
	$back[$i]['VendorID']			= $row['VendorID'];
	$back[$i]['QtyPending']			= $row['QtyPending'];
	$back[$i]['EstShipDate']		= $row['EstShipDate'];
	$back[$i]['ShipNote']			= $row['ShipNote'];

	$i++;
	}

$rst['Backorders']	= $back;

RestLog("Successful Request\n");
RestUtils::sendResponse(200,json_encode( stripHTML( $rst ) ));
return true;
}


?>
