<?php 

if(!defined("DO_AJAX_ACTION")) exit;

$elitaAjax = new ElitaAjax();

$elitaAjax->start();

$table=$elitaAjax->getParam('t');
$column=$elitaAjax->getParam('c');
$rowid=$elitaAjax->getParam('r');
$value=$elitaAjax->getParam('v');
$reload=$elitaAjax->getParam('reload');
$param=$elitaAjax->getParam('p');

$allowed_tables_and_columns=array(
	'commandedet'=>array('transfered_qty')
);

$process_function = "elb_inplace_process_".$table."_".$column;
if(function_exists($process_function)) {
	$value = call_user_func($process_function, $value);
}

if (in_array($table, array_keys($allowed_tables_and_columns)) && 
	in_array($column, $allowed_tables_and_columns[$table]) && 
	is_numeric($rowid)) 
{
	$res = ElitaCommonManager::updateField($table, $column, $value, $rowid);
	elitasoft_action_result($res);
} 
elseif (in_array($table, array_keys($allowed_tables_and_columns)) && 
		in_array($column, $allowed_tables_and_columns[$table]) && 
		strlen($param) > 0) 
{
	$get_param = json_decode(base64_decode($param), true);
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$table.' (';
	foreach ($get_param as $arr_key=>$arr_val) {
		$sql.= $arr_key.',';
	}
	$sql.= $column.') VALUES ( ';
	foreach ($get_param as $arr_key=>$arr_val) {
		$sql.= '"'.$arr_val.'",';
	}
	$sql.= '"'.$db->escape($value).'")';
	$res = ElitaCommonManager::execute($sql);
	elitasoft_action_result($res);
}

if($reload==1){
	$elitaAjax->addCode('setTimeout(function(){location.reload()},10);');
} else {
	$elitaAjax->showMessages();
}
$ret = $elitaAjax->getResponse();