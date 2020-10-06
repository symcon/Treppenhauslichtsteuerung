# Treppenhauslichtsteuerung
Nachdem ein Auslöser aktiviert wird, geht das Licht im Treppenhaus an. Wird der Auslöser wiederholt aktiviert bleibt das Licht an und der Timer wird zurückgesetzt. Erst wenn für eine vorgegebene Zeit keine weitere Auslösung stattfindet wird das Licht ausgeschaltet.


### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Auswahl von Ein- und Ausgabevariablen in einer Liste.
* Auswahl der Dauer bevor das Licht ausgeschaltet wird.
* Möglichkeit die verbleibende Zeit bis zum Auschalten anzuzeigen.

### 2. Voraussetzungen

- IP-Symcon ab Version 5.0

### 3. Software-Installation

* Über den Module Store das Modul Treppenhauslichtsteuerung installieren.
* Alternativ über das Module Control folgende URL hinzufügen:
`https://github.com/symcon/Treppenhauslichtsteuerung`

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" kann das 'Treppenhauslicht'-Modul mithilfe des Schnellfilters gefunden werden.
    - Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name                      | Beschreibung
------------------------- | ---------------------------------
Eingabesensoren           | Liste der Eingabesensoren, bei deren Aktivierung das Licht aktiviert werden soll, z.B. Bewegungssensoren oder Taster - Das Licht wird aktiviert sobald eine Variable auf aktiv gesetzt wird. Als aktiv gelten hierbei Variablen mit einem Wert, der nicht false, 0, oder "" ist. Sollte die Variable ein .Reversed Profil haben gelten die genannten Werte als aktiv.
Ausgabevariablen          | Liste der Variablen, welche auf den Maximalwert geschaltet werden. Sollte eine Variable ein .Reversed Profil haben wird diese auf den Minimalwert geschaltet. Variablen des Typs String werden nicht geschaltet
Dauer                     | Nachdem die ausgewählte Dauer ohne weitere Auslösung eines Eingabesensors vergeht, wird das Licht deaktiviert.
Restlaufzeit anzeigen     | Wenn aktiv wird die verbleibende Zeit bis zum Ausschalten in einer Variable angezeigt.
Aktualisierungsintervall  | Das Intervall, in dem die "Restzeit" Variable aktualisiert wird.

### 5. Statusvariablen und Profile

##### Statusvariablen

Name                       | Typ     | Beschreibung
-------------------------- | ------- | ---------------------------
Treppenhaussteuerung aktiv | Boolean | Die Variable gibt an, ob die Treppenhaussteuerung aktiviert ist
Restzeit                   | String  | Wenn "Restlaufzeit anzeigen" aktiv ist wird hier die verbleibende Zeit bis zum Auschalten angezeigt

##### Profile:

Es werden keine zusätzlichen Profile hinzugefügt.

### 6. WebFront

Über das WebFront werden keine zusätzlichen Informationen angezeigt.

### 7. PHP-Befehlsreferenz

`boolean THL_Start(integer $InstanzID);`  
Aktiviert das Licht im Treppenhaus und startet den Timer, welcher das Licht wieder deaktiviert. Bei wiederholtem Aufruf wird der Timer zurückgesetzt.

Beispiel:  
`THL_Start(12345);`

`boolean THL_Stop(integer $InstanzID);`
Deaktiviert das Licht im Treppenhaus und den Timer.

Beispiel:
`THL_Stop(12345);`

`boolean THL_SetActive(integer $InstanzID, boolean $Wert);`
Aktiviert oder deaktiviert die Treppenhauslichtsteuerung. Wurde das Treppenhauslicht durch die Steuerung eingeschaltet und die Steuerung wird deaktiviert, so wird der aktuelle Steuervorgang noch zu Ende geführt. Allerdings wird der Timer bei erneutem Auslösen des Eingabesensors nicht zurückgesetzt. Das Treppenhauslicht wird also trotz deaktivierter Steuerung nach Ablauf des Timers ausgeschaltet.

Beispiel:
`THL_SetActive(12345, true);`
