<?php 

if(!defined('DO_ACTION')) exit;

$error = 0;

$pk_nr = trim(GETPOST('pk_nr'));

$res = $object->setValueFrom('pk_nr', $pk_nr);
if (!($res > 0)) {
	$error++;
}

elitasoft_action_result(!$error);
header('Location: '.DOL_URL_ROOT.'/elitasoft/dpu/card.php?id='.$object->id);
exit;