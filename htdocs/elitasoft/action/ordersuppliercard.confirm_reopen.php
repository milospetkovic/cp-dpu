<?php 

if (!defined('DO_ACTION')) exit;

$elitaSupplierOrder = new ElitaOrderSupplier($db);

$idwarehouse = $conf->global->ELITASOFT_CENTRAL_WAREHOUSE;

// reopen supplier order
$result = $elitaSupplierOrder->reopenSupplierOrder($object, $user, $idwarehouse=0, $notrigger=0);

// result message
elitasoft_action_result($result > 0);

// redirect
header('Location: '.DOL_URL_ROOT.'/fourn/commande/card.php?id='.$object->id);
exit;
