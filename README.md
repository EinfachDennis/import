# ETL — Eat. Track. LevelUp. · Handoff-Paket

Alles, was Claude Code braucht, um die App zu bauen. Zielverzeichnis:

```
/var/www/html/eat-track-levlup/
```

## So benutzt du das Paket

1. Entpacke den Ordner.
2. Öffne Claude Code im Zielverzeichnis.
3. Gib den Inhalt von **PROMPT.md** als ersten Prompt ein — oder kürzer:
   `Lies PROMPT.md, DESIGN.md, DATENMODELL.md und SCREENS.md und stelle mir zuerst deine Rückfragen.`
4. Claude Code stellt Rückfragen, **bevor** Code entsteht. Das ist beabsichtigt.

## Dateien

| Datei | Inhalt |
|---|---|
| `PROMPT.md` | Der Hauptauftrag. Vollständige Spezifikation aller Funktionen. |
| `DESIGN.md` | Farben, Schriften, Radien, Komponenten-Regeln, Kontrastvorgaben. |
| `DATENMODELL.md` | Tabellen, Felder, Beziehungen als Ausgangsvorschlag. |
| `SCREENS.md` | Screen-für-Screen-Liste mit Zuständen und Übergängen. |
| `OPEN-QUESTIONS.md` | Die Punkte, die vor dem Bauen geklärt werden müssen. |
| `prototyp/` | Der interaktive Prototyp. Referenz für Layout und Verhalten. |
| `prototyp/ETL-Prototyp-standalone.html` | **Einzeldatei, offline lauffähig.** Doppelklick öffnet den kompletten Prototyp. |

## Prototyp ansehen

**`prototyp/ETL-Prototyp-standalone.html`** per Doppelklick im Browser öffnen — eine einzige Datei,
funktioniert offline, braucht keinen Server. Rechts neben dem Telefonrahmen liegen Sprungmarken zu
**jedem** Screen und Zustand: Anmeldung, Username, Zielrechner (alle 6 Schritte), Feed,
Chefkoch-Treffer, Rezept-Detail, Kochmodus, Nachkoch-Dialog, Tracking Tag und Woche,
Lebensmittel mit Grammsteuerung, Rezept eintragen, KI-Rezept, Erstellen, Reel-Import, Profil,
Rangliste, Abzeichen, Aufladen, Admin, Push um 12:00, Guthaben leer, Coach-Marks, Dark Mode.

Er zeigt **Aussehen und Verhalten** verbindlich, ist aber keine Produktions-Codebasis —
nicht übernehmen, sondern nachbauen.

## Wie du das Design zuverlässig an Claude Code übergibst

Screenshots sind hier der **schwächste** Weg — sie zeigen keine Abstände, keine Zustände und keine
Interaktion. Besser, in dieser Reihenfolge:

1. **Lass Claude Code die Prototyp-Quelle lesen.** In
   `prototyp/Fit Rezept.dc.html` stehen alle exakten Werte im Klartext: jede Farbe, jeder
   Pixel-Abstand, jeder Radius, jede Schriftgröße, jeder Zustandswechsel. Sprich es direkt an:

   > Lies `prototyp/Fit Rezept.dc.html` und übernimm daraus exakt Farben, Abstände, Radien,
   > Schriftgrößen und die Zustandslogik. Wenn ein Wert dort steht, rate nicht — verwende ihn.

2. **DESIGN.md als Regelwerk** für alles, was im Prototyp nicht vorkommt.
3. **SCREENS.md Screen für Screen abarbeiten** statt „baue die App". Ein Screen pro Arbeitsschritt,
   danach mit dem Prototyp vergleichen.
4. **Erst am Ende Screenshots** zum Abgleich: Prototyp und gebauten Screen nebeneinander öffnen.

### Warum der erste Versuch schlecht aussah
Prosa-Beschreibungen lassen zu viel Spielraum. Sobald die Quelldatei mit den konkreten Werten
vorliegt und Screen für Screen gearbeitet wird, verschwindet der Interpretationsraum.

## Wichtigste Regel

**Wenn Claude Code raten müsste, soll es fragen.** Steht ganz oben in PROMPT.md und gilt für
Stack-Wahl, Datenmodell-Details, Rechtsfragen, Zahlungsabwicklung und Deployment.

## Vier Themen, die rechtlich zu klären sind

1. **Yazio** — keine offene Schnittstelle, Einbindung wäre ein Lizenzverstoß. Ersetzt durch
   Open Food Facts mit USDA-Fallback.
2. **Chefkoch** — kein offenes öffentliches API. Scraping verstößt gegen die Nutzungsbedingungen.
3. **Reel-Import** — TikTok, Instagram und YouTube erlauben kein Scraping. Nur nutzergeteilte
   Links und öffentliche Metadaten, Quelle immer nennen.
4. **PayPal Freunde & Familie** — für gewerbliche Zahlungen laut AGB nicht zulässig und ohne
   Käuferschutz. Für echten Guthaben-Verkauf ist eine reguläre Zahlungsart nötig.
