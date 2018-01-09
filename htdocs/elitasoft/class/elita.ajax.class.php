<?php 

class ElitaAjax {
	
	var $code = '';
	
	function getParam($name) {
		return $_POST['params'][$name];
	}
	
	function getFormData() {
		parse_str($_POST['formData'], $formData);
		return $formData;
	}
	
	function start() {
		$this->code.='$(".jnotify-container").html("");';
	}
	
	function escape($content) {
		$content = str_replace("\t", "", $content);
		$content = str_replace("\r", "\\r", $content);
		$content = str_replace("\n", "\\n", $content);
		$content= str_replace("'","\\'",$content);
		return $content;
	}
	
	function addCode($code) {
		$this->code.=$code;
	}
	
	function getResponse() {
		return array(
				'type'=>'js',
				'code'=> $this->code
		);
	}
	
	function showMessages() {
		foreach($_SESSION['dol_events'] as $type => $msg_list) {
			$style = "ok";
			if($type=="errors") $style="error";
			if($type=="warnings") $style="warning";
			foreach($msg_list as $msg) {
				$this->code.= '$.jnotify("'.dol_escape_js($msg).'",
								"'.($style=="ok" ? 3000 : $style).'",
								'.($style=="ok" ? "false" : "true").',
								{ remove: function (){} } );';
			}
		}
		unset($_SESSION['dol_events']);
	}
	
	function outputJson($object) {
		ob_end_clean();
		header('Content-Type: application/json');
		echo json_encode($object);
		exit; 
	}
	
}