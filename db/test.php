<?
include( 'mysql4.php' );
include( '../config.php' );

//first we need to retrieve the list of all active companies that might have imported data
$db_support = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false );

if ( $db_support->db_connect_id )
	{
	$query	= "select CompanyID, DBName, DBHost, TableVersion from optUserCompany where Active=1 ";
		
	if ( !( $result = $db_support->sql_query( $query ) ) )
		{	
		$lbl_error = $dblang[ "ErrorInSQL" ]."<br>".$db_support->sql_error();
		}
	
	while ( $row = $db_support->sql_fetchrow( $result ))
		{		
		$db = new sql_db( $dbhost, $dbuser, $dbpasswd, $row[ 'DBName' ], true );

		$query = "select * from conLeadSources where LeadName='Other'";
		if ( !( $result2 = $db->sql_query( $query ) ) )
			echo "Problem with: ".$query;

		if ( $db->sql_numrows( $result2 ) == 0 )
			{
			$query = "insert into conLeadSources values ( null, 'Other', 1 )";
			if ( !( $result2 = $db->sql_query( $query ) ) )
				echo "Problem with: ".$query;
			}
		} //end while looping through companies	 
	}
?>
