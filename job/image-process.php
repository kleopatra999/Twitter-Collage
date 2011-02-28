<?php
/**
 * @package    Firefox 4 Twitter Party
 * @subpackage server
 * @version    v.0.4
 * @author     Andre Torgal <andre@quodis.com>
 * @license    http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * escape from global scope
 */
function main()
{
	DEFINE('CLIENT', 'script');
	DEFINE('CONTEXT', __FILE__);
	include dirname(__FILE__) . '/../bootstrap.php';

	Debug::setLogMsgFile($config['App']['pathLog'] .'/image-process.msg.log');
	Debug::setLogErrorFile($config['App']['pathLog'] .'/image-process.error.log');
	Debug::setForceLogToFile(TRUE);

	$period   = $config['Jobs']['image-process']['period'];
	$dbLimit  = $config['Jobs']['image-process']['dbLimit'];
	$imgLimit = $config['Jobs']['image-process']['imgLimit'];

	$processed = 0;

	// NOTE: first loop sleep, avoids sending too many requests to twitter if loop is crashing
	// - if process crashes it is restarted within 1 sec by superivise, and it is likely to crash again, and again...
	$sleep = ceil($period / 2);

	while (TRUE && $processed < $imgLimit)
	{
		// NOTE: sleep at the top of the loop prevents (see above)
		if ($sleep) sleep($sleep);

		// start time
		$start = time();

		// fetch tweets
		$tweetsWithoutImage = Tweet::getUnprocessed($dbLimit);

		while ($tweet = $tweetsWithoutImage->row())
		{
			$processed++;

			$start = microtime(TRUE);
			$time = array();

			// download
			if ($fileName = Image::download($tweet['imageUrl'], $tweet['id']))
			{
				$time['download'] = microtime(TRUE);

				try
				{
					// make image with
					$encoded = Image::makeTile($fileName, $tweet['id'], $tweet['position']);
				}
				catch(Exception $e)
				{
					Debug::logError('Fail Image::makeTile(). Defaulting to egg. Details follow.... id:' . $tweet['id'] . ' page:' . $tweet['page'] . ' position: ' . $tweet['position'] . ' from url:' . $tweet['imageUrl'] . ' into:' . Image::fileName('processed', md5($tweet['id']), 'gif') . ' with error: ' . $e->getMessage());

					// make default
					$defaultPic = $config['App']['path'] . '/' . $config['Mosaic']['defaultPic'];
					$encoded = Image::makeTile($defaultPic, $tweet['id'], $tweet['position']);
				}

				$time['make-tile'] = microtime(TRUE);

				// update db with image data
				Tweet::updateImage($tweet['id'], $encoded);

				$time['update-db'] = microtime(TRUE);

				Debug::logMsg('updated tweet id: ' . $tweet['id'] . ' page:' . $tweet['page'] . ' position:' . $tweet['position'] . ' [' . strlen($encoded) . ' bytes] '. Image::fileName('processed', md5($tweet['id']), 'gif'));
			}
			else Debug::logError('fail download tweet id:' . $tweet['id'] . ' page:' . $tweet['page'] . ' position: ' . $tweet['position'] . ' from url:' . $tweet['imageUrl']);

			$log = array();
			$previous = $start;
			$value = $start;
			foreach ($time as $key => $value)
			{
				$log[] = $key . ': ' . ceil(($value - $previous) * 1000) / 1000;
				$previous = $value;
			}
			dd('TIME! id:' . $tweet['id'] . ' > total:' . (ceil(($value - $start) * 1000) / 1000) . ', ' .implode(', ', $log));
		}

		// sleep?
		$elapsed = time() - $start;
		$sleep = $period - $elapsed;
		if ($sleep < 1) $sleep = 1;

		Debug::logMsg('OK! ... images processed: ' . $processed . '/' . $imgLimit . ' ... sleeping for ' . $sleep . ' seconds ...');
	}

	Debug::logMsg('...this honoured worker is now going to hara-kiri...');

	exit();

} // main()


try
{
	main();
}
catch(Exception $e) {
	Debug::logError($e, 'EXCEPTION ' . $e->getMessage());
	Dispatch::now(0, 'EXCEPTION ' . $e->getMessage());
}

?>