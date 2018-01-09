<?php 

class ActionsElitasoft {
	
	function __construct($db)
	{
		$this->db = $db;
	}
	
	/**
	 * Form confirm
	 *
	 */
	public function formConfirm($parameters, &$object, &$action, $hookmanager) 
	{
		global $db, $conf, $langs, $form, $user;
		
		$langs->load('common@elitasoft');
		
		// get current context
		$context = $parameters['currentcontext'];
		
		// customer order
		if ($context=='ordercard')
		{
			if ($action=="close") {
				$this->resprints = '';
				$this->resprints = $form->formconfirm($_SERVER["PHP_SELF"].'?id=' . $object->id, $langs->trans('CloseOrderTitle'), $langs->trans('ClosingOrderAskWithInfo'), 'confirm_close', '', 0, 1);
				return 1;
			}
		}
		
		// supplier order
		if ($context=='ordersuppliercard')
		{
			if ($action=="close") {
				$this->resprints = '';
				$this->resprints = $form->formconfirm($_SERVER["PHP_SELF"].'?id=' . $object->id, $langs->trans('CloseSupplierOrderTitle'), $langs->trans('ClosingSupplierOrderAskWithInfo'), 'confirm_close', '', 0, 1);
				return 1;
			}
			elseif ($action=="ask_reopen") {
				$this->resprints = '';
				$this->resprints = $form->formconfirm($_SERVER["PHP_SELF"].'?id=' . $object->id, $langs->trans('ReopenSupplierOrderTitle'), $langs->trans('ReopeningSupplierOrderAskWithInfo'), 'confirm_reopen', '', 0, 1);
				return 1;
			}
		}
		
		return 0;
	}
	
	/**
	 *	Add buttons for objects
	 *
	 * @return number
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) 
	{	
		global $db, $conf, $langs, $user;
		
		// get current context
		$context = $parameters['currentcontext'];
		
		// customer order
		if ($context=='ordercard') 
		{
			// Button for closing
			if ($object->statut == Commande::STATUS_DRAFT && $object->total_ttc >= 0 && count($object->lines) > 0 &&
			   ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->commande->creer))
			   	|| (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->commande->order_advance->validate))))
			{
				print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=close">' . $langs->trans('Close') . '</a></div>';
			}
			
			// Button reopen (set status draft)
			if ($object->statut == Commande::STATUS_CLOSED && $user->rights->commande->creer) {
				print '<div class="inline-block divButAction"><a class="butAction" href="card.php?id=' . $object->id . '&amp;action=modif">' . $langs->trans('Modify') . '</a></div>';
			}
			
			// Clone
			if ($user->rights->commande->creer) {
				print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&amp;socid=' . $object->socid . '&amp;action=clone&amp;object=order">' . $langs->trans("ToClone") . '</a></div>';
			}
			
			// Button delete 
			if ($user->rights->commande->supprimer && $object->statut == Commande::STATUS_DRAFT) 
			{
				print '<div class="inline-block divButAction"><a class="butActionDelete" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=delete">' . $langs->trans('Delete') . '</a></div>';
			}
			
			// return 1 so default buttons will be replaced
			return 1;
		}
		
		// supplier order
		else if ($context=='ordersuppliercard')
		{
			if ($user->societe_id == 0 && $action != 'editline' && $action != 'delete')
			{
				print '<div	 class="tabsAction">';

				// Button for closing
				if ($object->statut==ElitaOrderSupplier::STATUS_DRAFT && 
					count($object->lines) > 0 &&
					$user->rights->fournisseur->commande->creer)
				{
					print '<a class="butAction" href="'.$_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=close">'.$langs->trans('Close') . '</a>';
				}

				// Reopen
				if ($object->statut==ElitaOrderSupplier::STATUS_RECEIVED)
				{
					if ($user->rights->fournisseur->commande->creer)
					{
						print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=ask_reopen">'.$langs->trans("Open").'</a>';
					}
				}

				// Clone
				if ($user->rights->fournisseur->commande->creer)
				{
					print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;socid='.$object->socid.'&amp;action=clone&amp;object=order">'.$langs->trans("ToClone").'</a>';
				}
				
				// Delete
				if ($user->rights->fournisseur->commande->supprimer && $object->statut==ElitaOrderSupplier::STATUS_DRAFT)
				{
					print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=delete">'.$langs->trans("Delete").'</a>';
				}

				print "</div>";
			}
				
			// return 1 so default buttons will be replaced
			return 1;
		}
		
		
		
		return 0;
	}
	
	/**
	 *	Actions
	 *
	 * @return number
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $conf, $langs, $user;
	
		// get current context
		$context = $parameters['currentcontext'];
		
		$action_file = DOL_DOCUMENT_ROOT . "/elitasoft/action/".$context.".".$action.".php";
		if(file_exists($action_file)) {
			define("DO_ACTION",true);
			require_once $action_file;
			return;
		}
		
// 		// customer order
// 		if ($context=='ordercard')
// 		{
// 			// validation of customer order
// 			if ($action=="confirm_validate") 
// 			{	

// 			}
// 		}
		
	}
	
	/**
	 *	AJAX Actions
	 *	 
	 */
	function doAjaxActions($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $user, $langs;
	
		$context=$parameters['currentcontext'];
		parse_str($parameters['formData'],$formData);
	
		$action_file = DOL_DOCUMENT_ROOT . "/elitasoft/action/ajax/".$context.".".$action.".php";
		if(file_exists($action_file)) {
			define("DO_AJAX_ACTION",true);
			require_once $action_file;
			$this->results = $ret;
			return;
		}
	}
}