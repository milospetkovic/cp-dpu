<?php 

class ElitaDPU extends CommonObject 
{	
	const STATUS_DRAFT = 0;
	const STATUS_PROCESSED = 1;
	
	public $element='elita_dpu';
	public $table_element='elita_dpu';
	public $table_element_line = 'elita_dpudet';
	public $class_element_line = 'ElitaDpuLine';
	public $fk_element = 'fk_dpu';
	
	public $date; // date of dpu
	public $user_id; // author of dpu
	public $status;  // status of dpu
	public $pk_nr;	 // number of PK
	
	/**
	 *  DPULine[]
	 */
	public $lines = array();
	
	/**
	 *  Constructor
	 *
	 *  @param      DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
	}
	
	/**
	 *	Create DPU
	 *
	 *	@return 	int			<0 if KO, >0 if OK
	 */
	function create($notrigger=0)
	{
		global $db, $conf, $langs, $user;
		$error=0;
	
		dol_syslog(get_class($this)."::create user=".$user->id);
		
		$result=self::isExistingObject($this->date);
		if ($result > 0)
		{
			$this->error='ErrorDPUWithDateAlreadyExists';
			dol_syslog(get_class($this)."::create ".$this->error,LOG_WARNING);
			$this->db->rollback();
			return -1;
		}
	
		// $date_commande is deprecated
		$date = $this->date;
	
		$now=dol_now();
	
		$this->db->begin();
	
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."elita_dpu (";
		$sql.= " date, user_id, fk_statut, pk_nr";
		$sql.= ")";
		$sql.= " VALUES (";
		$sql.= "'".$this->db->idate($date)."'";
		$sql.= ", '".$this->db->escape($user->id)."'";
		$sql.= ", '".self::STATUS_DRAFT."'";
		$sql.= ", '".$this->db->escape($this->pk_nr)."'";
		$sql.= ")";
	
		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'elita_dpu');
		} else {
			$this->errors[] = $this->db->lasterror;
			$error++;
		}
		
		if (! $error && ! $notrigger)
		{
			// Call trigger
			$result=$this->call_trigger('ELITA_DPU_CREATE',$user);
			if ($result < 0) $error++;
			// End call triggers
		}
				
		if (! $error)
		{
			$this->db->commit();
			return $this->id;
		}
		else
		{
			$this->db->rollback();
			return -1*$error;
		}
	}

	/**
	 *	Get object and lines from database
	 *
	 *	@param      int			$id       		Id of object to load
	 * 	@param		string		$ref			Ref of object
	 * 	@param		string		$ref_ext		External reference of object
	 * 	@param		string		$ref_int		Internal reference of other object
	 *	@return     int         				>0 if OK, <0 if KO, 0 if not found
	 */
	function fetch($id)
	{
		global $conf;
	
		// Check parameters
		if (empty($id)) return -1;
	
		$sql = 'SELECT dpu.rowid, dpu.date, dpu.user_id, dpu.fk_statut, dpu.pk_nr ';
		$sql.= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' as dpu';
		$sql.= " WHERE dpu.rowid=".$id;
		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$result = $this->db->query($sql);
		
		if ($result)
		{
			$obj = $this->db->fetch_object($result);
			if ($obj)
			{
				$this->id					= $obj->rowid;
				$this->user_id				= $obj->user_id;
				$this->date					= $this->db->jdate($obj->date);
				$this->status				= $obj->fk_statut;
				$this->pk_nr				= $obj->pk_nr;
			
				$this->lines				= array();
				
				// fetch object lines
				$result=$this->fetch_lines();
				
				if ($result < 0)
				{
					return -3;
				}
				
				return 1;
			}
			else
			{
				$this->error='Order with id '.$id.' not found sql='.$sql;
				return 0;
			}
		}
		else
		{
			$this->error=$this->db->error();
			return -1;
		}
	}
	
	/**
	 *	Load array lines
	 *
	 *	@param		int		$only_product	Return only physical products
	 *	@return		int						<0 if KO, >0 if OK
	 */
	function fetch_lines()
	{
		$this->lines=array();
	
		$sql = 'SELECT l.rowid, l.fk_dpu, l.fk_product, l.transfered_qty, l.supplied_qty, l.sold_qty, l.remained_qty, l.unit_price, ';
		$sql.= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label as product_label, p.fk_unit ';
		$sql.= ' FROM '.MAIN_DB_PREFIX.$this->table_element_line.' as l';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON (p.rowid = l.fk_product)';
		$sql.= ' WHERE l.fk_dpu = '.$this->id;
		
		dol_syslog(get_class($this)."::fetch_lines", LOG_DEBUG);
		
		$result = $this->db->query($sql);
		
		if ($result)
		{
			$num = $this->db->num_rows($result);
	
			$i = 0;
			while ($i < $num)
			{
				$objp = $this->db->fetch_object($result);
	
				$line = new ElitaDPULine($this->db);
	
				$line->rowid            = $objp->rowid;
				$line->id               = $objp->rowid;
				$line->fk_dpu      		= $objp->fk_dpu;
				$line->fk_product     	= $objp->fk_product;
				$line->unit_price     	= $objp->unit_price;
				$line->fk_unit     		= $objp->fk_unit;
				$line->product_ref		= $objp->product_ref;
				$line->product_label	= $objp->product_label;
				$line->transfered_qty   = $objp->transfered_qty;
				$line->supplied_qty     = $objp->supplied_qty;
				$line->sold_qty     	= $objp->sold_qty;
				$line->remained_qty     = $objp->remained_qty;
				
				$this->lines[$i] = $line;
	
				$i++;
			}
	
			$this->db->free($result);
	
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			return -3;
		}
	}
	
	
	/**
	 *	Delete the dpu
	 *
	 *	@param	User	$user		User object
	 *	@param	int		$notrigger	1=Does not execute triggers, 0= execuete triggers
	 * 	@return	int					<=0 if KO, >0 if OK
	 */
	function delete()
	{
		global $conf, $langs;
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	
		$error = 0;
	
		$this->db->begin();
		
		$res = $this->deleteAllDPULines();
		if (!$res) {
			$error++;
			$this->errors[]=$this->db->lasterror();
		}

		if (! $error)
		{	

			$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".$this->id;
			dol_syslog(get_class($this)."::delete", LOG_DEBUG);
			if (! $this->db->query($sql) )
			{
				$error++;
				$this->errors[]=$this->db->lasterror();
			}
		}
		
		if (!$error) {
			// delete generated files
			$comref = dol_sanitizeFileName(ElitaDPU::returnSubDirForDPUsGeneratedDocs($this->date));
			if ($conf->commande->dir_output)
			{
				$dir = $conf->commande->dir_output . "/" . $comref ;				
			    if (file_exists($dir))
        		{
        			if (! dol_delete_dir_recursive($dir))
        			{
        				$error++;
        				$this->errors[] =$langs->trans("ErrorCanNotDeleteDir",$dir);
        			}
        		}
			}
		}
	
		if (! $error)
		{
			dol_syslog(get_class($this)."::delete $this->id by $user->id", LOG_DEBUG);
			$this->db->commit();
			return 1;
		}
		else
		{
			foreach($this->errors as $errmsg)
			{
				dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
	}
	
	public function deleteAllDPULines() {
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element_line." WHERE fk_dpu = ".$this->id;
		return ElitaCommonManager::execute($sql);
	}
								
								
	/**
	 * Check if dpu with date already exists		 
	 *
	 *  @return int     			<0 if KO, 0 if OK but not found, >0 if OK and exists
	 */
	static function isExistingObject($date)
	{
		global $db,$conf;
	
		$sql = "SELECT rowid, date";
		$sql.= " FROM ".MAIN_DB_PREFIX."elita_dpu";
		$sql.= " WHERE date= '".$db->idate($date)."'";
		
		dol_syslog(get_class()."::isExistingObject", LOG_DEBUG);
		$resql = $db->query($sql);
		if ($resql)
		{
			$num=$db->num_rows($resql);
			if ($num > 0) return 1;
			else return 0;
		}
		return -1;
	}
	
	static function printHeader(ElitaDPU $object) 
	{
		global $langs, $conf, $user;
		
		$langs->load('common@elitasoft');
		
		$h = 0;
		$head = array();
		
		$head[$h][0] = DOL_URL_ROOT.'/elitasoft/dpu/card.php?id='.$object->id;
		$head[$h][1] = $langs->trans("DPUCard");
		$head[$h][2] = 'card';
		$h++;
				
		return $head;
	}
	
	/**
	 *  Create a document onto disk accordign to template module.
	 *
	 *  @param	    string		$modele			Force le mnodele a utiliser ('' to not force)
	 *  @param		Translate	$outputlangs	objet lang a utiliser pour traduction
	 *  @param      int			$hidedetails    Hide details of lines
	 *  @param      int			$hidedesc       Hide description
	 *  @param      int			$hideref        Hide ref
	 *  @return     int         				0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails=0, $hidedesc=0, $hideref=0)
	{
		global $conf,$langs;
	
		$langs->load("orders");
	
		// Positionne le modele sur le nom du modele a utiliser
		if (! dol_strlen($modele))
		{
			if (! empty($conf->global->COMMANDE_ADDON_PDF))
			{
				$modele = $conf->global->COMMANDE_ADDON_PDF;
			}
			else
			{
				$modele = 'einstein';
			}
		}
	
		$modelpath = "elitasoft/core/modules/dpu/doc/";
		$modele = 'dpu';
	
		return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref);
	}
	
	
	function getLibStatus($status, $with_picto=true)
	{	
		global $langs;
		
		$out = '';
		
		if ($status==self::STATUS_DRAFT) {
			$status_name = $langs->trans('StatusDraft'); 
			if ($with_picto) $out.= img_picto($status_name,'statut0').' ';
			$out.= $status_name;
		}
		elseif ($status==self::STATUS_PROCESSED) {
			$status_name = $langs->trans('StatusProcessed');
			if ($with_picto) $out.= img_picto($status_name,'statut6').' ';
			$out.= $langs->trans($status_name);
		}
		return $out;
	}
	
	public function setStatus($status) {
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql.= " SET fk_statut=".$status;
		return $this->db->query($sql);
	}
	
	static function returnSubDirForDPUsGeneratedDocs($date_of_dpu_ts) {
		return 'DPU-'.date('Y-m-d', $date_of_dpu_ts);
	}
	
	/**
	 * The DPU id before current dpu
	 * 
	 * @param 	int 	$date 	The date of current dpu in timestamp format
	 * @return 	int		The id of previous DPU
	 */
	static function returnTheFirstDPUIDBeforeTheDate($date) {
		global $db;
		$sql = " SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."elita_dpu";
		$sql.= " WHERE date < ".$db->idate($date);
		$sql.= " ORDER BY date DESC LIMIT 1";
		$res = ElitaCommonManager::querySingle($sql);
		return $res->rowid;		
	}
	
}

