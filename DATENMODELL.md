# Datenmodell — Ausgangsvorschlag

Kein fertiges Schema, sondern die Grundlage für dein Rückfragen-Gespräch. Feldnamen und Typen
bitte mit mir abstimmen, bevor Migrationen entstehen.

## users
| Feld | Typ | Notiz |
|---|---|---|
| id | uuid | PK |
| email | text | unique |
| password_hash | text | null bei Google-Login |
| google_sub | text | null bei E-Mail-Login |
| username | text | unique, Pflicht, min. 3 Zeichen, `[a-z0-9._]` |
| username_changed_at | timestamp | Umbenennung nur einmal erlaubt |
| credit_usd | numeric(10,6) | Start 0.50 |
| xp | integer | Start 0 |
| is_admin | boolean | abgeleitet aus fest hinterlegter Admin-E-Mail |
| theme_pref | enum | system \| light \| dark |
| created_at | timestamp | |

## user_goals
Größe, Gewicht, Alter, Geschlecht, Aktivitätsfaktor, Schritte pro Tag, Zielrichtung,
berechnetes Tagesziel, manuell überschriebenes Ziel, Makroziele. Historie behalten, damit
Gewichtsverlauf möglich bleibt — bitte klären, ob gewünscht.

## recipes
id, author_id → users, title, servings, time_minutes, description, source_type
(eigenes \| tiktok \| instagram \| youtube \| screenshot \| ai), source_url, image_url,
image_is_ai, kcal_per_serving, protein_g, carbs_g, fat_g, is_published, created_at.

## recipe_ingredients
id, recipe_id, position, name, amount numeric, unit, is_estimated boolean
(für Mengen, die die KI geschätzt hat), food_ref (optional Verweis auf Open Food Facts / USDA).

## recipe_steps
id, recipe_id, position, text.

## recipe_tags
recipe_id, tag. Tags kleingeschrieben, für Filter und Suche.

## cooked_entries
id, user_id, recipe_id, rating smallint 1–5 **not null**, photo_url **not null**,
comment_id nullable, created_at. Unique auf (user_id, recipe_id) klären — mehrfaches Nachkochen
soll für das Abzeichen „Wiederholungstäter" zählen, also eher **nicht** unique.

**Regel:** Ein Rating existiert nur als Teil eines cooked_entry. Es gibt keine Bewertung ohne
Nachkoch-Nachweis mit Foto.

## comments
id, recipe_id, user_id, parent_id (eine Antwortebene), text, photo_url nullable, created_at.
Moderation: is_hidden, reported_count — Umfang bitte klären.

## diary_entries
id, user_id, date, slot (frueh \| mittag \| abend \| snack), source
(food_db \| recipe \| ai_photo \| manual), label, grams numeric nullable, kcal, protein_g,
carbs_g, fat_g, recipe_id nullable, food_ref nullable, ai_confirmed boolean, created_at.

**Regel:** KI-geschätzte Einträge werden erst gespeichert, nachdem der Nutzer die Portion
bestätigt oder korrigiert hat.

## shopping_items
id, user_id, name, amount, unit, is_checked, source_recipe_id nullable.
Gleichnamige Zutaten werden bei der Anzeige zusammengefasst — klären, ob auch in der Tabelle.

## meal_plan
id, user_id, weekday 0–6, recipe_id, push_enabled boolean.
Push um 12:00 als lokale Benachrichtigung, kein Server-Job nötig.

## ai_calls
id, user_id, purpose (recipe_gen \| reel_import \| photo_analysis \| image_gen), model,
prompt_tokens, completion_tokens, **cost_usd numeric(10,6)** (echter Wert aus der
OpenRouter-Antwort), request_id, created_at.

## credit_ledger
id, user_id, kind (signup_bonus \| topup \| ai_charge), amount_usd numeric(10,6) (positiv oder
negativ), ai_call_id nullable, note, created_by_admin_id nullable, created_at.

**Regel:** Guthaben ist immer die Summe des Ledgers. `users.credit_usd` ist nur ein Cache und
wird serverseitig fortgeschrieben, nie vom Client.

## xp_events
id, user_id, kind, amount, ref_type, ref_id, created_at.
XP ist die Summe der Events. Missbrauchsschutz serverseitig — Strenge bitte klären.

## badges / user_badges
Katalog plus Freischaltungen mit Zeitstempel und Fortschrittszähler.

## admin_settings
Einzeiliger Datensatz: openrouter_api_key (verschlüsselt), openrouter_model, paypal_link,
updated_at. **Wird niemals an Clients ausgeliefert.**
