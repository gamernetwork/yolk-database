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
 * Generic insert query.
 */
class Insert extends BaseQuery {

	protected $ignore;
	protected $into;
	protected $columns;
	protected $values;

	public function __construct( DatabaseConnection $db ) {
		parent::__construct($db);
		$this->ignore  = false;
		$this->into    = '';
		$this->columns = [];
		$this->values  = [];
	}

	public function ignore( $ignore = true ) {
		$this->ignore = (bool) $ignore;
		return $this;
	}

	public function into( $table ) {
		$this->into = $this->quoteIdentifier($table);
		return $this;
	}

	public function cols( array $columns ) {
		$this->columns = [];
		foreach( $columns as $column ) {
			$this->columns[] = $this->quoteIdentifier($column);
		}
	}

	public function item( array $item ) {

		if( !$this->columns )
			$this->cols(array_keys($item));

		$values = [];
		$index  = count($this->values) + 1;

		foreach( $item as $column => $value ) {
			$column = "{$column}_{$index}";
			$values[] = ":{$column}";
			$this->params[$column] = $value;
		}

		$this->values[] = $values;

		return $this;

	}

	public function execute( $return_insert_id = true ) {

		$result = $this->db->execute(
			$this->__toString(),
			$this->params
		);

		if( $return_insert_id )
			$result = $this->db->insertId();

		return $result;

	}

	protected function compile() {

		$sql = [
			($this->ignore ? 'INSERT IGNORE' : 'INSERT'). ' '. $this->into,
			'('. implode(', ', $this->columns). ')',
			'VALUES',
		];

		foreach( $this->values as $list ) {
			$sql[] = sprintf('(%s),', implode(', ', $list));
		}

		// remove comma from last values item
		$tmp = substr(array_pop($sql), 0, -1);
		array_push($sql, $tmp);

		return $sql;

	}

}

// EOF