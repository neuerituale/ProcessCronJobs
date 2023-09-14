# ProcessCronJobs

## What it does
The module provides paths under which cronjobs can be registered. It lists all registered cronjobs and can execute individual ones manually. The last execution, the last status and, in the event of an error, the last error message are displayed.

## Features
- Übersicht
- Timings
- letzte Ausführung des Cron sowie aller einzelnen Crons
- Manuelles Ausführen
- Letzte Fehlermeldung
- Pfade bzw. Namespaces für mehrere Crons
- Geheimes Pfadsegemnt

## Install

1. Copy the files for this module to /site/modules/ProcessCronJobs/
2. In admin: Modules > Refresh. Install ProcessCronJobs.
3. Go to Setup > CronJobs

## Install via composer
1. Execute the following command in your website root directory.
   ```bash
   composer require nr/processcronjobs
   ```

## Configuration

`Modules` > `Configure` > `ProcessCronJobs`

## The CronJob object

| Option      | Type          | Default                   | Description                                                                                                                                                                                                                                                                                                                                                                                                                                |
|-------------|---------------|---------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `name`      | String        |                           | Einzigartiger Name in PascalCase z.B. `MyFirstCronJob`                                                                                                                                                                                                                                                                                                                                                                                     |
| `callback`  | Callable      | function(CronJob $cron){} | Funktion, die ausgeführt werden soll                                                                                                                                                                                                                                                                                                                                                                                                       |
| `lazyCron`  | null, String  | `null`                    | Wenn leer wird der CronJob ohne immer ohne Verzögerung ausgeführt, sobalt der Pfad aufgerufen wird.                                                                                                                                                                                                                                                                                                                                        |
| `ns`        | null, String  | `null`                    | Wenn leer wird der CronJob über den Standardpfad aufgerufen.                                                                                                                                                                                                                                                                                                                                                                               |
| `timing`    | Integer       | `CronJob::timingReady`    | Der CronJon kann entweder bei onInit (1) oder onReady (2) aufgerufen werden. OnInit ist früher und dementsprechend auch schneller allerdings stehen hier noch nicht alle Funktionen von ProcessWire zur Verfügung z.B. Page und Sprache.                                                                                                                                                                                                   |
| `disabled`  | Boolean       | `false`                   | Hiermit kann der Cronjob deaktiviert werden z.B. `disabled = $config->debug`                                                                                                                                                                                                                                                                                                                                                               |
| `trigger`   | Integer       | `CronJob::triggerNever`   | Zeigt den den letzten Trigger für die Ausführung an. Möglich Werte sind:<br />1 (Never): CronJob wurde noch nie ausgeführt<br />2 (Auto): Der CronJob wurde beim letzten mal direkt über den "echten" Cron ausgeführt (onDemand)<br />4 (Lazy): Der CronJob wurde verzögert über den LazyCron aufgerufen<br />8 (Force): Der CronJob wurde manuell aufgeführt<br />16 (Error): Der letzte Aufruf endete mit einem Fehler (siehe Protokoll) |
| `lastRun`   | Integer       | 0                         | Enthält den letzten Ausführungszeitpunkt als Unix Timestamp. Wird im ProcessWire Cache gespeichert und wieder abgerufen                                                                                                                                                                                                                                                                                                                    |
| `lastError` | String        |                           | Mögliche Fehlermeldung des letzten Aufrufs                                                                                                                                                                                                                                                                                                                                                                                                 |

## Todos