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

namespace yolk\database\adapters;

use yolk\database\AbstractConnection;
use yolk\database\DSN;
use yolk\database\exceptions\ConfigurationException;

class MySQLConnection extends AbstractConnection {

	public function __construct( DSN $dsn ) {

		if( !$dsn->isSQLite() )
			throw new ConfigurationException(sprintf("\\%s expects a DSN of type '%s', '%s' given", __CLASS__, DSN::TYPE_SQLITE, $dsn->type));

		parent::__construct($dsn);

	}

}

// EOF