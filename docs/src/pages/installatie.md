  
# Installatie

De huwelijksplanner applicatie bestaat uit een aantal componenten en een/meerdere UI’s opgebouwd volgens het (Common Ground vijflagenmodel). Hierbij worden onderliggende componenten gedeeld tussen applicaties.  Het wordt aangeraden aan om alleen nieuwe componenten te installeren en verder bestaande componenten te hergebruiken.  Voor meer inzicht in een component  kunt u op de titel klikken voor de VNG-componenten catalogus of in de GitHub repository kijken voor uitgebreidere beschrijvingen en  de broncode-bestanden.  

Alle componenten zijn als Docker container beschikbaar via GitHub packages(in verband met  het downloadlimiet van Docker.io). Deze containers zijn in de repositories te vinden. Voor alle componenten zijn bovendien HELM-installatie bestanden beschikbaar. Deze zijn zowel in de repository van het component terug vinden (GitHub) als in de officiële HELM Hub [artifacthub.io](https://artifacthub.io/).


## Configuratie

De configuratie van de verschillende componenten vinden plaats via de Helm installatie van dat component. Volg hiervoor de bij het component aangeleverde handleiding. Voor  het extern bereikbaar maken  van alle componenten  zijn er drie stappen nodig:

1. De `value ingress.enabled` moet op `true` staan.

2. De `value ingress.host` moet een hostname bevatten die gerouteerd is naar de loadbalancer

3. De value `path` moet correct staan. Voor een applicatie is dit mogelijk,  maar voor componenten is `/api/v1/{componentnaam}` of `/api/v1/{componentcode} ` aan te bevelen

Voordat de componenten werkbaar zijn moet  de database worden voorbereid. Dit is te doen met behulp van het volgende commando:  

`bin/console doctrine:schema:update -f ` 

## Van componenten naar applicatie  

De huwelijksplanner applicatie bestaat uit serie componenten, om hiervan een applicatie te maken is samenwerking noodzakelijk. Hiervoor moet de centrale spin in het web (huwelijksplanner service) toegang krijgen tot de componenten zodat zij deze kan inrichten. De daarvoor benodigde configuratie is opgenomen in de (Helm) installatiebestanden en beschrijving van de huwelijksplanner UI (waar de huwelijksplanner service bij in zit). Installeer deze daarom als laatste en lees de installatiehandleiding en configuratie beschrijving zorgvuldig voordat u het component installeert.  

## Voorbeeld data 

Nadat de configuratie is afgehandeld kan ervoor worden gekozen om een set met voorbeeldgegevens in te laden (voor bijvoorbeeld demo-doeleinden). Om voorbeelddata in te laden moet deze data bij drie componenten op de juiste volgorde worden ingeladen, nadat de dependencies van de betreffende componenten zijn ingesteld.

- landelijketabellencatalogus  

- BRP-service 

- trouw-service 
  
Op deze componenten moet in de PHP-container het volgende commando worden uitgevoerd:  

`bin/console doctrine:fixtures:load -n` 

De Trouw Service zal ook voorbeelddata inschieten naar de overige componenten.
