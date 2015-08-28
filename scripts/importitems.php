<?php
include( "../includes/restcommon.php" );

//query your database after putting in vendors to grab the vendorid
//of this particular vendor before you import.
$vendorid = 2;

//replace clistnew.csv with the name of your dealer csv file.  NOTE:
//if you open the file on linux and see ^M anywhere you may need to run
//one of the two commands below to convert the file to unix format
//dos2unix -n filename.csv filenamenew.csv    DOS/Windows
//dos2unix -n -c mac filename.csv filenamenew.csv    OSX
if (( $handle = fopen( 'items.csv', 'r' )) !== FALSE ) 
	{
	//this command loops through each line of the file and converts it
	//into an array.  So the first column is 0 second 1...
	$i = 0;
	while (( $data = fgetcsv( $handle, 1000, ',' )) !== FALSE )
		{
		//determine which column holds the account number for each dealer and replace
		//0 with whatever column that is.  
		$itemnumber = addslashes( $data[0] );
		$description = addslashes( $data[5] );
		$list = $data[10];
		$pricecode = $data[9];
		$supersession = addslashes( $data[2] );
		$weight = $data[4];

		$test = true;
		if ( $test )
			{
			echo "Item=$itemnumber Desc=$description  List=$list Code=$pricecode Super=$supersession Weight=$weight\n";
			$i++;
			if ( $i > 20 )
				exit;
			}


		//now we're going to see if there is a supersession number and if so
		//we need to make sure its there so we can link up to it.
		if ( strlen( $supersession ) > 1 )
			{
			$query = "select ItemID from Items where ItemNumber='$supersession'
							and VendorID=$vendorid";

			$result = $db->sql_query( $query );
			
			if ( $db->sql_numrows( $result ) == 0 )
				{
				//first we need to insert the new supersession number so we can 
				//link to it and the below process will update it when it finally
				//hits.
				$query = "insert into Items (ItemNumber, VendorID) values
							( '$supersession', $vendorid )";
				$result = $db->sql_query( $query );
				$supersessionid = $db->sql_nextid( $result );
				}
			else
				{
				$row = $db->sql_fetchrow( $result );
				$supersessionid = $row['ItemID'];
				}
			}
		else
			$supersessionid = 0;


		//now we need to see if we have already got the item in our Items
		//list because if we do we need to just update it instead of creating
		//a new record.
		$query = "select ItemID from Items where ItemNumber='$itemnumber' and
					VendorID=$vendorid";
		$result = $db->sql_query( $query );

		//if we did not find it then insert it
		if ( $db->sql_numrows( $result ) == 0 )
			{
			//add whatever extra fields you may have in the price file that are 
			//not listed here
			$query = "insert into Items ( ItemNumber, VendorID, Description, 
								PriceCode, Retail, Weight ) values (
								'$itemnumber', $vendorid, '$description',
								'$pricecode', '$list',$supersessionid, '$weight' )";
			}
		else
			{
			//otherwise we're going to update the existing record.
			$row = $db->sql_fetchrow( $result );
			//here we're updating the record that already exists
			$query = "update Items set Description='$description',
						PriceCode='$pricecode', Weight='$weight',
						SupersessionID=$supersessionid where
						ItemID=$row[ItemID]";

			}

		$db->sql_query( $query );
		}
	}
?>
