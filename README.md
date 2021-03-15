# Triggerfish - Kodexempel
![Triggerfish - logotyp](https://stockholmsskrivbyra.se/wp-content/uploads/2018/12/Triggerfish-logo-705-1-1.png)
Hej! Här kommer koden ni bad om!

Jag tänkte att jag skickar över lite grejer från HelloWorld!-projektet. Desvärre kunde jag inte hitta ett projekt som visade på samtliga kunskaper, så jag bestämde mig för att
inkludera delar av två olika projekt inom hw.se. Det ena är lite mer PHP-orienterat, och det andra är nästan bara JavaScript. Nedan föler en kort förklaring om de två projekten

## Utskicksmodulen
Utskicksmodulen skapades i syfte att kunna göra ett relativt komplicerat urval på deltagare och ledare för att sedan kunna göra mail- eller sms-utskick till de individer som
"matchade" urvalet. 

Backenden är relativt stor, och är i princip en enda stor SQL-query-generator med lite andra funktioner. Koden är relativt skalbar, och det är enkelt att lägga till nya urval utan att behöva
ändra om i den befintliga koden.

## Incheckningsappen
Incheckningsappen är en webb-app som använder kameran för att skanna QR-koder. Syftet är att kunna skanna deltagares "biljetter" för att check in och ut dem från lägret.

När en deltagare blir in- eller ut-checkad skickas ett SMS till deltagerns föräldrar, samt så uppdateras en etikett i deltagerns kort i trello som är kopplad till den deltagaren. (Skrev även Trello-export-funktionaliteten)
