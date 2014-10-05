<?php
/*
 * This file is part of Yolk - Gamer Network's PHP Framework.
 *
 * Copyright (c) 2014 Gamer Network Ltd.
 * 
 * Distributed under the MIT License, a copy of which is available in the
 * LICENSE file that was bundled with this package, or online at:
 * https://github.com/gamernetwork/yolk-database
 */

namespace yolk\database;

interface ConnectionInterface {

	/**
	 * Open the database connection.
	 */
	public function connect();

	/**
	 * Close the database connection.
	 * @return boolean
	 */
	public function disconnect();

	/**
	 * Determines if the database connection is currently open.
	 * @return boolean
	 */
	public function isConnected();

	/**
	 * Perform a query against the database.
	 * Prepared statements are cached so each query will only be prepared once per the object's lifetime.
	 * Supports positional (?) and named (:name) parameter formats, see the PDO docs for more info.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  array $params   an array of parameters to pass into the query.
	 * @return \PDOStatement
	 */
	public function query( $statement, $params = array() );

	/**
	 * Perform a non-select query and return the number of affected rows.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  integer|array $params   an array of parameters to pass into the query.
	 * @return integer
	 */
	public function execute( $statement, $params = array() );

	/**
	 * Perform a select query and return all matching rows.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  array   $params    an array of parameters to pass into the query.
	 * @param  integer $expires   number of seconds to cache the result for if caching is enabled
	 * @param  string  $key       cache key used to store the results
	 * @return array
	 */
	public function getAll( $statement, $params = array(), $expires = 0, $key = '' );

	/**
	 * Perform a select query and return all matching rows.
	 * The first column in the resultset is used as the key for each record.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  array   $params    an array of parameters to pass into the query.
	 * @param  integer $expires   number of seconds to cache the result for if caching is enabled
	 * @param  string  $key       cache key used to store the results
	 * @return array
	 */
	public function getAssoc( $statement, $params = array(), $expires = 0, $key = '' );

	/**
	 * Perform a select query and return all matching rows as a multi-dimensional associative array.
	 * The first column in the resultset is used as the key for each record, with each record being an array of rows
	 * for that key.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  array   $params    an array of parameters to pass into the query.
	 * @param  integer $expires   number of seconds to cache the result for if caching is enabled
	 * @param  string  $key       cache key used to store the results
	 * @return array
	 */
	public function getAssocMulti( $statement, $params = array(), $expires = 0, $key = '' );

	/**
	 * Perform a select query and return the first matching row.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  array   $params    an array of parameters to pass into the query.
	 * @param  integer $expires   number of seconds to cache the result for if caching is enabled
	 * @param  string  $key       cache key used to store the results
	 * @return array
	 */
	public function getRow( $statement, $params = array(), $expires = 0, $key = '' );

	/**
	 * Perform a select query and return all the values of the first column in an array.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  scalar|array $params   an array of parameters to pass into the query.
	 * @param  integer $expires   number of seconds to cache the result for if caching is enabled
	 * @return array
	 */
	public function getCol( $statement, $params = array(), $expires = 0, $key = '' );

	/**
	 * Perform a select query and return the value of the first column of the first row.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  array   $params    an array of parameters to pass into the query.
	 * @param  integer $expires   number of seconds to cache the result for if caching is enabled
	 * @param  string  $key       cache key used to store the results
	 * @return mixed
	 */
	public function getOne( $statement, $params = array(), $expires = 0, $key = '' );

	/**
	 * Begins a database transaction.
	 * @return boolean
	 */
	public function begin();

	/**
	 * Commits the current transaction.
	 * @return boolean
	 */
	public function commit();

	/**
	 * Discards the current transaction.
	 * @return boolean
	 */
	public function rollback();

	/**
	 * Determines if there is an active transaction.
	 * @return boolean
	 */
	public function inTransaction();

	/**
	 * Returns the ID of the last inserted row or sequence value.
	 * If a sequence name was not specified for the name parameter, returns a string representing the row ID of the last row that was inserted into the database.
	 * If a sequence name was specified for the name parameter, returns a string representing the last value retrieved from the specified sequence object. 
	 * @return string
	 */
	public function insertId( $name = '' );

	/**
	 * Escape a value so it can be embedded in a query.
	 * This is only required where values are being directly inbedded in the SQL string,
	 * values passed as parameters to a query will be escaped automatically.
	 *
	 * @param   mixed     $value   the value to escape.
	 * @param   integer   $type    one of the PDO::PARAM_* constants indicating the datatype of $value.
	 * @return  string
	 */
	public function escape( $value, $type = \PDO::PARAM_STR );

}

// EOF