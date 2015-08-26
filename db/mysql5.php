<?
//03.27.2014 naj - this is the rewrite of the nizex sql_db class.
//This version now uses mysqli

class sql_db extends mysqli
{
public $db_connect_id = false;
public $query_result;
public $in_transaction = false;

//03.28.2014 naj - The mysqli interface does not use a new connect flag
//have kept the same parameters and order for backwards compatability.
public function __construct($sqlserver, $sqluser, $sqlpassword, $database, $persist = false, $newconnect = false)
{
//04.08.2014 naj - while we do have a persist flag, I am ignoring this flag for the moment.
//mysqli has proper connection pooling, so I am turning persistent off for every connection.
parent::__construct("$sqlserver", $sqluser, $sqlpassword, $database); //Not persistant
//parent::__construct("p:$sqlserver", $sqluser, $sqlpassword, $database); //Persistant

if (mysqli_connect_error())
	{
	//03.27.2014 naj - if we failed to connect produce some debugging output.
	//04.09.2014 naj - turned this off as it causes warnings with the new mysqli interface
	//error_log(print_r(debug_backtrace(), true));
	$this->db_connect_id = false;
	return false;
	}
else
	$this->db_connect_id = true;

//03.06.2015 naj - added setting for forcing utf8 on the databases connection.
$this->set_charset('utf8');
}


public function __destruct()
{
$this->sql_close();
}


public function sql_close()
{
if (!$this->db_connect_id)
	return false;

//
// Commit any remaining transactions
//
if( $this->in_transaction )
	{
	if ($this->query("COMMIT"))
		{
		$this->in_transaction = false;
		return true;
		}
	else
		return false;
	}

$this->db_connect_id = false;
return $this->close();
}


//main query routine
public function sql_query($query = "", $transaction = "")
{
unset( $this->query_result );

//03.27.2014 naj - handle transactions
if ($query == '')
	{
	if ($transaction == "BEGIN" && !$this->in_transaction)
		{
		if ($this->query("BEGIN"))
			{
			$this->in_transaction = true;
			return true;
			}
		else
			return false;
		}

	if ($transaction == "COMMIT" && $this->in_transaction)
		{
		if ($this->query("COMMIT"))
			{
			$this->in_transaction = false;
			return true;
			}
		else
			return false;
		}
	if ($transaction == "ROLLBACK" && $this->in_transaction)
		{
		if ($this->query("ROLLBACK"))
			{
			$this->in_transaction = false;
			return true;
			}
		else
			return false;
		}
	else
		//02.07.2012 ghh - added to deal with rolling back whenthere is no transaction
		if ( $transaction == 'ROLLBACK' )
			{
			$this->sql_error = "There is No Transaction To Rollback";
			return false;
			}
	
	//03.27.2014 naj - if we got this far then we have no query and no valid transaction command
	return false;
	}

//execute our query
$this->query_result = $this->query($query);

/*
//03.28.2014 naj - added a check to re-execute the query if it failed due to a 1213 deadlock.
if (!$this->query_result && $this->errno == 1213)
	{
	$delaytime = rand(5, 250) * 1000;
	$errorlog = "$this->errno Deadlock, retrying query in $delaytime microseconds\nDatabase: $this->dbname, Query: $query\n";
	usleep($delaytime);
	$this->query_result = $this->query($query);

	if ($this->query_result)
		$errorlog .= "Rerun successful";
	else
		$errorlog .= "Rerun failed";
		
	error_log($errorlog);
	}
*/

return $this->query_result;
}


//
// Other query methods
//
public function sql_numrows($query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

return $query_id->num_rows;
}


public function sql_affectedrows()
{
return $this->affected_rows;
}


public function sql_numfields($query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

return $query_id->field_count;
}


public function sql_fieldname($offset, $query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

if ($name = $query_id->fetch_field_direct($offset))
	return $name->name;
else
	return false;
}


public function sql_fetchrow($query_id = 0)
{
//12.8.2009 ghh added this unset due to memory leak situation.
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

	return $query_id->fetch_assoc();
	}
else
	{
	return false;
	}
}


public function sql_rowseek($rownum, $query_id = 0)
{
if( !$query_id )
	{
	$query_id = $this->query_result;
	}

return $query_id->data_seek($rownum);
}


public function sql_nextid()
{
return $this->insert_id;
}


public function sql_error()
{
$message = $this->error;
$code = $this->errno;

return $code. ' - '. $message;
}

} // class sql_db
?>
