<?php

defined('IN_APP') ? NULL : exit();

class App {
	
	/**
	 * App settings and sessions vars
	 */
	private static $registry = null;
    
    private static $currentMemoryUsage = null;
	
	public static function init() {

		// Report all PHP errors
		//error_reporting(-1);
		// Default errors off always, they will be turned back on for develoeprs
        //ini_set('display_errors', 0);

		// Initialize the core settings for all request types
		defined('DS') ? null : define('DS', '/');// (\ for Windows) and (/ for unix)
		// Define the sites root. Running localy will result in localhost
		defined('DIR_ROOT') ? null : define('DIR_ROOT', dirname( dirname(__FILE__) ));
		// Define library contain core files
		defined('DIR_LIB') ? null : define('DIR_LIB', dirname(__FILE__));
		// Define app directory, where all controllers are stored
		defined('DIR_APP') ? null : define('DIR_APP', DIR_ROOT.DS.'application');
        // Where all test files are held
		defined('DIR_TESTS') ? null : define('DIR_TESTS', DIR_ROOT.DS.'tests');
		// Define themes directory, where all controllers are stored
		defined('DIR_THEMES') ? null : define('DIR_THEMES', DIR_ROOT.DS.'themes');
		// Define models directory
		defined('DIR_MODELS') ? null : define('DIR_MODELS', DIR_ROOT.DS.'application'.DS.'models');
        // Define controllers directory
		defined('DIR_CONTROLLERS') ? null : define('DIR_CONTROLLERS', DIR_ROOT.DS.'application'.DS.'controllers');

		// Set up autoloading
		spl_autoload_register(array('App', 'autoLoader'));
		
		// Define sites root url
		defined('SITE_ROOT') ? null : define('SITE_ROOT', SEF::getBaseUrl(false, false));
		// Define sites themes url
		defined('SITE_THEMES') ? null : define('SITE_THEMES', SITE_ROOT . DS . 'themes');
		// Define sites plugin url
		defined('SITE_PLUGINS') ? null : define('SITE_PLUGINS', SITE_ROOT . DS . 'application' . DS . 'plugins');
		// Define sites plugin directory
		defined('DIR_PLUGINS') ? null : define('DIR_PLUGINS', DIR_ROOT . DS . 'application' . DS . 'plugins');
		
		// Initialize settings/plugin settings/configs/registry/sessions/cookies
		self::initRegistry();

        // Set up langauge support
        App::addTranslation('core', 'core');
        
        // Initialize app
        new Main;
        
		// Load plugins
		Plugins::loadPlugins();
	
		// Handle errors - any errors before or during plugin initalization will be handled normally
		set_error_handler(array('Error', 'catchError'));
		register_shutdown_function(array('Error', 'shutdownError'));
        
		// Fire plugins init event
		Plugins::action('onPluginsInit', Plugins::getEnabledPlugins());
		
		// Define current theme directory - Defined here so plugins can hook into onAppGetTheme event
		defined('DIR_CUR_THEME') ? null : define('DIR_CUR_THEME', DIR_ROOT.DS.'themes'.DS.App::getTheme());
		// Define sites current theme url - Defined here so plugins can hook into onAppGetTheme event
		defined('SITE_CUR_THEME') ? null : define('SITE_CUR_THEME', SITE_THEMES . DS . App::getTheme());
        
        // Run controllers
        if(SEF::isTestMode()) {
            self::executeTestControllers();
        } else {
            self::executeControllers();
        }

		// Controllers have completed, load view
		App::displayView();
	}

    /**
     * Determines is the application is in live more or not
     * 
     * All localhost development environments will return false.
     * And application is considered in live mode when the apps config
     * setting 'live' == true. If the 'live' config setting is not
     * defined inside of the Main class, then it will return true.
     * 
     * @returns Boolean True if in live mode otherwise false
     */
    public static function isLive() {
        if($_SERVER['HTTP_HOST'] != 'localhost' && App::get('live', 'configs', true)) {
            return true;
        }
        return false; 
    }
    
	public static function isDeveloper() {
		if($_SERVER['HTTP_HOST'] == 'localhost' || in_array(trim($_SERVER['REMOTE_ADDR']), App::get('developers', 'configs'), true)) {
			return true;
		} else {
			return false;
		}
	}

	public static function isAdmin() {
		if(in_array(trim($_SERVER['REMOTE_ADDR']), App::get('admins', 'configs'), true) || App::isDeveloper()) {
			return true;
		} else {
			return false;
		}
	}
    
    public static function isTester($type=null) {
        if($type) {
            if(in_array(trim($_SERVER['REMOTE_ADDR']), App::get('testers.' . $type, 'configs'), true)) return $type;
        } else {
            foreach(App::get('testers', 'configs', array()) as $type => $group) {
                if(in_array(trim($_SERVER['REMOTE_ADDR']), App::get('testers.' . $type, 'configs'), true)) return $type;
            }
        }
        return false;
	}
    
    /**
     * Adds a translation file
     * 
     * This scans the /language directory for files named
     * using the coorresponsing langauge. It then checks the users
     * browser language setting and will use the first match found.
     * 
     * For example if a user has fr, de, and en-US language used in their browser.
     * And if es.php, en-US.php, and de.php are available translation files
     * inside of the languages directory. The selected language translation will be
     * de.php. Since fr.php does not exist we move on to the users second language choice
     * which is de.
     * 
     * @author Justin Carlson
     * @date 8/19/2014
     * @since 1.0.0
     * 
     * @TODO Support for different tranlation adapters (gettext, custom, etc.)
     * @TODO Support for different folder structures
     * @TODO Support for controller specific tranlations 
     * @TODO String caching
     */
    public static function addTranslation($type, $namespace) {
        
        switch($type) {
            case 'core':
                $folder = DIR_ROOT.DS.'language';
            break;
            case 'plugin':
                $folder = DIR_PLUGINS.DS.$namespace.DS.'language';
                $namespace = 'plugins.'.$namespace;
            break;
            case 'controller':
                $folder = DIR_CONTROLLERS.DS.$namespace.DS.'language';
                $namespace = 'controllers.'.$namespace;
            break;
        }
        
        if(App::get($namespace, 'strings')) return;
        
        $supportedLangs = array(App::get('lang', 'configs'));
        
        if(is_dir($folder)) {
            $langDirs = scandir($folder);
            foreach($langDirs as $lang) {
                if(strpos($lang, '.') === 0) continue;
                $pathInfo = pathinfo($lang);
                $supportedLangs[] = $pathInfo['filename'];
            }
        }
 
        $langToUse = App::get('lang', 'configs');
        
        if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach($languages as $lang) {
                // Grab lang code
                $lang = (strpos($lang, ';') !== false) ? substr($lang, 0, strpos($lang, ';')) : $lang;
                // Check if lang is found in supported langauge
                if(in_array($lang, $supportedLangs)) {
                    // Set the page locale to the first supported language found
                    $langToUse = $lang;
                    break;
                }
            }
        }
        
        $file = $folder.DS.$langToUse.'.php';
        
        if(file_exists($file)) {
            App::set($namespace, require_once($file), 'strings');
        }
    }
    
    /**
     * Localization methods
     * 
     * Basic string
     * App::_('Hello world');
     * 
     * Some langauages may have a different word for each context that is used
     * App::_('Comment', 'noun'); // This is a comment
     * App::_('Comment', 'verb'); // Please comment on my post
     * 
     * Plural forms take 3 arguments
     * - First is the singular form of the word/phrase
     * - Second is the plural form of the word/phrase
     * - Third is the number to evaluate against
     * - You'll notice this is wrapped in a printf(), this is to replace the %s with the $number
     * after translation
     * printf(App::_('There are %s comment', 'There are %s comments', $number), $number);
     * 
     * To immediately echo any string use the method App::_e() with the same args
     * App::_e('Hello world');
     * 
     * @param array|string
     * @param string|int
     * 
     * @return string The translated string
     * 
     * @author Justin Carlson
     * @date 8/20/2014
     * @since 1.0.0
     */
    public static function _($namespace='') {
        
        $args = func_get_args();
        
        // At least 1 arg must be passed in
        if(count($args) < 2) {
            trigger_error(App::_('', 'App::_() expects at least two parameters.'));
            return;
        }
        
        if(!empty($namespace)) $namespace .= '.';
        
        if(isset($args[3])) {
            if($args[3] === 1) {
                $path = $namespace . $args[1] . '.0';
                $default = $args[1];
            } else {
                $path = $namespace . $args[1] . '.1';
                $default = $args[2];
            }
        } else {
            // Is a context defined?
            if(isset($args[2])) {
                $path = $namespace . $args[1] . '.' . $args[2];
                $default = $args[1];
            } else {
                $path = $namespace . $args[1];
                $default = $args[1];
            }
        }
        return App::get($path, 'strings', $default);
    }
    
    public static function _e() {
        echo call_user_func_array(array('App', '_'), func_get_args());
    }
	
	private static function initRegistry() {
		
        require_once(DIR_APP.DS.'index.php');
        
		// Load configs file - required
		self::$registry['configs'] = Main::$configs;
		
		self::initRequests();
		self::initSessions();
		self::initCookies();
        self::$registry['strings'] = array();
		
		// Check to see if any sessions timers exist
		if(count(self::get('timeouts'))) {
			
			// Check if the timeouts have expired
			foreach(self::get('timeouts') as $path => $timeout) {
				
				if($timeout < time()) {
					self::_unset(str_replace('__TOSEP__', '.', $path));
					self::_unset('timeouts.' . $path);
				}
				
			}
			
		}
	}
	
	private static function initSessions() {

        self::sessionStart();
	
	}
    
    // TODO catch assigned sessio vars while session is closed and merge with
    // session on reopen
    public static function sessionStart() {
        
        // If this is a session, make sure session is started
        if(session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        } else {
            return;
        }
        
		// Add the session to the registry
		self::$registry['session'] =& $_SESSION;
        
    }
    
    public static function sessionClose() {
        
        session_write_close();
        
    }
	
	private static function initCookies() {

		// Add any cookies into the registry
		self::$registry['cookies'] =& $_COOKIE;
		
	}
	
	private static function initRequests() {

		// Add request vars to the registry
		$parts = parse_url($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		if(isset($parts['query'])) parse_str($parts['query'], $query);
		
		self::$registry['request']['get'] = isset($query) ? $query : array();
		self::$registry['request']['post'] = $_POST;
        self::$registry['request']['files'] = $_FILES;
        
        // Get the request method
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        if($method != 'get' && $method != 'post') $method = 'get';
        
        // If its an ajax or json request, overwrite default method
        if(self::get('get.ajax_request', 'request')) $method = 'ajax';
        if(self::get('get.json_request', 'request')) $method = 'json';
        
        // If there is a callback param defined with a jsonp request
        if(self::get('get.jsonp_request', 'request') && self::get('get.callback', 'request')) $method = 'jsonp';
        
		self::$registry['request']['method'] = $method;
	}

	// Return a config setting
	public static function get($config=NULL, $namespace="session", $default=NULL) {
			
        $return = self::$registry;
        $path = $namespace . ($config === null ? '' : '.'.$config);

        foreach(explode('.', $path) as $k){
            if(isset($return[$k])) {
                $return = $return[$k];
            } else {
                $return = NULL;
                break;	
            }
        }

		return $return !== NULL ? $return : $default;
	}

	public static function set($path, $val, $namespace='session', $timeout=NULL){
	
        if(substr($namespace, 0, 7) == 'request' && $path != 'method') return;
        
		// check if we are setting a timeout val
		if($timeout !== NULL) {
			
			$tPath = str_replace('.', '__TOSEP__', $path);
		
			// create the timeout session
			self::set('timeouts.'.$tPath, $timeout+time());
			
		}

		//split the path
		$path = explode('.',$path);
		
		$set =& self::$registry;
		
		//check to make sure the namespace is defined and set it if it's not
		if( !isset($set[$namespace]) ) $set[$namespace] = array();
		$set =& $set[$namespace];
					
		foreach( $path as $v ){
			//if the piece isn't set, set it to an empty array
			if( !isset($set[$v]) ) $set[$v] = array();
			$set =& $set[$v];
		}
		
		// we're done creating structure, set the variable
		$set = $val;
	}
		
	public static function _unset($path, $namespace='session') {
		
        // Make sure there is something to unse
        //if(!App::get($path, $namespace)) return;
        
		$tPath = str_replace('.', '_', $path);
		
		// if path is null, unset the namespace
		if( $path === NULL || $path === "" ) {
            
            // If were unsetting the full session use session_unset
            if($namespace == 'session') {
                session_unset();
                $isFullSession = true;
            }
			$path = $path = "['$namespace']";
            
		} else {
			//split the path
			$path = explode('.',$path);
			//implode the path into an array structure
			if(!empty($path)){
				$path = "['$namespace']['".implode("']['",$path)."']";
			} else {
				$path = "['$namespace']";
			}
		}
	
        // we're done creating structure, set the variable
        eval('unset(self::$registry'.$path.');');
        
		// Check to see if any sessions timers exist and the full session was not unset
		if(!isset($isFullSession) && self::get('timeouts.' . $tPath)) {
			
			self::_unset('timeouts.' . $tPath);
			
		}

	}
	
	// TODO cookie support
	public static function setCookie($name, $val, $timeout='604800') {
		setcookie(sha1($name . self::get('salt', 'application')), $val, time()+$timeout);
	}

	public static function unsetCookie($name) {
		setcookie(sha1($name . self::get('salt', 'application')), "", time() - 3600, '/auth/login');
	}
    
    private static function executeTestControllers() {
        return;
        $sections = SEF::getSections();
        
        require_once(DIR_TESTS.DS.'controllers'.DS.SEF::getOption().'.php');
        if(isset($sections[0])) require_once(DIR_APP.DS.'controllers'.DS.SEF::getOption().DS.SEF::getOption().'.php');
        if(isset($sections[1])) require_once(DIR_APP.DS.'controllers'.DS.SEF::getOption().DS.SEF::getOption().'-'.SEF::getTask().'.php');
        
        
        $method ='';
        foreach(SEF::getSections() as $section) {
            $method .= ucfirst($section);
        }
        
        // MarketBrowse
        exit();
        
    }
    
    public static function executeControllers() {
        
        $args = func_get_args();
        
        if(count($args)) {
            // Cache runtime controllers
            $option = SEF::getOption();
            $task = SEF::getTask();
            $action = SEF::getAction();
            $method = self::get('method', 'request');
            
            // Set temp controllers to run
            call_user_func_array(array('SEF', 'setRoute'), $args);
        }
        
        // Try to execute option controller
		if(($optionController = App::executeOptionController()) !== false) {
		
			// Try to execute task controller
			if(($taskController = App::executeTaskController($optionController)) !== false) {
		
				// Try to execute action method
				App::executeActionMethod($taskController);
				
			}
            
            // Complete task controller
            if(method_exists($taskController, '_finish')) call_user_func(array($taskController, '_finish'));
            
            // Complete option controller
			if(method_exists($optionController, '_finish')) call_user_func(array($optionController, '_finish'));
		}
        
        // Restore runtime controllers
        if(count($args)) {
            $template = View::getContent();
            
            call_user_func_array(array('SEF', 'setRoute'), array($option, $task, $action));
            
            return $template;
        }
        
    }
	
	private static function executeOptionController() {
		
		// Get option name
		$name = SEF::getOption();
        // Get option file name
        $fileName = DIR_APP.DS.'controllers'.DS.$name.DS.$name.'.php';
		
		// Does controller exist
		if(file_exists($fileName)){
			
            $ret = null;
            
			require_once($fileName);
			$class = str_replace('-', '_', "Option_$name");
			$object = new $class();
			
			// Run defualt _execute method if exists
			if(method_exists($object, '_execute')) $ret = $object->_execute();
			
			// Run controller request specific method if exists
			if($ret !== false && method_exists($object, '_' . self::get('method', 'request'))) $ret = call_user_func(array($object, '_' . self::get('method', 'request')), $object);
            
			return $ret === false ? $ret : $object;
			
		}
		
		return false;
		
	}

	private static function executeTaskController($optionController) {
	
		// Get option name
		$name = SEF::getTask();
		
		// Does task controller exist
		if(file_exists(DIR_APP.DS.'controllers'.DS.SEF::getOption().DS.SEF::getOption().'-'.$name.'.php') ){
			
            $ret = null;
			require_once(DIR_APP.DS.'controllers'.DS.SEF::getOption().DS.SEF::getOption().'-'.$name.'.php');
			$class = str_replace('-', '_', "Task_$name");
			$object = new $class();
			
			// Run defualt _execute method if exists
			if(method_exists($object, '_execute')) $ret = $object->_execute();
			
			// Run controller request specific method if exists
			if($ret !== false && method_exists($object, '_' . self::get('method', 'request'))) $ret = call_user_func(array($object, '_' . self::get('method', 'request')), $object);
            
			return $ret === false ? $ret : $object;
			
		}
		
		return false;
		
	}

	private static function executeActionMethod($taskController) {
	
		// Get method name
        $name = str_replace('-', '_', SEF::getAction());
		
		if($name && method_exists($taskController, $name)) {
            
            $args = array_slice(SEF::getSections(), 3);
            call_user_func_array(array($taskController, $name), $args);
            
        }
		
		return false;
		
	}
	
	private static function displayView() {
	
		// Display the view
		View::display();
		
	}
    
    public static function debugMemory($name=null) {
        // Grab current memory
        $currentMemory = memory_get_usage(true);
        // Calc change in memory if previous mem exists
        if(self::$currentMemoryUsage) {
            $memoryChange = $currentMemory - self::$currentMemoryUsage;
        }
        // Update current memory
        self::$currentMemoryUsage = $currentMemory;
        App::debug('Memory Usage: ' . self::$currentMemoryUsage . (isset($memoryChange) ? '<br />Change of: ' . $memoryChange : ''), $name);
    }
	
	/**
	 * Debug data
	 */
	public static function debug($var, $name=NULL) {

		if(App::isDeveloper()) {
			
			$info = debug_backtrace();
            
			$name = $name ? $name : substr(strrchr($info[0]['file'], '\\'), 1) . " - Line " . $info[0]['line'];
			
            // Clone objects to output current state
            if(is_object($var)) {
                $var = clone $var;
            }
            
			$default = '<pre class="app-debug">' . print_r(array($name, print_r($var, true), $info[0]['line'], $info[0]['file']), true) . '</pre>';
			
            // Only output the debug on page requests that have a template
			if(App::get('method', 'request') != 'JSON' && App::get('method', 'request') != 'JSONP') {
                echo(Plugins::filter('onAppDebug', $default, $var, $name, $info));
            // Nothing will be output by default
            // Requests without a template will run the debug through a filter so plugina can still tie in
            } else {
				Plugins::filter('onAppDebug', $default, $var, $name, $info);
			}
				
		}
	
	}
	
	public static function getTheme() {
		$theme = App::get('theme', 'configs', 'default');
		return Plugins::filter('onAppGetTheme', $theme);
	}

    /**
     * Redirects the page to the passed in route
     * 
     * @param Mixed $route='/' Can be a string of the path to redirect to
     * ex. users/signup
     * ex. array('users', 'signup') or and array of each controller to load
     * @param String|Array $queryStr A query string to attach to the redirection url
     */
    public static function redirect($route='', $queryStr='') {
        // Check if this is occuring during an active import
        if(View::$activeImport) return;
        // Is this an external redirect?
        if(substr($route, 0, 7) == "http://" || substr($route, 0, 8) == "https://") {
            // Redirect
            header("Location: " . $route);
            exit();
        }
        // If is array, build query string from array
        $queryStr = is_array($queryStr) ? http_build_query($queryStr) : $queryStr;
        // Add leading ? if not present and we have a queryStr
        $queryStr = substr($queryStr, 0, 1) == '?' || empty($queryStr) ? $queryStr : '?' . $queryStr;
        // Remove leading / if present
        $route = strpos($route, '/') === 0 ? substr($route, 1) : $route;
        // Set route
        SEF::setRoute($route);
        // Redirect
        header("Location: " . SEF::getBaseURL() . '/' . SEF::getRoute() . $queryStr);
        exit();
    }
    
	private static function autoLoader($class) {

		$name = strtolower($class).'.class.php';
        
        if(strstr($class, '\\', true)) {
            $default = strstr($class, '\\', true) . '.php';
        } else {
            $default = $class.'.php';
        }
		
		// Check core library first
		if(is_file(DIR_LIB.DS.$name)) {
			require_once(DIR_LIB.DS.$name);
            return;
        }
        
        // Check user library second
		if(is_file(DIR_APP.DS.'library'.DS.$default)) {
			require_once(DIR_APP.DS.'library'.DS.$default);
            return;
        }	
	}
		
}

?>