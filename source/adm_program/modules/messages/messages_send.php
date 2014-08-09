<?php
/******************************************************************************
 * Check message information and save it
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * msg_id    - set message id for conversations
 * msg_type  - set message type
 * 
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/template.php');

// Initialize and check the parameters
$getMsgId        = admFuncVariableIsValid($_GET, 'msg_id', 'numeric', 0);
$getMsgType      = admFuncVariableIsValid($_GET, 'msg_type', 'string', '');

// Check form values
$postFrom        = admFuncVariableIsValid($_POST, 'mailfrom', 'string', '');
$postName        = admFuncVariableIsValid($_POST, 'name', 'string', '');
$postSubject     = admFuncVariableIsValid($_POST, 'subject', 'html', '');
$postSubjectSQL  = admFuncVariableIsValid($_POST, 'subject', 'string', '');
$postTo          = $_POST['msg_to'];
$postBody        = admFuncVariableIsValid($_POST, 'msg_body', 'html', '');
$postBodySQL     = admFuncVariableIsValid($_POST, 'msg_body', 'string', '');
$postDeliveryConfirmation  = admFuncVariableIsValid($_POST, 'delivery_confirmation', 'boolean', 0);
$postCaptcha     = admFuncVariableIsValid($_POST, 'captcha', 'string');

//if message not PM it must be Email and then directly check the parameters
if ($getMsgType != 'PM')
{
    $getMsgType      = 'EMAIL';
    
    //Stop if mail should be send and mail module is disabled
    if($gPreferences['enable_mail_module'] != 1)
    {
            $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    }
    
    // allow option to send a copy to your email address only for registered users because of spam abuse
    if($gValidLogin)
    {
        $postCarbonCopy = admFuncVariableIsValid($_POST, 'carbon_copy', 'boolean', 0);
    }
    else
    {
        $postCarbonCopy = 0;
    }
    
    // Falls Attachmentgroesse die max_post_size aus der php.ini uebertrifft, ist $_POST komplett leer.
    // Deswegen muss dies ueberprueft werden...
    if (empty($_POST))
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    
    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if (!$gValidLogin && $gPreferences['enable_mail_captcha'] == 1)
    {
        if ( !isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($postCaptcha) )
        {
            if($gPreferences['captcha_type']=='pic') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CODE_INVALID'));}
            else if($gPreferences['captcha_type']=='calc') {$gMessage->show($gL10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
        }
    }
    
}

//Stop if pm should be send pm module is disabled
if($gPreferences['enable_pm_module'] != 1 && $getMsgType == 'PM')
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//if user is logged in then show sender name and email
if ($gCurrentUser->getValue('usr_id') > 0)
{
    $postName = $gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME');
    $postFrom = $gCurrentUser->getValue('EMAIL');
}

//if no User is set, he is not able to ask for delivery confirmation 
if(!($gCurrentUser->getValue('usr_id')>0 && $gPreferences['mail_delivery_confirmation']==2) && $gPreferences['mail_delivery_confirmation']!=1)
{
    $postDeliveryConfirmation = 0;
}
    
// check if PM or Email and to steps:
if ($getMsgType == 'EMAIL')
{

	//put values into SESSION
	$_SESSION['message_request'] = array(
		'name'          => $postName,
		'msgfrom'       => $postFrom,
		'subject'       => $postSubject,
		'msg_body'      => $postBody,
		'carbon_copy'   => $postCarbonCopy,
		'delivery_confirmation' => $postDeliveryConfirmation,
	);

    if (isset($postTo))
    {
        $receiver = array();
   
        //Erst mal ein neues Emailobjekt erstellen...
        $email = new Email();
        
        // und ein Dummy Rollenobjekt dazu
        $role = new TableRoles($gDb);
        
        foreach ($postTo as $value)
        {
            if (strpos($value,':') == true) 
            {
                $groupsplit = explode( ':', $value);
                
                if (strpos($groupsplit[1],'-') == true)
                {
                    $group = explode( '-', $groupsplit[1]);
                }
                else
                {
                    $group[0] = $groupsplit[1];
                    $group[1] = 0;
                }

                // wird eine bestimmte Rolle aufgerufen, dann pruefen, ob die Rechte dazu vorhanden sind
                $sql = 'SELECT rol_mail_this_role, rol_name, rol_id 
                          FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                         WHERE rol_cat_id    = cat_id
                           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
                               OR cat_org_id IS NULL)
                           AND rol_id = '.$group[0];
                $result = $gDb->query($sql);
                $row    = $gDb->fetch_array($result);
        
                // Ausgeloggte duerfen nur an Rollen mit dem Flag "alle Besucher der Seite" Mails schreiben
                // Eingeloggte duerfen nur an Rollen Mails schreiben, zu denen sie berechtigt sind
                // Rollen muessen zur aktuellen Organisation gehoeren
                if((!$gValidLogin && $row['rol_mail_this_role'] != 3)
                || ($gValidLogin  && !$gCurrentUser->mailRole($row['rol_id']))
                || $row['rol_id']  == null)
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                }

                $role->readDataById($group[0]);

                // Falls der User eingeloggt ist checken ob er das recht hat der Rolle eine Mail zu schicken
                if ($gValidLogin && !$gCurrentUser->mailRole($row['rol_id']))
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                }
                // Falls der User nicht eingeloggt ist, muss der Wert 3 sein
                if (!$gValidLogin && $role->getValue('rol_mail_this_role') != 3)
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                }
                
                // Rolle wurde uebergeben, dann alle Mitglieder auslesen (ausser dem Sender selber)
                // je nach Einstellung mit oder nur Ehemalige
                
                if($group[1] == 1)
                {
                    // only former members
                    $sqlConditions = ' AND mem_end < \''.DATE_NOW.'\' ';
                }
                elseif($group[1] == 2)
                {
                    // former members and active members
                    $sqlConditions = ' AND mem_begin < \''.DATE_NOW.'\' ';
                }
                else
                {
                    // only active members
                    $sqlConditions = ' AND mem_begin  <= \''.DATE_NOW.'\'
                                       AND mem_end     > \''.DATE_NOW.'\' ';
                }
                
                $sql   = 'SELECT first_name.usd_value as first_name, last_name.usd_value as last_name, 
                                 email.usd_value as email, rol_name
                            FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
                            JOIN '. TBL_USER_DATA. ' as email
                              ON email.usd_usr_id = usr_id
                             AND LENGTH(email.usd_value) > 0
                            JOIN '.TBL_USER_FIELDS.' as field
                              ON field.usf_id = email.usd_usf_id
                             AND field.usf_type = \'EMAIL\'
                            LEFT JOIN '. TBL_USER_DATA. ' as last_name
                              ON last_name.usd_usr_id = usr_id
                             AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                            LEFT JOIN '. TBL_USER_DATA. ' as first_name
                              ON first_name.usd_usr_id = usr_id
                             AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
                           WHERE rol_id      = '.$group[0].'
                             AND rol_cat_id  = cat_id
                             AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                                 OR cat_org_id IS NULL )
                             AND mem_rol_id  = rol_id
                             AND mem_usr_id  = usr_id
                             AND usr_valid   = 1 '.
                                 $sqlConditions;
        
                // Wenn der User eingeloggt ist, wird die UserID im Statement ausgeschlossen, 
                //damit er die Mail nicht an sich selber schickt.
                if ($gValidLogin)
                {
                    $sql =$sql. ' AND usr_id <> '. $gCurrentUser->getValue('usr_id');
                } 
                $result = $gDb->query($sql);
        
                if($gDb->num_rows($result) > 0)
                {
                    // normaly we need no To-address and set "undisclosed recipients", but if 
                    // that won't work than the From-address will be set 
                    if($gPreferences['mail_sender_into_to'] == 1)
                    {
                        // always fill recipient if preference is set to prevent problems with provider
                        $email->addRecipient($postFrom,$postName);
                    }
                    
                    // all role members will be attached as BCC
                    while ($row = $gDb->fetch_object($result))
                    {
                        $receiver[] = array($row->email , $row->first_name.' '.$row->last_name);
                    }

                }
                else
                {
                    // Falls in der Rolle kein User mit gueltiger Mailadresse oder die Rolle gar nicht in der Orga
                    // existiert, muss zumindest eine brauchbare Fehlermeldung präsentiert werden...
                    $gMessage->show($gL10n->get('MAI_ROLE_NO_EMAILS'));
                }

                
                
                
            }
            else
            {
                $user = new User($gDb, $gProfileFields, $value);
                
                // besitzt der User eine gueltige E-Mail-Adresse
                if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
                {
                    $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
                }
                
                $receiver[] = array($user->getValue('EMAIL'), $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'));
            }
        }
        
    }
    else
    {
        // message when no receiver is given
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    // aktuelle Seite im NaviObjekt speichern. Dann kann in der Vorgaengerseite geprueft werden, ob das
    // Formular mit den in der Session gespeicherten Werten ausgefuellt werden soll...
    $gNavigation->addUrl(CURRENT_URL);


    //Nun der Mail die Absenderangaben,den Betreff und das Attachment hinzufuegen...
    if(strlen($postName) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
    }

    //Absenderangaben checken falls der User eingeloggt ist, damit ein paar schlaue User nicht einfach die Felder aendern koennen...
    if ( $gValidLogin 
    && (  $postFrom != $gCurrentUser->getValue('EMAIL') 
       || $postName != $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME')) )
    {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    //Absenderangaben setzen
    if ($email->setSender($postFrom,$postName))
    {
        //Betreff setzen
        if ($email->setSubject($postSubject))
        {
            //Pruefen ob moeglicher Weise ein Attachment vorliegt
            if (isset($_FILES['userfile']))
            {
                //noch mal schnell pruefen ob der User wirklich eingelogt ist...
                if (!$gValidLogin)
                {
                    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
                }
                $attachmentSize = 0;
                // Nun jedes Attachment
                for($currentAttachmentNo = 0; isset($_FILES['userfile']['name'][$currentAttachmentNo]) == true; $currentAttachmentNo++)
                {
                    //Pruefen ob ein Fehler beim Upload vorliegt
                    if (($_FILES['userfile']['error'][$currentAttachmentNo] != 0) &&  ($_FILES['userfile']['error'][$currentAttachmentNo] != 4))
                    {
                        $gMessage->show($gL10n->get('MAI_ATTACHMENT_TO_LARGE'));
                    }
                    //Wenn ein Attachment vorliegt dieses der Mail hinzufuegen
                    if ($_FILES['userfile']['error'][$currentAttachmentNo] == 0)
                    {
                        // pruefen, ob die Anhanggroesse groesser als die zulaessige Groesse ist
                        $attachmentSize = $attachmentSize + $_FILES['userfile']['size'][$currentAttachmentNo];
                        if($attachmentSize > $email->getMaxAttachementSize("b"))
                        {
                            $gMessage->show($gL10n->get('MAI_ATTACHMENT_TO_LARGE'));
                        }
                        
                        //Falls der Dateityp nicht bestimmt ist auf Standard setzen
                        if (strlen($_FILES['userfile']['type'][$currentAttachmentNo]) <= 0)
                        {
                            $_FILES['userfile']['type'][$currentAttachmentNo] = 'application/octet-stream';                        
                        }
                        
                        //Datei anhängen
                        try
                        {
                            $email->AddAttachment($_FILES['userfile']['tmp_name'][$currentAttachmentNo], $_FILES['userfile']['name'][$currentAttachmentNo], $encoding = 'base64', $_FILES['userfile']['type'][$currentAttachmentNo]);
                        }
                        catch (phpmailerException $e)
                        {
                            $gMessage->show($e->errorMessage());
                        }                  
                    }
                }
            }
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('MAI_SUBJECT')));
        }
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_INVALID', $gL10n->get('SYS_EMAIL')));
    }

    // if possible send html mail
    if($gValidLogin == true && $gPreferences['mail_html_registered_users'] == 1)
    {
        $email->sendDataAsHtml();
    }

    // Falls eine Kopie benoetigt wird, das entsprechende Flag im Mailobjekt setzen
    if (isset($postCarbonCopy) && $postCarbonCopy == true)
    {
        $email->setCopyToSenderFlag();

        // if mail was send to user than show recipients in copy of mail if current user has a valid login
        if($gValidLogin)
        {
            $email->setListRecipientsFlag();
        }
    }
	
	$sendresult = array_map("unserialize", array_unique(array_map("serialize", $receiver)));
	foreach ($sendresult as $address)
    {
		$email->addRecipient($address[0], $address[1]);
    }
	
    // Falls eine Lesebestätigung angefordert wurde
    if($postDeliveryConfirmation == 1)
    {
        $email->ConfirmReadingTo = $gCurrentUser->getValue('EMAIL');
    }

    // load the template and set the new email body with template
    $emailTemplate = admReadTemplateFile("template.html");
    $emailTemplate = str_replace("#message#",$postBody,$emailTemplate);

    //set Text
    $email->setText($emailTemplate);

    //Nun kann die Mail endgueltig versendet werden...
    $sendResult = $email->sendEmail();

}
else
{
	
	//usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $user = new User($gDb, $gProfileFields, $postTo[0]);

    // darf auf die User-Id zugegriffen werden    
    if(($gCurrentUser->editUsers() == false && isMember($user->getValue('usr_id')) == false)|| strlen($user->getValue('usr_id')) == 0 )
    {
            $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
    }

    // check if receiver of message has valid login
    if(strlen($user->getValue('usr_login_name')) == 0)
    {
        $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }

    // aktuelle Seite im NaviObjekt speichern. Dann kann in der Vorgaengerseite geprueft werden, ob das
    // Formular mit den in der Session gespeicherten Werten ausgefuellt werden soll...
    $gNavigation->addUrl(CURRENT_URL);

    if ($getMsgId == 0)
    {
        $PMId2 = 1;
        
        $sql = "SELECT MAX(msg_id1) as max_id
              FROM ". TBL_MESSAGES;
    
        $result = $gDb->query($sql);
        $row = $gDb->fetch_array($result);
        $getMsgId = $row['max_id'] + 1;
        
        $sql = "INSERT INTO ". TBL_MESSAGES. " (msg_type, msg_id1, msg_id2, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp, msg_read) 
            VALUES ('".$getMsgType."', '".$getMsgId."', 0, '".$postSubjectSQL."', '".$gCurrentUser->getValue('usr_id')."', '".$postTo[0]."', '', CURRENT_TIMESTAMP, '1')";
    
        $gDb->query($sql);    
        
    }
    else
    {
        $sql = "SELECT MAX(msg_id2) as max_id
              FROM ".TBL_MESSAGES." 
			  where msg_id1 = ".$getMsgId;
    
        $result = $gDb->query($sql);
        $row = $gDb->fetch_array($result);
        $PMId2 = $row['max_id'] + 1;
        
        $sql = "UPDATE ". TBL_MESSAGES. " SET  msg_read = '1', msg_timestamp = CURRENT_TIMESTAMP, msg_usrid1 = '".$gCurrentUser->getValue('usr_id')."', msg_usrid2 = '".$postTo[0]."'
                WHERE msg_id2 = 0 and msg_id1 = ".$getMsgId;

        $gDb->query($sql);

    }
        
    $sql = "INSERT INTO ". TBL_MESSAGES. " (msg_type, msg_id1, msg_id2, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp, msg_read) 
            VALUES ('".$getMsgType."', '".$getMsgId."', '".$PMId2."', '', '".$gCurrentUser->getValue('usr_id')."', '".$postTo[0]."', '".$postBodySQL."', CURRENT_TIMESTAMP, '0')";
    
    if ($gDb->query($sql)) {
      $sendResult = TRUE;
    }
}

// message if send/save is OK
if ($sendResult === TRUE)
{
    // save mail also to database
    if ($getMsgType != 'PM')
    {
         $sql = "SELECT MAX(msg_id1) as max_id
          FROM ". TBL_MESSAGES;
    
        $result = $gDb->query($sql);
        $row = $gDb->fetch_array($result);
        $getMsgId = $row['max_id'] + 1;
            
        $sql = "INSERT INTO ". TBL_MESSAGES. " (msg_type, msg_id1, msg_id2, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp, msg_read) 
            VALUES ('".$getMsgType."', '".$getMsgId."', 0, '".$postSubjectSQL."', '".$gCurrentUser->getValue('usr_id')."', '', '".$postBodySQL."', CURRENT_TIMESTAMP, '0')";
        
        $gDb->query($sql);    
    }

    // Delete CaptchaCode if send/save was correct
    if (isset($_SESSION['captchacode']))
    {
        unset($_SESSION['captchacode']);
    }

    // Bei erfolgreichem Versenden wird aus dem NaviObjekt die am Anfang hinzugefuegte URL wieder geloescht...
    $gNavigation->deleteLastUrl();
    // remove also the send-page if not an conversation
    $gNavigation->deleteLastUrl();

    
    // Meldung ueber erfolgreichen Versand und danach weiterleiten
    if($gNavigation->count() > 0)
    {
        $gMessage->setForwardUrl($gNavigation->getUrl());
		//$gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    }
    else
    {
        $gMessage->setForwardUrl($gHomepage, 2000);
    }
    
    if ($getMsgType != 'PM')
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_SEND'));
    }
    else
    {
        $gMessage->show($gL10n->get('SYS_EMAIL_SEND', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    }
}
else
{
    if ($getMsgType != 'PM')
    {
        $gMessage->show($sendResult.'<br />'.$gL10n->get('SYS_EMAIL_NOT_SEND', $sendResult));
    }
    else
    {
        $gMessage->show($sendResult.'<br />'.$gL10n->get('SYS_EMAIL_NOT_SEND', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $sendResult));
    }
}

?>