# PS5
Mit diesem Modul ist es möglich, ein PS4-System über IP-Symcon zu steuern.

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Installation](#2-installation)

## 1. Funktionsumfang 
* Wecken der PS5

## 2. Installation
Über den Module Store.

## Einrichtung
Die PS5 Instanz muss über den Objektbaum angelegt werden.

Feld | Erklärung
------------ | -------------
IP-Adresse | Hier die IP-Adresse der PS5 eintragen
User-Credentials | Hier die User-Credentials der PS5 eintragen

Um die User-Credentials herauszufinden, muss auf einem Rechner Remote Play installiert werden und mit Wireshark die UDP Pakete von Port 9302 gesnifft werden, wenn Remote Play sich versucht mit der Playstation 5 zu verbinden.
Es wird ein Paket mitgesnifft, welches aussieht wie ein HTTP Paket, dieses Paket enthält einen Zeichenfolge wie diese: user-credential:-NNNNNNN