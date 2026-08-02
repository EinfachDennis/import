# Handoff: BEYOND TELLING — Startseite (oberer Bereich), Erstellen, Identitäten

## Overview

Redesign von drei Bereichen der bestehenden Android-App **BEYOND TELLING** (PHP + Vanilla-JS-WebApp, läuft in einer Android-WebView-Hülle):

1. **Startseite — nur der obere Bereich**: Kopfzeile, animierter Quellen-Umschalter (BEYOND TELLING / TIPSY), Hero-Karussell mit großen Charakter-Karten, Suchzeile.
2. **Erstellen** (`#tab-create`): das bisherige lange Formular wird zu einem **5-stufigen Wizard mit horizontalen Slides**.
3. **Identitäten** (`#tab-identities`): dieselbe Systematik als **3-stufiger Wizard**, plus Chips-Leiste der vorhandenen Identitäten.

**Ausdrücklich NICHT Teil dieses Handoffs** (bitte unverändert lassen):

- Alles auf der Startseite **unterhalb der Suchzeile** (User-Sicht-Filter, Genre-/Typ-Filter, Charakter-Grid, Tipsy-Grid, "Mehr laden"). Diese Bereiche bleiben wie in der aktuellen App.
- Die **Chats-Liste**, der Chat-Screen und das Profil.
- Alle Modals, Sheets, Tour, Update-Bar usw.

Farben, Schriften und Texte stammen 1:1 aus der bestehenden App (`assets/app.css`, `index.php`). Es werden **keine neuen Farben eingeführt** — nur Layout, Hierarchie und Bewegung ändern sich.

## About the Design Files

Die Datei `prototype.dc.html` in diesem Bundle ist eine **Design-Referenz in HTML** — ein lauffähiger Prototyp, der Aussehen und Verhalten zeigt. Es ist **kein Produktionscode zum Kopieren**: der Prototyp nutzt eine React-basierte Streaming-Laufzeit (`support.js`) und einen Android-Geräterahmen (`android-frame.jsx`), die es in der Ziel-App nicht gibt.

Die Aufgabe ist, diese Designs im **bestehenden Stack der App** nachzubauen:

- `index.php` — HTML-Struktur der Tabs
- `assets/app.css` — Styles (CSS-Variablen bereits vorhanden, siehe *Design Tokens*)
- `assets/app.js` — Vanilla-JS ohne Framework, DOM-Manipulation über `$()`-Helper, `buildMoodPicker()`, `buildGenrePicker()`, Autosave über `autosaveField()` / `api()`

Also: **kein React, kein Build-Step, keine neuen Abhängigkeiten.** Alles mit CSS-Transitions/Transforms und der vorhandenen JS-Struktur umsetzen.

**Öffnen des Prototyps:** `prototype.dc.html` im Browser öffnen (die drei Dateien müssen im selben Ordner liegen). Unten in der Leiste zwischen *Start*, *Erstellen* und *Identitäten* wechseln. *Chats* und *Profil* sind im Prototyp bewusst leer.

## Fidelity

**High-fidelity.** Farben, Größen, Radien, Schriftgrößen und Timings sind final und unten exakt dokumentiert. Bitte pixelnah umsetzen. Wo im Prototyp Platzhalter stehen (Charakterbilder als Farbverlauf mit großem Initial), verwendet die echte App die bestehenden Bild-URLs (`API?action=image&id=…`) mit demselben Fallback-Verhalten wie heute (`.char-card-img` zeigt den ersten Buchstaben des Vornamens).

## WebView-Randbedingungen (wichtig)

Die App läuft in einer Android-WebView. Aus `app.css` übernommene Regeln, die weiter gelten:

- **Kein `backdrop-filter: blur()`** — verursacht Flackern. Stattdessen deckende/halbtransparente Hintergründe (`rgba(18,13,24,.68)` etc.).
- Animiert wird nur **`transform` und `opacity`** (GPU-freundlich). Keine Animation von `width`, `top`, `background-position` — Ausnahme: der bereits vorhandene `goldShimmer` auf Text.
- Große Ebenen mit `transform: translateZ(0)` isolieren, wie beim bestehenden `.animated-bg`.
- Horizontale Scroller mit `-webkit-overflow-scrolling: touch` und versteckter Scrollbar.
- Alle Eingabefelder behalten `font-size: 16px`, sonst zoomt Android beim Fokus.

---

## Screen 1 — Startseite (oberer Bereich)

**Zweck:** Einstieg. Der Nutzer sieht sofort große, kinoartige Charakter-Karten statt einer Formularleiste, und schaltet zwischen den beiden Quellen (eigene App / tipsy.chat) um.

**Container:** `#tab-home > header.page-head` wird ersetzt. Padding des Tabs bleibt (`max(18px, env(safe-area-inset-top)) 16px …`), die neuen Blöcke nutzen `padding: 0 18px` und brechen für das Karussell absichtlich aus (siehe unten).

### 1.1 Kopfzeile

| Element | Wert |
|---|---|
| Container | `display:flex; align-items:center; gap:10px; padding:4px 18px 16px` — enthält nur noch den Quellen-Umschalter (flex:1) und rechts daneben den Tutorial-Button |
| Tutorial-Button "?" | 40×40px, `flex:none`, `border-radius:50%`, `border:1px solid rgba(202,164,106,.4)`, `background:rgba(255,255,255,.05)`, Text `#caa46a`, 15px/700. Ersetzt `.tour-help-btn`, gleiche Funktion (`#btn-tour-help`). Sitzt **rechts neben dem Umschalter**. |

Die bisherige Begrüßung („Guten Abend" + Wortmarke „BEYOND TELLING") entfällt ersatzlos — der Umschalter benennt die Seite selbst und rückt an ihre Stelle.

### 1.2 Quellen-Umschalter (BEYOND TELLING / TIPSY) — **animiert**

Ersetzt `.create-mode-switch.src-switch` (`#src-bt` / `#src-tipsy`), Funktion identisch. Der Umschalter steht **ganz oben auf der Seite** (dort, wo bisher die Begrüßung stand) und teilt sich die Zeile mit dem Tutorial-Button.

```
Track:      flex:1; min-width:0;
            position:relative; display:flex; padding:4px;
            border-radius:999px;
            background:rgba(0,0,0,.42);
            border:1px solid rgba(157,107,240,.22);

Indikator:  position:absolute; top:4px; bottom:4px; left:4px;
            width:calc(50% - 4px); border-radius:999px;
            background:linear-gradient(135deg,#b48af0,#6425b0);   /* --grad-purple */
            box-shadow:0 6px 20px rgba(124,77,196,.5);
            transform:translateX(0)     -> BEYOND TELLING aktiv
            transform:translateX(100%)  -> TIPSY aktiv
            transition:transform .45s cubic-bezier(.34,1.4,.5,1);

Buttons:    position:relative (über dem Indikator); flex:1; padding:9px 0;
            border:0; background:none;
            font:600 11.5px/1 Inter; letter-spacing:.1em;
            color aktiv:#fff · inaktiv:#8f7fa3;
            transition:color .3s;
```

Das leichte Überschwingen der Easing-Kurve (`cubic-bezier(.34,1.4,.5,1)`) ist der Kern des Effekts — bitte genau so übernehmen. Nur `transform` animieren, nie `left`.

### 1.3 Hero-Karussell ("Vorgeschlagen")

Der auffälligste Teil: große Karten, horizontal, mit Scroll-Snap.

```
Scroller:   display:flex; gap:14px; overflow-x:auto;
            scroll-snap-type:x mandatory;
            padding:2px 18px 4px;      /* Padding = Rand, Karten scrollen bis zum Displayrand */
            scrollbar-width:none;  ::-webkit-scrollbar{display:none}

Karte:      flex:none; width:255px; height:340px;
            border-radius:26px; overflow:hidden;
            scroll-snap-align:center;
            border:1px solid rgba(202,164,106,.24);
            box-shadow:0 22px 44px -18px rgba(0,0,0,.9);
            background: <Charakterbild cover> | Fallback-Verlauf
```

Innerhalb der Karte (alle absolut positioniert):

| Ebene | Spezifikation |
|---|---|
| Fallback-Initial | zentriert, Cinzel 110px, `color:rgba(255,255,255,.13)` — nur wenn kein Bild vorhanden |
| Lesbarkeits-Verlauf | `inset:0; background:linear-gradient(180deg,transparent 38%,rgba(8,5,12,.55) 66%,rgba(8,5,12,.96))` |
| Badge (immer "Vorgeschlagen") | `top:12px; left:12px; padding:3px 10px; border-radius:999px;` Gold-Verlauf `linear-gradient(135deg,#caa46a,#f2dfae,#caa46a)`, `background-size:200% auto`, `animation:goldShimmer 3.5s linear infinite`, Text `#241a02`, `font:700 9.5px/1.6 Inter`, `letter-spacing:.08em`, uppercase. Entspricht dem bestehenden `.char-badge`. NSFW-Variante: `--grad-ember`, Text `#fff`, ohne Shimmer, rechts statt links. |
| Textblock | `left:0;right:0;bottom:0;padding:16px` |
| Name | Cinzel 600, 21px, `line-height:1.15` |
| Sub (Rolle · Alter · Sicht) | Inter 11.5px, `color:#cdbede` (`--text-secondary`), `margin-top:3px` |
| Tag-Chips | `display:flex; gap:6px; margin-top:10px`; Chip: `padding:3px 9px; border-radius:999px; font-size:9.5px; font-weight:600; background:rgba(157,107,240,.24); border:1px solid rgba(157,107,240,.36); color:#d9c7ff` |

**Inhalt der Karten:** **ausschließlich vorgeschlagene Charaktere** (`is_suggested`) — keine „Neu"- oder „Beliebt"-Karten. Badge daher immer „Vorgeschlagen" (bzw. zusätzlich die NSFW-Variante). Datenquelle wie heute `characters&scope=feed`, gefiltert auf `is_suggested`. Gibt es keine vorgeschlagenen Charaktere, entfällt das Karussell komplett (kein Platzhalter). Tippen öffnet wie bisher `openCharModal(c)`.

### 1.4 Suchzeile

Ersetzt `.search-row`.

```
Zeile:      display:flex; gap:10px; padding:10px 18px 14px;

Suchfeld:   flex:1; display:flex; align-items:center; gap:9px;
            padding:11px 15px; border-radius:999px;
            background:rgba(0,0,0,.45);
            border:1px solid rgba(202,164,106,.18);
            Icon: Lupe 15×15, stroke #8f7fa3, stroke-width 2
            Input: font-size:16px (Android-Zoom!), color #f5eee6,
                   Platzhalter "Charaktere suchen …" in #8f7fa3
            :focus -> border-color:#9d6bf0; box-shadow:0 0 0 3px rgba(157,107,240,.18)

Filter-Btn: 44×44px; border-radius:50%;
            border:1px solid rgba(157,107,240,.35);
            background:rgba(255,255,255,.05);
            Trichter-Icon 17×17 (SVG aus index.php übernehmen)
```

**Ab hier endet der Handoff für die Startseite.** Der Filterblock, die Charakterliste und der Tipsy-Bereich darunter bleiben unverändert.

---

## Screen 2 — "Erstellen" als 5-Schritt-Wizard

**Zweck:** Das bisherige, sehr lange Formular (`#form-character`) wird in fünf Slides zerlegt. Es bleibt **dasselbe Formular mit denselben Feldnamen** — nur visuell in Abschnitte geteilt und horizontal geschoben. Autosave (`autosaveField`) und Absenden (`#form-character submit`) bleiben unverändert.

### Schrittfolge

| # | Titel | Inhalt (Feldnamen aus index.php) |
|---|---|---|
| 1 | **Ein Gesicht** | Bild-Upload (`#char-img-input`, `#char-img-preview`) + Button "Bild erstellen lassen · 0,05 $" (`#btn-gen-avatar-go`-Flow) |
| 2 | **Wer ist das?** | `first_name` *, `last_name`, `age`, `gender`, `player_view` |
| 3 | **Die Geschichte** | `description` (max 750), `history`, `intro_message` * (max 2500) |
| 4 | **Stimmung & Genre** | `#char-moods`, `#char-genres` (mind. 1 Genre), Toggles `is_public`, `is_nsfw` |
| 5 | **Fertig** | Live-Vorschau der Charakterkarte + "Charakter löschen" (das Speichern läuft über den Primärbutton der Fußleiste, dort "Speichern & ansehen") |

### Kopfbereich

```
padding:16px 18px 12px
Zeile:  display:flex; align-items:flex-end; gap:12px
  links:  "SCHRITT n VON 5" — 10.5px, letter-spacing:.16em, uppercase, color:#caa46a
          Titel — Cinzel 600, 22px, margin-top:2px
  rechts: 🪄-Button 40×40, border-radius:14px,
          background:linear-gradient(135deg,#caa46a,#f2dfae,#caa46a) mit
          background-size:200% auto + goldShimmer 4s
          (= bestehender #btn-ai-char, "Erstellen lassen 0,05 $")

Fortschritt: display:flex; gap:5px; margin-top:14px
  5 Segmente: flex:1; height:4px; border-radius:4px;
              background:rgba(255,255,255,.09); overflow:hidden
  Füllung:    height:100%; background:linear-gradient(90deg,#9d6bf0,#caa46a);
              transform-origin:left;
              transform:scaleX(1) wenn index <= aktueller Schritt, sonst scaleX(0);
              transition:transform .5s cubic-bezier(.22,.9,.3,1)
  Segmente sind anklickbar -> direkt zu diesem Schritt springen.
```

### Slide-Mechanik

```
Viewport: flex:1; overflow:hidden; position:relative
Track:    display:flex; width:500%; height:100%;
          transform:translateX(-{step * 20}%);
          transition:transform .55s cubic-bezier(.3,1.05,.4,1)
Slide:    width:20%; height:100%; overflow-y:auto; padding:8px 18px 72px
```

Wichtig: Alle fünf Slides bleiben im DOM (nicht `display:none`), damit eingegebene Werte und der Autosave-Zustand erhalten bleiben und die Bewegung flüssig ist.

Auf Touch zusätzlich horizontales Wischen zwischen den Schritten zulassen (Pointer-Events, Schwelle ~60px) — optional, aber gewünscht.

### Feld-Styles (Schritt 2 & 3)

```
Label:    display:flex; flex-direction:column; gap:6px
          Beschriftung: 11px; letter-spacing:.09em; uppercase; color:#8f7fa3
Input/Textarea:
          padding:13px 15px; border-radius:16px;
          background:rgba(0,0,0,.45);
          border:1px solid rgba(202,164,106,.18);
          color:#f5eee6; font-size:16px (Input) bzw. 14px/1.55 (Textarea);
          outline:none; resize:none
          :focus -> border-color:#9d6bf0; box-shadow:0 0 0 3px rgba(157,107,240,.18)
Hint:     10.5px; color:#8f7fa3; line-height:1.5
```

**`<select>` wird durch Segment-Buttons ersetzt** (das ist der Kern von "weniger steif"):

```
Gruppe:   display:flex; gap:8px
Button:   flex:1; padding:11px 0; border-radius:14px;
          font:600 12.5px Inter; transition:all .28s
  inaktiv: background:rgba(255,255,255,.045); color:#8f7fa3;
           border:1px solid rgba(157,107,240,.2)
  aktiv:   background:linear-gradient(135deg,#b48af0,#6425b0); color:#fff;
           border:1px solid transparent
```

- `gender`: weiblich · männlich · divers
- `player_view`: Männlich · Weiblich · Neutral (Default **Neutral**)

Die Werte müssen exakt den heutigen `<option>`-Werten entsprechen, damit die API unverändert bleibt.

### Chips (Schritt 4)

Entspricht `.mood-chip`, leicht angepasst:

```
padding:8px 14px; border-radius:999px; font:500 12px Inter;
transition:all .25s cubic-bezier(.34,1.4,.5,1)
inaktiv: background:rgba(255,255,255,.045); color:#cdbede;
         border:1px solid rgba(157,107,240,.26); transform:none
aktiv:   background:linear-gradient(135deg,#b48af0,#6425b0); color:#fff;
         border:1px solid transparent; transform:scale(1.04)
NSFW-Chip (falls vorhanden): border-color:rgba(239,77,99,.55); color:#f87171;
         aktiv -> background:var(--grad-ember)
```

Weiter `buildMoodPicker()` / `buildGenrePicker()` verwenden, nur die Klassenstyles anpassen.

### Toggle-Zeilen (Schritt 4)

```
Zeile:  display:flex; align-items:center; gap:12px;
        padding:13px 15px; border-radius:18px;
        background:rgba(255,255,255,.04);
        border:1px solid rgba(157,107,240,.18)
Titel:  13.5px/600 · Hint darunter: 10.5px, #8f7fa3
Track:  48×27px; border-radius:20px
        aus: rgba(255,255,255,.12)
        an (Öffentlich): linear-gradient(135deg,#b48af0,#6425b0)
        an (NSFW):       linear-gradient(135deg,#ef4d63,#7a1424)
Knob:   21×21px; top:3px; left:3px -> 24px;
        transition:left .28s cubic-bezier(.34,1.56,.64,1)
```

Entspricht dem bestehenden `.toggle` — nur mit Beschreibungstext und Karten-Rahmen.

### Schritt 5 — Live-Vorschau

```
Slide:  display:flex; flex-direction:column; align-items:center; gap:14px;
        min-height:100%      /* damit der untere Block wirklich unten sitzt */
Karte:  width:100%; max-width:255px; aspect-ratio:.745;
        border-radius:28px; overflow:hidden;
        border:1px solid rgba(202,164,106,.35);
        box-shadow:0 24px 50px -18px rgba(0,0,0,.9);
        Bild bzw. Fallback-Verlauf + Initial (Cinzel 118px, rgba(255,255,255,.14))
        Verlauf: linear-gradient(180deg,transparent 45%,rgba(8,5,12,.95))
        Name: Cinzel 600, 21px · Sub: 11.5px #cdbede · padding:16px
        Einblendung: animation "cardIn" .6s cubic-bezier(.22,.9,.3,1)
Status: "✓ Automatisch zwischengespeichert" — 11px, #caa46a
        (nutzt #autosave-status, Klasse .saving = Gold während des Speicherns)
Danger: width:100%; padding:13px; border-radius:18px;
        background:rgba(239,77,99,.12); color:#ef4d63;
        border:1px solid rgba(239,77,99,.4)
        (nur im Bearbeiten-Modus sichtbar)

Statuszeile und Löschen-Button folgen direkt unter der Vorschaukarte
(gleicher 14px-Abstand wie im übrigen Slide) — nicht an den unteren Rand gepinnt.
Kein zweiter Speichern-Button in diesem Slide — gespeichert wird ausschließlich
über den Primärbutton der Fußleiste ("Speichern & ansehen").
```

Die Vorschau zeigt live die eingegebenen Werte (Name, gewählte Genres/Typen, Bild).

### Fußleiste (immer sichtbar)

```
display:flex; gap:10px; padding:12px 18px 6px
Zurück: padding:14px 20px; border-radius:18px;
        border:1px solid rgba(157,107,240,.3);
        background:rgba(255,255,255,.04); color:#cdbede; font:600 13px
        opacity:.35 auf Schritt 1
Weiter: flex:1; padding:14px; border-radius:18px; border:0;
        background:linear-gradient(135deg,#b48af0,#6425b0); color:#fff;
        font:700 13.5px; box-shadow:0 10px 26px rgba(124,77,196,.45)
        Label: "Weiter ›" — auf Schritt 5: "Speichern & ansehen"
```

### Validierung

- Schritt 2: `first_name` ist Pflicht → beim Weitergehen prüfen, sonst Feldrahmen `#ef4d63` + Fehlermeldung im bestehenden `#character-error`-Stil (`color:var(--danger); font-size:.85rem`).
- Schritt 3: `intro_message` ist Pflicht (max. 2500 Zeichen).
- Schritt 4: mindestens 1 Genre — heutige Meldung beibehalten: „Bitte mindestens ein Genre auswählen."
- Der Sprung über die Fortschrittssegmente darf die Validierung überspringen (freies Navigieren); geprüft wird erst beim Speichern, exakt wie heute.

### Universum-Modus

Der bestehende Umschalter `🎭 Einzelner Charakter / 🌌 Universum` bleibt erhalten. Empfehlung: Er sitzt in Schritt 1 über dem Bildfeld (gleicher Segment-Stil wie der Quellen-Umschalter oben, `transition:transform .45s cubic-bezier(.34,1.4,.5,1)`). Das Universum-Formular (`#form-universe`) ist **nicht** Teil dieses Redesigns und behält seine bisherige Darstellung.

---

## Screen 3 — "Identitäten" als 3-Schritt-Wizard

Gleiche Systematik wie Screen 2, nur kürzer. Ersetzt das Formular `#form-identity`.

Die bestehende Identitäten-**Liste** (`#identity-list`) bleibt unverändert, wie sie heute ist — es kommt **kein** zusätzlicher Chip-Streifen über dem Wizard. Der Wizard öffnet sich wie bisher über „＋ Neue Identität" (`#btn-new-identity`) bzw. beim Antippen einer Identität (`startIdentityEdit(i)`).

Identitäten im **alten Format** (`i.legacy`) behalten die heutige rote Markierung: `border-color:rgba(239,77,99,.6); background:rgba(239,77,99,.08)` und den Hinweistext im Wizard (`#identity-legacy-hint`).

### Schritte

| # | Titel | Inhalt |
|---|---|---|
| 1 | **Dein Gesicht** | Bild-Upload, rund: 170×170px, `border-radius:50%`, `border:2px dashed rgba(202,164,106,.45)`, `background:linear-gradient(150deg,rgba(124,77,196,.35),rgba(20,13,28,.9))`, sanftes Atmen: `animation:breathe 4.5s ease-in-out infinite` (`opacity .55→1`, `scale 1→1.06`). Darunter der Hinweis, dass Identitäten privat sind. |
| 2 | **Wer bist du?** | `first_name`, `last_name`, `age`, `gender` (Segment-Buttons) |
| 3 | **Dein Wesen** | `description` (Textarea, 7 Zeilen), `#identity-moods` Chips, Hinweis „Alle Felder optional — mindestens eines muss ausgefüllt sein.", direkt darunter der Button **„Identität löschen"** (`#btn-identity-delete`, nur im Bearbeiten-Modus sichtbar: `width:100%; padding:13px; border-radius:18px; background:rgba(239,77,99,.12); color:#ef4d63; border:1px solid rgba(239,77,99,.4)`) — nicht an den unteren Rand gepinnt. **Kein** Speichern-Button im Slide — gespeichert wird über den Primärbutton der Fußleiste („Identität speichern"). |

Kopfzeile: `SCHRITT n VON 3 · PRIVAT` (10.5px, `letter-spacing:.16em`, uppercase, `#caa46a`), Titel Cinzel 22px, 🪄-Button = `#btn-ai-identity`.
Fortschritt (3 Segmente), Slide-Track (`width:300%`, `translateX(-{step*33.3333}%)`) und Fußleiste identisch zu Screen 2. Der Abbrechen-Button des heutigen Formulars entfällt — Zurücknavigation läuft über „‹ Zurück" und die Zurück-Geste.

---

## Interactions & Behavior

| Interaktion | Verhalten |
|---|---|
| Quellen-Umschalter | Indikator gleitet, `transform .45s cubic-bezier(.34,1.4,.5,1)`; Inhalt darunter wechselt wie bisher (BT-Grid ⇄ Tipsy-Grid) |
| Hero-Karussell | Natives horizontales Scrollen mit `scroll-snap-type:x mandatory`, Karten `scroll-snap-align:center`; Tippen öffnet das Charakter-Modal |
| Wizard "Weiter"/"Zurück" | Track verschiebt sich, `transform .55s cubic-bezier(.3,1.05,.4,1)`; Fortschrittsbalken füllt sich `.5s cubic-bezier(.22,.9,.3,1)` |
| Fortschrittssegment antippen | Direkter Sprung zu diesem Schritt |
| Chips | Umschalten mit `transform:scale(1.04)` und Farbwechsel, `.25s cubic-bezier(.34,1.4,.5,1)` |
| Toggles | Knopf gleitet `.28s cubic-bezier(.34,1.56,.64,1)` |
| Screen-/Tab-Wechsel | Bestehende Einblendung beibehalten: `opacity 0→1`, `translateY(12px)→0`, `.4s cubic-bezier(.22,.9,.3,1)` |
| Buttons gedrückt | `transform:scale(.96)` (bestehendes `.btn:active`) |
| Karten gedrückt | `transform:scale(.96)`, `border-color:#caa46a` (bestehendes `.char-card:active`) |
| Autosave | Unverändert: Debounce, `#autosave-status` wird während des Speicherns gold (`.saving`) |
| Fehler | Bestehende `.form-error`-Container weiterverwenden; zusätzlich Feldrahmen in `#ef4d63` |
| `prefers-reduced-motion: reduce` | Shimmer, Ember und Atem-Animation abschalten, Slide-Transitions auf `.01ms` reduzieren |

## State Management

Vanilla-JS, im bestehenden `S`-Objekt in `app.js` ergänzen:

```js
S.homeSource   // 0 = BEYOND TELLING, 1 = TIPSY   (bereits vorhanden als src-Umschalter)
S.createStep   // 0..4 — aktueller Wizard-Schritt "Erstellen"
S.identityStep // 0..2 — aktueller Wizard-Schritt "Identität"
```

- Beide Step-Werte beim Verlassen des Tabs **nicht** zurücksetzen (Rückkehr landet im letzten Schritt); beim Klick auf „＋ Neuen Charakter erstellen" / „＋ Neu" auf 0 zurücksetzen.
- Schritt-Wert in `restoreView()` mitspeichern, falls die App neu geladen wird (analog zum bestehenden Draft-Handling).
- Datenfluss unverändert: `api('characters&scope=feed')`, `api('character_get&id=…')`, `autosaveField()`, `api('identities')`.

## Design Tokens

Alle bereits in `assets/app.css` unter `:root` vorhanden — bitte diese Variablen verwenden, keine neuen Farben:

| Token | Wert | Verwendung hier |
|---|---|---|
| `--background-color` | `#0d0912` | Seitenhintergrund |
| `--surface-color` | `#1b1428` | Bild-Platzhalter |
| `--primary-color` | `#7c4dc4` | Primärfarbe |
| `--twitch` | `#9d6bf0` | Aktive Navigation, Fokus-Ringe |
| `--gold` | `#caa46a` | Akzent, Schrittzähler, Badges |
| `--gold-soft` | `#e6cf96` | Sekundäres Gold (Buttontext) |
| `--text-primary` | `#f5eee6` | Fließtext |
| `--text-secondary` | `#cdbede` | Sub-Zeilen |
| `--text-muted` | `#8f7fa3` | Labels, Hinweise, inaktive Zustände |
| `--danger` | `#ef4d63` | Löschen, Fehler, NSFW |
| `--grad-purple` | `linear-gradient(135deg,#b48af0,#6425b0)` | Primärbuttons, aktive Chips, Toggle an |
| `--grad-premium` | `linear-gradient(135deg,#caa46a,#f2dfae 50%,#caa46a)` | Wortmarke, Badges, 🪄-Button |
| `--grad-ember` | `linear-gradient(135deg,#ef4d63,#7a1424)` | NSFW |
| `--glass-border` | `rgba(157,107,240,.28)` | Rahmen |
| `--ease` | `cubic-bezier(.22,.9,.3,1)` | Standard-Easing |
| `--ease-back` | `cubic-bezier(.34,1.56,.64,1)` | Überschwingen (Toggle, Chips) |

Zusätzlich im Redesign verwendet (bitte als neue Variablen anlegen, falls gewünscht):
`cubic-bezier(.34,1.4,.5,1)` (Umschalter-Indikator) und `cubic-bezier(.3,1.05,.4,1)` (Wizard-Slides).

**Radien:** 999px (Pills), 26px (Hero-Karte), 24px/22px (Vorschau, Karten), 18px (Buttons, Toggle-Zeilen), 16px (Eingabefelder), 14px (Segment-Buttons), 4px (Fortschritt).

**Abstände:** 18px horizontaler Seitenrand · 14px Karussell-Gap · 8–10px Chip-Gap · 14px Feld-Gap · 5px Fortschritt-Gap.

**Typografie:**
`Cinzel` 500/600/700 für Überschriften, Namen, Wortmarke.
`Inter` 400/500/600/700 für alles andere.
Skala: 9.5 · 10.5 · 11 · 11.5 · 12 · 12.5 · 13 · 13.5 · 14 · 15 · 16(Eingabe) · 18 · 19 · 21 · 22px.
Beide Fonts werden bereits in `index.php` geladen.

**Schatten:**
`0 22px 44px -18px rgba(0,0,0,.9)` (Hero-Karte) ·
`0 10px 30px rgba(124,77,196,.5)` (Primärbutton) ·
`0 6px 20px rgba(124,77,196,.5)` (Umschalter-Indikator) ·
`0 20px 46px -14px rgba(0,0,0,.85)` (Bottom-Nav, bestehend).

## Bottom-Navigation

**Unverändert.** Die bestehende `.bottom-nav` aus `app.css` bleibt exakt wie sie ist (74px hohe Pill, angehobener goldener „Erstellen"-Kreis, violette Aktiv-Farbe mit Glow). Kein neues Design, keine Änderungen an Höhe, Radius, Farben oder Icons.

## Assets
- **Keine neuen Assets.** Charakter- und Identitätsbilder kommen wie bisher aus der API (`API?action=image&id=…&token=…`); ohne Bild wird der Fallback-Verlauf mit dem Initial des Vornamens gezeigt (wie heute `.char-card-img`).
- Alle Icons sind Inline-SVGs; Lupe und Trichter stammen 1:1 aus `index.php`, die Navigations-Icons ebenfalls.
- Die Farbverläufe der Platzhalterbilder im Prototyp (`linear-gradient(150deg,#4a2478,#170d26)` u. a.) sind reine Demo-Werte und werden in der App nicht gebraucht — dort steht das echte Bild oder `--surface-color`.
- Schriften: Google Fonts Cinzel + Inter, bereits eingebunden.

## Files

| Datei | Inhalt |
|---|---|
| `prototype.dc.html` | Der interaktive Prototyp: Startseite (oberer Bereich), Erstellen-Wizard, Identitäten-Wizard. Im Browser öffnen. |
| `support.js` | Laufzeit des Prototyps — **nur zum Ansehen nötig, nicht übernehmen**. |
| `android-frame.jsx` | Android-Geräterahmen des Prototyps — **nur Darstellung, nicht übernehmen**. |

Referenz auf der Zielseite (nicht in diesem Bundle enthalten): `index.php`, `assets/app.css`, `assets/app.js` der bestehenden App.
