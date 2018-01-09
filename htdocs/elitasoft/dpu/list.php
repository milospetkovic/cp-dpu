<?php

require '../../main.inc.php';

$langs->load('common@elitasoft');


$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
$limit = GETPOST('limit')?GETPOST('limit','int'):$conf->liste_limit;
if ($page == -1) { $page = 0; }
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield='dpu.date';
if (! $sortorder) $sortorder='DESC';

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('dpulist'));

/*
 * View
 */
$now=dol_now();

$elita_dpu = new ElitaDPU($db);

llxHeader('',$langs->trans("ListDPU"));

$sql = 'SELECT dpu.rowid, dpu.date, dpu.fk_statut';
$sql.= ' FROM '.MAIN_DB_PREFIX.$elita_dpu->table_element.' dpu';
$sql.= $db->order($sortfield,$sortorder);

$nbtotalofrecords = 0;
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);
}

$sql.= $db->plimit($limit + 1,$offset);

//print $sql;
$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	print_barre_liste($title, $page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',$num,$nbtotalofrecords,'title_commercial.png');


    print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">';
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans('Nr'),$_SERVER["PHP_SELF"],'','',$param,'width="25"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Ref'),$_SERVER["PHP_SELF"],'','',$param,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('DPUDate'),$_SERVER["PHP_SELF"],'dpu.date','',$param, 'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Status'),$_SERVER["PHP_SELF"],'dpu.fk_statut','',$param,'align="right"',$sortfield,$sortorder);
	print '</tr>';

	$var=true;
	$total=0;
	$subtotal=0;
    $productstat_cache=array();
    $i=0;
    
    while ($i < min($num,$limit))
    {
        $objp = $db->fetch_object($resql);
        $var=!$var;
        
        $date_of_dpu_ts = $db->jdate($objp->date);
        $format_date_od_dpu = date('Y-m-d', $date_of_dpu_ts);
        
        print '<tr '.$bc[$var].'>';
        
        // numbering
        print '<td class="nowrap">';
        print ($i + 1 + $offset).'.';
        print '</td>';
        
        // numbering
        print '<td>';
        print '<a href="'.DOL_URL_ROOT.'/elitasoft/dpu/card.php?id='.$objp->rowid.'">'.'DPU-'.$format_date_od_dpu.'</a>';
        print '</td>';

		// date
		print '<td align="center">';
		print '<a href="'.DOL_URL_ROOT.'/elitasoft/dpu/card.php?id='.$objp->rowid.'">'.dol_print_date($date_of_dpu_ts, 'day').'</a>';
		print '</td>';

		// status
		print '<td align="right" class="nowrap">'.$elita_dpu->getLibStatus($objp->fk_statut, true).'</td>';

		print '</tr>';
		
		$i++;
	}

	print '</table>';

	print '</form>'."\n";

	$db->free($resql);
}
else
{
	dol_print_error($db);
}

llxFooter();
$db->close();