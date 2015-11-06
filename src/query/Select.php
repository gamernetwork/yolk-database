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

use yolk\contracts\database\DatabaseConnection;

/**
 * Generic.
 */
class Select extends BaseQuery {

	protected $cols;
	protected $distinct;
	protected $from;
	protected $group;
	protected $having;


	public function __construct( DatabaseConnection $db ) {
		parent::__construct($db);
		$this->cols     = [];
		$this->distinct = false;
		$this->from     = '';
		$this->group    = [];
		$this->having   = [];
	}

	public function cols( $columns = ['*'] ) {

		// default to everything
		if( !$columns )
			$columns = ['*'];

		// if we don't have an array of columns then they were specified as individual arguments
		elseif( !is_array($columns) )
			$columns = func_get_args();

		// $columns = [
		// 	'column',
		// 	['column', 'alias'],
		// 	'id',
		// 	['related_id', 'related'],
		// ];

		$this->cols = $columns;

		return $this;

	}

	// use raw columns statement
	public function colsRaw( $sql ) {
		$this->cols = $sql;
		return $this;
	}

	public function distinct( $distinct = true ) {
		$this->distinct = (bool) $distinct;
		return $this;
	}

	public function from( $table ) {
		$this->from = $this->quoteIdentifier($table);
		return $this;
	}

	public function fromRaw( $sql ) {
		$this->from = $sql;
		return $this;
	}

	public function groupBy( $columns ) {

		if( !is_array($columns) )
			$columns = [$columns];

		foreach( $columns as $column ) {
			$this->group[] = $this->quoteIdentifier($column);
		}

		return $this;

	}

	public function having( $having ) {
		$this->having = [$having];
	}

	public function __call( $method, $args ) {

		if( !in_array($method, ['getOne', 'getCol', 'getRow', 'getAssoc', 'getAll']) )
			throw new \BadMethodCallException("Unknown Method: {$method}");

		return $this->db->$method(
			$this->__toString(),
			$this->params
		);

	}

	public function compile() {

		$cols = $this->cols;

		if( is_array($cols) )
			$cols = $this->compileCols($cols);

		return array_merge(
			[
				($this->distinct ? 'SELECT DISTINCT' : 'SELECT'). ' '. $cols,
				'FROM '. $this->from,
			],
			$this->compileJoins(),
			$this->compileWhere(),
			$this->compileGroupBy(),
			$this->having,
			$this->compileOrderBy(),
			$this->compileOffsetLimit()
		);

	}

	protected function compileCols( array $cols ) {

		foreach( $cols as &$col ) {

			// if column is an array is should have two elements
			// the first being the column name, the second being the alias
			if( is_array($col) ) {
				list($column, $alias) = $col;
				$col = sprintf(
					'%s AS %s',
					$this->quoteIdentifier($column),
					$this->db->quoteIdentifier($alias)
				);
			}
			else {
				$col = $this->quoteIdentifier($col);
			}

		}

		return implode(', ', $cols);

	}

	protected function compileGroupBy() {

		$sql = [];

		if( $this->group )
			$sql[] = 'GROUP BY '. implode(', ', $this->group);

		return $sql;

	}

}

// EOF