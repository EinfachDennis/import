# Handoff: BEYOND TELLING — Redesign „Chats" & „Profil" (+ Neuer-Chat-Menü)

## Überblick
Redesign von zwei Tabs der bestehenden Mobile-Web-App (Android-WebView-Hülle, `index.php` + `assets/app.css` + `assets/app.js`):

1. **Chats-Tab** — kompakte Liste mit randlosem Charakterbild-Streifen, Filter-Chips, Sortierung, Pin/Stumm/Löschen per Swipe (mit Nachfrage), Ungelesen-Zähler, „Du bist am Zug"-Marker, Nachrichtenzahl je Chat, animierter Übergang Liste → Chat.
2. **Profil-Tab** — Kopf mit Avatar/Guthaben und fünf Registern: **Übersicht · Kosten · Freunde · Medien · Werke**. Der bisherige „Freundes-Hub"-Button entfällt; der Hub wird zum Register „Freunde".
3. **Neuer-Chat-Menü („＋")** — radialer Fächer, der aus dem Header-＋ nach unten aufklappt, mit Ein- **und** Ausblende-Animation und einem Eintrag „Modi Erklärung" (blau umkreistes Fragezeichen), der unter jedem Modus einen kurzen Erklärtext ein-/ausblendet.

Verbindlich ist **Variante 2a** im Prototyp (die zusammengebaute App). Die Varianten 1a–1f, 2b–2d sind Vergleichsstände und **nicht** umzusetzen.

## Zu den Design-Dateien
Die Dateien in `prototype/` sind **Design-Referenzen in HTML** — ein lauffähiger Prototyp, der Aussehen und Verhalten zeigt, **kein** Produktionscode zum Kopieren. Aufgabe ist es, diese Designs in der bestehenden Umgebung der App nachzubauen: **Vanilla JS + CSS**, gerendert aus `assets/app.js` in die Container von `index.php`, mit den vorhandenen Mustern (Klassen wie `.chat-item`, `.glass`, `.set-card`, CSS-Variablen aus `:root`, `esc()`, `api()`, `toast()`, `uiConfirm()`). Kein React, kein Build-Schritt, keine neuen Abhängigkeiten.

Der Prototyp läuft mit einer Streaming-Runtime (`support.js`) und nutzt Inline-Styles — beides ist ein Artefakt der Design-Umgebung. In der App gehören die Styles in `assets/app.css` als Klassen, passend zum vorhandenen Stil.

## Fidelity
**High-fidelity.** Farben, Radien, Abstände, Schriftgrößen und Animationen sind final und unten exakt dokumentiert. Pixelgenau nachbauen; Abweichungen nur, wo bestehende App-Muster (z. B. `.glass`) dasselbe schon liefern.

---

## Design-Tokens

Bestehende Variablen aus `:root` in `assets/app.css` weiterverwenden. Im Prototyp verwendete Werte:

| Zweck | Wert | Vorhandene Variable |
|---|---|---|
| Hintergrund | `#0d0912` | `--background-color` |
| Fläche/Karte | `rgba(24,17,28,.90–.96)` | `--surface-color` / `.glass-intense` |
| Kartenrahmen | `rgba(157,107,240,.22–.28)` | `--glass-border` |
| Rahmen angeheftet | `rgba(202,164,106,.45)` | `--gold` |
| Text | `#f5eee6` | `--text-primary` |
| Text sekundär | `#cdbede` | `--text-secondary` |
| Text gedämpft | `#8f7fa3` | `--text-muted` |
| Gold | `#caa46a`, hell `#e6cf96`, sehr hell `#f2dfae` | `--gold`, `--gold-soft` |
| Violett-Verlauf | `linear-gradient(135deg,#b48af0,#6425b0)` | `--grad-purple` |
| Gold-Verlauf (CTA) | `linear-gradient(135deg,#caa46a,#f2dfae)`, Text `#241704` | `--grad-premium` |
| Gefahr | `#ef4d63`, Fläche `rgba(239,77,99,.08)` | `--danger` |
| Hilfe-Blau (neu) | Rahmen/Text `#5a96ff`, Label `#8fb6ff`, Fläche `rgba(20,30,52,.95)` | *neu anlegen:* `--info: #5a96ff` |
| Online-Punkt | `#8fd6a0` | *neu:* `--ok` |

**Typografie**
- Überschriften/Namen: `Cinzel` 600–700, `letter-spacing:.03–.06em`
- Fließtext/UI: `Inter` 400/500/600/700
- Zahlen, Tokens, Kosten, technische Labels: `JetBrains Mono` 500 (ersatzweise `ui-monospace`) — **neu**, Google-Fonts-Link in `index.php` ergänzen
- Größen: Seitentitel 20 px · Chatname 14 px · Vorschau 11,8 px/1.45 · Meta 10 px · Mono-Badges 9,5 px · Tab-Labels 10 px · Kachel-Zahlen 19 px Cinzel

**Radien:** Zeile 18 px · Karte/Sektion 19–22 px · Bild-Thumb 11–15 px · Chip 9–11 px · Pille 999 px · Popup 24 px · Sheet 28 px oben
**Abstände:** Seitenrand 14–16 px · Listenabstand 7 px · Sektionsabstand 11 px · Innenabstand Karte 13–16 px
**Schatten:** Karten `0 8px 32px rgba(0,0,0,.5)` (vorhanden) · FAB `0 8px 20px -8px rgba(124,77,196,.9)` · Popup `0 24px 60px -18px rgba(0,0,0,.9)`
**Easing:** Standard `cubic-bezier(.22,.9,.3,1)` (= `--ease`) · Feder `cubic-bezier(.34,1.56,.64,1)` (= `--ease-back`) · Tab-Indikator `cubic-bezier(.34,1.4,.5,1)`

**Keyframes (neu in `app.css`)**
```css
@keyframes cardIn   {from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:none}}
@keyframes popIn    {from{opacity:0;transform:scale(.86)}to{opacity:1;transform:scale(1)}}
@keyframes popOut   {from{opacity:1;transform:scale(1)}to{opacity:0;transform:scale(.86)}}
@keyframes fadeIn   {from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
@keyframes fadeOut  {from{opacity:1}to{opacity:0}}
@keyframes overlayIn{from{opacity:0;transform:translateX(60px) scale(.97)}to{opacity:1;transform:none}}
@keyframes sheetUp  {from{opacity:0;transform:translateY(100%)}to{opacity:1;transform:none}}
@keyframes slideRight{from{opacity:0;transform:translateX(28px)}to{opacity:1;transform:none}}
@keyframes barGrow  {from{transform:scaleY(0)}to{transform:scaleY(1)}}
```
`glowPulse`, `attnPulse`, `emberRise` existieren bereits — weiterverwenden.

---

## Screen 1 — Chats (`#tab-chats`)

**Zweck:** Alle laufenden Chats finden, sortieren, verwalten und öffnen.

### Layout
- Sticky Kopf (`position:sticky;top:0;z-index:4`), Hintergrund `rgba(13,9,18,.97)`, unten 1 px `rgba(202,164,106,.14)`, Padding `14px 16px 10px`:
  1. Zeile: Titel „CHATS" (Cinzel 700, 20 px, `letter-spacing:.05em`) · flexibler Abstand · `<select>` Sortierung (Zuletzt / Kosten / Tokens; 11 px, Radius 11 px, Rahmen `rgba(157,107,240,.28)`) · ＋-Button 34×34, Radius 12 px, `--grad-purple`, Schatten `0 8px 20px -8px rgba(124,77,196,.9)`, `:active` `scale(.9)`
  2. Suchfeld: volle Breite, Padding `9px 12px`, Radius 13 px, `rgba(255,255,255,.05)`, Rahmen `rgba(157,107,240,.22)`, Lupe + Placeholder „In Chats suchen …"
  3. Filter-Chips, horizontal scrollbar, Abstand 6 px: **Alle · Charakter · Universum · AI vs. AI · Geteilt**. Aktiv: `--grad-purple`, Text `#fff`, Rahmen `rgba(202,164,106,.5)`. Inaktiv: `rgba(255,255,255,.05)`, Text `#cdbede`, Rahmen `rgba(157,107,240,.24)`. Übergang `all .25s ease`.
- Liste: Padding `10px 12px 16px`, `display:flex;flex-direction:column;gap:7px`
- Über der Liste zentriert der Hinweis `≪ SWIPE LINKS LÖSCHEN | SWIPE RECHTS ANHEFTEN ≫` (Mono 9,5 px, `letter-spacing:.14em`, `#8f7fa3`)

### Chat-Zeile (ersetzt `.chat-item`)
Zwei verschachtelte Ebenen:
- **Hülle** `position:relative;border-radius:18px;overflow:hidden`, Einflug `cardIn .5s var(--ease) both`, `animation-delay: index*0.06s`
- **Aktionsschicht** darunter (absolut, `inset:0`): `linear-gradient(90deg,rgba(202,164,106,.22),rgba(239,77,99,.22))`, links 📌, rechts 🗑, Padding `0 18px`
- **Zeile** darüber: `display:flex;align-items:stretch;gap:11px;padding:0 13px 0 0;background:rgba(24,17,28,.96);border:1px solid <Rahmen>;border-radius:18px;overflow:hidden;touch-action:pan-y`
  - **Bildstreifen** links, `width:66px`, volle Zeilenhöhe, randlos (kein Radius, kein Abstand): Charakterbild als `background-size:cover`. Ohne Bild: Verlauf `linear-gradient(155deg,hsl(H 52% 34%),hsl(H-38 58% 13%))` (H aus der Charakter-ID abgeleitet) + Streifen-Overlay `repeating-linear-gradient(115deg,rgba(255,255,255,.05) 0 8px,transparent 8px 18px)` + Mono-Label „BILD" unten links (7 px).
    Ungelesen-Badge oben links auf dem Bild: min 19×19, Radius 10, `#ef4d63`, `#fff`, 10,5 px/700, `popIn .4s var(--ease-back)`.
  - **Textspalte** `flex:1;min-width:0;padding:11px 0`
    - Namenszeile: optional 📌 (10 px) · Name (Inter 600, 14 px, `text-overflow:ellipsis`) · optional 🔇 (10 px, `opacity:.6`)
    - Vorschau: 11,8 px/1.45, `#8f7fa3`, `-webkit-line-clamp:2`
    - optional Pille „DU BIST AM ZUG": 9 px/700, `letter-spacing:.07em`, Padding `2px 7px`, `rgba(202,164,106,.18)`, Rahmen `rgba(202,164,106,.5)`, Text `#e6cf96`
  - **Metaspalte** rechts, `flex-direction:column;align-items:flex-end;justify-content:center;gap:5px;padding:11px 0`: Uhrzeit (10 px, `#8f7fa3`) · Token-Chip (Mono 9,5 px, `rgba(157,107,240,.16)`, `#b48af0`) · Kosten (Mono 9,5 px, `#caa46a`) · **Anzahl Nachrichten** (Mono 9 px, `#8f7fa3`, Format `1.284 Nachr.`, `toLocaleString('de-DE')`)
- Rahmenfarbe: angeheftet `rgba(202,164,106,.45)`, sonst `rgba(157,107,240,.26)`. Bestehende Zustände `.attention` / `.attention-left` (Gold-/Rot-Puls) bleiben unverändert erhalten.

### Sortierung & Reihenfolge
`Zuletzt` (Default, `last_message_at`), `Kosten` (`cost` absteigend), `Tokens` (`context_tokens` absteigend). **Angeheftete Chats stehen immer oben**, innerhalb der Gruppe gilt die gewählte Sortierung.

---

## Screen 2 — Profil (`#tab-profile`)

**Zweck:** Konto, Kosten, Freunde, Medien und eigene Werke an einem Ort.

### Kopf (nicht scrollend)
- Padding `16px 16px 0`; Zeile: Avatar 56×56, Radius 19 px, `--grad-purple`, `glowPulse 3.6s ease-in-out infinite` · Name (Cinzel 700, 18 px) + E-Mail (10,5 px, `#8f7fa3`) · rechts „GUTHABEN" (9 px, `letter-spacing:.12em`, `#caa46a`) über Betrag (Cinzel 700, 17 px, `#f2dfae`)
- **Registerleiste** darunter, `margin-top:14px`, Padding 4 px, Radius 15 px, `rgba(255,255,255,.05)`, Rahmen `rgba(157,107,240,.22)`:
  fünf gleich breite Buttons **Übersicht · Kosten · Freunde · Medien · Werke** (Inter 600, 10 px; aktiv `#fff`, sonst `#8f7fa3`, `transition:color .3s ease`).
  Gleitender Indikator: absolut, `top:4px;bottom:4px`, Radius 12 px, `--grad-purple`,
  `width:calc((100% - 8px)/5)`, `left:calc(4px + i*(100% - 8px)/5)`, `transition:left .42s cubic-bezier(.34,1.4,.5,1)`.
- Inhaltsbereich: `flex:1;overflow:auto;padding:14px 16px 20px`; jeder Registerwechsel spielt `slideRight .4s var(--ease) both`.

### Register „Übersicht"
1. **Kennzahlen-Grid** 2×2 (Karten: Radius 18, `rgba(24,17,28,.9)`, Rahmen `rgba(157,107,240,.22)`, Padding 13): Label (9,5 px, `#8f7fa3`, `letter-spacing:.06em`), Wert (Cinzel 700, 19 px; Gold `#e6cf96` bzw. Violett `#c9a7f5`), Zusatz (9,5 px).
   Inhalte: NACHRICHTEN · BILDER & VIDEOS · LIEBLINGSFIGUR · LÄNGSTE STORY.
2. **🔐 Anmeldeoptionen** (bestehender `#connect-list`): Zeilen mit Icon, Name, Status-Chip („Verknüpft" grün `rgba(124,196,140,.16)`/`#8fd6a0`, „Verbinden" violett `rgba(157,107,240,.18)`/`#c9a7f5`).
3. **🏅 Erfolge**: Pillen, Padding `6px 10px`, 10,5 px/600; Gold- oder Violett-Variante.
4. Buttonpaar **⚙️ Einstellungen** (öffnet `#profile-settings-page`) und **Abmelden** (rot umrandet).

### Register „Kosten"
1. Guthaben-Karte: Verlauf `linear-gradient(135deg,rgba(202,164,106,.16),rgba(124,77,196,.12))`, Rahmen `rgba(202,164,106,.32)`, Radius 20; links „GUTHABEN" + Betrag (Cinzel 700, 31 px, `#f2dfae`), rechts CTA **＋ Aufladen** (Gold-Verlauf, Text `#241704`, Radius 14).
2. Verlaufskarte „Verlauf · 14 Tage" mit Ø/Tag rechts; Balkenreihe Höhe 96 px, `gap:5px`, jeder Balken `flex:1`, Radius `4px 4px 2px 2px`, `transform-origin:bottom`, `barGrow .7s var(--ease) both`, `animation-delay: i*0.05s`; Spitzentag in Gold (`linear-gradient(180deg,#f2dfae,#caa46a)`), sonst Violett (`linear-gradient(180deg,#b48af0,#4e1c8c)`).
   Darunter Zeilen HEUTE / MONAT / GESAMT sowie „🔑 Beim Anbieter ausgegeben" und „🔑 Guthaben beim Anbieter" (aus `#provider-balance-box`, nur bei vorhandenen Werten).
3. „Teuerste Chats": Zeile aus 28×28-Thumb, Name, Fortschrittsbalken 70×5 px (`linear-gradient(90deg,#caa46a,#b48af0)`), Betrag (Mono 10,5 px, `#caa46a`, rechtsbündig, Breite 46 px).

### Register „Freunde" (ersetzt den Freundes-Hub-Button)
Reihenfolge der Karten:
1. **Offene Anfragen** (Gold-getönte Karte, Zähler-Badge rot) mit ✓/✕ je Anfrage (32×32).
2. **Suchfeld** „Username suchen — Freund hinzufügen …".
3. **Meine Freunde**: 34×34-Avatar mit Online-Punkt (11 px, `#8fd6a0`, 2 px Rand in Kartenfarbe), Name, Rechte-Zusammenfassung, Chevron.
4. **Charaktere der Freunde**: horizontale Rail, Kacheln 88×112, Radius 15, Bild + Verlauf `linear-gradient(180deg,transparent 45%,rgba(13,9,18,.9))`, unten Name (11 px) und **Besitzer-Username** (8,5 px, `#caa46a`, ohne Icon-Präfix).
5. **Geteilte Chats**: Zeilen mit Thumb, Titel, Unterzeile `‹Besitzer› · 👀 Nur-Lese · ‹Zeit›`.
6. **Geteilte Medien**: 4-spaltiges Raster, quadratisch, Radius 11; **unten mittig auf jeder Kachel** der Username des Teilenden (Inter 600, 7 px, Verlauf `linear-gradient(180deg,transparent,rgba(13,9,18,.85))`, Padding `7px 2px 3px`, einzeilig mit Ellipsis).
7. **Von mir geteilt**: Zeilen mit „Ändern"-Button + Abschnitt „Standard-Rechte für alle Freunde" als Pillen `Label · AN/AUS` (AN gold, AUS grau).

Die Read-Only-Chatansicht (`#screen-hub-chat`) und die Freund-Detailansicht bleiben unverändert, werden nur aus diesem Register heraus geöffnet.

### Register „Medien"
Hinweiszeile „Tippen zum Vergrößern · gedrückt halten zum Teilen oder Herunterladen.", danach 2-spaltiges Raster, quadratisch, Radius 17, Einflug `popIn .45s var(--ease-back)` mit `i*0.045s` Versatz, Mono-Label unten links (BILD/VIDEO). Darunter „Mehr laden". Tippen öffnet die bestehende Lightbox.

### Register „Werke"
Drei Karten **🎭 Meine Charaktere · 🌌 Meine Universen · 👤 Meine Identitäten** mit Zeilen aus 34×34-Thumb, Name, Unterzeile (Sichtbarkeit/Genre bzw. Geschlecht/Alter), Chevron. Hinweis „Identitäten sind privat — nur du siehst sie." bleibt erhalten.

---

## Screen 3 — „Neuer Chat" (＋)

Ersetzt `#sheet-newchat`. Overlay über der Chatliste, Hintergrund `radial-gradient(circle at 88% 12%,rgba(124,77,196,.35),rgba(6,4,9,.82) 60%)`.

- **Anker:** exakt der Header-＋ (`top:14px;right:16px`). Der Header-Button bleibt sichtbar; es gibt **keinen** zweiten FAB im Overlay.
- **Fünf Einträge** als Bogen, jeweils `position:absolute;top:14px;right:16px` plus `transform`:

| # | Eintrag | transform |
|---|---|---|
| 0 | Modi Erklärung (blaues „?") | `translate(0,70px)` |
| 1 | 🎭 AI-Charakter Chat | `translate(-26px,138px)` |
| 2 | 🌌 Universum-Chat | `translate(-40px,208px)` |
| 3 | 💬 Konversations-Chat | `translate(-26px,278px)` |
| 4 | 🤖 AI vs. AI | `translate(2px,344px)` |

  **Wichtig:** Positions-`transform` und Einflug-Animation liegen auf **zwei verschachtelten Elementen** (außen `transform`, innen `animation`) — sonst überschreibt das Keyframe-`transform` die Position.
- **Eintrag-Aufbau:** rechts das Icon-Feld 52×52, Radius 18, charakterfarbener Verlauf, Schatten `0 10px 26px -8px rgba(0,0,0,.9)`; links davor (Abstand 10 px) die Beschriftungs-Pille: `max-width:238px`, Padding `7px 12px`, Radius 16, `rgba(20,14,26,.95)`, Rahmen `rgba(202,164,106,.3)`, Inter 600 11,5 px, rechtsbündig.
- **Hilfe-Eintrag:** Kreis 40×40 (`border-radius:50%`, `margin:6px` zum Ausrichten), Rahmen 2 px `#5a96ff`, Fläche `rgba(20,30,52,.95)`, „?" 19 px/700 `#5a96ff`, Glow `0 0 18px -4px rgba(90,150,255,.8)`; Pille mit Rahmen `rgba(90,150,255,.45)`, Text `#8fb6ff`, Beschriftung wechselt „Modi Erklärung" ⇄ „Modi Erklärung ausblenden".
- **Erklärtexte:** Klick auf den Hilfe-Eintrag blendet unter **jedem** Modustitel einen zweiten Absatz ein (Inter 400, 10 px/1.4, `#8f7fa3`, `margin-top:3px`) mit `fadeIn .3s ease both`; erneuter Klick blendet ihn wieder aus. Texte:
  - AI-Charakter Chat — „Du spielst gegenüber einer KI-Figur — sie bleibt in ihrer Rolle."
  - Universum-Chat — „Mehrere Figuren einer Welt in einer Szene; du wählst, wen du ansprichst."
  - Konversations-Chat — „Zwei echte Menschen, abwechselnd — jeder zahlt seine eigenen Antworten."
  - AI vs. AI — „Du schreibst nicht mit, sondern gibst Regie und holst die nächste Nachricht."
- **Öffnen:** Overlay `popIn .22s ease both`; Einträge `popIn .42s var(--ease-back) both` mit Versatz `(i+1)*0.06s` (Hilfe zuerst).
- **Schließen:** Tippen ins Overlay → Einträge `popOut .26s ease both` **rückwärts gestaffelt** (`(3-i)*0.05s`), Overlay `fadeOut .3s ease both`; das Menü wird erst nach **330 ms** aus dem DOM genommen. Beim Schließen wird der Hilfe-Zustand zurückgesetzt.

---

## Interaktionen & Verhalten

| Auslöser | Verhalten |
|---|---|
| Tippen auf Chat-Zeile | Öffnet den Chat. Overlay fährt mit `overlayIn .42s var(--ease)` von rechts ein; Kopf-Avatar `popIn .45s var(--ease-back)`; Nachrichten `cardIn .5s` mit 0,12 s Versatz. Kein Zwischenschritt/Aufklappen mehr. |
| Swipe nach **links** (> 90 px) | Zeile federt zurück (`transform .4s var(--ease-back)`), danach Popup **„Chat löschen?"** |
| Swipe nach **rechts** (> 90 px) | Zeile federt zurück, danach Popup **„Chat anheften?"** bzw. **„Anheftung lösen?"** |
| Popup „Abbrechen" | Nichts passiert. |
| Popup bestätigen | Löschen: bestehender `chat_delete`-Aufruf. Anheften: neuer Zustand pro Chat (siehe State). |
| ＋ im Header | Öffnet das Neuer-Chat-Menü (siehe oben). |
| Nav „Chats"/„Profil" | Bestehende Tab-Logik; die Bottom-Nav bleibt **unverändert** (gleiche fünf SVG-Icons und Labels). |
| Register im Profil | Wechselt Inhalt, Indikator gleitet, Inhalt `slideRight .4s`. |
| Medien-Kachel | Bestehende Lightbox; Zoom/Teilen wie gehabt. |

**Swipe-Implementierung (Pointer-Events, kein Framework):**
```js
row.addEventListener('pointerdown', e => {
  const x0 = e.clientX; let dx = 0;
  row.setPointerCapture?.(e.pointerId);
  const move = ev => { dx = ev.clientX - x0; row.style.transition='none';
                       row.style.transform = `translateX(${dx}px)`; };
  const up = () => {
    row.removeEventListener('pointermove', move);
    row.removeEventListener('pointerup', up); row.removeEventListener('pointercancel', up);
    row.style.transition = 'transform .4s cubic-bezier(.34,1.56,.64,1)';
    row.style.transform  = 'translateX(0)';
    if (dx < -90) askDelete(chat); else if (dx > 90) askPin(chat);
  };
  row.addEventListener('pointermove', move);
  row.addEventListener('pointerup', up); row.addEventListener('pointercancel', up);
});
```
`touch-action:pan-y` auf der Zeile ist Pflicht, damit vertikales Scrollen weiter funktioniert.

**Bestätigungs-Popup:** kann das vorhandene `uiConfirm()` / `#modal-confirm` nutzen. Optik im Prototyp: Overlay `rgba(6,4,9,.72)`, Karte Radius 24, `rgba(24,17,28,.98)`, Rahmen `rgba(202,164,106,.32)`, Titel Cinzel 700 17 px, Text 12,5 px/1.5 `#cdbede`, zwei Buttons (links „Abbrechen" outline, rechts Aktion — Löschen rot `linear-gradient(135deg,#ef4d63,#7a1424)`, Anheften Gold-Verlauf mit Text `#241704`), Einblendung `sheetUp .34s var(--ease)`.

**Reduzierte Bewegung:** Alle Animationen in `@media (prefers-reduced-motion: reduce)` auf `animation:none` setzen.

---

## State

Client-seitig (in `S` bzw. lokal je Tab):

| Zustand | Typ | Zweck |
|---|---|---|
| `chatFilter` | `'all'|'char'|'universe'|'aivsai'|'shared'` | Filter-Chips |
| `chatSort` | `'recent'|'cost'|'tokens'` | Sortierung |
| `chatSearch` | string | Suchfeld |
| `pinned[chatId]` | bool | Anheftung (siehe Server) |
| `muted[chatId]` | bool | Stumm |
| `confirm` | `{kind:'del'|'pin', chatId}` \| null | offenes Popup |
| `profileTab` | `'overview'|'costs'|'friends'|'media'|'works'` | aktives Register |
| `newChatOpen` / `newChatClosing` | bool | Menü offen / in Ausblendung (330 ms) |
| `modiHelpOpen` | bool | Erklärtexte im Menü |

**Server-seitig neu nötig** (API `assets/api.php` bzw. bestehende Endpunkte erweitern):
- `chats` liefert zusätzlich `message_count` (Gesamtzahl Nachrichten je Chat), `is_pinned`, `is_muted`.
- Neue Aktionen `chat_pin {chat_id, pinned}` und `chat_mute {chat_id, muted}` (Spalten `pinned TINYINT`, `muted TINYINT` in der Chat-/Teilnehmer-Tabelle).
- Profil-Kosten: Tagesreihe der letzten 14 Tage (`[{date, amount}]`) für die Verlaufsgrafik, plus `avg_per_day` und Top-3-Chats nach Kosten.
- Statistik: `messages_total`, `media_total`, `favorite_character {name, messages}`, `longest_story {name, tokens}`.
Bis diese Felder existieren, die Werte weglassen (nicht mit Platzhaltern füllen) — die Layouts vertragen fehlende Zeilen.

`profileTab`, `chatFilter` und `chatSort` in `localStorage` merken (Schlüssel-Präfix wie bisher in der App), damit die App an derselben Stelle wieder aufmacht.

---

## Assets
Keine neuen Grafiken. Charakter-/Avatarbilder kommen wie bisher aus der API; **fehlt** ein Bild, wird der beschriebene Farbverlauf + Streifenmuster + Mono-Label als Platzhalter gezeichnet (keine Emoji-Fallbacks mehr auf den großen Flächen). Die fünf Bottom-Nav-Icons bleiben die vorhandenen Inline-SVGs aus `index.php`. Emojis werden weiterhin nur als Kategoriesymbole (🎭 🌌 💬 🤖 🔐 🏅 🖼️ 🔑 📌 🔇 👀) genutzt.
Zusätzlich einzubinden: Google-Font **JetBrains Mono** (400/500) — nur für Zahlen/technische Labels.

## Dateien
- `prototype/Chats & Profil Redesign.dc.html` — der Prototyp. Maßgeblich ist der Abschnitt **`id="2a"`** (zusammengebaute App inkl. Neuer-Chat-Menü). Frames `2b/2c/2d` zeigen verworfene Menü-Varianten, `1a`–`1f` die früheren Entwürfe.
- `prototype/support.js` — Laufzeit des Prototyps (nur zum Öffnen im Browser nötig, **nicht** übernehmen).
- Zielorte in der App: `index.php` (Markup-Container `#tab-chats`, `#tab-profile`, Ersatz für `#sheet-newchat`), `assets/app.css` (neue Klassen + Keyframes), `assets/app.js` (`loadChats()`, `renderProfile()`, Hub-Funktionen ins Register „Freunde", neues Menü).

## Nicht ändern
Bottom-Nav, Startseite, Erstellen-Wizard, Identitäten-Tab, Chat-Innenansicht, Einstellungsseiten, Tutorial/Tour, Update- und Wartungs-Dialoge, alle bestehenden API-Verträge außer den oben genannten Ergänzungen.
