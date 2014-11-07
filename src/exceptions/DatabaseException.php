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
 * Base database exception.
 */
class DatabaseException extends \Exception {

	/**
	 * https://bugs.php.net/bug.php?id=51742
	 * @var integer|string
	 */
	protected $code;

	public function __construct( $message = 'An unknown database error occured', $code = 0, \Exception $previous = null ) {
		parent::__construct($message, (int) $code, $previous);
		$this->code = $code;
	}

}

// EOF
