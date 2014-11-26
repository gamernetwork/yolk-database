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

use yolk\contracts\database\ConnectionInterface;
use yolk\contracts\profiler\ProfilerAwareTrait;
use yolk\contracts\profiler\ProfilerAwareInterface;

use yolk\database\exceptions\DatabaseException;
use yolk\database\exceptions\ConnectionException;
use yolk\database\exceptions\NotConnectedException;
use yolk\database\exceptions\QueryException;
use yolk\database\exceptions\TransactionException;

/**
 * A wrapper for PDO that provides some handy extra functions and streamlines everything else.
 */
abstract class AbstractConnection implements ConnectionInterface, ProfilerAwareInterface {

	use ProfilerAwareTrait;

	/**
	 * Connection details.
	 * @var DSN
	 */
	protected $dsn = null;

	/**
	 * Underlying PDO object.
	 * @var \PDO
	 */
	protected $pdo = null;

	/**
	 * Prepared statement cache.
	 * @var array
	 */
	protected $statements = [];

	/**
	 * Create a new database connection.
	 *
	 * @param DSN $dsn a DSN instance describing the database connection details
	 */
	public function __construct( DSN $dsn ) {

		$this->dsn = $dsn;

		// check for PDO extension
		if( !extension_loaded('pdo') )
			throw new DatabaseException('The PDO extension is required but the extension is not loaded');

		// check the PDO driver is available
		elseif( !in_array($this->dsn->type, \PDO::getAvailableDrivers()) )
			throw new DatabaseException("The {$this->dsn->type} PDO driver is not currently installed");

	}

	public function connect() {

		if( $this->pdo instanceof \PDO )
			return true;

		try {

			$this->pdo = new \PDO(
				$this->dsn->getConnectionString(),
				$this->dsn->user,
				$this->dsn->pass,
				$this->dsn->options
			);

			$this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);           // always use exceptions

			$this->setCharacterSet(
				$this->getOption('charset', 'UTF8'),
				$this->getOption('collation')
			);

		}
		catch( \PDOException $e ) {
			throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
		}

		return true;

	}

	public function disconnect() {
		$this->pdo = null;
		return true;
	}

	public function isConnected() {
		return $this->pdo instanceof \PDO;
	}

	public function query( $statement, $params = [] ) {

		$this->connect();

		// TODO: profiler start
		$this->profiler && $this->profiler->start('Query');

		try {

			$statement = $this->prepare($statement);

			// single parameters don't have to be passed in an array - do that here
			if( !is_array($params) )
				$params = array($params);

			$this->bindParams($statement, $params);

			$start = microtime(true);
			$statement->execute();
			$duration = microtime(true) - $start;
			
		}
		catch( \PDOException $e ) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}

		// TODO: profiler stop + record
		if( $this->profiler ) {
			$this->profiler->stop('Query');
			// remove all whitespace at start of lines
			$this->profiler->query(preg_replace("/^\s*/m", "", trim($statement->queryString)), $params, $duration);
		}

		return $statement;

	}

	public function execute( $statement, $params = [] ) {
		$statement = $this->query($statement, $params);
		return $statement->rowCount();
	}

	public function getAll( $statement, $params = [], $expires = 0, $key = '' ) {
		return $this->getResult(
			$statement,
			$params,
			$expires,
			$key,
			function( \PDOStatement $statement ) {
				$result = $statement->fetchAll();
				if( $result === false )
					$result = [];
				return $result;
			}
		);
	}

	public function getAssoc( $statement, $params = [], $expires = 0, $key = '' ) {
		return $this->getResult(
			$statement,
			$params,
			$expires,
			$key,
			function( \PDOStatement $statement ) {
				$result = [];
				while( $row = $statement->fetch() ) {
					$key = array_shift($row);
					$result[$key] = count($row) == 1 ? array_shift($row) : $row;
				}
				return $result;
			}
		);
	}

	public function getAssocMulti( $statement, $params = [], $expires = 0, $key = '' ) {
		return $this->getResult(
			$statement,
			$params,
			$expires,
			$key,
			function( \PDOStatement $statement ) {
				$result = [];
				while( $row = $statement->fetch() ) {
					$k1 = array_shift($row);
					$k2 = array_shift($row);
					$v  = count($row) == 1 ? array_shift($row) : $row;
					if( !isset($result[$k1]) )
						$result[$k1] = [];
					$result[$k1][$k2] = $v;
				}
				return $result;
			}
		);
	}

	public function getRow( $statement, $params = [], $expires = 0, $key = '' ) {
		return $this->getResult(
			$statement,
			$params,
			$expires,
			$key,
			function( \PDOStatement $statement ) {
				$result = $statement->fetch();
				if( $result === false )
					$result = [];
				return $result;
			}
		);
	}

	public function getCol( $statement, $params = [], $expires = 0, $key = '' ) {
		return $this->getResult(
			$statement,
			$params,
			$expires,
			$key,
			function( \PDOStatement $statement ) {
				$result = [];
				while( $row = $statement->fetch() ) {
					$result[] = array_shift($row);
				}
				return $result;
			}
		);
	}

	public function getOne( $statement, $params = [], $expires = 0, $key = '' ) {
		return $this->getResult(
			$statement,
			$params,
			$expires,
			$key,
			function( \PDOStatement $statement ) {
				$result = $statement->fetchColumn();
				if( $result === false )
					$result = null;
				return $result;
			}
		);
	}

	public function begin() {

		$this->connect();

		try {
			return $this->pdo->beginTransaction();
		}
		catch( \PDOException $e ) {
			throw new TransactionException($e->getMessage(), $e->getCode(), $e);
		}

	}

	public function commit() {

		if( !$this->isConnected() )
			throw new NotConnectedException();

		try {
			return $this->pdo->commit();
		}
		catch( \PDOException $e ) {
			throw new TransactionException($e->getMessage(), $e->getCode(), $e);
		}

	}

	public function rollback() {

		if( !$this->isConnected() )
			throw new NotConnectedException();

		try {
			return $this->pdo->rollBack();
		}
		catch( \PDOException $e ) {
			throw new TransactionException($e->getMessage(), $e->getCode(), $e);
		}

	}

	public function inTransaction() {
		return $this->isConnected() ? $this->pdo->inTransaction() : false;
	}

	public function insertId( $name = '' ) {
		if( !$this->isConnected() )
			throw new NotConnectedException();
		return $this->pdo->lastInsertId($name);
	}

	public function escape( $value, $type = \PDO::PARAM_STR ) {
		$this->connect();
		return $this->pdo->quote($value, $type);
	}

	/**
	 * Create a prepared statement.
	 * @param  \PDOStatement|string  $statement  an existing PDOStatement object or a SQL string.
	 * @return \PDOStatement
	 */
	protected function prepare( $statement ) {

		if( ! $statement instanceof \PDOStatement  ) {
			$statement = trim($statement);
			if( !isset($this->statements[$statement]) )
				$this->statements[$statement] = $this->pdo->prepare($statement);
			$statement = $this->statements[$statement];
		}

		return $statement;

	}

	/**
	 * Bind named and positional parameters to a PDOStatement.
	 * @param  PDOStatement $statement
	 * @param  array        $params
	 * @return void
	 */
	protected function bindParams( \PDOStatement $statement, array $params ) {
		foreach( $params as $name => $value ) {

			if( is_int($value) ) {
				$type = \PDO::PARAM_INT;
			}
			else {
				$type = \PDO::PARAM_STR;
			}

			// handle positional (?) and named (:name) parameters
			$name = is_numeric($name) ? (int) $name + 1 : ":{$name}";

			$statement->bindValue($name, $value, $type);

		}
	}

	/**
	 * Perform a select query and use a callback to extract a result.
	 * @param  \PDOStatement|string $statement   an existing PDOStatement object or a SQL string.
	 * @param  array $params        an array of parameters to pass into the query.
	 * @param  integer $expires     number of seconds to cache the result for if caching is enabled
	 * @param  string  $key         cache key used to store the result
	 * @param  \Closure $callback   function to yield a result from the executed statement
	 * @return array
	 */
	protected function getResult( $statement, $params, $expires, $key, \Closure $callback ) {

		// TODO: check cache

		$statement = $this->query($statement, $params);

		$result = $callback($statement);

		// TODO: store in cache

		return $result;

	}

	protected function getOption( $option, $default = null ) {
		return isset($this->dsn->options[$option]) ? $this->dsn->options[$option] : $default;
	}

	/**
	 * Make sure the connection is using the correct character set
	 * 
	 * @param string $charset   the character set to use for the connection
	 * @param string $collation the collation method to use for the connection
	 * @return self
	 */
	protected function setCharacterSet( $charset, $collation = '' ) {

		if( !$charset ) 
			throw new DatabaseException('No character set specified');

		$sql = 'SET NAMES '. $this->pdo->quote($charset);

		if( $collation )
			$sql .= ' COLLATE '. $this->pdo->quote($collation);

		$this->pdo->exec($sql);

		return $this;

	}

}

// EOF