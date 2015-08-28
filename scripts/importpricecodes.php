<?php
include( "../includes/restcommon.php" );

//if we are going to be importing different pricecodes for each dealer
//then we'll set this to true and tweak the code to grab the data from
//the file you specify.  Otherwise we're going to default all dealers to
//the same percentages unless they are specifically listed in the lookup
$import = false;
if ( $import )
	{
	//replace clistnew.csv with the name of your dealer csv file.  NOTE:
	//if you open the file on linux and see ^M anywhere you may need to run
	//one of the two commands below to convert the file to unix format
	//dos2unix -n filename.csv filenamenew.csv    DOS/Windows
	//dos2unix -n -c mac filename.csv filenamenew.csv    OSX
	if (( $handle = fopen( 'pricecodes.csv', 'r' )) !== FALSE ) 
		{
		//this command loops through each line of the file and converts it
		//into an array.  So the first column is 0 second 1...
		while (( $data = fgetcsv( $handle, 1000, ',' )) !== FALSE )
			{
			//determine which column holds the account number for each dealer and replace
			//0 with whatever column that is.  
			$query = "insert into DealerCredentials ( CreatedDateTime, Active, AccountNumber )
							values( now(), 1, '$data[0]' )";

			$db->sql_query( $query );
			}
		}
	}
else
	{
	//here we are going to be updating the records in the table with a zero
	//dealerid with whatever percentages have been specified.  These will take
	//over if there are no dealerspecific codes available.
	//first lets build our array.  remove or add whatever you need here.
	//NOTE: Specify amount as decimal and + or - to let the system know if you
	//will be discounting off of list or adding to cost in order to get the
	//dealers cost
	$ar[0]['code'] = "A1";
	$ar[0]['Percent'] = "-0.37"; //this would be 37% off retail

	//now we just copy the two and increase 0 to 1,2,3... and do the rest
	$ar[1]['code'] = "A2";
	$ar[1]['Percent'] = "-0.36"; 

	$ar[2]['code'] = "A3";
	$ar[2]['Percent'] = "-0.35"; 

	$ar[3]['code'] = "A4";
	$ar[3]['Percent'] = "-0.34"; 

	$ar[4]['code'] = "A5";
	$ar[4]['Percent'] = "-0.33"; 

	$ar[5]['code'] = "A6";
	$ar[5]['Percent'] = "-0.31"; 

	$ar[6]['code'] = "A7";
	$ar[6]['Percent'] = "-0.30"; 

	$ar[7]['code'] = "A8";
	$ar[7]['Percent'] = "-0.27"; 

	$ar[8]['code'] = "N";
	$ar[8]['Percent'] = "0"; 

	$ar[9]['code'] = "Z";
	$ar[9]['Percent'] = "1"; 


	//now make sure your numbers after ar[ all count from 0 - whatever and that
	//there are no duplicates
	//now we're going to be inserting these records into the table with a 0 dealerid
	//first we need to make sure its not already there as we may need to
	//update it instead
	foreach ( $ar as $a )
		{
		$query = "select DealerID, PriceCode from PriceCodesLink where
					DealerID=0 and PriceCode=$a[code]";

		$result = $db->sql_query( $query );

		//if we don't have a result then we need to insert a new record
		if ( $db->sql_numrows( $result ) == 0 )
			{
			$query = "insert into PriceCodesLink values( 0, '$a[code]','$a[Percent]' )";
			}
		else
			{
			//if we found it in the table already then we need to update the existing
			//record with the current percentage
			$query = "update PriceCodesLink set Discount=$a[Percent] where
							DealerID=0 and PriceCode='$a[code]'";
			}

		$db->sql_query( $query );
		}
	}
?>
