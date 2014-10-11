<?php

defined('IN_APP') ? NULL : exit();

class Main {
    
    /**
     * Application run time configuration settings
     */
    public static $configs = array(
        /**
         * The name of your app
         * 
         * Not a required config, but can be helful nonetheless
         */
        'appName'    => 'Nymbly PHP',
        /**
         * This is primarily used for the admin bar plugin but can be used in others
         * ways too. By calling App::isDeveloper() you can check if the current
         * user is listed as a developer IP. This is how the admin bar plugin determines
         * whether or not to show itself.
         */
		'developers' => array('127.0.0.1'),
        /**
         * Some websites may pass some functionality control on to non developer
         * personel. The App::isAdmin() can be used to allow controll to specific controllers
         * that should not be available publicly. For example an edit users controller.
         * 
         * Note: Developer IPs will also return true when App::isAdmin() is called.
         */
		'admins'	 => array(),
        /**
         * The default language the framework is using in all translation method calls.
         * 
         * App::_('This text is written in en-US');
         */
        'lang'       => 'en-US',
        /**
         * Routes let you define which controllers should run for specfic urls.
         * They are not required for every url. These are useful for unique urls.
         */
        'routes'     => array(
            // The url http://yourdomain.com/some-unique-url-slug will run controllers demo/routes
            // 'some-unique-url-slug' => 'demo/routes'
        ),
        /**
         * Plugins can be enabled/disabled and configured here.
         * Plugin authors may define additional configuration options.
         */
        'plugins'    => array(
            'admin_bar' => true
        )
	);
    
    public function __construct() {
        
        // Assign app name var on all controllers
        View::assign('appName', self::$configs['appName']);

    }
    
    /**
     * Sample set title hook. This, as well as all hooks can be used here, 
     * inside of plugin classes, and inside of controllers classes.
     * 
     * This method separates the current url into word and uppercases each part.
     * Then add the app name to the end of the title divided by a |.
     * 
     * ex. www.mywebsite.com/some/page will be "Some Page | My Website"
     */
    public static function onViewGetHeadTitle($title) {
        
        return (!empty(SEF::getRoute()) ? ucwords(str_replace('/', ' ', SEF::getRoute())) . ' | ' : '') . self::$configs['appName'];
        
    }
    
}

?>