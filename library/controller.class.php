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
    
    protected static function assign() {
        $args = func_get_args();
        $args[1] = isset($args[1]) ? $args[1] : null;
        $args[2] = SEF::getOption() . '/' . SEF::getTask();
        //array_unshift($args, 'controllers.' . SEF::getOption());
        call_user_func_array(array('View', 'assign'), $args);
        //View::assign($var, $val, SEF::getOption() . '/' . SEF::getTask(), $requestTypes);
    }
		
}

?>
