# klamm.de's ExportForce in PHP

Diese Klasse bildet die Funktionen der [ExportForce-API][ef] von klamm.de
ab.

[ef]: http://ef.klamm.de "ExportForce-API"

## Die Logfunktion

Beim Erzeugen der Klasse kann mittels des Parameters *$log_function* der Name
einer Log-Funktion angegeben werden, die bei jedem Query aufgerufen wird.
Die Funktion muss folgende Signatur aufweisen:

    function log($ip, $query, $result) {
        // ...
    }

Die Parameter im Einzelnen:

* **$ip**     - Die IP des Aufrufs
* **$query**  - Die an ExportForce gesendete Anfrage
* **$result** - Die erste von ExportForce zurückgelieferte Zeile
                bzw. *No connect* falls EF nicht ansprechbar ist
                bzw. *No result* wenn EF nichts zurücklieferte

> **!! WICHTIG !!**
>
>   Beim Übergeben der Funktion darf nur der Funktionsname übergeben
>   werden!

Wie die Funktion das Loggen übernimmt, bleibt dem Anwender
überlassen. Hier ein funktionierendes Anwendungsbeispiel:

    <?php
        class Logger {
            const EF_LOG = '../logs/efqueries.log';

            static function ExportForce($ip, $query, $result) {
                if ($fp = fopen(self::EF_LOG, 'a')) {
                    fputs($fp, time() . ' ' . $ip . ' ' . $query . ' ' . $result . "\n");
                    fclose($fp);
                }
            }
        }
        // ...
        $ef = new ExportForce($id, $pw, $kennung, true, 'Logger::ExportForce');
         

## Hinweis zur Verwendung mit D-EF.de (DevelopmentExportForce)

Diese Klasse ist durch zwei einzufügende Zeilen problemlos mit D-EF.de nutzbar:

    $exportforce->setapiurl('www.d-ef.de');
    $exportforce->setapipath = ('');

D-EF.de ist momentan allerdings offline und es ist eher unwahrscheinlich, dass die
Seite noch einmal wiederbelebt wird. Dieser Eintrag dient somit nur der Vollständigkeit.
