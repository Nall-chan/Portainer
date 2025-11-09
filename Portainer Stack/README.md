[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FPortainer%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)
]()
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FPortainer%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)
](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v80-v81-q3-2025/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/Portainer/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/Portainer/actions) [![Run Tests](https://github.com/Nall-chan/Portainer/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/Portainer/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# Portainer Stack <!-- omit in toc -->  

### Inhaltsverzeichnis  

- [Inhaltsverzeichnis](#inhaltsverzeichnis)
- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
  - [Statusvariablen](#statusvariablen)
  - [Profile](#profile)
- [6. Visualisierung](#6-visualisierung)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Instanz welche den Zustand eines Portainer Stack in Symcon abbildet. 

### 2. Voraussetzungen

- IP-Symcon ab Version 8.1
- Portainer Installation  

### 3. Software-Installation

* Dieses Modul ist Bestandteil der [Portainer-Library](../README.md#4-software-installation).  

### 4. Einrichten der Instanzen in IP-Symcon

 Unter `Instanz hinzufügen` ist das `Portainer Stack`-Modul unter dem Hersteller `Portainer` aufgeführt.  
![Module](../imgs/module.png) 

 Es wird empfohlen diese Instanz über die dazugehörige Instanz des [Portainer Konfigurator-Moduls](../Portainer%20Configurator/README.md) anzulegen.  

![Config](imgs/config.png)  

### Konfigurationsseite  

| Eigenschaft   | Text           | Beschreibung                                               |
| ------------- | -------------- | ---------------------------------------------------------- |
| EnvironmentId | Environment ID | Portainer ID des Environment unter welchem der Stack läuft |
| StackId       | Stack ID       | Stack ID des Stack                                         |
| Interval      | Intervall      | Intervall der Statusaktualisierung                         |

### 5. Statusvariablen  

Die Statusvariablen werden automatisch erzeugt.  

#### Statusvariablen

| Name                 | Typ     |
| -------------------- | ------- |
| Name                 | string  |
| Status               | integer |
| Typ                  | integer |
| Erstellungsdatum     | integer |
| Aktualisierungsdatum | integer |

### 6. Visualisierung

#### Kachel Visualisierung  
![Kachel](imgs/tile.png)  

#### WebFront  
![WebFront](imgs/wf.png)  

### 7. PHP-Befehlsreferenz

Zustandsabfrage des Stack.  
```php
boolean PORTAINER_RequestState(integer $InstanzID);
```

Beispiel:
```php
PORTAINER_RequestState(12345);
```
---

Stack starten.  
```php
boolean PORTAINER_StartStack(integer $InstanzID);
```

Beispiel:
```php
PORTAINER_StartStack(12345);
```
---

Stack anhalten.  
```php
boolean PORTAINER_StopStack(integer $InstanzID);
```

Beispiel:
```php
PORTAINER_StopStack(12345);
```
## 8. Aktionen

Es gibt keine speziellen Aktionen für dieses Modul.  

## 9. Anhang

### 1. Changelog

[Changelog der Library](../README.md#2-changelog)

### 2. Spenden

  Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share) 


## 10. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
