<?php
/**
 * @package    Firefox 4 Twitter Party
 * @subpackage server
 * @version    v.0.4
 * @author     Andre Torgal <andre@quodis.com>
 * @license    http://www.opensource.org/licenses/bsd-license.php BSD License
 */


DEFINE('APP_PATH', dirname(__FILE__));
DEFINE('LIB_PATH', dirname(__FILE__) . '/lib');

DEFINE('NL', "\n");

DEFINE('UPLOAD_FILE_FIELD', 'file');

DEFINE ('AJAX',   'ajax');
DEFINE ('HJSON',  'hjson');
DEFINE ('IMAGE',  'image');
DEFINE ('HTML',   'html');
DEFINE ('SCRIPT', 'script');

require LIB_PATH . '/tiny.lib.php';
require LIB_PATH . '/l10n.lib.php';
require LIB_PATH . '/twitterparty.model.php';
require LIB_PATH . '/Curl.class.php';
require LIB_PATH . '/Image.class.php';
require LIB_PATH . '/Mosaic.class.php';
require LIB_PATH . '/Twitter.class.php';

Debug::setCtx(basename(CONTEXT));
Debug::setLogMsgFile('/var/log/twitterparty/msg.log');
Debug::setLogErrorFile('/var/log/twitterparty/error.log');

session_start();

// DEBUG
set_error_handler(array('Debug', 'handleError'));


/**
 * boot db
 * @param array $config (by reference)
 */
function initDb(array & $config)
{
	$con = $config['Db']['connection'];
	global $mysqli;

	/* FIX mysqli subclass class defined in model file */
	$mysqli = mysqli_init();
	$mysqli->options(MYSQLI_INIT_COMMAND, "SET AUTOCOMMIT=1");
	$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
	$connected = $mysqli->real_connect($con['host'], $con['user'], $con['pass'], $con['name']);
	if ($connected)
	{
		$mysqli->set_charset('utf8');
	}
	else throw new Exception('Fail connecting to db');
}

// VALIDATE TOKENS
if (defined('VALIDATETOKEN')) {
	// generate a session token
	if (!isset($_SESSION['token']) || $_REQUEST['token'] !== $_SESSION['token']) {
		Dispatch::now(9, '');
	}
}


// CONFIG
if (!defined('NO_CONFIG'))
{
	global $config;
	// load
	include dirname(__FILE__) . '/config/config.php';

	// configure
	if (isset($config['App']['logEnabled'])) Debug::logEnabled($config['App']['logEnabled']);

	// configure facebook, cache and game
	Cache::configure($config);
	Image::configure($config);
	Mosaic::configure($config);
	Twitter::configure($config);

	// connect cache + db
	Cache::connect();
	if (!defined('NO_DB')) initDb($config);

	// GENERATE TOKENS
	if (defined('GENERATETOKEN')) {
		$_SESSION['token'] = md5(serialize($config) . time());
	}
}

?>