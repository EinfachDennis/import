# Auftrag: Android-App „ETL — Eat. Track. LevelUp."

Du baust eine Rezept-, Koch- und Kalorien-App als Android-App. Zielverzeichnis:

```
/var/www/html/eat-track-levlup/
```

Lege dort das komplette Projekt an (Frontend, Backend/Proxy, Datenbank-Migrationen, README).

---

## ⚠️ Wichtigste Arbeitsregel: FRAGEN statt RATEN

**Wenn du an einer Stelle raten müsstest, stelle stattdessen eine Frage.** Das gilt für alles:
Technologiewahl, Datenmodell-Details, Bibliotheken, Hosting, Namen von Feldern, Verhalten in
Grenzfällen, fehlende Texte, unklare Abläufe, Rechtsfragen, Zahlungsabwicklung, Deployment.

Konkret:
- Baue **keine** Platzhalter-Funktion, die „irgendwie" arbeitet, nur um weiterzukommen.
- Erfinde **keine** Inhalte, Preise, Rechtstexte oder Datenquellen.
- Wenn eine Anforderung mehrdeutig ist: kurz fragen, Optionen nennen, Empfehlung dazuschreiben, dann warten.
- Sammle Fragen wenn möglich gebündelt, statt bei jedem Detail zu stoppen.
- Wenn du eine Annahme treffen *musst*, um nicht zu blockieren: markiere sie im Code mit
  `// ANNAHME: …` und führe sie in `OPEN-QUESTIONS.md` im Projektroot.

---

## Namen und Tonalität

- Voller Name / Claim: **Eat. Track. LevelUp.**
- Kurzname für App-Icon, Header, Push-Absender, Android-Share-Target: **ETL**
- Sprache der App: **ausschließlich Deutsch**
- Tonalität: locker, „du", Community-Sprache. Keine Werbesprache, keine Emoji-Flut.
- Hinweis: Der frühere Arbeitstitel „Fit Rezept" ist verworfen (beschreibend, kaum schutzfähig, Markt belegt).

---

## Plattform & Architektur (bitte bestätigen oder korrigieren)

Ich brauche von dir eine Empfehlung und dann meine Bestätigung, bevor du Code schreibst:

- **Android-Client**: Kotlin + Jetpack Compose (native) ODER Flutter ODER PWA/Capacitor?
- **Backend**: erforderlich, weil der OpenRouter-Key niemals im Client liegen darf.
  Vorschlag: Node/TypeScript oder PHP (passend zu `/var/www/html/`), plus PostgreSQL oder MySQL.
- **Auth**: E-Mail + Passwort **und** Google-Login.

Frage mich, was ich hier will, statt zu entscheiden.

### Nicht verhandelbar
1. **Der OpenRouter-API-Key liegt ausschließlich serverseitig.** Alle KI-Aufrufe laufen über einen
   eigenen Proxy-Endpunkt. Kein Key im APK, in Umgebungsvariablen des Clients oder in Logs.
2. Jeder KI-Aufruf wird serverseitig protokolliert mit: User, Zweck, Modell, tatsächliche Kosten.
3. Guthaben-Abbuchung passiert serverseitig, nie im Client.

---

## Accounts & Registrierung

- Registrierung per E-Mail + Passwort oder Google.
- **Username ist Pflicht** — entweder aus Vorschlägen wählen oder selbst eintippen.
  Erlaubte Zeichen: Kleinbuchstaben, Zahlen, Punkt, Unterstrich; min. 3 Zeichen; Live-Prüfung auf Verfügbarkeit.
- Username ist danach höchstens **einmal** änderbar (bitte im UI so ansagen).
- Jeder neue Account erhält **0,50 $ KI-Startguthaben** „aufs Haus".

---

## Onboarding

Nach der Registrierung: **Zielrechner als Wizard, eine Frage pro Seite**, mit Fortschrittsbalken,
Zurück/Weiter, überspringbar. Auswahlfragen springen beim Antippen automatisch weiter.

**Seite 1 – Geschlecht:** Männlich / Weiblich

**Seite 2 – Körperdaten:** Größe (cm), Gewicht (kg), Alter

**Seite 3 – Aktivitätsniveau** (genau diese fünf, Faktor sichtbar):
1. Sitzende Lebensweise — wenig oder keine Bewegung, mäßiges Gehen, Bürotätigkeit außerhalb des Hauses (**1,2**)
2. Leicht aktiv — Bewegung oder leichter Sport 1–3 Tage/Woche, leichtes Joggen oder Spazieren 3–4 Tage (**1,375**)
3. Mäßig aktiv — körperliche Arbeit, Bewegung oder Sport 4–5 Tage/Woche, z. B. Bauarbeiter (**1,55**)
4. Sehr aktiv — schwere körperliche Arbeit, Sport 6–7 Tage/Woche, Schwerarbeiter (**1,75**)
5. Extrem aktiv — sehr schwere körperliche Arbeit oder täglich Sport, Profi-/Olympiasportler (**1,9**)

**Seite 4 – Schritte pro Tag:** Eingabefeld plus Schnellwerte 3k / 6k / 10k / 15k

**Seite 5 – Ziel:** Gewicht verlieren / Gewicht gleichbleibend / Gewicht erhöhen

**Seite 6 – Ergebnis:** Tagesziel groß, Makros, offener Rechenweg, alle Eingaben mit „Ändern"-Sprung.

### Rechenweg (Methodik nach sailrabbit.com/bmr)

```
BMR = Mittelwert aus
      Mifflin-St-Jeor:              10·kg + 6,25·cm − 5·Alter + (m: +5 | w: −161)
      Harris-Benedict (revidiert):  m: 13,397·kg + 4,799·cm − 5,677·Alter + 88,362
                                    w:  9,247·kg + 3,098·cm − 4,330·Alter + 447,593

TDEE            = BMR × Aktivitätsfaktor
Bewegungsbonus  = max(0, Schritte − 5000) × 0,00045 × kg      # unter 5000 steckt im Faktor
Ziel-Anpassung  = verlieren −500 | halten ±0 | erhöhen +350
Tagesziel       = round((TDEE + Bewegungsbonus + Ziel-Anpassung) / 10) × 10
Makros          = Protein kg×1,8 (bei Aufbau ×2), KH 40 % der kcal, Fett 30 % der kcal
```

Der Nutzer kann das Tagesziel jederzeit überschreiben. Disclaimer „Schätzung, kein medizinischer Rat" muss sichtbar sein.

---

## Navigation: 5 Tabs

### Tab 1 — Rezepte (Übersicht aller Nutzer)
- Suche oben, **Live-Ergebnisse beim Tippen**, durchsucht Titel, Zutaten, Tags, Koch-Namen.
- Filter-Chips (Alles, Suppe, Vegan, Low Carb, Frühstück, Meal Prep, Pasta …).
- Darstellung: **zweispaltiges Karten-Raster** (Bild, Titel, Kochlöffel-Bewertung, kcal, @Koch, Quelle, Zeit).
- **Chefkoch-Integration**: externe Treffer erscheinen in einer eigenen Sektion **unter** den ETL-Treffern,
  klar als extern markiert, Klick öffnet die Quelle. Eigene Rezepte stehen immer oben.
  ⚠️ Chefkoch hat kein offenes öffentliches API — kläre mit mir, welchen Weg wir gehen (Partnerschaft, RSS,
  offizielle Schnittstelle). **Kein Scraping.** Frag nach, bevor du hier etwas baust.
- Leerzustand: Hinweis plus Button „KI-Rezept erstellen".

### Tab 2 — Tracking
- Reiter **Tag** und **Woche**.
- Tag: Kalorien-Ring (gegessen / Ziel), verbleibende kcal, Makro-Balken, Mahlzeitenliste mit Slot
  (Frühstück, Mittag, Abend, Snack).
- Drei Schnellzugriffe:
  1. **Lebensmittel** — Datenbanksuche.
  2. **Rezept** — ganzes Gericht eintragen, mit Portionen-Stepper; Nährwerte kommen aus dem Rezept.
  3. **Foto analysieren** — KI, kostet Guthaben.
- **Datenbank:** primär **Open Food Facts**. **USDA FoodData Central nur als Fallback** — und zwar erst
  dann anzeigen, wenn Open Food Facts keine Treffer hat, mit Hinweiszeile „In Open Food Facts nichts
  gefunden — Treffer aus USDA FoodData Central".
  ⚠️ **Yazio ist nicht nutzbar**: keine offene Schnittstelle, Einbindung wäre ein Lizenzverstoß.
- **Grammangabe frei einstellbar**: Nach Auswahl eines Lebensmittels ein Panel mit −/+ (10er-Schritte),
  Eingabefeld und Schnellwerten (30/100/150/250); kcal und Makros rechnen live mit, dann „Ins Tagebuch".
- Woche: Balken Mo–So, Ø kcal/Tag, „x von y Tagen im Zielbereich", Rückblick (getrackte Tage, Ø Protein,
  nachgekochte Rezepte, Streak).
- Barcode-Scanner ist gewünscht — frag, ob in Phase 1 oder später.

### Tab 3 — KI-Rezept (hervorgehobener Button in der Mitte der Leiste)
Drei Eingabeblöcke, alle über „+ hinzufügen" gefüllt, **keine vorgegebenen Auswahl-Chips**:
- **Das habe ich da** — Zutaten, Enter fügt hinzu, Chip antippen entfernt.
- **Mag ich nicht** — gleiches Muster.
- **Heute Lust auf** — startet **leer**, nur „+ hinzufügen"; Freitext wie „Suppe", „sahnig", „schnell".
  Leer lassen ist erlaubt → dann entscheidet die KI aus den Zutaten.

Danach: Kostenhinweis, Generieren, Ladezustand, Ergebnis mit Titel, Nährwerten, Zutaten, Schritten,
KI-Badge, tatsächlich abgerechneter Betrag, „Speichern (+80 XP)" und „Neu".
Optional KI-Titelbild aus Zutaten und Beschreibung (teurer, separat abgerechnet).

### Tab 4 — Rezept erstellen
- **Reel-Import** aus TikTok, Instagram Reels, YouTube Shorts und Screenshot einer Rezeptseite.
  Drei Wege, alle drei bauen:
  1. Link in der App einfügen
  2. Android **Share-Sheet**: „Teilen → ETL"
  3. Screenshot/Foto einer Rezeptseite
  Ablauf immer: Quelle → KI liest aus → **Nutzer prüft und korrigiert** → speichern (+25 XP).
  Geschätzte Mengen müssen als geschätzt markiert sein.
  ⚠️ TikTok/Instagram/YouTube erlauben kein Scraping. Sauberer Weg: der Nutzer teilt den Link, es werden
  nur öffentlich verfügbare Metadaten verarbeitet, und die Quelle wird immer verlinkt und genannt.
  Frag mich, wie weit wir hier gehen.
- **Von Hand**: Titelbild (Foto wählen oder KI-Bild), Titel, Portionen, Zeit, Zutatenzeilen
  (Menge + Name, hinzufügen/entfernen), Zubereitungsschritte, Tags.
- **Nährwerte pro Portion automatisch aus den Zutaten** berechnen, kostenlos, ohne KI.
- Veröffentlichen gibt +80 XP.

### Tab 5 — Profil
- Kopf: Avatar, @Username, Level, XP, Fortschritt zum nächsten Level.
- Statistiken: Rezepte, Importe, Streak, Ø Kochlöffel.
- **Guthaben-Karte**: Restbetrag groß, „Aufladen", Hinweis „exakt der Betrag des KI-Aufrufs, kein
  Aufschlag", darunter **Verlauf** mit jeder Buchung (Zweck, Zeit, Betrag auf 4 Dezimalstellen).
- Bereiche: Meine Rezepte, **Wochen-Essensplan**, Offline gespeicherte Rezepte, **Abzeichen**, **Rangliste**.
- Darstellung: Theme-Umschalter **System / Hell / Dunkel** (Standard: System).
- Tutorial neu starten.
- Abmelden.
- Zahnrad oben rechts → **Admin-Einstellungen**, sichtbar nur für die im Backend hinterlegte Admin-E-Mail.

---

## Rezept-Detailseite

Reihenfolge von oben nach unten:
1. **Bildergalerie**, horizontal wischbar mit Punkt-Indikator: Bild 1 ist das Rezeptbild, danach die
   Nachkoch-Fotos der Nutzer als Bild 2, 3, 4 … mit Label „Nachgekocht von @user".
   Optional KI-generiertes Bild aus Zutaten und Beschreibung.
2. Titel, @Koch, Kochlöffel-Bewertung mit Anzahl, Quelle, Zeit.
3. Nährwerte pro Portion (kcal, Protein, KH, Fett).
4. Button **„Ganzes Gericht ins Tagebuch"** — rechnet die eingestellten Portionen mit.
5. **Zutaten**, komplett userfreundlich: Portionen-Stepper skaliert alle Mengen live, jede Zeile abhakbar,
   Button „Alles auf die Einkaufsliste".
6. **Zubereitung** als nummerierte Schritte, plus **Kochmodus** (Vollbild, ein Schritt groß, Fortschritt,
   Display bleibt an).
7. **Kochlöffel-Bewertung 1–5** — siehe Regel unten.
8. **Kommentare** mit Antworten (eine Ebene) und Bildern.

### Bewertungsregel (wichtig)
**Kochlöffel darf nur vergeben, wer „Ich hab's gekocht" antippt.** Vorher ist die Bewertung sichtbar
deaktiviert mit Erklärung.

Nach dem Antippen öffnet sich ein Pflicht-Dialog:
1. **Bewertung 1–5 Kochlöffel — Pflicht**
2. **Foto vom Nachgekochten — Pflicht.** Das Foto erscheint oben in der Galerie des Rezepts.
3. **Kommentar — optional**, erscheint unten bei allen anderen Kommentaren (+2 XP).

Absenden bleibt gesperrt, bis Bewertung und Foto vorhanden sind. Belohnung: +50 XP, Kommentar +2 XP,
Kennzeichnung „Nachgekocht" / verifiziert. Der Kochmodus öffnet am Ende denselben Dialog.

---

## Einkaufsliste & Essensplan

- **Einkaufsliste** über einen **schwebenden Button** erreichbar, der über den Tabs liegt (mit Anzahl-Badge).
  Zutaten gleichen Namens werden zusammengefasst, Abhaken bleibt gespeichert.
- **Wochen-Essensplan** liegt im Profil als eigener Bereich: Mo–So je ein Gericht, Sprung ins Rezept,
  „Einkaufsliste aus Plan füllen".
- **Push-Benachrichtigung um 12:00 Uhr** am jeweiligen Tag: „Heute auf dem Plan: <Gericht>" mit kcal und
  Zeit, Aktionen „Rezept öffnen" und „Später". Als lokale Benachrichtigung planen (kein Server nötig),
  im Essensplan abschaltbar. Die Benachrichtigung verschwindet nach wenigen Sekunden von selbst.

---

## Gamification

- **XP-Quellen mit Gewichtung** (Kleinkram bewusst klein, damit Spam sich nicht lohnt):

  | Aktion | XP |
  |---|---|
  | Rezept veröffentlicht | +80 |
  | Nachgekocht (mit Foto) | +50 |
  | Reel importiert | +25 |
  | Wochen-Challenge geschafft | +150 |
  | Tag vollständig getrackt | +10 |
  | Kalorienziel getroffen | +10 |
  | Kommentar oder Bewertung | +2 |
  | Streak-Tag | +2 |

- **Level als Kochlöffel-Stufen**: Holz-Löffel (ab 0 XP) → Stahl-Löffel (500) → Silber-Löffel (1500) →
  Gold-Löffel (3500).
- **Rangliste: global, All-Time.**
- **Abzeichen**, u. a.: Erster Löffel, 7-Tage-Streak, Reel-Jäger, Nachgekocht, Suppenkasper (5 Suppen),
  Grünzeug (10 vegane Rezepte), Meal-Prep-König (10 Prep-Rezepte), 30 Tage getrackt, Blitzkoch
  (15 Rezepte unter 20 Min), Publikumsliebling (Rezept mit 100 Löffeln), Punktlandung (7 Tage im
  Zielbereich), Küchengespräch (25 Kommentare), Planer (Woche komplett geplant), Wiederholungstäter
  (Rezept 5× gekocht).
- Serverseitiger Missbrauchsschutz gegen XP-Farming ist gewünscht — frag, wie streng.

---

## KI, Guthaben und Abrechnung

- Alle KI-Funktionen laufen über **OpenRouter**. API-Key, Modell und Anbieter kommen aus den
  Admin-Einstellungen und liegen **nur serverseitig**.
- **Abgerechnet wird exakt der Betrag, den der Aufruf gekostet hat** — kein Aufschlag. Anzeige mit
  4 Dezimalstellen (z. B. `0,0034 $`), plus vollständiger Verlauf im Profil.
- Richtwerte fürs UI (echte Kosten kommen aus der OpenRouter-Antwort):
  Foto-Analyse ≈ 0,0034 $ · KI-Rezept ≈ 0,0089 $ · Reel-Import ≈ 0,0052 $ · KI-Bild ≈ 0,0210 $.
- **Bei 0,00 $ Guthaben**: KI-Buttons sind **gesperrt** und zeigen einen Aufladen-Hinweis. Alles ohne KI
  (Suchen, Kochen, Tracken, Kommentieren) funktioniert weiter.
- **KI-Kennzeichnung**: Vor der **ersten** KI-Nutzung ein einmaliger Erklär-Hinweis („Deine Eingaben gehen
  an ein Sprachmodell bei OpenRouter, Ergebnisse sind Schätzungen, kein Ernährungsrat, abgerechnet wird
  der tatsächliche Betrag"). Danach trägt **jedes** KI-Ergebnis ein sichtbares **KI-Badge**.
- **Foto-Kalorien realistisch halten**: Ein allgemeines Vision-Modell erreicht die Genauigkeit
  spezialisierter Apps nicht — die gewinnen über Portionsschätzung, nicht über Bilderkennung.
  Deshalb: KI schätzt, **der Nutzer muss die Portion bestätigen oder korrigieren**, bevor gespeichert wird,
  und am Ergebnis steht ein ehrlicher Genauigkeitshinweis (grob ±20 %).

### Aufladen
- **PayPal-Button** mit einem Link, den ich in den Admin-Einstellungen hinterlege (Freunde & Familie).
- Der Nutzer muss seinen **@Username in die PayPal-Nachricht** schreiben, sonst ist keine Zuordnung möglich.
  Gutschrift erfolgt manuell.
- ⚠️ **Rechtlicher Hinweis, den du mir bestätigen lassen musst:** PayPal „Freunde & Familie" ist für
  gewerbliche Zahlungen laut PayPal-AGB nicht zulässig und ohne Käuferschutz. Für einen echten
  Guthaben-Verkauf brauchst du eine reguläre Zahlungsart. Frag mich, wie wir damit umgehen, bevor du
  Zahlungslogik baust.

---

## Admin-Einstellungen

Sichtbar **nur** für die im Backend fest hinterlegte **Admin-E-Mail**. Einmalige Eingabe, serverseitig
gespeichert, nie an Clients ausgeliefert:
- **OpenRouter API-Key** (maskiert)
- **Modell / Anbieter** (z. B. `anthropic/claude-sonnet-4.5`)
- **PayPal-Link** (Freunde & Familie)

Dazu ein Bereich für manuelle Guthaben-Gutschriften pro Nutzer (nach PayPal-Eingang).

---

## Bedienbarkeit, Tutorial, Theme

- **Sehr userfreundlich** ist Priorität: große Trefferflächen (mindestens 44 dp), klare Hierarchie,
  kurze Texte, keine versteckten Gesten als einziger Weg.
- **Tutorial per Coach-Marks**: Beim **ersten Besuch jedes Tabs** erscheint ein kleiner Hinweis (max. zwei
  Sätze) mit „Verstanden" und „Alle aus". Gebaut für Leute mit wenig Aufmerksamkeit — ein Gedanke pro
  Hinweis. Im Profil jederzeit neu startbar.
- **Dark Mode und Light Mode**, Standard folgt dem System, manuell umschaltbar. Beide Themes müssen
  4,5:1 Kontrast für Text erfüllen.
- Offline gespeicherte Rezepte müssen ohne Netz lesbar sein, inklusive Kochmodus.

---

## Designvorgaben (Prototyp-Referenz)

Farbtokens (Light / Dark):

```
--bg     #FBF7F0 / #15120E     --surf   #FFFFFF / #211C17
--surf2  #F2EBE0 / #2C261F     --ink    #16130F / #F8F3EB
--mut    #6E6559 / #A69C8E     --line   rgba(22,19,15,.11) / rgba(248,243,235,.13)
--hot    #E24A15 / #FF6B2C     --grn    #2C8F60 / #4CC088     --gold #E8B33A / #F0C558
```

- Schriften: **Bricolage Grotesque** für Überschriften und große Zahlen, **Instrument Sans** für Text.
- Radien: Karten 16–22 px, Buttons als Pille (999 px).
- Der mittlere Tab (KI-Rezept) ist ein erhabener runder Button in `--hot`, der über die Leiste hinausragt.
- Kochlöffel-Icon: gefüllte Ellipse als Laffe plus abgerundeter Stiel; gefüllt in `--gold`, leer als Kontur.
- Maximal ein bis zwei Hintergrundfarben pro Screen, keine wilden Gradienten.
- Echte Fotos statt Farbflächen einsetzen, sobald Bildmaterial vorhanden ist. Frag mich danach.

---

## Offene Punkte, die du mit mir klären musst, bevor du sie baust

1. Plattform-Stack (native Kotlin vs. Flutter vs. PWA) und Backend-Sprache
2. Chefkoch-Zugang — welcher legale Weg
3. Reel-Import — wie weit wir gehen und welche Metadaten wir verarbeiten
4. Zahlungsabwicklung statt PayPal F&F
5. Barcode-Scanner in Phase 1 oder später
6. Strenge des XP-Missbrauchsschutzes
7. Moderation von Kommentaren und Fotos (Meldefunktion, Löschung)
8. Datenschutz: Verarbeitung von Essensfotos, Aufbewahrungsdauer, DSGVO-Texte, Auftragsverarbeitung
   mit OpenRouter
9. Bildmaterial und Rezept-Startbestand für die Erstbefüllung
10. Deployment und Domain unter `/var/www/html/eat-track-levlup/`

---

## Reihenfolge, die ich mir vorstelle

1. Rückfragen stellen (Stack, offene Punkte oben) — **erst danach Code**
2. Datenmodell und Migrationen
3. Auth inklusive Username-Pflicht und Startguthaben
4. Backend-Proxy für OpenRouter inklusive Kostenerfassung und Guthaben-Abbuchung
5. Rezepte: Modell, Erstellen, Detail, Suche
6. Tracking mit Open Food Facts, USDA-Fallback, Grammsteuerung, Rezept-Logging
7. Zielrechner-Wizard
8. KI-Features: Rezeptgenerator, Reel-Import, Foto-Analyse, KI-Bild
9. Nachkoch-Dialog, Galerie, Kommentare
10. Gamification, Abzeichen, Rangliste
11. Einkaufsliste, Essensplan, 12-Uhr-Push
12. Coach-Marks, Theme, Offline
13. Admin-Bereich
14. `README.md`, `OPEN-QUESTIONS.md`, Deployment-Anleitung

Noch einmal, weil es das Wichtigste ist: **wenn du raten würdest, frage mich stattdessen.**
