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

	public function item( array $item ) {

		$values = [];
		foreach( $item as $column => $value ) {
			$this->columns[] = $this->quoteIdentifier($column);
			$values[] = ":{$column}";
		}
		$this->values[] = $values;

		$this->params = $item;

		return $this;

	}

	public function multipleItems( array $items ) {

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
			$sql[] = sprintf('(%s)', implode(', ', $list));
		}

		return $sql;

	}

}

// EOF