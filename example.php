<?php
// Beispieldatei

    // Exportforce
    $exportforceid      = 12345;
    $exportforcepwd     = 'gott';
    $exportforcekennung = 4321;

    // User
    $klammid = 27936;
    $losepw  = 'losepass';

    require 'ExportForce.class.php';
    $exportforce = new ExportForce($exportforceid, $exportforcepwd, $exportforcekennung);

    // User validieren
    if (!$exportforce->validate($klammid, $losepw)) {
        echo 'User konnte nicht validiert werden, EF-Fehler: '.$exportforce->errorstr().'<br>';
    } else {
        echo 'User wurde erfolgreich validiert!<br>';
    }

    // Lose einziehen
    if (!$exportforce->getlose(100, 'Einzahlung', $klammid, $losepw)) {
        echo 'Lose konnten nicht eingezogen werden, EF-Fehler: '.$exportforce->errorstr().'<br>';
    } else {
        echo 'Lose wurden eingezogen!<br>';
    }

    // Lose senden
    if (!$exportforce->sendlose(100, 'Auszahlung', $klammid)) {
        echo 'Lose konnten nicht versandt werden, EF-Fehler: '.$exportforce->errorstr().'<br>';
    } else {
        echo 'Lose wurden versandt<br>';
    }

    // Losestand eines Users abfragen
    $userlose = $exportforce->getuserlose($klammid, $losepw);
    if ($userlose === false) {
        echo 'Fehler beim Abfragen des Losestandes, EF-Fehler: '.$exportforce->errorstr().'<br>';
        
    } else {
        echo 'User hat '.$userlose.' Lose.<br>';
    }

    // EF-Losestand abfragen
    $eflose = $exportforce->geteflose();
    if ($eflose === false) {
        echo 'Fehler beim Abfragen des EF-Losestandes, EF-Fehler: '.$exportforce->errorstr().'<br>';
    } else {
        echo 'EF hat '.$eflose.' Lose.<br>';
    }

    // EF-Tresor-Losestand abfragen
    $eflose = $exportforce->geteflose(true);
    if ($eflose === false) {
        echo 'Fehler beim Abfragen des EF-Losestandes, EF-Fehler: '.$exportforce->errorstr().'<br>';
    } else {
        echo 'EF-Tresor hat '.$eflose.' Lose.<br>';
    }
