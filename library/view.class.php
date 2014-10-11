<?php

defined('IN_APP') ? NULL : exit();

/**
 * @TODO External dependencies, maybe make config options?
 */
class View {
	
    /**
     * Whether the app is currently running imported controlleres or not
     * 
     * This is checked when running App::redirect();
     * The redirect will be cancelled if activeImport is true.
     */
    public static $activeImport = false;
    
    private static $template = 'index.html';
	private static $jsVars = array();
	private static $vars = array();
	private static $content = "";
    
    private static function _() {
        $args = func_get_args();
        array_unshift($args, 'controllers.' . SEF::getOption());
        return call_user_func_array(array('App', '_'), $args);
    }
	
    /**
     * Determines which display method should be called
     * 
     * displayAll() is the default display method used for displaying
     * a full page of content.
     * 
     * displayAJAX() is used for AJAX requests and will not display
     * the main content. It will only return the controllers content.
     * 
     * displayJSON() is used for JSON requests and will only return
     * all assigned template vars in a json string.
     * 
     * displayJSONP() is used for JSONP requests. Returns the assigned template vars
     * wrapped in a jsonp function.
     */
	public static function display() {

        switch(App::get('method', 'request')) {
            case 'ajax';
                $method = 'displayAJAX';
            break;
            case 'json';
                $method = 'displayJSON';
            break;
            case 'jsonp';
                $method = 'displayJSONP';
            break;
            default:
                $method = 'displayAll';
            break;
        }
        
        Plugins::action('onBeforeViewDisplay', $method);
        
        echo call_user_func(array('View', $method));
        
        Plugins::action('onAfterViewDisplay');
    }
    
    private static function displayAll() {
        
        self::assign('NYM_HEAD', self::getHead());
        self::assign('NYM_CONTENT', self::getContent());
        self::assign('NYM_FOOTER', self::getFooter());
		
		return $template = self::loadTemplate(DIR_THEMES.DS.App::getTheme().DS.self::getTemplate());
	}
    
    private static function displayAJAX() {
        
        $template = self::getContent();
        
        $template = self::parseTemplate($template);
        
        return $template;
        
    }
    
    private static function displayJSON() {
        
        return json_encode(self::$vars);
        
    }
    
    private static function displayJSONP() {
        
        return App::get('get.callback', 'request') . '(' . json_encode(self::$vars) . ');';
        
    }
		
	public static function getContent() {
		
		// Get option name
		$option = SEF::getOption();
		$task = SEF::getTask();
		$action = SEF::getAction();
				
		// TODO - First try to load an request-type_action file
		
		// TODO - Second request-type_task

		// TODO - Third request-type
		
		// Fourth try to load action_task file
        $file = file_exists(DIR_CUR_THEME.DS.'controllers'.DS.$option.DS.$option.'-'.$task.'-'.$action.'.html') ?
                    DIR_CUR_THEME.DS.'controllers'.DS.$option.DS.$option.'-'.$task.'-'.$action.'.html' :
                (file_exists(DIR_CUR_THEME.DS.'controllers'.DS.$option.DS.$option.'-'.$task.'.html') ?
                    DIR_CUR_THEME.DS.'controllers'.DS.$option.DS.$option.'-'.$task.'.html' :
                (file_exists(DIR_CUR_THEME.DS.'controllers'.DS.$option.DS.$option.'.html') ?
                    DIR_CUR_THEME.DS.'controllers'.DS.$option.DS.$option.'.html' :
                (file_exists(DIR_CUR_THEME.DS.'controllers'.DS.$option.DS.'default.html') ?
                    DIR_CUR_THEME.DS.'controllers'.DS.$option.DS.'default.html' :
                false)));
        
        self::$content = self::loadView($file);

		return Plugins::filter('onViewGetContent', self::$content);
	}
		
	private static function getHead() {
		$head = array(
            'charset'     => '<meta charset="utf-8">',
            'title'       => '<title>' . Plugins::filter('onViewGetHeadTitle', 'Nymbly PHP') . '</title>',
            'description' => '<meta name="description" content="' . Plugins::filter('onViewGetHeadDescription', 'Nymbly PHP Framework') . '">'
        );
        
        $styles = self::getHeadStyles();
        $scripts = self::getHeadScripts();
        foreach($styles as $style) {
            $head[] = $style;
        }
        $head[] = self::getJSVars();
        foreach($scripts as $script) {
            $head[] = $script;
        }
		$head = Plugins::filter('onViewGetHead', $head);
        return implode("", $head);
	}
	
	private static function getHeadStyles() {
		
		$styles = array();
		
		// All styles are defined, allow plugins to alter the full list of styles to be loaded and append/prepend any others
		$styles = Plugins::filter('onViewGetHeadStyles', $styles);
		
        // Load main stylesheet after all others
        $styles[] = SITE_CUR_THEME . DS . 'css/styles.css';
        
        // Remove dups
        $styles = array_unique($styles);
        
		if(is_array($styles)) 
			return array_map(create_function('$a', 'return \'<link type="text/css" rel="stylesheet" href="\' . $a . \'" />\';'), $styles);
		else
			return array();
	}
    
    private static function getJSVars() {
        
        self::$vars['JS'] = isset(self::$vars['JS']) && count(self::$vars['JS']) ? array_merge(self::$vars['global'], self::$vars['JS']) : self::$vars['global'];
        
        if(!count(self::$vars['JS'])) return '';
        $vars = 'var ';
        $counter = 1;
        foreach(self::$vars['JS'] as $k => $v) {
            $vars .= "$k = '$v'";
            if($counter == count(self::$vars['JS'])) {
                $vars .= ';';
            } else {
                $vars .= ', ';
            }
            $counter++;
        }
        return '<script>' . $vars . '</script>';
    }
	
	private static function getHeadScripts() {
		$scripts = array();
		
		// Load core styles, main.css, and controller specific styles
        $scripts[] = SITE_CUR_THEME . DS . 'js/scripts.js';
		
		// Allow plugins to alter the full list of scripts to be loaded and append.prepend any more
		$scripts = Plugins::filter('onViewGetHeadScripts', $scripts);
		
        // Remove dups
        $scripts = array_unique($scripts);
        
		if(is_array($scripts)) 
			return array_map(create_function('$a', 'return \'<script type="text/javascript" src="\' . $a . \'"></script>\';'), $scripts);
		else
			return array();
	}
    
    private static function getFooter() {
        
        $content = Plugins::filter('onViewGetFooter', '');
        
        $content .= self::getFooterScripts();    
            
        return $content;
    }
	
	private static function getFooterScripts() {
		
        $scripts = array();
        
        $scripts = Plugins::filter('onViewGetFooterScripts', $scripts);
		
        // Remove dups
        $scripts = array_unique($scripts);
        
        if(is_array($scripts)) 
			$ret = implode("", array_map(function($a) {
                return '<script src="' . $a . '"></script>';
            }, $scripts));
		else
			$ret = "";
        
        return $ret;
	}
    
    private static function loadTemplate($file) {
        return self::loadFile($file, 'Template');
    }
    
    private static function loadView($file) {
        return self::loadFile($file, 'View');
    }
    
    private static function loadFile($file, $type='View') {
        if($file) {
            ob_start();
            // Extract global vars for every file
            extract(self::$vars['global']);
            // Extract controller vars if there are any
            if(isset(self::$vars[SEF::getRoute()])) extract(self::$vars[SEF::getRoute()]);
            require $file;
            // Compact extracted vars
            compact(self::$vars['global']);
            if(isset(self::$vars[SEF::getRoute()])) compact(self::$vars[SEF::getRoute()]);
            // Get file into variable
            $template = ob_get_clean();
            // Run loaded file through filter
            $template = Plugins::filter('onViewLoad' . $type, $template, $file);
        } else {
            trigger_error(sprintf(App::_('', "No template file was found for controllers %s/%s"), SEF::getOption(), SEF::getTask()));
            return '';
        }
        return $template;
    }
    
    public static function getTemplate() {
        return self::$template;
    }
    
    public static function setTemplate($file) {
        return self::$template = $file;
    }
	
    private static function parseTemplate($template) {
        $template = preg_replace_callback('/(?:\<code\>|\{)?\{(.+?)\}(?:\<\/code\>|\})?/', array('View', 'parseMatch'), $template);
        return Plugins::filter('onViewParseTemplate', $template);
    }
    
    private static function parseMatch($match) {

        $start = substr($match[1], 0, 1);
        
        // If it's contained in code block, ignore
        if(substr($match[0], 0, 6) == '<code>') {
            return $match[0];
        } elseif(substr($match[0], 0, 2) == '{{') {
            return str_replace(array('{{', '}}'), array('{', '}'), $match[0]);
        }
        
        // Is it a varaible
        if($start == '$') {
            $key = substr($match[1], 1);
            if(isset(self::$vars[$key])) return self::$vars[$key];
            return $match[1];
        }
        
        // Is it a letter
        if(ctype_alpha($start)) {
            return call_user_func_array(array('App', 'executeControllers'), explode('.', $match[1]));
        }
        
        // Is it a string to translate
        if($start == "'" || $start == '"') {
            $text = substr(substr($match[1], 1), 0, -1);
            return App::_('controllers.' . SEF::getOption(), $text);
        }
        
        // If its a url
        if($start == "/") {
            return SEF::getBaseURL() . $match[1];
        }
        
    }
    
    /**
     * Imports a controller into the current view being loaded
     * 
     * @param String $route The route of the controller to import
     */
    private static function import($route) {
        self::$activeImport = true;
        echo call_user_func_array(array('App', 'executeControllers'), explode('.', $route));
        self::$activeImport = false;
    }
    
    /**
     * Returns a url prepended with the current projects base path
     */
    public static function url($path='') {
        if(strpos($path, '/') !== 0) $path = '/' . $path;
        return SEF::getBaseURL() . $path;
    }
	
    /**
     * Get the image url for the current theme
     */
    public static function image($file) {
        return SITE_CUR_THEME . '/images/' . $file;
    }
    
    /**
     * Assign vars to the view
     */
	public static function assign($var, $val=null, $namespace='global', $requestTypes=false) {
        if(is_array($var)) {
            // Assign each var
            foreach($var as $k => $v) {
                self::assign($k, $v, $namespace, $requestTypes);
            }
        } else {
            // Only assign var for specified request type if is in array
            if(is_array($requestTypes) && !in_array(App::get('method', 'request'), $requestTypes)) return;
            // Capitalize all global variables
            if($namespace == 'global' && !is_array($var)) $var = strtoupper($var);
            // Assign the var
            self::$vars[$namespace][$var] = $val;
        }
	}
	
}

?>