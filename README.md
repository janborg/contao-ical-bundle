[![GitHub license](https://img.shields.io/github/license/janborg/contao-ical-bundle)](https://github.com/janborg/contao-ical-bundle)
![Packagist Version](https://img.shields.io/packagist/v/janborg/contao-ical-bundle)
![Packagist](https://img.shields.io/packagist/dt/janborg/contao-ical-bundle)

# Contao Ical Bundle

Mit diesem Bundle kann für einen Contao Kalender eine ics-Datei erstellt werden, um diesen in anderen Programmen (z.Bsp. ICSx5, GoogleCalender, etc) zu abonnieren. 
## Installation
Bitte nutze den Contao Manager oder führe `composer require janborg/contao-ical-bundle` in deiner CLI aus, um die Erweiterung zu installieren.


## Konfiguration
Das Bundle verwendet eine Standardkonfiguration.Bei Bedarf können diese Einstellungen in der Datei `config/config.yml` überschrieben werden.

Parameters:
- **defaultEndDateDays:** 
Maximale Anzahl an Tagen in der Zukunft, die im Ical-Kalender berücksichtigt werden, wenn kein Ende angegeben wird
- **defaultEventDuration:** Zeit in Minuten, die als Dauer für ein Event angegeben wird, wenn der Termin eine Start-, aber keine Endzeit hat

```yaml
# config/config.yml
# Contao Ical (default settings)
janborg_contao_ical:
  defaultEndDateDays: 365
  defaultEventDuration: 60
```

## Event oder Kalender als .ics exportieren
### Über eigene Route
Das Bundle implementiert zwei neue Routes, über die eine Ical-Datei eines Events oder eines ganzen Kalenders heruntergeladen werden kann. Über entsprechende Apps (bspw.ICSx5 für Android) kann über diese Route auch ein Kalender abonniert werden.

- **/ical/event/{alias}**
Über diese Route kann für jedes Event eine entsprechende *.ics Datei heruntergeladen werden, es sei denn, der überliegende Kalender ist geschützt. In diesem Fall muss man als berechtigter FE-User angemeldet sein.
- **/ical/calendar/{ical_alias}**
Damit ein Kalender über diese Route exportiert und importiert werden kann, muss dies im jeweiligen Kalender aktiviert und der ical_alias hinterlegt werden. Wenn ein Kalender geschützt ist, muss man als berechtigter FE-User angemeldet sein.

### Über Datei unter "/share"
Bei bedarf kann zusätzlich zur Route eine Datei <em>/share/ical_alias.ics</em> abgelegt werden. Hier kann keine Prüfung erfolgen, ob der Kalender geschützt ist!

## ical in der /App modifizieren
Es besteht die Möglichkeit die Eventdaten, die als ical bereitgestellt werden, zu modifizieren oder mit eigenen Feldern zu erweiteren. Dazu kann ein EventListener auf den Event `EditVeventEvent` oder ein Hook `editVEvent` registriert werden.

### ical Daten über EventListener modifizieren
Beispiel:

```php
<?php

namespace App\EventListener;

use Janborg\ContaoIcal\Event\EditVeventEvent;
use Kigkonsult\Icalcreator\Vevent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: EditVEventEvent::class)]
class EditVeventListener
{
    public function __invoke(EditVeventEvent $event): Vevent
    {

        // Get the Vevent object and the CalendarEventsModel object from the event
        $vevent = $event->getVevent();
        $calendarEvent = $event->getCalendarEvent();

        // Modify the Vevent object as needed

        // return the modified Vevent object
        return $vevent;
    }
}

```


### ical Daten über Hook modifizieren (veraltet, nicht empfohlen)
Beispiel:

```php
<?php

namespace App\EventListener;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Kigkonsult\Icalcreator\Vevent;

#[AsHook('editVEvent')]
class EditVEventListener
{
    public function __invoke(Vevent $vEvent, CalendarEventsModel $objEvent): Vevent
    {
        // Add additional data or modify $vEvent …

        return $vEvent;
    }
}

```