<?php
/***************************************************************************
This  module handles actual database access and execution processes for
MYSQL4 database servers.
 ***************************************************************************/

if(!defined("SQL_LAYER"))
	{
	define("SQL_LAYER","mysql4");

class sql_db
{
var $db_connect_id;
var $query_result;
var $row = array();
var $rowset = array();
var $num_queries = 0;
var $in_transaction = 0;

//
// Constructor
//
function sql_db($sqlserver, $sqluser, $sqlpassword, $database, $persist = false, $newconnect = false)
{
$this->persistency = $persist;
$this->user = $sqluser;
$this->password = $sqlpassword;
$this->server = $sqlserver;
$this->dbname = $database;
$this->newconnection = $newconnect;

if ( $this->persistency )
	$this->db_connect_id = mysql_pconnect($this->server, $this->user, $this->password);
else
	$this->db_connect_id = mysql_connect($this->server, $this->user, $this->password, $this->newconnection);

if( $this->db_connect_id )
	{
	if( $database != "" )
		{
		$this->dbname = $database;
		$dbselect = mysql_select_db($this->dbname);

		if( !$dbselect )
			{
			mysql_close($this->db_connect_id);
			$this->db_connect_id = $dbselect;
			}
		}

	//setup UTF8
	//$this->sql_query( "SET NAMES 'utf8'" );
	//$this->sql_query( "SET character set 'utf8'" );
	return $this->db_connect_id;
	}
else
	{
	//08.21.2012 naj - Added this so we can track down some connection errors where a hostname was not provided
   $backtrace = debug_backtrace();
	error_log(print_r($backtrace, true));
	return false;
	}
}



//
// Other base methods
//
function sql_close()
{
if( $this->db_connect_id )
	{
	//
	// Commit any remaining transactions
	//
	if( $this->in_transaction )
		{
		mysql_query("COMMIT", $this->db_connect_id);
		}

	return mysql_close($this->db_connect_id);
	}
else
	{
	return false;
	}
}






//main query routine
function sql_query($query = "", $transaction = "")
{
unset( $this->query_result );

if ( $query != "" )
	{
	$this->num_queries++;

	//execute our query
	$this->query_result = mysql_query( $query, $this->db_connect_id );

	/*
	//03.28.2014 naj - added a check to re-execute the query if it failed due to a 1213 deadlock.
	if (!$this->query_result && mysql_errno($this->db_connect_id) == 1213)
		{
		$delaytime = rand(5, 250) * 1000;
		$errorlog = mysql_errno($this->db_connect_id)." Deadlock, retrying query in $delaytime microseconds\nDatabase: $this->dbname, Query: $query\n";
		usleep($delaytime);
		$this->query_result = mysql_query( $query, $this->db_connect_id );

		if ($this->query_result)
			$errorlog .= "Rerun successful";
		else
			$errorlog .= "Rerun failed";
			
		error_log($errorlog);
		}
	*/
	}

//if we received and execute our query we enter here to finish things up
//note: this won't execute if the query didn't run
//04.22.2014 naj - somehow the query result is not returning false when a deadlock occurs and the second attempt fails.
//04.22.2014 naj - changed this code to ensure that we return a status one way or another.
//if ( isset($this->query_result) && $this->query_result )
if ( isset($this->query_result) )
	{
	if ($this->query_result)
		{
		unset( $this->row[ $this->query_result ] );
		unset( $this->rowset[ $this->query_result ] );
		}

	return $this->query_result;
	}
else
	{
	//begin a new transaction
	if ( $transaction == 'BEGIN' && !$this->in_transaction )
		{
		if ( !mysql_query( "BEGIN", $this->db_connect_id ))
			return false;

		$this->in_transaction = TRUE;
		return true;
		}

	//end our transaction if they happen to send in a blank query with
	//COMMIT parameter
	if ( $transaction == 'COMMIT' && $this->in_transaction )
		{
		if ( !mysql_query( "COMMIT", $this->db_connect_id ) )
			{
			mysql_query( "ROLLBACK", $this->db_connect_id );
			$this->in_transaction = FALSE;
			return false;
			}

		$this->in_transaction = FALSE;
		return true;
		}
	else
		if ( $transaction == 'COMMIT' )
			{
			$this->sql_error = "There is No Transaction To Commit";
			return false;
			}

	//rollback a transaction 
	if ( $transaction == 'ROLLBACK' && $this->in_transaction )
		{
		if ( !mysql_query( "ROLLBACK", $this->db_connect_id ) )
			{
			$this->in_transaction = FALSE;
			return false;
			}

		$this->in_transaction = FALSE;
		return true;
		}
	else
		//02.07.2012 ghh - added to deal with rolling back whenthere is no transaction
		if ( $transaction == 'ROLLBACK' )
			{
			$this->sql_error = "There is No Transaction To Rollback";
			return false;
			}
	}
}




function sql_command($query = "", $transaction = "")
{
unset( $this->query_result );

if ( $query != "" )
	{
	$this->num_queries++;

	//execute our query
	$this->query_result = mysql_query( $query, $this->db_connect_id );
	}

//if we received and execute our query we enter here to finish things up
//note: this won't execute if the query didn't run
if ( $this->query_result )
	{
	unset( $this->row[ $this->query_result ] );
	unset( $this->rowset[ $this->query_result ] );

	return $this->query_result;
	}
else
	{
	//begin a new transaction
	if ( $transaction == 'BEGIN' && !$this->in_transaction )
		{
		if ( !mysql_query( "BEGIN", $this->db_connect_id ))
			return false;

		$this->in_transaction = TRUE;
		return true;
		}

	//end our transaction if they happen to send in a blank query with
	//COMMIT parameter
	if ( $transaction == 'COMMIT' && $this->in_transaction )
		{
		if ( !mysql_query( "COMMIT", $this->db_connect_id ) )
			{
			mysql_query( "ROLLBACK", $this->db_connect_id );
			$this->in_transaction = FALSE;
			return false;
			}
		$this->in_transaction = FALSE;
		return true;
		}

	//rollback a transaction 
	if ( $transaction == 'ROLLBACK' && $this->in_transaction )
		{
		if ( !mysql_query( "ROLLBACK", $this->db_connect_id ) )
			{
			$this->in_transaction = FALSE;
			return false;
			}
		$this->in_transaction = FALSE;
		return true;
		}
	}
}





//
// Other query methods
//
function sql_numrows($query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

//08.21.2012 naj - added this to trackdown where sql_numrow is being called with a boolean instead of a queryid
if (is_bool($query_id))
	{
   $backtrace = debug_backtrace();
	error_log(print_r($backtrace, true));
	}

return ( $query_id ) ? mysql_num_rows($query_id) : false;
}











function sql_affectedrows()
{
return ( $this->db_connect_id ) ? mysql_affected_rows($this->db_connect_id) : false;
}







function sql_numfields($query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

return ( $query_id ) ? mysql_num_fields($query_id) : false;
}






function sql_fieldname($offset, $query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

return ( $query_id ) ? mysql_field_name($query_id, $offset) : false;
}






function sql_fieldtype($offset, $query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

return ( $query_id ) ? mysql_field_type($query_id, $offset) : false;
}






function sql_fetchrow($query_id = 0)
{
//12.8.2009 ghh added this unset due to memory leak situation.
unset( $this->row );
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

if( $query_id )
	{
	//05.15.2013 naj - added boolean check to stop the following php log.
	//mysql_fetch_array() expects parameter 1 to be resource, boolean given in /var/www/nizex.com/lizzy/db/mysql4.php on line 337
	if (is_bool($query_id))
		return false;

	$this->row[(int)$query_id] = mysql_fetch_array($query_id, MYSQL_ASSOC);
	return $this->row[(int)$query_id];
	}
else
	{
	return false;
	}
}







function sql_fetchrowset($query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

if( $query_id )
	{
	unset($this->rowset[(int)$query_id]);
	unset($this->row[(int)$query_id]);

	while($this->rowset[(int)$query_id] = mysql_fetch_array($query_id, MYSQL_ASSOC))
		{
		$result[] = $this->rowset[(int)$query_id];
		}

	return $result;
	}
else
	{
	return false;
	}
}







function sql_fetchfield($field, $rownum = -1, $query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

if( $query_id )
	{
	if( $rownum > -1 )
		{
		$result = mysql_result($query_id, $rownum, $field);
		}
	else
		{
		if( empty($this->row[(int)$query_id]) && empty($this->rowset[(int)$query_id]) )
			{
			if( $this->sql_fetchrow() )
				{
				$result = $this->row[(int)$query_id][$field];
				}
			}
		else
			{
			if( $this->rowset[(int)$query_id] )
				{
				$result = $this->rowset[(int)$query_id][0][$field];
				}
			else if( $this->row[(int)$query_id] )
				{
				$result = $this->row[(int)$query_id][$field];
				}
			}
		}

	return $result;
	}
else
	{
	return false;
	}
}






function sql_rowseek($rownum, $query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}


return ( $query_id ) ? @mysql_data_seek($query_id, $rownum) : false;
}







function sql_nextid()
{
return ( $this->db_connect_id ) ? mysql_insert_id($this->db_connect_id) : false;
}









function sql_freeresult($query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

if ( $query_id )
	{
	unset($this->row[(int)$query_id]);
	unset($this->rowset[(int)$query_id]);

	mysql_free_result($query_id);

	return true;
	}
else
	{
	return false;
	}
}








function sql_error()
{
$message = mysql_error($this->db_connect_id);
$code = mysql_errno($this->db_connect_id);

return $code. ' - '. $message;
}

} // class sql_db

	} // if ... define

?>
