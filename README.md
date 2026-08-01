# Handoff: BEYOND TELLING — Admin-Oberfläche (Neugestaltung)

## Overview

Neugestaltung der Admin-Seite `admin/beyond-telling.php` des LikeDennis-Portals.
Aus der bisherigen Sidebar-Seite mit langen Formular-Listen wird eine **Übersichts-Startseite plus vier Unterbereiche** mit oberer Segment-Navigation, Kachel-Layouts und durchgängigen Animationen.

**Wichtig: Der Funktionsumfang und die Inhalte bleiben unverändert.** Es geht ausschließlich um Struktur und Aussehen. Alle bestehenden Formulare, POST-Aktionen (`bt_action`), CSRF-Prüfung, Tab-Logik (`?tab=`), Chat-Einsichtssperre (`BT_CHAT_READABLE_USERS`, `?tab=sc`) und die Medien-Auslieferung (`?media=`, `?media_setting=`) bleiben genau so bestehen wie in `source/beyond-telling.php`.

---

## About the Design Files

Die Dateien in `design/` sind **Design-Referenzen in HTML** — Prototypen, die Aussehen und Verhalten zeigen. Sie sind **kein Produktionscode zum Kopieren**.

Aufgabe: **Diese Designs in der bestehenden Umgebung nachbauen** — hier also in PHP (`admin/beyond-telling.php`) mit `assets/css/style.css` und dem bestehenden `<style>`-Block der Seite. Die Prototypen nutzen React-artige Templates (`.dc.html`) nur zu Präsentationszwecken; im Zielsystem wird daraus normales PHP/HTML/CSS mit etwas Vanilla-JS.

**Öffnen der Prototypen:** `design/BT Admin.dc.html` im Browser öffnen (die Datei lädt `support.js` aus demselben Ordner). Klickbar sind alle Navigationselemente, Kacheln und Unter-Tabs.

### Dateien

| Datei | Inhalt |
|---|---|
| `design/BT Admin.dc.html` | **Der finale Entwurf.** Alle fünf Seiten, klickbar. |
| `design/BT Admin Übersicht (3 Layout-Varianten).dc.html` | Die drei ursprünglichen Layout-Richtungen (1a/1b/1c). Nur zur Einordnung — gebaut wurde **1a**. |
| `design/support.js` | Laufzeit für die Prototypen. Wird im Zielsystem **nicht** gebraucht. |
| `source/beyond-telling.php` | Die bestehende Seite (2.374 Zeilen), Stand vor dem Umbau. Maßgeblich für alle Funktionen. |

---

## Fidelity

**High-fidelity.** Farben, Typografie, Abstände, Radien, Schatten und Animationen sind final und unten exakt dokumentiert. Die Umsetzung soll pixelnah erfolgen, aber die vorhandene `assets/css/style.css` (Glass-Variablen, Header, Container) weiterverwenden.

Die im Prototyp gezeigten **Zahlen und Datensätze sind Platzhalter** (1.284 User, 8.931 Chats, „Grok 4.1 Fast", „Aurelia" …). Sie kommen im Zielsystem aus den bestehenden Queries auf `users`, `bt_profiles`, `bt_chats`, `bt_messages`, `bt_models`, `bt_providers`, `bt_generators`, `bt_settings`.

---

## Design Tokens

### Farben

| Rolle | Wert |
|---|---|
| Seiten-Hintergrund | `#0b0812` |
| Text primär | `#ece9f5` |
| Text sekundär | `#b9b2ce` / `#cfc8e0` |
| Text gedämpft (muted) | `#7d7691` |
| Placeholder | `#6b6480` |
| Akzent-Verlauf (Primär-Button, aktiver Tab) | `linear-gradient(135deg,#9147FF,#772ce8)` |
| Akzent hell (Text auf Lila-Flächen) | `#c4b3ee` |
| Link / Link-Hover | `#b98bff` / `#d3b8ff` |
| Glass-Fläche | `rgba(255,255,255,.05)` |
| Glass-Rand | `rgba(255,255,255,.1)` |
| Innenfläche (Block auf Karte) | `rgba(0,0,0,.2)` – `rgba(0,0,0,.25)` |
| Eingabefeld-Hintergrund | `rgba(0,0,0,.45)` |
| Eingabefeld-Rand | `rgba(255,255,255,.12)` |
| Erfolg / aktiv | `#4ade80`, Fläche `rgba(74,222,128,.1)`, Rand `rgba(74,222,128,.3)` |
| Warnung | `#fbbf24`, Fläche `rgba(251,191,36,.07–.14)`, Rand `rgba(251,191,36,.3)` |
| Fehler / gesperrt | `#f87171` (Text hell `#fca5a5`), Fläche `rgba(239,68,68,.06–.14)`, Rand `rgba(239,68,68,.32)` |
| Hover-Fläche (Kachel) | `rgba(145,71,255,.09–.12)`, Rand `rgba(145,71,255,.45)` |

Die Werte decken sich mit dem bestehenden `#9147FF`/`#772ce8`-Akzent der Seite. Wo möglich die vorhandenen CSS-Variablen (`--glass-bg`, `--glass-border`, `--text-muted`, `--border-color`, `--accent-color`) weiterverwenden.

### Typografie

- Familie: `system-ui, -apple-system, "Segoe UI", sans-serif`
- `font-variant-numeric: tabular-nums` global (Zahlen springen sonst beim Hochzählen)
- Skala:

| Element | Größe / Gewicht |
|---|---|
| Hero-Überschrift („Was möchtest du verwalten?") | 28 px / 800, `letter-spacing:-.02em` |
| Seitentitel („App-Verwaltung") | 26 px / 800, `-.015em` |
| KPI-Zahl | 40 px / 800, `-.02em` |
| KPI-Zahl (Nachkomma-Suffix „,80 $") | 21 px / normal, Farbe `#b9b2ce` |
| Detail-Titel (Modellname in der Detailansicht) | 20 px / 800 |
| Karten-Titel (`h3`-Ersatz) | 15 px / 700 |
| Kachel-Titel | 14–14,5 px / 700 |
| Navigations-Button | 13,5 px / 650 |
| Fließtext / Tabellenzelle | 12,5–13 px |
| Sekundärzeile | 11,5 px, `#7d7691` |
| Label über Eingabefeld | 10,5 px / 700, `text-transform:uppercase`, `letter-spacing:.05em`, `#7d7691` |
| Abschnitts-Label („SCHNELLZUGRIFF") | 12 px / 700, uppercase, `letter-spacing:.07em` |
| Badge / Chip | 10,5–11,5 px / 600–700 |

### Abstände, Radien, Schatten

- Abstandsraster: 4 / 6 / 8 / 10 / 12 / 14 / 16 / 18 / 20 / 22 / 26 px
- Radien: 8 px (Chip/kleiner Button) · 9–11 px (Button, Eingabefeld) · 12 px (Nav-Pille, Block) · 14–16 px (Karte, Kachel) · 20 px (Status-Pille) · 50 % (Punkt)
- Karten-Padding: 20–22 px · Kachel-Padding: 17–18 px · Block-Padding: 14–16 px
- Schatten aktiver Tab: `0 8px 22px rgba(145,71,255,.4), inset 0 1px 0 rgba(255,255,255,.22)`
- Schatten Suchfeld: `0 0 0 6px rgba(145,71,255,.08), 0 18px 44px rgba(0,0,0,.4)` (Hover: `0 0 0 8px rgba(145,71,255,.13), 0 18px 44px rgba(0,0,0,.5)`)
- `backdrop-filter: blur(8px)` auf Karten, `blur(16px)` auf der Kopfleiste

### Container

- Inhaltsbreite: `max-width:1400px; margin:0 auto; padding:26px 26px 60px`
- Kopfleiste: `position:sticky; top:0; z-index:20`, Hintergrund `rgba(10,7,17,.78)`, Unterkante `1px solid rgba(255,255,255,.08)`

---

## Screens / Views

Fünf Hauptansichten. Die Kopfleiste ist auf allen identisch und bleibt fixiert.

### Kopfleiste (global)

`display:flex; flex-wrap:wrap; gap:14px; padding:14px 26px`, von links nach rechts:

1. **Logo** — 26×26 px Quadrat, `border-radius:8px`, Akzent-Verlauf, Emoji 📖; daneben „BEYOND TELLING" (14 px/800). Im Zielsystem: `<?php echo e($site_name); ?>`.
2. Trennstrich 1×20 px, `rgba(255,255,255,.12)`.
3. **Segment-Navigation** — Container `padding:5px; gap:5px; border-radius:16px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08)`. Fünf Buttons: `◱ Übersicht`, `👥 Nutzer`, `📚 Inhalte`, `🤖 KI`, `⚙️ App`.
   - Button: `padding:11px 19px; border-radius:12px; font-size:13.5px; font-weight:650; border:1px solid transparent; transition:.26s cubic-bezier(.2,.8,.2,1)`; Icon 15 px, `gap:8px`.
   - Inaktiv: `background:transparent; color:#b9b2ce`. Hover: `background:rgba(255,255,255,.08); color:#fff`.
   - Aktiv: Akzent-Verlauf, `color:#fff`, Schatten s. o.
4. Spacer (`flex:1`).
5. **Status-Pille** „Alle Dienste laufen" — `padding:8px 14px; border-radius:20px`, grüne Fläche/Rand, davor 7 px Punkt mit `dvPulseG`-Animation. Bei Störung: rote Variante mit `dvPulse`.
6. **„← Admin Portal"** — `<a href="../index.php">`, `padding:9px 15px; border-radius:11px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); font-weight:600`. Hover: lila Fläche/Rand + `transform:translateX(-3px)`. Pfeil ist ein eigenes `<span style="font-size:14px">←</span>`.
7. **„🚪 Logout"** — gleiche Basis, Hover rot: `background:rgba(239,68,68,.16); border-color:rgba(239,68,68,.45); color:#fca5a5`. Ziel: `../?action=logout`.

---

### 1 · Übersicht (Startseite, `?tab=` leer / `home`)

Zweck: alles Wichtige auf einen Blick; Einstieg in alle Unterbereiche.

Aufbau von oben nach unten:

1. **Seitentitel** — „App-Verwaltung" (26 px/800) + Zeile „Alles Wichtige auf einen Blick · Stand HH:MM Uhr" (12,5 px, `#7d7691`). `margin-bottom:20px`.

2. **Such-Hero** — zentriert, `padding:14px 0 30px`.
   - Überschrift „Was möchtest du verwalten?" (28 px/800).
   - Zeile mit Gesamtzahlen: „1.284 User · 8.931 Chats · 168 Charaktere · 21 Modelle" (13 px, `#7d7691`).
   - Suchfeld: `max-width:620px; margin:0 auto; padding:15px 20px; border-radius:16px; background:rgba(0,0,0,.45); border:1px solid rgba(145,71,255,.35)` + Schatten. Inhalt: 🔍 (16 px) · blinkender Cursor (1,5×17 px, `#9147FF`, Animation `dvCaret`) · Platzhaltertext „User, Chat, Charakter, Modell oder Einstellung…" (14 px, `#7d7691`). Cursor steht **links vor** dem Text.
   - Vier Schnellaktions-Chips darunter (`gap:7px`, zentriert): „Guthaben aufladen" → Nutzer, „Modell anlegen" → KI, „Charakter importieren" → Inhalte/Import, „APK-Link ändern" → App/APK. Chip: `padding:6px 13px; border-radius:20px; font-size:11.5px`, Hover lila + `translateY(-2px)`.

3. **Warn-Leiste** — nur wenn es Warnungen gibt. `padding:14px 16px; border-radius:14px; background:linear-gradient(90deg,rgba(239,68,68,.14),rgba(239,68,68,.04)); border:1px solid rgba(239,68,68,.32)`. Links 9 px Punkt mit `dvPulse`. Titel „N Punkte brauchen Aufmerksamkeit" (13,5 px/700, `#fca5a5`), darunter eine Chip-Reihe, jeder Chip verlinkt in den zuständigen Bereich:
   - „💳 {Anbieter} — kein Guthaben · seit HH:MM" → KI
   - „🔑 N offene Passwort-Resets" → Nutzer
   - „🚫 N gesperrter Nutzer-Key · {name}" → KI

4. **Vier KPI-Kacheln** — `grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:14px`. Kachel: `padding:20px; border-radius:16px`, Glass, Hover `translateY(-5px)` + lila Rand.
   1. **CHATS** — Zahl 8.931 (hochzählend), Zeile „▲ 312 heute · 46 aktive User" in `#4ade80`, darunter 4 px Fortschrittsbalken (88 %, Akzent-Verlauf, Animation `dvBar`). Klick → Nutzer/Chats.
   2. **GUTHABEN INSGESAMT** — 742 + „,80 $", Zeile „14 User unter 1 $" in `#fbbf24`, Balken 54 % in Gelb-Verlauf. Klick → Nutzer.
   3. **KOSTEN · 30 TAGE** — 318 + „,42 $", Zeile „Ø 10,61 $ / Tag", darunter Mini-Balkendiagramm: 7 Säulen, Höhe 38/62/48/80/56/94/72 %, `gap:3px`, Höhe 26 px, Farben von `rgba(145,71,255,.45)` bis `#a86bff`, Animation `dvGrow` gestaffelt.
   4. **BETA** — Kopfzeile „Beta" + Badge „TOP 3 TEST-MODELLE" (lila Chip). Darunter drei Zeilen, jeweils: Rang-Quadrat 20×20 px (`border-radius:6px`; Rang 1 Akzent-Verlauf, Rang 2 `rgba(145,71,255,.3)`, Rang 3 `rgba(145,71,255,.18)`), Modellname (13 px/700, `text-overflow:ellipsis`), Unterzeile „142× genutzt · 38 Bewertungen" (11 px, `#7d7691`), rechts Bewertung „★ 4,6" (12,5 px/700, `#fbbf24`). Klick → KI/Betas.
      Datenquelle im Zielsystem: Test-Modelle (`bt_models.is_test = 1`) nach Nutzungszahl der letzten 30 Tage sortiert, mit Durchschnitts-Bewertung.

5. **Zweispaltiger Block** — `grid-template-columns:repeat(auto-fit,minmax(330px,1fr)); gap:16px`, linke Spalte `grid-column:span 2`.
   - **Links: Schnellzugriff** — Label „SCHNELLZUGRIFF", darunter Kachel-Grid `repeat(auto-fit,minmax(190px,1fr)); gap:12px` mit zehn Kacheln (Icon 22 px, Titel 14 px/700, Unterzeile 11,5 px):
     App-User · Chats · Charaktere · Charakter-Import · Typen & Genres · **Modelle & API-Keys** (roter Rand + Zähler-Badge, wenn ein Anbieter gesperrt ist) · Bild/Video/TTS-KI · Betas · Allgemein · APK & Links.
     Hover: `translateY(-4px) scale(1.015)` + lila Fläche/Rand.
   - **Rechts (Spalte, `gap:14px`)**:
     - Karte **„ANBIETER & MODELLE"**: pro Anbieter eine Zeile mit Status-Punkt (grün / rot+`dvPulse`), Name, rechts „N Modelle" bzw. „gesperrt". Danach Trennlinie und „Meistgenutzt · 7 Tage" mit drei beschrifteten 5-px-Balken (41 % / 28 % / 19 %).
     - Karte **„LETZTE AKTIVITÄT"** mit grünem Blink-Punkt (`dvBlink`): sechs Zeilen `HH:MM` (38 px breit, `#7d7691`) + Text, Name in `<b>`.

---

### 2 · Nutzer (`?tab=users` / `?tab=chats`)

Brotkrume „👥 Nutzer → **{Unterseite}**" (12 px, `#7d7691`), darunter Unter-Tabs als Pillen (`padding:9px 16px; border-radius:11px; font-size:13px/600`; aktiv = Akzent-Verlauf, inaktiv = `rgba(255,255,255,.05)` + Rand `rgba(255,255,255,.12)`).

**2a · App-User**

1. Karte **„🔑 Offene Passwort-Reset-Anfragen (N)"** in Gelb (`rgba(251,191,36,.07)` / Rand `.32`) — nur wenn vorhanden. Pro Zeile: Benutzername (fett, min. 90 px), E-Mail + „angefragt HH:MM", Button „Freigeben" (Akzent) und „Ablehnen" (rot, `rgba(239,68,68,.15)`). Aktionen: bestehende `reset_decide`.
2. Karte **„App-Profile (N)"** mit Filterfeld rechts oben. Pro Nutzer ein Block (`padding:14px 16px; border-radius:12px; background:rgba(0,0,0,.2)`, Hover lila Rand):
   Avatar 34×34 px (`border-radius:11px`, Verlauf, Initiale), Name + ggf. Badge „ADMIN", Unterzeile „E-Mail · seit TT.MM.JJJJ", rechtsbündig Guthaben (14 px/700; unter 1 $ in `#fbbf24`), Status-Pille (aktiv grün / „Reset offen" gelb / „gesperrt" grau), Button „Bearbeiten".
   Aufgeklappt (erste Zeile im Prototyp): Aktions-Chips „+ Guthaben", „Passwort-Reset erzwingen", „App-Abmeldung erzwingen" → `credit_adjust`, `force_reset`, `force_logout`.
   Darunter zentriert „Weitere N Profile laden".
3. Karte **„💰 Guthaben"** — Erklärtext, drei Kennzahl-Blöcke (`repeat(auto-fit,minmax(230px,1fr))`): Summe aller Konten · Unter 1 $ (gelber Rand) · Aufgeladen 30 Tage. Darunter Inline-Formular: Nutzer-Select + Betragsfeld + „$" + Button „Buchen" (`credit_adjust`).

**2b · Chats**

1. Vier kleine Kennzahl-Kacheln (`minmax(220px,1fr)`): Chats gesamt · Nachrichten · Tokens 30 Tage · Ø Kosten / Chat.
2. Karte **„Modell-Nutzung gesamt"** mit Hinweistext („Bilder, Videos und TTS sind nicht enthalten"), pro Modell eine Zeile: Name (150 px), 8-px-Balken, rechts „26,3 Mio Tok · 128,40 $" (130 px, rechtsbündig).
3. Karte **„Alle Chats (N)"** — Filterfeld, dann Tabelle als CSS-Grid `60px 1.2fr 1.4fr 90px 90px 1fr 110px`, Kopfzeile 11 px uppercase, Zeilen `padding:12px; border-radius:10px; background:rgba(0,0,0,.22)`, Hover `rgba(145,71,255,.1)`. Spalten: # · User · Partner · Nachr. · Kosten · Modelle · Aktion.
   **Aktionsspalte:** „Einsehen" (lila) nur bei Chats, die laut `$mayReadChat()` lesbar sind — sonst „🔒 gesperrt" (grau, nicht klickbar). Diese Regel muss serverseitig bleiben; `?tab=sc` hebt sie weiterhin auf und bleibt unverlinkt.
   Horizontal scrollbar über `overflow-x:auto` mit `min-width:720px`.

---

### 3 · Inhalte (`?tab=chars` / `import` / `tags`)

Brotkrume „📚 Inhalte → …", Unter-Tabs „Charaktere (NSFW)", „Charakter-Import", „Typen & Genres".

**3a · Charaktere** — Filterzeile: Pillen „Alle · 168" (aktiv), „Aktiv · 151", „NSFW · 62", „Entwürfe · 17"; rechts Primär-Button „＋ Charakter anlegen".
Karten-Grid `repeat(auto-fill,minmax(210px,1fr)); gap:14px`. Karte: Bildfläche 150 px hoch (im Prototyp Farbverlauf mit Initiale — im Zielsystem das echte Charakterbild aus `pb/`), oben rechts Badge „NSFW" (`rgba(239,68,68,.85)`) bzw. „ENTWURF" (schwarz, Karte auf `opacity:.55`); darunter `padding:14px`: Name (14 px/700), Genres (11,5 px, `#7d7691`), „N Chats" (11,5 px, `#4ade80`). Hover: `translateY(-6px)` + lila Rand.
Letzte Kachel: gestrichelter Rahmen, „＋ Neuer Charakter", `min-height:222px`.

**3b · Charakter-Import** — Karte mit Erklärtext und Dropzone: `padding:44px 20px; border-radius:14px; border:1.5px dashed rgba(145,71,255,.4); background:rgba(145,71,255,.05)`, Icon 📥 30 px, „Datei hierher ziehen", „PNG · JSON · max. 8 MB". Hover: Fläche/Rand kräftiger + `scale(1.008)`.
Darunter Karte „Letzte Importe": Zeilen mit Name, Dateiname + Größe, Zeitangabe, Status-Chip (übernommen grün / Entwurf gelb / fehlgeschlagen rot).

**3c · Typen & Genres** — zwei Karten nebeneinander (`repeat(auto-fit,minmax(300px,1fr))`).
Chips: `padding:7px 10px 7px 14px; border-radius:20px`, Text + Anzahl (11 px, gedämpft) + „✕" (Hover `#f87171`). Typen neutral (`rgba(255,255,255,.06)`), Genres lila (`rgba(145,71,255,.16)`, Rand `.38`). Inaktive Einträge `opacity:.45`.
Unter jeder Liste: Eingabefeld + „＋"-Button.

---

### 4 · KI (`?tab=ai` / `media` / `betas`)

Brotkrume „🤖 KI → …", Unter-Tabs „Modelle & API-Keys", „Bild/Video/TTS-KI", „Betas".

**4a · Modelle & API-Keys — zweistufig: Kachel-Übersicht → Detail**

*Stufe 1 — Übersicht*

1. Rote Warnleiste, falls ein Anbieter gesperrt ist: „{Anbieter} — kein Guthaben seit HH:MM Uhr" + Erklärtext + Button „Sperre aufheben" (`credit_clear`).
2. Label „ANBIETER" + Hinweis „Zentrale Keys — antippen zum Bearbeiten". Kachel-Grid `repeat(auto-fill,minmax(240px,1fr)); gap:12px`. Kachel: Status-Punkt + Name (14,5 px/700) + Chevron „›" rechts; Unterzeile „Stil: openrouter · Key ••••• 8f2a"; Fußzeile „12 Modelle · aktiv". Gesperrter Anbieter: rote Fläche/Rand, pulsierender Punkt, Fußzeile „kein Guthaben · gesperrt".
3. Label „MODELLE · 21" + Hinweis „Kachel antippen, um die Einstellungen zu öffnen" + Primär-Button „＋ Modell anlegen". Kachel-Grid `repeat(auto-fill,minmax(258px,1fr)); gap:13px`. Kachel:
   - Name (14,5 px/700) + Chevron „›"
   - „{Anbieter} · {model_key}" (11,5 px, `#7d7691`)
   - Preiszeile: `priceIn` (17 px/800) + „/ {priceOut} $ je Mtok" (11,5 px)
   - Merkmal-Chips (10,5 px/600, `rgba(255,255,255,.07)`): „⭐ Standard", „NSFW", „Reasoning", „Free", „Privat", „Test", „🔒 gesperrt"
   - Gesperrte Modelle: Fläche `rgba(239,68,68,.07)`, Rand `rgba(239,68,68,.35)`
   - Hover: `translateY(-5px)` + Rand `rgba(145,71,255,.55)`
   Darunter zentriert „Weitere N Modelle anzeigen".

*Stufe 2 — Detail (nach Klick auf eine Kachel)*

Die Übersicht wird ersetzt (Einblendung von rechts, `dvR`), oben ein Zurück-Button „‹ Alle Modelle" bzw. „‹ Alle Anbieter" (Hover `translateX(-3px)`).

- **Modell-Detail** — Karte mit Titel (20 px/800), rechts „▲ nach oben" / „▼ nach unten" (`model_move`); Unterzeile „{Anbieter} · {model_key}".
  Felder-Grid `repeat(auto-fit,minmax(180px,1fr)); gap:12px`: Anzeigename · Modell-Key · Anbieter · Preis In / Mtok · Preis Out / Mtok · Rabatt (Test) · Kurzbeschreibung (volle Breite).
  Checkbox-Reihe (`gap:10px 22px`): Aktiv · NSFW erlaubt · Reasoning · Free · Privat 🛡️ · Test-Modell · ⭐ Standard.
  Fußzeile über Trennlinie: „Speichern" (Akzent), „Abbrechen", rechts „Modell löschen" (rot). Aktionen: bestehende `model_save`, `model_delete`.
  Darunter drei Kennzahl-Kacheln: Nutzung 30 Tage · Kosten 30 Tage · Bewertung (`★ 4,6`, `#fbbf24`).
  **Hinweis:** Kontext-/Output-Länge wird bewusst **nicht** pro Modell gesetzt (ergibt sich aus dem Kontext-Umfang des Users, 70/30) — wie im Bestandscode.
- **Anbieter-Detail** — Titel + rechts Status; Unterzeile „{slug} · Stil: {api_style} · Key {hint}". Felder: API-Base und API-Key (`placeholder="••••• gesetzt"`, Label erklärt „leer = unverändert, ‚-' = löschen"). Checkbox „Aktiv", Button „Speichern" (`provider_save`).
  Ist der Anbieter gesperrt, folgt darunter die rote Karte „Guthaben-Sperre aktiv seit HH:MM Uhr" mit Button „Sperre aufheben".
  *Für OpenRouter zusätzlich* (im Prototyp nicht ausgebaut, im Bestand vorhanden und beizubehalten): Liste „Bevorzugte OpenRouter-Provider" mit Hinzufügen/Entfernen (`openrouter_route_add`, `openrouter_route_delete`).

**4b · Bild/Video/TTS-KI** — drei Karten nebeneinander (`repeat(auto-fit,minmax(300px,1fr))`): Bild- / Video- / TTS-Generatoren. Karten-Kopf: Icon-Quadrat 32×32 px (`border-radius:10px`; lila / blau / pink hinterlegt), Titel + „N aktiv · Preis". Darunter Zeilen mit Status-Punkt, Name, rechts Detail (Auflösung, „mit Ton", Stimmen); inaktive Zeilen `opacity:.5`.
Darunter Karte „Generator bearbeiten · {Name}" mit Feldern Anzeigename · Modell-Key · Bildgröße · Preis / Bild · Prompt-Verbesserung (Select) und Checkboxen „Aktiv", „Bild mitschicken" + „Speichern" (`generator_save`).

**4c · Betas**

1. Karte **„Test-Modelle · Nutzung & Bewertung"** + Badge „30 TAGE". Pro Modell eine Zeile: Rang-Quadrat 28×28 px, Name (14,5 px/700) + „{Anbieter} · Rabatt N % · N Bewertungen", rechts ein Balkenblock mit „142× genutzt" / „★ 4,6" und 6-px-Balken in Gelb-Verlauf, ganz rechts Button „Freigeben". Rang 1 mit lila Verlaufsfläche und Rand. Gesperrte/abgeschlagene Modelle als gedimmte Zeile ohne Button.
   Dieselbe Datenbasis wie die BETA-Kachel auf der Übersicht.
2. Karte **„Beta-Funktionen"** — Zeilen mit Titel + Erklärung + Nutzerzahl und Toggle rechts (42×24 px, `border-radius:12px`; an = Akzent-Verlauf mit weißem 18-px-Knopf rechts, aus = `rgba(255,255,255,.12)` mit grauem Knopf links).

---

### 5 · App (`?tab=settings` / `system`)

Brotkrume „⚙️ App → …", Unter-Tabs „Allgemein", „APK & Links". Layout jeweils `repeat(auto-fit,minmax(320px,1fr)); gap:16px; align-items:start`.

**5a · Allgemein**
- Karte „Grundeinstellungen": Seitenname · Startguthaben für neue User · Kontext-Umfang (Select, „70 % In / 30 % Out") · Begrüßungstext (Textarea, 3 Zeilen). Button „Speichern" rechtsbündig.
- Karte „Tutorial-Logo": Vorschau 78×78 px (`border-radius:16px`, Verlauf), Dateiname + Größe, Buttons „Ersetzen" und „Entfernen" (rot). Bild kommt über `?media_setting=` aus `pb/`.
- Karte „Schalter": Registrierung offen · NSFW global erlauben · Wartungsmodus — je Titel + Erklärung + Toggle.

**5b · APK & Links**
- Karte „Aktuelle APK": Block mit 46×46-px-Icon 📦, „v2.4.1", „Datei · 38,4 MB · vor 6 Tagen", Status-Pille „live". Darunter Upload-Dropzone (gestrichelt, ⬆️, „Neue APK hochladen", „Version wird aus dem Manifest gelesen"). Fußzeile „Bisherige Versionen: …".
- Karte „Links": Download-Link · Datenschutz · Support/Kontakt · Mindest-Version (erzwingt Update) + „Speichern".

---

## Interactions & Behavior

### Navigation
- Kopfleisten-Buttons wechseln die Hauptansicht und setzen die Unterseite auf deren erste zurück (Nutzer→App-User, Inhalte→Charaktere, KI→Modelle & API-Keys, App→Allgemein).
- Jeder Wechsel scrollt nach oben (`window.scrollTo({top:0, behavior:'smooth'})`).
- Übersichts-Kacheln, Warn-Chips und Schnellaktions-Chips springen direkt in die passende Unterseite.
- **Im Zielsystem:** weiterhin serverseitige Navigation über `?tab=` — die Klickziele der Kacheln sind schlicht Links. Der KI-Detail-Zustand lässt sich als `?tab=ai&model=<id>` bzw. `&provider=<id>` abbilden.

### KI-Detailansicht
- Klick auf Modell-/Anbieter-Kachel → Detail; „‹ Zurück" → Übersicht. Beide Übergänge scrollen nach oben.
- Beim Wechsel zwischen zwei Detailansichten muss der Formularzustand neu aufgebaut werden (im Prototyp über `key`).

### Animationen

| Name | Definition | Einsatz |
|---|---|---|
| `dvUp` | `opacity 0→1`, `translateY(18px→0)` | Abschnitte beim Ansichtswechsel, `.55s cubic-bezier(.2,.8,.2,1)`, gestaffelt `.04s`/`.06s`/`.1s`… |
| `dvIn` | `opacity 0→1` | Kopfleiste, Brotkrume, `.4–.5s ease` |
| `dvL` / `dvR` | `translateX(∓22px→0)` + Fade | linke/rechte Spalte der Übersicht; KI-Detail (`dvR`), `.45–.6s` |
| `dvPop` | `scale .9 → 1.03 → 1` + Fade | Kacheln, gestaffelt ab `.05s` in `.04–.05s`-Schritten, `.45–.5s` |
| `dvBar` | `scaleX(0→1)`, `transform-origin:left` | Fortschritts-/Nutzungsbalken, `.9–1.1s`, Verzögerung `.25–.96s` |
| `dvGrow` | `scaleY(0→1)`, `transform-origin:bottom` | Säulen im Mini-Diagramm, `.5–.55s`, `.04s`-Staffel |
| `dvPulse` | roter Ring `box-shadow 0→11px`, transparent auslaufend | Fehler-/Sperr-Punkte, `2s infinite` |
| `dvPulseG` | grüner Ring, `0→9px` | Status „Alle Dienste laufen", `2.4s infinite` |
| `dvBlink` | `opacity .35↔1` | Live-Punkt bei „Letzte Aktivität", `1.8s infinite` |
| `dvCaret` | `opacity 1/0`, `step-end` | Cursor im Suchfeld, `1.1s infinite` |
| `dvBlob1` / `dvBlob2` | `translate` + `scale` | zwei unscharfe Farbflächen im Seitenhintergrund, `19s` / `24s ease-in-out infinite` |

**Hintergrund:** `position:fixed; inset:0; pointer-events:none; z-index:0` mit zwei Kreisen — oben links 620 px, `radial-gradient(circle,rgba(145,71,255,.3),transparent 68%)`, `filter:blur(44px)`; unten rechts 660 px, `rgba(56,120,255,.18)`, `blur(50px)`.

**Zahlen-Hochzählen:** Elemente mit `data-count="8931"` zählen beim Sichtbarwerden von 0 auf den Zielwert. `IntersectionObserver` (`threshold: .35`), Dauer 1000 ms, Easing `1-(1-p)³`, Ausgabe über `toLocaleString('de-DE')`. Jedes Element nur einmal. Nach jedem Ansichtswechsel neu einsammeln.

**Hover-Muster:**
- Kachel: `translateY(-4…-6px)` (teils `scale(1.015)`), lila Fläche + Rand, `transition:.26s cubic-bezier(.2,.8,.2,1)`
- Listenzeile Betas: `translateX(4px)`
- Zurück-Button: `translateX(-3px)`; „← Admin Portal": `translateX(-3px)`
- Primär-Button: `translateY(-2px)`
- Tabellenzeile: Hintergrund → `rgba(145,71,255,.1)`

### Responsive

Keine Media-Queries — alle Raster nutzen `repeat(auto-fit|auto-fill, minmax(…, 1fr))` und brechen dadurch von selbst um. Kopfleiste und Chip-Reihen sind `flex-wrap:wrap`. Die Chat-Tabelle scrollt horizontal (`overflow-x:auto`, `min-width:720px`). Beim Übernehmen bitte genau so lassen — das ersetzt den bisherigen `@media (max-width:900px)`-Block für die Sidebar.

---

## State Management

Im Prototyp:

| State | Werte | Wirkung |
|---|---|---|
| `view` | `home` \| `users` \| `inh` \| `ki` \| `app` | Hauptansicht, aktiver Nav-Button |
| `sub` | `users`, `chats`, `chars`, `import`, `tags`, `ai`, `media`, `betas`, `settings`, `system` | Unterseite, aktive Unter-Pille, Brotkrume |
| `sel` | `null` \| `{kind:'m'\|'p', i}` | KI: Übersicht vs. Modell-/Anbieter-Detail |

Im Zielsystem entspricht `view`/`sub` dem bestehenden `?tab=`-Parameter (`users`, `chats`, `chars`, `import`, `tags`, `ai`, `media`, `betas`, `settings`, `system`, plus unverlinkt `sc`); `sel` wird ein zusätzlicher Query-Parameter. Kein Client-State nötig außer dem Zahlen-Hochzählen.

**Daten je Ansicht** (alles bereits im Bestandscode vorhanden): Nutzerliste + Guthaben + offene Resets · Chatliste + Modellnutzung + Tokens/Kosten · Charaktere/Importe/Tags · Anbieter + Modelle + Generatoren + Betas · Settings + APK.
**Neu zu berechnen:** Top-3-Test-Modelle mit Nutzungszahl, Anzahl Bewertungen und Durchschnittsbewertung (BETA-Kachel und Betas-Seite).

---

## Assets

Keine neuen Bilddateien. Verwendet werden nur Unicode-Zeichen und Emoji: 📖 👥 📚 🤖 ⚙️ 👤 💬 🎭 📥 🏷️ 🎨 🧪 🛠️ 📦 🔑 💰 💳 🚫 🖼️ 🎬 🔊 🔍 🚪 🔒 🛡️ ⭐ ★ ▲ ▼ ← ‹ › ◱ ＋ ✕ ⬆️.
Charakterbilder und das Tutorial-Logo kommen weiterhin aus `pb/` über die bestehende Auslieferung (`?media=`, `?media_setting=`). Favicon/Logo unverändert aus `assets/images/logo.png`.

---

## Files

```
design/
  BT Admin.dc.html                             ← finaler Entwurf, alle 5 Seiten, klickbar
  BT Admin Übersicht (3 Layout-Varianten).dc.html
  support.js                                   ← nur für die Prototypen
source/
  beyond-telling.php                           ← Bestandscode, Funktionsreferenz
```

Im Zielsystem betroffen: `admin/beyond-telling.php` (Markup + eingebetteter `<style>`-Block) und optional `assets/css/style.css`.
