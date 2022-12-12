# Contao Ical Bundle

Mit diesem Bundle kann für einen Contao Kalender eine ics-Datei erstellt werden, um diesen in anderen Programmen (z.Bsp. DAVx5, GoogleCalender, etc) zu abonnieren. 
## Installation
Please use the Contao Manager or run `composer require janborg/contao-ical-bundle` in your CLI to install the extension.


## Configuration
This extension is shipped with a default configuration. 
If you want to override these settings, you  can do this in your common configuration file located in `config/config.yml`.

Parameters:
- **defaultEndDateDays:** 
Maximale Anzahl Tage in der Zukunft an, die im Ical-Kalender berücksichtigt werden, wenn kein Ende angegeben wird
- **defaultEventDuration:** Zeit in Minuten an, die als Dauer für ein Event angegeben wird, wenn der Termin eine Start-, aber keine Endzeit hat

```yaml
# config/config.yml
# Contao Ical (default settings)
janborg_contaoical:
  defaultEndDateDays: 365
  defaultEventDuration: 60
```

