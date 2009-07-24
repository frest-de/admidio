<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_sessions
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Sessionobjekt zu erstellen.
 * Eine Session kann ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * renewUserObject($usr_id = 0)
 *                        - diese Funktion stoesst ein Neueinlesen des User-Objekts an
 * renewOrganizationObject() 
 *                        - diese Funktion stoesst ein Neueinlesen des Organisations-Objekts an
 * tableCleanup($max_inactive_time)         
 *                        - Funktion loescht Datensaetze aus der Session-Tabelle 
 *                          die nicht mehr gebraucht werden
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableSession extends TableAccess
{
    // Konstruktor
    function TableSession(&$db, $session = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_SESSIONS;
        $this->column_praefix = 'ses';
        
        if(strlen($session) > 0)
        {
            $this->readData($session);
        }
        else
        {
            $this->clear();
        }
    }

    // Session mit der uebergebenen Session-ID aus der Datenbank auslesen
    function readData($session, $sql_where_condition = '', $sql_additional_tables = '')
    {
        // wurde ses_session_id uebergeben, dann die SQL-Bedingung anpassen
        if(is_numeric($session) == false)
        {
            $sql_where_condition .= ' ses_session_id = "'.$session.'" ';
        }       
        
        parent::readData($session, $sql_where_condition, $sql_additional_tables);
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    function save()
    {
        if($this->new_record)
        {
            // Insert
            global $g_current_organization;
            $this->setValue('ses_org_id', $g_current_organization->getValue('org_id'));
            $this->setValue('ses_begin', DATETIME_NOW);
            $this->setValue('ses_timestamp', DATETIME_NOW);
            $this->setValue('ses_ip_address', $_SERVER['REMOTE_ADDR']);
        }
        else
        {
            // Update
            $this->setValue('ses_timestamp', DATETIME_NOW);
        }
        parent::save();
    }  

    // diese Funktion stoesst ein Neueinlesen des User-Objekts bei allen angemeldeten
    // Usern beim naechsten Seitenaufruf an
    // wird usr_id uebergeben, dann nur das Einlesen fuer diese usr_id anstossen
    function renewUserObject($usr_id = 0)
    {
        $sql_usr_id = '';
        if($usr_id > 0)
        {
            $sql_usr_id = ' WHERE ses_usr_id = '. $usr_id;
        }
        $sql    = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 1 '. $sql_usr_id;
        $this->db->query($sql);
    }
    
    // diese Funktion stoesst ein Neueinlesen des Organisations-Objekts bei allen angemeldeten
    // Usern beim naechsten Seitenaufruf an
    function renewOrganizationObject()
    {
        $sql    = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 2 ';
        $this->db->query($sql);
    }
    
    // diese Funktion loescht Datensaetze aus der Session-Tabelle die nicht mehr gebraucht werden
    function tableCleanup($max_inactive_time)
    {
        // Zeitpunkt bestimmen, ab dem die Sessions geloescht werden, mind. 1 Stunde
        if($max_inactive_time > 60)
        {
            $date_session_delete = time() - $max_inactive_time * 60;
        }
        else
        {
            $date_session_delete = time() - 60 * 60;
        }
            
        $sql    = 'DELETE FROM '. TBL_SESSIONS. ' 
                    WHERE ses_timestamp < "'. date('Y.m.d H:i:s', $date_session_delete). '"';
        $this->db->query($sql);
    }
}
?>