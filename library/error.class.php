<?php

defined('IN_APP') ? NULL : exit();

class Error extends Exception {
	
	private static $errors = array();
    
    private static $isFatalShutdown = false;

	/**
     * Catch Errors
     * 
     * If is developer	 
     *   If plugins are already loaded, fire off onAppHandleError event
     *   Else default to normal error handling
     * Else
     *   Display no errors	 
     *	 
     * @param <type> $errno 
     * @param <type> $errstr 
     * @param <type> $errfile 
     * @param <type> $errline 
     * 
     * @return <type>
     */
	public static function catchError($a=null,$b=null,$c=null,$d=null) { 
		if(App::isDeveloper()) {
			$args = func_get_args();
			// If no args and there was an error
            $error = error_get_last();
			if($error && empty($args)) {
				// Remove last error for shutdwn function
				$args = array(0, $error['message'], $error['file'], $error['line']);
			}
            call_user_func_array(array('Error', 'handleError'), $args);
		} else {
			ini_set('display_errors', 0);
		}
	}
	
	public static function getErrors() {
		return self::$errors;
	}
    
    public static function shutdownError() {
        $error = error_get_last();
        // Did we shutdown with a fatal error?
        if($error) {
            $args = array($error['type'], $error['message'], $error['file'], $error['line'], true);
            call_user_func_array(array('Error', 'handleError'), $args);
            self::$isFatalShutdown = true;
        }
        self::displayErrors();
    }
	
    /**
     * Handle errors
     * 
     * Handle erros caughts and append them to the errors tab
     * 
     * @param <type> $default 
     * @param <type> $errno 
     * @param <type> $errstr 
     * @param <type> $errfile 
     * @param <type> $errline 
     * 
     * @return <type>
     */
	protected static function handleError($errno, $errstr, $errfile, $errline, $isFatal=false) {
		
		$info = debug_backtrace();
		
		if(!file_exists($errfile)) return false;
		
        $errorData = array();
		$errorData['errorstr'] = $errstr;
		$errorData['errline'] = $errline;
		$errorData['errfile'] = $errfile;
		$errorData['errfatal'] = $isFatal;
		$errorData['errdebug'] = array();
        
        foreach($info as $v) {
			$line = isset($v['line']) ? $v['line']: 'empty';
			$file = isset($v['file']) ? $v['file']: 'empty';
			$errorData['errdebug'][] = "From line $line of $file";
		}
        
        $errorData['erroutput'] = self::generateOutput($errorData);

		self::$errors[] = Plugins::filter('onErrorHandleError', $errorData);
		
		// Is this a fatal error?
		if($errno === 0) {
			return self::handleFatalError();
		} else {
			// This cancels the default error output
			// By returning false, we can cancel the default $returned value if one exists for a filter event
			return false;
		}
	}
    
    public static function generateOutput($errorData) {
        
        $fh = fopen($errorData['errfile'], 'r');
		$error = '<table cellspacing="0" cellpadding="3" class="error-reporting-table" style="width:100%; border:1px solid rgba(0,0,0,.3); background: white; border-top: 1px solid #ff4646; margin-bottom: 5px;">';
        if($errorData['errfatal'] === true) {
            $error .= '<tr style="background: #a20000;"><td style="color: white; font-weight: bold; font-size: 18px;" colspan="2">FATAL</td></tr>';
        }
        $error .= '<tr><td colspan="2">'. $errorData['errorstr'] .'</td></tr>';
        $error .= '<tr><td colspan="2">Line: '. $errorData['errline'] .' of file: '. $errorData['errfile'] .'</td></tr>';
        
		$i=1;
		while ((feof ($fh) === false) ) {
			while ((feof ($fh) === false) && $i<($errorData['errline']-3) ) {
				fgets($fh);
				$i++;
			}
			
			$class = $i == $errorData['errline'] ? "errorLineError" : ""; 
            $rowStyle = "";
			$rowStyle .= $i == $errorData['errline'] ? 'background: #fcc;' : "background: none;"; 
			
			$theData = fgets($fh);
			$error .= '<tr class="errorLine '.$class.'" style="'.$rowStyle.'"><td width="80px" class="errorLineNumber" style="border-bottom: 1px solid rgba(0,0,0,.1); text-align: right;">' . $i . ':</td><td style="border-bottom: 1px solid rgba(0,0,0,.1); padding-left: 6px;"><pre>' . htmlentities($theData) . '</pre></td></tr>';
            if($i == $errorData['errline'] && strpos($theData, 'trigger_error') !== false) {
                $nbSpaces = strlen($theData) - strlen(ltrim($theData));
                $error .= '<tr><td></td><td style="font-weight: bold; background: #fcc; font-size: 16px;"><pre>' . str_pad('Called ' . $errorData['errdebug'][4], $nbSpaces*6 + strlen('Called ' . $errorData['errdebug'][4]), "&nbsp;", STR_PAD_LEFT) . '</pre></td></tr>';
            }
			$i++;
			
			while ((feof ($fh) === false) && $i>($errorData['errline']+3) ) {
				fgets($fh);
				$i++;
			}
		}
		
        $error .= '<tr><td>Backtrace:</td><td>' . implode('<br/>', $errorData['errdebug'])  . '</td></tr>';
        $error .= '</table>';
        
        fclose($fh);
        
        return $error;
    }
    
    public static function displayErrors() {
        
        $errors = self::getErrors();
        
        // Run filter on errors to allow plugins to tie into the erros system
        if(!self::$isFatalShutdown && Plugins::isInitialized()) {
            $errors = Plugins::filter('onErrorDisplayErrors', $errors);
        } elseif(self::$isFatalShutdown) {
            $errors = Plugins::filter('onErrorFatalShutdown', $errors);
        }
        
        if(count($errors)) {
            foreach($errors as $error) {
                echo $error['erroutput'];
            }
        }
    }
		
    /**
     * Handle fatal errors
     * 
     * This cannot handle parse errors but will catch most other
     * fatal errors. The app will still crash but the output will still
     * be nicely formatted helpful error report
     * 
     * @return <type>
     */
	private static function handleFatalError() {
		$errors = '';
		foreach(self::$errors as $error) {
			$errors .= print_r($error, 1);
		}
        echo $errors;
		return $errors;
	}
	
	
}

?>
