<?php
/**
 * @package    TwitterCollage
 * @subpackage server
 * @version    v.0.1
 * @author     Andre Torgal <andre@quodis.com>
 * @license    http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * File manipulation class
 */
class Image
{

	const FILE_ORIGINAL = 'o';
	const FILE_PUBLISH  = 'p';

	/**
	 * @var array
	 */
	private static $_config = null;

	/**
	 * static class, nothing to see here, move along
	 */
	private function __constructor() {}


	/**
	 * @param array $config
	 */
	public static function configure($config)
	{
		self::$_config = $config;
	}


	/**
	 * Create Cache File from url or default as fallback
	 *
	 * @param string $url
	 * @param string $id
	 * @return
	 * 		true if the file is saved, regardless if downloaded or default
	 */
	public static function download($url, $id)
	{
		// fetch the pathinfo for the given url
		$pathinfo = pathinfo($url);
		// define a sufix based on the extension key from the path info
		$sufix = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
		// define the cache file filename
		$cacheFile = self::fileName('original', md5($url), $sufix);
		// get the file from the url and save it to disk as cache file with the required permissions
		$fileData = Curl::get($url, $cacheFile, self::$_config['App']['cacheDirPermissions'], self::$_config['App']['cacheFilePermissions']);
		// TODO: configure the CURL timeout for a shorter period

		// use a default image if we're unable to fetch/save the url
		if (!$fileData)
		{
			$fileData = file_get_contents(self::$_config['App']['path'] . '/' . self::$_config['Collage']['defaultPic']);
		}

		// return true if we have fileData
		return !!$fileData;
	}


	/**
	 * Create the Tile file
	 *
	 * @param string $url
	 * @param string $id
	 *
	 * @return
	 * 		base64 encoded Tile data
	 */
	public static function makeTile($url, $id, $position)
	{
		// fetch the pathinfo from the $url
		$pathinfo = pathinfo($url);
		// fetch the suffix(file extension) from the pathinfo array
		$sufix = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
		// generate the file path for the destination/cache file
		$cacheFile = self::fileName('original', md5($url), $sufix);

		// fetch the config for this page that includes ???
		$config = & Collage::getPageConfig();

		// fetch 
		$index = $config['index'][$position];
		// debug
		Debug::logMsg($id . ' > ' . $position . ' > ' . $index['x'] . ',' . $index['y']);
		// fetch the 
		$tile  = $config['grid'][$index['y']][$index['x']];
		// ??
		$color = $tile['c'];
		// ???
		$rgbColor = str_pad(dechex($color[0]), 2, '0', STR_PAD_LEFT);
		$rgbColor.= str_pad(dechex($color[1]), 2, '0', STR_PAD_LEFT);
		$rgbColor.= str_pad(dechex($color[2]), 2, '0', STR_PAD_LEFT);

		try
		{
			// new Imagick object from cacheFile
			$image = new Imagick($cacheFile);
		}
		catch (Exception $e)
		{
			// use default twitter avatar when we can't open the cached Tile
			$default = self::$_config['App']['path'] .'/'. self::$_config['Collage']['defaultPic'];
			// new Imagick object from default twitter avatar, usually in the assets folder inside app root
			$image = new Imagick($default);
		}
		
		/* PROCESS THE ORIGINAL IMAGE */
		// desaturate the image
		$image->modulateImage(100, 0, 100);
		// resize the image to the tile size
		$image->thumbnailImage(self::$_config['Collage']['tileSize'], 0);
		
		// generate the filename
		$fileId = str_pad($index['x'], 2, '0', STR_PAD_LEFT) . str_pad($index['y'], 2, '0', STR_PAD_LEFT) . $id;
		// generate the destination
		$destination = self::fileName('processed', $fileId, 'jpg');

		// create the destination directory if it doesn't exist already
		if (!is_dir(dirname($destination)))
		{
			mkdir(dirname($destination), octdec(self::$_config['App']['cacheDirPermissions']), TRUE);
		}

		// store the processed original image
		$image->writeImage($destination);
		// set permissions on the original image
		chmod($destination, octdec(self::$_config['App']['cacheFilePermissions']));

		// TODO: the overlay should be generated once only, like the color matrix file
		// generate the overlay with the current rgb color and same size as original image
		$overlay = new Imagick();
		$overlay->newImage($image->getImageWidth(), $image->getImageHeight(), new ImagickPixel('#' . $rgbColor));
		$overlay->setImageFormat('png');
		// generate the overlay file path
		$overlayFile = self::fileName('processed', $fileId, $rgbColor . '.' .  'png');
		// save a blank file with the filename we generated above
		$overlay->writeImage($overlayFile);
		
		// discover the binary path - currently returning a new line, simple fix
		//$binary_path = system('which composite');
		$binary_path = '/usr/bin/composite';
		// build the cmd arguments
		$cmd_arguments = "$overlayFile $destination -gravity center -compose hardlight -matte";
		// reprocess the first pass image using shell_exec
		//shell_exec("$binary_path $cmd_arguments $destination");
		//Debug::logMsg("$binary_path $cmd_arguments $destination");
		// set permissions on the final image
		chmod($destination, octdec(self::$_config['App']['cacheFilePermissions']));

		// return the base64 encoded destination file
		return base64_encode(file_get_contents($destination));
		// FIXME: looks like we can optimize disk I/O
	}


	// --- file path/url

	/**
	 * Return file path string
	 *
	 * @param string $prefix
	 * @param string $id
	 * @param string sufix
	 *
	 * @return
	 *		file path string
	 */
	public static function fileName($dir, $id, $sufix)
	{
		// make filename
		//$fileName = substr($id, 0, 2) . '/' . substr($id, 2, 2) . '/' . substr($id, 4) . '.' . $sufix;
		$fileName = $id . '.' . $sufix;

		return self::$_config['App']['pathCache'] . '/' . $dir . '/' . $fileName;
	}
}

?>