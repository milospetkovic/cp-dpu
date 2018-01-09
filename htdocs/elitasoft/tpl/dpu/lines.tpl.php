<?php
$sql = 'SELECT l.rowid, l.fk_dpu, l.fk_product, l.transfered_qty, l.supplied_qty, l.sold_qty, l.remained_qty, l.unit_price, ';
$sql.= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label as product_label, p.fk_unit ';
$sql.= ' FROM '.MAIN_DB_PREFIX.$object->table_element_line.' as l';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON (p.rowid = l.fk_product)';
$sql.= ' WHERE l.fk_dpu = '.$object->id;

$nbtotalofrecords = 0;
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);
}

$sql.= $db->order($sortfield,$sortorder);
$sql.= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);

if ($resql)
{
	$i = 0;
	$num = $db->num_rows($resql);
	
	$product_static=new Product($db);

	print_barre_liste($texte, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords,'title_products.png');
?>
<table class="liste" width="100%">

	<thead>
		<tr class="liste_titre">
			<th class="liste_titre" align="center"></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Product') ?></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Label') ?></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Preneta količina') ?></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Nabavljena količina') ?></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Zaliha na kraju dana') ?></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Utrošena količina u toku dana') ?></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Cena po jedinici') ?></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Ostvaren promet') ?></th>
			<th class="liste_titre" align="left"><?php echo $langs->trans('Prodajna vrednost') ?></th>
		</tr>
	</thead>
	
	<tbody>
		<?php		
		$var=true;
    	while ($i < min($num,$limit))
    	{
    		$objp = $db->fetch_object($resql);
    		
    		$product_static->id = $objp->fk_product;
    		$product_static->ref = $objp->product_ref;
    		$product_static->label = $objp->product_label;
		?>
		
			<tr <?php echo $bc[$var] ?>>
				<td><?php echo ($i+1 + $page*$limit).'. ' ?></td>
				<td><?php echo $product_static->getNomUrl(1,'',24); ?></td>
				<td><?php echo dol_trunc($product_static->label,40) ?></td>
				<td><?php echo price($objp->transfered_qty, 0, '', 0, 0) ?></td>
				<td><?php echo price($objp->supplied_qty, 0, '', 0, 0) ?></td>
				<td><?php echo price(($objp->remained_qty), 0, '', 0, 0) ?></td>
				<td><?php echo price($objp->sold_qty, 0, '', 0, 0) ?></td>
				<td><?php echo price($objp->unit_price) ?></td>
				<td><?php echo price(round($objp->sold_qty * $objp->unit_price, 2)) ?></td>
				<td><?php echo price(round($objp->supplied_qty * $objp->unit_price, 2)) ?></td>
			</tr>
		
		<?php
			$i++;
		}
		?>
	
	</tbody>
	
</table>

<?php 
}
?>