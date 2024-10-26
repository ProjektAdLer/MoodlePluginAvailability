# availability_adler

[![Coverage Status](https://coveralls.io/repos/github/ProjektAdLer/MoodlePluginAvailability/badge.svg?branch=main)](https://coveralls.io/github/ProjektAdLer/MoodlePluginAvailability?branch=main)

Dieses Plugin implementiert eine _availability condition_ für die Adler-Raum-Logik (`requiredSpacesToEnter`, bspw `(5)v((7)^(4))`).

Ist das Haupt-Plugin nicht installiert wird die durch dieses Plugin implementierte _availability condition_ immer `true` zurück geben


## Dependencies
> [!CAUTION]
> This plugin requires the `local_adler` plugin to be installed. Because moodle has a lot of problems with circular dependencies, 
> this plugin does not list `local_adler` as a dependency in the plugin manager.
> 
> UPDATE: Now removed all dependency checks for all plugins as I'm pissed of about moodle saying: no you cannot update, because
> the updated version of the dependency that IS ALREADY PRESENT IN THE CORRESPONDING DIRECTORY is "Unavailable".
> 
> Using this plugin without `local_adler` will result in unexpected behaviour and errors.

| Plugin      | Version |
|-------------|---------|
| local_adler | ~3.0.0  |



## Kompatibilität
Folgende Versionen werden unterstützt (mit mariadb und postresql getestet):

| Moodle Branch           | PHP Version |
|-------------------------|-------------|
| MOODLE_404_STABLE       | 8.1         |
| MOODLE_404_STABLE       | 8.2         |
| MOODLE_404_STABLE       | 8.3         |
| MOODLE_405_STABLE (LTS) | 8.1         |
| MOODLE_405_STABLE (LTS) | 8.2         |
| MOODLE_405_STABLE (LTS) | 8.3         |

## Installation
1. Dieses Plugin benötigt das Plugin `local_adler` als Abhängigkeit. Beide müssen zeitgleich installiert werden (= vor dem upgrade in die Moodle-Installation entpackt sein). Installation siehe `local_adler`
2. Plugin in moodle in den Ordner `availability/condition` entpacken (bspw` moodle/availability/condition/adler/version.php` muss es geben)
3. Moodle upgrade ausführen

## Dev Setup / Tests
Dieses Plugin nutzt Mockery für Tests.
Die composer.json von Moodle enthält Mockery nicht, daher enthält das Plugin eine eigene composer.json.
Diese ist (derzeit) nur für die Entwicklung relevant, sie enthält keine Dependencies die für die normale Nutzung notwendig sind.
Um die dev-dependencies zu installieren muss `composer install` im Plugin-Ordner ausgeführt werden.

## Plugin Dokumentation

### Parser für boolsche Algebra
Das Statement wird mittels einer rekursiven Methode ausgewertet. 
Zustände (true/false) werden temporär als 't'/'f' in den String geschrieben.
Aufgrund der geringen Anzahl an Operatoren (`v`, `^`, `!` und Klammern) funktioniert dieser Ansatz recht gut.
Bei Hinzufügen weiterer Operatoren dürfte dieser Ansatz schnell an seine Grenzen stoßen, ein komplexerer Parser mit Baumstruktur wäre dann sinnvoller.
Bei dem gegebenen Umfang ist die zusätzliche Komplexität aber nicht notwendig.
