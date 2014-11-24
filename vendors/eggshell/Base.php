<?php
/**
 * Some functions i'd like to have in any shell class
 *
 * @author kvz
 */
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Base {
	protected $_options = array(
		'log-print-level' => 'info',
		'log-file-level' => 'debug',
		'log-memory-level' => 'debug',
		'log-break-level' => 'err',
		'log-mark-trace' => false,
		'log-file' => '/var/log/egg.log',
		'log-date-format' => 'H:i:s',
		'log-section-open' => array('section_open'),
		'log-section-close' => array('section_close'),
		'log-trail-level' => 'trail',
		'app-root' => '',
		'class-autobind' => false,
		'class-autosetup' => false,
	);

	protected $logger;

	public function setLogger(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	public $logs;

	/**
	 * Allows to automatically instantiate other Objects
	 * re-using the $options array for their constructors
	 *
	 * @var array
	 */
	public $register = array();

	/**
	 * Apparently not even Reflection can get a classes
	 * own methods.
	 * Resorting to source preg_matching until a better
	 * solution can be found.
	 *
	 * @param <type> $filename
	 * @param <type> $search
	 *
	 * @return <type>
	 */
	public function ownMethods ($filename, $search = null) {
		if (!file_exists($filename)) {
			return $this->err('Class source: %s not found', $filename);
		}

		$buf = file_get_contents($filename, FILE_IGNORE_NEW_LINES);
		if (!preg_match_all('/^[\t a-z]*function\s+?(.+)\s*\(/ismU', $buf, $matches)) {
			return array();
		}
		$methods = $matches[1];
		if ($search !== null) {
			return in_array($search, $methods);
		}

		return $methods;
	}

	/**
	 * null means skipped, false means fail, true means okay.
	 * Usefull for logging purposes
	 *
	 * @param mixed null or boolean $res
	 *
	 * @return string
	 */
	public function conclude ($res) {
		if (false === $res) {
			return 'Fail';
		} else if (null === $res) {
			return 'Skip';
		} else {
			return 'Okay';
		}
	}

	/**
	 * Echo something.
	 * @todo should use propper STDOUT at some point
	 *
	 * @param <type> $str
	 */
	public function out ($str) {
		$args = func_get_args();
		$str  = array_shift($args);
		if (count($args)) {
			$str = vsprintf($str, (array)$args);
		}
		echo $str;
		echo "\n";
	}

	/**
	 * Abbreviate a string. e.g: Kevin van zonneveld -> Kevin van Z...
	 *
	 * @param string  $str
	 * @param integer $cutAt
	 * @param string  $suffix
	 *
	 * @return string
	 */
	public function abbr ($str, $cutAt = 30, $suffix = '...') {
		if (strlen($str) <= $cutAt) {
			return $str;
		}

		$canBe = $cutAt - strlen($suffix);

		return substr($str, 0, $canBe). $suffix;
	}

	/**
	 * Returns camelBacked version of an underscored string.
	 * Taken from CakePHP's Inflector
	 *
	 * @param string $string
	 * @return string in variable form
	 * @access public
	 * @static
	 * @link http://book.cakephp.org/view/572/Class-methods
	 */
	public function variable ($string) {
		$string  = $this->camelize($this->underscore($string));
		$replace = strtolower(substr($string, 0, 1));
		return $replace . substr($string, 1);
	}


	/**
	 * Returns the given camelCasedWord as an underscored_word.
	 * Taken from CakePHP's Inflector
	 *
	 * @param string $camelCasedWord Camel-cased word to be "underscorized"
	 * @return string Underscore-syntaxed version of the $camelCasedWord
	 * @access public
	 * @static
	 * @link http://book.cakephp.org/view/572/Class-methods
	 */
	public function underscore ($camelCasedWord) {
		return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
	}



	/**
	 * Recursive wrapper for underscore
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function underscorer ($data) {
		if (is_array($data)) {
			$newFields = array();
			foreach ($data as $key => $val) {
				$key = $this->underscore($key);
				$newFields[$key] = $this->underscorer($val);
			}
			return $newFields;
		}
		return $data;
	}

	/**
	 * Returns the given lower_case_and_underscored_word as a CamelCased word.
	 * Taken from CakePHP's Inflector
	 *
	 * @param string $lower_case_and_underscored_word Word to camelize
	 * @return string Camelized word. LikeThis.
	 * @access public
	 * @static
	 * @link http://book.cakephp.org/view/572/Class-methods
	 */
	public function camelize ($lowerCaseAndUnderscoredWord) {
		return str_replace(" ", "", ucwords(str_replace("_", " ", $lowerCaseAndUnderscoredWord)));
	}

	/**
	 * Recursive wrapper for underscore
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function camelizer ($data) {
		if (is_array($data)) {
			$newFields = array();
			foreach ($data as $key => $val) {
				$key = $this->camelize($key);
				$newFields[$key] = $this->camelizer($val);
			}
			return $newFields;
		}
		return $data;
	}

	/**
	 * Indent an entire block of lines
	 *
	 * @param mixed string or array $lines
	 * @param mixed				 $indentation kind of intendation to use: 4, '	', true
	 * @param string				 $newlines   the char to use for newlines
	 *
	 * @return string
	 */
	public function indent ($lines, $indentation = 4, $newlines = "\n") {
		// Setup Input
		if (is_string($lines)) {
			$lines = explode("\n", $lines);
		}
		if (!is_array($lines)) {
			// Neither string nor array
			// give this stuff back before accidents happen
			return $lines;
		}

		// Lot of ways to set indent
		if (is_numeric($indentation)) {
			$indent = str_repeat(' ', $indentation);
		} elseif (is_string($indentation)) {
			$indent = $indentation;
		} elseif ($indentation === true || $indentation === null) {
			$indent = '    ';
		} elseif ($indentation === false) {
			$indent = '';
		} else {
			return $this->err('Indendation can be a lot of things but not "%s"', $indentation);
		}

		// Indent
		foreach ($lines as &$line) {
			$line = $indent . $line;
		}

		// Newline
		return join($newlines, $lines);
	}

	public function sensible ($arguments) {
		if (is_object($arguments)) {
			return get_class($arguments);
		}
		if (!is_array($arguments)) {
			if (!is_numeric($arguments) && !is_bool($arguments)) {
				$arguments = "'".$arguments."'";
			}
			return $arguments;
		}
		$arr = array();
		foreach($arguments as $key=>$val) {
			if (is_array($val)) {
				$val = json_encode($val);
			} elseif (!is_numeric($val) && !is_bool($val)) {
				$val = "'".$val."'";
			}

			$val = $this->abbr($val);

			$arr[] = $key.': '.$val;
		}
		return join(', ', $arr);
	}

	/**
	 * array_merge & $this->_merge is just never what you need
	 * Borrowed from CakePHP's Set
	 *
	 * @param <type> $arr1
	 * @param <type> $arr2
	 *
	 * @return <type>
	 */
	public function merge ($arr1, $arr2 = null) {
		$args = func_get_args();
		$r = (array)current($args);
		while (($arg = next($args)) !== false) {
			foreach ((array)$arg as $key => $val)	 {
				if (is_array($val) && isset($r[$key]) && is_array($r[$key])) {
					$r[$key] = $this->merge($r[$key], $val);
				} elseif (is_int($key)) {
					$r[] = $val;
				} else {
					$r[$key] = $val;
				}
			}
		}
		return $r;
	}

	/**
	 * Returns or dumps a trace of the last steps in code execution
	 *
	 * @param <type> $strip
	 * @param <type> $dump
	 * @param <type> $array
	 *
	 * @return <type>
	 */
	public function trace ($strip = 2, $dump = false, $array=false) {
		$want = array(
			'file',
			'line',
			'args',
			'class',
			'function',
		);

		$traces = array();
		$debug_traces_orig = debug_backtrace();
		$debug_traces = $debug_traces_orig;
		array_splice($debug_traces, 0, $strip);
		foreach($debug_traces as $debug_trace) {
			$debug_trace = array_intersect_key($debug_trace, array_flip($want));
			$debug_trace['file'] = $this->inPath(@$debug_trace['file']);

			if ($array) {
				$traces[] = $debug_trace;
			} else {
				$traces[] = sprintf('%20s#%-4s %12s->%s()',
					@$debug_trace['file'],
					@$debug_trace['line'],
					@$debug_trace['class'],
					@$debug_trace['function']
				);
			}

		}

		$traces = array_reverse($traces);

		if ($dump) {
			#prd(compact('strip', 'dump', 'traces', 'debug_traces', 'debug_traces_orig'));
			foreach($traces as $trace) {
				$this->out($trace);
			}
		}

		return $traces;
	}

	/**
	 * Breadcrum tool. Place it inside a function to leave a trace in the
	 * logfiles. Will look at ->_options['log-mark-trace'] to determine what
	 * loglevel/type to use.
	 *
	 * @param <type> $level
	 * @return <type>
	 */
	public function mark ($level = 'debug') {
		if (empty($this->_options['log-mark-trace'])) {
			return null;
		}

		$traces = $this->trace(2, false, true);
		$trace  = array_pop($traces);
		return call_user_func(array($this, $level), '=Fired: %s->%s(%s)',
			@$trace['class'],
			@$trace['function'],
			$this->sensible(@$trace['args'][0])
		);
	}

	/**
	 * Strips the application root from a path, so will return the relative path
	 * inside the app.
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	public function inPath ($filename) {
		if (is_string($filename)) {
			$filename = str_replace($this->_options['app-root'], '', $filename);
		}

		return $filename;
	}

	/**
	 * Generic log function
	 *
	 * @param <type> $name
	 * @param <type> $arguments
	 *
	 * @return false so you can easily break out of a function
	 */
	public function log ($name, $arguments) {
		$arguments = func_get_args();
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function emerg () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function crit () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function err () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function error () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function warning () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function notice () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function info () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function debug () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function debugv () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function section_open () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function section_close () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function trail () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function stdout () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}
	public function stderr () {
		$arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
		return call_user_func_array(array($this, '_log'), $arguments);
	}

	public function  __construct ($options = array()) {
		// Get parent defined options
		$parentVars	= @get_class_vars(@get_parent_class($this));
		// Override with own defined options
		$this->_options = $this->merge((array)@$parentVars['_options'], $this->_options);
		// Override with own instance options
		$this->_options = $this->merge($this->_options, $options);

		// Automatically instantiate classes
		if (@$this->_options['class-autobind']) {
			foreach(get_class_vars(get_class($this)) as $property=>$val) {
				if ($val === null && substr($property, 0 , 1) === strtoupper(substr($property, 0 , 1))) {
					if (class_exists($property)) {
						$this->{$property} = new $property($this->_options);
					}
				}
			}
		}

		if (@$this->_options['class-autosetup']) {
			// Call setup method if it exists
			if (method_exists($this, '__setup')) {
				call_user_func(array($this, '__setup'));
			}
		}
	}

	/**
	 * Get or set ->_options
	 *
	 * @param mixed string or array $key
	 * @param mixed				 $val
	 * @param boolean			   $forceWrite if value is set, it will be written. unless you want to write null. use force in that case
	 *
	 * @return mixed
	 */
	public function opt ($key, $val = null, $forceWrite = false) {
		if (is_array($key)) {
			foreach($key as $k => $v) {
				$this->opt($k, $v);
			}
			return $this->_options;
		}
		if ($val !== null || $forceWrite || func_num_args() === 2) {
			$this->_options[$key] = $val;
		}
		return $this->_options[$key];
	}

	/**
	 * Internal log function. Always address it via another function
	 * or the traces wont work
	 *
	 * @param <type> $name
	 * @param <type> $arguments
	 *
	 * @return false so you can easily break out of a function
	 */
	protected function _log ($level, $format, $arg1 = null, $arg2 = null, $arg3 = null) {
		$arguments = func_get_args();
		$level	 = $arguments[0];
		$format	= $arguments[1];

		$alias = array(
			'error' => 'err',
		);

		if (isset($alias[$level])) {
			$level = $alias[$level];
		}

		// recurse?
		if (is_array($format)) {
			foreach($format as $f) {
				$arguments[1] = $f;
				call_user_func_array(array($this, '_log'), $arguments);
			}
			return false;
		} else {
			unset($arguments[0]);
			unset($arguments[1]);
		}

		$str = $format;
		if (count($arguments)) {
			foreach($arguments as $k => $v) {
				$arguments[$k] = $this->sensible($v);
			}
			$str = vsprintf($str, $arguments);
		}

		$levels = array_flip(array(
			'emerg',
			'alert',
			'crit',
			'err',
			'warning',
			'notice',
			'info',
			'debug',
			'debugv',
		));
		if (!empty($this->logger)) {
			$levelMap = array(
				'emerg'   => LogLevel::EMERGENCY,
				'alert'   => LogLevel::ALERT,
				'crit'    => LogLevel::CRITICAL,
				'err'     => LogLevel::ERROR,
				'warning' => LogLevel::WARNING,
				'notice'  => LogLevel::NOTICE,
				'info'    => LogLevel::INFO,
				'debug'   => LogLevel::DEBUG,
				'debugv'  => LogLevel::DEBUG,
			);
		}

		$section   = false;
		$showLevel = $level;
		$useLevel  = $level;
		$date      = date($this->_options['log-date-format']);

		$indent    = '    ';
		$prefix    = "";
		if (in_array($level, $this->_options['log-section-open'])) {
			$useLevel  = 'notice';
			$showLevel = '';
			#$date      = '------->';
			$date      = '        ';
			$section   = 'open';
			$indent    = ' ';
			$prefix    = "\n";
		} else if (in_array($level, $this->_options['log-section-close'])) {
			$useLevel  = 'notice';
			$showLevel = '';
			$section   = 'close';
			$date      = '        ';
			$indent    = ' ';
			// Leave this out for now
			#return false;
		} elseif ($level == $this->_options['log-trail-level']) {
			$useLevel  = 'notice';
		} elseif ($level == 'stderr') {
			$useLevel  = 'warning';
			$date      = '        ';
			$showLevel = '';
			$indent    = '        ';
		} elseif ($level == 'stdout') {
			$useLevel  = 'debug';
			$date      = '        ';
			$showLevel = '';
			$indent    = '        ';
		}

		if (!empty($this->logger) && isset($levelMap[$useLevel])) {
			$this->logger->log($levelMap[$useLevel], $str);
		}

		$msgWeight    = $levels[$useLevel];
		$printWeight  = $levels[$this->_options['log-print-level']];
		$memoryWeight = $levels[$this->_options['log-memory-level']];
		$fileWeight   = $levels[$this->_options['log-file-level']];
		$breakWeight  = $levels[$this->_options['log-break-level']];

//		$str = sprintf('[%4s][%8s] %s%s%s',
//			str_repeat('*', (7-$msgWeight)),
//			$showLevel,
//			date('H:i:s'),
//			$indent,
//			$str);
		$str = $prefix.sprintf('%8s %s%s%s',
			$showLevel,
			$date,
			$indent,
			$str);

		if ($msgWeight <= $memoryWeight) {
			$this->logs[$level][] = $str;
		}
		if ($msgWeight <= $printWeight) {
			$this->out($str);
		}
		if ($msgWeight <= $fileWeight) {
                        if (!$this->_options['log-file']) {
                            die("Error occured before log-file was set: " . $str . "\n");
                        }

			file_put_contents($this->_options['log-file'], $str."\n", FILE_APPEND);
		}
		if ($msgWeight <= $breakWeight) {

			$this->trail('');
			$this->trail(' Process halt, triggered by the following path: ');
			$this->trail('');
			$this->trail($this->trace(4));
			$traces = $this->trace(4);
			$this->trail('');
			exit(1);
		}

		return false;
	}
}
