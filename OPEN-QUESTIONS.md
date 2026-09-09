# Vor dem Bauen zu klären

Claude Code soll diese Punkte mit mir durchgehen, **bevor** Code entsteht. Reihenfolge = Priorität.

## Technik
1. **Plattform:** native Kotlin + Compose, Flutter oder PWA/Capacitor?
2. **Backend:** Node/TypeScript oder PHP (passend zu `/var/www/html/`)? Datenbank Postgres oder MySQL?
3. **Auth-Umsetzung:** eigenes Backend, Firebase Auth oder Supabase?
4. **Deployment:** wie kommt das Android-Paket zu den Nutzern — Play Store, APK-Direktverteilung, PWA?
5. **Barcode-Scanner** in Phase 1 oder später?

## Rechtlich und Datenquellen
6. **Chefkoch:** kein offenes API. Welcher legale Weg — Partnerschaft, offizieller Feed, ganz weglassen?
7. **Reel-Import:** wie weit gehen wir? Nur nutzergeteilte Links und öffentliche Metadaten, oder mehr?
   Wie wird die Quelle sichtbar genannt?
8. **Zahlungen:** PayPal Freunde & Familie ist für gewerbliche Zahlungen nicht zulässig.
   Reguläre Zahlungsart (Stripe, PayPal Commerce) oder bleibt es ein privates Freundeskreis-Projekt?
9. **Datenschutz:** Essensfotos gehen an OpenRouter. Auftragsverarbeitungsvertrag, Aufbewahrungsdauer,
   Löschkonzept, DSGVO-Texte, Einwilligung vor der ersten Foto-Analyse?
10. **Nutzerinhalte:** Moderation von Kommentaren und Fotos — Meldefunktion, Löschung, wer prüft?

## Produkt
11. **XP-Missbrauchsschutz:** wie streng? Tagesdeckel pro Quelle, Rate-Limits, manuelle Prüfung?
12. **Mehrfaches Nachkochen:** darf ein Nutzer dasselbe Rezept mehrfach bewerten, oder nur einmal
    bewerten und mehrfach zählen (Abzeichen „Wiederholungstäter")?
13. **Erstbefüllung:** woher kommen die ersten Rezepte und Bilder? Ohne Inhalte ist der Feed leer.
14. **Bildmaterial:** eigene Fotos, Lizenzbilder oder ausschließlich Nutzerfotos und KI-Bilder?
15. **Gewichtsverlauf:** soll das Gewicht historisiert werden (Verlaufsdiagramm) oder nur der aktuelle Wert?

## Von mir bereits entschieden — nicht neu aufrollen
- Nährwert-Datenbank: Open Food Facts, USDA nur als Fallback. Yazio ist ausgeschlossen.
- Bewertung nur mit Nachkoch-Nachweis plus Pflicht-Foto.
- „Heute Lust auf" ohne Vorgaben, nur Freitext.
- Kosten exakt und ohne Aufschlag, 4 Dezimalstellen.
- Bei 0 $ Guthaben: KI gesperrt, alles andere läuft weiter.
- Tutorial als Coach-Marks pro Tab.
- Standard-Theme folgt dem System.
- Rangliste global All-Time, Level als Kochlöffel-Stufen.
- Kurzname ETL, Claim „Eat. Track. LevelUp.".
