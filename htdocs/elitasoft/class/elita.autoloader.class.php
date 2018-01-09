<?php 

class ElitaAutoloader {
	
	static $classes;
	
	static function init() {
		if(!empty(self::$classes)) {
			return;
		}
		self::get_classes();
		spl_autoload_register(array('ElitaAutoloader','autoload'));
	}
	
	static function autoload($class_name) {
		
		//Autoload classes with namespace
		$class_namespace="ElitaClass";
		if(substr($class_name, 0, strlen($class_namespace)) == $class_namespace) {
			$file = DOL_DOCUMENT_ROOT . "/elitasoft/class" . str_replace("\\", "/", substr($class_name, strlen($class_namespace))).".php";
			if(file_exists($file)) {
				require_once $file;
				return true;
			}
		}
		
		
		if(isset(self::$classes[$class_name])) {
			$path = self::$classes[$class_name];
			// check for overriden class
			$override_path = str_replace(DOL_DOCUMENT_ROOT, "", $path);
			$override_path = DOL_DOCUMENT_ROOT . "/elitasoft/override".$override_path;
			if(file_exists($override_path)) {
				require_once $override_path;
				return true;
			} else if (file_exists($path)) {
				require_once $path;
				return true;
			}
		} else {
			return false;
		}
	}
	
	static function get_classes() {
		self::$classes=array();
		$folder=DOL_DATA_ROOT."/elitasoft/temp";
		if(!file_exists($folder)) return;
		$file=$folder."/autoload_classes.php";
		if(file_exists($file)) {
			require $file;
		}
		self::$classes = $classes;
	}
	
	
}