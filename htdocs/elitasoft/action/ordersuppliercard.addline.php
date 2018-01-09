<?php 

if(!defined('DO_ACTION')) exit;

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/elitasoft/class/elita.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/elitasoft/lib/elitasoft.common.lib.php';

$error = 0;

// start transaction
$db->begin();

/*
 * Add a line into product
 */
if ($user->rights->fournisseur->commande->creer)
{    
	$predef='';
	$product_desc=(GETPOST('dp_desc')?GETPOST('dp_desc'):'');
	$idprod=GETPOST('idprod', 'int');
	$price_ht = price2num(GETPOST('price_ht'));
	$tva_tx = GETPOST('tva_tx', 'int');
	$qty = price2num(GETPOST('qty'.$predef));
	$remise_percent=GETPOST('remise_percent'.$predef);

    // Extrafields
    $extrafieldsline = new ExtraFields($db);
    $extralabelsline = $extrafieldsline->fetch_name_optionals_label($object->table_element_line);
    $array_options = $extrafieldsline->getOptionalsFromPost($extralabelsline, $predef);
    // Unset extrafield
    if (is_array($extralabelsline)) {
    	// Get extra fields
    	foreach ($extralabelsline as $key => $value) {
    		unset($_POST["options_" . $key]);
    	}
    }

    if (!($price_ht > 0)) 
    {
        setEventMessages($langs->trans($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('UnitPrice'))), null, 'errors');
        $error++;
    }
    if (!($qty > 0))
    {
        setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Qty')), 'errors');
        $error++;
    }
    
    if (!($idprod > 0))
    {
    	// Product not selected
    	$error++;
    	$langs->load("errors");
    	setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ProductOrService")), 'errors');
    }

    if (!($error))
    {
	    $productsupplier = new ProductFournisseur($db);

   		$res=$productsupplier->fetch($idprod);
   		$label = $productsupplier->label;
   		$desc = $productsupplier->description;
   		if (trim($product_desc) != trim($desc)) $desc = dol_concatdesc($desc, $product_desc);

    	$type = $productsupplier->type;
    		
    	//$tva_tx	= get_default_tva($object->thirdparty, $mysoc, $productsupplier->id, GETPOST('idprodfournprice'));
    	$tva_npr = get_default_npr($object->thirdparty, $mysoc, $productsupplier->id, GETPOST('idprodfournprice'));
		if (empty($tva_tx)) $tva_npr=0;
    	$localtax1_tx= get_localtax($tva_tx, 1, $mysoc, $object->thirdparty, $tva_npr);
    	$localtax2_tx= get_localtax($tva_tx, 2, $mysoc, $object->thirdparty, $tva_npr);

    	$result=$object->addline(
    		$desc,
    		$price_ht,
    		$qty,
    		$tva_tx,
    		$localtax1_tx,
    		$localtax2_tx,
    		$idprod,
    		GETPOST('idprodfournprice'),
    		$productsupplier->fourn_ref,
    		$remise_percent,
    		'HT',
    		$pu_ttc,
    		$type,
    		$tva_npr,
    		'',
    		$date_start,
    		$date_end,
    		$array_options,
		    $productsupplier->fk_unit
    	);
    }
	//print "xx".$tva_tx; exit;
	if (! $error && $result > 0)
	{
		$ret=$object->fetch($object->id);    // Reload to get new records

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

	    	$result=$object->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);
	    	if ($result < 0) dol_print_error($db,$result);
	    }
	}
	else
	{
		setEventMessages($object->error, $object->errors, 'errors');
		$error++;
	}
} else {
	$error++;
}
	
// close transaction
(!$error) ? $db->commit() : $db->rollback();	

// result message
elitasoft_action_result($result > 0);

// redirect
header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.$object->id.'#add');
exit;