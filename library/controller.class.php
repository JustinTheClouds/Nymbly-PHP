<?php

defined('IN_APP') ? NULL : exit();

class Controller {
	
    public function __construct() {
        App::addTranslation('controller', SEF::getOption());
    }
    
    protected static function _() {
        $args = func_get_args();
        array_unshift($args, 'controllers.' . SEF::getOption());
        return call_user_func_array(array('App', '_'), $args);
    }
    
    protected static function assign($var, $val=null) {
        View::assign($var, $val, SEF::getRoute());
    }
		
}

?>
