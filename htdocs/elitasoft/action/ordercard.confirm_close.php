<?php 

if(!defined('DO_ACTION')) exit;

require_once DOL_DOCUMENT_ROOT.'/elitasoft/class/elita.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/elitasoft/lib/elitasoft.common.lib.php';

$error = 0;

if (! $error) 
{	
	$elitaOrder = new ElitaCommande($db);
	
	$idwarehouse = $conf->global->ELITASOFT_CENTRAL_WAREHOUSE;
	
	// Check parameters
	if (!($idwarehouse > 0))
	{
		$error++;
		setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentitiesnoconv("Warehouse")), null, 'errors');
		$action='';
	}

	// close order (validate and close)
	if (!$error) {
		$result = $elitaOrder->closeOrder($object, $user, $idwarehouse, $notrigger=0);
	}
	
	if ($result >= 0 && !$error)
	{
		// Define output language
		if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
		{
			$outputlangs = $langs;
			$newlang = '';
			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id')) $newlang = GETPOST('lang_id','alpha');
			if ($conf->global->MAIN_MULTILANGS && empty($newlang))	$newlang = $object->thirdparty->default_lang;
			if (! empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
			}
			$model=$object->modelpdf;
			$ret = $object->fetch($id); // Reload to get new records

			$object->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);
		}
	}
}

// iterrate throw errors (if errors occur during executing an action)
if (count($object->errors) > 0) {
	foreach($object->errors as $error_msg) {
		setEventMessage($error_msg, 'errors');
	}
}

// result message
elitasoft_action_result($result > 0);

// redirect
header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$object->id);
exit;