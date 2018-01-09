<?php 

if(!defined('DO_ACTION')) exit;

$error = 0;

$idwarehouse = GETPOST('idwarehouse');

$qualified_for_stock_change=0;
if (empty($conf->global->STOCK_SUPPORTS_SERVICES))
{
	$qualified_for_stock_change=$object->hasProductsOrServices(2);
}
else
{
	$qualified_for_stock_change=$object->hasProductsOrServices(1);
}

// Check parameters
// if (! empty($conf->stock->enabled) && ! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $qualified_for_stock_change)
// {
// 	if (! $idwarehouse || $idwarehouse == -1)
// 	{
// 		$error++;
// 		setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentitiesnoconv("Warehouse")), null, 'errors');
// 		$action='';
// 	}
// }

if (! $error) 
{	
	$elitaSupplierOrder = new ElitaOrderSupplier($db);
	
	$idwarehouse = $conf->global->ELITASOFT_CENTRAL_WAREHOUSE;
	
	// Check parameters
	if (! empty($conf->stock->enabled) && ! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $qualified_for_stock_change)
	{
		if (! $idwarehouse || $idwarehouse == -1)
		{
			$error++;
			setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentitiesnoconv("Warehouse")), null, 'errors');
			$action='';
		}
	}
	
	// close supplier order (validate and close)
	$result = $elitaSupplierOrder->closeSupplierOrder($object, $user, $idwarehouse=0, $notrigger=0);
	
	if ($result >= 0)
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
	else
	{
		setEventMessages($object->error, $object->errors, 'errors');
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
header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.$object->id);
exit;