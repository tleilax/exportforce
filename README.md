# klamm.de's ExportForce in PHP

Diese Klasse bildet die Funktionen der [ExportForce-API][ef] von klamm.de
ab.

[ef]: http://ef.klamm.de "ExportForce-API"

## Die Logfunktion

Beim Erzeugen der Klasse kann mittels des Parameters *$logfunc* der
Name einer selbst geschriebenen Log-Funktion angegeben werden,
die anschliessend bei jedem Query aufgerufen wird.
Die Funktion muss folgenden Aufbau haben:

    function funktionsname($ip, $query, $result);

Die Parameter im Einzelnen:

* **$ip**     - Die IP des Aufrufers des Skriptes
* **$query**  - Die an ExportForce gesendete Anfrage
* **$result** - Die erste von ExportForce zurückgelieferte Zeile
              oder *No connect*, falls EF nicht ansprechbar ist,
              bzw. *No result*, wenn EF nichts zurücklieferte

> **!! WICHTIG !!**
>
>   Beim Übergaben der Funktion darf nur der Funktionsname übergeben
>   werden!

Wie die Funktion das Loggen übernimmt, bleibt dem Anwender
überlassen. Hier nur ein funktionierendes Anwendungsbeispiel:

    <?php
     define('LOGFILE', './log/efqueries.log');
     function _logger($ip, $query, $result) {
       $fp = fopen(LOGFILE, 'a');
       if (!$fp)
         return;
       fputs($fp, time().' '.$ip.' '.$query.' '.$result."\r\n");
       fclose($fp);
     }
     // ...
     $ef = new ExportForce($efid, $efpw, $efkennung, true, '_logger');

## Hinweis zur Verwendung mit D-EF.de (DevelopmentExportforce)

Diese Klasse ist durch zwei einzufügende Zeilen problemlos mit D-EF.de nutzbar:

    $exportforce->setapiurl('www.d-ef.de');
    $exportforce->setapipath = ('');

D-EF.de ist momentan allerdings offline und es ist eher unwahrscheinlich, dass die
Seite noch einmal wiederbelebt wird. Dieser Eintrag dient somit nur der Vollständigkeit.