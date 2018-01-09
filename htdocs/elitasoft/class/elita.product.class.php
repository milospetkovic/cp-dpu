<?php 

class ElitaProduct extends Product 
{
	
	function __contstruct($db) {
		$this->db = $db;
	}

	/**
	 * Fetch all products which are for selling
	 * 
	 * @return resource
	 */
	static function fetchProductsWhichAreForSelling() 
	{
		global $db;
		$sql = " SELECT p.rowid, p.ref ";
		$sql.= " FROM ".MAIN_DB_PREFIX."product p ";
		$sql.= " WHERE p.tosell=1";
		return $db->query($sql);
	}
	
	/** 
	 * Check up if product is marked for sale (flag in product table)
	 * 
	 * @param	object		$product	instance of Product (fetched product)
	 * @return 	boolean		true OK, false KO
	 */
	static function isProductMarkedForSale(Product $product)
	{
		return ($product->status > 0);
	}
	
	/**
	 * Check up if product is marked for buying (flag in product table)
	 *
	 * @param	object		$product	instance of Product (fetched product)
	 * @return 	boolean		true OK, false KO
	 */
	static function isProductMarkedForBuying(Product $product)
	{
		return ($product->status_buy > 0);
	}
	
	/**
	 * Check up if product is marked for sale ONLY
	 * The product is for sale only, not for buying
	 *
	 * @param	object		$product	instance of Product (fetched product)
	 * @return 	boolean		true OK, false KO
	 */
	static function isProductMarkedForSaleOnly(Product $product)
	{
		return ($product->status > 0 && !($product->status_buy > 0));
	}
	
	static function isProductMarkedAsDrink($product_id) {
		global $db;
		$sql = " SELECT 1 ";
		$sql.= " FROM ".MAIN_DB_PREFIX."product_extrafields";
		$sql.= " WHERE fk_object= ".$db->escape($product_id);
		$sql.= " AND drink=1";
		return ElitaCommonManager::querySingle($sql);
	}
	
}