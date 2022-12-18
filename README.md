# Contao Ical Bundle

Mit diesem Bundle kann für einen Contao Kalender eine ics-Datei erstellt werden, um diesen in anderen Programmen (z.Bsp. ICSx5, GoogleCalender, etc) zu abonnieren. 
## Installation
Bitte nutze den Contao Manager oder führe `composer require janborg/contao-ical-bundle` in deiner CLI aus, um die Erweiterung zu installieren.


## Konfiguration
Das Bundle verwendet eine Standardkonfiguration.Bei Bedarf können diese Einstellungen in der Datei `config/config.yml` überschrieben werden.

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

## Event oder Kalender als .ics exportieren
### Über eigene Route
Das Bundle implementiert zwei neue Routes, über die eine Ical-Datei eines Events oder eines ganzen Kalenders heruntergeladen werden kann. Über entsprechende Apps (bspw.ICSx5 für Android) kann über diese Route auch ein Kalender abonniert werden.

- **/ical/event/{alias}**
Über diese Route kann für jedes Event eine entsprechende *.ics Datei heruntergeladen werden, es sei denn, der überliegende Kalender ist geschützt. In diesem Fall muss man als berechtigter FE-User angemeldet sein.
- **/ical/calendar/{ical_alias}**
Damit ein Kalender über diese Route exportiert und importiert werden kann, muss die im jeweiligen Kalender aktiviert werden und der ical_alias hinterlegt werden. Wenn ein Kalender geschützt ist, muss man als berechtigter FE-User angemeldet sein.

### Über Datei unter "/share"
Bei bedarf kann zusätzlich zur Route eine Datei <em>/share/ical_alias.ics</em> abgelegt werden. Hier kann keine Prüfung erfolgen, ob der Kalender geschätzt ist!