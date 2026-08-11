# Handoff: LikeDennis Streamer-Website

## Overview
Extrem moderne One-Page-Website für den Twitch-Streamer **likedennis** (exoape.com-inspiriert): Video-Hintergrund, 4 Tabs (LIVE / PLAN / EVENTS / INFO), Live-Erkennung mit eingebettetem Twitch-Player, Countdown zum nächsten Stream, Channel-Empfehlungen mit Inline-Player, Login-View und Impressum. Alles ohne Scrollen, immer viewport-füllend und mittig, mit durchgängigen Fade/Slide-Animationen und einem Game-Fadenkreuz-Cursor.

## About the Design Files
Die Dateien in diesem Bundle sind **Design-Referenzen in HTML** (Prototyp mit Beispieldaten) — kein Produktionscode. Aufgabe: diese Designs **1:1 im Ziel-Stack nachbauen** (bestehendes Server-/Admin-System des Betreibers; Frontend-Framework frei wählbar, z.B. React/Next.js oder Vue) und an die real existierenden Systeme anbinden (Admin-CMS, Streamplan-Ersteller, Auth, Channel-Empfehlungs-System). Die HTML-Datei `LikeDennis Website.dc.html` ist die maßgebliche visuelle Referenz; `TargetCursor.jsx` ist der Custom-Cursor (React-Bits-Komponente, angepasst).

## Fidelity
**High-fidelity.** Farben, Typografie, Abstände, Animationstimings und Copy sind final gemeint und sollen pixelgenau übernommen werden.

## Grundlayout (alle Views)
- Vollbild, `overflow:hidden` — **niemals Scrollen**, auch nicht innerhalb von Boxen. Alles skaliert responsiv über `clamp()`/`vh`/`vw`.
- Hintergrund (hinterste Ebene, mit Maus-Parallax ±22px/±14px, transition 1.2s):
  1. Animierter Radial-Gradient (`#1c1430` / `#101a26` auf `#0a0a0c`, 26s drift loop) — immer da (Fallback).
  2. Video-Layer darüber (opacity .45, object-fit cover): akzeptiert `bg-video.mp4` **oder** `bg-video.webm` (erste existierende Datei; aktuell Pixabay-Testvideo). **Nahtloser Loop:** kurz vor Videoende (`duration - currentTime < .25s`) manuell auf `currentTime = .05` springen statt natives `loop` abzuwarten (native Loops erzeugen ~1s Lücke); `muted`/`defaultMuted` per JS-Property setzen + `play().catch()` (Autoplay-Policy), bei `stalled`/`ended` neu starten.
  3. Vignette: `radial-gradient(120% 90% at 50% 40%, transparent 40%, rgba(6,6,8,.82) 100%)`.
- Vertikale Struktur: Spacer `min(200px,20vh)` → Tab-Leiste (mittig) → Content (füllt Rest, Inhalt mittig zwischen Tabs und Footer) → Footer (mittig unten).
- **Nichts steht über der Tab-Leiste.** Oben rechts: LOGIN. Unten rechts: IMPRESSUM. Footer-Mitte: TWITCH · YOUTUBE · TIKTOK · DISCORD.
- **Keine Buttons mit Hintergrund** — nur Text-Links (Ausnahme: Outline-Buttons im Login für Twitch/Google).

## URL-Schema (verlinkbar, via history.replaceState)
- `?tab=live|plan|events|info` — aktiver Tab
- `?tab=plan&day=<0-6>` — Tages-Detailansicht (0=Sonntag … 6=Samstag)
- `?tab=events&article=<slug>` — Event-Artikel
- `?login` — Login-View, `?impressum` — Impressum-View
- Beim Laden aus der URL wiederherstellen.

## Design Tokens
- **Akzentfarbe: `#FF5C5C`** (global als CSS-Variable `--ld-accent`; färbt Countdown-Doppelpunkte, HEUTE-Tag, Event-Plattform-Labels, Info-Titel, Player-Glow, Hover-Linien, Hinweise). Muss zentral austauschbar bleiben.
- Hintergrund `#0a0a0c` · Text primär `#f2f2f0` · sekundär `#d6d6dc` / `#b9b9c0` · gedimmt `#8b8b93` · inaktiv/aus `#66666e` / `#55555c` · Trennlinien `rgba(255,255,255,.08)` · Live-Rot `#ff3b3b` · Twitch-Lila `#a970ff` (nur „MIT TWITCH"-Button)
- Fonts (Google Fonts): **Space Grotesk** (300–700, UI/Display) + **JetBrains Mono** (300/500/700, Zahlen/Uhrzeiten/Meta, `font-variant-numeric: tabular-nums`)
- Labels: 9–12px, UPPERCASE, letter-spacing .2–.42em. Wichtig: trailing letter-spacing kompensieren (`margin-right:-<spacing>`), damit Text optisch mittig sitzt.
- Radius: 2px (Outline-Buttons), 3–6px (Bilder/Player). Standard-Easing: `cubic-bezier(.22,1,.36,1)`.

## Animations-System
- Erschein-Animation `ldUp`: `translateY(26px)+opacity:0 → 0/1`, .6–.9s, gestaffelte Delays (~.08–.12s pro Element). Bei jedem Tab-/Viewwechsel neu abspielen.
- `ldPulse` (opacity 1↔.25, 1.2–2s): Countdown-Doppelpunkte, LIVE-Badges. `ldDot`: pulsierender roter Punkt am LIVE-Tab.
- **Auf-/Zuklapp-Animationen niemals über geschätzte max-height**, sondern `display:grid; grid-template-rows: 0fr↔1fr` + `transition` (animiert exakt auf Inhaltshöhe, kein Springen); inneres Element `overflow:hidden; min-height:0`.
- Fokus-Stile von Inputs über echtes CSS `:focus` lösen (nicht über JS-State — sonst remountet das Feld und Animationen starten neu).

## Custom Cursor (Desktop, `TargetCursor.jsx`)
- Systemcursor überall ausgeblendet (`@media (pointer:fine){*{cursor:none!important}}`); auf Mobile/Touch deaktiviert.
- Kleines Game-Fadenkreuz in **`#FF4A26`**: 2px-Mittelpunkt + 4 L-Ecken (7px, 1.5px border) eng um die Mitte, rotiert konstant (2s/Umdrehung). Folgt der Maus **ohne** Verzögerung (gsap.set, kein Tween).
- Beim Hover über `.cursor-target` (alle klickbaren Elemente): Ecken expandieren und rahmen das Ziel (borderWidth 2, cornerSize 7); Mittelpunkt fadet aus; Ziel-Rect **pro Frame neu messen** (Hover-Effekte ändern die Größe); wird das Ziel aus dem DOM entfernt → sofort in Ruhezustand zurück. Beim Verlassen: Ecken zurück, Punkt wieder ein, Rotation resumed.
- Beim Erfassen verschwinden Linien unter dem Ziel (Hover-Unterstreichungen der Links faden aus; aktiver Tab-Unterstrich → transparent).

## Tab-Leiste
- 4 Text-Tabs: LIVE · PLAN · EVENTS · INFO. 14px, **bold (700)**, letter-spacing .28em, Gap `clamp(22px,5vw,46px)`.
- Aktiv: weiß + 1px Unterstrich (border-bottom); inaktiv `#77777f`, Hover weiß. Padding symmetrisch `6px 2px` + `border-top:1px solid transparent` (Cursor-Rahmen sitzt mittig).
- LIVE-Tab bekommt bei Live-Status einen rot pulsierenden 7px-Punkt.

## Screens / Views

### 1) LIVE — live
- Player-Bereich spannt volle Höhe: Twitch-Embed (`player.twitch.tv/?channel=likedennis&parent=<domain(s)>&muted=true&autoplay=true`) oben nahe der Tab-Leiste, echtes **16:9** (Höhe = verfügbarer Platz, Breite folgt, max 88vw), `border-radius:6px`, **Glow**: `0 0 40px accent@24% , 0 0 120px accent@14%, 0 24px 80px rgba(0,0,0,.55)`.
- Darunter, nahe am Footer: „AUF TWITCH ÖFFNEN" (11px, .26em, weiße Grundlinie; Hover: Grundlinie aus, Akzentlinie wächst von links, letter-spacing → .32em).
- **Wichtig:** `parent`-Parameter muss alle einbettenden Domains enthalten.

### 2) LIVE — offline
Zentriert untereinander:
- Statuszeile (10px, .42em, `#8b8b93`): „GERADE OFFLINE — NÄCHSTER STREAM IN" / „AKTUELL KEIN STREAM GEPLANT" / „VERBINDE MIT TWITCH …".
- **Countdown** (JetBrains Mono bold, `clamp(44px,11vw,116px)`): `TT:HH:MM:SS`, Doppelpunkte in Akzent, pulsierend; darunter Einheiten-Zeile TAGE/STD/MIN/SEK (9px, .3em, `#66666e`); darunter nächster Termin „Mittwoch · 20:00 Uhr · GTA RP". Ziel = nächster Eintrag aus dem Streamplan. **Ist nichts geplant:** statt Countdown großes „OFFLINE" in gleicher Größe.
- **„WÄHRENDDESSEN"** (10px, .42em, `#66666e`, margin-top `clamp(30px,6vh,58px)`), darunter **Channel-Empfehlungen** (bis zu 4, immer horizontal zentriert, auch bei weniger): rundes Profilbild `clamp(44px,7.5vh,64px)` (Twitch-Avatar), Ring 2px — live: `#ff3b3b` + Glow `0 0 16px rgba(255,59,59,.55)`, offline: `rgba(255,255,255,.14)`; **darüber** 8px-Status: „LIVE" rot blinkend bzw. „OFFLINE" statisch grau `#55555c`; darunter Name (9px, .22em; live weiß, offline `#66666e`). Hover: scale 1.1. **Kein Hover-Player.**
- **Klick auf lives Profil** → Sequenz: Countdown-Inhalt fadet aus (.45s) → Countdown-Block klappt zu (grid-rows, .6s) → Player-Block klappt an gleicher Stelle auf (Profile gleiten runter): „✕ PLAYER SCHLIESSEN" (10px) + 16:9-Player des Kanals (Höhe `min(clamp(192px,41vh,408px), max(130px, calc(100vh - min(200px,20vh) - 260px)))` — passt IMMER zwischen Tabs und Footer). Abstand Player→„WÄHRENDDESSEN" im Player-Modus klein (`clamp(8px,1.6vh,16px)`). Schließen = exakt umgekehrte Sequenz, danach Countdown-Fade-in mit .35s Verzögerung. Klick auf anderes lives Profil wechselt den Player direkt. Offline-Profile verlinken normal zu twitch.tv/<channel>.

### 3) PLAN
- Liste Montag–Sonntag, volle Höhe zwischen Tabs und Footer, Zeilen mit `flex:1` gleichmäßig verteilt, Trennlinie `rgba(255,255,255,.08)`, gestaffeltes ldUp (~.08s/Zeile).
- Zeile: links Kategorie-Boxart (`clamp(25px,5.2vh,52px)` breit, 3:4, radius 3px; Quelle `https://static-cdn.jtvnw.net/ttv-boxart/<id>-144x192.jpg`; freie Tage: leere Box opacity .35) + Tagname (`clamp(15px,2.6vh,22px)`); rechts Mono „19:00 · Just Chatting" bzw. „— frei" (`#55555c`). Heutiger Tag: weiß + bold + „HEUTE" in Akzent (10px, .3em).
- **Klick auf Streamtag** (freie Tage nicht klickbar): andere Zeilen faden aus (.35s), dann Detailansicht (ldUp-Stagger): „← ZURÜCK ZUM PLAN" → großes Boxart (`clamp(120px,28vh,260px)` hoch, 3:4, radius 6, Schatten) → Mono-Metazeile „MITTWOCH · 20:00 UHR" in Akzent → Kategorie groß (`clamp(26px,5vh,44px)`, bold) → Beschreibung (`#b9b9c0`, light, max 520px).

### 4) EVENTS
- Selber Stil wie Plan: Zeilen mit Datum/Uhrzeit-Block links (Mono, `clamp(86px,12vw,120px)` breit, `#8b8b93`), Titel (`clamp(16px,2.1vw,20px)`, 600) + Beschreibung (`#b9b9c0`, light), rechts Plattform-Label in Akzent (9px, .3em).
- **Titel ist klickbar** (Hover: Akzentfarbe) → Artikelansicht: „← ZURÜCK ZU EVENTS", Titel (`clamp(24px,3.4vw,38px)`), Mono-Metazeile in Akzent („Fr, 21. Aug · 20:00 Uhr · TWITCH"), Absätze gestaffelt.

### 5) INFO
- Zentrierte Textblöcke (max 600px): Titel-Label in Akzent (10px, .34em) + Fließtext (`#d6d6dc`, light, line-height 1.65), gestaffeltes Fade-in. Inhalte kommen als freie Textblöcke aus dem Admin-System.

### 6) LOGIN (`?login`, oben rechts „LOGIN")
- Öffnen-Sequenz: Tab-Leiste slidet **nach oben raus** (translateY(-70px) + fade, .5s) und alles darunter fadet aus → Login erscheint mittig mit ldUp-Stagger.
- Inhalt: „← ZURÜCK" → Titel „ANMELDEN" (`clamp(36px,6.5vh,58px)`, 700) → Formular (`min(384px,86vw)`): Felder BENUTZERNAME / PASSWORT als transparente Inputs nur mit Unterstrich (`rgba(255,255,255,.22)`, 16px, zentriert; `:focus` → Unterstrich in Akzent; Placeholder `#55555c`), Submit „ANMELDEN" (16px, 700, Unterstrich-Hover) → Trenner „— ODER —" → zwei **Outline-Buttons** (radius 2px, 13px, .26em): „MIT TWITCH" Outline `#a970ff` (Hover: voll + Glow), „MIT GOOGLE" Outline weiß → Hinweis in Akzent (12px, .2em): **„LOGIN NUR MIT BESTEHENDEM ACCOUNT"** (keine Registrierung — muss sichtbar bleiben).
- Anbindung: Form-POST an bestehendes `/login`; OAuth-Links `/auth/twitch`, `/auth/google` (Systeme existieren serverseitig). „← ZURÜCK" kehrt mit umgekehrter Animation zurück.

### 7) IMPRESSUM (`?impressum`, unten rechts „IMPRESSUM", `#55555c` @ 75% opacity, Hover weiß)
- Gleiche Öffnen-/Schließen-Sequenz wie Login. Inhalt zentriert (max 520px): Titel „IMPRESSUM" + Abschnitte mit Akzent-Labels: ANGABEN GEMÄSS § 5 TMG (Beispiel: Max Mustermann · LikeDennis, Musterstraße 12, 12345 Musterstadt), KONTAKT (business@likedennis.de), VERANTWORTLICH FÜR DEN INHALT, HAFTUNGSHINWEIS. Beispieltext durch echte Daten ersetzen.

## Interactions & Behavior (Zusammenfassung)
- Tabwechsel: alter Inhalt weg, neuer mit ldUp-Stagger; URL aktualisieren; offene Unteransichten (Tag, Artikel, Player, Login, Impressum) schließen.
- Live-Check: alle 60s (Prototyp: `https://decapi.me/twitch/uptime/<channel>`; Produktion besser Twitch Helix API serverseitig). Countdown tickt sekündlich.
- Hover-Linien: Grundlinie `rgba(242,242,240,.3)` 100%, Akzentlinie wächst 0→100% von links (.6s); beim Cursor-Erfassen beide auf 0.
- Footer-Links & LOGIN/IMPRESSUM: 9–11px, .3em, `#66666e`→weiß.

## State Management
`tab`, `live (null|bool)`, `now` (1s-Tick), `showVideo`, `day`/`dayLeaving` (Plan-Detail), `article` (Events), `promos[] {name, channel, avatarUrl, isLive}`, `watchPromo`/`watchLeaving`/`watchClosing`/`watchOpen`/`cdOpen` (Promo-Player-Sequenzen), `loginMode`/`impressumMode`/`loginLeaving`.

## Server-Integration (existiert bereits — anbinden statt neu bauen)
1. **Streamplan**: kommt automatisch aus dem vorhandenen Streamplan-Ersteller. Datenmodell pro Tag: `{day, dow (0=So…6=Sa), time?, title?, cat? (Twitch-Kategorie-ID), desc?}` — ohne `time` = frei. Speist Plan-Tab UND Countdown.
- **Events**: `{slug, date, time, title, desc, place, article: string[]}` — Admin-gepflegt.
- **Infos**: `{title, text}` — Admin-gepflegt.
- **Channel-Empfehlungen**: `{name, channel}` (max 4) — existierendes System; Avatare + Live-Status von Twitch laden.
- **Seiten-System** (wie Homepage-Baukasten): Admin kann Unterseiten/Artikel anlegen (`slug`, Titel, Meta, Textblöcke) → generisch über die Artikelansicht rendern; Events referenzieren Seiten per Slug.
- **Auth**: `/login` (POST), `/auth/twitch`, `/auth/google`. Keine Registrierung.
6. **Video**: Admin lädt `bg-video.mp4`/`.webm` hoch.

## Assets
- Google Fonts: Space Grotesk, JetBrains Mono
- gsap (CDN) — nur für den Custom Cursor
- Twitch: Boxart `static-cdn.jtvnw.net/ttv-boxart/<id>-<w>x<h>.jpg`, Avatare + Live-Status via Twitch API, Player-Embed `player.twitch.tv`
- Hintergrundvideo: Platzhalter von Pixabay (ersetzen!)

## Files
- `LikeDennis Website.dc.html` — kompletter Prototyp (Markup, Styles inline, Logik als `Component`-Klasse am Dateiende)
- `TargetCursor.jsx` — Custom-Cursor-Komponente (React, gsap)
