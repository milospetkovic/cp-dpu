<?php 

if(!defined("DO_ACTION")) exit;

// Define output language
$outputlangs = $langs;
if (! empty($conf->global->MAIN_MULTILANGS)) {
	$outputlangs = new Translate("", $conf);
	$newlang = (GETPOST('lang_id') ? GETPOST('lang_id') : $object->thirdparty->default_lang);
	$outputlangs->setDefaultLang($newlang);
}

$model = 'generic_order_odt:/var/www/html/cp/trunk/documents/doctemplates/orders/template_order.odt';

$result = $object->generateDocument($model, $outputlangs, 0, 0, 0 );

if ($result <= 0)
{
	setEventMessages($object->error, $object->errors, 'errors');
	$action='';
}


elitasoft_action_result($result > 0);
header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
exit();