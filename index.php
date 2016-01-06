<?php

/**
 * SabreDAV CalDav and CardDav Server
 *
 * Setup for ZeyOS authentication and MySQL backend
 *
 * @author Peter-Christoph Haider <peter.haider@zeyos.com>
 * @url https://github.com/zeyosinc/sabredav
 */

// Load config and libraries
require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'auth.php';

date_default_timezone_set(DEFAULT_TIMEZONE);

// Connect to the MySQL database
$pdo = new PDO('mysql:dbname='.DB_USER.';host='.DB_HOST, DB_USER, DB_PASSWORD);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Mapping PHP errors to exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler");

// SabreDAV Backends
$carddavBackend   = new \Sabre\CardDAV\Backend\PDO($pdo);
$caldavBackend    = new \Sabre\CalDAV\Backend\PDO($pdo);
$principalBackend = new \Sabre\DAVACL\PrincipalBackend\PDO($pdo);
$dbBackend        = new \MicroDB\MySQL(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// ZeyOS Authentication Backend
$authBackend = new Sabre\DAV\Auth\Backend\ZeyOS('zeyon', $principalBackend, $dbBackend);

// Check for the ZeyOS group provisioning call (see /res/provisioning.ixml)
if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == '/provision') {
	if (defined('AUTH_TOKEN') && (!isset($_REQUEST['token']) || $_REQUEST['token'] != AUTH_TOKEN))
		die('Unauthorized');

	$authBackend->provisionZeyOSGroups(
		isset($_REQUEST['groups']) ? $_REQUEST['groups'] : [],
		isset($_REQUEST['users']) ? $_REQUEST['users'] : []
	);

	die('Provisioning complete');
}

// Directory structure
$nodes = [
    new Sabre\CalDAV\Principal\Collection($principalBackend),
    new Sabre\CalDAV\CalendarRoot($principalBackend, $caldavBackend),
	new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
];

$server = new Sabre\DAV\Server($nodes);

if (BASE_URI != '')
    $server->setBaseUri(BASE_URI);

// Plugins
$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend));
$server->addPlugin(new \Sabre\DAV\Browser\Plugin());
$server->addPlugin(new \Sabre\CalDAV\Plugin());
$server->addPlugin(new \Sabre\CardDAV\Plugin());
$server->addPlugin(new \Sabre\DAVACL\Plugin());
$server->addPlugin(new \Sabre\DAV\Sync\Plugin());
$server->addPlugin(new Sabre\CalDAV\Subscriptions\Plugin());
$server->addPlugin(new Sabre\CalDAV\Schedule\Plugin());

// And off we go!
$server->exec();