<?php
/* Copyright (C) 2003-2006	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Marc Barilley / Ocebo	<marc@ocebo.com>
 * Copyright (C) 2005-2015	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2006		Andre Cianfarani		<acianfa@free.fr>
 * Copyright (C) 2010-2013	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2011-2015	Philippe Grand			<philippe.grand@atoo-net.com>
 * Copyright (C) 2012-2013	Christophe Battarel		<christophe.battarel@altairis.fr>
 * Copyright (C) 2012		Marcos García			<marcosgdf@gmail.com>
 * Copyright (C) 2012       Cedric Salvador      	<csalvador@gpcsolutions.fr>
 * Copyright (C) 2013		Florian Henry			<florian.henry@open-concept.pro>
 * Copyright (C) 2014       Ferran Marcet			<fmarcet@2byte.es>
 * Copyright (C) 2015       Jean-François Ferry		<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file htdocs/commande/card.php
 * \ingroup commande
 * \brief Page to show customer order
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formorder.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmargin.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
// Elitasoft
require_once DOL_DOCUMENT_ROOT.'/elitasoft/lib/elitasoft.common.lib.php';
require_once DOL_DOCUMENT_ROOT.'/elitasoft/class/elita.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/elitasoft/class/elita.mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT.'/elitasoft/class/elita.dpu.class.php';
// end elitasoft

$langs->load('orders');
$langs->load('sendings');
$langs->load('companies');
$langs->load('bills');
$langs->load('propal');
$langs->load('deliveries');
$langs->load('sendings');
$langs->load('products');
if (!empty($conf->incoterm->enabled)) $langs->load('incoterm');
if (! empty($conf->margin->enabled))
	$langs->load('margins');

$id = (GETPOST('id', 'int') ? GETPOST('id', 'int') : GETPOST('orderid', 'int'));
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');

$limit = GETPOST("limit")?GETPOST("limit","int"):$conf->liste_limit;
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield="p.ref";
if (! $sortorder) $sortorder="ASC";

$object = new ElitaDPU($db);

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Must be include, not include_once

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('dpucard','globalcard'));


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	if ($cancel) $action='';

	include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php'; 	// Must be include, not include_once

	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';		// Must be include, not include_once

	include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';	// Must be include, not include_once

	// Remove dpu
	if ($action == 'confirm_delete' && $confirm == 'yes')
	{
		$result = $object->delete();
		if ($result > 0)
		{
			elitasoft_action_result(true);
			header('Location: '. DOL_URL_ROOT.'/elitasoft/dpu/list.php');
			exit;
		}
		else
		{
			elitasoft_action_result(false);
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	// Add DPU
	else if ($action == 'add')
	{
		$error = 0;
		
		$db->begin();
		
		$date = dol_mktime(12, 0, 0, GETPOST('datemonth'), GETPOST('dateday'), GETPOST('dateyear'));
		$pk_nr = trim(GETPOST("pk_nr"));

		if ($date == '') {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('Date')), null, 'errors');
			$action = 'create';
			$error++;
		}		

		if (! $error) 
		{
			$object->date = $date;
			$object->pk_nr = $pk_nr;

			$object_id = $object->create();

			// End of object creation, we show it
			if (!($object_id > 0))
			{
				$error++;
			}
		}
		
		if (! $error)
		{
			$db->commit();
			elitasoft_action_result(!$error);
			header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object_id);
			exit();
		}
		
		$db->rollback();
		elitasoft_action_result(!$error);
		$action = 'create';
		setEventMessages($object->error, $object->errors, 'errors');
		
	}

	else if ($action == 'confirm_validate' && $confirm == 'yes' &&
        ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->commande->creer))
       	|| (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->commande->order_advance->validate)))
	)
	{
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
		if (! empty($conf->stock->enabled) && ! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $qualified_for_stock_change)
		{
			if (! $idwarehouse || $idwarehouse == -1)
			{
				$error++;
				setEventMessages($langs->trans('ErrorFieldRequired',$langs->transnoentitiesnoconv("Warehouse")), null, 'errors');
				$action='';
			}
		}

		if (! $error) {
			$result = $object->valid($user, $idwarehouse);
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
	}

	// Remove file in doc form
	if ($action == 'remove_file')
	{
		if ($object->id > 0)
		{
			require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

			$langs->load("other");
			$upload_dir = $conf->commande->dir_output;
			$file = $upload_dir . '/' . GETPOST('file');
			$ret = dol_delete_file($file, 0, 0, 0, $object);
			if ($ret)
				setEventMessages($langs->trans("FileWasRemoved", GETPOST('urlfile')), null, 'mesgs');
			else
				setEventMessages($langs->trans("ErrorFailToDeleteFile", GETPOST('urlfile')), null, 'errors');
			$action = '';
		}
		
		elitasoft_action_result($ret);
		// redirect
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
		exit;
	}

	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';


	/*
	 * Send mail
	 */

	// Actions to send emails
	$actiontypecode='AC_COM';
	$trigger_name='ORDER_SENTBYMAIL';
	$paramname='id';
	$mode='emailfromorder';
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
	
}


/*
 *	View
 */

llxHeader('', $langs->trans('DPU').' - '.$langs->trans('DPUFullName'));

$form = new Form($db);
$formfile = new FormFile($db);
$formorder = new FormOrder($db);
$formmargin = new FormMargin($db);
if (! empty($conf->projet->enabled)) { $formproject = new FormProjets($db); }

/**
 * *******************************************************************
 *
 * Mode creation
 *
 * *******************************************************************
 */
if ($action == 'create')
{
	print load_fiche_titre($langs->trans('CreateDPU').' ('.$langs->trans('DPUFullName').')','','');


	print '<form  action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
	print '<input type="hidden" name="token" value="' . $_SESSION ['newtoken'] . '">';
	print '<input type="hidden" name="action" value="add">';

	dol_fiche_head('');

	print '<table class="border" width="100%">';

	// Date
	print '<tr><td class="fieldrequired">' . $langs->trans('Date') . '</td><td colspan="2">';
	$form->select_date('', 'date', '', '', '', "crea_commande", 1, 1);			// Always autofill date with current date
	print '</td></tr>';
	
	// PK nr
	print '<tr><td>' . $langs->trans('NrOfPK') . '</td><td colspan="2">';
	print '<input type="text" name="pk_nr" value="'.GETPOST('pk_nr').'">';
	print '</td></tr>';

	// Template to use by default
// 	print '<tr><td>' . $langs->trans('Model') . '</td>';
// 	print '<td colspan="2">';
// 	include_once DOL_DOCUMENT_ROOT . '/core/modules/commande/modules_commande.php';
// 	$liste = ModelePDFCommandes::liste_modeles($db);
// 	print $form->selectarray('model', $liste, $conf->global->COMMANDE_ADDON_PDF);
// 	print "</td></tr>";

	print '</table>';

	dol_fiche_end();

	// Button "Create Draft"
	print '<div class="center"><input type="submit" class="button" name="bouton" value="' . $langs->trans('Create') . '"></div>';

	print '</form>';

} 
else 
{
	/* *************************************************************************** */
	/*                                                                             */
	/* Mode vue et edition                                                         */
	/*                                                                             */
	/* *************************************************************************** */
	$now = dol_now();

	if ($object->id > 0) 
	{
		$product_static = new Product($db);

		$author = new User($db);
		$author->fetch($object->user_id);		

		$head = ElitaDPU::printHeader($object);
		dol_fiche_head($head, 'card', $langs->trans("DPU"), 0, 'card');

		$formconfirm = '';

		/*
		 * Ask delete a DPU which is in draft status
		*/
		if ($action == 'delete') {
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('DeleteDPU'), $langs->trans('ConfirmDeleteDPU'), 'confirm_delete', '', 0, 1);
		}
		
		/*
		 * Ask delete a DPU which is processed
		*/
		elseif ($action == 'ask_delete_processed') {
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('DeleteProcessedDPU'), $langs->trans('ConfirmDeleteProcessedDPU'), 'confirm_delete', '', 0, 1);
		}
		
		// ask processing DPU
		elseif ($action == 'ask_process') 
		{
			// get nr of orders by date
			$nr_of_orders_by_date = ElitaCommande::returnCountOfOrdersByDate($object->date);
			$friendly_date_format = dol_print_date($object->date, 'day');
			if (empty($nr_of_orders_by_date)) {
				setEventMessage($langs->transnoentities('WarningNoOrdersWithTheSameDateAsDpu', $friendly_date_format), 'warnings');
			}
			if ($nr_of_orders_by_date > 1) {
				setEventMessage($langs->transnoentities('WarningMoreThanOneOrderWithTheSameDateAsDpu', $friendly_date_format), 'warnings');
			}			
			// get nr of supply orders
			$nr_of_supp_orders_by_date = ElitaOrderSupplier::returnCountOfSupplyOrdersByDate($object->date);
			if (empty($nr_of_orders_by_date)) {
				setEventMessage($langs->transnoentities('WarningNoSupplyOrdersWithTheSameDateAsDpu', $friendly_date_format), 'warnings');
			}
						
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ProcessDPU'), $langs->trans('ConfirmProcessDPU'), 'confirm_process', '', 0, 1);
		}

		// Print form confirm
		print $formconfirm;

		print '<table class="border" width="100%">';

		$linkback = '<a href="' . DOL_URL_ROOT . '/elitasoft/dpu/list.php">' . $langs->trans("BackToList") . '</a>';
		
		// Name
		print '<tr>';
		print '<td width="18%">'.$langs->trans('Name').'</td>';
		print '<td colspan="3">'.$langs->trans('DPU').' - '.$langs->trans('DPUFullName').'</td>';

		// Date
		print '<tr><td class="fieldrequired">';
		print '<table class="nobordernopadding" width="100%"><tr><td>';
		print $langs->trans('Date');
		print '</td>';
		if ($action != 'editdate' && $object->brouillon)
			print '<td align="right"><a href="' . $_SERVER["PHP_SELF"] . '?action=editdate&amp;id=' . $object->id . '">' . img_edit($langs->trans('SetDate'), 1) . '</a></td>';
		print '</tr></table>';
		print '</td><td colspan="3">';
		if ($action == 'editdate') {
			print '<form name="setdate" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="post">';
			print '<input type="hidden" name="token" value="' . $_SESSION ['newtoken'] . '">';
			print '<input type="hidden" name="action" value="setdate">';
			$form->select_date($object->date, 'order_', '', '', '', "setdate");
			print '<input type="submit" class="button" value="' . $langs->trans('Modify') . '">';
			print '</form>';
		} else {
			print $object->date ? dol_print_date($object->date, 'daytext') : '&nbsp;';
		}
		print '</td>';
		print '</tr>';
		
		// NrOfPK
		print '<tr><td>';
		print '<table class="nobordernopadding" width="100%"><tr><td class="nowrap">';
		print $langs->trans('NrOfPK') . '</td><td align="left">';
		print '</td>';
		if ($action != 'editpknr') {
			print '<td align="right"><a href="' . $_SERVER['PHP_SELF'] . '?action=editpknr&amp;id=' . $object->id . '">' . img_edit($langs->trans('Modify')) . '</a></td>';
		}
		print '</tr></table>';
		print '</td><td colspan="3">';
		if ($user->rights->commande->creer && $action == 'editpknr') {
			print '<form action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" method="post">';
			print '<input type="hidden" name="token" value="' . $_SESSION ['newtoken'] . '">';
			print '<input type="hidden" name="action" value="setpknr">';
			print '<input type="text" class="flat" size="20" name="pk_nr" value="' . $object->pk_nr . '">';
			print ' <input type="submit" class="button" value="' . $langs->trans('Modify') . '">';
			print ' <a class="button butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans('Cancel').'</a>';
			print '</form>';
		} else {
			print $object->pk_nr;
		}
		print '</td>';
		print '</tr>';		
		
		// Status
		print '<tr>';
		print '<td>'.$langs->trans('Status').'</td>';
		print '<td colspan="3">'.$object->getLibStatus($object->status, true).'</td>';
		print '</tr>';

		print '</table><br>';
		print "\n";

		/*
		 * Lines
		 */
		//$result = $object->getLinesArray();

// 		print '	<form name="addproduct" id="addproduct" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . (($action != 'editline') ? '#add' : '#line_' . GETPOST('lineid')) . '" method="POST">
// 		<input type="hidden" name="token" value="' . $_SESSION ['newtoken'] . '">
// 		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline') . '">
// 		<input type="hidden" name="mode" value="">
// 		<input type="hidden" name="id" value="' . $object->id . '">
// 		';

// 		if (! empty($conf->use_javascript_ajax) && $object->statut == ElitaDPU::STATUS_DRAFT) {
// 			include DOL_DOCUMENT_ROOT . '/core/tpl/ajaxrow.tpl.php';
// 		}
// 		print "</form>\n";

		dol_fiche_end();
		
		// print generated lines
		if ($object->status == ElitaDPU::STATUS_PROCESSED)
		{
			include_once DOL_DOCUMENT_ROOT.'/elitasoft/tpl/dpu/lines.tpl.php';				
		}

		/*
		 * Boutons actions
		*/
		if ($action != 'presend' && $action != 'editline') 
		{
			print '<div class="tabsAction">';

		    // Close
			if ($object->status == ElitaDPU::STATUS_DRAFT)
			{
				print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=ask_process">' . $langs->trans('Process') . '</a></div>';
			}
			
			// Delete draft DPU
			if ($object->status == ElitaDPU::STATUS_DRAFT) {
				print '<div class="inline-block divButAction"><a class="butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=delete">' . $langs->trans('Delete') . '</a></div>';
			}
			
			// Delete processed DPU
			if ($object->status == ElitaDPU::STATUS_PROCESSED) {
				print '<div class="inline-block divButAction"><a class="butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=ask_delete_processed">' . $langs->trans('Delete') . '</a></div>';
			}
			
			print '</div>';
		}

		if ($object->status == ElitaDPU::STATUS_PROCESSED)
		{
			print '<div class="fichecenter"><div class="fichehalfleft">';

			/*
			 * Documents generes
			*/
			$comref = dol_sanitizeFileName(ElitaDPU::returnSubDirForDPUsGeneratedDocs($object->date));
			$file = $conf->commande->dir_output . '/' . $comref . '/' . $comref . '.pdf';
			$relativepath = $comref . '/' . $comref . '.pdf';
			$filedir = $conf->commande->dir_output . '/' . $comref;
			$urlsource = $_SERVER["PHP_SELF"] . "?id=" . $object->id;
			$genallowed = $user->rights->commande->creer;
			$delallowed = $user->rights->commande->supprimer;
			$somethingshown = $formfile->show_documents('commande', $comref, $filedir, $urlsource, $genallowed, $delallowed, $object->modelpdf, 1, 0, 0, 28, 0, '', '', '', $soc->default_lang);

			// Linked object block
			$somethingshown = $form->showLinkedObjectBlock($object);

			print '</div><div class="fichehalfright"><div class="ficheaddleft">';
			// print '</td><td valign="top" width="50%">';

			// List of actions on element
			include_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
			$formactions = new FormActions($db);
			$somethingshown = $formactions->showactions($object, 'order', $socid);

			// print '</td></tr></table>';
			print '</div></div></div>';
		}

	}
}

llxFooter();
$db->close();