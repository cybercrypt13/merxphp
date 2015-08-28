<?php
include( "../includes/restcommon.php" );

//replace clistnew.csv with the name of your dealer csv file.  NOTE:
//if you open the file on linux and see ^M anywhere you may need to run
//one of the two commands below to convert the file to unix format
//dos2unix -n filename.csv filenamenew.csv    DOS/Windows
//dos2unix -n -c mac filename.csv filenamenew.csv    OSX

//if you are importing then you'll match things up in this section.  Otherwise
//set import = false and move into the else condition below to just
//manually set everything
$import = false;
if ( $import )
	{
	if (( $handle = fopen( 'clistnew.csv', 'r' )) !== FALSE ) 
		{
		//this command loops through each line of the file and converts it
		//into an array.  So the first column is 0 second 1...
		while (( $data = fgetcsv( $handle, 1000, ',' )) !== FALSE )
			{
			//NOTE:  You may need to do some converting here to make sure your names of
			//warehouses match the integer we need for the insert.  ie: You might have
			//warehouse named GA in first column but that matches to 1 in the warehouse
			//table.  In that condition we'd set our ware house to be 1 like the following
			//if ( $data[1] == 'GA' ) $warehouseid = 1;
			//if ( $data[1] == 'TN' ) $warehouseid = 2;

			//if your file contains account numbers then add this query into the mix so that
			//the import script can determine what their specific internal DealerID is so it
			//can properly match things up. uncomment the next 4 lines
			//$query = "select DealerID from DealerCredentials where AccountNumber='$data[0]'";
			//$result = $db->sql_query( $query );
			//$row = $db->sql_fetchrow( $result );
			//$dealerid = $row['DealerID'];

			//determine which column holds the account number for each dealer and replace
			//0 with whatever column that is.  
			$query = "insert into DaysToFullfill ( DealerID, WarehouseID, DaysToArrive )
							values( $dealerid, $warehouseid, $data[3] )";

			$db->sql_query( $query );
			}
		}
	}
else
	{
	//in this section we're just going to loop through all of the dealer records and set their
	//daystoarrive to be 3 for each warehouse that exists;
	$query = "select WarehouseID from Warehouses";
	$result = $db->sql_query( $query );
	while ( $row = $db->sql_fetchrow( $result ) )
		$ar[] = $row;

	//now we're going to grab list of dealers and run through and insert our days
	$query = "select DealerID from DealerCredentials";
	$result = $db->sql_query( $query );

	while ( $row = $db->sql_fetchrow( $result ) )
		{
		foreach ( $ar as $a )
			{
			//this will set delivery to 2 days for each warehouse in the table
			$query = "insert into DaysToFullfill values (
							$row[DealerID], $a[WarehouseID], 2 )";

			$db->sql_query( $query );
			}
		}
	}
?>
