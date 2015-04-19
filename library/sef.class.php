<?php

defined('IN_APP') ? NULL : exit();

/**
 * The SEF class is for search engine friendly URLS
 * 
 * This class houses the url parsing methods that decide which
 * controllers should be run during execution.
 * 
 * @author Justin Carlson
 * @date 08/25/2014
 * 
 * @TODO Run routes/option/task/action through language methods to allow
 * translations for urls.
 */
class SEF {

	private static $isTestMode = false;
	
    private static $option = null;
	private static $task = null;
	private static $action = null;
	
	private static $initialized = false;
	private static $sections = null;	
    private static $route = null;
    private static $rootRoute = null;
	
	public static function init() {
	
		if(!self::$initialized) {
            
            // Assign base url
            View::assign('url', self::getBaseUrl());

            self::$initialized = true;
            
			$parts = parse_url($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            self::$rootRoute = self::$route = ltrim(str_replace(self::getBaseUrl(false), '', $parts['path']), '/');
			self::$sections = explode('/', self::$route);
			
            // Check if we have a first section
            if(!empty(self::$sections[0]) && isset(self::$sections[0])) {
                
                // Are we in test mode?
                if(App::isDeveloper() && !App::isLive() && self::$sections[0] == 'TEST') {
                    
                    self::$isTestMode = true;
                    array_shift(self::$sections);
                
                // Routes are ignored for test mode
                } else {
                
                    // Check if a route is defined for this section
                    if($route = App::get('routes.' . self::$sections[0], 'configs')) {
                        self::setRoute($route);
                        return;
                    }
                    
                }
                
                // Set route
                call_user_func_array(array('SEF', 'setRoute'), self::$sections);
                return;
            }
            
            // Set default route
            self::setDefaultRoute();
		}		
	}
    
    public static function isTestMode() {
        return App::isDeveloper() && !App::isLive() && self::$isTestMode;
    }
    
    /**
     * Set the default controllers to run if none supplied
     * 
     * First it checks if a defaultRoute is defined in app configs.
     * If no default route is define it will result to the index controller.
     * 
     */
    private static function setDefaultRoute() {
        if($route = App::get('defaultRoute', 'configs')) {
            self::setRoute($route);
        } else {
            self::setRoute('index');
        }
    }
    
    /**
     * Set the controllers to run
     * 
     * This accepts multiple options. A single string can be passed in.
     * This will only define an option controller to run.
     * 
     * @example SEF::setRoute('homepage');
     * Will run controllers/homepage/homepage.php
     * 
     * A tring containing any slashes(/) will split the parts into sections.
     * First is option, second is task, third is action.
     * 
     * @example SEF::setRoute('users/profile');
     * Will run controllers/users/users-profile.php
     * 
     * @example SEF::setRoute('users/profile/edit');
     * Will run controllers/users/users-profile.php and execute the edit method.
     * 
     * Or multiple args can be passed in to define the option, task, and action
     * respectively.
     * 
     * @example SEF::setRoute('users', 'profile', 'edit');
     */
    public static function setRoute() {
        
        $sections = func_get_args();
        
        if(count($sections) === 1) {
            $sections = explode('/', $sections[0]);
        } elseif($sections < 1) {
            trigger_error(App::_('', 'SEF::setRoute must be called with at least a single param passed in.'));
            return;
        }
        
        self::$route = implode('/', $sections);
        
        if(isset($sections[0])) self::$option = $sections[0];
        if(isset($sections[1])) self::$task = $sections[1];
        if(isset($sections[2])) self::$action = $sections[2];
    }
    
    public static function getSections($section=null) {
        if($section) {
            return isset(self::$sections[$section]) ? self::$sections[$section] : false;
        }
        return self::$sections;
    }
    
    public static function getRoute() {
        return self::$route;
    }
    
    public static function getRootRoute() {
        return self::$rootRoute;
    }
	
	public static function getOption() {
		
		self::init();
		
		if(self::$option) return self::$option;
		
		return false;
		
	}
	
	public static function getTask() {
		
		self::init();
		
		if(self::$task) return self::$task;
		
		return false;
		
	}
	
	public static function getAction() {
		
		self::init();
		
		if(self::$action) return self::$action;
		
		return false;
		
	}
    
    public static function getCurrentUrl() {
        $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : ""; 
        $s1 = strtolower($_SERVER["SERVER_PROTOCOL"]);
        $s2 = "/";
        $protocol = substr($s1, 0, strpos($s1, $s2)) . $s; 
        $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]); 
        return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI']; 
    }

	public static function getBaseUrl($withProtocol=true, $withHost=true) {
        
		/* First we need to get the protocol the website is using */
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER["HTTPS"] == 'on') ? 'https://' : 'http://';

        $path = $_SERVER['PHP_SELF'];

        /*
         * returns an array with:
         * Array (
         *  [dirname] => /myproject/
         *  [basename] => index.php
         *  [extension] => php
         *  [filename] => index
         * )
         */
        $path_parts = pathinfo($path);
        $directory = $path_parts['dirname'];
		
        /*
         * If we are visiting a page off the base URL, the dirname would just be a "/",
         * If it is, we would want to remove this
         */
        $directory = ($directory == "/") ? "" : $directory;

        /* Returns localhost OR mysite.com */
        $host = $_SERVER['HTTP_HOST'];

        /*
         * Returns:
         * http://localhost/mysite
         * OR
         * https://mysite.com
         */
        return ($withProtocol ? $protocol : '') . ($withHost ? $host : '') . $directory;
    }
	
}

?>