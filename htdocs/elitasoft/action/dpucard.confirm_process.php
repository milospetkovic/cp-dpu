<?php 

if(!defined('DO_ACTION')) exit;

require_once DOL_DOCUMENT_ROOT.'/elitasoft/class/elita.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/elitasoft/lib/elitasoft.common.lib.php';

$productstatic = new Product($db);
$commandestatic = new Commande($db);
$supplyorderstatic = new CommandeFournisseur($db);

// start transaction
$db->begin();

$error = 0;

// get nr of orders by date
$nr_of_orders_by_date = ElitaCommande::returnCountOfOrdersByDate($object->date);
$friendly_date_format = dol_print_date($object->date, 'day');

// there must be at least and only one order for the same date as dpu
if (!($nr_of_orders_by_date == 1))
{	
	if (empty($nr_of_orders_by_date)) {
		setEventMessage($langs->transnoentities('WarningNoOrdersWithTheSameDateAsDpu', $friendly_date_format), 'errors');
		$error++;
	}
	if ($nr_of_orders_by_date > 1) {
		setEventMessage($langs->transnoentities('WarningMoreThanOneOrderWithTheSameDateAsDpu', $friendly_date_format), 'errors');
		$error++;
	}
}

if (!$error)
{
	// get all products which are for selling
	$get_selling_products = ElitaProduct::fetchProductsWhichAreForSelling();
	
	if ($get_selling_products) 
	{
		// number of selling products
		$num = $db->num_rows($get_selling_products);
		
		$i = 0;
		
		// return order id for selected date
		$getOrderID = ElitaCommande::returnOrderIDByOrderByDate($object->date);
		$commande = new Commande($db);
		// fetch order
		$res = $commande->fetch($getOrderID);		
		if (!($res > 0)) {
			$error++;
			setEventMessage("Greska prilikom pribavljanja porudzbenice za datum $friendly_date_format", 'errors');
		}
		
		// order for the same date must be closed 
		if (!ElitaCommande::isOrderClosed($commande)) {
			$error++;
			setEventMessage("Da biste kreirali DPU onda prvo zatvorite porudzbenicu koja ima datum $friendly_date_format (ref=".$commande->ref.")", 'errors');
		}
		
		// get supply orders
		$supply_orders = [];
		$getSupplyOrders = ElitaOrderSupplier::getSupplyOrdersByDate($object->date);
		if (is_array($getSupplyOrders) && count($getSupplyOrders) > 0) {
			foreach ($getSupplyOrders as $supplyOrderObj) 
			{
				// fetch supply order
				$res = $supplyorderstatic->fetch($supplyOrderObj->rowid);
				if (!($res > 0)) {
					$error++;
					setEventMessage("Greska prilikom pribavljanja dobavljacke porudzbenice sa id=$supplyOrderObj->rowid", 'errors');
					break;
				}
				
				// supply order for the same date must be closed
				if (!ElitaOrderSupplier::isSupplyOrderClosed($supplyorderstatic)) {
					$error++;
					setEventMessage("Da biste kreirali DPU onda prvo zatvorite dobavljacku porudzbenicu cije trenutni broj je ref=".$supplyorderstatic->ref.")", 'errors');
					break;
				}
				
				// populate supply orders array
				$supply_orders[] = $supplyorderstatic;
			}
		}
		
		if (!$error)
		{
			while ($i < $num)
			{
				$i++;
				
				$objp = $this->db->fetch_object($get_selling_products);
				
				$line = new ElitaDPULine($db);
				$productstatic = new Product($db);
				
				$line->fk_dpu = $object->id;
				$line->fk_product = $objp->rowid;
				
				// fetch product
				$productstatic->fetch($line->fk_product);
				
				// get unit price from order position (or from product card if there's no position in the order)
				$getUnitPriceForProduct = ElitaCommande::returnUnitPriceForProductFromOrderPosition($line->fk_product, $commande);
								
				if (!(is_numeric($getUnitPriceForProduct) && $getUnitPriceForProduct > 0)) 
				{
					// get unit price from product card
					$res = $productstatic->fetch($line->fk_product);
					if (!($res > 0)) {
						$error++;
						setEventMessage("Greska prilikom pribavljanja proizvoda id=".$line->fk_product, 'errors');
						break;
					}					
					$getUnitPriceForProduct = $productstatic->price;
				}
				
				// unit price must exists
				if (!(is_numeric($getUnitPriceForProduct) && $getUnitPriceForProduct > 0))
				{
					$error++;
					setEventMessage("Cena za proizvod id=".$line->fk_product." mora da postoji ili u poziciji porudzbenice ili u proizvod kartici", 'errors');
					break;
				}
								
				// assign unit price
				$line->unit_price = $getUnitPriceForProduct;
				
				// get sold qty from order position
				$getSoldQtyForProduct = ElitaCommande::returnSoldQtyForProductFromOrderPosition($line->fk_product, $commande);
				if (!(is_numeric($getSoldQtyForProduct) && $getSoldQtyForProduct > 0))
				{
					$getSoldQtyForProduct = 0;
				}
				
				// assign sold qty
				$line->sold_qty = $getSoldQtyForProduct;				
				
				// first time only - get transfered qty from order position
				if ($conf->global->ELITASOFT_SHOW_PREVIOUS_QTY_INPUT)
				{
					$getTransferedQtyForProduct = ElitaCommande::returnTransferedQtyForProductFromOrderPosition($line->fk_product, $commande);					
				}
				// get transfered qty from previous DPU (remained_qty column)
				else 
				{
					$prevDPUID = ElitaDPU::returnTheFirstDPUIDBeforeTheDate($object->date);
					if (!($prevDPUID > 0)) {
						$error++;
						setEventMessage("Greska prilikom pronalazenja prethodnog DPU-a", 'errors');
						break;
					}
					$fetchPrevDPU = new ElitaDPU($db);
					$res = $fetchPrevDPU->fetch($prevDPUID);
					if (!($res > 0)) {
						$error++;
						setEventMessage("Greska prilikom pribavljanja prethodnog DPU-a", 'errors');
						break;
					}
					foreach ($fetchPrevDPU->lines as $prevDPULine) {
						if ($line->fk_product == $prevDPULine->fk_product) {
							$getTransferedQtyForProduct = $prevDPULine->remained_qty;
						}
					}
				}
				if (!(is_numeric($getTransferedQtyForProduct) && $getTransferedQtyForProduct > 0))
				{
					$getTransferedQtyForProduct = 0;
				}
				
				// assign transfered qty
				$line->transfered_qty = $getTransferedQtyForProduct;
				
				// get supplied qty
				$getSuppliedQtyForProduct = 0;
				if (count($supply_orders) > 0) {
					foreach ($supply_orders as $supplyorderstatic) {
						foreach ($supplyorderstatic->lines as $suppOrderLine) {					
							if ($line->fk_product == $suppOrderLine->fk_product) {
								$getSuppliedQtyForProduct += $suppOrderLine->qty;
							}
						}
					}
				}
				if (!(is_numeric($getSuppliedQtyForProduct) && $getSuppliedQtyForProduct > 0))
				{
					$getSuppliedQtyForProduct = 0;
				}
				
				// assign supplied qty
				$line->supplied_qty = $getSuppliedQtyForProduct;
				
				// calculate remained qty
				$calculateRemainedQty = $line->transfered_qty + $line->supplied_qty - $line->sold_qty;
				
				// assign remained qty
				$line->remained_qty = $calculateRemainedQty;
				if (ElitaProduct::isProductMarkedForSaleOnly($productstatic)) 
				{
					// we we'll not set remained qty for another day
					// if product is for sale only (sale will be for only one day, with no remained qty for the next day)
					$line->remained_qty = 0;
				}				
				
				// insert dpu line
				$res = $line->insert();
				
				if ($res < 0) {
					$error++;
					setEventMessage("Greska prilikom kreiranje pozicije za proizvod sa id=$objp->rowid", 'errors');
					break;
				}
				
			}
		}
	} 
	else 
	{
		$error++;
		setEventMessage('Nema proizvoda za prodaju', 'errors');
	}
}

// set status as processed
if (!$error) {
	$res = $object->setStatus(ElitaDPU::STATUS_PROCESSED);
	if (!$res) {
		$error++;
		setEventMessage("Greska prilikom setovanja statusa za DPU", 'errors');
	}
}

// close transaction
(!$error) ? $db->commit() : $db->rollback();

// result message
elitasoft_action_result(!$error);

// redirect
header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
exit;