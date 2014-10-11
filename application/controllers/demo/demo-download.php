<?php

defined('IN_APP') ? NULL : exit();

class Task_download extends Controller {
	
	public function _execute() {
        
        $codes = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $langs = array();
        foreach($codes as $code) {
            // Grab lang code
            $langs[] = (strpos($code, ';') !== false) ? substr($code, 0, strpos($code, ';')) : $code;
        }
        
        self::assign('langs', $langs);
		
	}
    
    public function _post($object) {
        
        $file = 'https://github.com/JustinTheClouds/Nymbly-PHP/blob/master/Nymbly-Project.zip?raw=true';
        
        $dir = DIR_ROOT.DS.'tmp';
            
        // Check for tmp dir
        if(!is_dir($dir)) mkdir($dir);
        
        // Create a tempfile for custom generated zip
        $tempFile = tempnam($dir, 'NYMBLY');
        
        copy($file, $tempFile);
        
        // Grab latest master framework repo
        //file_put_contents($tempFile, file_get_contents("zip://$tempFile"));
        //file_put_contents($tempFile, file_get_contents($file));
        
        $zip = new ZipArchive();
        if($zip->open($tempFile)) {
            
            // TODO Download and add selected plugins
            
            // Install admin bar plugin
            $adminBar = tempnam($dir, 'NYMBLY');
            file_put_contents($adminBar, file_get_contents('https://github.com/JustinTheClouds/Nymbly-PHP-Plugins/blob/master/admin_bar/admin_bar.zip?raw=true'));
            $adminBarZip = new ZipArchive();
            if($adminBarZip->open($adminBar)) {
                // Loop each file in archive
                for($i = 0; $i < $adminBarZip->numFiles; $i++) {
                    $info = pathinfo($adminBarZip->getNameIndex($i));
                    // Is this is a file
                    if(isset($info['extension'])) {
                        // Add file to downloaded zip
                        $zip->addFromString('Nymbly-Project/application/plugins/' . $adminBarZip->getNameIndex($i), $adminBarZip->getFromIndex($i));
                    }
                }
            }
            
            // Build htaccess file
            $htaccess = str_replace('NymblyPHP', App::get('post.local_path', 'request', '/'), $zip->getFromName('Nymbly-Project/.htaccess'));
            $zip->addFromString('Nymbly-Project/.htaccess', $htaccess);
            
            // Build main app file
            $mainApp = str_replace('Nymbly PHP', App::get('post.app_name', 'request', 'My New App'), $zip->getFromName('Nymbly-Project/application/index.php'));
            $zip->addFromString('Nymbly-Project/application/index.php', $mainApp);
            
            // Close the zip
            $zip->close();
            
            // Download the zip file
            header('Content-Type: "application/octet-stream"');
            header("Content-Disposition: attachment; filename=Nymbly-PHP.zip");
            header("Content-Transfer-Encoding: binary");
            header("Content-length: " . filesize($tempFile));
            header("Pragma: no-cache"); 
            header("Expires: 0"); 
            readfile($tempFile);
            
        } else {
            
        }
        
        // Delete temp file
        unlink($tempFile);
        
        // Delete tmp dir if empty
        if(count(scandir($dir)) == 2) rmdir($dir);
        
        exit();
        
    }
    
}

?>
