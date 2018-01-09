<?php

class ElitaCommande extends Commande 
{	
	/**
	 *	Validate and Close order
	 *
	 *	@param		Commande	$object     	Commande
	 *	@param		User		$user     		User making status change
	 *	@param		int			$idwarehouse	Id of warehouse to use for stock decrease
	 *  @param		int			$notrigger		1=Does not execute triggers, 0= execuete triggers
	 *	@return  	int							<0 if KO, >0 if OK
	 */
	function closeOrder($object, $user, $idwarehouse, $notrigger=0)
	{
		global $db, $conf, $langs;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		
		$error=0;
		
		// start transaction
		$db->begin();
		
		// validate order
		$result = $this->validateOrder($object, $user, $idwarehouse, $notrigger=0);
		if (!($result > 0)) {
			$error++;
		}
		
		// make movements for order on action close
		if (!$error) {
			$result = $this->createStockMovementsForCloseAction($object, $user, $idwarehouse, $notrigger=0);
			if (!($result > 0)) {
				$error++;
			}
		}
		
		// close order
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
	 *	Close order
	 *
	 *	@param		Commande	$object     	Commande
	 *	@param		User		$user     		User making status change
	 *	@param		int			$idwarehouse	Id of warehouse to use for stock decrease
	 *  @param		int			$notrigger		1=Does not execute triggers, 0= execuete triggers
	 *	@return  	int							<0 if KO, >0 if OK
	 */
	function validateOrder($object, $user, $idwarehouse, $notrigger=0)
	{
		global $conf, $langs;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		
		$error=0;
		
		$now=dol_now();
		
		$this->db->begin();
		
		// Definition du nom de module de numerotation de commande
		$soc = new Societe($this->db);
		$soc->fetch($object->socid);
		
		// Class of company linked to order
		$result=$soc->set_as_client();
		
		// Define new ref
		if (! $error && (preg_match('/^[\(]?PROV/i', $object->ref) || empty($object->ref))) // empty should not happened, but when it occurs, the test save life
		{
			$num = $object->getNextNumRef($soc);
		}
		else
		{
			$num = $object->ref;
		}
		$object->newref = $num;
		
		// Validate
		$sql = "UPDATE ".MAIN_DB_PREFIX."commande";
		$sql.= " SET ref = '".$num."',";
		$sql.= " fk_statut = ".self::STATUS_VALIDATED.",";
		$sql.= " date_valid='".$this->db->idate($now)."',";
		$sql.= " fk_user_valid = ".$user->id;
		$sql.= " WHERE rowid = ".$object->id;
		
		dol_syslog(get_class($this)."::valid()", LOG_DEBUG);
		$resql=$this->db->query($sql);
		if (! $resql)
		{
			dol_print_error($this->db);
			$object->error=$this->db->lasterror();
			$error++;
		}
		
		if (! $error && ! $notrigger)
		{
			// Call trigger
			$result=$object->call_trigger('ORDER_VALIDATE',$user);
			if ($result < 0) $error++;
			// End call triggers
		}
		
		if (! $error)
		{
			$object->oldref = $object->ref;
		
			// Rename directory if dir was a temporary ref
			if (preg_match('/^[\(]?PROV/i', $object->ref))
			{
				// On renomme repertoire ($object->ref = ancienne ref, $num = nouvelle ref)
				// in order not to lose the attachments
					$oldref = dol_sanitizeFileName($object->ref);
					$newref = dol_sanitizeFileName($num);
					$dirsource = $conf->commande->dir_output.'/'.$oldref;
					$dirdest = $conf->commande->dir_output.'/'.$newref;
					if (file_exists($dirsource))
					{
					dol_syslog(get_class($this)."::valid() rename dir ".$dirsource." into ".$dirdest);
	
					if (@rename($dirsource, $dirdest))
					{
						dol_syslog("Rename ok");
						// Rename docs starting with $oldref with $newref
						$listoffiles=dol_dir_list($conf->commande->dir_output.'/'.$newref, 'files', 1, '^'.preg_quote($oldref,'/'));
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
	
		// Set new ref and current status
		if (! $error)
		{
			$object->ref = $num;
			$object->statut = self::STATUS_VALIDATED;
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
	
	/**
	 *  Set status of order as closed
	 *  
	 *	@param		Commande	$object     Commande
	 * 	@param      User		$user       Objet user that close
	 *	@return		int						<0 if KO, >0 if OK
	 */
	function setStatusAsClosed($object, $user)
	{
		global $conf, $langs;
	
		$error=0;
	
		if ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->commande->creer))
			|| (! empty($conf->global->MAIN_USE_ADVANCED_PERMS) && ! empty($user->rights->commande->order_advance->validate)))
		{
			$this->db->begin();
	
			$now=dol_now();
	
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande';
			$sql.= ' SET fk_statut = '.self::STATUS_CLOSED.',';
			$sql.= ' fk_user_cloture = '.$user->id.',';
			$sql.= " date_cloture = '".$this->db->idate($now)."'";
			$sql.= ' WHERE rowid = '.$object->id.' AND fk_statut > '.self::STATUS_DRAFT;
	
			if ($this->db->query($sql))
			{
				// Call trigger
				$result=$object->call_trigger('ORDER_CLOSE',$user);
				if ($result < 0) $error++;
				// End call triggers
	
				if (! $error)
				{
					$object->statut=self::STATUS_CLOSED;
	
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
				$object->error=$this->db->lasterror();
	
				$this->db->rollback();
				return -1;
			}
		}
	}
	
	public function createStockMovementsForCloseAction($object, $user, $idwarehouse, $notrigger=0)
	{
		global $conf, $langs;
		
		$error = 0;
	
		$cpt=count($object->lines);
		if ($cpt > 0)
		{		
			for ($i = 0; $i < $cpt; $i++)
			{
				if ($object->lines[$i]->fk_product > 0)
				{
					// fetch product
					$productstatic = new Product($this->db);
					$res = $productstatic->fetch($object->lines[$i]->fk_product);
					if (!($res > 0)) {
						$error++;
						$object->errors[] = "Greska je nastala prilikom hvatanja proizvoda id=".$object->lines[$i]->fk_product;
						break;
					}
					
					// check if product is for sale
					$isProductForSale = ElitaProduct::isProductMarkedForSale($productstatic);
					if (!$isProductForSale) {
						$error++;
						$object->errors[] = "Proizvod sa id=".$object->lines[$i]->fk_product.", sifra proizvoda=".$productstatic->ref.", nije oznacen za prodaju";
						break;
					}
					
					$mouvP = new ElitaMouvementStock($this->db);
					$mouvP->origin = &$object;
				
					// we decrement stock of product
					$result=$mouvP->livraison($user, $object->lines[$i]->fk_product, $idwarehouse, $object->lines[$i]->qty, $object->lines[$i]->subprice, "Promena stanja zaliha nakon zatvaranja porudzbenice $object->ref");
	
					if ($result < 0) {
						$error++;
						$object->errors[] = $mouvP->error;
						break;
					}
				}
				else 
				{
					$error++;
					$object->errors[] = 'Pozicija nema definisan proizvod';
					break;
				}
				
				if ($error) break;
			}
		} 
		else 
		{
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
	static function returnCountOfOrdersByDate($date_ts) 
	{
		global $db;
		$date_format = date("Y-m-d", $date_ts);
		$sql = " SELECT COUNT(*) cnt ";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande ";
		$sql.= " WHERE date_commande='".$date_format."'";
		$res = ElitaCommonManager::querySingle($sql);
		return $res->cnt;
	}
	
	/**
	 * Get order id by order by date
	 *
	 * @param 	int	 $date_db_format	Integer format (timestamp)
	 * @return	int	 ID of order
	 */
	static function returnOrderIDByOrderByDate($date_ts)
	{
		global $db;
		$date_format = date("Y-m-d", $date_ts);
		$sql = " SELECT rowid ";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande ";
		$sql.= " WHERE date_commande='".$date_format."'";
		$res = ElitaCommonManager::querySingle($sql);
		return $res->rowid;
	}
	
	static function returnUnitPriceForProductFromOrderPosition($fk_product, Commande $object)
	{
		if ($fk_product > 0 && count($object->lines) > 0)
		{
			foreach ($object->lines as $line) {
				if ($line->fk_product == $fk_product) {
					return $line->price;
				}
			}
		}
		return null;
	}
	
	static function isOrderClosed($commande) {
		return ($commande->statut == Commande::STATUS_CLOSED);
	}
	
	static function returnSoldQtyForProductFromOrderPosition($fk_product, Commande $object)
	{
		if ($fk_product > 0 && count($object->lines) > 0)
		{
			foreach ($object->lines as $line) {
				if ($line->fk_product == $fk_product) {
					return $line->qty;
				}
			}
		}
		return null;
	}
	
	static function returnTransferedQtyForProductFromOrderPosition($fk_product, Commande $object)
	{
		if ($fk_product > 0 && count($object->lines) > 0)
		{
			foreach ($object->lines as $line) {
				if ($line->fk_product == $fk_product) {
					return $line->transfered_qty;
				}
			}
		}
		return 0;
	}
	
}