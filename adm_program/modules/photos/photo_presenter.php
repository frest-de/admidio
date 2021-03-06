<?php
/**
 ***********************************************************************************************
 * Show the photo within the Admidio html
 *
 * @copyright 2004-2020 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * photo_nr : Number of the photo that should be shown
 * pho_id   : Id of the album of the photo that should be shown
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

const PHOTO_SHOW_POPUP  = 0;
const PHOTO_SHOW_MODAL  = 1;
const PHOTO_SHOW_PAGE   = 2;

// Initialize and check the parameters
$getPhotoId = admFuncVariableIsValid($_GET, 'pho_id',   'int', array('requireValue' => true));
$getPhotoNr = admFuncVariableIsValid($_GET, 'photo_nr', 'int', array('requireValue' => true));

// check if the module is enabled and disallow access if it's disabled
if ((int) $gSettingsManager->get('enable_photo_module') === 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif ((int) $gSettingsManager->get('enable_photo_module') === 2)
{
    // only logged in users are allowed to use this page
    require(__DIR__ . '/../../system/login_valid.php');
}

// get album data if it's not already stored in session
if (isset($_SESSION['photo_album']) && (int) $_SESSION['photo_album']->getValue('pho_id') === $getPhotoId)
{
    $photoAlbum =& $_SESSION['photo_album'];
}
else
{
    $photoAlbum = new TablePhotos($gDb, $getPhotoId);
    $_SESSION['photo_album'] = $photoAlbum;
}

// check if the current user could view this photo album
if(!$photoAlbum->isVisible())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// get number of next and previous photo
$previousImage = $getPhotoNr - 1;
$nextImage     = $getPhotoNr + 1;
$urlPreviousImage = '#';
$urlNextImage     = '#';
$urlCurrentImage  = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php', array('pho_id' => $getPhotoId, 'photo_nr' => $getPhotoNr, 'max_width' => $gSettingsManager->getInt('photo_show_width'), 'max_height' => $gSettingsManager->getInt('photo_show_height')));

if ($previousImage > 0)
{
    $urlPreviousImage = SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/photos/photo_presenter.php', array('photo_nr' => $previousImage, 'pho_id' => $getPhotoId));
}
if ($nextImage <= $photoAlbum->getValue('pho_quantity'))
{
    $urlNextImage = SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES.'/photos/photo_presenter.php', array('photo_nr' => $nextImage, 'pho_id' => $getPhotoId));
}

// create html page object
$page = new HtmlPage('admidio-photos-presenter', $photoAlbum->getValue('pho_name'));

if ((int) $gSettingsManager->get('photo_show_mode') === PHOTO_SHOW_PAGE)
{
    // if you have no popup or colorbox then show a button back to the album
    $page->setUrlPreviousPage(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('pho_id' => $getPhotoId)));
}
else
{
    // if popup or colorbox than don't show default html layout with menu and sidebar
    $page->setInlineMode();
}

// show additional album information
if ((int) $gSettingsManager->get('photo_show_mode') !== PHOTO_SHOW_MODAL)
{
    $datePeriod = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));

    if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin') && strlen($photoAlbum->getValue('pho_end')) > 0)
    {
        $datePeriod .= ' '.$gL10n->get('SYS_DATE_TO').' '.$photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
    }

    $page->addHtml('<p class="lead">' . $datePeriod . '<br />' . $gL10n->get('SYS_PHOTO_OF_VAR', array($photoAlbum->getValue('pho_photographers'))) . '</p>');
}

// Show photo with link to next photo
if ($nextImage <= $photoAlbum->getValue('pho_quantity'))
{
    $page->addHtml('<div class="admidio-img-presenter"><a href="'.$urlNextImage.'"><img src="'.$urlCurrentImage.'" alt="Foto"></a></div>');
}
else
{
    $page->addHtml('<div class="admidio-img-presenter"><img src="'.$urlCurrentImage.'" alt="'.$gL10n->get('SYS_PHOTO').'" /></div>');
}

// show link to navigate to next and previous photos
if ((int) $gSettingsManager->get('photo_show_mode') !== PHOTO_SHOW_MODAL)
{
    $page->addHtml('<div class="btn-group">');

    if ($previousImage > 0)
    {
        $page->addHtml('
        <button class="btn btn-secondary" onclick="window.location.href=\''.$urlPreviousImage.'\'">
            <i class="fas fa-arrow-alt-circle-left"></i>'.$gL10n->get('PHO_PREVIOUS_PHOTO').'</button>');
    }
    if ($nextImage <= $photoAlbum->getValue('pho_quantity'))
    {
        $page->addHtml('
        <button class="btn btn-primary" onclick="window.location.href=\''.$urlNextImage.'\'">
            <i class="fas fa-arrow-alt-circle-right"></i>'.$gL10n->get('PHO_NEXT_PHOTO').'</button>');
    }
    $page->addHtml('</div>');
}
elseif ((int) $gSettingsManager->get('photo_show_mode') === PHOTO_SHOW_PAGE)
{
    // if no popup mode then show additional album information
    $datePeriod = $photoAlbum->getValue('pho_begin', $gSettingsManager->getString('system_date'));

    if ($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin')
    && strlen($photoAlbum->getValue('pho_end')) > 0)
    {
        $datePeriod .= ' '.$gL10n->get('SYS_DATE_TO').' '.$photoAlbum->getValue('pho_end', $gSettingsManager->getString('system_date'));
    }

    $page->addHtml('
    <div class="row">
        <div class="col-sm-2 col-4">'.$gL10n->get('SYS_DATE').'</div>
        <div class="col-sm-4 col-8"><strong>'.$datePeriod.'</strong></div>
    </div>
    <div class="row">
        <div class="col-sm-2 col-4">'.$gL10n->get('PHO_PHOTOGRAPHER').'</div>
        <div class="col-sm-4 col-8"><strong>'.$photoAlbum->getValue('pho_photographers').'</strong></div>
    </div>');
}

// show html of complete page
$page->show();
