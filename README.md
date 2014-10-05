
# Yolk Database

A simple database abstraction layer that provides a lightweight wrapper around PDO for ease-of-use.
It currently supports MySQL, Postgres and SQLite.

## Requirements

This library requires only PHP 5.4 or later.

## Installation

It is installable and autoloadable via Composer as gamer-network/yolk-database.

Alternatively, download a release or clone this repository, and add the \yolk\database namespace to an autoloader.

## License

Yolk Database is open-sourced software licensed under the MIT license

## Quick Start

```php
use yolk\database\DSN;
use yolk\database\adapters\MySQLConnection;

// create a DSN
$dsn = DSN::fromString('mysql://localhost/mydb');

// create a connection instance
$db = new MySQLConnection($dsn);

// get some data
$users = $db->getAssoc("SELECT * FROM users WHERE login = ?", 123);

// update some data
$updated = $db->execute("UPDATE users SET last_seen = NOW() WHERE id = :id". ['id' => 123]);
```

## DSNs
 
A DSN is an object that specifies the properties of a database connection.

Common properties are:
* type - the type of database to connect to (mysql, postgres or sqlite)
* host - the host to connect to
* port - the port number to connect on
* user - the user to authenticate as
* pass - the user's password
* db - the name of the database schema to connect to
* options - an array of driver specific options

DSNs can be created by passing an array of properties to the constructor:

```php
$dsn = new DSN([
	'type' => 'mysql',
	'host' => 'localhost',
	'db'   => 'myapp',
]);
```

or by calling the static `fromString()` method with a URI:

```php
$dsn = DSN::fromString('mysql://root:abc123@myapp.db/myapp?charset=utf-8');
```

## ConnectionManager

The ConnectionManager is a service to handle multiple database connections. A client can register a connection or DSN under a specific name and retrieve the connection at a later time.

When a DSN is registered, a suitable connection object is created automatically.

```php
use yolk\database\ConnectionManager;
use yolk\database\adapters\SQLiteConnection;

// create a ConnectionManager instance
$m = new ConnectionManager();

// register a DSN
$m->add('mydb1', 'mysql://localhost/mydb');

// register an existing connection
$db = new SQLiteConnection('sqlite://var/www/myapp/myapp.db');
$m->add('mydb2', $db);

// determine if a connection with the specified name exists
$exists = $m->has('mydb1');

// retrieve a previously added connection
$db = $m->get('mydb1');
```

## Connection Method Reference


query( $statement, $params = array() );

execute( $statement, $params = array() );

getAll( $statement, $params = array(), $expires = 0, $key = '' );

getAssoc( $statement, $params = array(), $expires = 0, $key = '' );

getAssocMulti( $statement, $params = array(), $expires = 0, $key = '' );

getRow( $statement, $params = array(), $expires = 0, $key = '' );

getCol( $statement, $params = array(), $expires = 0, $key = '' );

getOne( $statement, $params = array(), $expires = 0, $key = '' );
