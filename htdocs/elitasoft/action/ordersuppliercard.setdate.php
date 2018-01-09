<?php 

if(!defined('DO_ACTION')) exit;

$error = 0;

$date = dol_mktime(0, 0, 0, GETPOST('re_month'), GETPOST('re_day'), GETPOST('re_year'));

if ($date == '') {
	setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Date')), null, 'errors');
	$error++;
}

if (!$error) {
	$result = ElitaOrderSupplier::setDate($object->id, $date);
	if (!$result) {
		$error++;
	}
}

elitasoft_action_result(!$error);
header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.$object->id);
exit;