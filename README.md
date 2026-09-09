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

## Prototyp ansehen

`prototyp/Fit Rezept.dc.html` im Browser öffnen. Es ist ein klickbarer Prototyp aller Screens
mit Sprungmarken neben dem Telefonrahmen. Er zeigt **Aussehen und Verhalten**, ist aber keine
Produktions-Codebasis — nicht übernehmen, sondern nachbauen.

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
