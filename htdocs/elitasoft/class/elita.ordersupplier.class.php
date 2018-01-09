<?php 

class ElitaOrderSupplier extends CommandeFournisseur 
{	
	const STATUS_DRAFT 		= 0;
	const STATUS_RECEIVED 	= 5;
	
	function __construct($db) {
		$this->db = $db;
	}
	
	/**
	 *	Validate and Close supplier order
	 *
	 *	@param		object				$object     	CommandeFournisseur
	 *	@param		User				$user     		User making status change
	 *	@param		int					$idwarehouse	Id of warehouse to use for stock decrease
	 *  @param		int					$notrigger		1=Does not execute triggers, 0= execuete triggers
	 *	@return  	int					<0 if KO, >0 if OK
	 */
	function closeSupplierOrder($object, $user, $idwarehouse=0, $notrigger=0)
	{
		global $db, $conf, $langs;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	
		$error=0;
	
		// start transaction
		$db->begin();
	
		// validate
		$result = $this->validateSupplierOrder($object, $user, $idwarehouse=0, $notrigger=0);
		if (!($result > 0)) {
			$error++;
		}
		
		// make movements supplier order
		if (!$error) {
			$result = $this->createStockMovementsForCloseAction($object, $user);
			if (!($result > 0)) {
				$error++;
			}
		}
	
		// close supplier order
		if (!$error) {
			$result = $this->setStatusAsClosed($object, $user);
			if (!($result > 0)) {
				$error++;
			}
		}
	
		// close transaction and return info
		if ($error) {
			$db->rollback();
			return -1;
		}
		
		$db->commit();
		return 1;
	}
	
	
	/**
	 *	Validate supplier order
	 *
	 *	@param		object		$object     	CommandeFournisseur
	 *	@param		User		$user     		User making status change
	 *	@param		int			$idwarehouse	Id of warehouse to use for stock decrease
	 *  @param		int			$notrigger		1=Does not execute triggers, 0= execuete triggers
	 *	@return  	int							<0 if KO, >0 if OK
	 */
	function validateSupplierOrder($object, $user, $idwarehouse=0, $notrigger=0)
	{
	    global $db, $conf, $langs, $user;
        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

        $error=0;

        dol_syslog(get_class($this)."::valid");
        $result = 0;
        if ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->fournisseur->commande->creer))
       		|| (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->fournisseur->supplier_order_advance->validate)))
        {
            $this->db->begin();

            // Definition of supplier order numbering model name
            $soc = new Societe($this->db);
            $soc->fetch($object->fourn_id);

            // Check if object has a temporary ref
            if (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref)) // empty should not happened, but when it occurs, the test save life
            {
                $num = $object->getNextNumRef($soc);
            }
            else
			{
                $num = $object->ref;
            }
            $object->newref = $num;

            $sql = 'UPDATE '.MAIN_DB_PREFIX."commande_fournisseur";
            $sql.= " SET ref='".$this->db->escape($num)."',";
            $sql.= " fk_statut = 1,";
            $sql.= " date_valid='".$this->db->idate(dol_now())."',";
            $sql.= " fk_user_valid = ".$user->id;
            $sql.= " WHERE rowid = ".$object->id;
            $sql.= " AND fk_statut = 0";

            $resql=$this->db->query($sql);
            if (! $resql)
            {
                dol_print_error($this->db);
                $error++;
            }

            if (! $error && ! $notrigger)
            {
				// Call trigger
				$result=$this->call_trigger('ORDER_SUPPLIER_VALIDATE',$user);
				if ($result < 0) $error++;
				// End call triggers
            }

            if (! $error)
            {
	            $object->oldref = $object->ref;

                // Rename directory if dir was a temporary ref
                if (preg_match('/^[\(]?PROV/i', $object->ref))
                {
                    // We rename directory ($this->ref = ancienne ref, $num = nouvelle ref)
                    // in order not to lose the attached files
                    $oldref = dol_sanitizeFileName($object->ref);
                    $newref = dol_sanitizeFileName($num);
                    $dirsource = $conf->fournisseur->dir_output.'/commande/'.$oldref;
                    $dirdest = $conf->fournisseur->dir_output.'/commande/'.$newref;
                    if (file_exists($dirsource))
                    {
                        dol_syslog(get_class($this)."::valid rename dir ".$dirsource." into ".$dirdest);

                        if (@rename($dirsource, $dirdest))
                        {
                            dol_syslog("Rename ok");
                            // Rename docs starting with $oldref with $newref
	                        $listoffiles=dol_dir_list($conf->fournisseur->dir_output.'/commande/'.$newref, 'files', 1, '^'.preg_quote($oldref,'/'));
	                        foreach($listoffiles as $fileentry)
	                        {
	                        	$dirsource=$fileentry['name'];
	                        	$dirdest=preg_replace('/^'.preg_quote($oldref,'/').'/',$newref, $dirsource);
	                        	$dirsource=$fileentry['path'].'/'.$dirsource;
	                        	$dirdest=$fileentry['path'].'/'.$dirdest;
	                        	@rename($dirsource, $dirdest);
	                        }
                        }
                    }
                }
            }

            if (! $error)
            {
                $result = 1;
                $object->log($user, 1, time());	// Statut 1
                $object->statut = 1;
                $object->ref = $num;
            }

            if (! $error)
            {
                $this->db->commit();
                return 1;
            }
            else
            {
                $this->db->rollback();
                return -1;
            }
        }
        else
        {
            $this->error='NotAuthorized';
            dol_syslog(get_class($this)."::valid ".$this->error, LOG_ERR);
            return -1;
        }
	}
	
	/**
	 *  Set status of supplier order as closed
	 *
	 *	@param		object	$object     	CommandeFournisseur
	 * 	@param      User		$user       Objet user that close
	 *	@return		int						<0 if KO, >0 if OK
	 */
	function setStatusAsClosed($object, $user)
	{
		global $conf, $langs;
	
		$error=0;
	
		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."commande_fournisseur";
		$sql.= " SET fk_statut = ".self::STATUS_RECEIVED;
		$sql.= " WHERE rowid = ".$object->id;
		
		dol_syslog(get_called_class()."setStatusAsClosed", LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$result = 0;
			$object->statut = self::STATUS_RECEIVED;
			$result=$object->log($user, self::STATUS_RECEIVED, dol_now(), $comment);
			$this->db->commit();
		}
		else
		{
			$this->db->rollback();
			$this->error=$this->db->lasterror();
			$result = -1;
		}
		return $result;
	}
	
	public function reopenSupplierOrder($object, $user, $idwarehouse=0, $notrigger=0) {
		global $conf, $langs;
		
		$error=0;
		
		$this->db->begin();
		
		// reopen supplier order
		$result = $this->setStatusAsDraft($object, $user);
		if (!($result > 0)) {
			$error++;
		}
		
		
		// make movements supplier order
		if (!$error) {
			$result = $this->createStockMovementsForReopenAction($object, $user);
			if (!($result > 0)) {
				$error++;
			}
		}
		
		// close transaction and return info
		if ($error) {
			$this->db->rollback();
			return -1;
		}
		
		$this->db->commit();
		return 1;
	}
	
	public function setStatusAsDraft($object, $user) 
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."commande_fournisseur";
		$sql.= " SET fk_statut = ".self::STATUS_DRAFT;
		$sql.= " WHERE rowid = ".$object->id;
		
		dol_syslog(get_called_class()."setStatusAsDraft", LOG_DEBUG);
		
		$resql=$this->db->query($sql);
		
		if ($resql)
		{
			$result = 0;
			$object->statut = self::STATUS_DRAFT;
			$result=$object->log($user, self::STATUS_DRAFT, dol_now(), $comment);
		}
		else
		{	
			$this->errors[] = $this->db->lasterror();
			$result = -1;
		}
		
		return $result;
	}
	
	public function createStockMovementsForCloseAction($object, $user) 
	{
		global $conf, $langs;
	
		$cpt=count($object->lines);
		if ($cpt > 0) 
		{
			$idwarehouse = $conf->global->ELITASOFT_CENTRAL_WAREHOUSE;
			
			for ($i = 0; $i < $cpt; $i++)
			{
				if ($object->lines[$i]->fk_product > 0)
				{
					$mouvP = new ElitaMouvementStock($this->db);
					$mouvP->origin = &$object;
					
					// We increment stock of product
					$result=$mouvP->_create($user, $object->lines[$i]->fk_product, $idwarehouse, $object->lines[$i]->qty, 0, $object->lines[$i]->subprice, "Povecano stanje zatvaranjem dob. porudzbenice $object->ref");
						
					if ($result < 0) {
						$error++;
						$object->errors[] = $mouvP->error;
						break;
					}
				}
			
				if ($error) break;
			}
		} else {
			$error++;
			$object->errors[] = 'Zatvaranje nije moguce kad ne postoji ni jedna uneta pozicija';
		}
		
		if ($error > 0) {
			return -1;
		}
		
		return 1;
	}
	
	public function createStockMovementsForReopenAction($object, $user)
	{
		global $conf, $langs;
	
		$cpt=count($object->lines);
		if ($cpt > 0)
		{
			$idwarehouse = $conf->global->ELITASOFT_CENTRAL_WAREHOUSE;
				
			for ($i = 0; $i < $cpt; $i++)
			{
				if ($object->lines[$i]->fk_product > 0)
				{
					$mouvP = new ElitaMouvementStock($this->db);
					$mouvP->origin = &$object;
						
					// We decrement stock of product
					$result=$mouvP->_create($user, $object->lines[$i]->fk_product, $idwarehouse, -$object->lines[$i]->qty, 0, $object->lines[$i]->subprice, "Smanjeno stanje otvaranjem prethodno zatvorene dob. porudzbenice $object->ref");
			
					if ($result < 0) {
						$error++;
						$object->errors[] = $mouvP->error;
						break;
					}
				}
				
				if ($error) break;
			}
		} else {
			$error++;
			$object->errors[] = 'Zatvaranje nije moguce kad ne postoji ni jedna uneta pozicija';
		}
	
		if ($error > 0) {
			return -1;
		}
	
		return 1;
	}
	
	/**
	 * Get count of orders by date
	 *
	 * @param 	int	 $date_db_format	Integer format
	 */
	static function returnCountOfSupplyOrdersByDate($date_ts)
	{
		global $db;
		$date_format = date("Y-m-d", $date_ts);
		$sql = " SELECT COUNT(*) cnt ";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur ";
		$sql.= " WHERE date_commande='".$date_format."'";
		$res = ElitaCommonManager::querySingle($sql);
		return $res->cnt;
	}

	/**
	 * Get supply orders ids by date
	 *
	 * @param 	int	 	$date_db_format	Integer format (timestamp)
	 * @return	array	Array of sql result
	 */
	static function getSupplyOrdersByDate($date_ts)
	{
		global $db;
		$date_format = date("Y-m-d", $date_ts);
		$sql = " SELECT rowid ";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur ";
		$sql.= " WHERE date_commande='".$date_format."'";
		return ElitaCommonManager::queryList($sql);
	}
	
	static function isSupplyOrderClosed(CommandeFournisseur $object) {
		return ($object->statut == self::STATUS_RECEIVED);
	}
	
	static function doesSupplierPriceExistForProduct($fk_product, $unit_price, $fk_soc) {
		global $db;
		$sql = " SELECT 1 FROM ".MAIN_DB_PREFIX."product_fournisseur_price ";
		$sql.= " WHERE fk_product=".$db->escape($fk_product);
		$sql.= " AND unitprice=".$db->escape($unit_price);
		$sql.= " AND fk_soc=".$db->escape($fk_soc);
		$sql.= " ORDER BY rowid DESC LIMIT 1";
		return ElitaCommonManager::querySingle($sql);		
	}
	
	static function setDate($id, $date) 
	{
		global $db;
		$sql = "UPDATE ".MAIN_DB_PREFIX."commande_fournisseur";
		$sql.= " SET date_commande = ".$db->idate($date) ;
		$sql.= " WHERE rowid = ".$id;
		return ElitaCommonManager::execute($sql);
	}
	
// 	static function saveProductPriceForSupplier() {
// 		return true;
// 	}
	
}
