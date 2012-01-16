<?php
/* ExportForce-Klasse v1.64 by tleilax [klammId 27936], 2010-02-08
 *
 * Copyright (c) 2005-2011 Jan-Hendrik Willms <tleilax+ef2@gmail.com>
 * Lizensiert unter der MIT-Lizenz.
 */

class ExportForce
{
    const VERSION = '1.64';
    
    var $_goodtest    = false;
    var $_logfunc     = null;
    var $_reporterror = false;
    var $_lasterror   = -1;
    var $_efstr       = '';
    var $_querycount  = 0;

    var $_apiurl   = 'www.klamm.de';
    var $_apipath  = 'engine/';

    var $_efcalls  = null;
    var $_eflose   = null;
    var $_userlose = null;

    var $_efid      = null;
    var $_efpwd     = null;
    var $_efkennung = null;

    var $_error_messages = array(
        1001 => 'Alles OK',
        1002 => 'EF Account existiert nicht',
        1003 => 'EF Passwort falsch',
        1004 => 'Nicht genug freie EF Anfragen',
        1005 => 'EF Kennung existiert nicht',
        1006 => 'klammUser existiert nicht',
        1007 => 'klammUser ist gesperrt',
        1008 => 'klammUser hat zu wenig Lose',
        1009 => 'Losepasswort falsch',
        1010 => 'Zu wenig Lose auf EF Account',
        1011 => 'Anzahl nicht zulässig',
        1012 => 'Betreff nicht zulässig',
        1013 => 'Inout Parameter nicht zulässig',
        1014 => 'Limit Parameter nicht zulässig',
        1015 => 'ab_tid Parameter nicht zulässig',
        1016 => 'ab_time Parameter nicht zulässig',
        1017 => 'type Parameter nicht zulässig',
        1018 => 'Statistik Passwort falsch',
        1019 => 'Tresor Parameter nicht zulässig',
        1020 => 'Empfänger EF existiert nicht',
        1021 => 'Empfänger EF noch nicht aktiviert',
        1022 => 'Überweisung an eigenen EF nicht möglich',
        1023 => 'target Parameter nicht zulässig',
        1089 => 'Transaktions-Code nicht vorhanden',
        1097 => 'EF temporär überlastet',
        1098 => 'EF-Account ist gesperrt',
        1099 => 'Unbekannter Fehler',
        -99  => 'ExportForce nicht erreichbar'
    );

    /** Konstruktor der Klasse
     * Parameter:
     *   $ef_id       - ID des ExportForce-Accounts
     *   $ef_pwd      - Password des ExportForce-Accounts
     *   $ef_kennung  - Eine Kennungs-ID des ExportForce-Accounts
     *                  (Eine Zahl, kein Text!!)
     *   $reporterror - Fehlermeldungen anzeigen (optional, default=false)
     *   $logfunc     - Logfunktion, die bei jedem Query aufgerufen werden
     *                  soll (näheres dazu, siehe Header der Klasse)
     *
     * Rückgabe:
     *  - keine -
     **********************************************************************/
    function __construct($ef_id, $ef_pwd, $ef_kennung, $reporterror = false, $logfunc = null)
    {
        $this->_efid = $ef_id;
        $this->_efpwd = $ef_pwd;
        $this->_efstr = 'ef_id='.$ef_id.'&ef_pw='.urlencode($ef_pwd);
        $this->_efkennung = $ef_kennung;

        $this->_reporterror=$reporterror;
        if ($logfunc !== null and function_exists($logfunc)) {
            $this->_logfunc = $logfunc;
        }

        if (preg_match('~\D~', $ef_id) and $this->_reporterror) {
            throw new Exception('Die übergebene ExportForce-ID kann nicht gültig sein');
        }
        if (preg_match('~\D~', $ef_kennung) and $this->reporterror) {
            throw new Exception('Als Kennung muss eine Zahl angegeben werden');
        }
    }

    /** Sendet eine Anfrage an die ExportForce-API
     * Parameter:
     *  $query  - Die aufzurufende API-Funktion mit sämtlichen Parametern
     * Rückgabe:
     *  - Die Rückgabe der API
     ***********************************************************************/
    function _efQuery($query)
    {
        $this->_querycount++;

        $fp = @fsockopen($this->_apiurl, 80, $errno, $errstr, 15);
        if (!$fp) {
            if ($this->_logfunc !== null) {
                call_user_func($this->_logfunc, $_SERVER['REMOTE_ADDR'], $query, 'No connect');
            }
            $this->_lasterror = -99;

            if ($this->_reporterror) {
                throw new Exception('ExportForce nicht erreichbar');
            }
            return false;
        }
        $request = "GET /{$this->_apipath}{$query} HTTP/1.0\r\n"
                 . "Host: {$this->_apiurl}\r\n"
                 . "User-Agent: tlx_ef2class_".self::VERSION."\r\n"
                 . "Content-Type: text/html\r\n"
                 . "Content-Length: 0\r\n"
                 . "Connection: close\r\n"
                 . "\r\n";

        fputs ($fp, $request);
        do {
            $line = chop(fgets($fp));
        } while (!empty($line) and !feof($fp));

        $result = array();
        while (!feof($fp)) {
            array_push($result, chop(fgets($fp)));
        }
        fclose($fp);

        if ($result) {
            if ($this->_logfunc !== null) {
                call_user_func($this->_logfunc, $_SERVER['REMOTE_ADDR'], $query, $result[0]);
            }
            $tmpinfo = explode('|', $result[0]);
            $this->_lasterror = $tmpinfo[0];
            if (count($tmpinfo)>3) {
                $this->_efcalls = $tmpinfo[3];
            } else {
                $this->_efcalls = $tmpinfo[2];
            }
        } elseif ($this->_logfunc !== null) {
            call_user_func($this->_logfunc, $_SERVER['REMOTE_ADDR'], $query, 'No result');
        }
        return $result;
    }

    /** Überprüft einen User bei Klamm auf Gültigkeit
     * Parameter:
     *   $klammid - KlammID des zu überprüfenden Users
     *   $losepw  - Losepasswort des zu überprüfenden Users
     * Rückgabe:
     *   Nickname, wenn der User erfolgreich überprüft wurde
     *   false, falls der User nicht überprüft werden konnte oder ein Fehler
     *          aufgetreten ist (Fehlercode in lasterror gespeichert)
     ***********************************************************************/
    function validate($klammid, $losepw = null)
    {
        if ($this->_goodtest) {
            return substr(md5(time()),0,8);
        }

        $efQuery = 'klamm/validate.php?'.$this->_efstr.'&k_id='.$klammid;
        if ($losepw !== null) {
            $efQuery .= '&l_pw='.urlencode($losepw);
        }

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }
        $kres = explode('|', $kret[0]);

        return $kres[0] == 1001
            ? $kres[1]
            : false;
    }

    /** Zieht Lose von einem User ein
     * Parameter:
     *  $anzahl  - Anzahl der Lose, die eingezogen werden sollen
     *  $betreff - Betreff der Transaktion
     *  $klammid - KlammID des Users, von dem die Lose eingeogen werden sollen
     *  $losepw  - Losepasswort des Users
     * Rückgabe:
     *   Transaktionscode, wenn die Lose erfolgreich eingezogen wurden
     *   false, falls die Lose nicht eingezogen werden konnten oder ein Fehler
     *          aufgetreten ist (Fehlercode in lasterror gespeichert)
     ***********************************************************************/
    function getlose($anzahl, $betreff, $klammid, $losepw)
    {
        $tcode = md5(uniqid(time()));
        if ($this->_goodtest) {
            return $tcode;
        }

        $s = urlencode($betreff);
        $efQuery = 'lose/get.php?'.$this->_efstr.'&k='.$this->_efkennung.'&k_id='.$klammid.'&l_pw='.urlencode($losepw).'&s='.$s.'&n='.$anzahl.'&code='.$tcode;

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }
        $kres = explode('|', $kret[0]);
        $this->_eflose = $kres[1];
        $this->_userlose = $kres[2];
        $this->_efcalls = $kres[4];
        return $kres[0] != 1001
            ? false
            : $tcode;
    }

    /** Sendet Lose an einen User
     * Parameter:
     *  $anzahl  - Anzahl der Lose, die versandt werden sollen
     *  $betreff - Betreff der Transaktion
     *  $klammid - KlammID des Users, dem die Lose gesendet werden sollen
     *  $losepw  - Losepasswort des Users (optional)
     * Rückgabe:
     *   Transaktionscode, wenn die Lose erfolgreich eingezogen wurden
     *   false, falls die Lose nicht versandt werden konnten oder ein Fehler
     *          aufgetreten ist (Fehlercode in lasterror gespeichert)
     ***********************************************************************/
    function sendlose($anzahl, $betreff, $klammid, $losepw = null)
    {
        $tcode = md5(uniqid(time()));
        if ($this->_goodtest) {
            return $tcode;
        }

        $s = urlencode($betreff);
        $efQuery = 'lose/send.php?'.$this->_efstr.'&k='.$this->_efkennung.'&k_id='.$klammid.'&s='.$s.'&n='.$anzahl.'&code='.$tcode;
        if (!empty($losepw)) {
            $efQuery .= '&l_pw='.urlencode($losepw);
        }

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }
        $kres = explode('|', $kret[0]);
        $this->_eflose = $kres[1];
        $this->_userlose = $kres[2];
        $this->_efcalls = $kres[4];
        return $kres[0] != 1001
            ? false
            : $tcode;
    }

    /** Gibt den Losestand eines Users zurück
     * Parameter:
     *  $klammid - KlammID des Users, dessen Losestand ermittelt werden soll
     *  $losepw  - Losepasswort des Users
     *  $refresh - Gibt an, ob die aktuellen Daten oder (falls vorhanden) die
     *             gespeicherten Werte einer letzten Operation genutzt werden
     *             sollen (optional, default=false)
     * Rückgabe:
     *   Losestand des Users
     *   false, falls der Losestand nicht ermittelt werden konnte oder ein
     *          Fehler aufgetreten ist (Fehlercode in lasterror gespeichert)
     * !! Beachte !!
     *  Die Rückgabe dieser Funktion muss besonders abgefragt werden, da der
     *  Losestand eines Users auch 0 sein kann, was PHP auch als false
     *  interpretiert. Daher muss nach der Abfrage mittels
     *    if ($rueckgabe === false) fehlerbehandlung...
     *  überprüft werden, ob die Rückgabe false war. Eine Überprüfung mittels
     *    if ($rueckgabe == false) fehlerbehandlung...
     *  würde bei einem Losestand von 0 Losen auch die Fehlerbehandlung
     *  einleiten.
     ***********************************************************************/
    function getuserlose($klammid, $losepw, $refresh = false)
    {
        if ($this->_goodtest) {
            return mt_rand(0, 1000000);
        }

        if ($this->_userlose != null and !$refresh) {
            return $this->_userlose;
        }

        $efQuery = 'lose/saldo.php?'.$this->_efstr.'&k_id='.$klammid.'&l_pw='.urlencode($losepw);

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        $this->_userlose = $kres[1];
        $this->_efcalls = $kres[3];
        $this->_eflose = $kres[4];
        return $kres[0] != 1001
            ? false
            : $kres[1];
    }

    /** Liest statistische Werte eines Users aus
     * Parameter:
     *  $klammid - klammID des Users
     *  $statspw - Statistik-Passwort des Users (nicht Losepasswort!)
     *
     * Rückgabe:
     *  false, falls ein Fehler aufgetreten ist
     *  Ansonsten ein Array mit folgendem Aufbau:
     *   'msg'         => Anzahl neuer Nachrichten für den User
     *   'pn'          => Anzahl neuer PNs für den User
     *   'bew'         => Anzahl neuer Bewertungen des Users
     *   'gb'          => Anzahl neuer Gästebucheinträge
     *   'membernews'  => Neue Membernews für den User?
     *   'umfrage'     => Umfrage auf der Startseite?
     *   'special'     => Spezialfenster auf der Startseite?
     *   'reload'      => Sekunden, die der User noch im Reload ist
     *   'kontostand'  => Kontostand des Users in Euro
     *   'lose'        => Anzahl der Lose des Users
     *   'lose_tresor' => Anzahl der Lose im Tresor
     *   'lose_spent'  => Anzahl der gespendeten Lose
     *   'lose_shred'  => Anzahl der geshredderten Lose
     *   'last_trans'  => Timestamp der letzten Transaktion
     *   'gender'      => Geschlecht (1 - männlich, 2 - weiblich)
     *   'birthday'    => Timestamp des Geburtstages
     *   'refs'        => Array[1..5] mit der Anzahl der Refs der
     *                    jeweiligen Ebene
     *   'myfriends'   => Array[0..x] aller myFriends, die online sind
     ***********************************************************************/
    function getuserstatistics($klammid, $statspw)
    {
        $efQuery = 'klamm/data.php?'.$this->_efstr.'&k_id='.$klammid.'&s_pw='.urlencode($statspw);

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        if ($kres[0] != 1001) {
            return false;
        }

        $result = array();
        // Zeile 1: msg|pn|bew|gb|membernews|umfrage|special|reload|kontostand
        $t = explode('|', $kret[1]);
        $result['msg'] = $t[0];
        $result['pn'] = $t[1];
        $result['bew'] = $t[2];
        $result['gb'] = $t[3];
        $result['membernews'] = $t[4];
        $result['umfrage'] = $t[5];
        $result['special'] = $t[6];
        $result['reload'] = $t[7];
        $result['kontostand'] = $t[8];
        // Zeile 2: refsE1|refsE2|refsE3|refsE4|refsE5|nextmoney
        $t = explode('|', $kret[2]);
        $result['refs'] = array();
        $result['refs'][1] = $t[0];
        $result['refs'][2] = $t[1];
        $result['refs'][3] = $t[2];
        $result['refs'][4] = $t[3];
        $result['refs'][5] = $t[4];
        $result['nextmoney'] = $t[5];
        // Zeile 3: myfriend1|myfriend2|myfriend3|....
        $result['myfriends'] = explode('|', $kret[3]);
        // Zeile 4: #lose|#tresor|#gespendet|#geshreddert|last_trans
        $t = explode('|', $kret[4]);
        $result['lose'] = $t[0];
        $result['lose_tresor'] = $t[1];
        $result['lose_spent'] = $t[2];
        $result['lose_shred'] = $t[3];
        $result['last_trans'] = $t[4];
        // Zeile 5: geschlecht|geburtstag
        $t = explode('|', $kret[5]);
        $result['gender'] = $t[0];
        $result['birthday'] = $t[1];

        return $result;
    }

    /** Sendet Lose an einen anderen EF-Account oder den Tresor
     * Parameter:
     *  $anzahl  - Anzahl der Lose, die versandt werden sollen
     *  $betreff - Betreff der Transaktion
     *  $ef_id   - ID des EF-Accounts, dem die Lose gesendet werden sollen
     *             oder -1, um die Lose im Tresor zu sichern (hierbei ist
     *             die Verwendung von ef_savelose() vorzuziehen)
     * Rückgabe:
     *   Transaktionscode, wenn die Lose erfolgreich eingezogen wurden
     *   false, falls die Lose nicht versandt werden konnten oder ein Fehler
     *          aufgetreten ist (Fehlercode in lasterror gespeichert)
     ***********************************************************************/
    function ef_sendlose($anzahl, $betreff, $ef_id)
    {
        $tcode = md5(uniqid(time()));
        if ($this->_goodtest) {
            return $code;
        }

        $efQuery = 'lose/efsend.php?'.$this->_efstr.'&k='.$this->_efkennung.'&n='.$anzahl.'&empf='.$ef_id.'&code='.$tcode;
        if ($ef_id != -1) {
            $efQuery .= '&s='.urlencode($betreff);
        }

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        $this->_eflose = $kres[1];
        $this->_efcalls = $kres[4];
        return $kres[0] != 1001
            ? false
            : $tcode;
    }

    /** Sichert Lose in den Tresor
     * Parameter:
     *  $anzahl  - Anzahl der Lose, die gesichert werden sollen
     * Rückgabe:
     *   Transaktionscode, wenn die Lose erfolgreich gesichert wurden
     *   false, falls die Lose nicht gesichert werden konnten oder ein Fehler
     *          aufgetreten ist (Fehlercode in lasterror gespeichert)
     ***********************************************************************/
    function ef_savelose($anzahl)
    {
        return $this->ef_sendlose($anzahl, '', -1);
    }

    /** Gibt den Losestand Deines EF-Accounts (bzw des EF-Tresors) zurück
     * Parameter:
     *  $tresor  - Losestand des Tresors anzeigen (Optional, default=false)
     *  $refresh - Gibt an, ob die aktuellen Daten oder (falls vorhanden) die
     *             gespeicherten Werte einer letzten Operation genutzt werden
     *             sollen (optional, default=false)
     * Rückgabe:
     *   Losestand Deines EF-Accounts/Tresors
     *   false, falls der EF-Losestand nicht ermittelt werden konnte oder ein
     *          Fehler aufgetreten ist (Fehlercode in lasterror gespeichert)
     * !! Beachte !!
     *  Bei dieser Funktion gilt die gleiche Bemerkung wie bei getuserlose().
     ***********************************************************************/
    function geteflose($tresor = false, $refresh = false)
    {
        if ($this->_goodtest) {
            return mt_rand(0, 1000000);
        }

        if (!$tresor and $this->_eflose != null and !$refresh) {
            return $this->_eflose;
        }

        $efQuery = 'lose/efstatus.php?'.$this->_efstr;

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        $this->_efcalls = $kres[2];
        $this->_eflose = $kres[3];
        return $kres[0] != 1001
            ? false
            : ($tresor
                ? $kres[4]
                : $kres[3]);
    }

    /** Liest allgemein Transaktionen aus
     * Parameter:
     *  $query -
     * Optionale Parameter:
     *  $skip    - Überspringt <skip> Transaktionen
     *  $limit   - Liest <limit> Transaktionen aus
     *  $inout   - 1=Einnahmen, 2=Ausgaben
     *  $ab_tid  - Nur Transaktionen mit ID grösser <ab_tid>
     *  $ab_time - Nur Transaktionen nach <ab_time>
     *  $type    - 0=User, 1=EF-Skript, 2=EF-Account
     *  $target  - 0=System, 1=User, 2=EF-Account
     *
     * Rückgabe:
     *  false bei einem aufgetretenen Fehler
     *  Ansonsten ein Array[0..x] gefüllt mit Arrays des folgenden Aufbaus,
     *   die die Transaktionen des Users enthalten:
     *    'id'        => Transaktions ID
     *    'target'    => Empfänger/Sender (0 - System, 1 - Klamm-User, 2 - EF-Account)
     *    'type'      => Transfertyp (0 - User, 1 - EF-Script, 2 - EF-Account)
     *    'timestamp' => Unix-Timestamp der Transaktion
     *    'userid'    => klamm/EF-ID des Empfängers/Senders
     *    'lose'      => +/- Lose
     *    'kennung'   => Verwendete Kennung
     *    'subject'   => Betreff der Transaktion
     ***********************************************************************/
    function _gettransactions($efQuery, $skip = 0, $limit = 0, $inout = null, $ab_tid = null, $ab_time = null, $type = null, $target = null)
    {
        if ($inout !== null) {
            $efQuery .= '&inout='.$inout;
        }
        if ($skip != 0 or $limit != 0) {
            $efQuery .= '&limit='.$skip.','.$limit;
        }
        if ($ab_tid !== null) {
            $efQuery .= '&ab_tid='.$ab_tid;
        }
        if ($ab_time !== null) {
            $efQuery .= '&ab_time='.$ab_time;
        }
        if ($type !== null) {
            $efQuery .= '&type='.$type;
        }
        if ($target !== null) {
            $efQuery .= '&target='.$target;
        }

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        if ($kres[0] != 1001) {
            return false;
        }

        $count = $kres[1];
        $result = array();
        for ($i = 0; $i < $count; $i++) {
            $t = explode('|', $kret[1 + $i]);
            array_push($result, array(
                'id'        => $t[0],
                'target'    => $t[1],
                'type'      => $t[2],
                'timestamp' => $t[3],
                'userid'    => $t[4],
                'lose'      => $t[5],
                'kennung'   => $t[6],
                'subject'   => $t[7]
            ));
        }
        return $result;
    }

    /** Liest die Transaktionen eines Users ein
     * Parameter:
     *  $klammid - klammID des Users
     *  $statspw - Statistik-Passwort des Users (nicht Losepasswort!)
     * Optionale Parameter:
     *  [siehe _gettransactions()]
     *
     * Rückgabe:
     *  [siehe _gettransactions()]
     ***********************************************************************/
    function getusertransactions($klammid, $statspw, $skip = 0, $limit = 0, $inout = null, $ab_tid = null, $ab_time = null, $type = null, $target = null)
    {
        $efQuery = 'klamm/tlist.php?'.$this->_efstr.'&k_id='.$klammid.'&s_pw='.url($statspw);
        return $this->_gettransactions($efQuery, $skip, $limit, $inout, $ab_tid, $ab_time, $type, $target);
    }

    /** Liest die Transaktionen des EF-Accounts ein
     * Optionale Parameter:
     *  [siehe _gettransactions()]
     *
     * Rückgabe:
     *  [siehe _gettransactions()]
    ***********************************************************************/
    function geteftransactions($skip = 0, $limit = 0, $inout = null, $ab_tid = null, $ab_time = null, $type = null, $target = null)
    {
        $efQuery = 'lose/tlist.php?'.$this->_efstr;
        return $this->_gettransactions($efQuery, $skip, $limit, $inout, $ab_tid, $ab_time, $type, $target);
    }

    /** Überprüft eine getätigte Transaktion
     * Parameter:
     *  $tcode     - Eindeutiger Transaktionscode
     *  $timestamp - Unix-Timestamp der Transaktion (OUTPUT-Parameter)
     * Rückgabe:
     *   ReturnCode der Transaktion
     *   false, falls die Transaktion nicht überprüft werden konnte
     ***********************************************************************/
    function confirm($tcode, &$timestamp)
    {
        if ($this->_goodtest) {
            return true;
        }

        $efQuery = 'lose/efconfirm.php?'.$this->_efstr.'&code='.$tcode;

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        if ($kres[0] != 1001) {
            return false;
        }

        $timestamp = $kret[2];
        $tmpinfo = explode('|', $kret[1]);
        return $tmpinfo[0];
    }

    /** Liest die Statistiken von Klamm aus
     * Parameter:
     *  - keine -
     *
     * Rückgabe:
     *  false, falls ein Fehler aufgetreten ist
     *  Ein Array mit den Statistiken im folgenden Format:
     *   'users'        => Anzahl der angemeldeten User
     *   'activeusers'  => Anzahl der aktiven User
     *   'regyesterday' => Anzahl der gestrigen Anmeldungen
     *   'male'         => Prozentsatz der männlichen User
     *   'female'       => Prozentsatz der weiblichen User
     *   'age'          => Durchschnittsalter aller User
     *   'payout'       => Gesamtsumme an ausgezahlten Euros
     *   'payouts'      => Gesamtanzahl an Auszahlungen
     *   'userpayouts'  => Anzahl an ausgezahlten Usern
     *   'visits_yesterday' => Besucherzahl von gestern
     *   'visits_lastmonth' => Besucherzahl vom letzten Monat
     *   'hits_yesterday' => Anzahl der gestrigen Page Impressions
     *   'hits_lastmonth' => Page Impressions im letzten Monat
     *   'online_klamm' => Online-Benutzerzahl auf Klamm
     *   'online_chat'  => Online-Benutzerzahl im Chat
     *   'online_forum' => Online-Benutzerzahl im Forum
     *   'online_radio' => Online-Benutzerzahl des Radios
     *   'lose_daily'   => Anzahl der gesetzten Lose für die Tagesverlosung
     ***********************************************************************/
    function efgetstatistics()
    {
        $efQuery = 'klamm/stats.php?'.$this->_efstr;

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        if ($kres[0] != 1001) {
            return false;
        }

        $result = array();
        // Zeile 1: #user|#aktive_user|#anm_gestern|%m|%w|Øalter
        $t = explode('|', $kret[1]);
        $result['users'] = $t[0];
        $result['activeusers'] = $t[1];
        $result['regyesterday'] = $t[2];
        $result['male'] = $t[3];
        $result['female'] = $t[4];
        $result['age'] = $t[5];
        // Zeile 2: EURausgezahlt|#auszahlungen|#ausgez.user
        $t = explode('|', $kret[2]);
        $result['payout'] = $t[0];
        $result['payouts'] = $t[1];
        $result['userpayouts'] = $t[2];
        // Zeile 3: #besucher_gestern|#besucher_vormonat|#pi_gestern|#pi_vormonat
        $t = explode('|', $kret[3]);
        $result['visits_yesterday'] = $t[0];
        $result['visits_lastmonth'] = $t[1];
        $result['hits_yesterday'] = $t[2];
        $result['hits_lastmonth'] = $t[3];
        // Zeile 4: #online_klamm|#online_chat|#online_forum|#online_radio
        $t = explode('|', $kret[4]);
        $result['online_klamm'] = $t[0];
        $result['online_chat'] = $t[1];
        $result['online_forum'] = $t[2];
        $result['online_radio'] = $t[3];
        // Zeile 5: #gesetze lose tagesverlosung
        $t = explode('|', $kret[5]);
        $result['lose_daily'] = $t[0];

        return $result;
    }

    /** Liefert alle User aus der Abmeldequeue
     * Parameter:
     *  $timestamp - Nur Abmeldungen ab diesem Zeitpunkt (optional)
     * Rückgabe:
     *   Array[0..x] aller abgemeldeten User, das für jeden User
     *     ein weiteres Array mit den Feldern 'klammid' und 'timestamp'
     *     (Zeitpunkt des Abmeldens) enthält
     *   false, falls ein Fehler bei der Abfrage aufgetreten ist
     ***********************************************************************/
    function getcancelqueue($timestamp = null)
    {
        $efQuery = 'klamm/cancel.php?'.$this->_efstr;
        if ($timestamp != null) {
            $efQuery .= '&ab_time='.$timestamp;
        }

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        if ($kres[0] != 1001) {
            return false;
        }

        $result = array();
        for ($i = 0; $i < $kres[1]; $i++) {
            list($kid, $ts) = explode('|', $kret[1 + $i]);
            array_push($result, array(
                'klammid'   => $kid,
                'timestamp' => $ts
            ));
        }
        return $result;
    }

    /** Liefert alle gesperrten User
     * Parameter:
     *  $timestamp - Nur Sperrungen ab diesem Zeitpunkt (optional)
     * Rückgabe:
     *   Array[0..x] aller gesperrten User, das für jeden User
     *     ein weiteres Array mit den Feldern 'klammid' und 'timestamp'
     *     (Zeitpunkt der Sperrung) enthält
     *   false, falls ein Fehler bei der Abfrage aufgetreten ist
     ***********************************************************************/
    function getabusequeue($timestamp = null)
    {
        $efQuery = 'klamm/abuse.php?'.$this->_efstr;
        if ($timestamp != null) {
            $efQuery .= '&ab_time='.$timestamp;
        }

        if (($kret = $this->_efQuery($efQuery)) === false) {
            return false;
        }

        $kres = explode('|', $kret[0]);
        if ($kres[0] != 1001) {
            return false;
        }

        $result = array();
        for ($i = 0; $i < $kres[1]; $i++) {
            list($kid, $ts) = explode('|', $kret[1 + $i]);
            array_push($result, array(
                'klammid'   => $kid,
                'timestamp' => $ts
            ));
        }
        return $result;
    }

    /** Gibt eine Fehlermeldung zu einem EF-Errorcode zurück
     * Parameter:
     *  $error - EF-Errorcode (optional, falls nicht angegeben, wird der letzte
     *                         aufgetretene Fehler ausgewertet)
     * Rückgabe:
     *  String, der eine Fehlermeldung zu dem (übergegeben) Errorcode enthält
     *************************************************************************/
    function errorstr($error = null)
    {
        if ($error === null) {
            $error = $this->_lasterror;
        }

        return isset($this->_error_messages[$error])
            ? $this->_error_messages[$error]
            : 'Ausserordentlicher Fehler';
    }

    /** Gibt die Anzahl aller getätigten API-Aufrüfe zurück
     *********************************************************/
    function getquerycount()
    {
        return $this->_querycount;
    }

    /** Gibt die Anzahl der verbliebenen API-Anfragen zurück
     *********************************************************/
    function getefcalls()
    {
        return $this->_efcalls;
    }

    /**
     *********************************************************/
    function goodtest($state = true)
    {
        $this->_goodtest = $state;
    }

    /**
     *********************************************************/
    function setapiurl($url)
    {
        $this->_apiurl = $url;
    }

    /**
     *********************************************************/
    function setapipath($path)
    {
        $this->_apipath = $path;
    }
}
