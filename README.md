<a href="https://www.symcon.de"><img src="https://img.shields.io/badge/IP--Symcon-5.0-blue.svg?style=flat-square"/></a>
<a href="https://styleci.io/repos/120787671"><img src="https://styleci.io/repos/120787671/shield?branch=master" alt="StyleCI"></a>
<br />

# IPS-PS4
Mit diesem Modul ist es möglich, ein PS4-System über IP-Symcon zu steuern.
Das Modul befindet sich noch in einer Beta Phase.

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Installation](#2-installation)
3. [Spenden](#3-spenden)

## 1. Funktionsumfang 
* Wecken der PS4 aus dem Ruhemodus
* PS4 in den Ruhemodus versetzen
* PS4 Spiele / Apps starten
* Button senden:
    * Hoch, Runter, Links, Rechts
    * Option
    * Playstation Taste (PS)
    * Enter, Zurück
 

## 2. Installation
Dieses Modul besteht aus zwei Modulen (PS4 und PS4 Dummy).
Die eigentlichen Funktionen sind in dem PS4 Modul vorhanden.
Das PS4-Dummy Modul wird benötigt, um die Playstation mit IP-Symcon zu verknüpfen.

### Einrichtung in IP-Symcon
Github Repository in IP-Symcon über **Kerninstanzen -> Modules -> Hinzufügen** einrichten

`https://github.com/Schnittcher/IPS-PS4.git` 

### Einrichtung der Instanzen

#### PS4-Dummy
Der PS4-Dummy wird unter Splitter Instanzen angelegt.
Beim Anlegen des PS4-Dummy Moduls wird gleichzeitig ein Multicast Socket mit angelegt und geöffnet.

#### PS4
Die PS4 Instanz wird im Objektbaum erzeugt.

Feld | Erklärung
------------ | -------------
IP-Adresse der PS4 | Hier die IP-Adresse der PS4 eintragen
Auto Login | Hier wird angegeben, ob beim aufwecken der PS4, der Benutzer direkt eingeloggt werden soll.
Bootzeit | Hier wird die Zeit eingetragen, die die Playstation in etwa zum booten benötigt.
Updatetimer | Hier wird die Zeit eingetragen, wie oft die PS4 nach dem aktuellen Status befragt werden soll.
User-Credentials | Hier die User-Credentials der PS4 eintragen - siehe Registrierung von IPS an der PS4
Gameliste | Hier können die Spiele / Apps eingetragen werden, welche über IPS gestartet werden sollen


Innerhalb der Testumgebung wird die PS4, registriert, weiteres ist hier zu finden: Registrierung von IPS an der PS4
 
### Registrierung von IPS an der PS4
Es wird die App PS4 Second Screen auf einem Smartphone oder Tablet benötigt.
Diese App auf dem Gerät öffnen und nach neuen Geräten im Netzwerk suchen.
Nun sollte eine PS4 mit dem Namen IP-Symcon gefunden (das ist das PS4-Dummy Modul) werden.
Diesen Eintrag auswählen und die App wird versuchen sich mit IP-Symcon zu verbinden.
Unterhalb der PS4-Dummy Instanz gibt es eine Variable **Credentials**.
Diese sollte nun eine längere Zeichenfolge enthalten, diese Zeichenfolge kopieren und im Konfigurationsformular des PS4 Moduls unter User-Credentials eintragen.
  
Um die Registrierung der PS4 abzuschließen, muss auf dem PS4 System unter Einstellungen -> Einstellungen der Verbindung über die mobile App ->
Gerät hinzufügen, das IP-Symcon Modul autorisiert werden. Dort wird ein 8 stelliger Pincode angezeigt.
Diesen Pincode nun in dem Konfigurationsformular der PS4 Instanz in dem Feld Pincode eingeben und den Button Register anklicken.

Nun sollte die PS4 mit IP-Symcon verknüpft sein.

## 3. Spenden

Dieses Modul ist für die nicht kommzerielle Nutzung kostenlos, freiweillige Unterstützungen für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

## Bildnachweise

### Playstation Logo
<div>Icons made by <a href="http://www.freepik.com" title="Freepik">Freepik</a> from <a href="https://www.flaticon.com/" title="Flaticon">www.flaticon.com</a> is licensed by <a href="http://creativecommons.org/licenses/by/3.0/" title="Creative Commons BY 3.0" target="_blank">CC 3.0 BY</a></div>