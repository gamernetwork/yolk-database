<?php
/*
 * This file is part of Yolk - Gamer Network's PHP Framework.
 *
 * Copyright (c) 2013 Gamer Network Ltd.
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
class Update extends BaseQuery {

	protected $table;
	protected $set;

	public function __construct( DatabaseConnection $db ) {
		parent::__construct($db);
		$this->table = '';
		$this->set   = [];
	}

	public function table( $table ) {
		$this->table = $this->quoteIdentifier($table);
		return $this;
	}

	public function set( array $data, $replace = false ) {

		if( $replace )
			$this->set = [];

		$this->set = array_merge($this->set, $data);

		return $this;

	}

	public function execute() {
		return $this->db->execute(
			$this->__toString(),
			$this->params
		);
	}

	protected function compile() {

		return array_merge(
			[
				"UPDATE {$this->table}",
			],
			$this->compileSet(),
			$this->compileWhere(),
			$this->compileOrderBy(),
			$this->compileLimit()
		);

	}

	protected function compileSet() {

		$sql = [];
		$end = -1;

		foreach( $this->set as $column => $value ) {
			$this->bindParam($column, $value);
			$sql[] = "{$column} = :{$column},";
			$end++;
		}

		$sql[0]    = 'SET '. $sql[0];
		$sql[$end] = trim($sql[$end], ',');

		return $sql;

	}

	protected function compileLimit() {

		$sql = [];

		if( $this->limit ) {
			$sql[] = "LIMIT :limit";
			$this->params['limit']  = $this->limit;
		}

		return $sql;

	}

}

// EOF