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

use yolk\database\exceptions\ConfigurationException;

/**
 * Describes database connection details.
 *
 * @property-read string $type type of database being connected to
 * @property-read string $host hostname or ip address of database server
 * @property-read string $port network port to connect on
 * @property-read string $user user name used for authentication
 * @property-read string $pass password used for authentication
 * @property-read string $db   name of the database schema to use
 * @property-read array  $options array of database specific options
 */
class DSN {

	const TYPE_MYSQL  = 'mysql';
	const TYPE_PGSQL  = 'pgsql';
	const TYPE_SQLITE = 'sqlite';

	protected $config;

	public static function fromString( $config ) {

		// parse the string into some components
		$parts = parse_url(urldecode((string) $config));

		// no point continuing if it went wrong
		if( !$parts || empty($parts['scheme']) )
			throw new ConfigurationException('Invalid DSN string: '. $config);

		// use a closure to save loads of duplicate logic
		$select = function( $k, array $arr ) {
			return isset($arr[$k]) ? $arr[$k] : null;
		};

		// construct a well-formed array from the available components
		$config = array(
			'type'    => $select('scheme', $parts),
			'host'    => $select('host', $parts),
			'port'    => $select('port', $parts),
			'user'    => $select('user', $parts),
			'pass'    => $select('pass', $parts),
			'db'      => trim($select('path', $parts), '/'),
			'options' => [],
		);

		if( isset($parts['query']) ) {
			parse_str($parts['query'], $config['options']);
		}

		return new static($config);

	}

	public static function fromJSON( $config ) {

		$config = json_decode($config, true);

		if( !$config )
			throw new ConfigurationException('Invalid JSON configuration');

		return new static($config);

	}

	/**
	 * Create a DSN from an array of parameters.
	 * scheme - type of database (mysql, pgsql, sqlite) required
	 * host - hostname of database server
	 * port - network port to connect on
	 * user - user to connect as
	 * pass - user's password
	 * db - name of the database schema to connect to
	 * options - an array of database specific options
	 * @param array $dsn
	 */
	public function __construct( array $config ) {

		if( empty($config['type']) )
			throw new ConfigurationException('No database type specified');

		$config = $config + array(
			'host'    => 'localhost',
			'port'    => null,
			'user'    => null,
			'pass'    => null,
			'db'      => null,
			'options' => [],
		);

		$this->configure($config);

	}

	public function isMySQL() {
		return $this->config['type'] == static::TYPE_MYSQL;
	}

	public function isPgSQL() {
		return $this->config['type'] == static::TYPE_PGSQL;
	}

	public function isSQLite() {
		return $this->config['type'] == static::TYPE_SQLITE;
	}

	/**
	 * Dynamic property access.
	 * @param  string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		return isset($this->config[$key]) ? $this->config[$key] : null;
	}

	/**
	 * Dynamic property access.
	 * @param  string  $key
	 * @return boolean
	 */
	public function __isset( $key ) {
		return isset($this->config[$key]);
	}

	/**
	 * Convert the DSN into a URI-type string.
	 * @return string
	 */
	public function toString() {

		$str = $this->config['type']. '://';

		if( $this->config['user'] ) {
			$str .= $this->config['user'];
			if( $this->config['pass'] )
				$str .= ':'. $this->config['pass'];
			$str .= '@';
		}

		if( $this->config['host'] ) {
			$str .= $this->config['host'];
			if( $this->config['port'] )
				$str .= ':'. $this->config['port'];
		}

		$str .= '/'. $this->config['db'];

		if( $this->config['options'] ) {
			$str .= '?';
			foreach( $this->config['options'] as $k => $v ) {
				$str .= "$k=>$v&";
			}
			$str = substr($str, 0, -1);
		}

		return $str;

	}

	/**
	 * Return a connection string for use by PDO.
	 * @return string
	 */
	public function getConnectionString() {
		return $this->config['pdo'];
	}

	/**
	 * Ensure the dsn configuration is valid.
	 * @param  array  $config
	 * @return void
	 */
	protected function configure( array $config ) {

		if( !$config['db'] )
			throw new ConfigurationException('No database schema specified');

		switch( $config['type'] ) {

			case static::TYPE_MYSQL:
				$this->config = $this->configureMySQL($config);
				break;

			case static::TYPE_PGSQL:
				$this->config = $this->configureMySQL($config);
				break;

			case static::TYPE_SQLITE:
				$this->config = $this->configureMySQL($config);
				break;

			default:
				throw new ConfigurationException('Invalid database type: '. $config['type']);

		}

	}

	/**
	 * Configure a MySQL DSN.
	 * @param  array  $config
	 * @return array
	 */
	protected function configureMySQL( array $config ) {
		
		if( !$config['port'] )
			$config['port'] = 3306;

		// construct a MySQL PDO connection string
		$config['pdo'] = sprintf(
			"mysql:host=%s;port=%s;dbname=%s",
			$config['host'],
			$config['port'],
			$config['db']
		);

		return $config;

	}

	/**
	 * Configure a PostgreSQL DSN.
	 * @param  array  $config
	 * @return array
	 */
	protected function configurePgSQL( array $config ) {
		
		if( !$config['port'] )
			$config['port'] = 5432;

		// construct a PgSQL PDO connection string
		$config['pdo'] = sprintf(
			"pgsql:host=%s;port=%s;dbname=%s",
			$config['host'],
			$config['port'],
			$config['db']
		);

		return $config;

	}

	/**
	 * Configure a SQLite DSN.
	 * @param  array  $config
	 * @return array
	 */
	protected function configureSQLite( array $config ) {

		// these should always be null as they're invalid for SQLite connections
		$config['host'] = 'localhost';
		$config['port'] = null;
		$config['user'] = null;
		$config['pass'] = null;

		// construct a SQLite PDO connection string
		$config['pdo'] = sprintf(
			'sqlite::%s',
			$config['db']
		);

		return $config;

	}

}

// EOF