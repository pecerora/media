<?php
/**
 * MimeGlob file
 *
 * Parser for glob files and filename analyzer
 *  
 */
if (!class_exists('File')) {
	uses('file');
}
/**
 * MimeGlob class
 *
 */
class MimeGlob extends Object {
/**
 * Items indexed by priority
 *
 * @var array
 * @access protected
 */
	var $_items = array();
/**
 * Constructor
 * 
 * @param mixed $file
 * @access private
 */
	function __construct($db) {
		$this->__read($db);
	}
/**
 * Enter description here...
 *
 * @param unknown_type $db
 * @static 
 */
	function format($db) {
		if (is_array($db)) {
			return 'Array';
		}
		
		$File = new File($db);
		
		if ($File->exists()) {
			if ($File->ext() === 'php') {
				return 'PHP';
			} 
			
			$File->open('rb');
			$head = $File->read(4096);
			
			if (preg_match('/^[-\w.+]*\/[-\w.+]+:[\*\.a-zA-Z0-9]*$/m', $head)) {
				return 'Freedesktop Shared MIME-info Database';
			} else if (preg_match('/^[-\w.+]*\/[-\w.+]+\s+[a-zA-Z0-9]*$/m', $head)) {
				return 'Apache Module mod_mime';
			}
		}
		return null;
	}
/**
 * Analyzes a filename and determines the mime type
 *
 * @param string $file Path to a file, basename of a file or in reverse mode a mime type
 * @param array $options An array holding options
 * @return mixed A string containing the mime type of the file or false if mime type could not be determined, in reverse mode the pattern corresponding to the given mime type 
 * @access public
 */
	function analyze($file, $reverse = false) {
		if ($reverse) {
			return $this->__testReverse($file, $this->_items);
		}

		if ($results = $this->__test($file, $this->_items, true)) {
			return $results;
		}
		
		return $this->__test($file, $this->_items, false);
	}
/**
 * Will load a file from various sources
 * 
 * Supported formats:
 * - Freedesktop Shared MIME-info Database
 * - Apache Module mod_mime
 * - PHP file containing variables formatted like: $data[0] = array(item, item, item, ...) 
 *
 * @param mixed $file An absolute path to a glob file in apache, freedesktop or a filename (without .php) of a file in the configs/ dir in CakePHP format
 * @return mixed A format string or null if format could not be determined
 * @access private
 * @link http://httpd.apache.org/docs/2.2/en/mod/mod_mime.html
 * @link http://standards.freedesktop.org/shared-mime-info-spec/shared-mime-info-spec-0.13.html
 */
	function __read($db) {
		$format = MimeGlob::format($db);
		
		if ($format === 'Array') {
			foreach ($db as $item) {
				$this->register($item);
			}
		} elseif ($format === 'PHP') {
			include $db;
			foreach ($data as $item) {
				$this->register($item);
			}		
		} elseif ($format === 'Freedesktop Shared MIME-info Database') {
			$File =& new File($db);
			$File->open('rb');

			while (!feof($File->handle)) {
				$line = trim(fgets($File->handle));

				if (empty($line) || $line{0} === '#') {
					continue(1);
				}
				
				$line = explode(':', $line);
				
				if(count($line) > 2) {
					$priority = array_shift($line);
				} else {
					$priority = null;
				}
				if (!preg_match('/(\*\.)?[a-zA-Z0-9\.]+$|/', $line[1])) {
					continue(1);
				}
				$this->register(array('mime_type' => array_shift($line), 'pattern' => str_replace('*.',null, array_shift($line)), 'priority' => $priority));
			}			
		} elseif ($format === 'Apache Module mod_mime') {
			$File = new File($db);
			$File->open('rb');
			
			while (!feof($File->handle)) {
				$line = trim(fgets($File->handle));
				
				if (empty($line) || $line{0} === '#') {
					continue(1);
				}
				
				$line = preg_split('/\s+/', $line);
				$this->register(array('mime_type' => array_shift($line), 'pattern' => $line));
			}	
		} else {
			trigger_error('MimeGlob::read - Unknown db format', E_USER_WARNING);
		}
	}	
/**
 * Register a glob item
 * 
 * 	A valid item:
 * 		array(
 * 			'priority' => 50, [optional]
 * 			'mime_type' => 'image/jpeg',
 * 			'pattern' => 'jpg',
 * 			)
 * 	or
 * 		array(
 * 			'priority' => 90,
 * 			'mime_type' => 'image/jpeg',
 * 			'pattern' => array('jpg', 'jpeg'),
 * 			)
 * 
 * @param array $item A valid glob item
 * @param integer $priority A value between 0 and 100. Low numbers should be used for more generic types and higher values for specific subtypes. 
 * @return boolean True if item has successfully been registered, false if not
 * @access public
 */
	function register($item = array()) {
		foreach ((array)$item['pattern'] as $pattern) {
			if (isset($this->_items[$pattern])) {
				$this->_items[$pattern] = array_merge($this->_items[$pattern], array($item['mime_type']));	
			} else {
				$this->_items[$pattern] = array($item['mime_type']); 
			}
		}
	}		
/**
 * Exports current items as an array 
 *
 * @return array
 * @access public
 */
	function toArray() {
		$result = array();
		
		foreach ($this->_items as $pattern => $mimeTypes) {
			foreach($mimeTypes as $mimeType) {
				$result[] = array('mime_type' => $mimeType, 'pattern' => $pattern);
			}
		}
		return $result;
	}	
/**
 * Tests a file's contents against glob items
 *
 * This method also provides a wrapper for fnmatch which is
 * available only on POSIX compatible systems and 5x faster
 * 
 * @param string $name The basename of a file
 * @param array $items
 * @param boolean $caseSensitive
 * @return array Matched mime types keyed by patterns 
 * @access private
 */	
	function __test($name, $items, $caseSensitive = true) {
		$basename = pathinfo($name, PATHINFO_BASENAME);
		$ext = pathinfo($name, PATHINFO_EXTENSION);
		$results = array();
		
		if (!$caseSensitive) {
			$ext = strtolower($ext);
			$basename = strtolower($basename);
		}
		
		if (isset($items[$ext])) {
			$results = $items[$ext];
		}
		if (isset($items[$basename])) {
			$results = array_merge($results, $items[$basename]);
		}		
		return $results;		
	}
/**
 * Does a reverse test against glob items
 *
 * @param string $mimeType
 * @param array $items
 * @return array Matched patterns
 */
	function __testReverse($mimeType, $items) {
		$results = array();

		foreach ($items as $pattern => $mimeTypes) {
			if (in_array($mimeType, $mimeTypes)) {
				$results[] = $pattern;
			}
		}
		return $results;
	}
}
?>