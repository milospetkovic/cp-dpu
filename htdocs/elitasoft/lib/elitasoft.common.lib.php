<?php 

function elitasoft_action_result($ok=true) {
	global $langs;
	($ok) ? setEventMessage($langs->trans('ActionSuccess'), 'mesgs') : setEventMessage($langs->trans('ActionError'), 'errors');
}

function ElitaAjaxInplaceInputEdit($table, $table_column, $rowid, $value, $reload=false, $param='', $print_result=true) {
	global $langs;
	$title=$langs->trans("ClickToEdit");
	$out = "<span class=\"elitasoft-inplace-edit\" data-t=\"$table\" data-c=\"$table_column\"
	data-r=\"$rowid\" data-reload=\"". ($reload ? 1 : 0) ."\" data-p=\"".$param."\" title=\"$title\">$value</span>";
	if ($print_result) {
		print $out;
		return;
	}
	return $out;
}