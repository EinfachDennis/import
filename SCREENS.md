# Screens, Zustände, Übergänge

## Anmeldung
Reiter Anmelden / Registrieren, E-Mail, Passwort, Google-Button, Hinweis auf 0,50 $ Startguthaben.
→ immer weiter zur Username-Wahl.

## Username wählen (Pflicht)
Eingabe mit `@`-Präfix, Live-Prüfung (frei / vergeben / zu kurz), vier Vorschläge als Chips.
→ Zielrechner.

## Zielrechner (Wizard, 6 Seiten)
Fortschrittsbalken oben, Zurück/Weiter unten, „Später einstellen" jederzeit.
1. Geschlecht — Antippen springt automatisch weiter
2. Körperdaten (Größe, Gewicht, Alter)
3. Aktivitätsniveau — fünf Optionen mit Beschreibung und Faktor, Antippen springt weiter
4. Schritte pro Tag — Eingabe plus Schnellwerte 3k/6k/10k/15k, zeigt resultierende kcal
5. Ziel — drei Optionen mit kcal-Anpassung, Antippen springt weiter
6. Ergebnis — Tagesziel groß, Makros, offener Rechenweg, alle Eingaben mit „Ändern"-Sprung
→ Tab 1.

## Tab 1 · Rezepte
Kopf: Logo ETL, Theme-Umschalter, XP-Button (öffnet Rangliste). Suchfeld mit Live-Ergebnissen und
Löschen-Knopf. Filter-Chips horizontal scrollbar.
Inhalt: zweispaltiges Kartenraster. Bei Suchbegriff: Trefferzeile, darunter Ergebnisse, darunter
Sektion „Auch bei Chefkoch" (extern markiert). Leerzustand mit Button zum KI-Rezept.
Schwebender Einkaufslisten-Button unten rechts mit Anzahl.

## Rezept-Detail (Vollbild-Überlagerung)
Galerie oben, horizontal wischbar mit Punkt-Indikator: Bild 1 Rezeptbild, danach Nutzerfotos mit
„Nachgekocht von @user". Zurück-Knopf, Merken-Knopf, Quelle und Zeit als Badges.
Darunter: Titel, @Koch, Kochlöffel mit Anzahl · Nährwerte als vier Kacheln ·
„Ganzes Gericht ins Tagebuch" mit kcal für die eingestellten Portionen ·
Zutaten mit Portionen-Stepper (skaliert live), abhakbar, „Alles auf die Einkaufsliste" ·
Zubereitung nummeriert plus „Kochmodus" · Bewertungsblock · Kommentare mit Antworten und Bildern ·
Eingabezeile mit Foto-Knopf.

### Bewertungsblock, zwei Zustände
**Nicht gekocht:** Erklärung, deaktivierte Löffel, Button „Ich hab's gekocht (+50 XP)".
**Gekocht:** Badge „Gekocht", fünf antippbare Löffel, Bestätigungstext.

### Nachkoch-Dialog (Bottom-Sheet, nach „Ich hab's gekocht")
1. Bewertung 1–5 — **Pflicht**
2. Foto — **Pflicht**, mit Vorschau, Entfernen-Knopf, Badge „Verifiziert"
3. Kommentar — optional, +2 XP
Absenden gesperrt bis 1 und 2 vorliegen, mit Hinweis was fehlt.
→ Foto landet in der Galerie, Kommentar bei den Kommentaren, +50 XP (+2).

### Kochmodus (Vollbild, dunkel)
Ein Schritt groß, Fortschrittsbalken, Zurück/Weiter, Display bleibt an.
Am letzten Schritt „Fertig — geschafft!" → öffnet den Nachkoch-Dialog.

## Tab 2 · Tracking
Reiter Tag / Woche.
**Tag:** Kalorien-Ring (gegessen/Ziel), verbleibende kcal, drei Makro-Balken ·
drei Schnellzugriffe (Lebensmittel · Rezept · Foto analysieren) ·
Mahlzeitenliste mit Slot-Kürzel, Name, Quelle, kcal.
**Woche:** Ø kcal/Tag, „x von y Tagen im Zielbereich", Balken Mo–So (grün im Zielbereich,
orange außerhalb, grau ungetrackt), Rückblick-Liste, Datenquellen-Hinweis.

### Sheet: Lebensmittel suchen
Eigenes Suchfeld. Treffer aus Open Food Facts. Nur wenn dort nichts passt: USDA-Treffer mit
Hinweiszeile. Kein Treffer in beiden: Hinweis auf manuelle Eingabe oder Barcode.
Nach Auswahl: Mengen-Panel mit −/+ (10er-Schritte), Eingabefeld, Schnellwerte 30/100/150/250,
live gerechnete kcal und Makros, „Ins Tagebuch" oder „Zurück".

### Sheet: Ganzes Gericht eintragen
Mahlzeiten-Reiter, Rezeptliste mit Portionen-Stepper pro Zeile, Plus-Knopf trägt ein.

### Foto-Analyse
Gesperrt bei 0 $ Guthaben. Sonst: KI-Badge, Ergebnis mit Name, kcal, Makros,
abgerechneter Betrag, Genauigkeitshinweis (grob ±20 %), Buttons „Passt, übernehmen" und
„Korrigieren". Ohne Bestätigung wird nichts gespeichert.

## Tab 3 · KI-Rezept (mittlerer, erhabener Button)
Kopf mit Guthaben. Drei Blöcke, alle per „+ hinzufügen":
Das habe ich da · Mag ich nicht · Heute Lust auf (**startet leer, keine Vorgaben**).
Kostenhinweis mit KI-Badge. Generieren-Button (gesperrt bei 0 $).
Ladezustand → Ergebnis: Bild mit KI-Badge und „KI-Bild"-Knopf, Titel, Beschreibung,
drei Kacheln (kcal, Minuten, Protein), Zutaten, Schritte, abgerechneter Betrag,
„Speichern (+80 XP)" und „Neu".

## Tab 4 · Rezept erstellen
Reel-Import: vier Quellen als Kacheln (TikTok, Instagram, YouTube, Screenshot),
Hinweis auf das Android-Share-Sheet („Teilen → ETL").
Darunter von Hand: Titelbild (Foto wählen / KI-Bild), Titel, Portionen, Zeit,
Zutatenzeilen (Menge + Name, entfernen, hinzufügen), automatische Nährwerte pro Portion,
Zubereitung, „Veröffentlichen (+80 XP)".

### Sheet: Reel importieren
**Leer:** Link-Feld, KI-Hinweis mit Kosten, Button „Rezept auslesen".
**Läuft:** Spinner, „Reel wird gelesen …".
**Fertig:** KI-Badge mit abgerechnetem Betrag, editierbarer Titel, erkannte Zutatenliste,
Hinweis auf geschätzte Mengen, „Speichern (+25 XP)".

## Tab 5 · Profil
Kopf: Avatar, @Username, Level und XP, Zahnrad (Admin, nur für Admin-E-Mail),
Level-Fortschritt, vier Statistik-Kacheln.
Guthaben-Karte: Betrag, „Aufladen", Kein-Aufschlag-Hinweis, Verlauf mit jeder Buchung.
Bereiche: Meine Rezepte · Wochen-Essensplan · Offline gespeichert · Abzeichen · Rangliste.
Darstellung: Theme-Umschalter, Tutorial zurücksetzen. Abmelden.

### Sheet: Wochen-Essensplan
Schalter „Erinnerung um 12:00 Uhr" plus Vorschau-Knopf. Liste Mo–So mit Gericht, kcal und Zeit.
„Einkaufsliste aus Plan füllen".

### Benachrichtigung 12:00
Karte oben im Bild: Absender ETL, „Heute auf dem Plan: <Gericht>", kcal und Zeit,
„Rezept öffnen" und „Später". Verschwindet nach wenigen Sekunden von selbst.

### Sheet: Rangliste
Global All-Time, Platz, Avatar, @Name, Level, XP. Darunter Tabelle „So bekommst du XP"
und der Hinweis, dass Kleinkram nur 2 XP bringt.

### Sheet: Abzeichen
Dreispaltiges Raster, freigeschaltete voll deckend, gesperrte abgeblendet mit Fortschritt.

### Sheet: Guthaben aufladen
Hinweis auf PayPal Freunde & Familie mit Pflicht-Angabe des @Usernames in der Nachricht,
drei Beträge zur Orientierung, PayPal-Button, Hinweis auf manuelle Gutschrift.

### Sheet: Admin-Einstellungen
Nur für hinterlegte Admin-E-Mail. OpenRouter API-Key (maskiert), Modell, PayPal-Link,
Speichern. Hinweis, dass der Schlüssel serverseitig bleibt.

### Sheet: Einkaufsliste (schwebender Button)
Abhakbare Liste, zusammengefasste Zutaten, Zustand bleibt gespeichert.

## Coach-Marks
Beim ersten Besuch jedes Tabs eine dunkle Karte über der Tab-Leiste: Zähler „Tipp n/5",
Titel, zwei Sätze, „Verstanden" und „Alle aus". Im Profil neu startbar.
