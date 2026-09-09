# Designvorgaben — ETL

## Farbtokens

```css
:root {
  --bg:    #FBF7F0;   --surf:  #FFFFFF;   --surf2: #F2EBE0;
  --ink:   #16130F;   --mut:   #6E6559;   --line:  rgba(22,19,15,.11);
  --hot:   #E24A15;   --grn:   #2C8F60;   --gold:  #E8B33A;
  --sh: 0 1px 3px rgba(22,19,15,.07), 0 8px 24px rgba(22,19,15,.05);
}
[data-theme="dark"] {
  --bg:    #15120E;   --surf:  #211C17;   --surf2: #2C261F;
  --ink:   #F8F3EB;   --mut:   #A69C8E;   --line:  rgba(248,243,235,.13);
  --hot:   #FF6B2C;   --grn:   #4CC088;   --gold:  #F0C558;
  --sh: 0 1px 3px rgba(0,0,0,.5), 0 10px 28px rgba(0,0,0,.35);
}
```

Rollen: `--bg` Seitenhintergrund · `--surf` Karten · `--surf2` eingelassene Flächen, Chips,
Fortschritts-Spuren · `--ink` Text · `--mut` Sekundärtext · `--line` Trennlinien und Rahmen ·
`--hot` Akzent, primäre Aktion, KI-Badge · `--grn` Erfolg, verifiziert, im Zielbereich ·
`--gold` gefüllte Kochlöffel, Level.

**Standard-Theme: System.** Manuell umschaltbar System / Hell / Dunkel.

## Schriften

- Überschriften und große Zahlen: **Bricolage Grotesque**, 800, negatives Tracking
  (−0,6 px bei 21 px bis −2 px bei 44 px).
- Text und UI: **Instrument Sans**, 400 / 500 / 600 / 700.
- Kleinste Textgröße im UI: 11 px, und nur für Meta-Angaben. Fließtext ab 13 px.
- Rubriken: 11–12 px, 700, `letter-spacing: 1.2px`, `text-transform: uppercase`, in `--mut`.

## Form

- Karten 16–22 px Radius, Eingabefelder 13–14 px, Buttons als Pille (999 px).
- Buttons: Höhe 44–56 px. Trefferfläche nie unter 44 dp.
- Chips: Pille, 9–10 px vertikal, 13–15 px horizontal.
- Schatten nur auf erhabenen Karten und dem mittleren Tab-Button, sonst Rahmen aus `--line`.

## Komponenten-Regeln

**Tab-Leiste**: fünf Einträge, der mittlere (KI-Rezept) ist ein erhabener runder Button, 56 px,
`--hot`, ragt 22 px über die Leiste, mit langsamem Puls. Aktiver Tab in `--ink`, inaktiv `--mut`.

**Kochlöffel-Icon**: gefüllte Ellipse als Laffe (rx 4.3, ry 5.1 bei 24er ViewBox) plus abgerundeter
Stiel. Gefüllt `--gold`, leer als Kontur in `currentColor` bei halber Deckkraft.

**KI-Badge**: `--hot`, weiße Schrift, 10 px, 800, Pille, Text „KI". Sitzt an jedem KI-Ergebnis.

**Kostenanzeige**: immer 4 Dezimalstellen, deutsches Komma, z. B. `0,0034 $`. Guthaben 2 Stellen.

**Bottom-Sheets**: 26 px Radius oben, Griff-Balken, maximal 88 % Höhe, Abdunklung dahinter.

**Coach-Marks**: dunkle Karte über der Tab-Leiste, `--ink` Hintergrund, `--bg` Text, Zähler-Badge,
zwei Buttons („Verstanden", „Alle aus").

## Kontrast

Text mindestens 4,5:1 gegen den Hintergrund, in **beiden** Themes. Keine transparenten Textfarben
auf Akzentflächen oder Fotos — volle Deckkraft verwenden. Für Text auf Bildern eine Abdunklung
(`rgba(0,0,0,.45)` plus `backdrop-filter: blur(6px)`) unterlegen.

## Bilder

Im Prototyp stehen Farbverläufe als Platzhalter. In der App echte Fotos verwenden.
Rezeptbild-Seitenverhältnis in der Galerie: etwa 16:11 bei voller Breite.

## Vermeiden

Aggressive Gradienten als Flächenhintergrund, mehr als zwei Hintergrundfarben pro Screen,
Emoji als Funktionsicons (Abzeichen ausgenommen), gezeichnete Illustrationen als Bildersatz.
