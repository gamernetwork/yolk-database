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

use yolk\database\exceptions\QueryException;

/**
 * Generic insert query.
 */
class Replace extends Insert {

	protected $ignore;
	protected $into;
	protected $columns;
	protected $values;

	public function ignore( $ignore = true ) {
		throw new QueryException('IGNORE flag not valid for REPLACE queries.');
	}

	public function execute( $return_insert_id = false ) {
		return parent::execute(false);
	}

	protected function compile() {

		$sql = [
			'REPLACE'. ' '. $this->into,
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