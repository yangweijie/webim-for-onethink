<?php
namespace Addons\Webim\Lib;
/**
 * WebIM DB Class
 *
 * Original code from WordPress DB Class
 *
 */


/**
 * @since 0.71
 */
define( 'OBJECT', 'OBJECT', true );

/**
 * @since 2.5.0
 */
define( 'OBJECT_K', 'OBJECT_K' );

/**
 * @since 0.71
 */
define( 'ARRAY_A', 'ARRAY_A' );

/**
 * @since 0.71
 */
define( 'ARRAY_N', 'ARRAY_N' );


class WebimDB {

	/**
	 * Whether to show SQL/DB errors
	 *
	 * @since 0.71
	 * @access private
	 * @var bool
	 */
	var $show_errors = false;

	/**
	 * Whether the database queries are ready to start executing.
	 *
	 * @since 2.5.0
	 * @access private
	 * @var bool
	 */
	var $ready = false;

	/**
	 * Whether to suppress errors during the DB bootstrapping.
	 *
	 * @access private
	 * @since 2.5
	 * @var bool
	 */
	var $suppress_errors = false;

	/**
	 * The last error during query.
	 *
	 * @see get_last_error()
	 * @since 2.5
	 * @access private
	 * @var string
	 */
	var $last_error = '';

	/**
	 * Amount of queries made
	 *
	 * @since 1.2.0
	 * @access private
	 * @var int
	 */
	var $num_queries = 0;

	/**
	 * Count of rows returned by previous query
	 *
	 * @since 1.2
	 * @access private
	 * @var int
	 */
	var $num_rows = 0;

	/**
	 * Count of affected rows by previous query
	 *
	 * @since 0.71
	 * @access private
	 * @var int
	 */
	var $rows_affected = 0;

	/**
	 * The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
	 *
	 * @since 0.71
	 * @access public
	 * @var int
	 */
	var $insert_id = 0;

	/**
	 * Saved result of the last query made
	 *
	 * @since 1.2.0
	 * @access private
	 * @var array
	 */
	var $last_query;

	/**
	 * Results of the last query made
	 *
	 * @since 1.0.0
	 * @access private
	 * @var array|null
	 */
	var $last_result;

	/**
	 * Saved info on the table column
	 *
	 * @since 1.2.0
	 * @access private
	 * @var array
	 */
	var $col_info;

	/**
	 * Saved queries that were executed
	 *
	 * @since 1.5.0
	 * @access private
	 * @var array
	 */
	var $queries;

	/**
	 * Table prefix
	 *
	 */
	var $prefix = '';

	/**
	 * List tables
	 *
	 * @since 2.5.0
	 * @access private
	 * @see wpdb::tables()
	 * @var array
	 */
	var $tables = array();

	/**
	 * Format specifiers for DB columns. Columns not listed here default to %s. Initialized during WP load.
	 *
	 * Keys are column names, values are format types: 'ID' => '%d'
	 *
	 * @since 2.8.0
	 * @see wpdb:prepare()
	 * @see wpdb:insert()
	 * @see wpdb:update()
	 * @see wp_set_wpdb_vars()
	 * @access public
	 * @var array
	 */
	var $field_types = array();

	/**
	 * Database table columns charset
	 *
	 */
	var $charset;

	/**
	 * Database table columns collate
	 *
	 * @var string
	 */
	var $collate;

	/**
	 * A textual description of the last query/get_row/get_var call
	 *
	 * @since unknown
	 * @access public
	 * @var string
	 */
	var $func_call;

	/**
	 * Connects to the database server and selects a database
	 *
	 * PHP4 compatibility layer for calling the PHP5 constructor.
	 *
	 * @uses __construct() Passes parameters and returns result
	 * @since 0.71
	 *
	 * @param string $dbuser MySQL database user
	 * @param string $dbpassword MySQL database password
	 * @param string $dbname MySQL database name
	 * @param string $dbhost MySQL database host
	 */
	function webim_db( $dbuser, $dbpassword, $dbname, $dbhost ) {
		return $this->__construct( $dbuser, $dbpassword, $dbname, $dbhost );
	}

	/**
	 * Connects to the database server and selects a database
	 *
	 * PHP5 style constructor for compatibility with PHP5. Does
	 * the actual setting up of the class properties and connection
	 * to the database.
	 *
	 * @link http://core.trac.wordpress.org/ticket/3354
	 * @since 2.0.8
	 *
	 * @param string $dbuser MySQL database user
	 * @param string $dbpassword MySQL database password
	 * @param string $dbname MySQL database name
	 * @param string $dbhost MySQL database host
	 */
	function __construct( $dbuser, $dbpassword, $dbname, $dbhost ) {
		register_shutdown_function( array( &$this, '__destruct' ) );

		if ( defined( 'WEBIMDB_DEBUG' ) && WEBIMDB_DEBUG )
			$this->show_errors();

		if ( defined( 'WEBIMDB_COLLATE' ) ) {
			$this->collate = WEBIMDB_COLLATE;
		}

		if ( defined( 'WEBIMDB_CHARSET' ) )
			$this->charset = WEBIMDB_CHARSET;

		$this->dbh = @mysql_connect( $dbhost, $dbuser, $dbpassword, true );
		if ( !$this->dbh ) {
			$this->bail( sprintf( /*WP_I18N_DB_CONN_ERROR*/"
				<h1>Error establishing a database connection</h1>
				<ul>
				<li>Are you sure you have the correct username and password?</li>
				<li>Are you sure that you have typed the correct hostname?</li>
				<li>Are you sure that the database server is running?</li>
				</ul>
				"/*/WP_I18N_DB_CONN_ERROR*/, $dbhost ), 'db_connect_fail' );
			return;
		}

		$this->ready = true;

		if ( $this->has_cap( 'collation' ) && !empty( $this->charset ) ) {
			if ( function_exists( 'mysql_set_charset' ) ) {
				mysql_set_charset( $this->charset, $this->dbh );
				$this->real_escape = true;
			} else {
				$query = $this->prepare( 'SET NAMES %s', $this->charset );
				if ( ! empty( $this->collate ) )
					$query .= $this->prepare( ' COLLATE %s', $this->collate );
				$this->query( $query );
			}
		}

		$this->select( $dbname, $this->dbh );
	}

	/**
	 * PHP5 style destructor and will run when database object is destroyed.
	 *
	 * @see wpdb::__construct()
	 * @return bool true
	 */
	function __destruct() {
		return true;
	}

	/**
	 * Returns an array of tables.
	 *
	 * @return array Table names. When a prefix is requested, the key is the unprefixed table name.
	 */
	function tables() {
		$tables = $this->tables;
		$prefix = $this->prefix;
		if ( $prefix ) {
			foreach( $tables as $k => $table ) {
				$tables[ $table ] = $prefix . $table;
				unset( $tables[ $k ] );
			}
		}
		return $tables;
	}

	/**
	 * Add tables.
	 *
	 * @param array $tables will be add.
	 * @return void
	 */
	function add_tables( $tables ) {
		$this->tables = array_unique( array_merge( $this->tables, $tables ) );
		$prefix = $this->prefix;
		if ( $prefix ) {
			foreach ( $this->tables() as $table => $prefixed_table )
				$this->$table = $prefixed_table;
		}
	}


	/**
	 * Sets the table prefix for the WordPress tables.
	 *
	 * @since 2.5.0
	 *
	 * @param string $prefix Alphanumeric name for the new prefix.
	 * @return false on invalid or old_prefix.
	 */
	function set_prefix( $prefix, $set_table_names = true ) {

		if ( preg_match( '|[^a-z0-9_]|i', $prefix ) ) {
			$this->bail( /*WP_I18N_DB_BAD_PREFIX*/'Invalid database prefix'/*/WP_I18N_DB_BAD_PREFIX*/ ,'invalid_db_prefix' );
			return false;
		}

		if ( $set_table_names ) {
			foreach ( $this->tables() as $table => $prefixed_table )
				$this->$table = $prefixed_table;
		}

		$old_prefix = $this->prefix;
		$this->prefix = $prefix;
		return $old_prefix;
	}

	/**
	 * Selects a database using the current database connection.
	 *
	 * The database name will be changed based on the current database
	 * connection. On failure, the execution will bail and display an DB error.
	 *
	 * @since 0.71
	 *
	 * @param string $db MySQL database name
	 * @param resource $dbh Optional link identifier.
	 * @return null Always null.
	 */
	function select( $db, $dbh = null) {
		if ( is_null($dbh) )
			$dbh = $this->dbh;

		if ( !@mysql_select_db( $db, $dbh ) ) {
			$this->ready = false;
			$this->bail( sprintf( /*WP_I18N_DB_SELECT_DB*/'<h1>Can&#8217;t select database</h1>
				<p>We were able to connect to the database server (which means your username and password is okay) but not able to select the <code>%1$s</code> database.</p>
				<ul>
				<li>Are you sure it exists?</li>
				<li>Does the user <code>%2$s</code> have permission to use the <code>%1$s</code> database?</li>
				<li>On some systems the name of your database is prefixed with your username, so it would be like <code>username_%1$s</code>. Could that be the problem?</li>
				</ul>
				'/*/WP_I18N_DB_SELECT_DB*/, $db, $this->dbuser ), 'db_select_fail' );
			return;
		}
	}


	/**
	 * Perform a MySQL database query, using current database connection.
	 *
	 * More information can be found on the codex page.
	 *
	 * @since 0.71
	 *
	 * @param string $query Database query
	 * @return int|false Number of rows affected/selected or false on error
	 */
	function query( $query ) {
		if ( ! $this->ready )
			return false;

		$return_val = 0;
		$this->flush();

		// Log how the function was called
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug..
		$this->last_query = $query;

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
			$this->timer_start();

		$dbh =& $this->dbh;
		$this->last_db_used = "other/read";

		$this->result = @mysql_query( $query, $dbh );
		$this->num_queries++;

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
			$this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );

		// If there is an error then take note of it..
		if ( $this->last_error = mysql_error( $dbh ) ) {
			$this->print_error();
			return false;
		}

		if ( preg_match( "/^\\s*(insert|delete|update|replace|alter) /i", $query ) ) {
			$this->rows_affected = mysql_affected_rows( $dbh );
			// Take note of the insert_id
			if ( preg_match( "/^\\s*(insert|replace) /i", $query ) ) {
				$this->insert_id = mysql_insert_id($dbh);
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$i = 0;
			while ( $i < @mysql_num_fields( $this->result ) ) {
				$this->col_info[$i] = @mysql_fetch_field( $this->result );
				$i++;
			}
			$num_rows = 0;
			while ( $row = @mysql_fetch_object( $this->result ) ) {
				$this->last_result[$num_rows] = $row;
				$num_rows++;
			}

			@mysql_free_result( $this->result );

			// Log number of rows the query returned
			// and return number of rows selected
			$this->num_rows = $num_rows;
			$return_val     = $num_rows;
		}

		return $return_val;
	}

	/**
	 * Insert a row into a table.
	 *
	 * <code>
	 * wpdb::insert( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
	 * wpdb::insert( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
	 * </code>
	 *
	 * @since 2.5.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $data Data to insert (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @return int|false The number of rows inserted, or false on error.
	 */
	function insert( $table, $data, $format = null ) {
		return $this->_insert_replace_helper( $table, $data, $format, 'INSERT' );
	}

	/**
	 * Replace a row into a table.
	 *
	 * <code>
	 * wpdb::replace( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
	 * wpdb::replace( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
	 * </code>
	 *
	 * @since 3.0.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $data Data to insert (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @return int|false The number of rows affected, or false on error.
	 */
	function replace( $table, $data, $format = null ) {
		return $this->_insert_replace_helper( $table, $data, $format, 'REPLACE' );
	}

	/**
	 * Helper function for insert and replace.
	 *
	 * Runs an insert or replace query based on $type argument.
	 *
	 * @access private
	 * @since 3.0.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $data Data to insert (in column => value pairs).  Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @return int|false The number of rows affected, or false on error.
	 */
	function _insert_replace_helper( $table, $data, $format = null, $type = 'INSERT' ) {
		if ( ! in_array( strtoupper( $type ), array( 'REPLACE', 'INSERT' ) ) )
			return false;
		$formats = $format = (array) $format;
		$fields = array_keys( $data );
		$formatted_fields = array();
		foreach ( $fields as $field ) {
			if ( !empty( $format ) )
				$form = ( $form = array_shift( $formats ) ) ? $form : $format[0];
			elseif ( isset( $this->field_types[$field] ) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$formatted_fields[] = $form;
		}
		$sql = "{$type} INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
		return $this->query( $this->prepare( $sql, $data ) );
	}

	/**
	 * Update a row in the table
	 *
	 * <code>
	 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 'bar' ), array( 'ID' => 1 ) )
	 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( 'ID' => 1 ), array( '%s', '%d' ), array( '%d' ) )
	 * </code>
	 *
	 * @since 2.5.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $data Data to update (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array $where A named array of WHERE clauses (in column => value pairs). Multiple clauses will be joined with ANDs. Both $where columns and $where values should be "raw".
	 * @param array|string $format Optional. An array of formats to be mapped to each of the values in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @param array|string $format_where Optional. An array of formats to be mapped to each of the values in $where. If string, that format will be used for all of the items in $where.  A format is one of '%d', '%s' (decimal number, string).  If omitted, all values in $where will be treated as strings.
	 * @return int|false The number of rows updated, or false on error.
	 */
	function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( ! is_array( $data ) || ! is_array( $where ) )
			return false;

		$formats = $format = (array) $format;
		$bits = $wheres = array();
		foreach ( (array) array_keys( $data ) as $field ) {
			if ( !empty( $format ) )
				$form = ( $form = array_shift( $formats ) ) ? $form : $format[0];
			elseif ( isset($this->field_types[$field]) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$bits[] = "`$field` = {$form}";
		}

		$where_formats = $where_format = (array) $where_format;
		foreach ( (array) array_keys( $where ) as $field ) {
			if ( !empty( $where_format ) )
				$form = ( $form = array_shift( $where_formats ) ) ? $form : $where_format[0];
			elseif ( isset( $this->field_types[$field] ) )
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$wheres[] = "`$field` = {$form}";
		}

		$sql = "UPDATE `$table` SET " . implode( ', ', $bits ) . ' WHERE ' . implode( ' AND ', $wheres );
		return $this->query( $this->prepare( $sql, array_merge( array_values( $data ), array_values( $where ) ) ) );
	}

	/**
	 * Retrieve one variable from the database.
	 *
	 * Executes a SQL query and returns the value from the SQL result.
	 * If the SQL result contains more than one column and/or more than one row, this function returns the value in the column and row specified.
	 * If $query is null, this function returns the value in the specified column and row from the previous SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query Optional. SQL query. Defaults to null, use the result from the previous query.
	 * @param int $x Optional. Column of value to return.  Indexed from 0.
	 * @param int $y Optional. Row of value to return.  Indexed from 0.
	 * @return string|null Database query result (as string), or null on failure
	 */
	function get_var( $query = null, $x = 0, $y = 0 ) {
		$this->func_call = "\$db->get_var(\"$query\", $x, $y)";
		if ( $query )
			$this->query( $query );

		// Extract var out of cached results based x,y vals
		if ( !empty( $this->last_result[$y] ) ) {
			$values = array_values( get_object_vars( $this->last_result[$y] ) );
		}

		// If there is a value return it else return null
		return ( isset( $values[$x] ) && $values[$x] !== '' ) ? $values[$x] : null;
	}

	/**
	 * Retrieve one row from the database.
	 *
	 * Executes a SQL query and returns the row from the SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query SQL query.
	 * @param string $output Optional. one of ARRAY_A | ARRAY_N | OBJECT constants. Return an associative array (column => value, ...),
	 * 	a numerically indexed array (0 => value, ...) or an object ( ->column = value ), respectively.
	 * @param int $y Optional. Row to return. Indexed from 0.
	 * @return mixed Database query result in format specifed by $output or null on failure
	 */
	function get_row( $query = null, $output = OBJECT, $y = 0 ) {
		$this->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if ( $query )
			$this->query( $query );
		else
			return null;

		if ( !isset( $this->last_result[$y] ) )
			return null;

		if ( $output == OBJECT ) {
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		} elseif ( $output == ARRAY_A ) {
			return $this->last_result[$y] ? get_object_vars( $this->last_result[$y] ) : null;
		} elseif ( $output == ARRAY_N ) {
			return $this->last_result[$y] ? array_values( get_object_vars( $this->last_result[$y] ) ) : null;
		} else {
			$this->print_error(/*WP_I18N_DB_GETROW_ERROR*/" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N"/*/WP_I18N_DB_GETROW_ERROR*/);
		}
	}

	/**
	 * Retrieve one column from the database.
	 *
	 * Executes a SQL query and returns the column from the SQL result.
	 * If the SQL result contains more than one column, this function returns the column specified.
	 * If $query is null, this function returns the specified column from the previous SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query Optional. SQL query. Defaults to previous query.
	 * @param int $x Optional. Column to return. Indexed from 0.
	 * @return array Database query result. Array indexed from 0 by SQL result row number.
	 */
	function get_col( $query = null , $x = 0 ) {
		if ( $query )
			$this->query( $query );

		$new_array = array();
		// Extract the column values
		for ( $i = 0, $j = count( $this->last_result ); $i < $j; $i++ ) {
			$new_array[$i] = $this->get_var( null, $x, $i );
		}
		return $new_array;
	}

	/**
	 * Retrieve an entire SQL result set from the database (i.e., many rows)
	 *
	 * Executes a SQL query and returns the entire SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string $query SQL query.
	 * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants. With one of the first three, return an array of rows indexed from 0 by SQL result row number.
	 * 	Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
	 * 	With OBJECT_K, return an associative array of row objects keyed by the value of each row's first column's value.  Duplicate keys are discarded.
	 * @return mixed Database query results
	 */
	function get_results( $query = null, $output = OBJECT ) {
		$this->func_call = "\$db->get_results(\"$query\", $output)";

		if ( $query )
			$this->query( $query );
		else
			return null;

		$new_array = array();
		if ( $output == OBJECT ) {
			// Return an integer-keyed array of row objects
			return $this->last_result;
		} elseif ( $output == OBJECT_K ) {
			// Return an array of row objects with keys from column 1
			// (Duplicates are discarded)
			foreach ( $this->last_result as $row ) {
				$key = array_shift( $var_by_ref = get_object_vars( $row ) );
				if ( ! isset( $new_array[ $key ] ) )
					$new_array[ $key ] = $row;
			}
			return $new_array;
		} elseif ( $output == ARRAY_A || $output == ARRAY_N ) {
			// Return an integer-keyed array of...
			if ( $this->last_result ) {
				foreach( (array) $this->last_result as $row ) {
					if ( $output == ARRAY_N ) {
						// ...integer-keyed row arrays
						$new_array[] = array_values( get_object_vars( $row ) );
					} else {
						// ...column name-keyed row arrays
						$new_array[] = get_object_vars( $row );
					}
				}
			}
			return $new_array;
		}
		return null;
	}

	/**
	 * Retrieve column metadata from the last query.
	 *
	 * @since 0.71
	 *
	 * @param string $info_type Optional. Type one of name, table, def, max_length, not_null, primary_key, multiple_key, unique_key, numeric, blob, type, unsigned, zerofill
	 * @param int $col_offset Optional. 0: col name. 1: which table the col's in. 2: col's max length. 3: if the col is numeric. 4: col's type
	 * @return mixed Column Results
	 */
	function get_col_info( $info_type = 'name', $col_offset = -1 ) {
		if ( $this->col_info ) {
			if ( $col_offset == -1 ) {
				$i = 0;
				$new_array = array();
				foreach( (array) $this->col_info as $col ) {
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			} else {
				return $this->col_info[$col_offset]->{$info_type};
			}
		}
	}

	/**
	 * Prepares a SQL query for safe execution. Uses sprintf()-like syntax.
	 *
	 * The following directives can be used in the query format string:
	 *   %d (decimal number)
	 *   %s (string)
	 *   %% (literal percentage sign - no argument needed)
	 *
	 * Both %d and %s are to be left unquoted in the query string and they need an argument passed for them.
	 * Literals (%) as parts of the query must be properly written as %%.
	 *
	 * This function only supports a small subset of the sprintf syntax; it only supports %d (decimal number), %s (string).
	 * Does not support sign, padding, alignment, width or precision specifiers.
	 * Does not support argument numbering/swapping.
	 *
	 * May be called like {@link http://php.net/sprintf sprintf()} or like {@link http://php.net/vsprintf vsprintf()}.
	 *
	 * Both %d and %s should be left unquoted in the query string.
	 *
	 * <code>
	 * wpdb::prepare( "SELECT * FROM `table` WHERE `column` = %s AND `field` = %d", 'foo', 1337 )
	 * wpdb::prepare( "SELECT DATE_FORMAT(`field`, '%%c') FROM `table` WHERE `column` = %s", 'foo' );
	 * </code>
	 *
	 * @link http://php.net/sprintf Description of syntax.
	 * @since 2.3.0
	 *
	 * @param string $query Query statement with sprintf()-like placeholders
	 * @param array|mixed $args The array of variables to substitute into the query's placeholders if being called like
	 * 	{@link http://php.net/vsprintf vsprintf()}, or the first variable to substitute into the query's placeholders if
	 * 	being called like {@link http://php.net/sprintf sprintf()}.
	 * @param mixed $args,... further variables to substitute into the query's placeholders if being called like
	 * 	{@link http://php.net/sprintf sprintf()}.
	 * @return null|false|string Sanitized query string, null if there is no query, false if there is an error and string
	 * 	if there was something to prepare
	 */
	function prepare( $query = null ) { // ( $query, *$args )
		if ( is_null( $query ) )
			return;

		$args = func_get_args();
		array_shift( $args );
		// If args were passed as an array (as in vsprintf), move them up
		if ( isset( $args[0] ) && is_array($args[0]) )
			$args = $args[0];
		$query = str_replace( "'%s'", '%s', $query ); // in case someone mistakenly already singlequoted it
		$query = str_replace( '"%s"', '%s', $query ); // doublequote unquoting
		$query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s
		array_walk( $args, array( &$this, 'escape_by_ref' ) );
		return @vsprintf( $query, $args );
	}

	/**
	 * Kill cached query results.
	 *
	 * @since 0.71
	 * @return void
	 */
	function flush() {
		$this->last_result = array();
		$this->col_info    = null;
		$this->last_query  = null;
	}

	/**
	 * Starts the timer, for debugging purposes.
	 *
	 * @since 1.5.0
	 *
	 * @return true
	 */
	function timer_start() {
		$mtime            = explode( ' ', microtime() );
		$this->time_start = $mtime[1] + $mtime[0];
		return true;
	}

	/**
	 * Stops the debugging timer.
	 *
	 * @since 1.5.0
	 *
	 * @return int Total time spent on the query, in milliseconds
	 */
	function timer_stop() {
		$mtime      = explode( ' ', microtime() );
		$time_end   = $mtime[1] + $mtime[0];
		$time_total = $time_end - $this->time_start;
		return $time_total;
	}

	/**
	 * Print SQL/DB error.
	 *
	 * @since 0.71
	 * @global array $EZSQL_ERROR Stores error information of query and error string
	 *
	 * @param string $str The error to display
	 * @return bool False if the showing of errors is disabled.
	 */
	function print_error( $str = '' ) {
		global $EZSQL_ERROR;

		if ( !$str )
			$str = mysql_error( $this->dbh );
		$EZSQL_ERROR[] = array( 'query' => $this->last_query, 'error_str' => $str );

		if ( $this->suppress_errors )
			return false;

		if ( $caller = $this->get_caller() )
			$error_str = sprintf( /*WP_I18N_DB_QUERY_ERROR_FULL*/'Database error %1$s for query %2$s made by %3$s'/*/WP_I18N_DB_QUERY_ERROR_FULL*/, $str, $this->last_query, $caller );
		else
			$error_str = sprintf( /*WP_I18N_DB_QUERY_ERROR*/'Database error %1$s for query %2$s'/*/WP_I18N_DB_QUERY_ERROR*/, $str, $this->last_query );

		if ( function_exists( 'error_log' )
			&& ( $log_file = @ini_get( 'error_log' ) )
			&& ( 'syslog' == $log_file || @is_writable( $log_file ) )
		)
		@error_log( $error_str );

		// Are we showing errors?
		if ( ! $this->show_errors )
			return false;

		$str   = htmlspecialchars( $str, ENT_QUOTES );
		$query = htmlspecialchars( $this->last_query, ENT_QUOTES );

		print "<div id='error'>
			<p class='wpdberror'><strong>Database error:</strong> [$str]<br />
			<code>$query</code></p>
			</div>";
	}

	/**
	 * Weak escape, using addslashes()
	 *
	 * @see addslashes()
	 * @since 2.8.0
	 * @access private
	 *
	 * @param string $string
	 * @return string
	 */
	function _weak_escape( $string ) {
		return addslashes( $string );
	}

	/**
	 * Real escape, using mysql_real_escape_string() or addslashes()
	 *
	 * @see mysql_real_escape_string()
	 * @see addslashes()
	 * @since 2.8
	 * @access private
	 *
	 * @param  string $string to escape
	 * @return string escaped
	 */
	function _real_escape( $string ) {
		if ( $this->dbh && $this->real_escape )
			return mysql_real_escape_string( $string, $this->dbh );
		else
			return addslashes( $string );
	}

	/**
	 * Escape data. Works on arrays.
	 *
	 * @uses wpdb::_escape()
	 * @uses wpdb::_real_escape()
	 * @since  2.8
	 * @access private
	 *
	 * @param  string|array $data
	 * @return string|array escaped
	 */
	function _escape( $data ) {
		if ( is_array( $data ) ) {
			foreach ( (array) $data as $k => $v ) {
				if ( is_array($v) )
					$data[$k] = $this->_escape( $v );
				else
					$data[$k] = $this->_real_escape( $v );
			}
		} else {
			$data = $this->_real_escape( $data );
		}

		return $data;
	}

	/**
	 * Escapes content for insertion into the database using addslashes(), for security.
	 *
	 * Works on arrays.
	 *
	 * @since 0.71
	 * @param string|array $data to escape
	 * @return string|array escaped as query safe string
	 */
	function escape( $data ) {
		if ( is_array( $data ) ) {
			foreach ( (array) $data as $k => $v ) {
				if ( is_array( $v ) )
					$data[$k] = $this->escape( $v );
				else
					$data[$k] = $this->_weak_escape( $v );
			}
		} else {
			$data = $this->_weak_escape( $data );
		}

		return $data;
	}

	/**
	 * Escapes content by reference for insertion into the database, for security
	 *
	 * @uses wpdb::_real_escape()
	 * @since 2.3.0
	 * @param string $string to escape
	 * @return void
	 */
	function escape_by_ref( &$string ) {
		$string = $this->_real_escape( $string );
	}

	/**
	 * Enables showing of database errors.
	 *
	 * This function should be used only to enable showing of errors.
	 * wpdb::hide_errors() should be used instead for hiding of errors. However,
	 * this function can be used to enable and disable showing of database
	 * errors.
	 *
	 * @since 0.71
	 * @see wpdb::hide_errors()
	 *
	 * @param bool $show Whether to show or hide errors
	 * @return bool Old value for showing errors.
	 */
	function show_errors( $show = true ) {
		$errors = $this->show_errors;
		$this->show_errors = $show;
		return $errors;
	}

	/**
	 * Disables showing of database errors.
	 *
	 * By default database errors are not shown.
	 *
	 * @since 0.71
	 * @see wpdb::show_errors()
	 *
	 * @return bool Whether showing of errors was active
	 */
	function hide_errors() {
		$show = $this->show_errors;
		$this->show_errors = false;
		return $show;
	}

	/**
	 * Whether to suppress database errors.
	 *
	 * By default database errors are suppressed, with a simple
	 * call to this function they can be enabled.
	 *
	 * @since 2.5
	 * @see wpdb::hide_errors()
	 * @param bool $suppress Optional. New value. Defaults to true.
	 * @return bool Old value
	 */
	function suppress_errors( $suppress = true ) {
		$errors = $this->suppress_errors;
		$this->suppress_errors = (bool) $suppress;
		return $errors;
	}

	/**
	 * Wraps errors in a nice header and footer and dies.
	 *
	 * Will not die if wpdb::$show_errors is true
	 *
	 * @param string $message The Error message
	 * @param string $error_code Optional. A Computer readable string to identify the error.
	 * @return false|void
	 */
	function bail( $message, $error_code = '500' ) {
		if ( !$this->show_errors ) {
			$this->error = $message;
			return false;
		}
		die($message);
	}

	/**
	 * Retrieve the name of the function that called wpdb.
	 *
	 * Searches up the list of functions until it reaches
	 * the one that would most logically had called this method.
	 *
	 * @since 2.5.0
	 *
	 * @return string The name of the calling function
	 */
	function get_caller() {
		$trace  = array_reverse( debug_backtrace() );
		$caller = array();

		foreach ( $trace as $call ) {
			if ( isset( $call['class'] ) && __CLASS__ == $call['class'] )
				continue; // Filter out wpdb calls.
			$caller[] = isset( $call['class'] ) ? "{$call['class']}->{$call['function']}" : $call['function'];
		}

		return join( ', ', $caller );
	}

	/**
	 * Whether the database supports collation.
	 *
	 * Called when WordPress is generating the table scheme.
	 *
	 * @since 2.5.0
	 *
	 * @return bool True if collation is supported, false if version does not
	 */
	function supports_collation() {
		return $this->has_cap( 'collation' );
	}

	/**
	 * Determine if a database supports a particular feature
	 *
	 * @since 2.7
	 * @see   wpdb::db_version()
	 *
	 * @param string $db_cap the feature
	 * @return bool
	 */
	function has_cap( $db_cap ) {
		$version = $this->db_version();

		switch ( strtolower( $db_cap ) ) {
		case 'collation' :    // @since 2.5.0
			case 'group_concat' : // @since 2.7
				case 'subqueries' :   // @since 2.7
					return version_compare( $version, '4.1', '>=' );
		};

		return false;
	}

	/**
	 * Whether MySQL database is at least the required minimum version.
	 *
	 * @param string $version
	 *
	 * @return bool
	 */
	function check_db_version( $version ) {
		// Make sure the server has the required MySQL version
		return version_compare($this->db_version(), $version, '>');
	}

	/**
	 * The database version number.
	 *
	 * @return false|string false on failure, version number on success
	 */
	function db_version() {
		return preg_replace( '/[^0-9.].*/', '', mysql_get_server_info( $this->dbh ) );
	}

}
