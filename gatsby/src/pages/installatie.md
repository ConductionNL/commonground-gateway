  
# Installatie

[comment]: <> (De huwelijksplanner applicatie bestaat uit een aantal componenten en een/meerdere ui’s opgebouwd volgens het [commonground vijflagen model]. Hierbij kunnen onderliggende componenten worden gedeeld tussen applicaties, we raden daarom aan om alleen nieuwe componenten te installeren en reeds bestaande componenten te hergebruiken.  Als u meer inzicht van een component wilt kunt u op de titel klikken voor de VNG componenten catalogus of in de github repository kijken voor uitgebreidere beschrijvingen en  de broncode bestanden.  )

[comment]: <> (Alle componenten zijn als docker container beschikbaar via github packages &#40;in verband met het download maximum op dockerhub.io&#41;, de containers vind u derhalve rechtstreeks bij de repositories. Voor alle componenten zijn tevens HELM installatie bestanden beschikbaar. Deze kunt u zowel in de repositorie van het component terug vinden &#40;github&#41; als in de officieel HELM  hub [artifacthub.io]&#40;https://artifacthub.io/&#41;.)


[comment]: <> (## Configuratie)

[comment]: <> (De configuratie van de verschillende componenten vind plaats via de helm installatie van dat component, volg hiervoor de bij het component aangeleverde handleiding. Echter is er een algemeen punt voor alle componenten wat betreft het extern bereikbaar maken van deze componenten:)

[comment]: <> (Om een component naar buiten open te zetten zijn er drie stappen nodig:)

[comment]: <> (1. De value ingress.enabled moet op true staan)

[comment]: <> (2. De value ingress.host moet een hostname bevatten die geroute is naar de loadbalancer)

[comment]: <> (3. De value path moet correct staan. Voor een applicatie kan dit / zijn, maar voor componenten is /api/v1/{componentnaam} of /api/v1/{componentcode} aan te bevelen)

[comment]: <> (Voordat de componenten werkbaar zijn zal de database moeten worden voorbereid. Dit doen we met behulp van het volgende commando:  )

[comment]: <> (bin/console doctrine:schema:update -f  )

[comment]: <> (## Van componenten naar applicatie  )

[comment]: <> (De huwelijksplanner applicatie bestaat uit een reeks van componenten, om ze gezamenlijk een applicatie te laten vormen is het noodzakelijk om ze te laten samenwerken. Hiervoor is het nodig om de centrale spin in het web &#40;huwelijksplanner service&#41; toegang te geven tot de componenten zodat zij deze kan inrichten. De daarvoor benodigde configuratie is opgenomen in de &#40;helm&#41; installatiebestanden en beschrijving van de huwelijksplanner ui &#40;waar de huwelijksplanner service bij in zit&#41;. Installeer deze daarom als laatste en leest de installatiehandleiding en configuratie beschrijving zorgvuldig voordat u het component installeert.  )

[comment]: <> (## Voorbeeld data)

[comment]: <> (Nadat de configuratie is afgehandeld kan er voorwoorden gekozen om een zet met voorbeeld gegevens in te laden &#40;voor bijvoorbeeld demo doeleinden&#41;. Om voorbeelddata in te laden moet deze data op drie componenten op volgorde worden ingeladen nadat de dependencies van het betreffende component zijn ingesteld:  )

[comment]: <> (- landelijketabellencatalogus  )

[comment]: <> (- brpservice  )

[comment]: <> (- trouw-service  )
  
[comment]: <> (Op deze componenten moet in de php container het volgende commando worden uitgevoerd:  )

[comment]: <> (bin/console doctrine:fixtures:load -n  )

[comment]: <> (De Trouw Service zal ook voorbeelddata inschieten naar de overige componenten.)
