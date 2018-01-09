<?php

/**
 *  Class of triggers for demo module
 */
class InterfaceModElitasoft
{
    var $db;
    
    /**
     *   Constructor
     *
     *   @param		DoliDB		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
    
        $this->name = preg_replace('/^Interface/i','',get_class($this));
        $this->family = "elb";
        $this->description = "Custom trigger actions from Elitasoft module";
        $this->version = 'dolibarr';            // 'development', 'experimental', 'dolibarr' or version
        $this->picto = 'technic';
    }
    
    
    /**
     *   Return name of trigger file
     *
     *   @return     string      Name of trigger file
     */
    function getName()
    {
        return $this->name;
    }
    
    /**
     *   Return description of trigger file
     *
     *   @return     string      Description of trigger file
     */
    function getDesc()
    {
        return $this->description;
    }

    /**
     *   Return version of trigger file
     *
     *   @return     string      Version of trigger file
     */
    function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') return $langs->trans("Development");
        elseif ($this->version == 'experimental') return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else return $langs->trans("Unknown");
    }
    
    /**
     *      Function called when a Dolibarrr business event is done.
     *      All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
     *
     *      @param	string		$action		Event action code
     *      @param  Object		$object     Object
     *      @param  User		$user       Object user
     *      @param  Translate	$langs      Object langs
     *      @param  conf		$conf       Object conf
     *      @return int         			<0 if KO, 0 if no triggered ran, >0 if OK
     */
	function run_trigger($action,$object,$user,$langs,$conf)
    {	
    	/* DPU TRIGGERS */
    	if ($action=="ELITA_DPU_CREATE")
    	{
    		global $db, $langs, $user;
    		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
    		
    		$nr_of_orders_by_date = ElitaCommande::returnCountOfOrdersByDate($object->date);
    		
    		$friendly_date_format = dol_print_date($object->date, 'day');
    		
    		if (empty($nr_of_orders_by_date)) {
    			setEventMessage($langs->transnoentities('WarningNoOrdersWithTheSameDateAsDpu', $friendly_date_format), 'warnings');
    		}
    		if ($nr_of_orders_by_date > 1) {
    			setEventMessage($langs->transnoentities('WarningMoreThanOneOrderWithTheSameDateAsDpu', $friendly_date_format), 'warnings');
    		}

    		// save action in calender
    		$res = ElitaCommonObject::saveActionInCalender($object, $action, $notify_comment);
    		 
    		return 1;
    	}
    	
    	/* SUPPLIER ORDER TRIGGERS */
    	elseif ($action==LINEORDER_SUPPLIER_CREATE)
    	{
    		global $db, $langs, $user;
    		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
    		
    		$line_id = $this->db->last_insert_id(MAIN_DB_PREFIX.'commande_fournisseurdet');
    		$line = new CommandeFournisseurLigne($db);
    		$line->fetch($line_id);
    		
    		$doesPriceExist = ElitaOrderSupplier::doesSupplierPriceExistForProduct($line->fk_product, $line->subprice, $object->socid);
    	
    		if (!$doesPriceExist)
    		{
	    		$id_fourn = $object->socid;
	    		$ref_fourn=$line->ref;
	    		$quantity=$line->qty;
	    		$remise_percent=price2num(GETPOST('remise_percent','alpha'));
	    		$npr = preg_match('/\*/', $_POST['tva_tx']) ? 1 : 0 ;
	    		$tva_tx = str_replace('*','', GETPOST('tva_tx','alpha'));
	    		$tva_tx = price2num($tva_tx);
	    		
	    		$delivery_time_days = 0;
	    		
	    		if ($tva_tx == '')
	    		{
	    			$error++;
	    			$langs->load("errors");
	    			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("VATRateForSupplierProduct")), null, 'errors');
	    		}
	    		if (! is_numeric($tva_tx))
	    		{
	    			$error++;
	    			$langs->load("errors");
	    			setEventMessages($langs->trans("ErrorFieldMustBeANumeric",'eeee'), null, 'errors');
	    		}
	    		if (empty($quantity))
	    		{
	    			$error++;
	    			$langs->load("errors");
	    			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Qty")), null, 'errors');
	    		}
	    		if (empty($ref_fourn))
	    		{
	    			$error++;
	    			$langs->load("errors");
	    			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("RefSupplier")), null, 'errors');
	    		}
	    		if ($id_fourn <= 0)
	    		{
	    			$error++;
	    			$langs->load("errors");
	    			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Supplier")), null, 'errors');
	    		}
	    		
	    		if (! $error)
	    		{
	    			$db->begin();
	    		
	    			if (! $error)
	    			{
	    				$productSuppPrice = new ProductFournisseur($db);
	    				$productSuppPrice->fetch($line->fk_product);
	    				
	    				$ret=$productSuppPrice->add_fournisseur($user, $id_fourn, $ref_fourn, $quantity);    // This insert record with no value for price. Values are update later with update_buyprice
	    				if ($ret == -3)
	    				{
	    					$error++;	    		
	    					setEventMessages($langs->trans("ReferenceSupplierIsAlreadyAssociatedWithAProduct",$productLink), null, 'errors');
	    				}
	    				else if ($ret < 0)
	    				{
	    					$error++;
	    					setEventMessages($object->error, $object->errors, 'errors');
	    				}
	    			}
	    		
	    			if (! $error)
	    			{
	    				$supplier=new Fournisseur($db);
	    				$result=$supplier->fetch($id_fourn);
	    		
	    				$ret=$productSuppPrice->update_buyprice($quantity, $line->subprice, $user,'HT', $supplier, 0, $ref_fourn, $tva_tx, 0, $remise_percent, 0, $npr, $delivery_time_days);
	    				if ($ret < 0)
	    				{	    		
	    					$error++;
	    					setEventMessages($object->error, $object->errors, 'errors');
	    				}
	    			}
	    		
	    			if (! $error)
	    			{
	    				setEventMessage('Uneta je cena za dobavljaca', 'mesgs');
	    				$db->commit();
	    			}
	    			else
	    			{
	    				$db->rollback();
	    				return -1;
	    			}
	    		}
    		}
    		return 1;
    	}
    	
    	return 0;
    	                  
    }

}