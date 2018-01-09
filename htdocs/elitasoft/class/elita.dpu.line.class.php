<?php 

class ElitaDPULine extends CommonOrderLine
{
	public $element='elita_dpudet';
	public $table_element='elita_dpudet';

	public $fk_dpu;
	
	public $fk_product;
	public $fk_unit;
	public $product_ref;
	public $product_label;
	
	public $unit_price;
	
	public $transfered_qty;
	public $supplied_qty;
	public $sold_qty;
	public $remained_qty;
	

	/**
     *      Constructor
     *
     *      @param     DoliDB	$db      handler d'acces base de donnee
     */
    function __construct($db)
    {
        $this->db= $db;
    }
    

    /**
     *	Insert line into database
     *
     *	@param      int		$notrigger		1 = disable triggers
     *	@return		int						<0 if KO, >0 if OK
     */
    function insert()
    {
    	global $langs, $conf, $user;
    
    	$error=0;	
    
    	$this->db->begin();
    
    	// Insertion dans base de la ligne
    	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element;
    	$sql.= ' (fk_dpu, fk_product, unit_price, transfered_qty, supplied_qty, sold_qty, remained_qty)';
    	$sql.= " VALUES (".$this->fk_dpu.",";
    	$sql.= " ".$this->fk_product.",";
    	$sql.= " '".price2num($this->unit_price)."',";
    	$sql.= " '".price2num($this->transfered_qty)."',";
    	$sql.= " '".price2num($this->supplied_qty)."',";
    	$sql.= " '".price2num($this->sold_qty)."',";
    	$sql.= " '".price2num($this->remained_qty)."'";
    	$sql.= ')';
    
    	dol_syslog(get_class($this)."::insert", LOG_DEBUG);
    	
    	$resql=$this->db->query($sql);
    	
    	if ($resql)
    	{
    		$this->rowid=$this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);		
    
    		if (!$error) {
    			$this->db->commit();
    			return 1;
    		}
    
    		$this->db->rollback();
    		return -1;
    	}
    	else
    	{
    		$this->error=$this->db->error();
    		$this->db->rollback();
    		return -2;
    	}
    }
}