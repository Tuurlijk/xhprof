<?php
if (PHP_SAPI == 'cli') {
	$_SERVER['REMOTE_ADDR'] = NULL;
	$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
}

include(dirname(__FILE__) . '/../xhprof_lib/config.php');

// I'm Magic :)
class visibilitator {
	public static function __callstatic($name, $arguments) {
		$func_name = array_shift($arguments);
		//var_dump($name);
		//var_dump("arguments" ,$arguments);
		//var_dump($func_name);
		if (is_array($func_name)) {
			list($a, $b) = $func_name;
			if (count($arguments) == 0) {
				$arguments = $arguments[0];
			}
			return call_user_func_array(array($a, $b), $arguments);
			// echo "array call  -> $b ($arguments)";
		} else {
			call_user_func_array($func_name, $arguments);
		}
	}
}

// Only users from authorized IP addresses may control Profiling
if ($controlIPs === FALSE || in_array($_SERVER['REMOTE_ADDR'], $controlIPs) || PHP_SAPI == 'cli') {
	if (isset($_GET['_profile'])) {
		// Give them a cookie to hold status, and redirect back to the same page
		setcookie($_xhprof['cookieName'], $_GET['_profile']);
		$newURI = str_replace(array('_profile=1', '_profile=0'), '', $_SERVER['REQUEST_URI']);
		header("Location: $newURI");
		exit;
	}

	if (isset($_COOKIE[$_xhprof['cookieName']]) && $_COOKIE[$_xhprof['cookieName']] || PHP_SAPI == 'cli' && ((isset($_SERVER['XHPROF_PROFILE']) && $_SERVER['XHPROF_PROFILE']) || (isset($_ENV['XHPROF_PROFILE']) && $_ENV['XHPROF_PROFILE']))) {
		$_xhprof['doprofile'] = TRUE;
		$_xhprof['type'] = 1;
	}
}


// Certain URLs should never have a link displayed. Think images, xml, etc.
foreach ($exceptionURLs as $url) {
	if (stripos($_SERVER['REQUEST_URI'], $url) !== FALSE) {
		$_xhprof['display'] = FALSE;
		header('X-XHProf-No-Display: Trueness');
		break;
	}
}
unset($exceptionURLs);

// Certain urls should have their POST data omitted. Think login forms, other privlidged info
$_xhprof['savepost'] = TRUE;
foreach ($exceptionPostURLs as $url) {
	if (stripos($_SERVER['REQUEST_URI'], $url) !== FALSE) {
		$_xhprof['savepost'] = FALSE;
		break;
	}
}
unset($exceptionPostURLs);

// Determine wether or not to profile this URL randomly
if ($_xhprof['doprofile'] === FALSE) {
	// Profile weighting, one in one hundred requests will be profiled without being specifically requested
	if (rand(1, $weight) == 1) {
		$_xhprof['doprofile'] = TRUE;
		$_xhprof['type'] = 0;
	}
}
unset($weight);

// Certain URLS should never be profiled.
foreach ($ignoreURLs as $url) {
	if (stripos($_SERVER['REQUEST_URI'], $url) !== FALSE) {
		$_xhprof['doprofile'] = FALSE;
		break;
	}
}
unset($ignoreURLs);

unset($url);

// Certain domains should never be profiled.
foreach ($ignoreDomains as $domain) {
	if (stripos($_SERVER['HTTP_HOST'], $domain) !== FALSE) {
		$_xhprof['doprofile'] = FALSE;
		break;
	}
}
unset($ignoreDomains);
unset($domain);

// Display warning if extension not available
if ($_xhprof['doprofile'] === TRUE && extension_loaded('tideways')) {
	include_once dirname(__FILE__) . '/../xhprof_lib/utils/xhprof_lib.php';
	include_once dirname(__FILE__) . '/../xhprof_lib/utils/xhprof_runs.php';
	if (isset($ignoredFunctions) && is_array($ignoredFunctions) && !empty($ignoredFunctions)) {
		tideways_enable(TIDEWAYS_FLAGS_CPU + TIDEWAYS_FLAGS_MEMORY, array('ignored_functions' => $ignoredFunctions));
	} else {
		tideways_enable(TIDEWAYS_FLAGS_CPU + TIDEWAYS_FLAGS_MEMORY);
	}
} elseif ($_xhprof['display'] === TRUE && !extension_loaded('tideways')) {
	$message = 'Warning! Unable to profile run, xhprof extension not loaded';
	trigger_error($message, E_USER_WARNING);
}

if ($_xhprof['doprofile'] === TRUE && extension_loaded('xhprof')) {
	include_once dirname(__FILE__) . '/../xhprof_lib/utils/xhprof_lib.php';
	include_once dirname(__FILE__) . '/../xhprof_lib/utils/xhprof_runs.php';
	if (isset($ignoredFunctions) && is_array($ignoredFunctions) && !empty($ignoredFunctions)) {
		xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY, array('ignored_functions' => $ignoredFunctions));
	} else {
		xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
	}
} elseif ($_xhprof['display'] === TRUE && !extension_loaded('xhprof')) {
	$message = 'Warning! Unable to profile run, xhprof extension not loaded';
	trigger_error($message, E_USER_WARNING);
}

function xhprof_shutdown_function() {
	global $_xhprof;
	require dirname(__FILE__) . '/footer.php';
}

register_shutdown_function('xhprof_shutdown_function');
