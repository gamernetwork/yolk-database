<?php
/*
 * This file is part of Yolk - Gamer Network's PHP Framework.
 *
 * Copyright (c) 2013 Gamer Network Ltd.
 * 
 * Distributed under the MIT License, a copy of which is available in the
 * LICENSE file that was bundled with this package, or online at:
 * https://github.com/gamernetwork/yolk
 */

namespace yolk\database\exceptions;

/**
 * Thrown if a database connection could not be established.
 */
class ConnectionException extends DatabaseException {

	public function __construct( $message = 'An error occured attempting to connect to the database', $code = 0, \Exception $previous = null ) {
		parent::__construct($message, $code, $previous);
	}

}

// EOF