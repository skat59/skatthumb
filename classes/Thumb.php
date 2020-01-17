<?php
namespace SkatThumb;

class Thumb {
	
	public static function addSnippet(\DocumentParser $modx)
	{
		$modx->addSnippet('thumb', '\SkatThumb\Thumb::thumb');
	}
	
	public static function thumb($params = array('input', 'options'))
	{
		global $modx;
		
		if (is_array($params)) {
            extract($params, EXTR_SKIP);
        }else{
			return "not input options";
		}
		
		
		$newFolderAccessMode = $modx->getConfig('new_folder_permissions');
		$newFolderAccessMode = empty($new) ? 0777 : octdec($newFolderAccessMode);
		$cacheFolder = isset($cacheFolder) ? $cacheFolder : rtrim($modx->getCacheFolder(), "/\\") . "/images";
		
		$path = MODX_BASE_PATH . $cacheFolder;
		if (!file_exists($path) && mkdir($path) && is_dir($path)) {
			chmod(MODX_BASE_PATH . $cacheFolder, $newFolderAccessMode);
		}
		
		$tmpFolder = $modx->getCacheFolder() . '/tmp';
		if (!empty($input)) {
			$input = rawurldecode($input);
		}
		
		if (empty($input) || !file_exists(MODX_BASE_PATH . $input)) {
			$input = isset($noImage) ? $noImage : 'assets/snippets/phpthumb/noimage.png';
		}
		
		if (!file_exists(MODX_BASE_PATH . $cacheFolder . '/.htaccess') &&
			$cacheFolder !== $modx->getCacheFolder() &&
			strpos($cacheFolder, $modx->getCacheFolder()) === 0
		)
		{
			file_put_contents(MODX_BASE_PATH . $cacheFolder . '/.htaccess', "order deny,allow\nallow from all\n");
		}
		
		$path = MODX_BASE_PATH . $tmpFolder;
		if (!file_exists($path) && mkdir($path) && is_dir($path)) {
			chmod($path, $newFolderAccessMode);
		}
		
		$path_parts = pathinfo($input);
		$tmpImagesFolder = $path_parts['dirname'];
		$tmpImagesFolder = str_replace('assets/images/', '', $tmpImagesFolder);
		$tmpImagesFolder = explode('/', $tmpImagesFolder);
		$ext = strtolower($path_parts['extension']);
		$options = 'f=' . (in_array($ext, array('png', 'gif', 'jpeg', 'jpg')) ? $ext : 'jpg&q=85') . '&' .
			strtr($options, array(',' => '&', '_' => '=', '{' => '[', '}' => ']'));
		
		
		parse_str($options, $params);
		
		foreach ($tmpImagesFolder as $folder) {
			if (!empty($folder)) {
				$cacheFolder .= '/' . $folder;
				$path = MODX_BASE_PATH . $cacheFolder;
				if (!file_exists($path) && mkdir($path) && is_dir($path)) {
					chmod($path, $newFolderAccessMode);
				}
			}
		}
		
		$fNamePref = rtrim($cacheFolder, '/') . '/';
		$fName = $path_parts['filename'];
		$fNameSuf = '-' .
			$params['w'] .'x' . $params['h'] . '-' .
			substr(md5(serialize($params) . filemtime(MODX_BASE_PATH . $input)), 0, 3) .
			'.' . $params['f'];
		
		$outputFilename = MODX_BASE_PATH . $fNamePref . $fName . $fNameSuf;
		
		if (!file_exists($outputFilename)) {
			require_once MODX_BASE_PATH . 'assets/snippets/phpthumb/phpthumb.class.php';
			$phpThumb = new \phpthumb();
			$phpThumb->config_temp_directory = $tmpFolder;
			$phpThumb->config_document_root = MODX_BASE_PATH;
			$phpThumb->setSourceFilename(MODX_BASE_PATH . $input);
			foreach ($params as $key => $value) {
				$phpThumb->setParameter($key, $value);
			}
			if ($phpThumb->GenerateThumbnail()) {
				$phpThumb->RenderToFile($outputFilename);
				$modx->invokeEvent('OnGenerateThumbnail', array('thumbnail' => $outputFilename));
			} else {
				$modx->logEvent(0, 3, implode('<br/>', $phpThumb->debugmessages), 'phpthumb');
			}
		}
		return $fNamePref . rawurlencode($fName) . $fNameSuf;
	}
	
	public static function optimized($file)
	{
		global $modx;
		
		if(is_file($file) && is_writable($file))
		{
			$validate = array('jpg', 'jpeg', 'png', 'gif');
			$arr = array_map('strtolower', explode(',', $modx->config['upload_images']));
			$path_parts = pathinfo($file);
			$ext = strtolower($path_parts['extension']);
			if (in_array($ext, $arr) && in_array($ext, $validate)){
				try {
					$factory = new \ImageOptimizer\OptimizerFactory();
					$optimizer = $factory->get();
					$optimizer->optimize($file);
					$h = @fopen(MODX_BASE_PATH . "00000-thumb.txt", 'a+');
					@fwrite($h, $file ."\n\n");
					@fclose($h);
				} catch(\Exception $e){
					$modx->logEvent(0, 3, implode('<br />', $e->getMessage()), 'Asset class');
				}
			}
		}
	}
	
	public static function plugin(\DocumentParser $modx)
	{
		$e = &$modx->event;
		$params = &$modx->event->params;
		
		switch($e->name){
			case 'OnFileBrowserUpload':
			case 'OnFileManagerUpload':
				$params['filepath'] = str_replace("\\", "/", $params['filepath']);
				$params['filename'] = str_replace("\\", "/", $params['filename']);
				$params['filename'] = str_replace($params['filepath'] . '/', "", $params['filename']);
				$path = $params['filepath'] . '/' . $params['filename'];
				self::optimized($path);
				break;
			case "OnGenerateThumbnail":
				$path = $params['thumbnail'];
				self::optimized($path);
				break;
		}
	}
	
}