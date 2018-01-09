<?php 

class ElitaCommonObject extends CommonObject 
{

	function __contstruct($db) {
		$this->db = $db;
	}
	
	static function saveActionInCalender($object, $action, $log_txt, $assigned_user_id=false) 
	{
		global $db, $langs, $user;
		$now = dol_now();
	
		$contactforaction=new Contact($db);
		$societeforaction=new Societe($db);
		if ($object->sendtoid > 0) $contactforaction->fetch($object->sendtoid);
		if ($object->socid > 0)    $societeforaction->fetch($object->socid);
	
		$object->actiontypecode='AC_OTH_AUTO';
	
		if (empty($object->actionmsg2)) $object->actionmsg2=$log_txt;
		$object->actionmsg=$log_txt;

		$object->actionmsg.="\n".$langs->transnoentities("Author").': '.$user->login;
		$object->sendtoid=0;

		$actioncomm = new ActionComm($db);
		$actioncomm->type_code   = $object->actiontypecode;		// code of parent table llx_c_actioncomm (will be deprecated)
		$actioncomm->code='AC_'.$action;
		$actioncomm->label       = $object->actionmsg2;
		$actioncomm->note        = $object->actionmsg;
		$actioncomm->datep       = $now;
		$actioncomm->datef       = $now;
		$actioncomm->durationp   = 0;
		$actioncomm->punctual    = 1;
		$actioncomm->percentage  = -1;   // Not applicable
		$actioncomm->societe     = $societeforaction;
		$actioncomm->contact     = $contactforaction;
		$actioncomm->socid       = $societeforaction->id;
		$actioncomm->contactid   = $contactforaction->id;
		$actioncomm->authorid    = ($assigned_user_id > 0) ? $assigned_user_id : $user->id;   // User saving action
		$actioncomm->userownerid = ($assigned_user_id > 0) ? $assigned_user_id : $user->id;	// Owner of action
		$actioncomm->fk_element  = $object->id;
		$actioncomm->elementtype = $object->element;
		// ELB
		if ($assigned_user_id > 0) {
			$actioncomm->authorid = $assigned_user_id;
		}
		// end ELB
		$ret=$actioncomm->add($user);

		return $ret;
	}
	
}