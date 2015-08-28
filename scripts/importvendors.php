<?
include( "../includes/restcommon.php" );

//replace clistnew.csv with the name of your dealer csv file.  NOTE:
//if you open the file on linux and see ^M anywhere you may need to run
//one of the two commands below to convert the file to unix format
//dos2unix -n filename.csv filenamenew.csv    DOS/Windows
//dos2unix -n -c mac filename.csv filenamenew.csv    OSX
if (( $handle = fopen( 'vendors.csv', 'r' )) !== FALSE ) 
	{
	//this command loops through each line of the file and converts it
	//into an array.  So the first column is 0 second 1...
	while (( $data = fgetcsv( $handle, 1000, ',' )) !== FALSE )
		{
		//determine which column holds the account number for each dealer and replace
		//0 with whatever column that is.  
		$query = "insert into Vendors ( VendorName )
						values( '$data[0]' )";

		$db->sql_query( $query );
		}
	}
?>
