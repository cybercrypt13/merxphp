<?php
include( '../mysql5.php' );
include( '../../includes/restcommon.php' );


//04.22.2013 naj - get list of updates that have not been applied to the current database.
$query = "select max(Version) from tableversion";

if (!$result = $db->sql_query($query)) die( "Error in query $query\n".$db->sql_error()."\n\n");

$row = $db->sql_fetchrow( $result );

if ( $row[ 'Version' ] > 0 )
	$tableversion = $row[ 'Version' ];
else
	$tableversion = 0;

$keepgoing = true;
$tableversion++;
while( $keepgoing )
	{
	if ( is_file( $tableversion.".sql" ) )
		{
		//now we're going to source the sql file in question which should execute all the code
		//contained in it.
		$command = "mysql -u $dbuser --password=$dbpasswd -h ".$dbhost.' '.$dbname. ' < ' .$tableversion. '.sql';
		$msg = "\nPlease wait while updates are performed for ".$$tableversion;
		shell_exec( $command ); 

		//now we update the tableversion table with this version update so we don't ever 
		//run it again
		$query = "insert into tableversion values ( null, now(), $tableversion )";
		$db->sql_query( $query );

		$tableversion++;
		}
	else
		break;

	}
echo "\n";
?>
