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
use yolk\contracts\database\ConnectionManagerInterface;

use yolk\database\exceptions\DatabaseException;
use yolk\database\exceptions\ConfigurationException;

class ConnectionManager implements ConnectionManagerInterface {

	/**
	 * Array of database connections
	 * @var []
	 */
	protected $connections = [];

	public function add( $name, $connection ) {

		$this->checkName($name);

		if( $connection instanceof ConnectionInterface )
			$this->connections[$name] = $connection;
		else
			$this->connections[$name] = $this->create($connection);

		return $this->connections[$name];

	}

	public function remove( $name ) {
		unset($this->connections[$name]);
		return $this;
	}

	public function get( $name ) {
		return isset($this->connections[$name]) ? $this->connections[$name] : null;
	}

	public function has( $name ) {
		return isset($this->connections[$name]);
	}

	/**
	 * Create a suitable implementation of ConnectionInterface based on the specified DSN.
	 * @param  mixed $dsn
	 * @return ConnectionInterface
	 */
	protected function create( $dsn ) {

		$dsn = $this->validateDSN($dsn);

		$factories = [
			DSN::TYPE_MYSQL  => 'createMySQL',
			DSN::TYPE_PGSQL  => 'createPgSQL',
			DSN::TYPE_SQLITE => 'createSQLite',
		];

		if( !isset($factories[$dsn->type]) )
			throw new ConfigurationException('Invalid database type: '. $dsn->type);

		$factory = $factories[$dsn->type];

		return $this->$factory($dsn);

	}

	/**
	 * Ensure we have a valid DSN instance.
	 * @param  mixed $dsn a string, array or DSN instance
	 * @return DSN
	 */
	protected function validateDSN( $dsn ) {
		if( $dsn instanceof DSN )
			return $dsn;
		elseif( is_string($dsn) )
			return DSN::fromString($dsn);
		elseif( is_array($dsn) )
			return new DSN($dsn);
		else
			throw new ConfigurationException('Invalid DSN: '. $dsn);
	}

	/**
	 * Create a MySQL connection.
	 * @param  DSN    $dsn
	 * @return adapters\MySQLConnection
	 */
	protected function createMySQL( DSN $dsn ) {
		return new adapters\MySQLConnection($dsn);
	}

	/**
	 * Create a PgSQL connection.
	 * @param  DSN    $dsn
	 * @return adapters\PgSQLConnection
	 */
	protected function createPgSQL( DSN $dsn ) {
		return new adapters\PgSQLConnection($dsn);
	}

	/**
	 * Create a SQLite connection.
	 * @param  DSN    $dsn
	 * @return adapters\SQLiteConnection
	 */
	protected function createSQLite( DSN $dsn ) {
		return new adapters\SQLiteConnection($dsn);
	}

	/**
	 * Ensure we have a valid connection name, i.e. it's not empty and doesn't already exist.
	 * @param  string $name
	 * @return void
	 */
	protected function checkName( $name ) {
		if( !$name )
			throw new DatabaseException('Managed database connections must have a name');
		if( $this->has($name) )
			throw new DatabaseException('Connection already exists with name: '. $name);
	}

}

// EOF