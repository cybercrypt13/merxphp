<?
global $language;
//02.14.2013 naj - somewhere this file is included and $language does not exist.
//This causes warnings in the error log. As a fix I am checking the language var
//and setting it to one if it has no value.
if (empty($language)) $language = 1;

include_once($RootPath."/language/".$language."/includes/general_functions.inc");

//11.5.2010 ghh added 3rd parameter to skip db logging so it just echos
//02.02.2012 jss - adding 4th param to NOT echo the error to the user.  have a situation where need to see if something is still happening
//but don't want to alert the user
function LogError( $errorid, $val = 'General Error', $logtodb = true, $echoerror=true )
{
global $UserID, $Company_ID;
global $db;
global $dbhost, $dbuser, $dbpasswd, $supportdb;
global $RootPath;


//09.07.2011 ghh - added to make it hide sql errors from this point forward and show generic error
//or error from our internal tables.
$erow = array();

//02.22.2012 ghh - added duplicate entry to trap key violations too
//09.27.2012 ghh - the paren was off for unknown column and wasn't working.  fixed
//09.19.2013 ghh - ( added BIGINT as an exclusion
//12.23.2013 ghh - added ambiguous to list of error phrases
if ( strpos( $val, 'SQL' ) === false 
		&& !strpos( $val, 'Unknown column' ) 
		&& !strpos( $val, 'Duplicate entry' )
		&& !strpos( $val, 'BIGINT' )  
		&& !strpos( $val, 'ambiguous' )  
		&& !strpos( $val, '1146 - Table' ) )
	{
	//09.27.2012 ghh - modified function to not echo until bottom so we just save
	//message here
	//if ( $echoerror )
	$error = "Error: <span class=errortxt><b>".$errorid.'</b><br> '.$val.'</span><br><br><hr>';
	}
else
	{
	//TKS 05.23.2014 #52732 grab dealership name for email
	//##############
	//##############
	$db_support = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true );
	if ( $db_support->db_connect_id )
		{
		$query = "select CompanyName from optUserCompany where CompanyID = ".$Company_ID;
		if ( !$result = $db_support->sql_query( $query ) )
			{
			echo "Error: , Problem retrieving company name.<br>"; 
			exit;
			}
		$company_row = $db_support->sql_fetchrow( $result );
		$CompanyName = stripslashes( $company_row[ "CompanyName" ] );
		}

	//04.14.2015 naj - added code to lookup the location of nizex_nizex
	$query = "select DBHost from optUserCompany where DBName = 'nizex_nizex'";
	if ( !$result = $db_support->sql_query( $query ) )
		{
		echo "Error: , Problem retrieving database host.<br>"; 
		exit;
		}
	$row = $db_support->sql_fetchrow($result);
	$nizex_host = $row["DBHost"];

	$db_support->sql_close();
	//##############
	//##############

	//09.07.2011 ghh - now that we know we're dealing with some sort of sql error, the next step is to lookup
	//the error code and provide details of it to the user if available.
	//04.12.2012 naj - Changed this to be hard coded to the database server containing nizex
	$db_support = new sql_db( $nizex_host, $dbuser, $dbpasswd, 'nizex_nizex', false, true );
	if ( $db_support->db_connect_id )
		{
		//TKS 05.23.2014 #52732 including the new internal notes field so we can put it into the body
		//of the email to help our people out
		$query	= "select Title, Detail, InternalNotes from proErrorCodes where ErrorCode=".intval( $errorid );
			
		if ( !$result = $db_support->sql_query( $query ) )
			{	
			echo "Error: 8344, Problem retrieving Error information.<br>"; 
			exit;
			}

		$erow = $db_support->sql_fetchrow( $result );
		//TKS 05.23.2014 #52732 added error code tracking. Grab any and all users that may be tracking this error and email them
		//##############
		//##############
		$query = "select UserID from logErrorUserLink where ErrorCode = ".intval( $errorid );
		if ( !$result = $db_support->sql_query( $query ) )
			{
			echo "Error: 14711, Problem retrieving Error user link information.<br>"; 
			exit;
			}

		//at this point we have the company name that got the error,
		//the error title and detail and errorcode as well as all the users linked to this error
		//now we email them to let them know.
		if ( $db_support->sql_numrows( $result ) > 0 )
			{
			include_once($RootPath."/email/email_functions.php");
			//TKS 06.17.2014 #53806 added userid and companyid to body of email
			$from 	= PrimaryEmail( 0, $UserID );//email of customer that got the error. This function is still using $db for their local table
			$Name 	= UserRealName( $UserID, 0, true );//name of customer that got the error. This function is still using $db for their local table
			$subject = 'An error code you are following has been executed.';
			$body		= "<b>Error code :</b> ".$errorid."<br>
							<b>Company :</b> ".$CompanyName." ( ".$Company_ID." )<br>
							<b>Name :</b> ".$Name." ( ".$UserID." )<br>
							<b>Title :</b> ".$erow[ "Title" ]."<br>
							<b>General Info :</b><br> ".$erow[ "Detail" ]."<br>";

			//TKS 06.17.2014 #53806 GHH wants the details of the error in the email. Before we send it out, we need to make sure that 
			//it does not contain creditcard info so we exclude any queries that contain conCreditCardInfo or actTempPayments
			if ( substr_count( 'conCreditCardInfo', $val ) == 0 && substr_count( 'actTempPayment', $val ) == 0 )
				$body .= "<b>Details :</b><br>".$val."<br>"; 

			if ( !empty( $erow[ "InternalNotes" ] ) )
				$body .= "<br><b>Internal Notes :</b><br> ".$erow[ "InternalNotes" ];

			while( $row = $db_support->sql_fetchrow( $result ) )
				{
				$query = "select EmailAddress from conAdditionalContacts where UserID = ".$row[ "UserID" ];
				if ( !$result2 = $db_support->sql_query( $query ) )
					{
					echo "Error: 14712, Problem retrieving Error user link email address.<br>"; 
					exit;
					}
				$row2 = $db_support->sql_fetchrow( $result2 ); 
				$to 	= $row2[ "EmailAddress" ];
				if ( validEmail( $to ) && validEmail( $from ) )
					{
					//email our staff
					BackEndEmail( $subject, $body, '', false, $from, $to, 0, 1, 0 ); 
					}
				}
			}
		//##############
		//##############
		}
		
	$db_support->sql_close();

	//09.27.2012 ghh - modified function to not echo until bottom so we just save
	//message here
	//if ( $echoerror )
	//12.23.2013 ghh - changing format of the error message to make things clearer
	//$error = "Error: <span class=errortxt><b>(".$errorid.")</b> <br>The information provided was not sufficient for Lizzy to perform this task. 
	//		<br>Additional Detail:</span><hr><p><b>".$erow[ 'Title' ]."<br>".$erow[ 'Detail' ]."</b><hr><p \>";

	$error = "Error: <span class=errortxt><b>(".$errorid.")</b>  
			</span><br><h1>".$erow[ 'Detail' ]."</h1><hr><p \>";
	}

//09.07.2011 ghh - lastly we're going to log the error to a new support table that our support staff
//can lookup and review and we're also going to alert the proper people that its been done so that
//we can review it.
$db_support = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true );
if ( $db_support->db_connect_id )
	{
	//04.09.2014 naj - changed mysql_real_escape_string to prepareNote.
	$query = "insert into logErrors values( null, ".intval( $errorid ).",'".prepareNote( $val )."', ".$UserID.",".$Company_ID.",utc_timestamp )";
	if ( !$result = $db_support->sql_query( $query ) )
		{	
//03.19.2014 ghh - ( removed error message here as its sorta pointless.  
//		echo "Error: 8345, Problem inserting Error information.<br>"; 
//		error_log("Error inserting into logErrors\n$query");
		}
	}

//09.27.2012 ghh - added this to echo error if we're suppose to and then return error no matter what
if ( $echoerror )
	echo $error;

return $error;
}



//this function deals with determining what access any users has within the nizex system
//function isAuth( $UserPriv, $ModulePriv )
//03.19.2014 ghh - added = 0 to moduleid to allow pushing thorugh with no module because we
//want to fix the control so that it can work with only a menuid since that number is unique
//enough to run off of
function isAuth( $moduleid = 0, $masterid = 0, $menuid = 0, $sectionid = 0 )
{
global $UserID;
global $db;

//first we determine if we can get a userid
if ( empty( $UserID ) ) 
	return false;
//return true on com module since all users should have access
//02.02.2012 ghh - added these if statements to allow certain dashboard menus to always show but others require admin
//access to see.
if ( $moduleid == 8 && $masterid == 109 && ( $menuid == 518 || $menuid == 519 ) 
		&& $secondid == 0 ) 
	return true;
if ( $moduleid == 8 && $masterid == 109 && $menuid == 0 ) 
	return true;
if ( $moduleid == 8 && $masterid == 0 ) 
	return true;

//10.04.2013 ghh - added to return true for the new report menus so that its not necessary to give
//people access to the menus since the reports themselves are protected using existing security
if ( $moduleid == 19 && $menuid == 0 )
	return true;

if ( $menuid > 686 && $menuid < 702 )
	return true;

//05.21.2010 ghh added this to prevent customers from seeing email module as they
//do not have access to it anyway.
if ( $moduleid == 18 && isEmployee( $UserID ) )
	return true;

if ( $db->db_connect_id && ( $moduleid > 0 || $menuid > 0 ) )
	{
	$query	= "select RoleID from secUserRoles where UserID=" .$UserID;
		
	if ( !$result = $db->sql_query( $query ) )
		LogError( 1388, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	
	$row	= $db->sql_fetchrow( $result );

	if ( $db->sql_numrows( $result ) == 0 )
		return false;

	//if users is admin then we don't bother checking anything else
	if ( $row[ 'RoleID' ] == 13 )
		return true;

	//03.19.2014 ghh - added because if we have a menu id there is no real need in 
	//looking any further since its a distinct id for every menu in the system.  This
	//is being added to deal with doing a double check in common file to make sure they
	//have access to something, even if they have tried to bypass the system in some way.
	//also added the else block around the old code as we only execute it if this isn't
	//done
	if ( $menuid > 0 && $sectionid == 0 )
		{
		$query = "select ModuleID from secRoleSecurity where MenuID=" .$menuid;
		}
	else
		{
		//first we need to figure out what we've been passed in to build our query up
		$query = "select ModuleID from secRoleSecurity where ModuleID=" .$moduleid;

		if ( $masterid > 0 )
			{
			$query .= " and MasterID=" .$masterid;

			if ( $menuid > 0 )
				{
				$query .= " and MenuID=" .$menuid;

				if ( $sectionid > 0 )
					$query .= " and SectionID=" .$sectionid;
				}
			}
		}

	$query .= " and RoleID=" .$row[ 'RoleID' ]. " limit 1";

	if ( !$result = $db->sql_query( $query ) )
		LogError( 1389, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	
	if ( $db->sql_numrows( $result ) == 1 )
		return true;
	else
		return false;
	}
else
	return false;

//if ( $ret == 1 )
if ( $db->sql_numrows( $result ) == 1 )
	return true;
else
	return false;
}



//this function takes all the users and gives you a list of the type you need
//pass in Tester or PDM for usertype and it will show all checked in conContactInfo
//for fields ShowInDeveloperList or ShowInTesterList. Second parameter is order to show name
//Last, First or First Last. Defaults to last name then first
//See switch statement inside this function for all possible usertypes being used at present
//TKS 04.23.2015 added $return. Pass in 'array' and it will return multidimensional array
//pass in 'query' and it will return comma separated ids you can plug into a query for 
//a not in or in clause
//TKS 06.25.2015 #69916 adding new type for job classification and we need to limit
//our list based on who is set to that classification. Adding a new $id parameter that can
//be used by other areas in lizzy based on what ever you need to limit by in my case it will be classificationid if Mechanic is passed in 
//as usertype
function getUserList( $usertype = '', $showname = 'first', $return='array', $id=0 )
{
global $db;
global $dbname;

if ( $db->db_connect_id )
	{
	$select	= "select Distinct conAdditionalContacts.UserID, conAdditionalContacts.ContactID, 
					conAdditionalContacts.FirstName, conAdditionalContacts.LastName, conContactInfo.BusinessName ";
	
	$from		= " FROM conAdditionalContacts, conContactInfo ";

	if ( $usertype == 'Employee' || $usertype == 'EmployeeAll' )
		$from .= ", conContactType ";
	
	//looking for list of managers out of optDepartmentManagers table
	if ( $usertype == 'Managers' )
		$from		.= " ,optDepartmentManagers ";

	//TKS 06.25.2015 #69916
	if ( $usertype == 'Mechanic' && $id > 0 )
		$from .= ", optMechanicClassification ";


	$where	= " WHERE conAdditionalContacts.ContactID = conContactInfo.ContactID  ";

	//1.11.2010 ghh removed to allow any user in the list to show in the drop downs.  The problem was if I had 2 accounts able
	//to login on the same user it caused some drop downs to display incorrect values because it only defaulted to grabbing the primary
	//conAdditionalContacts.PrimaryContact = 1 and 

	//only show list of employees
	switch ( $usertype )
		{
		case 'Employee':
			$where	.= " and conAdditionalContacts.Login = 1  and conAdditionalContacts.UserID > 0 and
				conAdditionalContacts.PrimaryContact = 1 and conAdditionalContacts.UserID is not NULL and conContactType.ContactTypeID = 1 and conContactType.ContactID = conContactInfo.ContactID and conContactInfo.Active = 1";
			break;
		//TKS 7-15-10 added EmployeeAll to include all employees active or inactive in necessary lists
		case 'EmployeeAll':
			$where	.= " and conAdditionalContacts.UserID > 0 and conAdditionalContacts.PrimaryContact = 1 
			and conAdditionalContacts.UserID is not null and conContactType.ContactTypeID = 1 and conContactType.ContactID = conContactInfo.ContactID ";
			break;
		case 'Managers':
			$where	.= " and conAdditionalContacts.Login = 1  and conAdditionalContacts.UserID > 0 and
				conAdditionalContacts.PrimaryContact = 1 and conAdditionalContacts.UserID is not NULL and optDepartmentManagers.UserID = conAdditionalContacts.UserID and conContactInfo.Active = 1";
			break;
		case 'PDM':
			$where .= " and conAdditionalContacts.Login = 1  and conAdditionalContacts.UserID > 0 and
				conAdditionalContacts.PrimaryContact = 1 and conAdditionalContacts.UserID is not NULL and conContactInfo.ShowInDeveloperList = 1 and conContactInfo.Active = 1";
			break;
		case 'Sales':
			//03.01.2011 jss - a dealer has salesmen that do not log into lizzy, they fax things into the dealership, so they
			//can't be listed as "allow login", so per ghh removing that part of it from this query
			$where .= " and conAdditionalContacts.UserID > 0 and
				conAdditionalContacts.PrimaryContact = 1 and conAdditionalContacts.UserID is not NULL and
				conContactInfo.ShowInSalesList = 1 and conContactInfo.Active = 1";
			/*
			$where .= " and conAdditionalContacts.Login = 1  and conAdditionalContacts.UserID > 0 and
				conAdditionalContacts.PrimaryContact = 1 and conAdditionalContacts.UserID is not NULL and conContactInfo.ShowInSalesList = 1 and conContactInfo.Active = 1";
			*/
			break;
		case 'Support':
			$where .= " and conAdditionalContacts.Login = 1  and conAdditionalContacts.UserID > 0 and
				conAdditionalContacts.PrimaryContact = 1 and conAdditionalContacts.UserID is not NULL and conContactInfo.ShowInSupportList = 1 and conContactInfo.Active = 1";
			break;
		case 'Tester':
			$where .= " and conAdditionalContacts.Login = 1  and conAdditionalContacts.UserID > 0 and
				conAdditionalContacts.PrimaryContact = 1 and conAdditionalContacts.UserID is not NULL and conContactInfo.ShowInTesterList = 1 and conContactInfo.Active = 1";
			break;
		case 'Carrier':
			$where .= " and conContactInfo.ShowInCarrierList = 1 and conContactInfo.Active = 1";
			break;
		case 'Terminal':
			$where .= " and conContactInfo.ShowInTerminalList = 1 and conContactInfo.Active = 1";
			break;
		case 'Mechanic':
			//TKS 10.18.2012 #24419 changed the ShowInMechanicList to hold 1 or 2 for primary and secondary mechs
			//so changing it to != 0 here so it gets both now
			$where .= " and conAdditionalContacts.UserID > 0 and conAdditionalContacts.UserID is not NULL and 
							conAdditionalContacts.PrimaryContact = 1 and 
							 conContactInfo.ShowInMechanicList != 0 and conContactInfo.Active = 1";
			//TKS 06.25.2015 #69916
			if ( $usertype == 'Mechanic' && $id > 0 )
				$where .= " and conAdditionalContacts.UserID = optMechanicClassification.MechanicID and optMechanicClassification.JobClassID = ".$id;
			break;
		default://all
			$where .= '';
			break;
		}

	//TKS 10.06.2011 #7484 making sure that if in another DB other than nizex and nizex employees are set to login into their DB
	//we don't want nizex employees to show in their employee list
	if ( $dbname <> "nizex_nizex" && $dbname <> "nizex_demo" )
		$where .= " and LEFT( UPPER( conContactInfo.BusinessName ), 5 ) <> 'NIZEX' ";

	if ( $showname == 'last' )
		$orderby .= " ORDER BY conAdditionalContacts.LastName, conAdditionalContacts.FirstName";
	else
		$orderby .= " ORDER BY conAdditionalContacts.FirstName, conAdditionalContacts.LastName";
	
	$query	= $select.$from.$where.$orderby;
	if ( !$result = $db->sql_query( $query ) )
		LogError( 469, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		
	//loop through checking if each user is of that role for that module
	//if so, then add them to our array
	$k = 0;
	if ( $return == 'query' )
		$Contacts = '';
	else
		$Contacts = array();
	while( $row	= $db->sql_fetchrow( $result ) )
		{		
		//TKS 04.23.2015 added ability for this function to return a comma separated list of userids
		if ( $return == 'array' )
			{
			$Contacts[ $k ][ "UserID" ] 	= $row[ "UserID" ];
			$Contacts[ $k ][ "ContactID" ]	= $row[ "ContactID" ];

			//02.03.2010 ghh added to show business name if there is not first or last
			//name
			if ( $usertype == 'Carrier' || $usertype == 'Terminal' )
				$Contacts[ $k ][ 'Name' ] 		= $row[ 'BusinessName' ];
			else
				{
				if ( $showname == 'last' )
					$Contacts[ $k ][ "Name" ] 		= $row[ "LastName" ].", ".$row[ "FirstName" ];
				else
					$Contacts[ $k ][ "Name" ] 		= $row[ "FirstName" ]." ".$row[ "LastName" ];
				}
			//you can add more show name options here (like First Only )
			}
		else
			{
			if ( $k == 0 )
				$Contacts = $row[ "UserID" ];
			else
				$Contacts .= ",".$row[ "UserID" ];
			}
		$k++;
		}	 
	}
return $Contacts;
 
}





	
function hasPurchased( $dbname, $moduleid )
{
//03.08.2010 ghh because of the way I changed things around we no longer need this
//function so am just going ot return true
return true;
global $dbhost;
global $dbuser;
global $dbpasswd;
global $supportdb;

if ( !isset( $dbname ) || !isset( $moduleid ) )
	return false;

//04.11.2011 ghh - changed to supportdb alias
$db_support = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true );
if	(	!$db_support->db_connect_id )
	{
		LogError( 453, $query );
	}

if ( $db_support->db_connect_id )
	{
	$query = "select ModuleID from optCompanyModules, optUserCompany where optUserCompany.DBName = '". $dbname . "' and optCompanyModules.ModuleID=". $moduleid . " and optCompanyModules.CompanyID=optUserCompany.CompanyID";

	if ( !( $result = $db_support->sql_query( $query ) ) )
		{	
		//log error
		LogError( 454, $query );
		}
	
	if ( $db_support->sql_numrows( $result ) > 0 )
		return true;
	else
		return false;
	}
}

//this function takes in a time field and returns the time taking
//into consideration the users timezone
function formatTime( $time, $flag )
{
//first we retrieve the users offset from GMT
$offset = $_COOKIE[ 'ssi_timezone' ];
switch ( $flag )
	{
	case 1: //gets ready to send to mysql query 
		{
		$temp = explode( ':', $time );
		$newtime = ( $temp[ 0 ] + $offset ) .":".$temp[ 1 ]. ":00";
		return $newtime;
		}

	case 2: //gets ready to display to user
		{
		$temp = explode( ':', $time );
		$newtime = ( $temp[ 0 ] - $offset ) .":".$temp[ 1 ]. ":00";
		return $newtime;
		}
	}
}

	
function getDateFormat( )
{
global $db;
if ( $db->db_connect_id )
	{
	$query	= "select DateFormat from optCompanyInfo";
		
	if ( !$result = $db->sql_query( $query ) )
		{	
		//log error
		LogError( 593, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		}
	
	$dateformat	= $db->sql_fetchrow( $result ) ;

	return $dateformat[ 'DateFormat' ];
	}
}


//10.30.2010 ghh function to adjust for daylight savings time
function adjustDaylightSavings( $newdate, $storage = false )
{
//10.30.2010 ghh added to allow date to return based on whether we are now in
//daylight savings and if the time we're looking at is also.  Otherwise it will change the
//time to make it correct.
//first get the current time period we find ourselves in
$daylightnow = date( 'I' );
//now get daylight for date being sent to us
$daylightthen = date( 'I', $newdate );

//now see if we need to do the adjustment
if ( $daylightnow != $daylightthen )
	{
	if ($storage)
		{
		if ( $daylightnow == 0 )
			$newdate -= 3600;//reduce by 1 hour
		else
			$newdate += 3600;//reduce by 1 hour
		}
	else
		{
		//we are currently in daylight savings time
		if ( $daylightnow == 1 )
			$newdate -= 3600;//reduce by 1 hour
		else
			$newdate += 3600;//reduce by 1 hour
		}
	}

return $newdate;
}//end adjustDaylightSavings

//handles formatting dates from the database as well as dates to
//the database from the users.
function FormatDate( $date, $flag, $includestime=false )
{
//TKS 05.03.2012 to prevent the passing in an invalid 
//date and getting a 1969 date back
//I added this return ''
if ( trim($date) == '' || $date == '0000-00-00' || $date == '0000-00-00 00:00:00' )
	return '';


//first we retrieve the users offset from GMT
@$offset = $_COOKIE[ 'ssi_timezone' ];

if ( $offset == '' )
	$offset = 0;

//added to deal with daylight savings temperarily
$dateformat = getDateFormat();
$seperator = substr( $dateformat, 1,1 );

//05.25.2010 ghh if separator is not a dash or slash then make it a slash
if ( $seperator != '-' || $seperator != '/' )
	$seperator = '/';

if ( trim( $date ) == '' || trim( $date ) == 'NULL' )
	return $date;

switch ( $flag )
	{
	case 1: //gets ready to send to mysql query 
		{
		//04.06.2012 ghh - added to fix problem with php that we recently found in latest version
		//of php
		//04.22.2014 naj - now that we have Canadian dealers we need to support dashes in the date format.
		//06.17.2014 naj - Ticket 52930 - added time offset to non-US formatted dates.
		if ($dateformat == 'm/d/Y')
			$newdate = strtotime( str_replace( '-','/',$date ) ) - ( 3600 * $offset );
		else
			$newdate = strtotime( $date ) - ( 3600 * $offset );

		if ( $includestime )
			{
			//03.09.2012 naj - added daylight saving adjustment for storing data time data
			$newdate = adjustDaylightSavings( $newdate, true);
			return date( 'Y-m-d H:i:s', $newdate );
			}
		else
			return date( 'Y-m-d ', $newdate ).'12:00:00';
		}

	case 2: //gets ready to display to user
		{
		//if date falls inside daylight savings time then we adjust by an additional
		//hour before viewing... if we are outside this 
		//case 2 will be to determine user formating of date for display
		//purposes.  This case always assumes you are passing in a mysql datetime field
		$newdate = strtotime( $date ) + ( 3600 * $offset );

		//10.30.2010 ghh adjust for daylight savings if necessary
		$newdate = adjustDaylightSavings( $newdate );

		//NOTE need query here to determine users format so we can format correctly
		//TKS 9-8-10 #1598 needed to print today's date on the invoice with time. 
		//I used gmdate and then called our FormatDate( gmdate( "m/d/Y h:i:s" ), 2, true );
		//this gave the correct date and time but the meridian said AM instead of PM so it looks like it is off
		//by 12 hours. The code below was return date( $dateformat . ' g:i:s A',  $newdate ); 
		//GHH said to go ahead and remove the 'A' for showing AM or PM and make a note to come back to it
		//later
		if ( $includestime ) 
			return date( $dateformat . ' g:i:s A',  $newdate );
		else
			return date( $dateformat, $newdate );
		}

	case 3: //gets ready for mysql query but without a time on it. Used when we want to hard code other times
		{
		//$datesep = strptime( $date, $dateformat );
		//$date = $datesep[ 0 ]. '/'. $datesep[ 1 ]. '/'. $datesep[ 2 ];
		//04.06.2012 ghh - added str_replace to fix problem with php
		//$newdate = strtotime( str_replace( '-','/', $date ) ) - ( 3600 * $offset );
		//02.03.2014 naj - Ticket 46604, per Glenn we should not be dealing with an offset on date only fields.
		//04.22.2014 naj - now that we have Canadian dealers we need to support dashes in the date format.
		if ($dateformat == 'm/d/Y')
			$newdate = strtotime( str_replace( '-','/', $date ) );
		else
			$newdate = strtotime( $date );

		return date( 'Y-m-d', $newdate );
		}

	case 4: //formats for user but without adding timezone information always assumes date field without timestamp
		{
		//$datesep = strptime( $date, $dateformat );
		//$date = $datesep[ 0 ]. '/'. $datesep[ 1 ]. '/'. $datesep[ 2 ];
		$newdate = strtotime( $date );

		if ( $includestime ) 
			return date( $dateformat . ' g:i:s A',  $newdate );
		else
			return date( $dateformat, $newdate );
		}
	}
}

//TKS. Example of usage is in the invoicing/cv_invoicelist.php file
//this function takes a date ( no time ) for from date and to date
// and returns a date time  range in an array. We discovered that we needed to not just
//grab from 00:00:00 to 23:59:59 but actually go to the next day in our range to ensure we grab 
//records entered at 11:59pm that might put it into the next day after the offset
////11.03.2011 : CMK : adding flag so that we can return just the date ( no time ) for just date fields
function FormatDate_DateRange( $fromdate, $todate, $flag=1 )
{
if ( empty( $fromdate ) || empty( $todate ) )
	return false;

if ( $flag == 1 )
	{
	//get the time that goes into the DB for the next day due to the conversion
	//also look to see if timezone needs the leading zero
	if ( trim( strlen( $_COOKIE[ "ssi_timezone" ] ) ) == 1 )
		$time = " 0".( -1 * $_COOKIE[ 'ssi_timezone' ] ).":00:00";
	else	
		$time = ( -1 * $_COOKIE[ 'ssi_timezone' ] ).":00:00";

	//02.29.2012 : CMK : # 14102 : for some reason on some reports - the time portion is coming back 5:00:00 instead of 05:00:00 - so need to look
	//at time and add the preceeding 0 if its not there
	if ( strpos( trim( $time ), ':' ) == 1 )
		$time = " 0".trim( $time );

	//TKS 03.27.2012 noticed that we had problems with mysql pulling wrong date because we had a space being added to the
	//time var above then appending time with a space below which caused the wrong results in some cases
	//so I removed the extra space and this fixed this behavior. It was only an issue it seems when clocking in and out near the 
	//midnight to 4am window on the next day
	$date[ 0 ] 	= date( "Y-m-d", strtotime( $fromdate ) ).$time;//temp var to increment our date one day at a time
	//11.09.2011 naj - alter the calculation for the next day due to a problem with the day daylight savings time starts and ends.
	//$date[ 1 ] 	= date( "Y-m-d", strtotime( $todate ) + 86400 )." ".$time;//this goes to the next day to ensure that we get all the time in the DB for the day we are on
	$date[ 1 ]  = date( "Y-m-d", mktime(0, 0, 0, date("m", strtotime($todate))  , date("d", strtotime($todate))+1, date("Y", strtotime($todate)))).$time;
	}
else
	{
	$date[ 0 ] 	= date( "Y-m-d", strtotime( $fromdate ) );
	$date[ 1 ] 	= date( "Y-m-d", strtotime( $todate ) );
	}

return $date;
}





//this function takes seconds and sends back formated as a timestamp
//ie: 3600 passed in will return 01:00:00 for 1 hour 0 min 0 sec
//09.15.2011 : CMK : modifying slightly - if you pass in 32400 ( 9 hours ) - you get back 9:0:0 - not 09:00:00
//simply adding in checks to make sure your value is more than 10 or else throw a 0 in first
function TimeStamp( $sec )
{
$temp = explode( '.' ,( $sec / 3600 ) );
$timestamp = '';

//build up our time stamp
if ( $temp[ 0 ] < 10 )
	$timestamp .= '0';
$timestamp .= $temp[ 0 ]. ":";

//now we build up the minutes
$val = '.' . $temp[ 1 ];
$temp1 = explode( '.', ( $val * 60 ) );

if ( $temp1[ 0 ] < 10 )
	$timestamp .= '0';
$timestamp .= $temp1[ 0 ] . ":";

//now format seconds
$val = '.' . $temp1[ 1 ];
$temp = explode( '.', ( $val * 60 ) );
if ( $temp[ 0 ] < 10 )
	$timestamp .= '0';
$timestamp .= $temp[ 0 ];

return $timestamp;
}



function SetupUserID( $userhash )
{
global $dbhost;
global $dbuser;
global $dbpasswd;
global $supportdb;

//04.11.2011 ghh - changed to proper supportdb alias
$db_support = new sql_db($supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true);
if	(	!$db_support->db_connect_id )
	{
		$lbl_error = $dblang[ "CannotConnect" ];
	}

if ( $db_support->db_connect_id )
	{
	$query	= "select UserID, CurrentDB, CompanyID, DBHost from optUsers, optUserCompany where optUserCompany.DBName = optUsers.CurrentDB and LoginHash='". $userhash . "'";

	if ( !( $result = $db_support->sql_query( $query ) ) )
		{	
		//log error
		LogError( 408, $query .$dblang[ "ErrorInSQL" ]."<br>".$db_support->sql_error() );
		}
	
	global $UserID;
	global $dbname;
	global $Company_ID;
	
	$row	= $db_support->sql_fetchrow( $result ) ;
	$UserID = $row[ 'UserID' ];
	$dbname = $row[ 'CurrentDB' ];

	//in the even this is the first login attempt or something it will not have a hash key and therefore not get back
	//a dbhost so we want to make sure we keep using the old one.
	if ( !is_null( $row[ 'DBHost' ] ) )
		$dbhost = $row[ 'DBHost' ];
	$Company_ID = $row[ 'CompanyID' ];


	$db_support->sql_close();
	}
}








//this function looks to see if the date falls on a weekend and if so we adjust for it.
function adjForWeekend( $movedate )
{
//we're going to either add 1 day, 2 days or no days depending
//on whether we're on sat, sun, or a weekday respectfully
if ( date( 'w', $movedate ) == 6 )	
	return $movedate + ( 172800 ); //2 days
else
	if ( date( 'w', $movedate ) == 1 )
		return $movedate + 86400; //1 day
	else
		return $movedate;
}


//This function updates the DateAssigned values for all the incompleted tasks assigned to a
//particular developer, and updates/adjusts them as needed.
function ART( $userid, $futurerelease = false )
{
global $db;

if ( $db->db_connect_id )
	{
	//get the currently assigned tasks and note that dateDiff is going to return negative numbers for future dates, positive for past dates
	//TKS 11.21.2011 #9405 Keep Rejected Tickets on today's plate. Added Status to select so we know if the ticket is rejected
	if ( !$futurerelease )
		//02.08.2012 ghh - excluding rejected tickets from the list so they are totally ignored.  We will then update rejected tickets to
		//today in a single query that is outside of ART
		$query = "select StatusID, ID, UNIX_TIMESTAMP( DateAssigned ) as TimeStamp, DateAssigned 
					from proTasks 
					where UserID = ". $userid ." 
					and FutureRelease is null 
					and DateCompleted is null 
					and DateAssigned is not null 
					and StatusID<>8
					and ifnull(LockTask,2) <> 1 order by TimeStamp"; 
	else
		$query = "select StatusID, ID, UNIX_TIMESTAMP( DateAssigned ) as TimeStamp, DateAssigned 
						from proTasks 
						where UserID = ". $userid ." 
						and FutureRelease is not null 
						and DateCompleted is null 
						and DateAssigned is not null 
						and ifnull(LockTask,2) <> 1 order by TimeStamp"; 

	if ( !$result = $db->sql_query( $query ) )
		{	
		LogError( 588, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}

	$datearray[] 		= array();
	$i = 0;
	$first = true;
	$continue = true;
	$startday = date( 'd' );
	$startmonth = date( 'm' );
	$startyear = date( 'Y' );
	$changed = false;

	//loop through and determine which way we're shifting
	while( $row	= $db->sql_fetchrow( $result ) )
		{		
		//if the first task is already on todays date then we'll stop
		//echo  FormatDate( $row[ 'DateAssigned' ] , 2) .'=='. FormatDate(date( 'Y-m-d' ),4) ;
		if ( $first )
			if ( FormatDate( $row[ 'DateAssigned' ] , 2) == FormatDate(date( 'Y-m-d' ),4) )
				{
				$continue = false;
				return true;
				break;
				}
			else
				{
				//now we're going to start building up our array that we'll use to update
				//records later
				$curdate = date( 'm-d-Y', $row[ 'TimeStamp' ] ); //gets current task dateassigned value
	//			echo "Error".$curdate."<br>";
				$first = false;
				}

		//see if we need to update curdate
		if ( $curdate != date( 'm-d-Y', $row[ 'TimeStamp' ] ) )
			{
			$newdatetime = mktime( 12,0,0,$startmonth, $startday + 1, $startyear );
			//need to make sure this is not a weekend day before moving on
			$newdatetime = adjForWeekend( mktime( 12,0,0,$startmonth, $startday + 1, $startyear ) ); 

			$startday = date( 'd',  $newdatetime );
			$startmonth = date( 'm', $newdatetime );
			$startyear = date( 'Y', $newdatetime );
			$curdate = date( 'm-d-Y', $row[ 'TimeStamp' ] ); //gets current task dateassigned value
			//echo $curdate." - ". date( 'm-d-Y', $row[ 'TimeStamp' ] ) . ' + ' .$newdatetime ."<br>";
			}
	//	echo "skipping<br>";

		//now we're going to proceed to update this task and all other tasks on this date with today's date
		$datearray[ $i ][ 'ID' ] = $row[ 'ID' ];
		//TKS 11.21.2011 #9405 Keep Rejected Tickets on today's plate
		//02.08.2012 ghh - commented out as I'm doing this a bit different so that tickets move around
		//normally and just ignore rejected tickets altogether
		//if ( $row[ "StatusID" ] <> 8 )
		$datearray[ $i ][ 'DateAssigned' ] = $startyear.'-'.$startmonth.'-'.$startday. ' 12:00:00'; //we put in middle of day to deal with timezones
		//else
			//set to today's date
		//	$datearray[ $i ][ 'DateAssigned' ] = date( "Y" ).'-'.date( "m" ).'-'.date( "d" ). ' 12:00:00'; //we put in middle of day to deal with timezones

		$i++;
		}

	//08.01.2008 ghh now we update dates for tasks
	if ( $continue )
		{
		for ( $j = 0; $j < $i; $j++ )
			{
			$query = "update proTasks set DateAssigned= '" . $datearray[ $j ][ 'DateAssigned'] . "' where ID=" . $datearray[ $j ][ 'ID' ];
				
			if ( !$result = $db->sql_query( $query ) )
				{	
				//log error
				LogError( 589, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
				return false;
				}
			}
		} //end of continue that actually updates the dates in proTasks

	//now if we are not in the process of checking for future releases then recall this function so that
	//we can move those tasks as well
	//02.08.2012 ghh - added else portion to handle updating all rejected tickets for this user to todays date
	if ( !$futurerelease )
		ART( $userid, true );
	else
		{
		$query = "update proTasks set DateAssigned='".date( "Y" ).'-'.date( "m" ).'-'.date( "d" ).' 12:00:00'."'
					where UserID = ". $userid ." 
					and FutureRelease is null 
					and DateCompleted is null 
					and DateAssigned is not null 
					and StatusID=8
					and ifnull(LockTask,2) <> 1 "; 
		if ( !$result = $db->sql_query( $query ) )
			{	
			//log error
			LogError( 9439, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
			return false;
			}
		}
		

	return true;
	}
else
	{	
	//log error
	LogError( 590, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	return false;
	}
}



//this function will return the company id for the current user.
function getCompanyID( )
{
global $dbhost;
global $dbname;
global $dbuser;
global $dbpasswd;
global $dbname;
global $supportdb;//04.11.2011 ghh - 

//04.11.2011 ghh - changed to always use support db instead of nizexdb
$db_support = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true );

if ( $db_support->db_connect_id )
	{
	$query = "select CompanyID from optUserCompany where DBName='".$dbname."'";
	if ( !( $result = $db_support->sql_query( $query ) ) )
		LogError( 1790, $query .$dblang[ "ErrorInSQL" ]."<br>".$db_support->sql_error() );

	$row = $db_support->sql_fetchrow( $result );
	return $row[ 'CompanyID' ];
	}
else
	{
		LogError( 1791, '' );
	}
}



//handles scheduling emails
//Added CompanyEmailID to support attachements on outgoing emails. NAJ 01/04/2010
function EmailSchedule( $To, $From, $Subject, $Body, $CompanyEmailID = '' )
{
global $dbhost;
global $dbname;
global $dbuser;
global $dbpasswd;
global $supportdb;//04.11.2011 ghh -

//04.11.2011 ghh - changed to always use proper alias instead of nizexdb
$db_support = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true );

if ( $db_support->db_connect_id )
	{
	$query = "select CompanyID from optUserCompany where DBName='".$dbname."'";

	if ( !$result = $db_support->sql_query( $query ) )
		{
		LogError( 677, $query .$dblang[ "ErrorInSQL" ]."<br>".$db_support->sql_error() );
		return false;
		}

	$row = $db_support->sql_fetchrow( $result );
	$companyid = $row[ 'CompanyID' ];

	$query	= "insert into optEmailSchedule ( EmailTo, EmailFrom, DateRequested, Subject, Body, CompanyID, CompanyEmailID ) values ( '". $To ."','". $From ."',now(),'". prepareNote( $Subject )."','". prepareNote( $Body ). "','". $companyid. "','". $CompanyEmailID."')";
		
	if ( !$result = $db_support->sql_query( $query ) )
		{
		LogError( 675, $dblang[ "ErrorInSQL" ]."<br>".$db_support->sql_error().' <br> '.$query );
		return false;
		}

	$db_support->sql_close();
	}
else
	{
		LogError( 676, $query );
		$lbl_error = $dblang[ "CannotConnect" ];
		return false;
	}
}


//returns the week of the month
function WeekOfMonth( $tempdate )
{
$dayofmonth = date( "d", strtotime( $tempdate ));

if ( $dayofmonth - 7 <= 0 )
	return 1;
elseif ( $dayofmonth - 14 <= 0 )
	return 2;
elseif ( $dayofmonth - 21 <= 0 )
	return 3;
elseif ( $dayofmonth - 28 <= 0 )
	return 4;
else
	return 5;

}

//returns next instance of weekOfMonth() so if I pass in a date it tells me when the next date should be.
function nextWeekOfMonth( $tempdate, $num_of_months, $skip = 0 )
{
$dayofweek = date( "w", strtotime( $tempdate ) );//first thing, figure out what day of the week we are on.

$weekofmonth 	= WeekOfMonth( $tempdate );//what week of the month are we on

$nummonth = $num_of_months;
for ( $j = 1; $j <= $skip; $j++ )
	$nummonth = $nummonth + $num_of_months;

//break apart the date so I can set up a new date based off the 1st day of the month
$month	= date( "m", strtotime( $tempdate ) );
$month 	= $month + $nummonth;
$day		= "01";
$year		= date( "Y", strtotime( $tempdate ) );

//checking to be sure we don't need to go to new year
if ( $month > 12 )
	{
	$year ++;
	$month = $month - 12;
	}
	
$newdate = $year."-".$month."-".$day;

//now we find the day of week for this date
$startdow = date( "w", strtotime( $newdate ) );

//now we compare the first day of month's day to our day and adjust the date appropriately
//we add 1 because we always start on the first day of month and have to add it back in.
if ( $startdow < $dayofweek )
	$day = $dayofweek - $startdow + 1;
elseif ( $startdow > $dayofweek )
	$day = ( $dayofweek - $startdow + 1 ) + 7;
else
	$day = $startdow;

//echo "Tempdate".$tempdate."Month".$month."==Day".$day." StartDOW ".$startdow." dayofweek=".$dayofweek."<br>";
//now we're going to add to days to get a new date for our time period we're looking for
//if weekofmonth is already 1 then we already have our new date
for ( $i = 2; $i <= $weekofmonth; $i++ )
	$day = $day + 7;	

$daysinmonth = date('d', mktime( 0,0,0,$month+1,0,$year ) );
if ( $daysinmonth < $day )
	{
	if ( $skip == 0 )
		$skip = 1;
	else
		$skip++;
		
	$returndate = nextWeekOfMonth( $tempdate, $num_of_months, $skip );
	}
else
	$returndate = $year."-".$month."-".$day;

//before we return the date we need to be sure that the month has not changed. If so we pass
//back a null
return $returndate;
}





//this function takes a projectid and returns the name
//I use this mostly on reports where they selected a project and I need
//a quick way to display the name
function GetProjectName( $projid )
{
global $db;

$query	= "Select Name from proProject where ID = ".$projid;

if ( !$result = $db->sql_query( $query ) )
	{	
	//log error
	LogError( 1542, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	}

$row		= $db->sql_fetchrow( $result );
return $row[ "Name" ];
}

//this function takes a sectionid and returns the name
//I use this mostly on reports where they selected a section and I need
//a quick way to display the name
function GetSectionName( $sectid )
{
global $db;

$query	= "Select Name from proSection where ID = ".$sectid;

if ( !$result = $db->sql_query( $query ) )
	{	
	//log error
	LogError( 1543, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	}

$row		= $db->sql_fetchrow( $result );
return $row[ "Name" ];
}





//function returns contact's zipid
function getContactZipID ( $contactid )
{
global $db;

if( $db->db_connect_id )
	{
	if ( $contactid > 0 )
		$query	=	"select ZipID FROM conContactInfo WHERE ContactID = ".$contactid;
	
	if ( !$result = $db->sql_query( $query ) )
		LogError( 1604, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	
	$row		= $db->sql_fetchrow( $result );

	if ( !is_null( $row[ "ZipID" ] ) && $row[ "ZipID" ] > 0 )
		$zip = $row[ "ZipID" ];
	else
		$zip = 0;

	return $zip;
	}
}







//function to return user's real name
//TKS 03.23.2012 #15644 customer wanted sales rep initials on invoice. Adding here in case we need this else where
function UserRealName( $userid, $usercontactid=0, $firstlast=false, $return_initials=false )
{
global $db;

if( $db->db_connect_id )
	{
	//07.27.2011 jss - if no user and no contact, pass back nothing (common with conversions)
	if ( $userid == 0 && $usercontactid == 0 )
		return 'UserID 0 - normally from conversions';

	//TKS 03.23.2012 #15644 added middle name
	if ( $userid > 0 )
		$query	=	"select ifnull( FirstName, '' ) as FirstName, 
						ifnull( MiddleName, '' ) as MiddleName, ifnull( LastName, '' ) as LastName 
						FROM conAdditionalContacts WHERE UserID = ".$userid;
	else
		$query	=	"select ifnull( FirstName, '' ) as FirstName, 
						ifnull( MiddleName, '' ) as MiddleName, ifnull( LastName, '' ) as LastName 
						FROM conAdditionalContacts WHERE PrimaryContact = 1 and ContactID = ".$usercontactid;

	$result	= $db->sql_query( $query ); 
	
	$row		= $db->sql_fetchrow( $result );
	
	if ( !$return_initials )
		{
		if ( $firstlast )
			$name		= $row[ "FirstName" ]." ".$row[ "LastName" ];
		else
			$name		= $row[ "LastName" ].", ".$row[ "FirstName" ];
		}
	//TKS 03.23.2012 #15644 added middle name to above select so we can have middle initial in the case they have a tim smith and tom sullivan
	//the middle initial could make the difference in identifying
	else
		$name = substr( $row[ "FirstName" ], 0, 1 ).substr( $row[ "MiddleName" ], 0, 1 ).substr( $row[ "LastName" ], 0, 1 );
	
	//TKS 1-27-11 added stripslashes to the return
	return stripslashes( $name );
	}
}



function ContactRealName( $contactid, $firstlast = false, $includebusiness = false )
{
global $db;
if ( !$contactid > 0 )
	return '';

if( $db->db_connect_id )
	{
	$query	=	"select conContactInfo.BusinessName, conAdditionalContacts.FirstName, 
					conAdditionalContacts.LastName FROM conContactInfo, conAdditionalContacts 
					WHERE conAdditionalContacts.PrimaryContact = 1 and conAdditionalContacts.ContactID = ".$contactid."
					and conContactInfo.ContactID = conAdditionalContacts.ContactID";

	$result	= $db->sql_query( $query ); 
	
	$row		= $db->sql_fetchrow( $result );
	
	if ( $firstlast )
		$name		= $row[ "FirstName" ]." ".$row[ "LastName" ];
	else
		$name		= $row[ "LastName" ].", ".$row[ "FirstName" ];

	if ( trim( $name ) == '' || trim( $name ) == ',' )
		$name = $row[ 'BusinessName' ];
	else
		if ( $includebusiness == true && $row[ "BusinessName" ] != '' && !is_null( $row[ "BusinessName" ] ) )
			$name .= " @ ".$row[ 'BusinessName' ];
	return stripslashes( $name );
	}

}


//TKS 02.22.2012 #13836 there's a situation where I already have the customer name in a spokewith field
//and need to append the business name and do not want it to return the customer's name if business is blank
//so I am adding a parameter to allow return of empty string if no business is found $allowempty
function BusinessName( $userid, $contactid=0, $usebrandname=false, $allowempty=false )
{
global $db;

if( $db->db_connect_id )
	{
	//TKS 06.18.2015 noticed getting FirstName ambigious error in conAdditionalContact/conContactInfo query
	if ( $userid > 0 )
		$query	=	"select BusinessName, conAdditionalContacts.FirstName, conAdditionalContacts.LastName, BrandName FROM  conContactInfo, conAdditionalContacts 
						WHERE conAdditionalContacts.ContactID=conContactInfo.ContactID 
						and conAdditionalContacts.UserID = ".$userid;
	else
		$query	=	"select BusinessName, FirstName, LastName, BrandName FROM  conContactInfo 
						WHERE conContactInfo.ContactID = ".$contactid;

	$result		= $db->sql_query( $query ); 
	
	$row			= $db->sql_fetchrow( $result );
	
	$business	= stripslashes( $row[ "BusinessName" ] );
	$brand		= stripslashes( $row[ "BrandName" ] );

	if ( $business == '' && !$allowempty )
		$business = stripslashes( $row[ 'FirstName' ].' '.$row[ 'LastName' ] );

	//12.31.2010 jss - changed from || to &&
	if ( $usebrandname && $brand != '' )
		$business = $brand;
	
	return $business;
	}
}


//gets contactid for specified userid
function getUserContactID( $userid )
{
global $db;
if ( $userid > 0 )
	{
	$query = "select ContactID from conAdditionalContacts where UserID=".$userid;

	if ( !$result = $db->sql_query( $query ) )
		LogError( 10745, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );

	$row = $db->sql_fetchrow( $result );
	//TKS 05.16.2014 to ensure that we always return a numeric value to prevent select queries from
	//breaking if numrows 0 then we return 0. This would be the case when I log into a dealer's DB as research and development
	//and the home screen tries to show me tickets linked to my contactid in nizex db ( of which I don't exist with this userid )
	if ( $db->sql_numrows( $result ) == 0 )
		return 0;
	else
		return $row[ 'ContactID' ];
	}
else
	return 0;
}


//gets userid for specified contactid
function getContactUserID( $contactid )
{
global $db;

if ( $contactid > 0 )
	{
	//TKS 5-11-10 added PrimaryContact=1 
	//I came across a situation where a user ( Jef Schink ) had multiple additional contacts and 2 of them had UserIDs
	//and the query below was just grabbing a Userid for the contactid and his contactid would have 2 userids so it would always
	//just grab the first one and may not be the results you expect. So talked with GHH and for now adding PrimaryContact flag to 
	//always grab that UserID. There is the possibility that a user can set an additional contact of theirs to Primary that does not have 
	//a userid and in that case this would fail so we need to keep an eye on this.
	$query = "select UserID from conAdditionalContacts where ContactID=".$contactid." and UserID is not null and UserID>0 and PrimaryContact=1";

	if ( !$result = $db->sql_query( $query ) )
		LogError( 1394, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );

	$row = $db->sql_fetchrow( $result );

	//08.09.2011 ghh - added if and else to prevent sending back a blank userid
	if ( $row[ 'UserID' ] > 0 )
		return $row[ 'UserID' ];
	else
		return 0;
	}
else
	return 0;
}




//this function will replace return strokes with <br> and prepareNote
//$foredit tells it whether it is going into the database, html page, or if its being
//formatted to display inside an edit box.
//TKS 01.02.2013 #28328 when working with pdf reports we have to call prepareNote and pass in
//true for edit so it reverts html back to the proper format. But the htmlspecialchars with 
//ENT_QUOTES is converting a ' to &#039; as it should but not for pdf file. I am adding
//another parameter, defaulting to false for pdf files so we can catch this and prevent this in our reports
function prepareNote( $note, $foredit = false, $convertentities = false, $ispdf = false )
{
if ( !$foredit )
	{
	//03.14.2012 naj - added htmlentities back in to handle utf8 characters pasted from word.
	//TKS 03.26.2012 changed ENT_COMPAT to NOQUOTES to leave qoutes alone and also adding strreplace to
	//convert entities back to <> for html tags. Reason is on note fields we build links and image tags
	//for the user and it was converting the tags to &lt; and &gt; which caused the browser to display the HTML code instead of the link or image
	//12.21.2012 naj - Added str_replace to replace Rights Reserved and Copywrite with the html entities
	//Found that the rights reserved ascii character causes MySQL to terminate the text data at that char.
	//01.10.2013 naj - deal with pasted shit from customers that has multiple encoded special chars.
	//01.10.2013 naj - added count to ensure that we don't somehow get stuck in a loop.
	$continue = true;
	$count = 0;
	
	while ($continue)
		{			
		$tempnote = html_entity_decode( stripslashes( $note ), ENT_QUOTES | ENT_HTML401 , 'UTF-8' );

		if ($tempnote != $note)
			$note = $tempnote;
		else
			$continue = false;

		$count++;
		if ($count > 10)
			$continue = false;
		}
	//02.24.2014 naj - added detection of utf-8
	if (mb_detect_encoding($note) != 'UTF-8')
		{
		$note = str_replace(chr(174), '&reg;', $note);
		$note = str_replace(chr(169), '&copy;', $note);
		}

	if ($convertentities)
		{
		//04.24.2014 naj - added html5 and false flag to htmlentities
		$newnote = replaceSmartQuote( htmlentities($note, ENT_QUOTES | ENT_HTML401, 'UTF-8', false) );
		$newnote = str_replace( '&lt;', '<', $newnote );
		$newnote = str_replace( '&gt;', '>', $newnote );
		}
	else
		{
		$newnote = replaceSmartQuote( stripslashes( $note ) );
		}

	//return  prepareNote( htmlentities( trim( str_replace ( chr( 10 ), "<br>", $newnote ) ), ENT_NOQUOTES, "UTF-8", false ) ); 
	//TKS 11.04.2011 #8845 commented out below and no longer using the utf8_encode
	//worked with Noel on this as it seemed that characters coming over from a word doc were being encoded more than once
	//since the browser uses utf8 and the db encodes to utf8 we are not thinking this needs to be called. If something breaks we will know
	//	return  utf8_encode( addslashes( trim( str_replace ( chr( 10 ), "<br>", $newnote ) ) ) ); 
	//03.13.2014 naj - added utf-8 detection to prevent using the chr in string replace.
	if (mb_detect_encoding($newnote) != 'UTF-8')
		return  addslashes( trim( str_replace ( "\n", "<br>", $newnote ) ) ); 
	else
		return  addslashes( trim( str_replace ( chr( 10 ), "<br>", $newnote ) ) ); 
	}
else
	{
	//12.07.2012 naj - added htmlspecialchars to convert any quotes to the appropriate html entity
	//12.07.2012 naj - this will allow text that includes double quotes and single quotes to display properly
	//TKS 01.02.2013 #28328 see comment at function declaration. 
	//I verified that this is happening in all reports with quotes or single ticks in
	//our pdf files so now passing in a flag to tell this function we are on a pdf and not to
	//call htmlspecialchars
	//TKS 01.07.2013 #28328 added html_entity_decode to convert garbage pasted from documents
	if ( !$ispdf )
		return  htmlspecialchars(stripslashes( trim( str_replace ( "<br>", chr( 10 ), $note ) ) ), ENT_QUOTES); 
	else
		return  html_entity_decode( stripslashes( trim( str_replace ( "<br>", chr( 10 ), $note ) ) ), ENT_QUOTES ); 
	}
}

//03.23.2011 ghh added to allow printing barcode labels because the quotes and other special characters were messing us up.
function prepareNoteForBarcode( $note )
{
$note = htmlspecialchars( $note );
return  $note; 
}


//this function prepares a string for going to the db or coming from it
function prepareString( $string, $fordb = false )
{
if ( $fordb )
	{
	//04.24.2014 naj - added encoding method, utf8, and false flag to htmlentities
	$temp = htmlentities( trim( prepareNote( $string ) ), ENT_QUOTES | ENT_HTML401, "UTF-8", false);
	}
else
	{
	$temp =  stripslashes( $string );
	$temp = str_replace( '"', '&#34;', $temp );
	}

return $temp;
}


//I wanted this function to be generic so it could be used on different tables and fieldnames. This could for example also be called for 
//payroll timesheets or other areas. Which is why I ask for the tablename and fieldnames to be passed in.
//this function takes the table you want to work with, the names of the date fields in the table you are ranging from(can be same field)
//the name of the field for either userid or contactid, that field's value ( userid or contactid ), date range and field you want to total and returns the sum of time for that user and range.
//Now this function will ONLY WORK for field type of 'time' and assumes you want to take hh:mm:ss and add them up for a total, NOT FOR TIME OF DAY. TKS
function UserRangeTotalTime( $tablename, $datefield1, $datefield2, $sumfield, $userid_contactid_field, $fromdate, $todate, $userid_contactid )
{
global $db;
$date = FormatDate_DateRange( $fromdate, $todate );

$query	= "select sec_to_time( sum( time_to_sec( ".$sumfield." ) ) ) as Sum from ".$tablename." 
				where ".$userid_contactid_field." = ".$userid_contactid. " and ".$datefield1." >= '".$date[ 0 ]."'
				and ".$datefield2." <= '".$date[ 1 ]."'";


if ( !$result = $db->sql_query( $query ) )
	{	
	//log error
	LogError( 1106, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	return false;
	}

$row = $db->sql_fetchrow( $result );
return $row[ 'Sum' ];
}



//takes a time length like '27:14:01' and returns total seconds
function time_to_seconds( $origtime )
{
//if negative then we remove the  dash
if ( substr( $origtime, 0, 1 ) == '-' )
	$time = str_replace( '-', '', $origtime );
else
	$time = $origtime;

//do our math	
$temp	= explode( ':', $time );

$sec1 = $temp[ 2 ];//seconds

$sec2 = $temp[ 1 ] * 60;//minutes

$sec3 = $temp[ 0 ] * 3600;//hours

$total = $sec1 + $sec2 + $sec3;

//if the original time passed in was negative, tehn we need to make sure our seconds are negative
if ( substr( $origtime, 0, 1 ) == '-' )
	$total = $total - ( $total * 2 );

return $total;
}


function seconds_to_time( $seconds )
{
// positive or negative?
$negative = $seconds < 0;

// cast as positive
$seconds  = abs( $seconds );

// calculate hours, minutes, and seconds
$hours    = floor( $seconds / 3600 );
$seconds -= $hours * 3600;
$minutes  = floor( $seconds / 60 );
$seconds -= $minutes * 60;

// pad minutes and seconds for display
$hours 	= str_pad( $hours, 2, '0', STR_PAD_LEFT );
$minutes = str_pad( $minutes, 2, '0', STR_PAD_LEFT );
$seconds = str_pad( $seconds, 2, '0', STR_PAD_LEFT );

return( $negative ? '-' : '' ) . $hours
. ':' . str_pad( $minutes, 2, '0', STR_PAD_LEFT )
. ':' . str_pad( $seconds, 2, '0', STR_PAD_LEFT );
}
																	

//this function takes 2 time lengths in string format like '01:30:22' + '34:17:03' adds them
//together and returns '35:47:25'
//I use this when adding up time length totals
function Sum_times( $firsttime, $secondtime )
{
if ( is_null( $firsttime ) || $firsttime == '' )
	$firsttime = '00:00:00';
if ( is_null( $secondtime ) || $secondtime == '' )
	$secondtime = '00:00:00';

$temp	=	time_to_seconds( $firsttime ) + time_to_seconds( $secondtime );

$Total	= seconds_to_time( $temp );

return $Total;
}

function Minus_times( $firsttime, $secondtime )
{
$temp	=	time_to_seconds( $firsttime ) - time_to_seconds( $secondtime );

$Total	= seconds_to_time( $temp );

return $Total;
}



//this function replaces special characters not supported in html browsers with
//standard characters that are supported.  These things happen when people
//copy and paste from word into a browse text field
//TKS 11.04.2011 #8845 added a few more unicode replacements. People were complaining 
//when pasting from MS Word that odd characters were saving in textareas
//changes start at %u0027
function replaceSmartQuote( $val, $reverse = false )
{
//02.24.2014 naj - added fix for UTF-8 characters
if (mb_detect_encoding($val) == 'UTF-8')
	return $val;

$search = array(	chr(145), 
						chr(146), 
						chr(147), 
						chr(148), 
						chr(151),
						'%u2019',
						'%u2013',
						'%u201C',
						'%u201D',
						'%u2022',
						'%u2028', 
						'%u0027',
						'%u02DC' ); 
																										
$replace = array(	"'", 
						"'", 
						'"', 
						'"', 
						'-',
						"'",
						"-",
						'"',
						'"',
						'-',
						' ',
						"'",
						'~' ); 


//03.23.2011 ghh added to allow going the other way
if ( $reverse )
	return str_replace($replace, $search, $val);
else
	return str_replace($search, $replace, $val);
}




//This function returns a table full of custom fields for whatever table
//is requested
//TKS 05.22.2012 this function was setting up spans and classes for lbl but not the data
//as a result, in places we printed custom fields, we wrapped in class data, causing the already
//sized lbl text to go really small. I removed the class data from one place and added
//it in the function here.
function customTableView( $tableid, $searchid, $edit_view, $searchable = false )
{
global $db;
global $domain;
global $usetemplate;

if ( $tableid > 0 )
	{
	$query	= "select TableName, SearchField from cstCustomTables where cstID=" . $tableid;
		
	if ( !$result = $db->sql_query( $query ) )
		LogError( 1040, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	
	$row	= $db->sql_fetchrow( $result ) ;
	$tablename = $row[ 'TableName' ];
	$searchfield = $row[ 'SearchField' ];

	$query	= "select * from ".$tablename." where ".$searchfield." = ".$searchid;

		
	if ( !$result = $db->sql_query( $query ) )
		LogError( 1039, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	
	$row2	= $db->sql_fetchrow( $result ) ;

	$query	= "select * from cstCustomFields where cstID = ".$tableid;

	if ( $searchable )
		$query .= " and Searchable=1";

	$query .= " order by DisplayOrder, FieldTypeID";
	//$query .= " order by FieldDisplayName";
	if ( !$result = $db->sql_query( $query ) )
		LogError( 1037, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	
	$i = 0;
	$customfields[ 'radio' ]	 = '';
	$customfields[ 'notradio' ]	 = '';
	$customfields[ 'text' ]		 = '';
	$customfields[ 'memo' ]		 = '';
	$customfields[ 'integer' ]	 = '';
	$customfields[ 'date' ]		 = '';
	$tab_text	= 100;//tabindexs for custom fields, we increment them in teh loop below
	$tab_date 	= 130;
	$tab_int		= 135;
	$tab_note	= 300;
	while( $row1	= $db->sql_fetchrow( $result ) )
		{		
		//checkboxes
		if ($row1[ 'FieldTypeID' ] == 1)
			{
			if ($row2[ $row1[ 'FieldName' ] ] ==1)
				$checked = 'checked';
			else
				$checked = '';
				
			
			//since this is all stored in a single array, and am delimiting each field with a '|' so I can explode and have a count
			//and display separately later
			if ( $customfields[ 'radio' ] != '' )
				$customfields[ 'radio' ] .= '|';
				
			//I had to add the opposite of radio to search forms so has and has not
			//I am storing the radio has nots in a separate array to lend for more control
			//added 7-29-09 TKS
			if ( $customfields[ 'notradio' ] != '' )
				$customfields[ 'notradio' ] .= '|';

			if ( $searchable )
				{
				$customfields[ 'radio' ] .= '<input type=checkbox class="chk_edit" id="chk_'.$row1[ 'FieldName' ].'" name="chk_'.$row1[ 'FieldName' ].'" '.$checked.' onclick="if ( this.checked ){ document.getElementById( \'chk_not'.$row1[ "FieldName" ].'\' ).checked = false;} locatecontacts( \'reg_list\', 0, 0, \'no\' )" ><span class=lbl>'.$row1[ 'FieldDisplayName' ].'</span>';
				$customfields[ 'notradio' ] .= '<input type=checkbox class="chk_edit" id="chk_not'.$row1[ 'FieldName' ].'" name="chk_not'.$row1[ 'FieldName' ].'" onclick="if ( this.checked ){ document.getElementById( \'chk_'.$row1[ "FieldName" ].'\' ).checked = false;} locatecontacts( \'reg_list\', 0, 0, \'no\' )" ><span class=lbl>'.$row1[ 'FieldDisplayName' ].'</span>';
				}
			else
				{
				if ( $edit_view == 'view' )
					{
					if ($row2[ $row1[ 'FieldName' ] ] ==1)
						$customfields[ 'radio' ] .= "<img src=\"".$domain."/templates/".$usetemplate."/images/green_yes.png\" border=0> <span class=lbl>". $row1[ 'FieldDisplayName' ]."</span>";
					else
						$customfields[ 'radio' ] .= "<img src=\"".$domain."/templates/".$usetemplate."/images/red_no.png\" border=0> <span class=lbl>". $row1[ 'FieldDisplayName' ]."</span>";
					}
				else
					$customfields[ 'radio' ] .= '<input type=checkbox class="chk_edit"  name="chk_'.$row1[ 'FieldName' ] .'" '. $checked.'><span class=lbl>'. $row1[ 'FieldDisplayName' ].'</span>';
				}
			}
		//*****************************************************

		//text fields
		if ($row1[ 'FieldTypeID' ] == 2) 
			{
			//since this is all stored in a single array, and am delimiting each field with a '|' so I can explode and have a count
			//and display separately later
			if ( $customfields[ 'text' ] != '' )
				$customfields[ 'text' ] .= '|';

				
			$customfields[ 'text' ] .= '<div class=lbl>'.$row1[ 'FieldDisplayName' ] .'</div>';
			if ( $searchable )
				$customfields[ 'text' ] .= '<input tabindex='.$tab_text.' type="text" class="txt_edit" id="txt_' . $row1[ 'FieldName' ] .'" name="txt_' . $row1[ 'FieldName' ] . '"  value="'.$row2[ $row1[ 'FieldName' ]  ]."\" onKeyup=\"locatecontacts( 'reg_list', 0, 0, 'no' )\" autocomplete=\"off\">";
			else
				{
				if ( $edit_view == 'view' )
					$customfields[ 'text' ] .= "<span class=data>".$row2[ $row1[ 'FieldName' ]  ]."</span>";
				else
					$customfields[ 'text' ] .= '<input tabindex='.$tab_text.' type="text" class="txt_edit" id="txt_' . $row1[ 'FieldName' ] .'" name="txt_' . $row1[ 'FieldName' ] . '" value="'.$row2[ $row1[ 'FieldName' ]  ]."\">";
				}
			$tab_text++;
			}

		//*****************************************************
		
		//memo fields
		if ($row1[ 'FieldTypeID' ] == 3 && !$searchable ) 
			{
			//since this is all stored in a single array, and am delimiting each field with a '|' so I can explode and have a count
			//and display separately later
			if ( $customfields[ 'memo' ] != '' )
				$customfields[ 'memo' ] .= '|';

			$customfields[ 'memo' ] .= '<div class=lbl>'.$row1[ 'FieldDisplayName' ] .'</div>';
			
			if ( $edit_view == 'view' )
				$customfields[ 'memo' ] .= "<span class=data>".$row2[ $row1[ 'FieldName' ]  ]."</span>";
			else	
				{
				$customfields[ 'memo' ] .= '<textarea tabindex='.$tab_note.' class="txt_edit" id="txt_' . $row1[ 'FieldName' ] .'" name="txt_' . $row1[ 'FieldName' ] .'" style="width:650px; height:80px;"  wrap="VIRTUAL" >'.prepareNote($row2[ $row1[ 'FieldName' ] ],True).'</textarea><br>
													<input value=" + " class="btn_textarea" onclick="textbox_resize( 100, \'txt_'.$row1[ "FieldName" ].'\' );" type="button">
													<input value=" - " class="btn_textarea" onclick="textbox_resize( -100, \'txt_'.$row1[ "FieldName" ].'\' );" type="button">';
				}
			$tab_note++;
			}
		//*****************************************************

		//integer
		if ($row1[ 'FieldTypeID' ] == 4) 
			{
			//since this is all stored in a single array, and am delimiting each field with a '|' so I can explode and have a count
			//and display separately later
			if ( $customfields[ 'integer' ] != '' )
				$customfields[ 'integer' ] .= '|';

			if ( $searchable )
				$customfields[ 'integer' ] .= "<input tabindex=".$tab_int." type=\"text\" class=\"txt_edit\" id=\"txt_".$row1[ 'FieldName' ]."\" name=\"txt_".$row1[ 'FieldName' ]."\"  value=\"".$row2[ $row1[ 'FieldName' ]  ]."\" onKeyup=\"locatecontacts( 'reg_list', 0, 0, 'no' );\" autocomplete=\"off\">";
			else
				{
				$customfields[ 'integer' ] .= '<div class=lbl>'.$row1[ 'FieldDisplayName' ] .'</div>';
				
				if ( $edit_view == 'view' )
					$customfields[ 'integer' ] .= "<span class=data>".$row2[ $row1[ 'FieldName' ]  ]."</span>";
				else	
					$customfields[ 'integer' ] .= "<input tabindex=".$tab_int." type=\"text\" class=\"txt_edit\" id=\"txt_".$row1[ 'FieldName' ]."\" name=\"txt_".$row1[ 'FieldName' ]."\" value=\"".$row2[ $row1[ 'FieldName' ]  ]."\">";
				}
			$tab_int++;
			}
		//*****************************************************

		//date
		if ($row1[ 'FieldTypeID' ] == 5) 
			{
			//since this is all stored in a single array, and am delimiting each field with a '|' so I can explode and have a count
			//and display separately later
			if ( $customfields[ 'date' ] != '' )
				$customfields[ 'date' ] .= '|';

			if ( $searchable )
				{
				$customfields[ 'date' ] .= '<div class=lbl>'.$row1[ 'FieldDisplayName' ] .'</div>';
				//01.06.2012 ghh - changed formatdate from ,2 to ,4 because these dates are never datetime and therefore don't need to be handled for timezones
				$customfields[ 'date' ] .= '<input tabindex='.$tab_date.' type="text" class="txt_edit" name="txt_' . $row1[ 'FieldName' ] .'" id="txt_' . $row1[ 'FieldName' ] ."\" size=\"10\"  onkeydown=\"Tab_Cal_Close( event.keyCode, 'reg_".$row1[ "FieldName" ]."' );\" onFocus=\"get_cv_datetimepicker( 'reg_" .$row1[ 'FieldName' ]."','txt_" . $row1[ 'FieldName' ] ."' );\" value=\"".FormatDate( $row2[ $row1[ 'FieldName' ] ],4 ).'"><div id="reg_'. $row1[ 'FieldName' ] .'" class="calendar-back"></div>';
				}
			else
				{
				$customfields[ 'date' ] .= '<div class=lbl>'.$row1[ 'FieldDisplayName' ] .'</div>';
				
				if ( $edit_view == 'view' )
					//01.06.2012 ghh - changed formatdate from ,2 to ,4 because these dates are never datetime and therefore don't need to be handled for timezones
					$customfields[ 'date' ] .= "<span class=data>".FormatDate( $row2[ $row1[ 'FieldName' ]  ], 4 )."</span>";
				else	
					//01.06.2012 ghh - changed formatdate from ,2 to ,4 because these dates are never datetime and therefore don't need to be handled for timezones
					$customfields[ 'date' ] .= '<input tabindex='.$tab_date.' type="text" class="txt_edit" name="txt_' . $row1[ 'FieldName' ] .'" id="txt_' . $row1[ 'FieldName' ] ."\" size=\"10\"  onkeydown=\"Tab_Cal_Close( event.keyCode, 'reg_".$row1[ "FieldName" ]."' );\" onFocus=\"get_cv_datetimepicker( 'reg_" .$row1[ 'FieldName' ]."','txt_" . $row1[ 'FieldName' ] .'\' );" value="'.FormatDate( $row2[ $row1[ 'FieldName' ] ],4).'"><div id="reg_'.$row1[ 'FieldName' ].'" class="calendar-back"></div>';
				}
			$tab_date++;
			}
		//*****************************************************
		}	 
	}
return $customfields;
} //end customTableView


//this function handles building a query and returning it to the calling
//function
function customTableUpdate( $tableid, $searchid )
{
global $db;

	$query	= "select TableName, SearchField from cstCustomTables where cstID=" .$tableid;
		
	if ( !$result = $db->sql_query( $query ) )
		{	
		//log error
		LogError( 1041, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		}
	
	$row	= $db->sql_fetchrow( $result ) ;
	$tablename = $row[ 'TableName' ];
	$searchfield = $row[ 'SearchField' ];

	$query	= "select * from cstCustomFields where cstID =" .$tableid. " order by FieldTypeID";
		
	if ( !$result = $db->sql_query( $query ) )
		LogError( 1038, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );

	//we had an issue with the possible situation with nothing being in teh cstCustomFields table and it caused this function to fail
	//so if there are no fields in the table set, we do not run the queries below TKS 3-5-09
	if ( $db->sql_numrows( $result ) > 0 )
		{
		//before we actually try to do an update we're going to try to insert a new record.  This should
		//let the update work propertly which is the next step in the process.  
		$query = "insert into " .$tablename. " (".$searchfield. ") values( " . $searchid. ")";
		$db->sql_query( $query );

		$count = $db->sql_numrows( $result );
		$i	= 2;
		$query = "update " .$tablename. " set ";
		while( $row1	= $db->sql_fetchrow( $result ) )
			{
			if ($row1[ 'FieldTypeID' ] == 1)
				{
				if ( $_POST[ 'chk_' . $row1['FieldName'] ] == true )
					$checked = 1;
				else
					$checked = 0;
				$query .= $row1[ 'FieldName' ] . '=' . $checked;
				if ($count >= $i) 
					$query .= ',';
				$i++;	
				}

			if ($row1[ 'FieldTypeID' ] == 2)
				{
				$query .= $row1[ 'FieldName' ] . "='" . $_POST[ 'txt_' .$row1[ 'FieldName' ] ]."'";
				if ($count >= $i) 
					$query .= ',';
				$i++;	
				}

			if ($row1[ 'FieldTypeID' ] == 3)
				{
				$query .= $row1[ 'FieldName' ] . "='" . prepareNote( $_POST[ 'txt_' .$row1[ 'FieldName' ] ], false )."'";
				if ($count >= $i) 
					$query .= ',';
				$i++;	
				}

			if ($row1[ 'FieldTypeID' ] == 4)
				{
				if ( $_POST[ 'txt_'.$row1[ 'FieldName' ] ] > 0 )
					$intdefault = $_POST[ 'txt_' .$row1[ 'FieldName'] ];
				else
					$intdefault = 0;

				$query .= $row1[ 'FieldName' ] . '=' . $intdefault;
				if ($count >= $i) 
					$query .= ',';
				$i++;	
				}

			if ($row1[ 'FieldTypeID' ] == 5)
				{
				if ( !is_null( $_POST[ 'txt_'.$row1[ 'FieldName' ] ] ) && date( 'Y', strtotime( $_POST[ 'txt_' . $row1[ 'FieldName' ] ] ) ) > 1980 )
					$datedefault = "'".FormatDate($_POST[ 'txt_'.$row1[ 'FieldName' ] ], 1, False )."'";
				else
					$datedefault = "NULL";

				$query .= $row1[ 'FieldName' ] . "=" . $datedefault ;
				if ($count >= $i) 
					$query .= ',';
				$i++;	
				}

			}

	$query .= " where " .$searchfield. "=" . $searchid;

	if ( !$result = $db->sql_query( $query ) )
		LogError( 1035, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	}
} //end customTableUpdates



//this function deals with building up search fields to include if we
//in fact have values that were passed to us
function getCustomSearch( $tableid )
{
global $db;

if ( $tableid > 0 )
	{
	$query	= "select TableName, SearchField from cstCustomTables where cstID=" . $tableid;
		
	if ( !$result = $db->sql_query( $query ) )
		LogError( 1118, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	
	$row	= $db->sql_fetchrow( $result ) ;
	$tablename = $row[ 'TableName' ];
	$searchfield = $row[ 'SearchField' ];

	$query	= "select * from cstCustomFields where cstID = ".$tableid;

	$query .= " and Searchable=1";

	$query .= ' order by FieldTypeID';

	if ( !$result = $db->sql_query( $query ) )
		LogError( 1120, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );

	$where = '';
	$foundcustom = false;
	//now we're going to loop through and see if we have any values that we
	//should return as part of a where clause
	while( $row	= $db->sql_fetchrow( $result ) )
		{		
		//true / false
		if ( $row[ "FieldTypeID" ] == 1 )
			{
			//now see if the field has a value
			$fieldname = "chk_".$row[ 'FieldName' ];
			if ( trim( $_POST[ $fieldname ] ) != '' )
				{
				$foundcustom = true;

				if ( $_POST[ $fieldname ] == true )
					$checked = 1;

				$where .= " and ".$tablename.".".$row[ 'FieldName' ]." = ".$checked;
				}
			
			//now see if the field has a value
			$fieldname = "chk_not".$row[ 'FieldName' ];
			if ( trim( $_POST[ $fieldname ] ) != '' )
				{
				$foundcustom = true;

				if ( $_POST[ $fieldname ] == true )
					$checked = 0;

				$where .= " and ".$tablename.".".$row[ 'FieldName' ]." = ".$checked;
				}
			}

		//text field
		if ( $row[ "FieldTypeID" ] == 2 )
			{
			//now see if the field has a value
			$fieldname = "txt_".$row[ 'FieldName' ];
			if ( trim( $_POST[ $fieldname ] ) != '' )
				{
				$foundcustom = true;

				//10.07.2009 jss - changed from = to like for hot searching
				$where .= " and ".$tablename.".".$row[ 'FieldName' ]." like '".prepareNote( $_POST[ $fieldname ] )."%'";
				}
			}

		//int fields
		if ( $row[ "FieldTypeID" ] == 4 )
			{
			//now see if the field has a value
			$fieldname = "txt_".$row[ 'FieldName' ];
			if ( trim( $_POST[ $fieldname ] ) != '' )
				{
				$foundcustom = true;

				$where .= " and ".$tablename.".".$row[ 'FieldName' ] ." = ".prepareNote( $_POST[ $fieldname ] );
				}
			}

		//date fields
		if ( $row[ "FieldTypeID" ] == 5 )
			{
			//now see if the field has a value
			$fieldname = "txt_".$row[ 'FieldName' ];
			if ( trim( $_POST[ $fieldname ] ) != '' )
				{
				$foundcustom = true;

				//$where .= " and " .$tablename. "." .$row[ 'FieldName' ] .">='". FormatDate( $_POST[ $fieldname ], 3 )." 00:00:00'";
				$where .= " and ".$tablename.".".$row[ 'FieldName' ]." <= '".FormatDate( $_POST[ $fieldname ], 3 )." 23:59:59'";
				}
			}

		}	 

	//lastly we need to tag on the tablename links to make sure things link up properly if we found
	//fields that needed to be included above
	if ( $foundcustom )
		$where .= " and " .$tablename.".".$searchfield."="; //note: user calling this will need to finish the link here since they know the table name it should link to

	return $where;
	}//end if tableid > 0	
}


//this function determines if the user is an employee or not
//TKS 05.31.2012 #19367 we have a situation where we need to hide
//the view icon on contact locate for other employees if not allowed in a 
//security role. The code for isEMployee worked for the single record that
//held the userid but in some searches, the additional contacts are in the
//results allowing you to then bypass since they are not record that is the employee
//so I am now adding contactid to this so it will return true for all addcons under
//a contact if one is employee
function isEmployee( $userid=0, $contactid=0 )
{
if ( $userid > 0 || $contactid > 0) 
	{
	global $db;

	if ( $db->db_connect_id )
		{
		$query	= "select ContactTypeID from conContactType, conAdditionalContacts 
						where conContactType.ContactID=conAdditionalContacts.ContactID
						and ContactTypeID=1 and ";
						
		if ( $userid > 0 )
			$query .= "conAdditionalContacts.UserID = " .$userid;
		else
			$query .= "conAdditionalContacts.ContactID = ".$contactid;
			
		if ( !$result = $db->sql_query( $query ) )
			LogError( 1390, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		
		if ( $db->sql_numrows( $result ) > 0 )
			return true;
		else
			return false;
		}
	}
else
	return false;
}


//this function makes sure person is clocked in if necessary and
//won't let them do anything until they do
function isClockedIn( )
{
global $UserID;
global $db;
global $Company_ID;

//first we need to make sure we're dealing with an employee as they are the only ones required to clock in
if ( isEmployee( $UserID ) )
	{
	//04.28.2011 ghh - added menumodule part to keep it from showing error message when you're working with dashboard controls
	if ( $db->db_connect_id && ( $_COOKIE[ 'ssi_menumodule' ] != 8 ) )
		{
		//TKS 07.25.2012 #21749 added flag per user for force clock in
		//first we check for the user then we go to company if they are set to a 2
		//0 don't force, 1 force, 2 look at company settings
		$query	= "select ForceClockIn from conAdditionalContacts 
						where Login = 1 and UserID = ".$UserID;

		if ( !$result = $db->sql_query( $query ) )
			LogError( 10556, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
			
		$row = $db->sql_fetchrow( $result );
		//***************************
		////TKS 07.25.2012 #21749 if we enter here then they are set to look at company settings which
		//holds same field name and values mean the same so we just re-use row
		if ( $row[ "ForceClockIn" ] == 2 )
			{
			$query	= "select ForceClockIn from optCompanyInfo";
				
			if ( !$result = $db->sql_query( $query ) )
				LogError( 1540, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
			
			$row = $db->sql_fetchrow( $result );
			}

		//has system been set to force clock ins?
		if ( $row[ 'ForceClockIn' ] == 1 )
			{
			//02.20.2012 ghh - if the database is nizex_nizex then we still want to check
			if ( $Company_ID == 22 ) $continue = true; else $continue = false;

			//02.20.2012 ghh - before we force the person to clock in we need to be sure that its not a nizex employee that
			//is logging into a customer database because we don't need to clockin in that condition.
			$query = "select EmailAddress from conAdditionalContacts where UserID=$UserID and EmailAddress Like '%nizex%'";
			if ( !$result = $db->sql_query( $query ) )
				LogError( 9552, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		
			//02.20.2012 ghh - added if and else to this to ignore nizex employees logging into non nizex databases
			if ( $db->sql_numrows( $result ) == 0 || $continue )
				{
				//now we're going to see if the user is clocked in
				$query = "select TimeID from prlTimeClock where TimeOut is null and TimeTypeID=3 and UserID=" .$UserID;
				if ( !$result = $db->sql_query( $query ) )
					LogError( 1541, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
			
				if ( $db->sql_numrows( $result ) > 0 )
					return true;
				else
					return false;
				}
			else
				return true;
			}
		else
			return true;
		}
	else
		return true;
	}
else
	return true;
}

//05.29.2009 jss - this functions sets the locale
//now, in the future, we're gonna have to be able to set this per user, ie some companies may have employees in different
//countries, so we can't really do it by company...
function set_locale()
{
setlocale(LC_MONETARY, "en_US.utf8");	
//setlocale(LC_MONETARY, "no_NO");	
}

//05.27.2009 jss - this function converts #'s to currency and passes it back
//06.26.2013 : CMK : # 36926 : adding paranthesis option for financial statments
function currency( $Amount, $AddCurrSymbl=True, $KeepDecimal = false, $useParen = false, $Rounded = false )
{
set_locale( LC_ALL, '');
$Amount = trim( $Amount );
//02.23.2012 ghh - added to strip out commas and other stuff that may exist and then convert it
//back to a number with formatting if needed
$Amount = preg_replace( '/[^\-0-9.]/','', $Amount );

//03.05.2013 naj - added support for keeping decimals past 2 places
if ($KeepDecimal)
	$Places = strlen(substr(strrchr(floatval($Amount), "."), 1));
else
	$Places = 2;

//03.05.2013 naj - make sure we have at least 2 decimal places
if ($Places < 2)
	$Places = 2;

//03.04.2015 naj - added Rounded override flag to allow for reports that are rounded to the nearest
//whole dollar.
if ($Rounded)
	$Places = 0;

if ( trim( $Amount ) == '' || is_null( $Amount ) || empty( $Amount ) )
	$Amount = 0;

//06.26.2013 : CMK : # 36926 : added else if ( $useParen ) and its option
if ( $AddCurrSymbl )
	return money_format("%#0.".$Places."n",$Amount);
else if ( $useParen )
	return money_format('%(#0.'.$Places.'n',$Amount);
else
	//07.14.2010 jss - added "trim" cuz it's putting a blank space at the beginning
	return trim( money_format("%!#0.".$Places."n",$Amount) );

}

function formatInt( $Amount )
{
set_locale( LC_ALL, '');

if ( trim( $Amount ) == '' || is_null( $Amount ) || empty( $Amount ) )
	$Amount = 0;

$temp = explode( '.', money_format("%!#9.2n",$Amount) );
return trim( $temp[0] );
}
//11.24.2009 ghh this function attempts to replace region specific
//formating with nothing so that we can work with the number
function parseFloat( $val, $removedecimal=false )
{
set_locale( LC_ALL, '');
$Locale = localeconv();
$val = str_replace($Locale["mon_thousands_sep"] , "", $val);
$val = str_replace($Locale["mon_decimal_point"] , ".", $val);

if ( $removedecimal )
	{
	$temp1 = explode( '.', $val );
	$val = trim( $temp1[ 0 ] );
	}

return $val; 
}

//05.28.2009 jss - this function converts currency to double
function strip_currency( $Amount )
{
set_locale();

//03.06.2015 naj - replace this with a more efficient method, cause damn.
$result = preg_replace('/[^0-9-.]/', '', $Amount);

if (empty($result))
	return 0.00;
else
	return $result;
/*
$result = '';
for ( $i = 0; $i < strlen( $Amount ); $i++ )
	{		
	$s = substr( $Amount, $i, 1 );
	if ( $s == '0' 
		|| $s == '1' 
		|| $s == '2' 
		|| $s == '3' 
		|| $s == '4' 
		|| $s == '5' 
		|| $s == '6' 
		|| $s == '7' 
		|| $s == '8' 
		|| $s == '9' 
		|| $s == '.'
		|| $s == '-'
		) 
		$result = $result.$s;
	}	 
if ( $result == '' )
	$result = 0.00;
return trim( $result );
*/
}


//this function checks to see if there is already an index.php file in 
//the directory you give it, if not, creates one, and writes a message and closes it.
//this dummy file prevents people from opening the directory's contents in the browser
function index_dummy( $temp_path )
{
/*
TKS 6-30-10 commeted out becuase Noel said we no longer need it. It is creating files on teh server we no longer
need since the current settings do not allow someone the ability to view a dir contents in the browser
$found = false;
//check to see if dir exists
if ( is_dir( $temp_path ) )
	{
	//open dir and look for an index file
	$handle = opendir( $temp_path );
	while ( ( $file = readdir( $handle ) ) !== false ) 
		{
		if ( $file == 'index.php' )
			$found = true;
		}
	closedir( $handle );//close dir
	
	//if file does not exist we create it
	if ( !$found )
		{
		exec( "touch ".$temp_path."/index.php" );
		//if creation was successful, open and write a line to it
		if ( file_exists( $temp_path."/index.php" ) )
			{
			$myFile = $temp_path."/index.php";
			$fh = fopen( $myFile, 'w' );

			$stringData = "You have reached this page in error.";
			fwrite( $fh, $stringData );//write to file
			fclose( $fh );//close file					
			}
		}
	}*/
}//end function



//returns number of days between 2 dates
function DiffBetweenDates( $start, $end )
{
$month 	= date( "m", strtotime( $start ) );
$day 		= date( "d", strtotime( $start ) );
$year 	= date( "Y", strtotime( $start ) );
$month2 	= date( "m", strtotime( $end ) );
$day2 	= date( "d", strtotime( $end ) );
$year2 	= date( "Y", strtotime( $end ) );

$first_date = MKTIME( 12, 0, 0, $month, $day, $year);
$second_date = MKTIME( 12, 0, 0,$month2, $day2, $year2);
 
$offset = $second_date - $first_date;
  
return FLOOR( $offset/60/60/24 );
}

//10.12.2009 jss - this function will strip everything except #'s, used to sending to skype
function formatPhoneForSkype( $phone )
{
//03.06.2015 naj - replace this with a more efficient method, cause damn.
$result = preg_replace('/[^0-9]/', '', $phone);

/*
$result = '';
for ( $i = 0; $i < strlen( $phone ); $i++ )
	{		
	$s = substr( $phone, $i, 1 );
	if ( $s == '0' 
		|| $s == '1' 
		|| $s == '2' 
		|| $s == '3' 
		|| $s == '4' 
		|| $s == '5' 
		|| $s == '6' 
		|| $s == '7' 
		|| $s == '8' 
		|| $s == '9' 
		) 
		$result = $result.$s;
	}	 
*/

//10.12.2009 jss - if the phone # entered has a 1 in front of it, like 1-800-555-5555, strip it off
if ( strlen($result) == 11 && substr( $result, 0, 1 ) == '1' )
	$result = substr( $result, 1, 10 );	

return $result;

}


//TKS 07.21.2011 added to return the default primary phone for a contact
function ContactPrimaryPhone( $contactid )
{
//TKS 10.19.2011 getting error from checklist alert so to safegaurd against this getting called without
//a contactid, we return if not > 0
if ( !$contactid > 0 )
	return;

global $db;

if ( $db->db_connect_id )
	{
	$query	= "select conAdditionalContacts.Phone from conAdditionalContacts, conPhoneTypes where 
					conAdditionalContacts.ContactID = ".$contactid." 
					and conAdditionalContacts.PhoneTypeID = conPhoneTypes.PhoneTypeID and conPhoneTypes.DefaultType = 1";
		
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 8119, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		exit;
		}

	$row	= $db->sql_fetchrow( $result ); 
	}
return $row[ "Phone" ];
}


//I found this function at http://www.linuxjournal.com/article/9585
//TKS
/**
Validate an email address.
Provide email address (raw input)
Returns true if the email address has the email 
address format and the domain exists.
*/
function validEmail( $email )
{
//01.11.2012 naj - Something is calling this function but not passing a string
//Added this check to see if I can catch what is comming in and try to fix it.
if (!is_string($email) && !empty($email))
	{
	error_log(print_r(debug_backtrace(), true), 3, "/var/log/lizzy/validemail.log");
	error_log(print_r($email,true), 3, "/var/log/lizzy/validemail.log");
	}

//11.07.2014 naj - The old validEmail checks were allowing lots of things through that were not actually valid.
//However it turns out that PHP has a checker for email addresses, so we did not need all the stuff below.

if (filter_var($email, FILTER_VALIDATE_EMAIL)) 
	$isValid = true;
else
	$isValid = false;

return $isValid;
}




//this function returns the primary email address for a contactid passed in ( if any )
//keep in mind that you will still want to run our validEmail() just in case the email
//address is invalid from an import process or old data
//TKS 4-13-10 added ability to pass in userid to get the email address of a user
function PrimaryEmail( $contactid, $userid=0 )
{
//TKS 10.13.2011 if neither are passed in return
if ( !$contactid > 0 && !$userid > 0 )
	return false;

global $db;

if ( $db->db_connect_id )
	{
	if ( $contactid > 0 )
		$query	= "select EmailAddress from conAdditionalContacts where ContactID = ".$contactid." and
						PrimaryContact = 1";
	else
		$query	= "select EmailAddress from conAdditionalContacts where UserID = ".$userid;

	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 3488, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}

	$row	= $db->sql_fetchrow( $result );
	//12-30-10 TKS added trim()
	return trim( $row[ "EmailAddress" ] );
	}
}//END of PrimaryEmail()


//01.12.2010 jss - this function returns a mechanics payrate
//11.15.2011 jss - function no longer used
/*
function getMechanicPayRate( $userid, $unittypeid=0 )
{
global $db;
global $lang;
global $RootPath;
global $language;
global $domain;

$payrate = 0;

if ( $unittypeid != 0 )
	{
	$query  = 'select * from optMechanicUnitTypeLink
	           where UserID = ' . $userid . '
				  and UnitTypeID = ' . $unittypeid;

	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 3972, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}

	if ( $db->sql_numrows( $result ) > 0 )
		{
		$row	= $db->sql_fetchrow( $result );
		$payrate = $row[ 'PayRate' ];
		}

	}
if ( $payrate == 0 )
	{
	$query  = 'select * from optMechanicInfo
	           where UserID = ' . $userid;

	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 3973, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}

	if ( $db->sql_numrows( $result ) > 0 )
		{
		$row	= $db->sql_fetchrow( $result );
		$payrate = $row[ 'PayRate' ];
		}
	}
return $payrate;
}//end getMechanicPayRate
*/

//01.25.2010 ghh this function takes a number and strips off trailing zeros and possibly the
//decimal point itself
function stripTrailingZeros( $number, $currency=false )
{
if ( trim( $number ) == '' )
	return '';

//03.05.2013 naj - replaced all the code below with some php functions that do the same job.
$number = floatval($number);

//03.05.2013 naj - if currency is set to true make sure we have at least 2 decimal places.
if ($currency && strlen(substr(strrchr(floatval($Amount), "."), 1)) < 2)
	number_format(floatval($number), 2);

return floatval($number);
}


//06.03.2014 er - (52571) For multiple reasons we need to know if the 
//						sale is for an out-of-state customer
function InStateSale( $invoiceid )
{
$CompanyState = getCompanyState();
$InvoiceContactState = getInvoiceContactState( $invoiceid );
if ( $CompanyState == $InvoiceContactState )
	$InStateSale = 1;
else
	$InStateSale = 0;

return $InStateSale;
}


//03.17.2010 ghh added to get the state of the company for F&I and tax
//purposes.
function getCompanyState()
{
global $db;
if ( $db->db_connect_id )
	{
	$query	= "select Abbreviation from genStates, optCompanyInfo, conZipCode
						where conZipCode.ZipID=optCompanyInfo.ZipID and genStates.StateID=conZipCode.StateID";
		
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 4275, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		exit;
		}

	$row	= $db->sql_fetchrow( $result );

	return $row[ 'Abbreviation' ];
	}
}//end company state


//06.03.2014 er - (52571) Often times we need to know the customer's state
function getInvoiceContactState( $invoiceid )
{
global $db;
if ( $db->db_connect_id )
	{
	$query	= "select invInvoice.ContactID
						from invInvoice
						where invInvoice.InvoiceID = ".$invoiceid; 
		
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 14756, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		exit;
		}

	$row	= $db->sql_fetchrow( $result );

	$query	= "select conContactInfo.ZipID, conZipCode.StateID, genStates.Abbreviation
						from conContactInfo, conZipCode, genStates
						where conContactInfo.ContactID = ".$row[ 'ContactID' ]." 
						and conZipCode.ZipID = conContactInfo.ZipID
						and genStates.StateID = conZipCode.StateID";
		
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 14757, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		exit;
		}

	$row	= $db->sql_fetchrow( $result );

	return $row[ 'Abbreviation' ];
	}
}//end contact state


//06.17.2013 er added to get the company country code.
function getCompanyCountry()
{
global $db;
if ( $db->db_connect_id )
	{
	$query	= "select CountryID from optCompanyInfo, conZipCode
						where conZipCode.ZipID=optCompanyInfo.ZipID";
		
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 12631, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		exit;
		}

	$row	= $db->sql_fetchrow( $result );

	return $row[ 'CountryID' ];
	}
}//end company country


//TKS 7-27-10. I found that I was having to run a check in multiple places to see
//if the user has Email Module access in order to make a button show or an email address
//clickable. SO I wrote this function to just return true or false to keep things simple
function HasEmailModuleSetup( )
{
global $UserID;
global $dbhost;
global $dbuser;
global $dbpasswd;
global $supportdb;

//04.11.2011 ghh - changed to use supportdb alias
$db_support = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true );

if ( $db_support->db_connect_id )
	{
	//make sure they have the recieve mail setup
	$query	= "select EmailUserName from optEmailProcessing 
					where UserID = ".$UserID." 
					and CompanyID = ".$_SESSION[ 'UserInfo' ][ 'CompanyID' ]." 
					and SMTPServerName != ''";
		
	if ( !$result = $db_support->sql_query( $query ) )
		{
		LogError( 5277, $query .$dblang[ "ErrorInSQL" ]."<br>".$db_support->sql_error() );
		exit;
		}

	if ( $db_support->sql_numrows( $result ) == 0 )
		{
		$db_support->sql_close();
		return false;
		}
	}

$db_support->sql_close();
return true;
}//End of HasEmailModuleSetup

//TKS 8-30-10 #1582 this function handles building our date fields
//field_id is name/id of html element. cal_region div for popup calendar, mod_val is what to populate the field's value with, 
//class is if the field needs a specific CSS class like 'required', format_flag is the 1-4 formating flag used in the FormatDate()
//pass in zero to leave date as is, like in the case where the date may have been already formated and passed to a control via query string.
//04.04.2011 : CMK : # 3279 : when testing and hand entering dates ( not picking from calander ) the code in js_datetimepicker was not
//being executed.  Adding an on change event to the build date function so that it will handle the hand entry part.  The onchange event is going 
//call the ProcessDateChange function in the js_datetimepicker.js file
//TKS 09.16.2011 #7312 changing CK's change=false to hold the string for the function call needed onchange of the date
function BuildDateField( $field_id, $cal_region='reg_calendar1', $tabindex='', $val='', $class='', $format_flag=0, $disabled=0, $change='' )
{
//02.07.2012 ghh - added to make sure if change has no value that we set it to equal an empty set of ticks
//this was not happening earlier and it was breaking all the code if you didn't pass in a change function
if ( trim( $change ) == '' )
	$change = "''";

//onkeydown we have a selectDay() that if the user hits spacebar, we take the hidden field that holds today's date in the user's current format
//and fill in the field. Tab_Cal_Close closes the calendar popup when they hit tab 
if ( $format_flag == 0 )
	$date = $val;
else
	$date = FormatDate( $val, $format_flag );

//11.05.2010 jss - if date ends up being null, display it as null, not as 12/31/1969
if ( $date == '12/31/1969' )
	$date = '';

//01.19.2011 jss - 
if ( $disabled == 1 )
	$disabled = ' disabled ';
else
	$disabled = '';

//this is temporary for now until I get all places updated then we will remove this 
/*
//TKS 02.06.2012 I updated all places that used ProcessDateChanged to no longer rely on it
if ( $change == '' )
	$func = "ProcessDateChanged( '".$field_id."', this.value )";
else
	$func = $change;
*/

//TKS 01.27.2012 the $change var is passed in with ticks around it becuase it gets passed into the selectDay() for the calendar
//and this has to be there for it work. However, the same var is used in the onCHange event of the field and the tickets around the function are breaking it
//it would have been simpler to just pass in the save function and handle all the ticks in here but the onChange was added much later and instead of going 
//on a search for all the places, I am just going to remove the ticket here.
if ( $change != '' && substr( $change, 0, 1 ) == "'" )
	{
	//remove the ticks on the outside and remove the slashes
	$onchange = stripslashes( substr( $change, 1, strlen( $change )-2 ) );
	}
else
	$onchange = $change;


//TKS 09.16.2011 #7312 added parameter to selectDay for function you pass in
//TKS 09.22.2011 changed the function call on tab to call selectDay and pass in '' for date
//this allows us to not only close the window but call any set save() to clear out the date.
//07.03.2012 : CMK : # chaning the width to 100px as 80 is cutting off date #s
$date 	= "<input tabindex=\"".$tabindex."\" type=\"text\" ".$disabled." 
						id=\"".$field_id."\" name=\"".$field_id."\" 
						class=\"".$class."\" style=\"width:100px;\"  
						onkeyup=\"if ( event.keyCode == 32 ){selectDay( document.getElementById( 'global_todaysdate' ).value, '".$field_id."', '".$cal_region."', ".$change." );}\" 
						onkeydown=\"if( event.keyCode == 9 )
										{
										if ( document.getElementById( '".$field_id."' ).value =='' )
											{ 
											selectDay( '', '".$field_id."', '".$cal_region."' );
											}
										else
											{
											document.getElementById( '".$cal_region."' ).innerHTML = '';
											}
										}\" 
						onFocus=\"get_cv_datetimepicker( '".$cal_region."','".$field_id."', ".$change." );\" 
						value=\"".$date."\" onChange=\"".$onchange."\" >
						<div id=\"".$cal_region."\" class=\"calendar-back\"></div>";
return $date;
}


//09.27.2010 jss - this function gets the LocationID (work location) of the logged in user
function getUserLocation()
{
global $db;
global $UserID, $Company_ID;

//03.24.2011 jss - lizzy is 402, and if i run compare on nizex it doesn't have lizzy as a user, so just return locationid 1
if ( $UserID == 402 && $Company_ID == 22 )
	return 1;

if ( $db->db_connect_id )
	{
	$contactid = getUserContactID( $UserID );

	//01.04.2012 ghh - changed this to always grab 1 if the field happens to be blank 
	$query = 'select ifnull(WorkLocation,1) as WorkLocation from conContactInfo where ContactID = ' . $contactid;
	if ( !$result = $db->sql_query( $query ) )
		{
		if ( $UserID != 88 )
			LogError( 6054, $query . $UserID .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return 1;
		}

	$row = $db->sql_fetchrow( $result );

	//09.28.2010 jss - if worklocation is 0 set it to 1, the default location for the store
 
	if ( $row[ 'WorkLocation' ] == 0 || $db->sql_numrows( $result ) == 0 )
		$row[ 'WorkLocation' ] = 1;

	return $row[ 'WorkLocation' ];
	}
else
	return 1;
}

//09.29.2010 jss - this function returns the location name
function getLocationName( $locationid )
{
global $db;

if ( !$locationid > 0 )
	return '';

if ( $db->db_connect_id )
	{
	$query = 'select Name from itmBinLocations where LocationID = ' . $locationid;
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 6055, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return '';
		}
	$row = $db->sql_fetchrow( $result );
	return $row[ 'Name' ];
	}
}


//10.05.2010 jss - this function returns the locationid of a specific binid
function getBinsLocationID( $binid )
{
global $db;

if ( $db->db_connect_id )
	{
	if ( $binid == '' )
		$binid = 0;

	if ( !$binid == 0 )
		{
		$query = 'select LocationID from itmBins where BinID = ' . $binid;
		if ( !$result = $db->sql_query( $query ) )
			{
			LogError( 6056, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
			return 1;
			}
		$row = $db->sql_fetchrow( $result );
		if ( !$row[ 'LocationID' ] == 0 )
			return $row[ 'LocationID' ];
		else
			return 1;
		}
	else
		return 1;
	}
else
	return 1;
}


//06.17.2010 ghh this function determines whether an account should be hit
//for departmental accounting purposes.
//05.24.2011 jss - moved here from gl class 
function isAccountDepartmental( $acctid )
{
global $db;

if ( !$acctid > 0 )
	return false;

if ( $db->db_connect_id )
	{
	$query	= "select TypeID from actSubType, actCOA where
						actCOA.SubTypeID=actSubType.SubTypeID and
						actCOA.AcctID=".$acctid;
		
	if ( !$result = $db->sql_query( $query ) )
		{
		//$this->Error .= '( 7615 )'.$query."<br>".$db->sql_error();
		LogError( 7615, $query . "<br>".$db->sql_error() );
		return false;
		}

	$row	= $db->sql_fetchrow( $result );

	//if type is 4,5,6 (income, expense, COG) then we return true
	if ( $row[ 'TypeID' ] > 3 )
		return true;
	else	
		return false;
	}
return false;
}//end isaccountdepartmental

//05.24.2011 jss - this function builds an image link that will open up the cv_department_breakdown form
//and is used from all over lizzy, including checks, po's, invoices, petty cash, basically anywhere a p&l account might get
//hit from, so that's why i've added it to this functions file
function buildDepartmentBreakdownImage( $comingfrom, $id )
{
global $db;
global $domain;
global $usetemplate;
global $lang;
global $RootPath;
global $language;

include_once($RootPath."/language/".$language."/includes/general_functions.inc");

//07.28.2011 jss - replaced with call to new function
/*
$query = 'select UseDepartmentalPopups from optAccountingDefaults';
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 7626, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	return '';
	}
$row = $db->sql_fetchrow( $result );

if ( $comingfrom == '' || !$id > 0 || $row[ 'UseDepartmentalPopups' ] == 0 )
	return '';
*/
if ( $comingfrom == '' || !$id > 0 || !useDepartmentalPopups() )
	return '';
return "<a href=\"javascript:get_cv_department_breakdown( '" . $comingfrom . "', " . $id . " );\" title=\"" . $lang[ 'hpl_DeptBreakPopup' ]."\"><img src=\"".$domain."/templates/".$usetemplate."/images/M105.png\" border=0></a>";
}

//07.28.2011 jss -	
function useDepartmentalPopups()
{
global $db;
global $lang;
if ( $db->db_connect_id )
	{
	$query = 'select UseDepartmentalPopups from optAccountingDefaults';
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 8061, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}
	$row = $db->sql_fetchrow( $result );
	if ( $row[ 'UseDepartmentalPopups' ] == 1 )
		return true;
	else
		return false;
	}
return false;
}

//02.29.2012 jss - ( 14230 )
function useDepartmentalAccounting()
{
global $db;
global $lang;
if ( $db->db_connect_id )
	{
	$query = 'select UseDepartmentalAccounting from optAccountingDefaults';
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 9648, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}
	$row = $db->sql_fetchrow( $result );
	if ( $row[ 'UseDepartmentalAccounting' ] == 1 )
		return true;
	else
		return false;
	}
return false;
}

//08.05.2011 jss - this function gets the invtype of an invoice
function getInvType( $invoiceid )
{
global $db;
if ( $db->db_connect_id )
	{
	$query = 'select InvType from invInvoice where InvoiceID = ' . $invoiceid;
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 8118, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		exit;
		}
	$row = $db->sql_fetchrow( $result );
	return $row[ 'InvType' ];
	}
}


//08.08.2011 jss - this function creates and returns a hotlink, or an image that links to various things like Inovices, POs, Checks, etc.
//06.06.2013 jss - ( 35794 ) added $shoinvtype var
function hotLink( $type, $id, $displayvalue='', $useimage=false, $functioncall='', $placement='reg_body', $showinvtype=false )
{
global $db;
global $lang;
global $domain;
global $usetemplate;
global $RootPath;
global $language;
global $masterdatabase;
//global $modeldatabase;
global $UserID;

include_once($RootPath."/language/".$language."/includes/general_functions.inc");

if ( $id == '' || $id == 0 )
	return '';

$authorized = false;

//set some var's based on $type
switch( $type )
	{
	//Invoice (lightbox)
	//08.23.2012 jss - ( 23166 ) changed case 1 to bring the invoice up in a lightbox.  The old
	//case 1 is now case 9 and it takes you to the invoice section and pulls it up.
	case 1:
		if ( isAuth( 1,81,232,211 ) )
			$authorized = true;

		$useimage = true;

		$displayvaluequery = 'select CustomInvoiceNumber as DisplayValue 
									from invInvoice where InvoiceID = ' . $id;
		//TKS 11.13.2013 #43002 got rid of globalInvoiceID being set when loading in overlay
		//01.20.2014 naj - changed get_mv_view_invoice to get_mv_viewinvoice, since we have moved to
		//the new invoice format.
		$myfunctioncall .= '
			get_menu_includes( 231,81 );
			get_menu_includes( 628,98 );
			get_menu_includes( 636,98 );
			globalOverlayInvoiceID=' . $id . '; 
			globalOverlayContactID = document.getElementById( \'global_contactid\' ).value;
			var tempmaster = readCookie( \'ssi_menumaster\' );
			var tempmenu	= readCookie( \'ssi_menuid\' );
			createCookie( \'ssi_menuid\', tempmenu );
			createCookie( \'ssi_menumaster\', tempmaster );
			get_mv_viewinvoice( \'overlay2\' );';
		//$title = $lang[ 'ViewInvoice' ];
		$title = $lang[ 'hpl_ViewInvoice' ];

		//04.25.2013 jss - for me, get the invoice location and add it to the image title
		if ( compareSecurityLevel($UserID) == 1 )
			{
			include_once( "$RootPath/invoicing/general_invoice_functions.php" );
			$invoicelocation = " (" . getLocationName(getInvoiceLocation($id)) . ")";
			$title .= $invoicelocation;

			//05.21.2013 jss - add customer name to hint for compare user
			$query3 = "select ContactID from invInvoice where InvoiceID = $id";
			if ( !$result3 = $db->sql_query( $query3 ) )
				{
				LogError( 000, $query3 ."<br>".$db->sql_error() );
				return false;
				}
			$row3 = $db->sql_fetchrow( $result3 );

			////08.16.2013 ghh - added var so we only call once
			$rn = contactRealName( $row3[ 'ContactID' ] );
			$title .= ' - ' . $rn;
			$invoicelocation .= ' - ' . $rn;
			}

		$myfunctioncall2 .= 'globalInvoiceID=' . $id . '; get_menu_getpage( \'' . $placement . '\', 232, 81 );';

		break;
	//PO
	case 2:
		if ( isAuth( 3,50,292,273 ) )
			$authorized = true;

		//12.14.2012 jss - ( 27855 ) changed to run the query here instead so we can add the 
		//consignment flag if needed
		//$displayvaluequery = 'select PONumber as DisplayValue from actPO where POID = ' . $id;
		$query = "select PONumber, ConsignmentPartsPO, LocationID from actPO where POID = $id";
		if ( !$result = $db->sql_query( $query ) )
			{
			LogError( 000, $query ."<br>".$db->sql_error() );
			return false;
			}
		$row = $db->sql_fetchrow( $result );
		$displayvalue = $row[PONumber];
		if ( $row[ConsignmentPartsPO] )
			$displayvalue .= "(C)";
		


		$myfunctioncall .= "globalPOID=$id;"; 

		//12.07.2011 jss - changed to take you to the accounting module first cuz the bin controls look at cookies to see what module you're in
		//and act accordingly when selecting a bin.  Calling a po from the inventory section was causing the wrong "select" links to appear on it.
		//$myfunctioncall .= 'document.getElementById( \'header2\' ).innerHTML = \'Accounting\'; get_menu_master( \'reg_left1\', 3 );';
		//06.04.2012 jss - no longer needed as tim and i have re-written the header
		//$myfunctioncall .= "document.getElementById( 'header2' ).innerHTML = '" . $lang[ 'accounting' ] . "';"; 

		$myfunctioncall .= "get_menu_master( 'reg_left1', 3 );";
		$myfunctioncall .= "try { get_menu_main( 'mnu_Payables', 50 ); } catch(err) {}";

		$myfunctioncall .= "get_menu_getpage( '$placement', 292, 50 );";
		$title = $lang[ 'hpl_ViewPO' ];

		//04.25.2013 jss - for me, get the invoice location and add it to the image title
		if ( compareSecurityLevel($UserID) == 1 )
			{
			$title .= " ( " . getLocationName($row['LocationID']) . " )";
			}
		break;
	//Unit (vin)
	case 3:
		if ( isAuth( 16, 65, 181, 160 ) )
			$authorized = true;
		$displayvaluequery = 'select VIN as DisplayValue from untUnitInfo where UnitID = ' . $id;
		//TKS 09.05.2012 added the closing of video_overlay since unit controls are loading in overlay
		//in new invoice flow. 
		$myfunctioncall .= 'globalUnitID=' . $id . '; get_menu_getpage( \'' . $placement . '\', 181, 65 ); close_overlay( \'video_overlay\' );';
		$title = $lang[ 'hpl_ViewUnit' ];
		break;
	//Unit (stockno)
	case 4:
		if ( isAuth( 16, 65, 181, 160 ) )
			$authorized = true;
		$displayvaluequery = 'select StockNo as DisplayValue from untUnitInfo where UnitID = ' . $id;
		//TKS 09.05.2012 added the closing of video_overlay since unit controls are loading in overlay
		//in new invoice flow. 
		$myfunctioncall .= 'globalUnitID=' . $id . '; get_menu_getpage( \'' . $placement . '\', 181, 65 ); close_overlay( \'video_overlay\' );';
		$title = $lang[ 'hpl_ViewUnit' ];
		break;
	//Unit (model#)
	case 5:
		if ( isAuth( 16, 65, 181, 160 ) )
			$authorized = true;

		//TKS 09.05.2012 added the closing of video_overlay since unit controls are loading in overlay
		//in new invoice flow. 
		//11.26.2011 ghh - changed masterdatabase to modeldatabase to work with new database for model numbers
		//05.10.2013 jss - removed $modeldatabase var as it's old and the table is back to being local
		/*
		$displayvaluequery = 'select t2.ModelNumber as DisplayValue
										from ( untUnitInfo as t1, ' . $modeldatabase . '.untModels as t2 )
										where t1.UnitID = ' . $id . '
										and t1.ModelID = t2.ModelID';
										*/
		$displayvaluequery = "select t2.ModelNumber as DisplayValue
										from ( untUnitInfo as t1, untModels as t2 )
										where t1.UnitID = $id
										and t1.ModelID = t2.ModelID";
		$myfunctioncall .= 'globalUnitID=' . $id . '; get_menu_getpage( \'' . $placement . '\', 181, 65 ); close_overlay( \'video_overlay\' );';
		$title = $lang[ 'hpl_ViewUnit' ];
		break;
	//Part #
	case 6:
		if ( isAuth( 9, 44, 208, 187 ) )
			$authorized = true;
		//if ( ( $id ) < 5000000 )
			$displayvaluequery = 'select ItemNumber as DisplayValue from itmItems 
										where ItemID = ' . $id;

		$myfunctioncall .= 'global_item=' . $id . ';';

		//06.26.2012 jss - recently changed to bring up the inventory module, but due to complaints
		//i added an if statement to only do it for me
		if ( compareSecurityLevel($UserID) == 1 )
			$myfunctioncall .= "get_menu_master( 'reg_left1', 9 );";

		$myfunctioncall .= 'get_menu_getpage( \'' . $placement . '\', 208, 44 );';
		//TKS 08.23.2013 #39971 added the close_overlay call as there are cases where you 
		//click on a part # link and in overlay and it needs to close
		$myfunctioncall .= 'close_overlay(\'video_overlay\' );';
		$title = $lang[ 'hpl_ViewItem' ];
		break;

	//ARID
	case 7:
		if ( isAuth( 3, 49, 290, 270 ) )
			$authorized = true;
		$displayvaluequery = 'select ARID as DisplayValue, ContactID
										from actAR
										where ARID = ' . $id;

		//09.28.2011 jss - get contactid
		$query = $displayvaluequery;
		if ( !$result = $db->sql_query( $query ) )
			{
			LogError( 8472, $query ."<br>".$db->sql_error() );
			exit;
			}
		$row = $db->sql_fetchrow( $result );
		$contactid = $row[ 'ContactID' ];

		$myfunctioncall .= 'document.getElementById( \'global_contactid\' ).value = \'' . $contactid . '\';
									get_menu_getpage(\'reg_body\',447,81);
									get_menu_master(\'reg_left1\',3);
									get_menu_main( \'mnu_Receivables\', 49);
									get_menu_getpage( \'reg_body\', 290,49 );';

		$title = $lang[ 'hpl_ViewAR' ];
		break;
	
	//CHECKID
	case 8:
		if ( isAuth( 3, 62, 168, 147 ) )
			$authorized = true;

		$displayvaluequery = 'select CheckNumber as DisplayValue from chkChecks where CheckID = ' . $id;

		//04.11.2012 jss - get more checking info in order to create myfunctioncall
		$query = "select * from chkChecks where checkid = $id";
		if ( !$result = $db->sql_query( $query ) )
			{
			LogError( 000, $query ."<br>".$db->sql_error() );
			exit;
			}
		$row = $db->sql_fetchrow( $result );

		//$myfunctioncall .= 'chk_reg_datefrom = \'\';';
		//$myfunctioncall .= 'chk_reg_dateto = \'\';';
		$myfunctioncall .= 'chk_reg_account = ' . $row[ 'AccountID' ] . ';';
		$myfunctioncall .= 'chk_reg_checknum = \'' . $row[CheckNumber] . '\';';
		$myfunctioncall .= 'chk_reg_checkamount = \'\';';

		//06.04.2012 jss - no longer needed as tim and i have re-written the header
		//$myfunctioncall .= 'document.getElementById( \'header2\' ).innerHTML = \'Accounting\';';

		$myfunctioncall .= 'get_menu_master( \'reg_left1\', 3 );';
		$myfunctioncall .= 'get_menu_main( \'mnu_Checking\', 62 );';
		$myfunctioncall .= 'get_menu_getpage( \'reg_body\', 168, 62 );';

		$title = $lang[ 'hpl_ViewCheck' ];
		break;
	}

//get displayvalue if it's not passed in ( ie.  Invoice #, PO #, Check #, etc... )
if ( $displayvalue == '' )
	{
	if ( !$result = $db->sql_query( $displayvaluequery ) )
		{
		LogError( 8148, $displayvaluequery .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		exit;
		}
	$row = $db->sql_fetchrow( $result );
	$displayvalue = $row[ 'DisplayValue' ];
	}

#######################################################################################################################

if ( $authorized )
	{
	//start building the anchor
	$link = '<a href="javascript:';

	//build the link function
	if ( $functioncall == '' )
		$link .= $myfunctioncall;
	else
		$link .= $functioncall;

	$link .= '"';

	//IMAGE TITLE
	$link .= ' title="' . $title . ' ..."';

	//05.15.2013 jss - 
	$link .= ' tabindex="-1"';

	$link .= '>';

	//IMAGE
	if ( $useimage )
		{
		switch( $type )
			{
			case 1:
				$imagename = 'overlay_icon.png';
				break;
			case 9:
				$imagename = 'overlay_icon.png';
				break;
			default:
				$imagename = 'view.png';
				break;
			}
		$link .= "<img src=\"$domain/templates/$usetemplate/images/$imagename\" border=\"0\">";
		}
	else
		$link .= $displayvalue;

	//close the anchor
	$link .= '</a>';

	//08.31.2012 jss - if doing an invoice link, we'll have something in $myfunctioncall2 which means 
	//we will display both an icon ( used for lightbox ) and a link ( that takes you to the invoice section )
	//04.25.2013 jss - added invoice location for me
	//05.15.2013 jss - added tabindex="-1"
	if ( $myfunctioncall2 != '' )
		//$link .= ' <a href="javascript:' . $myfunctioncall2 . '"' . ' title="' . $lang[hpl_GoToInvoice] . '">' . $displayvalue;
		$link .= " <a href=\"javascript:$myfunctioncall2\" tabindex=\"-1\" title=\"$lang[hpl_GoToInvoice] $invoicelocation\">$displayvalue</a>";
	}
//TKS 08.23.2013 got rid of span class=data as the class is no longer used
else
	$link = $displayvalue;

//06.06.2013 jss - ( 35794 ) add invtype icon to end of invoice # link
if ( $type == 1 && $showinvtype )
	{
	$invtype = getInvType( $id );
	include_once( "$RootPath/invoicing/general_invoice_functions.php" );
	$link .= ' '.getInvoiceType( $invtype, false );
	}

return $link;
}


//09.20.2011 ghh - this function takes any and all post and get vars and 
//adds slashes to them to prevent sql injection attempts.  It is called in
//common.php which is loaded 100% of the time with all controls
function cleanVars()
{
foreach ($_POST as $key=>$value)
	{
	//10.02.2013 naj - added support for arrays in the $_POST vars.
	if (is_array($value))
		{
		foreach ($value as $key2 => $value2)
			$value[$key2] = addslashes($value2);

		$_POST[$key] = $value;
		}
	else
		$_POST[$key] = addslashes($value);
	}

//03.06.2012 naj - added support for arrays in the $_GET vars.
foreach ($_GET as $key=>$value)
	if (is_array($value))
		{
		foreach ($value as $key2 => $value2)
			$value[$key2] = addslashes($value2);

		$_GET[$key] = $value;
		}
	else
		$_GET[$key] = addslashes($value);
}

//09.21.2011 jss - this function checks the status of an invoice to make sure it's still open.  Problem is, if 2 people have the same invoice open
//and one of them cashes it out, the other one can do some serious damage to the paid invoice.
function invoiceStillOpen( $invoiceid )
{
global $db;

$query = "select Status from invInvoice where InvoiceID = $invoiceid";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 8456, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
if ( $row[ 'Status' ] < 3 )
	return true;
else
	{
	LogError( 8457, 'This invoice was just cashed out by another user, changes not saved' );
	return false;
	}
}

//10.07.2011 jss - this function retrieves and returns the general contactid
function getGeneralContactID()
{
global $db;

$query = 'select ifnull(GeneralContactID,0) as GeneralContactID from optInvoiceOptions';
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 8535, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
return $row[ 'GeneralContactID' ];
}


//TKS 11.11.2011 #9102 this function is called all over Lizzy to keep track of User Productivity
//$where tells us where we are and what the ID is for
//1 - ticket, 2 - call, 3 -invoice, 4 - job, 5 - email, 6 - check list
//$id is the TaskID, CallLogID, InvoiceID, JobID, EmailID or ChecklistContactID
//$contactid will hold the contact id in cases like when a call is placed, not sure if this will get used in the first opening of report or if it will only 
//be shown when viewing details
//$start_stop tells us whether we are starting time or stopping 
//$statustype is for tickets. tells us the status the ticket is in when the time is logged 1 support, 2 R&D, 3 testing
function UpdateUserProductivity( $where, $id, $contactid=0, $start_stop='', $statustype=0  )
{
global $UserID;
global $db;
global $RootPath;

//10.01.2012 ghh - added to include our activities file in case we need it
include_once( $RootPath."/includes/activities.php" );

$activitiesquery = '';
switch( $where )
	{
	case 1://ticket
		if ( $start_stop == 'start' )//we are starting time
			{
			//TKS 11.16.2011 added projectID so we can easily limit results in report
			$query	= "select proTasks.UserID, proProject.ID as ProjectID from proTasks, proProject, proSection, proDetailHeader 
							where proTasks.ID = ".$id." and proTasks.PID = proDetailHeader.ID and proDetailHeader.PID = proSection.ID
							and proSection.PID = proProject.ID";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8774, $query ."<br>".$db->sql_error() );
				return false;
				}
			$row = $db->sql_fetchrow( $result );
			if ( $row[ "UserID" ] == $UserID )
				$is_assigned = 1;
			else
				$is_assigned = 0;

			$query	= "insert into genUserProductivity ( TaskID, UserID, StartTime, StatusType, isUserAssigned, ProjectID ) 
							values ( ".$id.", ".$UserID.", UTC_TIMESTAMP, ".$statustype.", ".$is_assigned.", ".$row[ "ProjectID" ]." )";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8775, $query ."<br>".$db->sql_error() );
				return false;
				}
			}

		if ( $start_stop == 'stop' )//we are stoping time
			{
			$query	= "update genUserProductivity set EndTime = UTC_TIMESTAMP, TotalTime = sec_to_time( Unix_timestamp( utc_timestamp ) - unix_timestamp( StartTime ) ) 
							where TaskID = ".$id." and EndTime is NULL and UserID = ".$UserID;

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8776, $query ."<br>".$db->sql_error() );
				return false;
				}

			//10.01.2012 ghh - added to deal with adding this entry to our activities panel
			//first we need to grab some info from the ticket itself
			$query = "select Title, Description, TotalTime, StartTime, EndTime 
						from proTasks, genUserProductivity 
						where proTasks.ID=$id
						and genUserProductivity.TaskID=proTasks.ID 
						order by EndTime desc limit 1";
			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 10907, $query ."<br>".$db->sql_error() );
				return false;
				}
			$activityrow = $db->sql_fetchrow( $result );
			$activitiesquery = "insert into activities ( PrimaryID, TypeID, UserID, ActivityDate,
									Title,
									StartTime, EndTime, Duration ) values ( $id, 9, $UserID, now(), 
									'".addslashes( $activityrow[Title] )."','$activityrow[StartTime]',
									'$activityrow[EndTime]','$activityrow[TotalTime]' )"; 
			}

		if ( $start_stop == 'complete' )//we are completing a ticket and need to stop time on all users 
			{
			//TKS 05.22.2014 #52713 query to see if this person has time logging on this ticket when they hit complete/submit to testing
			//and if so, set a flag to insert a record into the activities panel for the time
			$query	= "select * from genUserProductivity where UserID = ".$UserID." and TaskID = ".$id." and EndTime is NULL";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 14699, $query ."<br>".$db->sql_error() );
				return false;
				}
			if ( $db->sql_numrows( $result ) == 0 )
				$insert_time = false;
			else
				$insert_time = true;

			$query	= "update genUserProductivity set EndTime = UTC_TIMESTAMP, TotalTime = sec_to_time( Unix_timestamp( utc_timestamp ) - unix_timestamp( StartTime ) ) 
							where TaskID = ".$id." and EndTime is NULL";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8781, $query ."<br>".$db->sql_error() );
				return false;
				}


			//TKS 08.29.2013 #40204 this query was grabbing the status ( which is updated before
			//this function gets called ) and the last times logged. The problem with this is
			//you may have logged these times yesterday but completing or sending to testing today
			//which makes this inaccurate. SO I am going to change the start and end time to just insert
			//for now and now + 1 second and set the total time to 1 sec so it is more accurate.
			//as a result this query no longer needs to link to genUserProductivity.
			//TKS 05.22.2014 #52713 changed this to go back to the old select query and added a second insert into
			//activities to log the ticket time. If you logged time on a ticket then click submit to testing or complete
			//to both send and stop you time, the activity panel would only show that it was sent to testing and not include the time logged
			//on the ticket. This will insert for the time logged then turn around and insert again for the time logged
			$query = "select StatusID, Title, Description, TotalTime, StartTime, EndTime 
						from proTasks left outer join genUserProductivity 
						on genUserProductivity.TaskID = proTasks.ID 
						where proTasks.ID=$id
						order by EndTime desc limit 1";
			//$query = "select StatusID, Title, Description from proTasks where ID= ".$id;
			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 10983, $query ."<br>".$db->sql_error() );
				return false;
				}

			$activityrow = $db->sql_fetchrow( $result );
			if ( $insert_time )
				{
				$query2 = "insert into activities ( PrimaryID, TypeID, UserID, ActivityDate,
										Title,
										StartTime, EndTime, Duration ) values ( $id, 9, $UserID, now(), 
										'".addslashes( $activityrow[Title] )."','$activityrow[StartTime]',
										'$activityrow[EndTime]','$activityrow[TotalTime]' )"; 

				if ( !$result2 = $db->sql_query( $query2 ) )
					{
					LogError( 14700, $query."<br>".$db->sql_error() );
					exit;
					}
				}

			if ( $activityrow[ "StatusID" ] == 5 || $activityrow[ "StatusID" ] == 7 )
				$type = 7;//completed
			else
				$type = 8;//testing
			//TKS 08.29.2013 #40204 changed start, end and total times to use time stamp and
			//add a second to end time and total time for completing or sending a ticket to testing
			$activitiesquery = "insert into activities ( PrimaryID, TypeID, UserID, ActivityDate,
									Title,
									StartTime, EndTime, Duration ) values ( $id, $type, $UserID, now(), 
									'".addslashes( $activityrow[Title] )."', utc_timestamp(),
									DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 second ) ,'00:00:01' )"; 
			}
		break;
	case 2://call
		if ( $start_stop == 'start' )
			{
			$query	= "insert into genUserProductivity ( CallLogID, UserID, StartTime, ContactID ) 
							values ( ".$id.", ".$UserID.", UTC_TIMESTAMP, ".$contactid." )";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8777, $query ."<br>".$db->sql_error() );
				return false;
				}
			}

		if ( $start_stop == 'stop' )
			{
			//11.14.2014 ghh - changed query to get its info directly from the call log table because it
			//is looking at pause times where this was not
			$query	= "update genUserProductivity, conCallLogs set genUserProductivity.EndTime = conCallLogs.CallEnded, 
							genUserProductivity.TotalTime = conCallLogs.TotalTime
							where genUserProductivity.CallLogID = ".$id." 
							and conCallLogs.CallLogID=genUserProductivity.CallLogID 
							and genUserProductivity.EndTime is NULL and genUserProductivity.UserID = ".$UserID;

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8778, $query ."<br>".$db->sql_error() );
				return false;
				}

			//10.01.2012 ghh - added to deal with logging activiites and tracking call topics and such
			//first we need to grab information off the call so we can log it.
			$query = "select conCallTopics.CallTopicID, conCallLogs.ContactID, conCallLogs.CallStarted,
						conCallLogs.CallEnded, conCallLogs.TotalTime, conCallNotes.SpokeWith, 
						conCallLogs.Contacted, conCallTopics.TopicName, conCallTopics.Opportunity,
						conCallTopics.ProgressLevel, conCallTopics.ProbabilityOfClose,
						CloseDate, StartUpAmount, RecurringAmount, SalesRepID,
						BusinessName, conCallTopics.Resolved
						from conContactInfo,conCallLogs, conCallNotes, conCallTopics
						where conCallLogs.CallLogID=$id 
						and conCallLogs.CallLogID=conCallNotes.CallLogID
						and conCallLogs.ContactID=conContactInfo.ContactID
						and conCallNotes.CallTopicID=conCallTopics.CallTopicID";
			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 10910, $query ."<br>".$db->sql_error() );
				return false;
				}

			$callrow = $db->sql_fetchrow( $result );
			//TKS 01.13.2015 #62030 I have no idea why there would not be a call topic id but support
			//was having issues clocking out. THey were getting a failure on the query below. For now, 
			//I am taking care of this by checking for a topic id but there is a bigger issue if we have a logid
			//above but not result from the query linking to get the topicid.
			if ( $callrow[ "CallTopicID" ] > 0 )
				{
				//TKS 01.15.2014 #46047 now we store sales progress level in a link table. In order to display
				//this under each topic on activities panel and not have to query the link table, we update the
				//ProgressLevel flag in activities. First we need to check for an open level
				$query = "select SalesProcessID from conSalesProgressLink where TopicID = ".$callrow[ "CallTopicID" ]." 
							and DaysAtThisLevel is null";
				if ( !$result = $db->sql_query( $query ) )
					{
					LogError( 15634, $query ."<br>".$db->sql_error() );
					return false;
					}

				$ProgressLevel = 0;
				if ( $db->sql_numrows( $result ) > 0 )
					{
					$progressrow = $db->sql_fetchrow( $result ); 
					$ProgressLevel = $progressrow[ "SalesProcessID" ];
					}

				//now build up our insert to place in actvities for the logged call
				//note: depending on the information supplied we may be about to work with multiple
				//rows in activity
				//TKS 05.07.2013 #35202 added secondaryid to hold the calltopicid
				$activitiesquery = "insert into activities ( PrimaryID, SecondaryID, TypeID, ContactID, UserID, 
										ActivityDate, Title,
										StartTime, EndTime, Duration, FirstName,
										BusinessName, ProgressLevel ) values ( $id, $callrow[CallTopicID], 19, $callrow[ContactID],$UserID, now(), 
										'".addslashes( $callrow[TopicName] )."','$callrow[CallStarted]',
										'$callrow[CallEnded]','$callrow[TotalTime]',
										'".addslashes( $callrow[ 'SpokeWith' ] ) ."',
										'".addslashes( $callrow[ 'BusinessName' ] ) ."', ".$ProgressLevel." )"; 

				//10.01.2012 ghh - now we need to see if we're dealing with an opportunity and if so
				//we need to write it to the table.  Noting that its possible the opportunity could
				//already exist in the activities panel from a previous call.
				if ( $callrow[ 'Opportunity' ] == 1 )
					handleOpportunity( $id );
				}
			}
		break;
	case 3://invoice
		if ( $start_stop == 'start' )
			{
			$query	= "insert into genUserProductivity ( InvoiceID, UserID, StartTime ) 
							values ( ".$id.", ".$UserID.", UTC_TIMESTAMP )";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8785, $query ."<br>".$db->sql_error() );
				return false;
				}
			}

		if ( $start_stop == 'stop' )
			{
			$query	= "update genUserProductivity set EndTime = UTC_TIMESTAMP, TotalTime = sec_to_time( Unix_timestamp( utc_timestamp ) - unix_timestamp( StartTime ) ) 
							where InvoiceID = ".$id." and EndTime is NULL and UserID = ".$UserID;

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8786, $query ."<br>".$db->sql_error() );
				return false;
				}

			//10.04.2012 ghh - added to deal with activites panel
			handleInvoice( $id );
			}
		break;
	case 4://job
		//TKS 11.18.2013 #43115 no longer assuming viewing user. We now look for the mechanic
		//assigned tot he job and start time for them. Else start for viewing user
		$query2 = "select ScheduledFor from invJobSchedule where JobID = ".$id;
		if ( !$result2 = $db->sql_query( $query2 ) )
			{
			LogError( 13722, $query2 ."<br>".$db->sql_error() );
			return false;
			}
		$row2 = $db->sql_fetchrow( $result2 );
		if ( $row2[ "ScheduledFor" ] > 0 )
			$mechanic = $row2[ "ScheduledFor" ];
		else
			$mechanic = $UserID;
		if ( $start_stop == 'start' )
			{
			$query	= "insert into genUserProductivity ( JobID, UserID, StartTime ) 
							values ( ".$id.", ".$mechanic.", UTC_TIMESTAMP )";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8788, $query ."<br>".$db->sql_error() );
				return false;
				}
			}

		if ( $start_stop == 'stop' )
			{
			$query	= "update genUserProductivity set EndTime = UTC_TIMESTAMP, TotalTime = sec_to_time( Unix_timestamp( utc_timestamp ) - unix_timestamp( StartTime ) ) 
							where JobID = ".$id." and EndTime is NULL and UserID = ".$mechanic;

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8787, $query ."<br>".$db->sql_error() );
				return false;
				}
			}
		break;
	case 5://email
			//TKS 11.18.2011 added StartTime so the emails will show up for the date range ;)
			$query	= "insert into genUserProductivity ( EmailID, UserID, StartTime ) values ( ".$id.", ".$UserID.", utc_timestamp() )";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8784, $query ."<br>".$db->sql_error() );
				return false;
				}

			handleEmail( $id );
			
		break;
	case 6://check list
		if ( $start_stop == 'start' )
			{
			$query	= "insert into genUserProductivity ( ChecklistContactID, UserID, StartTime ) 
							values ( ".$id.", ".$UserID.", UTC_TIMESTAMP )";

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8780, $query ."<br>".$db->sql_error() );
				return false;
				}
			}

		if ( $start_stop == 'stop' )
			{
			$query	= "update genUserProductivity set EndTime = UTC_TIMESTAMP, TotalTime = sec_to_time( Unix_timestamp( utc_timestamp ) - unix_timestamp( StartTime ) ) 
							where ChecklistContactID = ".$id." and EndTime is NULL and UserID = ".$UserID;

			if ( !$result = $db->sql_query( $query ) )
				{
				LogError( 8783, $query ."<br>".$db->sql_error() );
				return false;
				}
			}
		break;
	}
//10.01.2012 ghh - added to deal with activities
if ( $activitiesquery != '' )
	if ( !$result = $db->sql_query( $activitiesquery ) )
		{
		LogError( 10908, $query ."<br>".$db->sql_error() );
		return false;
		}

return true;
}//end of UpdateUserProductivity


//12.22.2011 jss - this function gets the flushacctid
function getFlushAcctID()
{
global $db;

if ( $db->db_connect_id )
	{
	$query = "select FlushAcctID from optAccountingDefaults";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 8965, $query ."<br>".$db->sql_error() );
		return 0;
		}
	$row = $db->sql_fetchrow( $result );
	return $row[ 'FlushAcctID' ];
	}
}//end getFlushAcctID


//TKS 01.11.2012 #11267 this function handles updating an additional contact's email address
//TKS 01.19.2012 passing in the addconid now for direct update on local changes only
function UpdateAddConEmail( $oldemail='', $newemail='', $contactid, $addconid = 0 )
{
global $db;
global $lang;
global $dbname;
global $dbhost, $supportdb, $dbuser, $dbpasswd;
$dbsupport = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true );

$newemail = trim( $newemail );
$oldemail = trim( $oldemail );
/*****************
//after talking with GHH I am changing this function in the following way
//first check to see if there is a userid on the addcon we are editing, if not then
//we allow them to add, edit or clear, we don't care what they do
//if they have a userid then we make sure that who they are changing the email adress to
//is not in the support tables under a different userid, if so then we error and exit;
//***************/
$query	= "select UserID from conAdditionalContacts where UserID > 0 and 
				EmailAddress = '".$oldemail."' and AdditionalContactID = ".$addconid;

if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 9255, $query ."<br>".$db->sql_error(), false );
	return false;
	}

if ( $db->sql_numrows( $result ) == 0 )
	{
	//first make sure they are not changing the email address to one that already exists on the same contact
	$query	= "select EmailAddress from conAdditionalContacts where EmailAddress = '".$newemail."' and
					ContactID = ".$contactid;
			
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 9141, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}

	if ( $db->sql_numrows( $result ) == 0 )
		{
		$query	= "update conAdditionalContacts set EmailAddress = '".$newemail."' where AdditionalContactID = ".$addconid;

		if ( !$result = $db->sql_query( $query ) )
			{
			LogError( 9142, $query .$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
			return false;
			}
		}
	}//end of editing for addcon with no userid
else
	{
	//TKS 06.12.2012 noticed if trying to save an add con without an email on a user
	//they no longer set to login, it would enter here becuase of the userid then error
	//due to invalid email. So if the email was blank before and blank now, we just return
	if ( $oldemail == '' && $newemail == '' )
		return true;
	//if the current email is hooked to a userid they cannot clear out the email address
	//TKS 01.25.2012 I moved this to the top of this else so that if they have a userid
	//and trying to clear out the email, we just return
	if ( !validEmail( $newemail ) || empty( $newemail ) || $newemail == '' )
		{
		LogError( $lang[ "ErrEmailValid" ], false );
		return false;
		}

	$temprow	= $db->sql_fetchrow( $result ); 
	$userid = $temprow['UserID'];
	//I spoke with Noel and he said to add Training DB and Demo and exit if they are editing a record with
	//a userid in either of those DBs because we have a bunch of customers and employees editing and messing with data
	// and it screwed up people's login ability. SO these 2 dbs, if oldemail has userid, they cannot edit it, they
	//will have to login to their individual DB to edit the record
	//05.14.2013 naj - added trial databases to the list of databases you cannot edit from.
	if ( ( $dbname == 'nizex_training' || $dbname == 'nizex_demo' || preg_match('/nizex_trial/', $dbname)) && ( $newemail != $oldemail ) )
		{
		LogError( $lang[ "ErrDBEmail" ], false );
		return false;
		}

	//first see if the email addresses are different
	if (  $newemail != $oldemail )
		{
		//first check to see if the new address is in the optUsers table, under a different userid if so we return false
		$query	= "select UserID from optUsers where EmailAddress = '$newemail' and UserID != $userid";

		if ( !$result = $dbsupport->sql_query( $query ) )
			{
			LogError( 9136, $query ."<br>".$dbsupport->sql_error(), false );
			return false;
			}

		if ( $dbsupport->sql_numrows( $result ) > 0 )
			{
			LogError(  $lang[ "ErrEmailExists" ], false );
			return false;
			}

			//grab all company DBs linked to this user
			$query	= "select optUserCompany.DBName, DBHost from optUserCompany, optUserLinks where optUserLinks.UserID = $userid
							and optUserLinks.CompanyID = optUserCompany.CompanyID";

			if ( !$result = $dbsupport->sql_query( $query ) )
				{
				LogError( 9138, $query ."<br>".$dbsupport->sql_error(), false );
				return false;
				}

			//04.09.2013 naj - changed everything to use a transaction, so we can roll this back if it fails
			$dbarray = array();
			while ( $dbrow	= $dbsupport->sql_fetchrow( $result ) )
				{
				$tempdb = new sql_db( $dbrow[ "DBHost" ], $dbuser, $dbpasswd, $dbrow[ "DBName" ], false, true );
				if ( $tempdb->db_connect_id )
					{
					//04.09.2013 naj - add the current database to the db array.
					$dbarray[] = $tempdb;
					if ( !$tempresult = $tempdb->sql_query( '', 'BEGIN'))
						{
						LogError (12118, $query."<br>".$tempdb->sql_error());
						foreach ($dbarray as $tempdb)
							{
							$tempdb->sql_query('', 'ROLLBACK');
							$tempdb->sql_close();
							}
						return false;
						}

					$query	= "update conAdditionalContacts set EmailAddress = '".$newemail."' where EmailAddress = '".$oldemail."' and UserID = $userid";
						
					if ( !$tempresult = $tempdb->sql_query( $query ) )
						{
						LogError( 9139, $query."<br>".$tempdb->sql_error() );
						foreach ($dbarray as $tempdb)
							{
							$tempdb->sql_query('', 'ROLLBACK');
							$tempdb->sql_close();
							}
						return false;
						}
					}
				}

			//now update the optUsers record
			$query	= "update optUsers set EmailAddress  = '".$newemail."' where UserID = $userid";

			if ( !$result = $dbsupport->sql_query( $query ) )
				{
				LogError( 9140, $query ."<br>".$dbsupport->sql_error(), false );
				foreach ($dbarray as $tempdb)
					{
					$tempdb->sql_query('', 'ROLLBACK');
					$tempdb->sql_close();
					}
				return false;
				}	

			//04.09.2013 naj - if we made it this far, then the update is complete.
			foreach ($dbarray as $tempdb)
				{
				$tempdb->sql_query('', 'COMMIT');
				$tempdb->sql_close();
				}

		//12.09.2013 naj - this is to ensure that the current database gets updated to in the event that the user was allowed to login in the past but now is not.
		$query	= "update conAdditionalContacts set EmailAddress = '".$newemail."' where EmailAddress = '".$oldemail."' and UserID = $userid";
		if ( !$result = $db->sql_query( $query ) )
			{
			LogError(13858, $query ."<br>".$db->sql_error(), false );
			return false;
			}
		}
	}

return true;
}//end of UpdateAddConEmail



//01.13.2012 jss -
function getDepartmentName( $deptid )
{
global $db;

$deptname = '';

if ( $deptid > 0 )
	{
	$query = "select DeptName from optDepartments where DeptID = $deptid";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 9198, $query ."<br>".$db->sql_error() );
		return false;
		}
	$row = $db->sql_fetchrow( $result );
	$deptname = $row[ 'DeptName' ];
	}

return $deptname;
}

//01.13.2012 jss -
function getDepartmentID( $type )
{
global $db;

$deptid = 0;

switch( $type )
	{
	case 1:
		$fieldname = 'Parts';
		break;
	case 2:
		$fieldname = 'Service';
		break;
	case 3:
		$fieldname = 'NewUnits';
		break;
	case 4:
		$fieldname = 'UsedUnits';
		break;
	case 5:
		$fieldname = 'FI';
		break;
	case 6:
		$fieldname = 'Apparel';
		break;
	case 7:
		$fieldname = 'RentalUnits';
		break;
	}

$query = "select DeptID from optDepartments where $fieldname = 1";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 9199, $query ."<br>".$db->sql_error() );
	return 0;
	}
if ( $db->sql_numrows( $result ) > 0 )
	{
	$row = $db->sql_fetchrow( $result );
	$deptid = $row[ 'DeptID' ];
	}

return $deptid;
}

//TKS 01.31.2012 #12249 now that we have multiple DBServers when connecting to a different DB
//like I am with support tickets...we need to know the dhbost. We can't use the global $dbhost as this could be wrong
//so to fix this, I am writing a function to query the support DB and get the dbhost for the dbname and return it
function getDBHost( $databasename = '' )
{
global $supportdb, $dbuser, $dbpasswd;
$dbsupport = new sql_db( $supportdb, $dbuser, $dbpasswd, 'nizex_support', false, true );

$query	= "select DBHost from optUserCompany where DBName = '".$databasename."'";

if ( !$result = $dbsupport->sql_query( $query ) )
	{
	LogError( 9369, $query ."<br>".$dbsupport->sql_error(), false );
	return false;
	}

$row = $dbsupport->sql_fetchrow( $result );
return $row[ "DBHost" ];
}

//01.31.2012 ghh - added to clear our barcode table
function dumpBarCodes()
{
global $db;
global $UserID;

if ( $db->db_connect_id )
	{
	$query	= 'delete from itmBarCodePrint where UserID = ' . $UserID;
		
	if ( !$result = $db->sql_query( $query ) )
		{
		//$this->Error .= '( 6365 )'.$query."<br>".$db->sql_error().'<p>';
		LogError( 6365, $query . "<br>".$db->sql_error() );
		return false;
		}
	}
return true;
}//end dumpBarCodes

//03.07.2012 naj - Return true if the specified Java module is enabled.
function javaModuleEnabled( $module = '' )
{
global $db;

if (!$db->db_connect_id) return false;

if (empty($module)) return false;

$query = "select ".addslashes($module)." from optCompanyInfo";

if ( !$result = $db->sql_query( $query ) )
	{
	//$this->Error .= '( 6365 )'.$query."<br>".$db->sql_error().'<p>';
	LogError( 9583, $query . "<br>".$db->sql_error() );
	return false;
	}

$row = $db->sql_fetchrow($result);

if ($row[$module] == 1)
	return true;
else
	return false;
}

function getTabletDetails( $getcontrol = false, $companyhash = "", $sigdate = "", $transid = "", $invoice = true)
{
global $db, $localpath, $lang;

if (!$db->db_connect_id) return false;

$query = "select * from optTabletSettings";

if ( !$result = $db->sql_query( $query ) )
	{
	//$this->Error .= '( 6365 )'.$query."<br>".$db->sql_error().'<p>';
	LogError( 9583, $query . "<br>".$db->sql_error() );
	return false;
	}

$row = $db->sql_fetchrow($result);

if (!$getcontrol)
	return $row;

//02.07.2014 naj - added code to actually produce the signature control based on the value in optTabletSettings.
//07.16.2014 naj - cleaned up if statements.
if ($row["TabletTypeID"] >= 1 && $row["TabletTypeID"] <= 3)
	{
	if ($invoice)
		$service = 1;
	else
		$service = 0;

	$control = "<object type=\"application/x-java-applet\" width=\"300\" height=\"150\" code=\"SigApplet.class\">
						<param name=\"archive\" value=\"/java/SigPlus2_64.jar,/java/RXTXcomm.jar,/java/SigApplet.jar\" />
						<param name=\"uploadurl\" value=\"https://$localpath.nizex.com/java/SigApplet.php\">
						<param name=\"key\" value=\"$companyhash\">
						<param name=\"date\" value=\"$sigdate\">
						<param name=\"transid\" value=\"$transid\">
						<param name=\"service\" value=\"$service\">
						<param name=\"tablettype\" value=\"$row[TabletTypeID]\">
						<param name=\"codebase_lookup\" value=\"false\">
					</object>";
	}
elseif ($row["TabletTypeID"] >= 4 && $row["TabletTypeID"] <= 7)
	{
	//02.07.2014 naj - create the new control for the scriptel pad
	if ($invoice)
		$filename = "service-signature-$transid.png";
	else
		$filename = "credit-signature-$transid.png";
		
	$control = "<div id=\"sigarea\">
						<span class=\"lbl\">$lang[ScriptelInst]</span><br>
						<textarea id=\"txt_scriptel\" name=\"txt_scriptel\" rows=\"4\" cols=\"50\" wrap=\"soft\" autofocus></textarea>
						<br>
						<input type=\"button\" class=\"btn_Save\"
							onClick=\"grabSignature('$sigdate', '$filename', '$companyhash'); return false;\" 
							value=\"$lang[ScriptelButton]\"/>
					</div>";
	}
else
	$control = "";

return $control;
}

//03.01.2012 jss - ( 14230 )
function isFakeDept( $deptid )
{
global $db;
$query = "select Fake from optDepartments where DeptID = $deptid";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 9654, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
if ( $row[ 'Fake' ] == 1 )
	return true;
else
	return false;
}

//03.01.2012 jss - ( 0 ) what all can a user do with the compare utility
//1 = admin, full control
//2 = view control panel and compare ( no changes )
//3 = view control panel ( no changes )
function compareSecurityLevel()
{
global $UserID;

switch( $UserID )
	{
	/*jss*/ case 4:				$level = 1; break;
	/*jss*/ case 88:				$level = 1; break;
	/*jss*/ case 741:				$level = 1; break;
	/*ghh*/ case 85:				$level = 1; break;
	/*ghh*/ case 3812:			$level = 1; break;

	/*jek*/ case 129:				$level = 1; break;
	/*conversion*/ case 742:	$level = 1; break;

//	/*ghh*/ case 2:				$level = 2; break;
//	/*ghh*/ case 85:				$level = 2; break;
//	/*ghh*/ case 3812:			$level = 2; break;
//	/*naj*/ case 124:				$level = 2; break;

//	/*bbb*/ case 131:				$level = 3; break;
//	/*michael*/ case 1042:		$level = 3; break;
	/*ecg&bbb*/ case 402:		$level = 3; break;

	default: $level = 0; break;
	}
return $level;
}

//04.13.2012 jss - this function tells you if it's ok to hit the gl for the date that's passed in based on the gl close date
//NOTE:  date being passed in must be formatted for displaying to user
function verifyGLDate( $date )
{
global $UserID;
global $db;


$query = "select * from optGLClose order by 1 DESC Limit 1";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 000, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );

$dt1 = explode( "-", $row[ 'CloseDate' ] );
$dt2 = explode( "-", FormatDate( $date, 3 ) );
if ( mktime( 0, 0, 0, $dt2[1], $dt2[2], $dt2[0] ) <= mktime( 0, 0, 0, $dt1[1], $dt1[2], $dt1[0] ) )
	{
	$dt1 = explode( '-', $row[ 'OverrideDate' ] );
	$dt2 = explode( '-', date( 'Y-m-d' ) );
	//ok - we are trying to hit a closed period - so now we see if our user is allowed to make the adjustment
	if ( $UserID == $row[ 'UserID' ] && mktime( 0, 0, 0, $dt2[1], $dt2[2], $dt2[0] ) <= mktime( 0, 0, 0, $dt1[1], $dt1[2], $dt1[0] ) )
		{
		//user is the override user and we haven't passed the override date
		return true;
		}
	else
		{
		// NO CAN DO, BOOKS ARE CLOSED ;-)
		return false;
		}
	}
return true;
}

function getRoundingAccount()
{
global $db;

$query = "select RoundingAccountID from optAccountingDefaults";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 10127, $query ."<br>".$db->sql_error() );
	return 0;
	}
$row = $db->sql_fetchrow( $result );
return $row[RoundingAccountID];
}

function scrapUnits()
{
global $db;

$query = "select ScrapItemID from optUnitDefaults";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 10134, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
if ( $row[ScrapItemID] > 0 )
	return true;
else
	return false;
}

//06.13.2012 naj - added new password hashing and vaildation schemes.
function hashPassword($password)
{
//Takes a password and returns the salted hash
//$password - the password to hash
//returns - the hash of the password (128 hex characters)

$salt = bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM)); //get 256 random bits in hex
$hash = hash("sha256", $salt . $password); //prepend the salt, then hash

//store the salt and hash in the same string, so only 1 DB column is needed
$final = $salt . $hash; 
return $final;
}

function validatePassword($password, $correctHash)
{
//Validates a password
//returns true if hash is the correct hash for that password
//$hash - the hash created by HashPassword (stored in your DB)
//$password - the password to verify
//returns - true if the password is valid, false otherwise.

$salt = substr($correctHash, 0, 64); //get the salt from the front of the hash
$validHash = substr($correctHash, 64, 64); //the SHA256

$testHash = hash("sha256", $salt . $password); //hash the password being tested

//if the hashes are exactly the same, the password is valid
return $testHash === $validHash;
}

function getBinName( $binid, $includelocation=true )
{
global $db;
global $UserID;

$binname = '';
if ( $binid > 0 )
	{
	$query = "select b.BinName, l.Name
					from itmBins b, itmBinLocations l
					where b.BinID = $binid
					and b.LocationID = l.LocationID";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 11195, $query ."<br>".$db->sql_error() );
		return false;
		}
	$row = $db->sql_fetchrow( $result );

	//if including location only do it if they have more than one
	if ( $includelocation )
		{
		$query = "select LocationID from itmBinLocations";
		if ( !$result = $db->sql_query( $query ) )
			{
			LogError( 11196, $query ."<br>".$db->sql_error() );
			return false;
			}
		if ( $db->sql_numrows( $result ) == 1 )
			$includelocation = false;
		}

	$binname = $row[BinName];

	if ( $includelocation )
		$binname .= " ($row[Name])";

	if ( compareSecurityLevel($UserID) == 1 )
		$binname .= " ($binid)";
	}
else
	$binname = '';

return $binname;
}

//12.07.2012 jss - ( 27285 ) - this function gets the inventory method, (lifo,fifo,averaging).
//Need this cuz we're disabling kits and consignment parts and don't want queries all over the place.
function getInventoryMethod()
{
global $db;
$query = "select InventoryMethod from optInventoryOptions";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 000, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
return $row[InventoryMethod];
}


//TKS 05.07.2013 #35202 writing a function that will return email addresses for a contact
//currently we have additional contacts under a contact and now we support multiple email s under
//a single additional contact. This function will take a contactid and return an array or emails
//This way we can get the list or just see if they have a valid email at all
//TKS 04.17.2014 adding on to this function. Adding a parameter that allows you to clarify if you
//want to limit the array to only contacts marked as SendInvoiceHere
//TKS 03.04.2015 #63795 added flag for additional contact id so we could get it back without
//creating a separate function
function ContactEmailAddresses( $contactid=0, $sendinvoicehere=false, $addconid=0 )
{
global $db;
if ( !$contactid > 0 )
	return false;

if ( $db->db_connect_id )
	{
	if ( $addconid > 0 )
		$extra = " and conAdditionalContacts.AdditionalContactID = ".$addconid;
	else
		$extra = '';
	//TKS 04.16.2013 Noel helped me with this query and it is optimized.
	//TKS 08.17.2015 #71423 removed reference to conAdditionalEmailAddresses
	$query	= "select AdditionalContactID, FirstName, LastName, EmailAddress, PrimaryContact 
					from conAdditionalContacts where ContactID = $contactid and EmailAddress != ''
					and EmailAddress is not null $extra";
	//TKS 04.17.2014 added new parameter to only grab for send invoices here flag
	if ( $sendinvoicehere )
		$query .= " and conAdditionalContacts.SendInvoiceHere = 1 ";

	$query .= " order by LastName, FirstName, EmailAddress";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 12437, $query."<br>".$db->sql_error() );
		return false;
		}
	if ( $db->sql_numrows( $result ) == 0 )
		return false;

	$i = 0;	
	while( $row	= $db->sql_fetchrow( $result ) )
		{
		if ( validEmail( $row[ "EmailAddress" ] ) )
			{
			$Email[ $i ][ "EmailAddress" ]			= $row[ "EmailAddress" ]; 
			$Email[ $i ][ "Name" ]						= stripslashes( $row[ "FirstName" ]." ".$row[ "LastName" ] ); 
			$Email[ $i ][ "AdditionalContactID" ]	= $row[ "AdditionalContactID" ]; 
			$Email[ $i ][ "PrimaryContact" ]			= $row[ "PrimaryContact" ]; 
			$i++;
			}
		}
	}
if ( count( $Email ) == 0 )
	return false;
return $Email;
}

//06.20.2013 naj - added function to lookup a files mime type
//This will be used when storing files in GridFS
function getFileMimeType($filepath)
{
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimetype = finfo_file($finfo, $filepath);
finfo_close($finfo);
return $mimetype;
}

function UseEPLPrinter()
{
global $db;

$query = "select BarcodePrinter from optCompanyInfo";
		
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 0000, $query."<br>".$db->sql_error() );
	return false;
	}

$row = $db->sql_fetchrow($result);

if ($row["BarcodePrinter"] == 1)
	return true;
else
	return false;
}



//added to see if user can edit invoices
function canUserEdit( $invtype )
{
//general invoices
if ( $invtype == 1 )
	if ( isAuth( 1, 81, 233, 367 ) )
		return true;
//service
if ( $invtype == 2 )
	if ( isAuth( 1, 81, 233, 366 ) )
		return true;
//unit sale
if ( $invtype == 3 )
	if ( isAuth( 1, 81, 233, 365 ) )
		return true;
//internet
if ( $invtype == 4 )
	if ( isAuth( 1, 81, 233, 364 ) )
		return true;
//catalog
if ( $invtype == 5 )
	if ( isAuth( 1, 81, 233, 363 ) )
		return true;
//customer deposit
if ( $invtype == 6 )
	if ( isAuth( 1, 81, 233, 362 ) )
		return true;
//internal
if ( $invtype == 7 )
	if ( isAuth( 1, 81, 233, 361 ) )
		return true;
//layaway
if ( $invtype == 8 )
	if ( isAuth( 1, 81, 233, 360 ) )
		return true;
//refund
if ( $invtype == 9 )
	if ( isAuth( 1, 81, 233, 359 ) )
		return true;
//parts quote
if ( $invtype == 10 )
	if ( isAuth( 1, 81, 233, 367 ) )
		return true;
//sales quote
if ( $invtype == 11 )
	if ( isAuth( 1, 81, 233, 389 ) )
		return true;
//finance only
if ( $invtype == 12 )
	if ( isAuth( 1, 81, 233, 509 ) )
		return true;
//rental
if ( $invtype == 13 )
	if ( isAuth( 1, 81, 233, 531 ) )
		return true;
//major unit purchase
if ( $invtype == 14 )
	if ( isAuth( 1, 81, 233, 365 ) )
		return true;
//parts purchase
if ( $invtype == 15 )
	if ( isAuth( 1, 81, 233, 644 ) )
		return true;
//crm quote
if ( $invtype == 16 )
	if ( isAuth( 1, 81, 233, 698 ) )
		return true;
//rental quote
if ( $invtype == 17 )
	if ( isAuth( 1, 81, 233, 763 ) )
		return true;

return false;
}


//TKS 07.26.2013 #38705 this function takes an additional contact id and returns a userid 
//if possible
function GetUserAddConID( )
{
global $db;
global $UserID;

if ( $db->db_connect_id )
	{
	$query	= "select AdditionalContactID from conAdditionalContacts where 
					UserID = ".$UserID." and Login = 1";
		
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 12900, $query."<br>".$db->sql_error() );
		return 0;
		}

	$row	= $db->sql_fetchrow( $result );
	$addcon = $row[ "AdditionalContactID" ];
	}
return $addcon;
}//end of GetUserAddConID



//09.06.2013 jss - this function can get called from anywhere we are setting up the gl class 
//and an account doesn't exist.  The flag will tell us which type of error to return, in an 
//attempt to direct the customer to the area where the missing account is.
//Most of all the accounts in lizzy are already in this function, so please check first before
//adding a new case.
function missingAccount( $type=0, $acctid=0 )
{
global $db;

if ( $type == 0 )
	return false;

if ( $acctid > 0 )
	return false;

switch( $type )
	{
	##########################
	### INVOICING DEFAULTS ###
	##########################
	case 1:
		$acctname = 'Customer Deposit';
		$where = 'Settings > Accounting > Defaults and look in the "Invoicing Defaults" section';
		break;
	case 2:
		$acctname = 'Undeposited Funds';
		$where = 'Settings > Accounting > Defaults and look in the "Invoicing Defaults" section';
		break;
	case 3:
		$acctname = 'Cashdrawer Over/Under';
		$where = 'Settings > Accounting > Defaults and look in the "Invoicing Defaults" section';
		break;

	##########################
	### INVENTORY DEFAULTS ###
	##########################
	case 4:
		$acctname = 'Inventory Shrinkage';
		$where = 'Settings > Accounting > Defaults and look in the "Inventory Defaults" section';
		break;
	case 5:
		$acctname = 'Unit Shrinkage';
		$where = 'Settings > Accounting > Defaults and look in the "Inventory Defaults" section';
		break;

	########################
	### PAYABLE DEFAULTS ###
	########################
	case 6:
		$acctname = 'Payable Rounding';
		$where = 'Settings > Accounting > Defaults and look in the "Payable Defaults" section';
		break;
	case 7:
		$acctname = 'Discount';
		$where = 'Settings > Accounting > Defaults and look in the "Payable Defaults" section';
		break;
	case 8:
		$acctname = 'Shipping';
		$where = 'Settings > Accounting > Defaults and look in the "Payable Defaults" section';
		break;
	case 9:
		$acctname = 'Shipping Liability';
		$where = 'Settings > Accounting > Defaults and look in the "Payable Defaults" section';
		break;

	###################################
	### GENERAL ACCOUNTING DEFAULTS ###
	###################################
	case 10:
		$acctname = 'Petty Cash';
		$where = 'Settings > Accounting > Defaults and look in the "General Accounting Defaults" section';
		break;
	case 11:
		$acctname = 'Flush';
		$where = 'Settings > Accounting > Defaults and look in the "General Accounting Defaults" section';
		break;
	case 12:
		$acctname = 'Owners Equity';
		$where = 'Settings > Accounting > Defaults and look in the "General Accounting Defaults" section';
		break;

	################
	### RESERVED ###
	################
	//case 13 thru 24 are reserved for "Supplier Defaults" in settings > accounting > defaults cuz i
	//don't think we need to ever worry about them.  They are just the defaults for suppliers,
	//and if the supplier record is blank we fall back to here, so fixing taking them to the supplier
	//record would be suffice

	################
	### RESERVED ###
	################
	//case 25 thru 100 are reserved for Finance Default Accounts.  It's actually 25 thru 92, but decided
	//to leave room in case we add more stuff later.  We'll add these in as needed.

	########################
	### INVOICE DEFAULTS ###
	########################
	//CASH ACCOUNTS
	case 101:
		$acctname = 'Cash Drawer';
		$where = 'Settings > Invoicing > Defaults and look in the "Cash Accounts" section';
		break;
	//SALES ACCOUNTS
	case 102:
		$acctname = 'Labor';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales Accounts" section';
		break;
	case 103:
		$acctname = 'Setup Labor';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 104:
		$acctname = 'Sublet Labor';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 105:
		$acctname = 'Shop Materials';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 106:
		$acctname = 'EPA';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 107:
		$acctname = 'Pickup/Delivery';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 108:
		$acctname = 'Discount';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 109:
		$acctname = 'Restocking Fee';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 110:
		$acctname = 'Shipping Charges';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 111:
		$acctname = 'Warranty Handling Fee';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 112:
		$acctname = 'Warranty Misc Fee';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 113:
		$acctname = 'Internal Parts';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	case 114:
		$acctname = 'Internal Labor';
		$where = 'Settings > Invoicing > Defaults and look in the "Sales" section';
		break;
	//COST / EXP / INV ACCOUNTS 
	case 115:
		$acctname = 'Labor';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	case 116:
		$acctname = 'Setup Labor';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	case 117:
		$acctname = 'Sublet Labor';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	case 118:
		$acctname = 'Sublet Labor Inventory';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	case 119:
		$acctname = 'Payroll Expense';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	case 120:
		$acctname = 'Account for Expensing Jobs on Internal ROs';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	case 121:
		$acctname = 'Shipping';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	case 122:
		$acctname = 'Internal Parts';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	case 123:
		$acctname = 'Internal Labor';
		$where = 'Settings > Invoicing > Defaults and look in the "Cost / Expense / Inventory Accounts" section';
		break;
	// LIABILITY ACCOUNTS
	case 124:
		$acctname = 'Gift Cards';
		$where = 'Settings > Invoicing > Defaults and look in the "Liability Accounts" section';
		break;
	case 125:
		$acctname = 'Gift Certificates';
		$where = 'Settings > Invoicing > Defaults and look in the "Liability Accounts" section';
		break;

	############################
	### UNIT RENTAL ACCOUNTS ###
	############################
	case 126:
		$acctname = 'Rental Sales';
		$where = 'Settings > Serialized > Default Accounts';
		break;
	case 127:
		$acctname = 'Rental Cogs';
		$where = 'Settings > Serialized > Default Accounts';
		break;
	case 128:
		$acctname = 'Rental Asset';
		$where = 'Settings > Serialized > Default Accounts';
		break;

	#########################
	### SUPPLIER ACCOUNTS ###
	#########################
	case 129:
		$acctname = 'Expense';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 130:
		$acctname = 'Payable';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 131:
		$acctname = 'Shipping Expense';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 132:
		$acctname = 'Shipping Liability';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 133:
		$acctname = 'Rebate A/R';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 134:
		$acctname = 'Rebate Credit';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 135:
		$acctname = 'Rebate Over/Under';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 136:
		$acctname = 'Holdback A/R';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 137:
		$acctname = 'Holdback Credit';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 138:
		$acctname = 'Holdback Over/Under';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 139:
		$acctname = 'Warranty A/R';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 140:
		$acctname = 'Warranty Credit';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 141:
		$acctname = 'Warranty Over/Under';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 142:
		$acctname = 'Refund A/R';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 143:
		$acctname = 'Refund Credit';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;
	case 144:
		$acctname = 'Refund Over/Under';
		$where = ' the Suppliers record and click on one of the Supplier contact types';
		break;

	######################
	### MANUF ACCOUNTS ###
	######################
	case 145:
		$acctname = 'Parts Sales';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 146:
		$acctname = 'Parts COGS';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 147:
		$acctname = 'Parts Inventory';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 148:
		$acctname = 'Unit Sales';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 149:
		$acctname = 'Unit COGS';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 150:
		$acctname = 'Unit Inventory';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 151:
		$acctname = 'Freight Sales';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 152:
		$acctname = 'Setup Sales';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 153:
		$acctname = 'Setup COGS';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 154:
		$acctname = 'Repair Sales';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 155:
		$acctname = 'Repair COGS';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 156:
		$acctname = 'Installation Sales';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 157:
		$acctname = 'Installation COGS';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 158:
		$acctname = 'Other Sales';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 159:
		$acctname = 'Other COGS';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 160:
		$acctname = 'PDI Sales';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 161:
		$acctname = 'PDI COGS';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 162:
		$acctname = 'Trade Payoff';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	case 163:
		$acctname = 'Trade Over/Under Allowance';
		$where = ' the Manufacturers record and click on the Manufacturer contact type.  NOTE:  you may need to drill down to the appropriate Item Category or Unit Type';
		break;
	}

LogError( "You are missing the default \"$acctname\" account. Please go to $where" );
return true;
}

//09.17.2013 jss - ( 38950 ) this functions returns whether or not we're using the new tax stuff
function useNewTaxClass()
{
global $db;

$query = "select UseNewTaxSystem from optCompanyInfo";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 13362, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
if ( $row['UseNewTaxSystem'] == 1 )
	return true;
else
	return false;
}

//10.11.2013 jss - ( 41721 ) this function checks to see if we're using the new bin system or not
function useTransitBins( $locationid=0 )
{
//12.10.2013 jss - ( 41721 ) changed to look at the new flag instead of userid's 
/*
global $UserID;
if ( $UserID == 741 
		|| $UserID == 88
		|| $UserID == 124 //noel
		|| $UserID == 3812 //lizzy glenn
		|| $UserID == 2709 //joy
		)
	return true;
else
	return false;
	*/
global $db;
global $UserID;

//01.17.2014 jss - ( 45654 ) changed from looking at the checkbox to looking to see if they have any
//transit bins setup.  This was glenn's idea for know which location is using them or not.
/*
$query = "select * from optInventoryOptions";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 13868, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
if ( $row['UseTransitBins'] == 1 )
*/
if ( $locationid == 0 )
	$locationid = getUserLocation();
$query = "select * from shpBins where LocationID = $locationid and SOBinTypeID = 4 limit 1";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 14117, $query ."<br>".$db->sql_error() );
	return false;
	}
if ( $db->sql_numrows( $result ) > 0 )
	return true;
else
	return false;
}


//11.15.2013 ghh - ( added to deal with retrieving default invoice taxes
function getDefaultTaxes( $LocationID, $InvType, $ContactStateID = 0 )
{
global $db;

//01.11.2014 ghh 45871- now we need to see if we're doing an internet invoice
//and if so we need to make sure the customer resides within our district
//all we do here is maybe change the locationid if necessary
if ( (int)$ContactStateID > 0 && ( $InvType == 4 || $InvType == 5 ) )
	{
	//07.03.2014 ghh - ( 54436 ) if we happen to be in a canadian state then we
	//need to ignore where the dealership is and just charge the tax rate for
	//the current state
	$query = "select CountryID from genStates where StateID=$ContactStateID";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 14933, $qrySel.$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}
	$row = $db->sql_fetchrow( $result );

	//07.03.2014 ghh - ( 54436 ) if we're working with canada then just use
	//the taxid linked to the current state as each state gets taxed at the
	//rate of the customer no matter where they are buying products
	if ( $row[ 'CountryID' ] == 38 )
		{
		$query = "select TaxID from taxList where StateID=$ContactStateID";
		if ( !$result = $db->sql_query( $query ) )
			{
			LogError( 14933, $qrySel.$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
			return false;
			}
		return $result;
		}
		

	//now we know we're doing an internet or phone in invoice type so we next
	//need to see if we have an office in the customers state
	$query = "select LocationID from conZipCode, conContactInfo, itmBinLocations
			where conZipCode.StateID=$ContactStateID and
			itmBinLocations.InvoiceHeaderContactID=conContactInfo.ContactID and
			conZipCode.ZipID=conContactInfo.ZipID";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 14051, $qrySel.$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		return false;
		}

	//now we've determined that the customer does reside in a taxable
	//state so we're going to retrieve the default taxes for that state
	if ( $db->sql_numrows( $result ) > 0 )
		{
		//all we really need to do here is reset the location id to that
		//of the customer and let the last bit of code continue running
		$row = $db->sql_fetchrow( $result );
		$LocationID = $row[ 'LocationID' ];
		}
	else
		{
		//02.26.2014 ghh - we have determined that we have no specific location that
		//matches the customers state, however, before we return exempt we need to
		//be sure that optCompanyInfo doesn't provide us a different answer
		$query = "select StateID from optCompanyInfo, conZipCode where
					optCompanyInfo.ZipID=conZipCode.ZipID";
		if ( !$result = $db->sql_query( $query ) )
			{
			LogError( 14370, $qrySel.$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
			return false;
			}
		$staterow = $db->sql_fetchrow( $result );

		//if we determine that the company state does not match the customer state
		//then we're going to return exempt out of state. Otherwise, just continue
		//getting our default taxes
		if ( $staterow[ 'StateID' ] != $ContactStateID )
			{
			//now we've determined that the customer is tax exempt so we'll grab the
			//out of state exempt taxid 
			$query = "select 1082 as TaxID";
			$result = $db->sql_query( $query );
			return $result;
			}
		}
	}

//first grab the default taxes for most invoice types
$query		= "select TaxID from taxDefault where LocationID = $LocationID"; 
if ( !$defaultresult = $db->sql_query( $query ) )
	{
	LogError( 3473, $qrySel.$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
	return false;
	}

//next see if we should grab ones for units
if( $InvType == 3 || $InvType == 11 )
	{
	//01.12.2014 ghh - added stateid to the results so we know what tax system to load on invoice
	$query		= "select TaxID from taxMUDefault where LocationID = $LocationID";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 10111, $query.$dblang[ "ErrorInSQL" ]."<br>".$db->sql_error() );
		//exit;
		//11.18.2013 naj - we should never exit here since we will be in a transaction.
		return false;
		}
	if ( $db->sql_numrows( $result ) > 0 )
		{
		$defaultresult = $result;
		}	//no default unit taxes
	}	//unit sale

return $defaultresult;
}

//03.19.2014 ghh - added this function to deal with isauthing all menus being called
//as an extra safety measure.



//04.09.2014 ghh - ( added new function to tell us if a date is inside of a closed
//period
$closedperiodoverride = 0;
function isClosedPeriod( $date ) 
{
global $db;
global $UserID;
global $closedperiodoverride; //04.09.2014 ghh - added to hold override id for the class

$query = "select * from optGLClose order by 1 DESC Limit 1";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 9049, "There was an error looking at the closed date. <br>".$db->sql_error() );
	return false;
	}

if ( $db->sql_numrows( $result ) > 0 )
	{
	$rowclose = $db->sql_fetchrow( $result );
	$dt1 = explode( "-", $rowclose[ 'CloseDate' ] );
	$dt2 = explode( "-", FormatDate( $date, 3 ) );
	if ( mktime( 0, 0, 0, $dt2[1], $dt2[2], $dt2[0] ) <= mktime( 0, 0, 0, $dt1[1], $dt1[2], $dt1[0] ) )
		{
		$dt1 = explode( '-', $rowclose[ 'OverrideDate' ] );
		$dt2 = explode( '-', date( 'Y-m-d' ) );
		//ok - we are trying to hit a closed period - so now we see if our user is allowed to make the adjustment
		if ( $UserID == $rowclose[ 'UserID' ] && mktime( 0, 0, 0, $dt2[1], $dt2[2], $dt2[0] ) <= mktime( 0, 0, 0, $dt1[1], $dt1[2], $dt1[0] ) )
			{
			//user is the override user and we haven't passed the override date
			$closedperiodoverride = $rowclose[ 'OverrideID' ];
			return false;
			}
		else
			{
			LogError( 9050, "You are trying to Post to a closed period. <br>Either you are not the authorized override user, or your authorization has expired.<br />If you are adjusting cost on an old Purchase Order you can edit the po and change it's GL Posting Date and try again." );
			return true;
			}
		}	//trying to hit a closed period
	}	//only in here if we have a closed date - if not - just move on

$closedperiodoverride = 0;
return false;
}

function gen_uuid()
{
return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
// 32 bits for "time_low"
mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

// 16 bits for "time_mid"
mt_rand( 0, 0xffff ),

// 16 bits for "time_hi_and_version",
// four most significant bits holds version number 4
mt_rand( 0, 0x0fff ) | 0x4000,

// 16 bits, 8 bits for "clk_seq_hi_res",
// 8 bits for "clk_seq_low",
// two most significant bits holds zero and one for variant DCE1.1
mt_rand( 0, 0x3fff ) | 0x8000,

// 48 bits for "node"
mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
);
}

//06.16.2014 jss - ( 53772 ) this functions determines what pull status an invoice should have
//used for sorting the instock pull control
function setInvoicePullStatus( $invoiceid )
{
global $db;

if ( $invoiceid > 0 )
   {
   //first, set the status to 99, which is the default
   $pullstatus = 99;

   $invtype = getInvType( $invoiceid );
   if ( $invtype > 0 )
      {
      //PART SALES & UNIT SALES
      if ( $invtype == 1 || $invtype == 3 )
         $pullstatus = 1;

      //SERVICE TICKETS
      if ( $pullstatus == 99 && ( $invtype == 2 || $invtype == 7 ) )
         $pullstatus = 2;

      //STOCK ONLY INTERNET CATALOG
      if ( $pullstatus == 99 && ( $invtype == 4 || $invtype == 5 ) )
         {
         //now check to see if this int/cat sale only has stock items on it
         $query = "select * 
                     from invBackOrderPOLink
                     where BackOrderID in ( select BackOrderID from invBackOrders where InvoiceID = $invoiceid and Complete = 0 )";
         if ( !$result = $db->sql_query( $query ) )
            {
            LogError( 14799, $query ."<br>".$db->sql_error() );
            return false;
            }
         if ( $db->sql_numrows( $result ) == 0 )
            $pullstatus = 3;
         }

      //update the invoice
      $query = "update invInvoice set PullStatus = $pullstatus where InvoiceID = $invoiceid";
      if ( !$result = $db->sql_query( $query ) )
         {
         LogError( 14800, $query ."<br>".$db->sql_error() );
         return false;
         }
      }
   }

return true;
}

//07.14.2014 jss - ( 54610 ) this function checks the setting for allowing parts on unit sales
function allowPartsOnUnitSales()
{
global $db;

$query = "select NoMUSpecialOrders from optInvoiceOptions";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 14983, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
if ( $row['NoMUSpecialOrders'] == 0 )
	return true;
else
	return false;
}

//07.15.2014 jss - ( 54610 ) this function updates the deferred invoice credits for a unit sale
//NOTE:  If a non unit sale is passed in AND it's a deferred invoice that is linked to 1 or more
//unit sales, we'll update all the unit sale figures
function updateDeferredCredits( $invoiceid )
{
global $db;
global $RootPath;

$invtype = getInvType( $invoiceid );

//03.03.2015 jss - ( 64362 ) added invtype 11 for unit quote to the mix
if ( $invtype == 3 || $invtype == 11  )
	{
	//unit sale was passed in, so query the link table for this invoice so or loop below will work
	$mainquery = "select InvoiceID from invDeferredCredits where InvoiceID = $invoiceid";
	}
else
	{
	//possible deferred invoice was passed in, so query for all invoices that are linked to it
	//03.03.2015 jss - ( 64362 ) added invtype 11 for unit quotes
	$mainquery = "select InvoiceID 
						from invDeferredCredits 
						where LinkInvoiceID = $invoiceid
						and InvoiceID in ( select InvoiceID from invInvoice where InvType in (3,11) and Status < 3 )";
	}
if ( !$mainresult = $db->sql_query( $mainquery ) )
	{
	LogError( 14990, $mainquery ."<br>".$db->sql_error() );
	return false;
	}
while( $mainrow = $db->sql_fetchrow( $mainresult ) )
	{
	$invoiceid = $mainrow['InvoiceID'];

	//first we update invDeferredCredits.Amount for the deferred invoice that's linke to this unit sale.
	//we'll update it with the invoices CurrentBalance
	$query = "update invDeferredCredits, invInvoice
					set invDeferredCredits.Amount = invInvoice.BalanceDue
					where invDeferredCredits.InvoiceID = $invoiceid
					and invDeferredCredits.LinkInvoiceID > 0
					and invInvoice.InvoiceID = invDeferredCredits.LinkInvoiceID";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 14987, $query ."<br>".$db->sql_error() );
		return false;
		}

	//now we'll sum up the deferred credits from the link table
	$query = "select round(ifnull(sum(Amount),0),2) as amt
					from invDeferredCredits
					where InvoiceID = $invoiceid";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 14988, $query ."<br>".$db->sql_error() );
		return false;
		}
	$row = $db->sql_fetchrow( $result );

	//now update invInvoice.DeferredCredits
	$query = "update invInvoice
					set DeferredCredits = $row[amt]
					where InvoiceID = $invoiceid";
	if ( !$result = $db->sql_query( $query ) )
		{
		LogError( 14989, $query ."<br>".$db->sql_error() );
		return false;
		}

	//recalc the unit sale
	if ( useNewTaxClass() )
		{
		include_once( $RootPath."/invoicing/invoice_functions.php" );
		$invoice = new Invoice( $invoiceid );
		$invoice->totalInvoice();
		}
	else
		{
		include_once( $RootPath . '/invoicing/tax_functions.php' );
		$tax = new calculateInvoice( $invoiceid );
		}
	}

return true;
}

//05.08.2015 jss - ( 67343 ) this function returns the setting for how we handle webstore coupons
//Option #1 is our current way at the time this was written, whereas we split the coupon up among
//all the parts on the invoice as line item discounts.
//Option #2 is the old way, where we used a non inventory item to locate and use a dealer promo
//payment method on the payment form when cashing out the initial invoie.
function webCouponSetting()
{
global $db;

$query = "select * from invWebstoreGlobalOptions";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 16114, $query ."<br>".$db->sql_error() );
	return false;
	}
$row = $db->sql_fetchrow( $result );
return $row['DiscountOption'];
}


//TKS 05.14.2015 #66724 adding this function here because we need to check 
//in inventory, payables and invoicing.
//pass in itemid and it returns and array that tells us if the part is a parent or child
//or not in a relationship at all
function ItemChildParent( $itemid=0 )
{
global $db;
if ( $itemid == 0 )
	{
	$items[] = 0;//parent
	$items[] = 0;//child
	return $items;
	}

$query = "select ParentItemID, ItemID from itmParentLink where ( ParentItemID = ".$itemid." || ItemID = ".$itemid." )";
if ( !$result = $db->sql_query( $query ) )
	{
	LogError( 16147, $query ."<br>".$db->sql_error() );
	return 0;
	}

if ( $db->sql_numrows( $result ) == 0 ) 
	{
	$items[] = 0;//parent
	$items[] = 0;//child
	}
else
	{
	$row = $db->sql_fetchrow( $result ); 
	$items[]	= $row[ "ParentItemID" ];
	$items[]	= $row[ "ItemID" ];
	}
return $items;
}
?>
