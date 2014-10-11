<?php 

defined('IN_APP') or die();

// TODO Caching for repetitive tasks like, getEnabledPlugins
class Plugins {
	
	private static $initialized = false;
	private static $plugins = array();
    
    public static function get($name, $type='session', $default=null) {
        $namespace = 'plugins.' . self::getPluginName() . ($name === null ? '' : '.' . $name);
        return App::get($namespace, $type, $default);
    }
    
    public static function set($name, $val, $type='session', $timeout=null) {
        $namespace = 'plugins.' . self::getPluginName() . ($name === null ? '' : '.' . $name);
        return App::set($namespace, $val, $type, $timeout);
    }
    
    public static function _unset($name, $type='session') {
        $namespace = 'plugins.' . self::getPluginName() . ($name === null ? '' : '.' . $name);
        return App::_unset($namespace, $type);
    }
    
	public static function loadPlugins() {
		
		// Do we have a plugins directory?
		if(is_dir(DIR_PLUGINS)) {
			
			// Grab enabled plugins
			$plugins = self::getEnabledPlugins();
			
			foreach($plugins as $name => $settings) {
				
				// Does the plugin exist?
				if(file_exists(DIR_PLUGINS.DS.$name.DS.'index.php')) {
					
                    $className = 'Plugin_'.$name;
                    
					require_once(DIR_PLUGINS.DS.$name.DS.'index.php');
					
					self::$plugins[$name] = array(); 
					
					// store the plugins methods
					self::$plugins[$name]['methods'] = get_class_methods($className);
					
					// Load plugin settings
					self::$plugins[$name]['settings'] = is_array($settings) ? $settings : array();
					
                    // Load plugin language
                    App::addTranslation('plugin', $name);
                    
					// Initialize plugin
                    new $className;
                    
                    // Make sure plugin is still registered after init
                    if(self::pluginExists($name)) {
				        
                        Plugins::action('onPluginInit_'.$name, self::$plugins[$name]['settings']);
                        
                        // Run plugin request methods if set
                        if(isset($_REQUEST[$name . '_request']) && method_exists($className, '_' . App::get('method', 'request'))) {
                            call_user_func(array($className, '_' . App::get('method', 'request')), App::get('plugins.' . $name, 'request.' . (App::get('method', 'request') == 'post' ? 'post' : 'get')));
                        }
                    }
				}
			}
		}
		self::$initialized = true;
	}

    public static function _() {
        $args = func_get_args();
        array_unshift($args, 'plugins.' . self::getPluginName());
        return call_user_func_array(array('App', '_'), $args);
    }
    
    /**
     * Automatically load default plugin styles if exist
     * 
     * This method can be overwritten per plugin to load styles however
     * and from whereever you please. External styles as well.
     * 
     * @param <type> $styles Current styles already being loaded
     * 
     * @return <type> List of styles with the current plugin styles added
     */
	protected static function onViewGetHeadStyles($styles) {
        
        $pluginStyles = (array)self::getPluginSettings('styles');
        
        // Do we have any plugin styles
        if(!$pluginStyles) return $styles;
        
        // Create script paths
        array_walk($pluginStyles, function(&$value, $key, $pluginURI) {
            // If not external file
            if(strpos($value, '//') === false) {
                $value = $pluginURI . DS . $value;
            }
        }, self::getPluginURI());
        
        // Merge existing scripts
        $styles = array_merge($styles, $pluginStyles);
		return $styles;		
	}
    
    /**
     * Automatically load default plugin styles if exist
     * 
     * This method can be overwritten per plugin to load styles however
     * and from whereever you please. External styles as well.
     * 
     * @param <type> $styles Current styles already being loaded
     * 
     * @return <type> List of styles with the current plugin styles added
     */
	protected static function onViewGetFooterScripts($scripts) {
        
        $pluginScripts = (array)self::getPluginSettings('scripts');
        
        // Do we have any plugin scripts
        if(!$pluginScripts) return $scripts;
        
        // Create script paths
        array_walk($pluginScripts, function(&$value, $key, $pluginURI) {
            // If not external file
            if(strpos($value, '//') === false) {
                $value = $pluginURI . DS . $value;
            }
        }, self::getPluginURI());
        
        // Merge existing scripts
        $scripts = array_merge($scripts, $pluginScripts);
		return $scripts;		
	}
    
    /**
     * Are the plugins all initialized
     * 
     * @return <bool>
     */
	public static function isInitialized() {
		return self::$initialized;
	}
    
    public static function getPluginName() {
        return str_replace('Plugin_', '', get_called_class());
    }
    
    public static function getPluginURI() {
        return SITE_PLUGINS . DS . self::getPluginName();
    }
    
    public static function getPluginPath() {
        return DIR_PLUGINS . DS . self::getPluginName();
    }
    
    public static function getPluginRequestUrl($type='GET', $jsonpCallback=null) {
        switch(strtolower($type)) {
            case 'get':
            case 'post':
                $type = '';
            break;
            case 'ajax':
            case 'json':
                $type = '&' . strtolower($type) . '_request=1';
            break;
            case 'jsonp':
                $type = '&jsonp_request=1&callback=' . $jsonpCallback;
            break;
        }
        return SEF::getBaseUrl() . '/?' . self::getPluginName() . '_request=1' . $type;
    }
    /**
     * Does the supplied plugin exists
     * 
     * @param <type> $plugin 
     * 
     * @return <bool>
     */
	public static function pluginExists($plugin) {
		return isset(self::$plugins[$plugin]);
	}
    
    /**
     * Returns a plugins verion number x.x.x
     * 
     * If a version file does not exist or is invalid, will return false.
     * 
     * Note: Calling Plugins::getPluginVersion() will always return false. When used
     * within a plugin it must be called as self::getPluginVersion()
     * 
     * @param   String $plugin=null The plugin to get the version of or if called from within a plugin,
     * the current plugins version.
     * 
     * @returns Boolean  Plugins version number x.x.x or false
     */
    public static function getPluginVersion($plugin=null) {
        $plugin = $plugin ? $plugin : self::getPluginName();
        if(file_exists(DIR_PLUGINS.DS.$plugin.DS.'version.json')) {
            $info = json_decode(file_get_contents(DIR_PLUGINS.DS.$plugin.DS.'version.json'), true);
            return $info && $info['version'] ? $info['version'] : false;
        }
        return false;
    }
	
    /**
     * Returns an array of enabled plugins and its configs
     * 
     * A plugin can be disabled a few different ways through your applications
     * config.php file.
     * 
     * 1. Remove the plugin key from the plugins array
     * 2. Set the plugin key => false
     * 3. Add a disabled property to the plugin key and set to false
     */
	public static function getEnabledPlugins() {
        if(!App::get('plugins', 'configs')) return false;
        $plugins = array_filter( App::get('plugins', 'configs', array()), function($val) { 
            return (
                (!is_array($val) && $val === true)
                || 
                (
                    is_array($val) 
                    && (
                        !isset($val['disabled'])
                        || (
                            isset($val['disabled'])
                            &&
                            $val['disabled'] === false
                        )
                    )
                )
            ) ? true : false; 
        });
        return $plugins;
	}
    
    /**
     * Returns all plugins installed in the plugins dir as an assoc array key
     * being the plugin name and value being true or false (enabled or disabled)
     */
    public static function getInstalledPlugins() {
        $plugins = array_flip(array_filter( scandir(DIR_PLUGINS), function($val) {
            return strpos($val, '.') === 0 ? false : true;
        }));
        $enabled = self::getEnabledPlugins();
        foreach($plugins as $name => &$isEnabled) {
            $isEnabled = array_key_exists($name, $enabled);
        }
        return $plugins;
    }
	
	protected static function getPluginSettings($setting=null, $name=null) {
		$name = $name ? $name : str_replace('Plugin_', '', get_called_class());
		if($setting && isset(self::$plugins[$name]['settings'][$setting]))
			return self::$plugins[$name]['settings'][$setting];
		elseif(!$setting)
			return self::$plugins[$name]['settings'];
	}
	
	protected static function setPluginSettings($setting, $value) {
		$name = str_replace('Plugin_', '', get_called_class());
		self::$plugins[$name]['settings'][$setting] = $value;
	}
	
	public static function filter($event, $returned) {
		$args = func_get_args();
		array_splice($args, 1, 0, 'filter');
		return call_user_func_array("self::fireEvent", $args);
	}

	public static function action($event) {
		$args = func_get_args();
		//array_unshift($args, $event, 'action');
        array_splice($args, 1, 0, 'filter');
		call_user_func_array("self::fireEvent", $args);
	}
	
	public static function __callStatic($name, $args) {
		// This allows us to call every plugin event method even if they do not exist
		// ? What is this? return $args[0];
        return;
	}
	
	private static function fireEvent($event, $type, $returned=null) {
	
		// Grab the args
		$args = $origArgs = func_get_args();
					
		// Remove the event name and type from the args
		array_shift($args);	
		array_shift($args);
        
        $ret = null;
        
        // Run app based hooks if they exist
        if(method_exists('Main', $event)) {
            $ret = call_user_func_array(array('Main', $event), $args);
            //if($type == 'filter' && $ret !== NULL)
            if($type == 'filter')
				$args[0] = $returned = $ret;
        }
        
        // Run model based hooks if they exist
        if(method_exists('Model_' . SEF::getOption(), $event)) {
            $ret = call_user_func_array(array('Model_' . SEF::getOption(), $event), $args);
            //if($type == 'filter' && $ret !== NULL)
            if($type == 'filter')
				$args[0] = $returned = $ret;
        }
        
        // Run controller based hooks if they exist
        if(method_exists('Option_' . SEF::getOption(), $event)) {
            $ret = call_user_func_array(array('Option_' . SEF::getOption(), $event), $args);
            //if($type == 'filter' && $ret !== NULL)
            if($type == 'filter')
				$args[0] = $returned = $ret;
        }
        if(method_exists('Task_' . SEF::getTask(), $event)) {
            $ret = call_user_func_array(array('Task_' . SEF::getTask(), $event), $args);
            //if($type == 'filter' && $ret !== NULL)
            if($type == 'filter')
				$args[0] = $returned = $ret;
        }
        
		// Find all plugins with a hook to this event
		foreach(self::$plugins as $name => $plugin) {
            
            // Does this event hook exist for this plugin
            if(method_exists("Plugin_$name", $event)) {

                // Call the plugins hooked method
                $ret = call_user_func_array("Plugin_$name::$event", $args);
                
                // If this is a filter and there is a new $ret, update the returned param for next plugin call
                //if($type == 'filter' && $ret !== NULL)
                if($type == 'filter')
                    $args[0] = $returned = $ret;
                
            }
			
		}
		
		// Fire off the onEventFired event, make sure to prevent and infinite loop
		if($event != 'onEventFired') {
			array_unshift($origArgs, 'onEventFired');
			call_user_func_array("Plugins::action", $origArgs);
		}
        
		// If this is a filter event, returned the filtered data or, null for an action
		return $type == 'filter' ? ($ret !== NULL ? $ret : $returned) : null;
		
	}
	
	protected static function assign() {
        $args = func_get_args();
        // If namespace is set, overwrite it
        $args[2] = 'plugins/' . self::getPluginName();
        call_user_func_array(array('View', 'assign'), $args);
	}
	
	protected static function unRegisterPlugin() {
		unset(self::$plugins[str_replace('Plugin_', '', get_called_class())]);
	}
    
    /**
     * Return text prefixed with the plugins name. This should be used for all
     * classes, aand ids. It will automatically prefix everything with the plugins
     * name to avoid styling conflicts bewtween plugins.
     * 
     * @param   String $text The class/id you want applied
     * @returns String The prefixed class.
     */
    protected static function prefix($text) {
        return str_replace('_', '-', strtolower(self::getPluginName() . '-' . $text));
    }
    
    protected static function inputName($name) {
        return 'plugins[' . self::getPluginName() . '][' . str_replace('.', '][', $name) . ']';
    }
	
}

?>