<?php
// Beispieldatei
    require 'ExportForce.class.php';

    // Exportforce
    $id      = 12345;
    $pwd     = 'gott';
    $kennung = 4321;
    
    $ef = new ExportForce($id, $pwd, $kennung);

    // User
    $klammid = 27936;
    $losepw  = 'losepass';

    // User validieren
    if (!$ef->validate($klammid, $losepw)) {
        echo 'User konnte nicht validiert werden, EF-Fehler: '.$ef->errorstr().'<br>';
    } else {
        echo 'User wurde erfolgreich validiert!<br>';
    }

    // Lose einziehen
    if (!$ef->getlose(100, 'Einzahlung', $klammid, $losepw)) {
        echo 'Lose konnten nicht eingezogen werden, EF-Fehler: '.$ef->errorstr().'<br>';
    } else {
        echo 'Lose wurden eingezogen!<br>';
    }

    // Lose senden
    if (!$ef->sendlose(100, 'Auszahlung', $klammid)) {
        echo 'Lose konnten nicht versandt werden, EF-Fehler: '.$ef->errorstr().'<br>';
    } else {
        echo 'Lose wurden versandt<br>';
    }

    // Losestand eines Users abfragen
    $userlose = $ef->getuserlose($klammid, $losepw);
    if ($userlose === false) {
        echo 'Fehler beim Abfragen des Losestandes, EF-Fehler: '.$ef->errorstr().'<br>';
        
    } else {
        echo 'User hat '.$userlose.' Lose.<br>';
    }

    // EF-Losestand abfragen
    $eflose = $ef->geteflose();
    if ($eflose === false) {
        echo 'Fehler beim Abfragen des EF-Losestandes, EF-Fehler: '.$ef->errorstr().'<br>';
    } else {
        echo 'EF hat '.$eflose.' Lose.<br>';
    }

    // EF-Tresor-Losestand abfragen
    $eflose = $ef->geteflose(true);
    if ($eflose === false) {
        echo 'Fehler beim Abfragen des EF-Losestandes, EF-Fehler: '.$ef->errorstr().'<br>';
    } else {
        echo 'EF-Tresor hat '.$eflose.' Lose.<br>';
    }
