<?php
/******************************************************************************
 * Create new folder
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * folder_id : Folder id of the parent folder
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'numeric', null, true);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
//nur von eigentlicher OragHompage erreichbar
if($gCurrentOrganization->getValue('org_shortname')!= $g_organization)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $gHomepage));
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if (!$gCurrentUser->editDownloadRight())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$gNavigation->addUrl(CURRENT_URL);

if(isset($_SESSION['download_request']))
{
   $form_values = strStripSlashesDeep($_SESSION['download_request']);
   unset($_SESSION['download_request']);
}
else
{
   $form_values['new_folder'] = null;
   $form_values['new_description'] = null;
}

try
{
    // get recordset of current folder from databse
    $folder = new TableFolder($gDb);
    $folder->getFolderForDownload($getFolderId);
}
catch(AdmException $e)
{
	$e->showHtml();
}

$parentFolderName = $folder->getValue('fol_name');


// show html head
$gLayout['title']  = $gL10n->get('DOW_CREATE_FOLDER');
$gLayout['header'] = '<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.noblecount.min.js"></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// show back link
echo $gNavigation->getHtmlBackButton();

// show headline of module
echo '<h1 class="admHeadline">'.$gLayout['title'].'</h1>';

// show form
$form = new Form('new_folder_form', $g_root_path.'/adm_program/modules/downloads/download_function.php?mode=3&amp;folder_id='.$getFolderId);
$form->addDescription($gL10n->get('DOW_CREATE_FOLDER_DESC', $parentFolderName));
$form->addTextInput('new_folder', $gL10n->get('SYS_NAME'), $form_values['new_folder'], 255, FIELD_MANDATORY);
$form->addMultilineTextInput('new_description', $gL10n->get('SYS_DESCRIPTION'), $form_values['new_description'], 4, 255);
$form->addSubmitButton('btn_create', $gL10n->get('DOW_CREATE_FOLDER'), THEME_PATH.'/icons/folder_create.png');
$form->show();

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>