<?php
//08.20.2015 ghh -  this function receives an order request and processes it into the
//appropriate tables
function sendOrder($vars, $responsetype)
{
global $db;
$ar = json_decode( $vars['Data']['Data'], true, 5 );
if ( empty( $ar )  || !isset($ar[ 'PONumber' ]) || !isset( $ar[ 'Status' ] ) ||
	( empty( $ar['Items'] ) && empty( $ar['Units'] ) ) ) 
	{
	RestLog("16521 - Insufficient data provided for creating order \n".print_r($vars,true)."\n");
	RestUtils::sendResponse(400, "16521 - Insufficient data provided" ); //Internal Server Error
	return false;
	}

//08.21.2015 ghh -  before we get started we need to see if the current dealer
//already has a PO in the system matching what they are now sending.  If so we're
//going to be updating it if its pending or if it hasn't been pulled by the primary
//vendor system yet.
$query = "select POID, Status from PurchaseOrders where PONumber='$ar[PONumber]' and
				DealerID=$vars[DealerID]";

if (!$result = $db->sql_query($query))
	{
	RestLog("Error 16522 in query: $query\n".$db->sql_error());
	RestUtils::sendResponse(500, "16522 - There was a problem attempting to locate the PO"); //Internal Server Error
	return false;
	}

//if we have no purchase order at all then we're going to be inserting a new one
if ( $db->sql_numrows( $result ) == 0 )
	{
	$shiptofields 	= '';
	$shiptovals 	= '';
	if ( $ar['ShipToAddress1'] != '' )
		{
		$shiptofields = "ShipToFirstName, ShipToLastName, ShipToCompany,
								ShipToAddress1, ShipToAddress2, ShipToCity, ShipToState,
								ShipToZip, ShipToCountry, ShipToPhone, ShipToEmail,";

		if ( $ar['ShipToFirstName'] == '' ) $shiptovals = "'',"; 
		else $shiptovals = "'$ar[ShipToFirstName]',";
		if ( $ar['ShipToLastName'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToLastName]',";
		if ( $ar['ShipToCompany'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToCompany]',";
		if ( $ar['ShipToAddress1'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToAddress1]',";
		if ( $ar['ShipToAddress2'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToAddress2]',";
		if ( $ar['ShipToCity'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToCity]',";
		if ( $ar['ShipToState'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToState]',";
		if ( $ar['ShipToZip'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToZip]',";
		if ( $ar['ShipToCountry'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToCountry]',";
		if ( $ar['ShipToPhone'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToPhone]',";
		if ( $ar['ShipToEmail'] == '' ) $shiptovals .= "'',"; 
		else $shiptovals .= "'$ar[ShipToEmail]',";
		if ( $ar['PaymentMethod'] == '' ) $shiptovals .= "1,"; 
		else $shiptovals .= "'$ar[PaymentMethod]',";
		if ( $ar['ShipMethod'] == '' ) $shiptovals .= "1,"; 
		else $shiptovals .= "'$ar[ShipMethod]',";
		}

	$query = "insert into PurchaseOrders (Status, DealerID, BSVKeyID, PONumber,
				DateCreated, $shiptofields LastFour,OrderType) values 
				( $ar[Status], $vars[DealerID], $vars[BSVKeyID], '$ar[PONumber]', now(),
				$shiptovals '$ar[LastFour]',$ar[OrderType] )
				";

	}
else
	{
	//if we do have a purchase order we need to determine if its ok to update it or not
	//and return error if its not.
	$row = $db->sql_fetchrow( $result );

	$poid = $row['POID'];

	//08.21.2015 ghh -  if the status is greater than 2 it means the supplier has already
	//started pulling the order and we can no longer update it.  In this case we're going 
	//to die and return error
	if ( $row['Status'] > 2 )
		{
		RestLog("Purchase has already been pulled by supplier $ar[PONumber]\n");
		RestUtils::sendResponse(409, "Order has already been pulled by supplier"); //Internal Server Error
		return false;
		}

	//if we reach here then it must be ok to update the purchase order data so will build the
	//query here
	$query = "update PurchaseOrders set ";

	if ( $ar['ShipToAddress1'] != '' )
		{
		if ( $ar['ShipToFirstName'] != '' ) $query1 .= "ShipToFirstName='$ar[ShipToFirstName]',"; 
		if ( $ar['ShipToLastName'] != '' ) $query1 .= "ShipToLastName='$ar[ShipToLastName]',"; 
		if ( $ar['ShipToCompany'] != '' ) $query1 .= "ShipToCompany='$ar[ShipToCompany]',"; 
		if ( $ar['ShipToAddress1'] != '' ) $query1 .= "ShipToAddress1='$ar[ShipToAddress1]',"; 
		if ( $ar['ShipToAddress2'] != '' ) $query1 .= "ShipToAddress2='$ar[ShipToAddress2]',"; 
		if ( $ar['ShipToCity'] != '' ) $query1 .= "ShipToCity='$ar[ShipToCity]',"; 
		if ( $ar['ShipToState'] != '' ) $query1 .= "ShipToState='$ar[ShipToState]',"; 
		if ( $ar['ShipToZip'] != '' ) $query1 .= "ShipToZip='$ar[ShipToZip]',"; 
		if ( $ar['ShipToCountry'] != '' ) $query1 .= "ShipToCountry='$ar[ShipToCountry]',"; 
		if ( $ar['ShipToPhone'] != '' ) $query1 .= "ShipToPhone='$ar[ShipToPhone]',"; 
		if ( $ar['ShipToEmail'] != '' ) $query1 .= "ShipToEmail='$ar[ShipToEmail]',"; 
		}

	if ( $ar['PaymentMethod'] != '' ) $query1 .= "PaymentMethod=$ar[PaymentMethod],"; 
	if ( $ar['LastFour'] != '' ) $query1 .= "LastFour='$ar[LastFour]',"; 
	if ( $ar['ShipMethod'] != '' ) $query1 .= "ShipMethod='$ar[ShipMethod]',"; 
	
	//if we are actually updating the PO then we're also going ot update the
	//poreceiveddate
	if ( $query1 != '' )
		{
		$query1 .= " DateLastModified=now() ";
	
		$query .= "$query1 where DealerID=$vars[DealerID] and PONumber='$ar[PONumber]'";
		}
	else
		$query = '';
	}


//08.21.2015 ghh -  now we execute either of the two queries above to update or insert
//the purchase order itself.
if ( $query != '' )
	if (!$result = $db->sql_query($query))
		{
		RestLog("Error 16523 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500,"16523 - There was a problem attempting to insert/update the PO");
		return false;
		}

//if we don't already have a poid then we must have done an insert so we'll grab it now
if ( !$poid > 0 )
	$poid = $db->sql_nextid( $result );


####################################################PARTS###########################################
//now that the purchase order has been updated we'll next start taking a look
//at the items and units arrays
//08.21.2015 rch -  we need to loop through each item that is passed in and evaluate whether or not
//we are inserting the po or updating the po
$i = 0;
foreach ( $ar['Items'] as $value => $key)
	{
	//08.21.2015 rch -  first we need to see if the item is already on the order
	$query = "select POItemID, Quantity 
					from PurchaseOrderItems
					where POID='$poid' and ItemNumber = '$key[ItemNumber]'
					and VendorID = '$key[VendorID]'";

	if (!$result = $db->sql_query($query))
		{
		RestLog("Error 16524 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16524 - There was an error locating purchase order items");
		return false;
		}
	




	//08.21.2015 rch -  we want to make sure that we have a partnumber and vendorid 
	//before attempting to insert.
	if ( $key['ItemNumber'] != '' && $key['VendorID'] != '' )
		{
		//08.21.2015 ghh -  before we bother inserting the item we're going to first grab some
		//details from items so we can build up our response.
		$query = "select ItemID, NLA, CloseOut, PriceCode, Category, SupersessionID, 
					MSRP, Cost
					from
					Items where ItemNumber='$key[ItemNumber]' and VendorID=$key[VendorID]";
		if (!$itemresult = $db->sql_query($query))
			{
			RestLog("Error 16526 in query: $query\n".$db->sql_error());
			RestUtils::sendResponse(500, "16526 - There was an error locating the order item");
			return false;
			}
		$itemrow = $db->sql_fetchrow( $itemresult );

		if ( $db->sql_numrows( $itemresult ) == 0 )
			{
			RestLog("Error 16545 The ItemNumber or VendorID you sent are not valid");
			RestUtils::sendResponse(500, "16545 - The Item Number or VendorID passed are invalid");
			return false;
			}

		//now lets see if we can calculate the cost for the current dealer
		$cost = getItemCost( $itemrow['ItemID'], $vars['DealerID'], $itemrow['PriceCode'],
						$itemrow['Cost'], $itemrow['MSRP']);
		}
	else
		{
		RestLog("$row[PONumber] is missing a vendor id\n");
		RestUtils::sendResponse(409, "$key[ItemNumber] is missing a vendor id");
		return false;
		}


	//08.21.2015 rch -  if we enter here,the partnumber does not exist on the po
	if ( $db->sql_numrows( $result ) == 0 )
		{
		//08.21.2015 ghh -  make sure the non required fields have a value
		if ( $key['FillStatus'] == '' ) $key['FillStatus'] = 0;
		if ( $key['OrderType'] == '' ) $key['OrderType'] = 2;
		$query = "insert into PurchaseOrderItems (POItemID,POID,ItemNumber,Quantity,
					 FillStatus,ItemID,VendorID) values ( '','$poid','$key[ItemNumber]',$key[Qty],
					 $key[FillStatus],$itemrow[ItemID], $key[VendorID])";
		}
	else
		{
		//08.21.2015 rch -  if we enter here,the item is already in the table and just needs to be 
		//updated
		$row = $db->sql_fetchrow( $result );

		//08.21.2015 rch -  here we are updating the purchase order items table
		$query = "update PurchaseOrderItems set ";

		if ( $key['Qty'] != '' ) $query1 = "Quantity=$key[Qty]";

		if ( $query1 != '' )
			$query .= "$query1 where POItemID=$row[POItemID]";
		else
			$query = '';
		}

	//08.21.2015 rch -  now we need to execute the query
	if ( $query != '' )
		{
		if (!$result = $db->sql_query($query))
			{
			RestLog("Error 16525 in query: $query\n".$db->sql_error());
			RestUtils::sendResponse(500,"16525 - There was a problem attempting to insert/update the PO"); //Internal Server Error
			return false;
			}

		//08.24.2015 ghh - update the PO with the current time for last modified date
		$query = "update PurchaseOrders set DateLastModified=now() where POID = $poid";
		if (!$result = $db->sql_query($query))
			{
			RestLog("Error 16548 in query: $query\n".$db->sql_error());
			RestUtils::sendResponse(500,"16548 - There was a problem updating the last modified date"); //Internal Server Error
			return false;
			}
		}

	//08.21.2015 ghh -  now we need to figure out what our current inventory is
	//minus any items already on orders so that we pass back a fairly reasonable
	//backorder response
	$query = "select (ifnull(sum(p1.Quantity), 0) - ifnull(sum(p2.QtyShipped),0)) as qty  
					from PurchaseOrderItems p1 
					left outer join PurchaseOrderShipped p2 on p1.POItemID=p2.POItemID 
					where ItemID=$itemrow[ItemID]";

	if (!$qtyresult = $db->sql_query($query))
		{
		RestLog("Error 16529 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16529 - There was an error getting total on order");
		return false;
		}
	$qtyrow = $db->sql_fetchrow( $qtyresult );
	$qtyonorder = $qtyrow[ 'qty' ];

	$query = "select sum( Qty ) as Qty from ItemStock where ItemID=$itemrow[ItemID]";
	if (!$qtyresult = $db->sql_query($query))
		{
		RestLog("Error 16530 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16530 - There was an error getting total stock qty");
		return false;
		}
	$qtyrow = $db->sql_fetchrow( $qtyresult );
	$qtyinstock = $qtyrow[ 'Qty' ];


	//08.21.2015 ghh -  now we have all of our return information and have updated or
	//inserted into the items list for the purchase order so we only need to build our
	//response now.
	$items[$i]['VendorID'] 		= $key['VendorID'];
	$items[$i]['ItemNumber']	= $key['ItemNumber'];
	$items[$i]['Superseded']	= $itemrow['SupersessionID'];
	$items[$i]['NLA']				= $itemrow['NLA'];
	$items[$i]['Closeout']		= $itemrow['CloseOut'];
	$items[$i]['MSRP']			= $itemrow['MSRP'];
	$items[$i]['Cost']			= $cost;

	if ( $qtyinstock - $qtyonorder < 0 )
		$items[$i]['BackorderQty']	= abs($qtyinstock - $qtyonorder);
	else
		$items[$i]['BackorderQty']	= 0;

	$i++;
	}

$rst['PONumber'] 		= $ar['PONumber'];
$rst['InternalID']	= $poid;
$rst['DealerKey']		= $vars['DealerKey'];
$rst['Items']			= $items;




########################################UNITS###################################
//08.25.2015 ghh -  this section deals with unit purchase orders
$i = 0;
foreach ( $ar['Units'] as $value => $key)
	{
	$key['ModelNumberNoFormat'] = preg_replace('/[^a-zA-Z0-9]/', '', $key['ModelNumber'] ); //strip formatting.

	//08.21.2015 rch -  first we need to see if the item is already on the order
	$query = "select POUnitID
					from PurchaseOrderUnits
					where POID='$poid' and ModelNumber = '$key[ModelNumber]'
					and VendorID = '$key[VendorID]'";

	if (!$result = $db->sql_query($query))
		{
		RestLog("Error 16549 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16549 - There was an error locating purchase order unit");
		return false;
		}

	//08.21.2015 rch -  we want to make sure that we have a partnumber and vendorid 
	//before attempting to insert.
	if ( $key['ModelNumberNoFormat'] != '' && $key['VendorID'] != '' )
		{
		if ( isset( $key['Year'] ) )
			$year = $key['Year'];
		else
			$year = 0;

		//08.21.2015 ghh -  before we bother inserting the item we're going to first grab some
		//details from items so we can build up our response.
		$query = "select ModelID, NLA, CloseOut, Cost, OrderCode 
					MSRP from UnitModel 
					where ModelNumberNoFormat='$key[ModelNumberNoFormat]' and VendorID=$key[VendorID]
					and Year=$year";
		if (!$unitresult = $db->sql_query($query))
			{
			RestLog("Error 16560 in query: $query\n".$db->sql_error());
			RestUtils::sendResponse(500, "16560 - There was an error locating the order model");
			return false;
			}
		$unitrow = $db->sql_fetchrow( $unitresult );

		if ( $db->sql_numrows( $unitresult ) == 0 )
			{
			RestLog("Error 16561 The Unit Model you sent is not valid");
			RestUtils::sendResponse(500, "16561 - The Model Number or VendorID passed are invalid");
			return false;
			}

		//now lets see if we can calculate the cost for the current dealer
		$cost = getUnitCost( $unitrow['ModelID'], $vars['DealerID'],
						$unitrow['Cost']);
		}
	else
		{
		RestLog("Error 16563 $row[PONumber] is missing a vendor id\n");
		RestUtils::sendResponse(409, "Error 16563 $key[ModelNumber] is missing a vendor id");
		return false;
		}


	//08.25.2015 ghh -  if we have less line items on the PO than the qty we need then
	//we're going to insert a few more rows until they match.  
	if ( $db->sql_numrows( $result ) < $key['Qty']  )
		{
		for ( $i = 0; $i < $key['Qty'] - $db->sql_numrows($result); $i++ )
			{
			$query = "insert into PurchaseOrderUnits (POID,ModelNumber,
					 ModelID,OrderCode,Year, Colors, VendorID, Cost) values 
					 ( '$poid','$key[ModelNumber]',$unitrow[ModelID],'$unitrow[OrderCode]',
					 $year,'$key[Colors]', $key[VendorID], '$cost')";

			if (!$tmpresult = $db->sql_query($query))
				{
				RestLog("Error 16564 in query: $query\n".$db->sql_error());
				RestUtils::sendResponse(500, "16564 - There was an error trying to add the unit to the order");
				return false;
				}
			}

		//08.25.2015 ghh - update the PO with the current time for last modified date
		$query = "update PurchaseOrders set DateLastModified=now() where POID = $poid";
		if (!$result = $db->sql_query($query))
			{
			RestLog("Error 16565 in query: $query\n".$db->sql_error());
			RestUtils::sendResponse(500,"16565 - There was a problem updating the last modified date"); //Internal Server Error
			return false;
			}
		}
	else
		if ( $db->sql_numrows( $result ) > $key['Qty']  )
			{
			$qtytoremove = $db->sql_numrows($result) - $key['Qty'];

			$query = "select POUnitID from PurchaseOrderUnits where POID=$poid
						and ModelID=$unitrow[ModelID] limit $qtytoremove";
			if (!$tmpresult = $db->sql_query($query))
				{
				RestLog("Error 16566 in query: $query\n".$db->sql_error());
				RestUtils::sendResponse(500,"16566 - There was a problem deleting changed models"); //Internal Server Error
				return false;
				}

			while ( $tmprow = $db->sql_fetchrow( $tmpresult ) )
				{
				$query = "delete from PurchaseOrderUnits where POUnitID=$tmprow[POUnitID]";
				if (!$tmp2result = $db->sql_query($query))
					{
					RestLog("Error 16567 in query: $query\n".$db->sql_error());
					RestUtils::sendResponse(500,"16567 - There was a problem deleting changed models"); //Internal Server Error
					return false;
					}

				}

			//08.25.2015 ghh - update the PO with the current time for last modified date
			$query = "update PurchaseOrders set DateLastModified=now() where POID = $poid";
			if (!$result = $db->sql_query($query))
				{
				RestLog("Error 16568 in query: $query\n".$db->sql_error());
				RestUtils::sendResponse(500,"16568 - There was a problem updating the last modified date"); //Internal Server Error
				return false;
				}
			}



	//08.25.2015 ghh -  first lets grab total qty for the current model
	$query = "select sum(Qty) as Qty from UnitModelStock where ModelID=$unitrow[ModelID]";
	if (!$qtyresult = $db->sql_query($query))
		{
		RestLog("Error 16570 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16570 - There was an error getting total instock");
		return false;
		}
	$tmprow = $db->sql_fetchrow( $qtyresult );
	$stockqty = $tmprow[ 'Qty' ];

	$query = "select count(POUnitID) as Qty from PurchaseOrderUnits 
				where ModelID=$unitrow[ModelID] and SerialVin is null";
	if (!$qtyresult = $db->sql_query($query))
		{
		RestLog("Error 16571 in query: $query\n".$db->sql_error());
		RestUtils::sendResponse(500, "16571 - There was an error getting total instock");
		return false;
		}
	$tmprow = $db->sql_fetchrow( $qtyresult );
	$orderqty = $tmprow[ 'Qty' ];


	//08.21.2015 ghh -  now we have all of our return information and have updated or
	//inserted into the items list for the purchase order so we only need to build our
	//response now.
	$units[$i]['VendorID'] 		= $key['VendorID'];
	$units[$i]['ModelNumber']	= $key['ModelNumber'];
	$units[$i]['NLA']				= $unitrow['NLA'];
	$units[$i]['Closeout']		= $unitrow['CloseOut'];
	$units[$i]['MSRP']			= $unitrow['MSRP'];
	$units[$i]['Cost']			= $cost;

	if ( $stockqty - $onorderqty < 0 )
		$units[$i]['BackorderQty']	= abs($stockqty - $onorderqty);
	else
		$units[$i]['BackorderQty']	= 0;

	$i++;
	}

$rst['Units']			= $units;

RestLog("Successful Request\n");
//08.10.2012 naj - return code 200 OK.
RestUtils::sendResponse(200,json_encode( stripHTML( $rst ) ));
return true;
}




?>
