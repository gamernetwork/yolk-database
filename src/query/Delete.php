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
class Delete extends BaseQuery {

	protected $from;

	public function __construct( DatabaseConnection $db ) {
		parent::__construct($db);
		$this->from = '';
	}

	public function from( $table ) {
		$this->from = $this->quoteIdentifier($table);
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
				"DELETE FROM {$this->from}",
			],
			$this->compileWhere(),
			$this->compileOrderBy(),
			$this->compileLimit()
		);

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