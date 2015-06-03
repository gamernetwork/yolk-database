<?php
/*
 * This file is part of Yolk - Gamer Network's PHP Framework.
 *
 * Copyright (c) 2015 Gamer Network Ltd.
 *
 * Distributed under the MIT License, a copy of which is available in the
 * LICENSE file that was bundled with this package, or online at:
 * https://github.com/gamernetwork/yolk-database
 */

namespace yolk\database\query;

use yolk\Yolk;

use yolk\contracts\database\DatabaseConnection;

/**
 * Generic.
 */
abstract class BaseQuery {

	/**
	 * Database connection the query is associated with.
	 * @var DatabaseConnection
	 */
	protected $db;

	/**
	 * Array of where clauses.
	 * @var array
	 */
	protected $where;

	/**
	 * Array of order by clauses.
	 * @var array
	 */
	protected $order;

	/**
	 * Query offset.
	 * @var integer
	 */
	protected $offset;

	/**
	 * Query limit.
	 * @var integer|null
	 */
	protected $limit;

	/**
	 * Array of query parameters
	 * @var array
	 */
	protected $params;

	public function __construct( DatabaseConnection $db ) {
		$this->db       = $db;
		$this->where    = [];
		$this->order    = [];
		$this->offset   = 0;
		$this->limit    = null;
		$this->params   = [];
	}

	public function __toString() {
		return d(implode("\n", $this->compile()), $this->params);
	}

	public function where( $column, $operator, $value = null ) {

		// shortcut for equals
		if( func_num_args() == 2 ) {
			$value    = $operator;
			$operator = '=';
		}

		$operator = trim(strtoupper($operator));

		// can't bind IN values as parameters so we escape them and embed them directly
		if( in_array($operator, ['IN', 'NOT IN']) && is_array($value) ) {
			$value = $this->makeInClause($value);
		}
		// do parameter binding
		else {
			$value = $this->bindParam(
				$this->getParameterName($column, $operator),
				$value
			);
		}

		$this->where[] = [$this->quoteIdentifier($column), $operator, $value];

		return $this;

	}

	public function whereRaw( $sql, $parameters = [] ) {
		$this->where[] = $sql;
		$this->params = array_merge($this->params, $parameters);
		return $this;
	}

	public function orderBy( $column, $ascending = true ) {
		$column = $this->quoteIdentifier($column);
		$this->order[$column] = (bool) $ascending ? 'ASC' : 'DESC';
		return $this;
	}

	public function offset( $offset ) {
		$this->offset = max(0, (int) $offset);
		return $this;
	}

	public function limit( $limit ) {
		$this->limit = max(1, (int) $limit);
		return $this;
	}

	/**
	 * Generate a SQL string as an array.
	 * @return array
	 */
	abstract protected function compile();

	protected function compileWhere() {

		$sql = [];

		foreach( $this->where as $i => $clause ) {
			if( is_array($clause) )
				$clause = implode(' ', $clause);
			$sql[] = ($i ? 'AND ' : 'WHERE '). $clause;
		}

		return $sql;

	}

	protected function compileOrderBy() {

		$sql = [];

		if( $this->order ) {
			$order = 'ORDER BY ';
			foreach( $this->order as $column => $dir ) {
				$order .= $column. ' '. $dir. ', ';
			}
			$sql[] = trim($order, ', ');
		}

		return $sql;

	}

	protected function compileOffsetLimit() {

		$sql = [];

		$limit  = $this->limit;
		$offset = $this->offset;

		if( $limit || $offset ) {

			if( !$limit )
				$limit = PHP_INT_MAX;

			$sql[] = sprintf(
				"LIMIT %s OFFSET %s",
				$this->bindParam('_limit', $limit),
				$this->bindParam('_offset', $offset)
			);

		}

		return $sql;

	}

	protected function quoteIdentifier( $spec ) {

		// don't quote things that are functions/expressions
		if( strpos($spec, '(') !== false )
			return $spec;

		foreach( [' AS ', ' ', '.'] as $sep) {
			if( $pos = strripos($spec, $sep) ) {
				return
					$this->quoteIdentifier(substr($spec, 0, $pos)).
					$sep.
					$this->db->quoteIdentifier(substr($spec, $pos + strlen($sep)));
			}
		}

		return $this->db->quoteIdentifier($spec);

	}

	/**
	 * Join an array of values to form a string suitable for use in a SQL IN clause.
	 * The numeric parameter determines whether values are escaped and quoted;
	 * a null value (the default) will cause the function to auto-detect whether
	 * values should be escaped and quoted.
	 * 
	 * @param  array          $values
	 * @param  null|boolean   $numeric
	 * @return string
	 */
	protected function makeInClause( array $values, $numeric = null ) {

		// if numeric flag wasn't specified then detected it
		// by checking all items in the array are numeric
		if( $numeric === null ) {
			$numeric = count(array_filter($values, 'is_numeric')) == count($values);
		}

		// not numeric so we need to escape all the values
		if( !$numeric ) {
			$values = array_map([$this->db, 'escape'], $values);
		}
			
		return sprintf('(%s)', implode(', ', $values));

	}

	protected function getParameterName( $column, $operator ) {

		$suffixes = [
			'='    => 'eq',
			'!='   => 'neq',
			'<>'   => 'neq',
			'<'    => 'max',
			'<='   => 'max',
			'>'    => 'min',
			'>='   => 'min',
			'LIKE' => 'like',
			'NOT LIKE' => 'notlike',
		];

		$name = $column;

		// strip the table identifier
		if( $pos = strpos($name, '.') )
			$name = substr($name, $pos + 1);

		if( isset($suffixes[$operator]) )
			$name .= '_'. $suffixes[$operator];

		return $name;

	}

	/**
	 * Add a parameter and return the placeholder to be inserted into the query string.
	 * @param  string $name
	 * @param  mixed  $value
	 * @return string
	 */
	protected function bindParam( $name, $value ) {

		if( isset($this->params[$name]) )
			throw new \LogicException("Parameter: {$name} has already been defined");

		$this->params[$name] = $value;

		return ":{$name}";

	}

}

// EOF