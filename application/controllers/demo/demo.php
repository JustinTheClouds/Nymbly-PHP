<?php

defined('IN_APP') ? NULL : exit();

class Option_demo extends Controller {
    
    private static $demoTemplateDisplayed = false;
    
    public function _setTitle() {
        return 'Demoing Nymbly PHP';
    }
	
	public function _execute() {
		
        self::assign('testing', 'some test');
        if(!App::isDeveloper()) return false;
		
	}
    
    public static function onViewLoadView($content) {
        if(!self::$demoTemplateDisplayed) {
            self::$demoTemplateDisplayed = true;
            return App::executeControllers('demo', 'layouts', 'header') . App::executeControllers('demo', 'layouts', 'nav') . $content . App::executeControllers('demo', 'layouts', 'footer');
        }
        return $content;
    }
	
}

?>
