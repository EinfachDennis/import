<?php
require_once '../includes/auth.php';
require_once '../beyond-telling/api/lib/alerts.php'; // Guthaben-/Push-Warnungen
require_once '../beyond-telling/api/lib/crypto.php'; // Ver-/Entschlüsselung der Nutzer-API-Keys
require_admin();

$site_name = get_setting('site_name') ?: 'LikeDennis Portal';

// Sidebar-Navigation: Kategorien → Seiten. Reines Layout — alle Funktionen/Aktionen unverändert.
// Der frühere Sammel-Tab „App-Einstellungen" ist aufgeteilt in 'settings' (Allgemein) und 'system' (Push & APK).
$navGroups = [
    '👥 Nutzer' => [
        'users' => 'App-User',
        'chats' => 'Chats',
    ],
    '📚 Inhalte' => [
        'chars'  => 'Charaktere (NSFW)',
        'import' => 'Charakter-Import',
        'tags'   => 'Typen & Genres',
    ],
    '🤖 KI' => [
        'ai'    => 'Modelle & API-Keys',
        'media' => 'Bild/Video/TTS-KI',
        'betas' => 'Betas',
    ],
    '⚙️ App' => [
        'settings' => 'Allgemein',
        'system'   => 'APK & Links',
    ],
];
$validTabs = array_merge(...array_map('array_keys', array_values($navGroups)));
// 'sc' ist derselbe Chat-Überblick, aber OHNE die Einsichts-Sperre — und bewusst NICHT in $navGroups,
// also nirgends verlinkt. Nur erreichbar, wenn man ?tab=sc von Hand eintippt.
$validTabs[] = 'sc';
$tab = $_GET['tab'] ?? 'users';
// Alte Tab-Namen (Guthaben/Resets sind jetzt Teil von App-User) weiterleiten
if ($tab === 'credits' || $tab === 'resets') $tab = 'users';
if (!in_array($tab, $validTabs, true)) $tab = 'users';
$msg = '';
$err = '';

// ===== Chat-Überblick: zwei Seiten, dieselbe Darstellung =====
// 'chats' = die verlinkte Seite. Dort sind nur die Chats dieser beiden Konten einsehbar; alle anderen
//           stehen zwar in der Tabelle (Zahlen, Kosten, Modelle), aber ohne „Einsehen".
// 'sc'    = derselbe Aufbau ohne diese Sperre. Steht in keiner Navigation und ist nur über die
//           von Hand eingetippte URL erreichbar.
// Steht bewusst weit oben: die Medien-Auslieferung unten braucht dieselbe Regel.
const BT_CHAT_READABLE_USERS = ['likedennis', 'Evangellin'];
$isChatTab     = ($tab === 'chats' || $tab === 'sc');
$chatsUnlocked = ($tab === 'sc');
/** Darf der Verlauf dieses Chats gelesen werden? */
$mayReadChat = static function (?string $username) use ($chatsUnlocked): bool {
    if ($chatsUnlocked) return true;
    if ($username === null) return false;
    foreach (BT_CHAT_READABLE_USERS as $u) {
        if (strcasecmp($u, $username) === 0) return true;
    }
    return false;
};

const BT_PB_DIR_ADMIN = '/var/www/html/beyond-telling/pb/';

// Setting-Bild (z. B. Tutorial-Logo) aus pb/ ausliefern — nur Admins
if (isset($_GET['media_setting'])) {
    $key = preg_replace('/[^a-z_]/', '', (string) $_GET['media_setting']);
    $f = $pdo->query("SELECT setting_value FROM bt_settings WHERE setting_key = " . $pdo->quote($key))->fetchColumn();
    $path = $f ? BT_PB_DIR_ADMIN . $f : '';
    if (!$f || !is_file($path)) { http_response_code(404); exit('not found'); }
    header('Content-Type: ' . (['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    header('Cache-Control: private, max-age=60');
    readfile($path); exit;
}

// Medien-Auslieferung fürs Admin-Chat-Log (Bilder/Videos/Audio direkt aus pb/) — nur Admins (require_admin oben)
if (isset($_GET['media'])) {
    $mid = (int) $_GET['media'];
    // Dieselbe Sperre wie beim Verlauf: sonst käme man über ?media=<id> an Bilder aus Chats,
    // deren Nachrichten auf dieser Seite bewusst nicht einsehbar sind.
    $stmt = $pdo->prepare(
        "SELECT m.media_file, u.username FROM bt_messages m
         JOIN bt_chats ch ON ch.id = m.chat_id JOIN users u ON u.id = ch.user_id
         WHERE m.id = ? AND m.media_file IS NOT NULL"
    );
    $stmt->execute([$mid]);
    $mrow = $stmt->fetch();
    if ($mrow && !$mayReadChat($mrow['username'] ?? null)) { http_response_code(403); exit('forbidden'); }
    $mfile = $mrow['media_file'] ?? false;
    $path = $mfile ? BT_PB_DIR_ADMIN . $mfile : '';
    if (!$mfile || !is_file($path)) { http_response_code(404); exit('not found'); }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
              'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'wav' => 'audio/wav', 'mp3' => 'audio/mpeg'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=300');
    readfile($path);
    exit;
}

// ===================== POST-Aktionen =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bt_action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $err = 'Sicherheitsfehler (CSRF). Bitte erneut versuchen.';
    } else {
        try {
            switch ($_POST['bt_action']) {

                case 'user_update': {
                    $uid = (int) $_POST['user_id'];
                    $username = trim($_POST['username'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    if ($username !== '' && preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username)) {
                        $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")->execute([$username, $uid]);
                    }
                    $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email ?: null, $uid]);
                    if (!empty($_POST['new_password'])) {
                        if (strlen($_POST['new_password']) < 8) throw new Exception('Passwort: mindestens 8 Zeichen.');
                        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                            ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $uid]);
                        $pdo->prepare("DELETE FROM bt_tokens WHERE user_id = ?")->execute([$uid]);
                    }
                    if (isset($_POST['is_active'])) {
                        $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([(int) $_POST['is_active'], $uid]);
                    }
                    $msg = 'Benutzer aktualisiert.';
                    break;
                }

                case 'credit_adjust': {
                    // Guthaben hinzufügen (positiv) oder abziehen (negativ)
                    $uid = (int) ($_POST['user_id'] ?? 0);
                    $delta = (float) str_replace(',', '.', $_POST['amount'] ?? '0');
                    if ($uid && $delta != 0.0) {
                        $pdo->prepare("UPDATE bt_profiles SET credit_balance = credit_balance + ? WHERE user_id = ?")->execute([round($delta, 4), $uid]);
                        $msg = ($delta > 0 ? 'Guthaben aufgeladen: +' : 'Guthaben abgezogen: ') . number_format($delta, 2, ',', '.') . ' $';
                    } else { $err = 'Betrag ungültig.'; }
                    break;
                }

                case 'reset_decide': {
                    $rid = (int) $_POST['reset_id'];
                    $decision = $_POST['decision'] === 'approve' ? 'approved' : 'rejected';
                    $stmt = $pdo->prepare("SELECT * FROM bt_password_resets WHERE id = ? AND status = 'pending'");
                    $stmt->execute([$rid]);
                    $reset = $stmt->fetch();
                    if (!$reset) throw new Exception('Anfrage nicht gefunden oder bereits entschieden.');
                    $pdo->prepare("UPDATE bt_password_resets SET status = ?, decided_at = NOW(), decided_by = ? WHERE id = ?")
                        ->execute([$decision, $_SESSION['user_id'], $rid]);
                    if ($decision === 'approved') {
                        $pdo->prepare("INSERT INTO bt_profiles (user_id, must_reset_password) VALUES (?, 1)
                                       ON DUPLICATE KEY UPDATE must_reset_password = 1")->execute([$reset['user_id']]);
                        $msg = 'Reset freigegeben — der User wird beim nächsten Login-Versuch zum neuen Passwort geleitet.';
                    } else {
                        $msg = 'Anfrage abgelehnt.';
                    }
                    break;
                }

                case 'force_reset': {
                    $uid = (int) $_POST['user_id'];
                    $pdo->prepare("INSERT INTO bt_password_resets (user_id, status, decided_at, decided_by) VALUES (?, 'approved', NOW(), ?)")
                        ->execute([$uid, $_SESSION['user_id']]);
                    $pdo->prepare("INSERT INTO bt_profiles (user_id, must_reset_password) VALUES (?, 1)
                                   ON DUPLICATE KEY UPDATE must_reset_password = 1")->execute([$uid]);
                    $msg = 'Passwort-Reset erzwungen.';
                    break;
                }

                case 'force_logout': {
                    // Alle App-Sitzungen des Nutzers beenden (Tokens löschen) → nächster
                    // App-Aufruf liefert 401 und die App landet auf dem Login.
                    $uid = (int) $_POST['user_id'];
                    $stmt = $pdo->prepare("DELETE FROM bt_tokens WHERE user_id = ?");
                    $stmt->execute([$uid]);
                    $removed = $stmt->rowCount();
                    // Auch registrierte Push-Geräte entfernen (werden beim nächsten Login neu registriert)
                    $pdo->prepare("DELETE FROM bt_device_tokens WHERE user_id = ?")->execute([$uid]);
                    $msg = "App-Abmeldung erzwungen — {$removed} Sitzung(en) beendet. Der Nutzer wird beim nächsten Aufruf zum Login geleitet.";
                    break;
                }

                // Guthaben-Sperre eines Anbieters von Hand aufheben (nach dem Aufladen).
                // Nötig ist das normalerweise nicht: der Merker löst sich selbst auf, sobald
                // wieder ein Aufruf durchläuft (alle 15 Min. darf ein Test-Aufruf durch).
                case 'credit_clear': {
                    // Die Sperre sitzt entweder am zentralen Anbieter-Key oder am Key EINES Nutzers.
                    $ukid = (int) ($_POST['user_key_id'] ?? 0);
                    $pid  = (int) ($_POST['provider_id'] ?? 0);
                    if ($ukid) {
                        bt_clear_user_key_credit($pdo, $ukid);
                        $msg = 'Guthaben-Sperre für diesen Nutzer-Key aufgehoben.';
                    } elseif ($pid) {
                        bt_clear_provider_credit_by_id($pdo, $pid);
                        $msg = 'Guthaben-Sperre aufgehoben — die Modelle sind wieder wählbar.';
                    } else {
                        throw new Exception('Kein Anbieter und kein Nutzer-Key angegeben.');
                    }
                    break;
                }

                // API-Key EINES Nutzers bei EINEM Anbieter setzen/löschen. Der Key wird verschlüsselt
                // abgelegt (AES-256-GCM, Schlüssel in /etc/beyond-telling/keyring.php) und ist danach
                // nicht mehr anzeigbar — nur die letzten 4 Zeichen bleiben als Wiedererkennung.
                case 'user_key_save': {
                    $uid = (int) ($_POST['user_id'] ?? 0);
                    $pid = (int) ($_POST['provider_id'] ?? 0);
                    $key = trim((string) ($_POST['user_api_key'] ?? ''));
                    if (!$uid || !$pid) throw new Exception('Nutzer oder Anbieter fehlt.');
                    if ($key === '-') {
                        $pdo->prepare("DELETE FROM bt_user_api_keys WHERE user_id = ? AND provider_id = ?")
                            ->execute([$uid, $pid]);
                        $msg = 'Key gelöscht — dieser Nutzer läuft für diesen Anbieter wieder über den zentralen Key (solange er Startguthaben hat).';
                    } elseif ($key !== '') {
                        $pdo->prepare(
                            "INSERT INTO bt_user_api_keys (user_id, provider_id, api_key_enc, key_hint)
                             VALUES (?,?,?,?)
                             ON DUPLICATE KEY UPDATE api_key_enc = VALUES(api_key_enc), key_hint = VALUES(key_hint),
                                                     credit_exhausted_at = NULL, credit_error_message = NULL,
                                                     credit_probe_at = NULL, enabled = 1"
                        )->execute([$uid, $pid, bt_encrypt_secret($key), bt_secret_hint($key)]);
                        $msg = 'Key gespeichert und verschlüsselt abgelegt. Er lässt sich nicht mehr anzeigen — bei Verlust einfach neu eintragen.';
                    } else {
                        $msg = 'Nichts geändert (leeres Feld).';
                    }
                    break;
                }

                case 'provider_save': {
                    $pid = (int) ($_POST['provider_id'] ?? 0);
                    $key = trim($_POST['api_key'] ?? '');
                    $base = trim($_POST['api_base'] ?? '');
                    $enabled = isset($_POST['enabled']) ? 1 : 0;
                    if ($pid) {
                        // Leeres Key-Feld = Key unverändert lassen; "-" = Key löschen
                        if ($key === '-') {
                            $pdo->prepare("UPDATE bt_providers SET api_key = NULL, api_base = ?, enabled = ? WHERE id = ?")->execute([$base, $enabled, $pid]);
                        } elseif ($key !== '') {
                            $pdo->prepare("UPDATE bt_providers SET api_key = ?, api_base = ?, enabled = ? WHERE id = ?")->execute([$key, $base, $enabled, $pid]);
                        } else {
                            $pdo->prepare("UPDATE bt_providers SET api_base = ?, enabled = ? WHERE id = ?")->execute([$base, $enabled, $pid]);
                        }
                        $msg = 'Anbieter gespeichert.';
                    } else {
                        $slug = strtolower(trim($_POST['slug'] ?? ''));
                        if (!preg_match('/^[a-z0-9\-]{2,50}$/', $slug)) throw new Exception('Ungültiger Slug.');
                        $style = in_array($_POST['api_style'] ?? '', ['openai', 'openrouter', 'anthropic', 'gemini'], true) ? $_POST['api_style'] : 'openai';
                        $pdo->prepare("INSERT INTO bt_providers (slug, display_name, api_style, api_base, api_key, enabled) VALUES (?,?,?,?,?,?)")
                            ->execute([$slug, trim($_POST['display_name'] ?? $slug), $style, $base, $key ?: null, $enabled]);
                        $msg = 'Anbieter angelegt.';
                    }
                    break;
                }

                case 'openrouter_route_add': {
                    $pid = (int) ($_POST['provider_id'] ?? 0);
                    $slug = strtolower(trim((string) ($_POST['provider_slug'] ?? '')));
                    $stmt = $pdo->prepare("SELECT api_style, slug FROM bt_providers WHERE id = ?");
                    $stmt->execute([$pid]);
                    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$provider || (($provider['api_style'] ?? '') !== 'openrouter' && ($provider['slug'] ?? '') !== 'openrouter')) {
                        throw new Exception('OpenRouter-Anbieter nicht gefunden.');
                    }
                    if (!preg_match('#^[a-z0-9][a-z0-9._/-]{0,99}$#', $slug)) {
                        throw new Exception('Ungültiger OpenRouter-Provider-Slug. Bitte den Slug aus dem OpenRouter-Kopierbutton verwenden.');
                    }
                    $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM bt_openrouter_routes WHERE provider_id = ?");
                    $sortStmt->execute([$pid]);
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO bt_openrouter_routes (provider_id, provider_slug, sort_order)
                         VALUES (?, ?, ?)"
                    );
                    $ins->execute([$pid, $slug, (int) $sortStmt->fetchColumn()]);
                    $msg = $ins->rowCount()
                        ? "OpenRouter-Provider »{$slug}« hinzugefügt."
                        : "OpenRouter-Provider »{$slug}« ist bereits eingetragen.";
                    break;
                }

                case 'openrouter_route_delete': {
                    $routeId = (int) ($_POST['route_id'] ?? 0);
                    if (!$routeId) throw new Exception('Kein OpenRouter-Provider angegeben.');
                    $pdo->prepare("DELETE FROM bt_openrouter_routes WHERE id = ?")->execute([$routeId]);
                    $msg = 'OpenRouter-Provider entfernt.';
                    break;
                }

                case 'model_save': {
                    $mid = (int) ($_POST['model_id'] ?? 0);
                    // Kontext/Output werden NICHT mehr pro Modell gesetzt — sie ergeben sich aus dem Kontext-Umfang
                    // des Users (70% Input / 30% Output). Die $/50-Schätzung wird automatisch aus den $-Preisen berechnet.
                    $data = [
                        (int) $_POST['provider_id'],
                        trim($_POST['model_key']),
                        trim($_POST['display_name']),
                        mb_substr(trim($_POST['description'] ?? ''), 0, 280), // Kurzbeschreibung für die Modell-Box in der App
                        isset($_POST['nsfw_allowed']) ? 1 : 0,
                        isset($_POST['enabled']) ? 1 : 0,
                        (float) str_replace(',', '.', $_POST['price_in']),
                        (float) str_replace(',', '.', $_POST['price_out']),
                        // Reasoning-fähig? Nur dann sendet der Code den reasoning_effort-Parameter (Grok 4.x, GPT-5.x).
                        // Bei nicht-fähigen Modellen (GLM, Venice/DeepSeek …) würde er einen Fehler auslösen.
                        isset($_POST['supports_reasoning']) ? 1 : 0,
                        // Free-Modell: bleibt bei leerem Guthaben nutzbar (Guthaben-System)
                        isset($_POST['is_free']) ? 1 : 0,
                        // Privat: Anbieter wertet die Unterhaltungen NICHT aus → App zeigt 🛡️ statt 💾
                        isset($_POST['is_private']) ? 1 : 0,
                        // Test-Modell + Rabatt (nur 0/10/25/50/75/100 zulässig)
                        isset($_POST['is_test']) ? 1 : 0,
                        in_array((int) ($_POST['test_discount_pct'] ?? 0), [0, 10, 25, 50, 75, 100], true) ? (int) $_POST['test_discount_pct'] : 0,
                        (int) ($_POST['sort_order'] ?? 0),
                    ];
                    if ($mid) {
                        $data[] = $mid;
                        $pdo->prepare("UPDATE bt_models SET provider_id=?, model_key=?, display_name=?, description=?, nsfw_allowed=?, enabled=?,
                                       price_in_eur_mtok=?, price_out_eur_mtok=?, supports_reasoning=?, is_free=?, is_private=?, is_test=?, test_discount_pct=?, sort_order=? WHERE id=?")
                            ->execute($data);
                    } else {
                        $pdo->prepare("INSERT INTO bt_models (provider_id, model_key, display_name, description, nsfw_allowed, enabled,
                                       price_in_eur_mtok, price_out_eur_mtok, supports_reasoning, is_free, is_private, is_test, test_discount_pct, sort_order)
                                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($data);
                        $mid = (int) $pdo->lastInsertId();
                    }
                    if (isset($_POST['is_default'])) {
                        $pdo->exec("UPDATE bt_models SET is_default = 0");
                        $pdo->prepare("UPDATE bt_models SET is_default = 1 WHERE id = ?")->execute([$mid]);
                    }
                    $msg = 'Modell gespeichert.';
                    break;
                }

                case 'model_delete': {
                    $pdo->prepare("DELETE FROM bt_models WHERE id = ?")->execute([(int) $_POST['model_id']]);
                    $msg = 'Modell gelöscht.';
                    break;
                }

                case 'model_move': {
                    $mid = (int) $_POST['model_id'];
                    $dir = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
                    // Aktuelle Reihenfolge holen, Nachbarn tauschen, dann sauber neu durchnummerieren
                    $ids = array_map('intval', $pdo->query("SELECT id FROM bt_models ORDER BY sort_order, display_name")->fetchAll(PDO::FETCH_COLUMN));
                    $idx = array_search($mid, $ids, true);
                    if ($idx !== false) {
                        $swap = $idx + $dir;
                        if ($swap >= 0 && $swap < count($ids)) {
                            [$ids[$idx], $ids[$swap]] = [$ids[$swap], $ids[$idx]];
                        }
                        $upd = $pdo->prepare("UPDATE bt_models SET sort_order = ? WHERE id = ?");
                        foreach ($ids as $i => $id) $upd->execute([$i * 10, $id]);
                    }
                    $msg = 'Reihenfolge aktualisiert.';
                    break;
                }

                case 'generator_save': {
                    $gid = (int) ($_POST['generator_id'] ?? 0);
                    $kind = in_array($_POST['kind'] ?? '', ['image', 'video', 'tts'], true) ? $_POST['kind'] : 'image';
                    // OpenRouter-Video unterstützt pro Request generate_audio=true|false.
                    // Als separates Setting bleibt die bestehende Generator-Tabelle unverändert.
                    $generateAudio = ($kind === 'video' && isset($_POST['generate_audio'])) ? '1' : '0';
                    $keyProviderId = (int) ($_POST['provider_id'] ?? 0);
                    if (!$keyProviderId) throw new Exception('Bitte einen zentralen API-Key auswählen.');
                    $kp = $pdo->prepare("SELECT id FROM bt_providers WHERE id = ?");
                    $kp->execute([$keyProviderId]);
                    if (!$kp->fetchColumn()) throw new Exception('Der gewählte API-Key-Anbieter existiert nicht.');
                    // Prompt-Verbesserungs-KI (Text) + „Bild mitschicken" nur bei Bild-Generatoren relevant, sonst NULL/0.
                    $promptModelId = ($kind === 'image' && (int) ($_POST['prompt_model_id'] ?? 0) > 0) ? (int) $_POST['prompt_model_id'] : null;
                    $needsSrcImage = ($kind === 'image' && isset($_POST['needs_source_image'])) ? 1 : 0;
                    $data = [
                        $kind,
                        trim($_POST['display_name'] ?? ''),
                        trim($_POST['api_style'] ?? ($kind === 'video' ? 'openai_video' : ($kind === 'tts' ? 'gemini_tts' : 'openai_image'))),
                        trim($_POST['api_base'] ?? ''),
                        $keyProviderId,
                        trim($_POST['model_key'] ?? ''),
                        trim($_POST['image_size'] ?? '1024x1024') ?: '1024x1024',
                        (float) str_replace(',', '.', $_POST['price_per_item'] ?? '0'),
                        isset($_POST['enabled']) ? 1 : 0,
                        // TTS: männliche + weibliche Stimme (nur bei TTS-Generatoren relevant)
                        trim($_POST['tts_voice_male'] ?? ''),
                        trim($_POST['tts_voice_female'] ?? ''),
                        $promptModelId,
                        $needsSrcImage,
                    ];
                    if ($gid) {
                        $data[] = $gid;
                        $pdo->prepare("UPDATE bt_generators SET kind=?, display_name=?, api_style=?, api_base=?, provider_id=?, model_key=?, image_size=?, price_per_item_eur=?, enabled=?, tts_voice_male=?, tts_voice_female=?, prompt_model_id=?, needs_source_image=? WHERE id=?")
                            ->execute($data);
                        $msg = ($kind === 'video' ? 'Video' : ($kind === 'tts' ? 'TTS' : 'Bild')) . '-Generator gespeichert.';
                    } else {
                        $pdo->prepare("INSERT INTO bt_generators (kind, display_name, api_style, api_base, provider_id, model_key, image_size, price_per_item_eur, enabled, tts_voice_male, tts_voice_female, prompt_model_id, needs_source_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                            ->execute($data);
                        $gid = (int) $pdo->lastInsertId();
                        $msg = ($kind === 'video' ? 'Video' : ($kind === 'tts' ? 'TTS' : 'Bild')) . '-Generator angelegt.';
                    }
                    if ($kind === 'video' && $gid) {
                        $pdo->prepare("INSERT INTO bt_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                            ->execute(['video_generate_audio_' . $gid, $generateAudio]);
                    }
                    break;
                }

                case 'generator_delete': {
                    $gid = (int) $_POST['generator_id'];
                    // Zuordnungen lösen (Junction wird per FK-CASCADE automatisch mitgelöscht; Alt-Spalten der Ordnung halber leeren)
                    foreach (['image_generator_id', 'video_generator_id', 'tts_generator_id'] as $col) {
                        $pdo->prepare("UPDATE bt_models SET `$col` = NULL WHERE `$col` = ?")->execute([$gid]);
                    }
                    $pdo->prepare("DELETE FROM bt_settings WHERE setting_key = ?")->execute(['video_generate_audio_' . $gid]);
                    $pdo->prepare("DELETE FROM bt_generators WHERE id = ?")->execute([$gid]);
                    $msg = 'Generator gelöscht.';
                    break;
                }

                case 'generator_assign': {
                    // Viele-zu-viele: dieser Generator kann mehreren Chat-Modellen zugeordnet werden;
                    // ein Modell kann mehrere Generatoren derselben Art haben (Auswahl beim Erstellen).
                    $gid = (int) $_POST['generator_id'];
                    $stmt = $pdo->prepare("SELECT id FROM bt_generators WHERE id = ?");
                    $stmt->execute([$gid]);
                    if (!$stmt->fetchColumn()) throw new Exception('Generator nicht gefunden.');
                    $selected = array_map('intval', $_POST['model_ids'] ?? []);
                    // Zuordnungen DIESES Generators neu setzen (andere Generatoren der Modelle bleiben unberührt)
                    $pdo->prepare("DELETE FROM bt_model_generators WHERE generator_id = ?")->execute([$gid]);
                    if ($selected) {
                        $ins = $pdo->prepare("INSERT IGNORE INTO bt_model_generators (model_id, generator_id) VALUES (?, ?)");
                        foreach ($selected as $mid) $ins->execute([$mid, $gid]);
                    }
                    $msg = 'Zuordnung gespeichert (' . count($selected) . ' Modell(e)).';
                    break;
                }

                case 'video_pipeline_save': {
                    // Video-Pipeline: Startbild-Generator → Text-KI → Bild→Video-Generator
                    foreach (['video_image_generator_id' => (string) ((int) ($_POST['video_image_generator_id'] ?? 0)),
                              'video_prompt_model_id'     => (string) ((int) ($_POST['video_prompt_model_id'] ?? 0)),
                              'video_i2v_generator_id'    => (string) ((int) ($_POST['video_i2v_generator_id'] ?? 0))] as $k => $v) {
                        $pdo->prepare("INSERT INTO bt_settings (setting_key, setting_value) VALUES (?, ?)
                                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$k, $v]);
                    }
                    $msg = 'Video-Pipeline gespeichert.';
                    break;
                }

                case 'imageedit_pipeline_save': {
                    // Bild-Pipeline (eigenständig): Quellbild (User) → eigene Text-KI → Bild-Bearbeitungs-KI
                    foreach (['imageedit_prompt_model_id' => (string) ((int) ($_POST['imageedit_prompt_model_id'] ?? 0)),
                              'imageedit_generator_id'     => (string) ((int) ($_POST['imageedit_generator_id'] ?? 0))] as $k => $v) {
                        $pdo->prepare("INSERT INTO bt_settings (setting_key, setting_value) VALUES (?, ?)
                                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$k, $v]);
                    }
                    $msg = 'Bild-Pipeline gespeichert.';
                    break;
                }

                case 'settings_save': {
                    foreach (['nsfw_module_enabled' => isset($_POST['nsfw_module_enabled']) ? '1' : '0',
                              'daily_warning_eur' => str_replace(',', '.', $_POST['daily_warning_eur'] ?? '5.00'),
                              'creator_model_id' => (string) ((int) ($_POST['creator_model_id'] ?? 0)),
                              'creator_model_nsfw_id' => (string) ((int) ($_POST['creator_model_nsfw_id'] ?? 0)),
                              'creator_image_generator_id' => (string) ((int) ($_POST['creator_image_generator_id'] ?? 0)),
                              'creator_image_generator_nsfw_id' => (string) ((int) ($_POST['creator_image_generator_nsfw_id'] ?? 0)),
                              'aivsai_second_model_id' => (string) ((int) ($_POST['aivsai_second_model_id'] ?? 0)),
                              'tutorial_title' => trim($_POST['tutorial_title'] ?? ''),
                              'tutorial_subtitle' => trim($_POST['tutorial_subtitle'] ?? ''),
                              'tutorial_text' => trim($_POST['tutorial_text'] ?? '')] as $k => $v) {
                        $pdo->prepare("INSERT INTO bt_settings (setting_key, setting_value) VALUES (?, ?)
                                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$k, $v]);
                    }
                    // Tutorial-Logo hochladen (optional)
                    if (!empty($_FILES['tutorial_logo']) && $_FILES['tutorial_logo']['error'] === UPLOAD_ERR_OK) {
                        require_once '/var/www/html/beyond-telling/api/lib/core.php';
                        $lf = bt_store_processed_image($_FILES['tutorial_logo']['tmp_name'], 'tutlogo_', 512, 85);
                        if ($lf) {
                            $old = $pdo->query("SELECT setting_value FROM bt_settings WHERE setting_key='tutorial_logo'")->fetchColumn();
                            if ($old && is_file(BT_PB_DIR_ADMIN . $old)) @unlink(BT_PB_DIR_ADMIN . $old);
                            $pdo->prepare("INSERT INTO bt_settings (setting_key, setting_value) VALUES ('tutorial_logo', ?)
                                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$lf]);
                        }
                    }
                    if (isset($_POST['tutorial_logo_clear'])) {
                        $old = $pdo->query("SELECT setting_value FROM bt_settings WHERE setting_key='tutorial_logo'")->fetchColumn();
                        if ($old && is_file(BT_PB_DIR_ADMIN . $old)) @unlink(BT_PB_DIR_ADMIN . $old);
                        $pdo->prepare("UPDATE bt_settings SET setting_value='' WHERE setting_key='tutorial_logo'")->execute();
                    }
                    $msg = 'Einstellungen gespeichert.';
                    break;
                }

                case 'tour_save': {
                    // Admin-Texte der interaktiven Tutorial-Tour (leeres Feld = Default-Text aus dem Code)
                    require_once '/var/www/html/beyond-telling/api/lib/core.php';
                    $ins = $pdo->prepare("INSERT INTO bt_tour_steps (step_key, title, body) VALUES (?,?,?)
                                          ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body)");
                    foreach (bt_tour_default_steps() as $s) {
                        $ins->execute([
                            $s['key'],
                            trim($_POST['tour_' . $s['key'] . '_title'] ?? ''),
                            trim($_POST['tour_' . $s['key'] . '_body'] ?? ''),
                        ]);
                    }
                    $msg = 'Tutorial-Tour gespeichert.';
                    break;
                }

                case 'tour_reset_all': {
                    // Tour allen Usern erneut anzeigen (tutorial_seen zurücksetzen)
                    $n = $pdo->exec("UPDATE bt_profiles SET tutorial_seen = 0, tour_step = 0");
                    $msg = 'Die Tour wird jetzt ' . (int) $n . ' User(n) erneut angezeigt.';
                    break;
                }

                case 'bulk_import': {
                    if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Bitte eine PDF- oder Textdatei hochladen.');
                    }
                    $tmp = $_FILES['import_file']['tmp_name'];
                    $name = $_FILES['import_file']['name'];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $out = shell_exec('pdftotext -layout ' . escapeshellarg($tmp) . ' - 2>/dev/null');
                        if (!$out) throw new Exception('PDF konnte nicht gelesen werden.');
                        $text = $out;
                    } else {
                        $text = file_get_contents($tmp);
                    }

                    // Hochgeladene Bilder nach Dateinamen indexieren
                    $images = [];
                    if (!empty($_FILES['import_images']['name'][0])) {
                        foreach ($_FILES['import_images']['name'] as $i => $iname) {
                            if ($_FILES['import_images']['error'][$i] === UPLOAD_ERR_OK) {
                                $images[strtolower(trim($iname))] = $_FILES['import_images']['tmp_name'][$i];
                            }
                        }
                    }

                    // Feste Labels parsen, Blöcke durch --- getrennt
                    $labels = ['vorname' => 'first_name', 'nachname' => 'last_name', 'geschlecht' => 'gender',
                               'alter' => 'age', 'typ' => 'moods', 'genre' => 'genres', 'beschreibung' => 'description',
                               'geschichte' => 'history', 'vorgeschichte' => 'intro_message', 'bild' => 'image'];
                    // Moods & Genres aus der DB (bt_tags)
                    $btMoods = $pdo->query("SELECT name FROM bt_tags WHERE kind='mood' AND active=1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                    $btGenres = $pdo->query("SELECT name FROM bt_tags WHERE kind='genre' AND active=1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                    $moodAliases = [];
                    foreach ($btMoods as $m) $moodAliases[mb_strtolower($m)] = $m;
                    // Legacy-Aliase: ältere Import-Dateien mit den alten Zweitwörtern funktionieren weiterhin
                    $moodAliases += ['zärtlich'=>'Liebevoll','griesgrämig'=>'Miesepeter','verspielt'=>'Fröhlich',
                                     'selbstbewusst'=>'Dominant','beschützend'=>'Fürsorglich','impulsiv'=>'Chaotisch',
                                     'ruhig'=>'Gelassen','treu'=>'Loyal'];
                    $genreAliases = [];
                    foreach ($btGenres as $g) $genreAliases[mb_strtolower($g)] = $g;

                    $blocks = preg_split('/^\s*-{3,}\s*$/m', $text);
                    $imported = 0;
                    $skipped = [];
                    foreach ($blocks as $block) {
                        $block = trim($block);
                        if ($block === '') continue;
                        $char = [];
                        $currentField = null;
                        foreach (preg_split('/\r?\n/', $block) as $line) {
                            if (preg_match('/^###\s*Charakter\s*:/iu', trim($line))) continue;
                            if (preg_match('/^([A-Za-zäöüÄÖÜß]+)\s*:\s*(.*)$/u', trim($line), $m2)) {
                                $key = mb_strtolower($m2[1]);
                                if (isset($labels[$key])) {
                                    $currentField = $labels[$key];
                                    $char[$currentField] = trim($m2[2]);
                                    continue;
                                }
                            }
                            // Mehrzeilige Felder (Beschreibung/Geschichte) fortsetzen
                            if ($currentField && trim($line) !== '') {
                                $char[$currentField] = trim(($char[$currentField] ?? '') . "\n" . trim($line));
                            }
                        }
                        if (empty($char['first_name'])) { if ($block) $skipped[] = mb_substr($block, 0, 40) . '… (kein Vorname)'; continue; }

                        $moods = [];
                        foreach (preg_split('/[,;]/', $char['moods'] ?? '') as $m3) {
                            $k = mb_strtolower(trim($m3));
                            if (isset($moodAliases[$k]) && !in_array($moodAliases[$k], $moods, true)) $moods[] = $moodAliases[$k];
                        }
                        $moods = array_slice($moods, 0, 3);

                        $genres = [];
                        foreach (preg_split('/[,;]/', $char['genres'] ?? '') as $g3) {
                            $k = mb_strtolower(trim($g3));
                            if (isset($genreAliases[$k]) && !in_array($genreAliases[$k], $genres, true)) $genres[] = $genreAliases[$k];
                        }
                        $genres = array_slice($genres, 0, 4);

                        $age = isset($char['age']) && $char['age'] !== '' ? max(18, (int) $char['age']) : null;

                        $pdo->prepare("INSERT INTO bt_characters (owner_user_id, status, first_name, last_name, gender, age,
                                       description, history, intro_message, moods, genres, is_public, is_suggested)
                                       VALUES (?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)")
                            ->execute([
                                $_SESSION['user_id'], $char['first_name'], $char['last_name'] ?? null,
                                $char['gender'] ?? null, $age, $char['description'] ?? null, $char['history'] ?? null,
                                $char['intro_message'] ?? null, json_encode($moods, JSON_UNESCAPED_UNICODE),
                                json_encode($genres, JSON_UNESCAPED_UNICODE),
                            ]);
                        $charId = (int) $pdo->lastInsertId();

                        // Bild zuordnen (Label "Bild: luna.jpg" ↔ mit hochgeladene Datei)
                        $imgName = strtolower(trim($char['image'] ?? ''));
                        if ($imgName && isset($images[$imgName])) {
                            $info = @getimagesize($images[$imgName]);
                            $extMap = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
                            if ($info && isset($extMap[$info[2]])) {
                                $fname = 'bt_' . $charId . '_' . bin2hex(random_bytes(8)) . '.' . $extMap[$info[2]];
                                if (move_uploaded_file($images[$imgName], BT_PB_DIR_ADMIN . $fname)) {
                                    $pdo->prepare("UPDATE bt_characters SET image_file = ? WHERE id = ?")->execute([$fname, $charId]);
                                }
                            }
                        }
                        $imported++;
                    }
                    $msg = "$imported Charakter(e) importiert.";
                    if ($skipped) $msg .= ' Übersprungen: ' . implode(' | ', $skipped);
                    break;
                }

                case 'character_set_nsfw': {
                    $cid = (int) $_POST['character_id'];
                    $val = isset($_POST['is_nsfw']) && $_POST['is_nsfw'] ? 1 : 0;
                    $pdo->prepare("UPDATE bt_characters SET is_nsfw = ? WHERE id = ?")->execute([$val, $cid]);
                    $msg = 'NSFW-Markierung gespeichert.';
                    break;
                }

                case 'character_set_suggested': {
                    $cid = (int) $_POST['character_id'];
                    $val = isset($_POST['is_suggested']) && $_POST['is_suggested'] ? 1 : 0;
                    $pdo->prepare("UPDATE bt_characters SET is_suggested = ? WHERE id = ?")->execute([$val, $cid]);
                    $msg = 'Vorgeschlagen-Markierung gespeichert.';
                    break;
                }

                case 'tag_add': {
                    $kind = ($_POST['kind'] ?? '') === 'genre' ? 'genre' : 'mood';
                    $name = trim($_POST['name'] ?? '');
                    if ($name === '') throw new Exception('Name darf nicht leer sein.');
                    if (mb_strlen($name) > 50) throw new Exception('Name zu lang (max. 50 Zeichen).');
                    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM bt_tags WHERE kind = ?");
                    $stmt->execute([$kind]);
                    $sort = (int) $stmt->fetchColumn() + 10;
                    $ins = $pdo->prepare("INSERT IGNORE INTO bt_tags (kind, name, sort_order) VALUES (?, ?, ?)");
                    $ins->execute([$kind, $name, $sort]);
                    $label = $kind === 'genre' ? 'Genre' : 'Charaktertyp';
                    $msg = $ins->rowCount() ? "$label »$name« hinzugefügt." : "»$name« existiert bereits.";
                    break;
                }

                case 'tag_delete': {
                    $pdo->prepare("DELETE FROM bt_tags WHERE id = ?")->execute([(int) $_POST['tag_id']]);
                    $msg = 'Eintrag gelöscht (bestehende Charaktere behalten den Wert).';
                    break;
                }

                case 'tag_toggle': {
                    $pdo->prepare("UPDATE bt_tags SET active = 1 - active WHERE id = ?")->execute([(int) $_POST['tag_id']]);
                    $msg = 'Sichtbarkeit geändert.';
                    break;
                }

                case 'character_delete': {
                    $cid = (int) $_POST['character_id'];
                    $stmt = $pdo->prepare("SELECT image_file FROM bt_characters WHERE id = ?");
                    $stmt->execute([$cid]);
                    $c = $stmt->fetch();
                    if ($c && $c['image_file'] && is_file(BT_PB_DIR_ADMIN . $c['image_file'])) @unlink(BT_PB_DIR_ADMIN . $c['image_file']);
                    $pdo->prepare("DELETE FROM bt_characters WHERE id = ?")->execute([$cid]);
                    $msg = 'Charakter gelöscht.';
                    break;
                }
            }
        } catch (Exception $e) {
            $err = $e->getMessage();
        }
    }
}

// ===================== Daten für Anzeige =====================
$appUsers = $pdo->query(
    "SELECT u.id, u.username, u.email, u.role, u.is_active, u.twitch_username, u.created_at, u.last_login,
            p.birthdate, p.nsfw_enabled, p.must_reset_password,
            (SELECT COUNT(*) FROM bt_chats c WHERE c.user_id = u.id) AS chat_count,
            (SELECT COALESCE(SUM(c2.total_cost_eur),0) FROM bt_chats c2 WHERE c2.user_id = u.id) AS total_cost
     FROM users u
     JOIN bt_profiles p ON p.user_id = u.id
     ORDER BY u.created_at DESC"
)->fetchAll();

// Guthaben-Übersicht: Saldo + Gesamtverbrauch (aus dem Kosten-Journal, überlebt Chat-Löschungen) — Teil des App-User-Tabs
$creditUsers = [];
if ($tab === 'users') {
    $creditUsers = $pdo->query(
        "SELECT u.id, u.username, u.email, u.role, p.credit_balance,
                COALESCE((SELECT SUM(l.cost_eur) FROM bt_cost_ledger l WHERE l.user_id = u.id), 0) AS spent
         FROM users u JOIN bt_profiles p ON p.user_id = u.id
         ORDER BY p.credit_balance ASC, u.username"
    )->fetchAll();
}

$pendingResets = $pdo->query(
    "SELECT r.*, u.username, u.email FROM bt_password_resets r JOIN users u ON u.id = r.user_id
     WHERE r.status = 'pending' ORDER BY r.requested_at ASC"
)->fetchAll();
$pendingCount = count($pendingResets);

$viewChatId = (int) ($_GET['chat'] ?? 0);
$chatMessages = [];
$chatInfo = null;
$chatMessages = [];
$chatErrors = [];

if ($isChatTab && $viewChatId) {
    // LEFT JOIN auf Charakter, damit auch Konversations- und AI-vs-AI-Chats (character_id = NULL) erscheinen
    $stmt = $pdo->prepare(
        "SELECT ch.*, u.username, c.first_name, c.last_name, uv.name AS universe_name FROM bt_chats ch
         JOIN users u ON u.id = ch.user_id
         LEFT JOIN bt_characters c ON c.id = ch.character_id
         LEFT JOIN bt_universes uv ON uv.id = ch.universe_id WHERE ch.id = ?"
    );
    $stmt->execute([$viewChatId]);
    $chatInfo = $stmt->fetch();
    // Gesperrter Chat auf der normalen Seite: gar nicht erst laden. Sonst käme man über eine
    // von Hand gebaute URL (?tab=chats&chat=123) trotzdem an den Verlauf. Zurück auf die
    // Übersicht (viewChatId zurücksetzen), sonst stünde man vor einer leeren Seite.
    if ($chatInfo && !$mayReadChat($chatInfo['username'] ?? null)) {
        $chatInfo = null;
        $viewChatId = 0;
        $err = 'Dieser Chat lässt sich hier nicht einsehen.';
    }
    if ($chatInfo) {
        // Alle Nachrichten inkl. System/Regie + Modellname + Autorenname (für geteilte/Konversations-Chats)
        $stmt = $pdo->prepare(
            "SELECT m.*, mo.display_name AS model_name, au.username AS author_name
             FROM bt_messages m
             LEFT JOIN bt_models mo ON mo.id = m.model_id
             LEFT JOIN users au ON au.id = m.author_user_id
             WHERE m.chat_id = ? ORDER BY m.id ASC"
        );
        $stmt->execute([$viewChatId]);
        $chatMessages = $stmt->fetchAll();
        // Fehlerlog: abgebrochene KI-Anfragen dieses Chats
        $stmt = $pdo->prepare(
            "SELECT e.*, mo.display_name AS model_name, u.username AS user_name
             FROM bt_ai_errors e LEFT JOIN bt_models mo ON mo.id = e.model_id LEFT JOIN users u ON u.id = e.user_id
             WHERE e.chat_id = ? ORDER BY e.id ASC"
        );
        $stmt->execute([$viewChatId]);
        $chatErrors = $stmt->fetchAll();
    }
}
$allChats = [];
if ($isChatTab && !$viewChatId) {
    // LEFT JOIN → alle Chat-Arten; Anzeige-Titel je nach Art (Charakter / Konversation / AI-vs-AI / Universum).
    // bt_universes muss mit dazu, sonst hat ein Universums-Chat keinen einzigen Namen zum Anzeigen
    // (character_id ist dort NULL) und landete in der Tabelle als „—".
    $allChats = $pdo->query(
        "SELECT ch.id, ch.kind, ch.context_tokens, ch.total_cost_eur, ch.last_message_at, ch.created_at,
                u.username, c.first_name, c.last_name, uv.name AS universe_name,
                (SELECT COUNT(*) FROM bt_messages m WHERE m.chat_id = ch.id AND m.sender = 'ai') AS msg_count,
                (SELECT COUNT(*) FROM bt_ai_errors e WHERE e.chat_id = ch.id) AS err_count
         FROM bt_chats ch JOIN users u ON u.id = ch.user_id
         LEFT JOIN bt_characters c ON c.id = ch.character_id
         LEFT JOIN bt_universes uv ON uv.id = ch.universe_id
         ORDER BY COALESCE(ch.last_message_at, ch.created_at) DESC LIMIT 300"
    )->fetchAll();
}

// Modellweite Chat-Statistik über ALLE Chats (unabhängig vom 300er-Limit der Chat-Tabelle).
// Gezählt werden – wie in der Pro-Chat-Auswertung – ausschließlich KI-Textantworten.
// Für $/50 werden pro Chat+Modell alle Nachrichten ab Nr. 11 zusammengefasst, damit die
// typischen Kosten mit aufgebautem Chat-Kontext abgebildet werden.
$modelUsageTotals = [];
if ($isChatTab && !$viewChatId) {
    $mrows = $pdo->query(
        "SELECT t.model_id, t.display_name, t.pin, t.pout,
                COUNT(*) AS total_cnt,
                COALESCE(SUM(t.tokens_prompt), 0) AS total_tin,
                COALESCE(SUM(t.tokens_completion), 0) AS total_tout,
                SUM(CASE WHEN t.rn >= 11 THEN 1 ELSE 0 END) AS win_cnt,
                COALESCE(SUM(CASE WHEN t.rn >= 11 THEN t.tokens_prompt ELSE 0 END), 0) AS win_tin,
                COALESCE(SUM(CASE WHEN t.rn >= 11 THEN t.tokens_completion ELSE 0 END), 0) AS win_tout
         FROM (
             SELECT m.chat_id, m.model_id, mo.display_name,
                    mo.price_in_eur_mtok AS pin, mo.price_out_eur_mtok AS pout,
                    m.tokens_prompt, m.tokens_completion,
                    ROW_NUMBER() OVER (PARTITION BY m.chat_id, m.model_id ORDER BY m.id) AS rn
             FROM bt_messages m
             JOIN bt_models mo ON mo.id = m.model_id
             WHERE m.sender = 'ai' AND m.media_file IS NULL AND m.media_type IS NULL
         ) t
         GROUP BY t.model_id, t.display_name, t.pin, t.pout
         ORDER BY total_cnt DESC, t.display_name"
    )->fetchAll();
    foreach ($mrows as $r) {
        $total = (int) $r['total_cnt'];
        $winCnt = (int) $r['win_cnt'];
        $per50 = null;
        if ($total >= 50 && $winCnt > 0) {
            $winCost = (int) $r['win_tin'] / 1000000 * (float) $r['pin']
                     + (int) $r['win_tout'] / 1000000 * (float) $r['pout'];
            $per50 = round($winCost / $winCnt * 50, 2);
        }
        $modelUsageTotals[] = [
            'name' => $r['display_name'],
            'count' => $total,
            'tokens_in' => (int) $r['total_tin'],
            'tokens_out' => (int) $r['total_tout'],
            'tokens' => (int) $r['total_tin'] + (int) $r['total_tout'],
            'win' => $winCnt,
            'per50' => $per50,
        ];
    }
}

// Stand der täglich per Cron (04:00, /etc/cron.d/bt-token-baselines) neu berechneten Nutzerdaten.
// Wird an zwei Stellen angezeigt (Modell-Nutzung gesamt + Modell-Liste), deshalb einmal zentral.
// $blStale = der Lauf ist überfällig (>26 h) → dann stimmt am Cron etwas nicht.
$blRaw   = $pdo->query("SELECT setting_value FROM bt_settings WHERE setting_key='token_baselines'")->fetchColumn();
$blData  = $blRaw ? json_decode($blRaw, true) : null;
$blTs    = isset($blData['ts']) ? (int) $blData['ts'] : 0;
$blWhen  = $blTs ? date('d.m.Y H:i', $blTs) : '—';
$blN     = isset($blData['models']) ? count($blData['models']) : 0;
$blStale = $blTs > 0 && (time() - $blTs) > 26 * 3600;
$blAgeH  = $blTs ? (int) floor((time() - $blTs) / 3600) : 0;

// Pro Chat: welche Chat-Modelle wurden für NACHRICHTEN genutzt (+ Preis pro 50 Nachrichten).
// Berechnungsfenster: pro Chat+Modell alle Nachrichten ab Nr. 11 (die ersten 10 werden ignoriert, da der
// Kontext dort noch klein/günstig ist). Text-Kosten aus den Tokens (nicht aus cost_eur — dort können nachträgliche
// TTS-Kosten auf einer Textnachricht liegen). Medien (Bild/Video/TTS) zählen NICHT als Nachricht.
$chatModelUsage = [];
if ($isChatTab && !$viewChatId && $allChats) {
    $ids = implode(',', array_map(fn($c) => (int) $c['id'], $allChats));
    // Fenster PRO MODELL: die ersten 10 Nachrichten dieses Modells ignorieren, dann ab Nr. 11 alles werten.
    // (Pro Modell — sonst würde bei Mehr-Modell-Chats ein Modell leer ausgehen, wenn die Chat-Positionen
    //  ab Nr. 11 zufällig alle vom anderen Modell stammen.)
    $mrows = $pdo->query(
        "SELECT t.chat_id, t.display_name, t.pin, t.pout,
                COUNT(*) AS total_cnt,
                SUM(CASE WHEN t.rn >= 11 THEN 1 ELSE 0 END) AS win_cnt,
                SUM(CASE WHEN t.rn >= 11 THEN t.tokens_prompt ELSE 0 END) AS win_tin,
                SUM(CASE WHEN t.rn >= 11 THEN t.tokens_completion ELSE 0 END) AS win_tout
         FROM (
             SELECT m.chat_id, m.model_id, mo.display_name, mo.price_in_eur_mtok AS pin, mo.price_out_eur_mtok AS pout,
                    m.tokens_prompt, m.tokens_completion,
                    ROW_NUMBER() OVER (PARTITION BY m.chat_id, m.model_id ORDER BY m.id) AS rn
             FROM bt_messages m JOIN bt_models mo ON mo.id = m.model_id
             WHERE m.chat_id IN ($ids) AND m.sender = 'ai' AND m.media_file IS NULL AND m.media_type IS NULL
         ) t
         GROUP BY t.chat_id, t.model_id, t.display_name, t.pin, t.pout"
    )->fetchAll();
    foreach ($mrows as $r) {
        $total = (int) $r['total_cnt'];
        if ($total < 1) continue;
        $winCnt = (int) $r['win_cnt'];
        $per50 = null;
        if ($winCnt > 0) {
            $winCost = (int) $r['win_tin'] / 1000000 * (float) $r['pin'] + (int) $r['win_tout'] / 1000000 * (float) $r['pout'];
            $per50 = round($winCost / $winCnt * 50, 2);
        }
        $chatModelUsage[(int) $r['chat_id']][] = [
            'name' => $r['display_name'],
            'count' => $total,
            'win' => $winCnt,
            'per50' => $per50,
        ];
    }
}

// ===== Betas-Tab: Test-Modelle + Feedback der User =====
$betaModels = [];
$betaFeedback = []; // model_id => [user_id => ['username'=>, 'items'=>[...]]]
if ($tab === 'betas') {
    $betaModels = $pdo->query(
        "SELECT m.id, m.display_name, m.is_test, m.test_discount_pct
         FROM bt_models m
         WHERE m.is_test = 1 OR EXISTS(SELECT 1 FROM bt_model_feedback f WHERE f.model_id = m.id)
         ORDER BY m.is_test DESC, m.sort_order, m.display_name"
    )->fetchAll();
    $fb = $pdo->query(
        "SELECT f.model_id, f.user_id, u.username, f.q_story, f.q_context, f.q_continue, f.comment, f.created_at
         FROM bt_model_feedback f JOIN users u ON u.id = f.user_id
         ORDER BY f.created_at DESC"
    )->fetchAll();
    foreach ($fb as $row) {
        $mid = (int) $row['model_id'];
        $uid = (int) $row['user_id'];
        if (!isset($betaFeedback[$mid][$uid])) $betaFeedback[$mid][$uid] = ['username' => $row['username'], 'items' => []];
        $betaFeedback[$mid][$uid]['items'][] = [
            'story' => (int) $row['q_story'], 'context' => (int) $row['q_context'], 'continue' => (int) $row['q_continue'],
            'comment' => $row['comment'], 'at' => $row['created_at'],
        ];
    }
}

$providers = $pdo->query("SELECT * FROM bt_providers ORDER BY id")->fetchAll();

// Key-Matrix: welcher Nutzer hat bei welchem Anbieter einen eigenen Key?
$keyUsers = $pdo->query(
    "SELECT u.id, u.username, COALESCE(p.credit_balance, 0) AS credit_balance
     FROM users u JOIN bt_profiles p ON p.user_id = u.id
     WHERE u.is_active = 1 ORDER BY u.username"
)->fetchAll();
$userKeys = [];   // [user_id][provider_id] => Zeile
foreach ($pdo->query("SELECT * FROM bt_user_api_keys")->fetchAll() as $k) {
    $userKeys[(int) $k['user_id']][(int) $k['provider_id']] = $k;
}
$models = $pdo->query(
    "SELECT m.*, p.display_name AS provider_name FROM bt_models m JOIN bt_providers p ON p.id = m.provider_id ORDER BY m.sort_order"
)->fetchAll();
$suggestedChars = $pdo->query(
    "SELECT c.id, c.first_name, c.last_name, c.moods, c.image_file, u.username
     FROM bt_characters c JOIN users u ON u.id = c.owner_user_id
     WHERE c.is_suggested = 1 AND c.status = 'active' ORDER BY c.id DESC"
)->fetchAll();

$generatorSelect =
    "SELECT g.*, p.display_name AS key_provider_name, p.slug AS key_provider_slug,
            p.enabled AS key_provider_enabled,
            (p.api_key IS NOT NULL AND p.api_key <> '') AS has_central_key
     FROM bt_generators g LEFT JOIN bt_providers p ON p.id = g.provider_id";
$imageGens = $pdo->query($generatorSelect . " WHERE g.kind='image' ORDER BY g.sort_order, g.id")->fetchAll();
$videoGens = $pdo->query($generatorSelect . " WHERE g.kind='video' ORDER BY g.sort_order, g.id")->fetchAll();
$ttsGens = $pdo->query($generatorSelect . " WHERE g.kind='tts' ORDER BY g.sort_order, g.id")->fetchAll();
$openRouterRoutes = [];
foreach ($pdo->query("SELECT * FROM bt_openrouter_routes ORDER BY provider_id, sort_order, id") as $route) {
    $openRouterRoutes[(int) $route['provider_id']][] = $route;
}
$chatModelsForAssign = $pdo->query(
    "SELECT m.id, m.display_name, p.display_name AS provider_name
     FROM bt_models m JOIN bt_providers p ON p.id = m.provider_id ORDER BY m.sort_order"
)->fetchAll();
// Junction als Lookup: $modelGenMap[model_id][generator_id] = true (viele-zu-viele)
$modelGenMap = [];
foreach ($pdo->query("SELECT model_id, generator_id FROM bt_model_generators") as $mg) {
    $modelGenMap[(int) $mg['model_id']][(int) $mg['generator_id']] = true;
}

$allActiveChars = $pdo->query(
    "SELECT c.id, c.first_name, c.last_name, c.is_nsfw, c.is_public, c.is_suggested, u.username
     FROM bt_characters c JOIN users u ON u.id = c.owner_user_id
     WHERE c.status = 'active' ORDER BY c.is_nsfw DESC, c.is_suggested DESC, c.id DESC LIMIT 500"
)->fetchAll();

$moodTags = $pdo->query("SELECT * FROM bt_tags WHERE kind='mood' ORDER BY name")->fetchAll();
$genreTags = $pdo->query("SELECT * FROM bt_tags WHERE kind='genre' ORDER BY name")->fetchAll();

function bt_admin_setting($pdo, $key, $default) {
    $stmt = $pdo->prepare("SELECT setting_value FROM bt_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false ? $default : $v;
}
$nsfwEnabled = bt_admin_setting($pdo, 'nsfw_module_enabled', '1') === '1';
$dailyWarn = bt_admin_setting($pdo, 'daily_warning_eur', '5.00');
$creatorModelId = (int) bt_admin_setting($pdo, 'creator_model_id', '0');
$creatorModelNsfwId = (int) bt_admin_setting($pdo, 'creator_model_nsfw_id', '0');
$creatorModels = $pdo->query("SELECT id, display_name, nsfw_allowed FROM bt_models WHERE enabled = 1 ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
$creatorImageGenId = (int) bt_admin_setting($pdo, 'creator_image_generator_id', '0');
$creatorImageGenNsfwId = (int) bt_admin_setting($pdo, 'creator_image_generator_nsfw_id', '0');
$aivsaiSecondModelId = (int) bt_admin_setting($pdo, 'aivsai_second_model_id', '0');
// Video-Bild-Pipeline (global)
$videoImgGenId    = (int) bt_admin_setting($pdo, 'video_image_generator_id', '0');
$videoPromptModelId = (int) bt_admin_setting($pdo, 'video_prompt_model_id', '0');
$videoI2vGenId    = (int) bt_admin_setting($pdo, 'video_i2v_generator_id', '0');
// Bild-Pipeline (eigenständig, eigene Text-KI)
$imageeditPromptModelId = (int) bt_admin_setting($pdo, 'imageedit_prompt_model_id', '0');
$imageeditGenId   = (int) bt_admin_setting($pdo, 'imageedit_generator_id', '0');
$tutTitle = bt_admin_setting($pdo, 'tutorial_title', '');
$tutSubtitle = bt_admin_setting($pdo, 'tutorial_subtitle', '');
$tutText = bt_admin_setting($pdo, 'tutorial_text', '');
$tutLogo = bt_admin_setting($pdo, 'tutorial_logo', '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($site_name); ?> - BEYOND TELLING Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <style>
        .admin-container { padding-top: 100px; min-height: 100vh; padding-bottom: 60px; }
        /* ===== Sidebar-Layout: Kategorien links, Inhalt rechts ===== */
        .bt-layout { display: flex; gap: 24px; align-items: flex-start; }
        .bt-sidebar {
            width: 232px; flex-shrink: 0; position: sticky; top: 100px;
            background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 16px;
            padding: 14px 12px; backdrop-filter: blur(8px);
        }
        .bt-nav-group { margin-bottom: 14px; }
        .bt-nav-group:last-child { margin-bottom: 0; }
        .bt-nav-group-title {
            font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
            color: var(--text-muted); padding: 4px 10px 6px;
        }
        .bt-nav-link {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            padding: 8px 12px; margin-bottom: 2px; border-radius: 10px; text-decoration: none;
            font-size: .88rem; color: var(--text-secondary); border: 1px solid transparent;
        }
        .bt-nav-link:hover { background: rgba(255,255,255,0.06); color: var(--text-primary); }
        .bt-nav-link.active { background: linear-gradient(135deg, #9147FF, #772ce8); color: #fff; }
        .bt-content { flex: 1; min-width: 0; }
        .bt-crumb { color: var(--text-muted); font-size: .82rem; margin-bottom: 14px; }
        .bt-crumb b { color: var(--text-secondary); font-weight: 600; }
        /* Mobil: Sidebar über dem Inhalt, Gruppen kompakt umbrechend */
        @media (max-width: 900px) {
            .bt-layout { flex-direction: column; }
            .bt-sidebar { position: static; width: 100%; display: flex; flex-wrap: wrap; gap: 12px; }
            .bt-nav-group { margin-bottom: 0; min-width: 150px; flex: 1; }
            .bt-content { width: 100%; }
        }
        .bt-badge { background: var(--accent-color); color: #fff; border-radius: 10px; padding: 0 7px; font-size: .75rem; }
        .bt-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 16px; padding: 22px; margin-bottom: 22px; backdrop-filter: blur(8px); overflow-x: auto; }
        /* ===== Block-Layout: ein Eintrag (Modell/Generator/Anbieter) = eine Box mit beschrifteten Feldern ===== */
        .bt-block { border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; background: rgba(0,0,0,0.18); }
        .bt-block-head { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .bt-block-title { font-weight: 700; font-size: .98rem; }
        .bt-block-title .muted { font-weight: 400; }
        .bt-fields { display: flex; flex-wrap: wrap; gap: 10px 14px; }
        .bt-field { display: flex; flex-direction: column; gap: 4px; flex: 1 1 150px; min-width: 0; }
        .bt-field.sm { flex: 0 1 110px; }
        .bt-field.md { flex: 1 1 190px; }
        .bt-field.lg { flex: 2 1 280px; }
        .bt-field.full { flex: 1 1 100%; }
        .bt-field > span { font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); }
        .bt-field .bt-input, .bt-field .bt-select { width: 100%; }
        .bt-checks { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-top: 12px; }
        .bt-checks label { display: inline-flex; align-items: center; gap: 7px; font-size: .85rem; color: var(--text-secondary); cursor: pointer; }
        .bt-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 12px; }
        .bt-subhead { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin: 20px 0 10px; padding-bottom: 6px; border-bottom: 1px solid var(--border-color); }
        .bt-card .bt-subhead:first-of-type { margin-top: 4px; }
        .bt-assign { padding: 12px; background: rgba(0,0,0,0.25); border-radius: 10px; margin-top: 10px; }
        .bt-card h3 { margin-bottom: 14px; }
        table.bt { width: 100%; border-collapse: collapse; font-size: .85rem; }
        table.bt th, table.bt td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        table.bt th { color: var(--text-muted); font-weight: 600; }
        .bt-input, .bt-select, .bt-textarea {
            width: 100%; padding: 8px 10px; background: rgba(0,0,0,0.35); color: var(--text-primary);
            border: 1px solid var(--border-color); border-radius: 8px; font: inherit; font-size: .85rem;
        }
        .bt-btn {
            padding: 8px 16px; border: none; border-radius: 10px; cursor: pointer; font: inherit; font-size: .85rem; font-weight: 600;
            background: linear-gradient(135deg, #9147FF, #772ce8); color: #fff;
        }
        .bt-btn.secondary { background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-primary); }
        .bt-btn.danger { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.4); }
        .bt-msg { padding: 12px 16px; border-radius: 12px; margin-bottom: 18px; }
        .bt-msg.ok { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.4); color: #4ade80; }
        .bt-msg.err { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.4); color: #f87171; }
        .bt-inline-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .bt-inline-form .bt-input, .bt-inline-form .bt-select { width: auto; min-width: 120px; }
        details.bt-user { margin-bottom: 8px; }
        details.bt-user summary { cursor: pointer; padding: 8px 4px; }
        .bt-chatmsg { padding: 10px 14px; border-radius: 12px; margin-bottom: 8px; max-width: 85%; font-size: .88rem; white-space: pre-wrap; }
        .bt-chatmsg.user { background: rgba(145,71,255,0.2); margin-left: auto; }
        .bt-chatmsg.ai { background: rgba(255,255,255,0.07); }
        .bt-chatmsg.direction { background: rgba(255,107,53,0.12); border: 1px dashed rgba(255,107,53,0.5); margin: 0 auto 8px; text-align: center; }
        .bt-chatmsg .meta { color: var(--text-muted); font-size: .68rem; margin-top: 4px; }
        pre.bt-template {
            background: rgba(0,0,0,0.4); padding: 16px; border-radius: 12px; overflow-x: auto;
            border: 1px solid var(--border-color); font-size: .82rem;
        }
        .muted { color: var(--text-muted); font-size: .8rem; }
        .bt-tagchip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 8px 6px 14px; border-radius: 20px; background: var(--glass-bg); border: 1px solid var(--glass-border); font-size: .88rem; }
        .bt-tagchip.inactive { opacity: .45; }
        .bt-tagchip form { margin: 0; }
        .bt-tagchip button { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: .82rem; padding: 2px 3px; line-height: 1; }
        .bt-tagchip button:hover { color: #f87171; }
    </style>
</head>
<body>
    <div class="animated-bg"></div>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo"><?php echo e($site_name); ?> - Admin</div>
                <nav class="nav">
                    <a href="../dashboard.php" class="nav-btn">🏠 Dashboard</a>
                    <a href="index.php" class="nav-btn admin">📊 Übersicht</a>
                    <a href="users.php" class="nav-btn admin">👥 Benutzer</a>
                    <a href="beyond-telling.php" class="nav-btn admin">📖 BEYOND TELLING</a>
                    <a href="settings.php" class="nav-btn admin">⚙️ Einstellungen</a>
                    <a href="../?action=logout" class="nav-btn logout">🚪 Logout</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="admin-container">
        <div class="container">
            <h1 style="margin-bottom: 20px;">📖 BEYOND TELLING — App-Verwaltung</h1>

            <?php if ($msg): ?><div class="bt-msg ok"><?php echo e($msg); ?></div><?php endif; ?>
            <?php if ($err): ?><div class="bt-msg err"><?php echo e($err); ?></div><?php endif; ?>

            <div class="bt-layout">
                <nav class="bt-sidebar">
                    <?php foreach ($navGroups as $groupTitle => $groupTabs): ?>
                    <div class="bt-nav-group">
                        <div class="bt-nav-group-title"><?php echo e($groupTitle); ?></div>
                        <?php foreach ($groupTabs as $tabKey => $tabLabel): ?>
                        <a class="bt-nav-link <?php echo $tab === $tabKey ? 'active' : ''; ?>" href="?tab=<?php echo $tabKey; ?>">
                            <span><?php echo e($tabLabel); ?></span>
                            <?php if ($tabKey === 'users' && $pendingCount): ?><span class="bt-badge" title="Offene Passwort-Reset-Anfragen"><?php echo $pendingCount; ?></span><?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </nav>
                <div class="bt-content">
                    <?php
                    // Brotkrume: Kategorie → aktuelle Seite
                    if ($tab === 'sc') {
                        // Nicht verlinkte Seite → keine Kategorie, aber ein klarer Hinweis, wo man ist
                        echo '<div class="bt-crumb">👥 Nutzer → <b>Chats (alle einsehbar)</b></div>';
                    } else {
                        foreach ($navGroups as $groupTitle => $groupTabs) {
                            if (isset($groupTabs[$tab])) {
                                echo '<div class="bt-crumb">' . e($groupTitle) . ' → <b>' . e($groupTabs[$tab]) . '</b></div>';
                                break;
                            }
                        }
                    }
                    ?>

            <?php if ($tab === 'users'): ?>
            <?php if ($pendingResets): ?>
            <div class="bt-card">
                <h3>🔑 Offene Passwort-Reset-Anfragen (<?php echo $pendingCount; ?>)</h3>
                <table class="bt">
                    <tr><th>User</th><th>E-Mail</th><th>Angefragt</th><th>Aktion</th></tr>
                    <?php foreach ($pendingResets as $r): ?>
                    <tr>
                        <td><?php echo e($r['username']); ?></td>
                        <td><?php echo e($r['email'] ?: '—'); ?></td>
                        <td><?php echo e($r['requested_at']); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="bt_action" value="reset_decide">
                                <input type="hidden" name="reset_id" value="<?php echo (int) $r['id']; ?>">
                                <button class="bt-btn" name="decision" value="approve" type="submit">Freigeben</button>
                                <button class="bt-btn danger" name="decision" value="reject" type="submit">Ablehnen</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <p class="muted" style="margin-top: 12px;">Nach der Freigabe wird der User beim nächsten Login-Versuch — unabhängig vom eingegebenen Passwort — zwingend auf „Neues Passwort setzen“ geleitet.</p>
            </div>
            <?php endif; ?>

            <div class="bt-card">
                <h3>App-Profile (<?php echo count($appUsers); ?>)</h3>
                <?php foreach ($appUsers as $u): ?>
                <details class="bt-user">
                    <summary>
                        <strong><?php echo e($u['username']); ?></strong>
                        <span class="muted">
                            · <?php echo e($u['email'] ?: '—'); ?>
                            · Rolle: <?php echo e($u['role']); ?>
                            · <?php echo (int) $u['chat_count']; ?> Chats
                            · <?php echo number_format((float) $u['total_cost'], 2, ',', '.'); ?> $
                            <?php if (!$u['is_active']): ?> · <span style="color:#f87171">deaktiviert</span><?php endif; ?>
                            <?php if ($u['must_reset_password']): ?> · <span style="color:#fbbf24">Reset ausstehend</span><?php endif; ?>
                        </span>
                    </summary>
                    <form method="post" class="bt-inline-form" style="padding: 10px 4px;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="user_update">
                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                        <input class="bt-input" type="text" name="username" value="<?php echo e($u['username']); ?>" title="Username">
                        <input class="bt-input" type="email" name="email" value="<?php echo e($u['email']); ?>" placeholder="E-Mail" title="E-Mail">
                        <input class="bt-input" type="text" name="new_password" placeholder="Neues Passwort (leer = unverändert)">
                        <select class="bt-select" name="is_active">
                            <option value="1" <?php echo $u['is_active'] ? 'selected' : ''; ?>>aktiv</option>
                            <option value="0" <?php echo !$u['is_active'] ? 'selected' : ''; ?>>deaktiviert</option>
                        </select>
                        <button class="bt-btn" type="submit">Speichern</button>
                    </form>
                    <form method="post" style="padding: 0 4px 10px;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="force_reset">
                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                        <button class="bt-btn secondary" type="submit">Passwort-Reset erzwingen</button>
                        <span class="muted">Geb.: <?php echo e($u['birthdate'] ?: '—'); ?> · NSFW: <?php echo $u['nsfw_enabled'] ? 'an' : 'aus'; ?> · Twitch: <?php echo e($u['twitch_username'] ?: '—'); ?> · Letzter Login: <?php echo e($u['last_login'] ?: '—'); ?></span>
                    </form>
                    <form method="post" style="padding: 0 4px 12px;" onsubmit="return confirm('Diesen Nutzer wirklich aus der App abmelden? Er muss sich danach neu anmelden.');">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="force_logout">
                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                        <button class="bt-btn secondary" type="submit">🚪 App-Abmeldung erzwingen</button>
                        <span class="muted">Beendet sofort alle App-Sitzungen dieses Nutzers.</span>
                    </form>
                </details>
                <?php endforeach; ?>
                <?php if (!$appUsers): ?><p class="muted">Noch keine App-User.</p><?php endif; ?>
            </div>

            <div class="bt-card">
                <h3>💰 Guthaben (<?php echo count($creditUsers); ?> User)</h3>
                <p class="muted" style="margin-bottom:10px;">Jeder neue User erhält 1&nbsp;$ Startguthaben. Ist das Guthaben ≤&nbsp;0&nbsp;$, werden KI-Anfragen (Chat/TTS/Bild/Video) blockiert — nur der „Free"-Chat bleibt nutzbar.</p>
                <table class="bt">
                    <tr><th>User</th><th>Guthaben</th><th>Verbraucht (gesamt)</th><th>Guthaben ändern ($)</th></tr>
                    <?php foreach ($creditUsers as $cu): $bal = (float) $cu['credit_balance']; ?>
                    <tr>
                        <td><?php echo e($cu['username']); ?><?php if ($cu['email']): ?> <span class="muted" style="font-size:.8rem;">(<?php echo e($cu['email']); ?>)</span><?php endif; ?></td>
                        <td><strong style="color:<?php echo $bal <= 0 ? '#ff6b6b' : '#7ee787'; ?>;"><?php echo number_format($bal, 2, ',', '.'); ?> $</strong></td>
                        <td><?php echo number_format((float) $cu['spent'], 4, ',', '.'); ?> $</td>
                        <td>
                            <form method="post" style="display:flex; gap:6px; align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="bt_action" value="credit_adjust">
                                <input type="hidden" name="user_id" value="<?php echo (int) $cu['id']; ?>">
                                <input class="bt-input" type="text" name="amount" placeholder="z. B. 5 oder -2" style="width:110px;">
                                <button class="bt-btn" type="submit">Übernehmen</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (!$creditUsers): ?><p class="muted">Noch keine User.</p><?php endif; ?>
            </div>

            <?php elseif ($isChatTab): ?>
                <?php if ($chatInfo):
                    $kind = $chatInfo['kind'] ?? 'character';
                    $partner = trim(($chatInfo['first_name'] ?? '') . ' ' . ($chatInfo['last_name'] ?? ''));
                    $uniName = trim((string) ($chatInfo['universe_name'] ?? ''));
                    $kindLabel = $kind === 'conversation' ? '💬 Konversation'
                        : ($kind === 'aivsai' ? '🤖 AI vs AI'
                        : ($kind === 'universe' ? '🌌 ' . ($uniName !== '' ? $uniName : 'Universum')
                        : ($partner !== '' ? $partner : 'Chat')));
                ?>
                <div class="bt-card">
                    <p><a href="?tab=<?php echo e($tab); ?>" style="color: #9147FF;">‹ Alle Chats</a></p>
                    <h3>Chat #<?php echo (int) $chatInfo['id']; ?>: <?php echo e($chatInfo['username']); ?> ↔ <?php echo e($kindLabel); ?></h3>
                    <p class="muted">
                        Art: <?php echo e($kind); ?> · <?php echo number_format((float) $chatInfo['total_cost_eur'], 4, ',', '.'); ?> $ gesamt · <?php echo (int) $chatInfo['context_tokens']; ?> Kontext-Tokens
                        · <?php echo count(array_filter($chatMessages, fn($m) => $m['sender'] === 'ai')); ?> KI-Nachrichten<?php if ($chatErrors): ?> · <span style="color:#ff6b6b;"><?php echo count($chatErrors); ?> Fehler</span><?php endif; ?>
                    </p>

                    <?php if ($chatErrors): ?>
                    <div style="margin:14px 0;padding:10px 12px;border:1px solid #5a2b2b;background:#2a1414;border-radius:10px;">
                        <h4 style="margin:0 0 8px;color:#ff8a8a;">⚠️ Fehlerlog — abgebrochene KI-Anfragen (<?php echo count($chatErrors); ?>)</h4>
                        <?php foreach ($chatErrors as $er): ?>
                        <div style="font-size:.82rem;padding:6px 0;border-top:1px solid #3a1e1e;">
                            <span class="muted"><?php echo e($er['created_at']); ?></span>
                            · <code><?php echo e($er['action']); ?></code>
                            <?php if ($er['model_name']): ?> · <?php echo e($er['model_name']); ?><?php endif; ?>
                            <?php if ($er['user_name']): ?> · <?php echo e($er['user_name']); ?><?php endif; ?>
                            <div style="color:#ffb3b3;margin-top:2px;"><?php echo e($er['error_message']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top: 16px;">
                        <?php foreach ($chatMessages as $m):
                            $sender = $m['sender'];
                            $isDir = ($sender === 'user' && $m['mode'] === 'direction') || ($sender === 'system' && ($m['mode'] ?? '') === 'direction');
                            $cls = $sender === 'system' ? 'direction' : ($sender === 'user' ? ($m['mode'] === 'direction' ? 'direction' : 'user') : 'ai');
                            $hasMedia = !empty($m['media_file']);
                            $mtype = $m['media_type'] ?? '';
                            $mediaUrl = '?tab=' . rawurlencode($tab) . '&chat=' . (int) $chatInfo['id'] . '&media=' . (int) $m['id'];
                        ?>
                        <div class="bt-chatmsg <?php echo $cls; ?>">
                            <?php
                                // Autoren-/Seiten-Kennung (geteilte Chats, Konversation, AI-vs-AI)
                                $who = [];
                                if ($sender === 'ai' && !empty($m['ai_side'])) $who[] = 'Seite ' . strtoupper($m['ai_side']);
                                if (!empty($m['author_name'])) $who[] = $m['author_name'];
                                if ($sender === 'system') $who[] = 'System';
                            ?>
                            <?php if ($who): ?><div style="font-weight:600;font-size:.75rem;opacity:.7;margin-bottom:3px;"><?php echo e(implode(' · ', $who)); ?></div><?php endif; ?>

                            <?php if ($hasMedia && $mtype === 'video'): ?>
                                <video controls preload="metadata" style="max-width:280px;max-height:360px;border-radius:10px;display:block;" src="<?php echo e($mediaUrl); ?>"></video>
                            <?php elseif ($hasMedia): ?>
                                <a href="<?php echo e($mediaUrl); ?>" target="_blank"><img loading="lazy" style="max-width:280px;max-height:360px;border-radius:10px;display:block;" src="<?php echo e($mediaUrl); ?>" alt="Medium"></a>
                            <?php elseif ($mtype === 'video' && ($m['media_status'] ?? '') === 'pending'): ?>
                                <em class="muted">🎬 Video wird erstellt … (noch nicht fertig)</em>
                            <?php elseif ($mtype === 'video' && ($m['media_status'] ?? '') === 'failed'): ?>
                                <em class="muted">🎬 Video fehlgeschlagen</em>
                            <?php endif; ?>

                            <?php if (trim((string) $m['content']) !== ''): ?><div><?php echo nl2br(e($m['content'])); ?></div><?php endif; ?>

                            <div class="meta">
                                <?php echo e($m['created_at']); ?>
                                <?php if ($sender === 'ai' && $m['model_name']): ?> · <strong><?php echo e($m['model_name']); ?></strong><?php endif; ?>
                                <?php if ($sender === 'ai' && ((int) $m['tokens_prompt'] + (int) $m['tokens_completion']) > 0): ?> · <span title="Input (Kontext) / Output (Antwort)"><?php echo (int) $m['tokens_prompt']; ?> / <?php echo (int) $m['tokens_completion']; ?> Tok</span><?php endif; ?>
                                <?php if ((float) $m['cost_eur'] > 0): ?> · <?php echo number_format((float) $m['cost_eur'], 4, ',', '.'); ?> $<?php endif; ?>
                                <?php if (!empty($m['tts_file'])): ?> · 🔊 Audio<?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$chatMessages): ?><p class="muted">Keine Nachrichten.</p><?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="bt-card">
                    <h3>Modell-Nutzung gesamt</h3>
                    <p class="muted" style="margin-bottom:10px;">KI-Textantworten und Tokens über alle Chats. Bilder, Videos und TTS sind nicht enthalten.</p>
                    <p class="muted" style="margin-bottom:10px; font-size:.82rem; line-height:1.5;">
                        🕒 Diese Tabelle wird bei <b>jedem Aufruf frisch</b> aus den Nachrichten berechnet — Stand also immer jetzt
                        (<?php echo date('d.m.Y H:i'); ?>), kein Zwischenspeicher.
                        Getrennt davon laufen die <b>Nutzerdaten für die „$/50"-Schätzung in der App</b>: die werden
                        <b>täglich um 04:00 Uhr</b> per Cron neu berechnet · zuletzt:
                        <b<?php echo $blStale ? ' style="color:#ef4d63;"' : ''; ?>><?php echo e($blWhen); ?></b>
                        (<?php echo (int) $blN; ?> Modelle mit Daten)<?php if ($blStale): ?> — ⚠️ überfällig, seit <?php echo (int) $blAgeH; ?> h keine Aktualisierung<?php endif; ?>.
                    </p>
                    <table class="bt">
                        <tr><th>Modell</th><th>Nachrichten gesamt</th><th>Tokens gesamt</th><th>Preis pro 50 Nachrichten</th></tr>
                        <?php foreach ($modelUsageTotals as $mt): ?>
                        <tr>
                            <td><strong><?php echo e($mt['name']); ?></strong></td>
                            <td><?php echo number_format((int) $mt['count'], 0, ',', '.'); ?></td>
                            <td>
                                <?php echo number_format((int) $mt['tokens'], 0, ',', '.'); ?>
                                <span class="muted" style="display:block;font-size:.78rem;">
                                    <?php echo number_format((int) $mt['tokens_in'], 0, ',', '.'); ?> In ·
                                    <?php echo number_format((int) $mt['tokens_out'], 0, ',', '.'); ?> Out
                                </span>
                            </td>
                            <td>
                                <?php if ((int) $mt['count'] < 50): ?>
                                    <span class="muted">zu wenig Daten (<?php echo (int) $mt['count']; ?>/50)</span>
                                <?php elseif ($mt['per50'] === null): ?>
                                    <span class="muted">nur Anfangsnachrichten</span>
                                <?php else: ?>
                                    <strong style="color:#c79bef;"><?php echo number_format((float) $mt['per50'], 2, ',', '.'); ?> $ / 50</strong>
                                    <span class="muted" style="display:block;font-size:.78rem;"><?php echo number_format((int) $mt['win'], 0, ',', '.'); ?> Nachrichten gewertet</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php if (!$modelUsageTotals): ?><p class="muted">Noch keine Modell-Nutzung vorhanden.</p><?php endif; ?>
                    <p class="muted" style="margin-top:10px;font-size:.8rem;">Preis pro 50 Nachrichten = reine Text-Kosten. Berechnet aus allen Nachrichten ab Nr. 11 je Chat und Modell (nur die ersten 10 werden ignoriert) und den dabei tatsächlich verbrauchten Tokens. Anzeige ab 50 Nachrichten pro Modell.</p>
                </div>

                <div class="bt-card">
                    <h3>Alle Chats (<?php echo count($allChats); ?>)</h3>
                    <table class="bt">
                        <tr><th>User</th><th>Chat</th><th>KI-Nachrichten</th><th>Fehler</th><th>Kontext-Tokens</th><th>Kosten</th><th>Modelle</th><th>Letzte Aktivität</th><th></th></tr>
                        <?php foreach ($allChats as $c):
                            $ck = $c['kind'] ?? 'character';
                            $cp = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                            $un = trim((string) ($c['universe_name'] ?? ''));
                            $ctitle = $ck === 'conversation' ? '💬 Konversation'
                                : ($ck === 'aivsai' ? '🤖 AI vs AI'
                                : ($ck === 'universe' ? '🌌 ' . ($un !== '' ? $un : 'Universum')
                                : ($cp !== '' ? $cp : '—')));
                            $canRead = $mayReadChat($c['username'] ?? null);
                        ?>
                        <tr>
                            <td><?php echo e($c['username']); ?></td>
                            <td><?php echo e($ctitle); ?></td>
                            <td><?php echo (int) $c['msg_count']; ?></td>
                            <td><?php echo (int) $c['err_count'] > 0 ? '<span style="color:#ff6b6b;">' . (int) $c['err_count'] . '</span>' : '–'; ?></td>
                            <td><?php echo number_format((int) $c['context_tokens'], 0, ',', '.'); ?></td>
                            <td><?php echo number_format((float) $c['total_cost_eur'], 4, ',', '.'); ?> $</td>
                            <td><?php $mu = $chatModelUsage[(int) $c['id']] ?? [];
                                if ($mu): ?>
                                <button type="button" class="bt-models-btn" data-title="<?php echo e($ctitle . ' — ' . $c['username']); ?>" data-total="<?php echo (int) $c['msg_count']; ?>" data-models='<?php echo e(json_encode($mu, JSON_UNESCAPED_UNICODE)); ?>'><?php echo count($mu); ?> <?php echo count($mu) === 1 ? 'Modell' : 'Modelle'; ?></button>
                                <?php else: ?>–<?php endif; ?></td>
                            <td><?php echo e($c['last_message_at'] ?: $c['created_at']); ?></td>
                            <td><?php if ($canRead): ?><a href="?tab=<?php echo e($tab); ?>&chat=<?php echo (int) $c['id']; ?>" style="color:#9147FF;">Einsehen</a><?php else: ?><span class="muted">–</span><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php if (!$allChats): ?><p class="muted">Noch keine Chats.</p><?php endif; ?>
                </div>

                <!-- Popup: genutzte Modelle eines Chats + Preis pro 100 Nachrichten -->
                <div id="bt-models-modal" class="bt-modal-back hidden" onclick="if(event.target===this)this.classList.add('hidden')">
                    <div class="bt-modal">
                        <div class="bt-modal-head"><span id="bt-models-title">Genutzte Modelle</span>
                            <button type="button" class="bt-btn secondary" onclick="document.getElementById('bt-models-modal').classList.add('hidden')">✕</button></div>
                        <div id="bt-models-body"></div>
                        <p class="muted" style="margin-top:10px;font-size:.8rem;">Preis pro 50 Nachrichten = reine Text-Kosten (Bilder/Videos/TTS zählen nicht). Berechnet aus allen Nachrichten ab Nr. 11 (nur die ersten 10 werden ignoriert) und den dabei tatsächlich verbrauchten Tokens. Anzeige ab 50 Nachrichten.</p>
                    </div>
                </div>
                <style>
                    .bt-modal-back { position:fixed; inset:0; background:rgba(0,0,0,.6); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; }
                    .bt-modal-back.hidden { display:none; }
                    .bt-modal { background:#1c1526; border:1px solid #3a2b4d; border-radius:14px; padding:20px 22px; max-width:460px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.5); }
                    .bt-modal-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; font-weight:600; font-size:1.05rem; }
                    .bt-model-row { display:flex; justify-content:space-between; align-items:baseline; gap:12px; padding:9px 0; border-top:1px solid #2e2340; }
                    .bt-model-row:first-child { border-top:0; }
                    .bt-model-row .mm-name { font-weight:600; }
                    .bt-model-row .mm-sub { color:var(--text-muted,#9b8bb4); font-size:.82rem; }
                    .bt-model-row .mm-per100 { color:#c79bef; font-weight:600; white-space:nowrap; }
                    .bt-model-row .mm-per100.muted { color:var(--text-muted,#9b8bb4); font-weight:400; font-size:.82rem; }
                    /* Kompakter Tabellen-Button (dezente Pille statt großer Button) */
                    .bt-models-btn { padding:3px 10px; border-radius:999px; cursor:pointer; font:inherit; font-size:.78rem; font-weight:600;
                        background:rgba(145,71,255,0.14); border:1px solid rgba(145,71,255,0.4); color:#c79bef; white-space:nowrap; }
                    .bt-models-btn:hover { background:rgba(145,71,255,0.28); }
                </style>
                <script>
                (function () {
                    var modal = document.getElementById('bt-models-modal');
                    if (!modal) return;
                    var body = document.getElementById('bt-models-body');
                    var title = document.getElementById('bt-models-title');
                    var euro = function (n) { return Number(n).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $'; };
                    document.querySelectorAll('.bt-models-btn').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var models = [];
                            try { models = JSON.parse(btn.getAttribute('data-models') || '[]'); } catch (e) {}
                            var chatTotal = Number(btn.getAttribute('data-total')) || 0; // KI-Nachrichten des Chats (inkl. Intro)
                            title.textContent = btn.getAttribute('data-title') || 'Genutzte Modelle';
                            // Preis pro 50 erst ab 50 KI-Nachrichten des Chats anzeigen (die ersten 10 gehen nicht in die Rechnung ein)
                            body.innerHTML = models.length ? models.map(function (m) {
                                var priceCell, sub = Number(m.count).toLocaleString('de-DE') + ' Nachrichten';
                                if (chatTotal < 50) {
                                    priceCell = '<div class="mm-per100 muted">zu wenig Daten (' + chatTotal + '/50)</div>';
                                } else if (m.per50 === null) {
                                    priceCell = '<div class="mm-per100 muted">nur Anfangsnachrichten</div>';
                                } else {
                                    priceCell = '<div class="mm-per100">' + euro(m.per50) + ' / 50</div>';
                                    sub += ' · ' + Number(m.win) + ' gewertet (ab Nr. 11)'; // erste 10 ignoriert
                                }
                                return '<div class="bt-model-row"><div><div class="mm-name">' + m.name +
                                    '</div><div class="mm-sub">' + sub + '</div></div>' + priceCell + '</div>';
                            }).join('') : '<p class="muted">Keine Nachrichten-Modelle.</p>';
                            modal.classList.remove('hidden');
                        });
                    });
                })();
                </script>
                <?php endif; ?>

            <?php elseif ($tab === 'ai'): ?>
            <?php echo bt_admin_alert_banner_html($pdo); ?>

            <?php
            // Fehlerliste: Nutzer, die weder einen eigenen Key noch Startguthaben haben — für die
            // steht die App still, bis ein Key eingetragen wird.
            $keyProblems = [];
            foreach ($keyUsers as $ku) {
                if (((float) $ku['credit_balance']) > 0) continue;
                if (!empty($userKeys[(int) $ku['id']])) continue;
                $keyProblems[] = $ku['username'];
            }
            ?>
            <?php if ($keyProblems): ?>
            <div class="bt-msg err" style="margin-bottom:18px;">
                <strong>❌ Kein API-Key und kein Startguthaben:</strong>
                <?php echo e(implode(', ', $keyProblems)); ?>.
                Für diese Nutzer sind alle Modelle in der App rot und gesperrt — bitte unten einen Key eintragen.
            </div>
            <?php endif; ?>

            <div class="bt-card">
                <h3>API-Keys pro Nutzer</h3>
                <p class="muted" style="margin:6px 0 14px;font-size:.86rem;">
                    Jeder Nutzer ruft die KI mit seinem <strong>eigenen</strong> Key auf und zahlt damit selbst — auch in geteilten Chats.
                    Ohne eigenen Key läuft er über den zentralen Key weiter unten, solange sein Startguthaben reicht; danach ist das Modell für ihn gesperrt.
                    Keys werden verschlüsselt gespeichert und lassen sich <strong>nicht mehr anzeigen</strong> — bei Verlust einfach neu eintragen.
                </p>
                <?php foreach ($keyUsers as $ku): $uid = (int) $ku['id']; ?>
                <div class="bt-block">
                    <div class="bt-block-head">
                        <div class="bt-block-title">
                            <?php echo e($ku['username']); ?>
                            <span class="muted">· Startguthaben: <?php echo number_format((float) $ku['credit_balance'], 2, ',', '.'); ?> $</span>
                        </div>
                    </div>
                    <?php foreach ($providers as $p): $pid = (int) $p['id']; $uk = $userKeys[$uid][$pid] ?? null; ?>
                    <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="user_key_save">
                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                        <input type="hidden" name="provider_id" value="<?php echo $pid; ?>">
                        <span style="min-width:150px;"><?php echo e($p['display_name']); ?></span>
                        <span class="muted" style="min-width:110px;font-size:.85rem;">
                            <?php echo $uk ? '✅ ' . e((string) $uk['key_hint']) : '– zentral'; ?>
                        </span>
                        <input class="bt-input" type="password" name="user_api_key" style="flex:1;min-width:200px;"
                               placeholder="<?php echo $uk ? 'gesetzt (leer = unverändert, - = löschen)' : 'eigenen API-Key eintragen'; ?>"
                               autocomplete="new-password">
                        <button class="bt-btn" type="submit">Speichern</button>
                        <?php if ($uk && !empty($uk['credit_exhausted_at'])): ?>
                        <span style="color:#ff9a9a;font-size:.85rem;">⚠️ kein Guthaben seit <?php echo e(bt_db_time_local($pdo, $uk['credit_exhausted_at'])); ?></span>
                        <?php endif; ?>
                    </form>
                    <?php if ($uk && !empty($uk['credit_exhausted_at'])): ?>
                    <form method="post" style="margin:-4px 0 10px;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="credit_clear">
                        <input type="hidden" name="user_key_id" value="<?php echo (int) $uk['id']; ?>">
                        <button class="bt-btn" type="submit">Sperre für <?php echo e($p['display_name']); ?> aufheben</button>
                        <span class="muted" style="font-size:.82rem;"><?php echo e((string) $uk['credit_error_message']); ?></span>
                    </form>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bt-card">
                <h3>Zentrale Anbieter-Keys <span class="muted" style="font-weight:400;font-size:.85rem;">· Rückfall fürs Startguthaben</span></h3>
                <?php foreach ($providers as $p): ?>
                <form method="post" class="bt-block">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="bt_action" value="provider_save">
                    <input type="hidden" name="provider_id" value="<?php echo (int) $p['id']; ?>">
                    <div class="bt-block-head">
                        <div class="bt-block-title"><?php echo e($p['display_name']); ?> <span class="muted">· <?php echo e($p['slug']); ?> · Stil: <?php echo e($p['api_style']); ?> · Key: <?php echo $p['api_key'] ? '✅ gesetzt' : '❌ fehlt'; ?></span></div>
                    </div>
                    <?php if (!empty($p['credit_exhausted_at'])): ?>
                    <div class="bt-block" style="border:1px solid #ff5a5a;background:rgba(255,60,60,.12);border-radius:10px;padding:10px 12px;margin-bottom:10px;">
                        <strong style="color:#ff9a9a;">⚠️ Kein Guthaben</strong>
                        <span class="muted">seit <?php echo e(bt_db_time_local($pdo, $p['credit_exhausted_at'])); ?> Uhr — alle Modelle dieses Anbieters sind in der App rot und gesperrt.</span>
                        <div class="muted" style="font-size:.85em;margin-top:4px;"><?php echo e((string) $p['credit_error_message']); ?></div>
                        <div class="muted" style="font-size:.85em;margin-top:4px;">Hebt sich automatisch auf, sobald wieder ein Aufruf durchläuft (Test alle 15 Min.).</div>
                    </div>
                    <?php endif; ?>
                    <div class="bt-fields">
                        <label class="bt-field lg"><span>API-Base</span><input class="bt-input" type="text" name="api_base" value="<?php echo e($p['api_base']); ?>"></label>
                        <label class="bt-field lg"><span>API-Key</span><input class="bt-input" type="password" name="api_key" placeholder="<?php echo $p['api_key'] ? '••••• gesetzt (leer = unverändert, - = löschen)' : 'API-Key eintragen'; ?>" autocomplete="new-password"></label>
                    </div>
                    <div class="bt-actions">
                        <label class="muted" style="cursor:pointer;"><input type="checkbox" name="enabled" <?php echo $p['enabled'] ? 'checked' : ''; ?>> Aktiv</label>
                        <button class="bt-btn" type="submit">Speichern</button>
                    </div>
                    <?php if (($p['api_style'] ?? '') === 'openrouter' || ($p['slug'] ?? '') === 'openrouter'): ?>
                    <p class="muted" style="margin-top:8px;font-size:.82rem;">
                        Modell-Keys im Format <code>anbieter/modell</code>, z. B. <code>openai/gpt-5.2</code>.
                        <a href="https://openrouter.ai/models" target="_blank" rel="noopener noreferrer" style="color:#c79bef;">OpenRouter-Modellkatalog ↗</a>
                    </p>
                    <?php endif; ?>
                </form>
                <?php if (($p['api_style'] ?? '') === 'openrouter' || ($p['slug'] ?? '') === 'openrouter'): ?>
                <div class="bt-block" style="margin-top:-6px;">
                    <div class="bt-block-title">Bevorzugte OpenRouter-Provider</div>
                    <p class="muted" style="margin:6px 0 10px;font-size:.82rem;">
                        Zuerst wird der aktuell günstigste verfügbare Provider aus dieser Liste genutzt.
                        Ist keiner erreichbar oder unterstützt das Modell nicht, nutzt OpenRouter automatisch
                        den günstigsten allgemeinen Fallback.
                    </p>
                    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:10px;">
                        <?php foreach (($openRouterRoutes[(int) $p['id']] ?? []) as $route): ?>
                        <form method="post" style="display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border:1px solid rgba(199,155,239,.4);border-radius:999px;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="bt_action" value="openrouter_route_delete">
                            <input type="hidden" name="route_id" value="<?php echo (int) $route['id']; ?>">
                            <code><?php echo e($route['provider_slug']); ?></code>
                            <button class="bt-btn danger" type="submit" style="padding:1px 6px;min-height:0;" title="Provider entfernen">✕</button>
                        </form>
                        <?php endforeach; ?>
                        <?php if (empty($openRouterRoutes[(int) $p['id']])): ?>
                            <span class="muted">Noch keine bevorzugten Provider – OpenRouter nutzt alle verfügbaren nach Preis.</span>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="bt-fields" style="align-items:end;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="openrouter_route_add">
                        <input type="hidden" name="provider_id" value="<?php echo (int) $p['id']; ?>">
                        <label class="bt-field md">
                            <span>Provider-Slug</span>
                            <input class="bt-input" type="text" name="provider_slug" placeholder="z. B. digitalocean" maxlength="100" required>
                        </label>
                        <div class="bt-field sm"><button class="bt-btn" type="submit">＋ Hinzufügen</button></div>
                    </form>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['credit_exhausted_at'])): ?>
                <form method="post" style="margin:-6px 0 14px;">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="bt_action" value="credit_clear">
                    <input type="hidden" name="provider_id" value="<?php echo (int) $p['id']; ?>">
                    <button class="bt-btn" type="submit">✅ Guthaben aufgeladen — Sperre jetzt aufheben</button>
                </form>
                <?php endif; ?>
                <?php endforeach; ?>
                <details style="margin-top: 14px;">
                    <summary class="muted" style="cursor:pointer;">＋ Neuen Anbieter hinzufügen (z. B. weiterer NSFW-fähiger Anbieter)</summary>
                    <form method="post" class="bt-block" style="margin-top: 10px;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="provider_save">
                        <input type="hidden" name="provider_id" value="0">
                        <div class="bt-fields">
                            <label class="bt-field sm"><span>Slug</span><input class="bt-input" type="text" name="slug" placeholder="z. B. mistral" required></label>
                            <label class="bt-field md"><span>Anzeigename</span><input class="bt-input" type="text" name="display_name" placeholder="z. B. Mistral" required></label>
                            <label class="bt-field md"><span>API-Stil</span>
                                <select class="bt-select" name="api_style">
                                    <option value="openai">OpenAI-kompatibel</option>
                                    <option value="openrouter">OpenRouter</option>
                                    <option value="anthropic">Anthropic</option>
                                    <option value="gemini">Gemini</option>
                                </select>
                            </label>
                            <label class="bt-field lg"><span>API-Base</span><input class="bt-input" type="text" name="api_base" placeholder="https://api…" required></label>
                            <label class="bt-field md"><span>API-Key</span><input class="bt-input" type="password" name="api_key" placeholder="API-Key"></label>
                        </div>
                        <div class="bt-actions">
                            <label class="muted" style="cursor:pointer;"><input type="checkbox" name="enabled" checked> Aktiv</label>
                            <button class="bt-btn" type="submit">Anlegen</button>
                        </div>
                    </form>
                </details>
            </div>

            <div class="bt-card">
                <h3>Modelle</h3>
                <p class="muted" style="margin-bottom:10px;">💾 Änderungen werden automatisch gespeichert. Mit ▲▼ die Reihenfolge festlegen — sie gilt überall in der App.</p>
                <p class="muted" style="margin-bottom:12px; font-size:.82rem; line-height:1.5;">
                    💵 Die „$/50 Nachr."-Schätzung wird automatisch aus den $-Preisen (In/Out pro MTok) × <b>echten Nutzerdaten pro Modell</b>
                    berechnet — Input skaliert mit dem Kontext-Umfang, Output bleibt konstant. Die Nutzerdaten werden <b>täglich um 04:00 Uhr</b>
                    aktualisiert · zuletzt: <b<?php echo $blStale ? ' style="color:#ef4d63;"' : ''; ?>><?php echo e($blWhen); ?></b>
                    (<?php echo (int) $blN; ?> Modelle mit Daten)<?php if ($blStale): ?> — ⚠️ überfällig, seit <?php echo (int) $blAgeH; ?> h keine Aktualisierung<?php endif; ?>.
                </p>
                <?php foreach ($models as $m): ?>
                <div class="bt-block model-row" data-mid="<?php echo (int) $m['id']; ?>" data-provider="<?php echo (int) $m['provider_id']; ?>" data-sort="<?php echo (int) $m['sort_order']; ?>">
                    <div class="bt-block-head">
                        <div class="bt-block-title"><?php echo e($m['display_name']); ?> <span class="muted">· <?php echo e($m['provider_name']); ?><?php echo $m['is_default'] ? ' · ⭐ Standard' : ''; ?></span></div>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <span class="save-ind muted" style="font-size:.72rem;"></span>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="bt_action" value="model_move">
                                <input type="hidden" name="model_id" value="<?php echo (int) $m['id']; ?>">
                                <button class="bt-btn secondary" type="submit" name="dir" value="up" title="Nach oben">▲</button>
                                <button class="bt-btn secondary" type="submit" name="dir" value="down" title="Nach unten">▼</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Modell löschen?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="bt_action" value="model_delete">
                                <input type="hidden" name="model_id" value="<?php echo (int) $m['id']; ?>">
                                <button class="bt-btn danger" type="submit" title="Modell löschen">✕</button>
                            </form>
                        </div>
                    </div>
                    <div class="bt-fields">
                        <label class="bt-field md" title="Anbieter/Provider dieses Modells. Muss zum Modell-Key passen (z. B. ein gemini-Key gehört zu Google, ein grok-Key zu xAI). Falsche Zuordnung → „Modell nicht verfügbar“."><span>Anbieter</span>
                            <select class="bt-select" data-field="provider_id">
                                <?php foreach ($providers as $p): ?><option value="<?php echo (int) $p['id']; ?>" <?php echo (int) $p['id'] === (int) $m['provider_id'] ? 'selected' : ''; ?>><?php echo e($p['display_name']); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label class="bt-field md"><span>Anzeigename</span><input class="bt-input" data-field="display_name" type="text" value="<?php echo e($m['display_name']); ?>"></label>
                        <label class="bt-field lg" title="Kurze Erklärung, die den Usern in der neuen Modell-Auswahl-Box angezeigt wird (z. B. „Bestes Storytelling, ausgewogener Preis“). Leer = automatischer Hinweis."><span>Beschreibung (App-Box)</span><input class="bt-input" data-field="description" type="text" maxlength="280" value="<?php echo e($m['description'] ?? ''); ?>" placeholder="z. B. Bestes Storytelling — ausgewogener Preis"></label>
                        <label class="bt-field md"><span>Modell-Key</span><input class="bt-input" data-field="model_key" type="text" value="<?php echo e($m['model_key']); ?>"></label>
                        <label class="bt-field sm" title="Einkaufspreis pro 1 Mio. Input-Tokens. Basis der automatischen $/50-Schätzung (skaliert mit dem Kontext-Umfang des Users)."><span>$ In/MTok</span><input class="bt-input" data-field="price_in" type="text" value="<?php echo e($m['price_in_eur_mtok']); ?>"></label>
                        <label class="bt-field sm" title="Einkaufspreis pro 1 Mio. Output-Tokens. Basis der automatischen $/50-Schätzung."><span>$ Out/MTok</span><input class="bt-input" data-field="price_out" type="text" value="<?php echo e($m['price_out_eur_mtok']); ?>"></label>
                        <label class="bt-field sm" title="Preisnachlass auf den $/50-Preis dieses Modells — gilt unabhängig vom Test-Modell-Status."><span>Rabatt</span>
                            <select class="bt-select" data-field="test_discount_pct">
                                <?php foreach ([0, 10, 25, 50, 75, 100] as $pct): ?>
                                <option value="<?php echo $pct; ?>" <?php echo (int) ($m['test_discount_pct'] ?? 0) === $pct ? 'selected' : ''; ?>><?php echo $pct === 0 ? 'kein Rabatt' : '-' . $pct . '%'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="bt-checks">
                        <label title="Nur ankreuzen bei Modellen, die den reasoning_effort-Parameter unterstützen (Grok 4.x, GPT-5.x). Bei nicht-fähigen Modellen (GLM, Venice/DeepSeek) NICHT ankreuzen — sonst API-Fehler."><input type="checkbox" data-field="supports_reasoning" <?php echo !empty($m['supports_reasoning']) ? 'checked' : ''; ?>> Reasoning-fähig</label>
                        <label title="Free-Modell: bleibt nutzbar, wenn das Guthaben des Users aufgebraucht ist."><input type="checkbox" data-field="is_free" <?php echo !empty($m['is_free']) ? 'checked' : ''; ?>> Free-Modell</label>
                        <label title="Angehakt = der Anbieter wertet die Unterhaltungen NICHT aus. Die App zeigt dann 🛡️ neben dem Modellnamen. Nicht angehakt = 💾 (Daten werden zu Trainingszwecken genutzt)."><input type="checkbox" data-field="is_private" <?php echo !empty($m['is_private']) ? 'checked' : ''; ?>> 🛡️ Komplett privat</label>
                        <label title="Modell ist für NSFW-Charaktere nutzbar."><input type="checkbox" data-field="nsfw_allowed" <?php echo $m['nsfw_allowed'] ? 'checked' : ''; ?>> NSFW</label>
                        <label><input type="checkbox" data-field="enabled" <?php echo $m['enabled'] ? 'checked' : ''; ?>> Aktiv</label>
                        <label title="Standardmodell, wenn User nichts gewählt haben."><input type="checkbox" data-field="is_default" <?php echo $m['is_default'] ? 'checked' : ''; ?>> Standardmodell</label>
                        <label title="Test-Modell: erscheint in der App unter „Testing-Modelle". User zahlt weniger, wird aber alle 25 Nachrichten nach Feedback gefragt."><input type="checkbox" class="test-toggle" data-field="is_test" <?php echo !empty($m['is_test']) ? 'checked' : ''; ?>> 🧪 Test-Modell</label>
                    </div>
                </div>
                <?php endforeach; ?>
                <script>
                (function () {
                    var csrf = <?php echo json_encode(csrf_token()); ?>;
                    function saveModel(row) {
                        var fd = new FormData();
                        fd.append('csrf_token', csrf);
                        fd.append('bt_action', 'model_save');
                        fd.append('model_id', row.dataset.mid);
                        // provider_id kommt jetzt aus dem Anbieter-Dropdown (data-field) — nachträglich änderbar.
                        fd.append('sort_order', row.dataset.sort);
                        row.querySelectorAll('[data-field]').forEach(function (el) {
                            if (el.type === 'checkbox') { if (el.checked) fd.append(el.dataset.field, '1'); }
                            else fd.append(el.dataset.field, el.value);
                        });
                        var ind = row.querySelector('.save-ind');
                        ind.textContent = 'speichere…';
                        fetch(location.href, { method: 'POST', body: fd }).then(function (r) {
                            if (r.ok) {
                                ind.textContent = 'gespeichert ✓';
                                if (fd.get('is_default')) {
                                    document.querySelectorAll('.model-row').forEach(function (rr) {
                                        if (rr !== row) { var c = rr.querySelector('[data-field="is_default"]'); if (c) c.checked = false; }
                                    });
                                }
                            } else { ind.textContent = 'Fehler'; }
                            setTimeout(function () { ind.textContent = ''; }, 2500);
                        }).catch(function () { ind.textContent = 'Fehler'; });
                    }
                    document.querySelectorAll('.model-row [data-field]').forEach(function (el) {
                        el.addEventListener('change', function () { saveModel(el.closest('.model-row')); });
                    });
                })();
                </script>
                <details style="margin-top: 14px;">
                    <summary class="muted" style="cursor:pointer;">＋ Neues Modell hinzufügen</summary>
                    <form method="post" class="bt-block" style="margin-top: 10px;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="model_save">
                        <input type="hidden" name="model_id" value="0">
                        <input type="hidden" name="sort_order" value="99">
                        <div class="bt-fields">
                            <label class="bt-field md"><span>Anbieter</span>
                                <select class="bt-select" name="provider_id">
                                    <?php foreach ($providers as $p): ?><option value="<?php echo (int) $p['id']; ?>"><?php echo e($p['display_name']); ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label class="bt-field md"><span>Anzeigename</span><input class="bt-input" type="text" name="display_name" placeholder="z. B. Telling Turbo" required></label>
                            <label class="bt-field md"><span>Modell-Key</span><input class="bt-input" type="text" name="model_key" placeholder="z. B. glm-5.2" required></label>
                            <label class="bt-field sm"><span>$ In/MTok</span><input class="bt-input" type="text" name="price_in" value="0"></label>
                            <label class="bt-field sm"><span>$ Out/MTok</span><input class="bt-input" type="text" name="price_out" value="0"></label>
                            <label class="bt-field sm" title="Preisnachlass auf den $/50-Preis dieses Modells — gilt unabhängig vom Test-Modell-Status."><span>Rabatt</span>
                                <select class="bt-select" name="test_discount_pct">
                                    <?php foreach ([0, 10, 25, 50, 75, 100] as $pct): ?>
                                    <option value="<?php echo $pct; ?>"><?php echo $pct === 0 ? 'kein Rabatt' : '-' . $pct . '%'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="bt-checks">
                            <label title="Nur bei Modellen ankreuzen, die den reasoning_effort-Parameter unterstützen (Grok 4.x, GPT-5.x)."><input type="checkbox" name="supports_reasoning"> Reasoning-fähig</label>
                            <label title="Free-Modell: bei leerem Guthaben weiterhin nutzbar."><input type="checkbox" name="is_free"> Free-Modell</label>
                            <label title="Angehakt = Anbieter wertet die Unterhaltungen nicht aus (App zeigt 🛡️), sonst 💾."><input type="checkbox" name="is_private"> 🛡️ Komplett privat</label>
                            <label><input type="checkbox" name="nsfw_allowed"> NSFW</label>
                            <label><input type="checkbox" name="enabled" checked> Aktiv</label>
                        </div>
                        <div class="bt-actions"><button class="bt-btn" type="submit">Anlegen</button></div>
                    </form>
                </details>
                <p class="muted" style="margin-top:10px;">Preise in $ pro Million Tokens. „$/50 Nachr." = Schätzung, die den Usern im Profil hinter dem Modell angezeigt wird. Die ▲▼-Reihenfolge gilt überall in der App (Profil, Chat-Modellwahl, NSFW-Auswahl); in eingeschränkten Bereichen werden nur die erlaubten Modelle gezeigt, aber in derselben Reihenfolge.</p>
            </div>

            <?php elseif ($tab === 'import'): ?>
            <div class="bt-card">
                <h3>Bulk-Import: Vorgeschlagene Charaktere</h3>
                <p class="muted">PDF- oder Textdatei mit festen Labels, mehrere Charaktere durch <code>---</code> getrennt. Bilder optional zusätzlich auswählen — Zuordnung über das Label <code>Bild:</code> per Dateiname. Importierte Charaktere erscheinen als „Vorgeschlagen“ im Startseiten-Feed aller App-User.</p>
                <form method="post" enctype="multipart/form-data" style="margin: 16px 0; display: flex; flex-direction: column; gap: 10px; max-width: 560px;">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="bt_action" value="bulk_import">
                    <label class="muted">Charakter-Datei (PDF oder .txt)
                        <input class="bt-input" type="file" name="import_file" accept=".pdf,.txt,text/plain,application/pdf" required>
                    </label>
                    <label class="muted">Bilder (optional, mehrere möglich)
                        <input class="bt-input" type="file" name="import_images[]" accept="image/*" multiple>
                    </label>
                    <button class="bt-btn" type="submit" style="align-self: flex-start;">Importieren</button>
                </form>
                <h4 style="margin: 18px 0 8px;">Kopiervorlage</h4>
                <pre class="bt-template">### Charakter: Luna
Vorname: Luna
Nachname: Weber
Geschlecht: weiblich
Alter: 24
Typ: Neugierig, Fürsorglich, Gelassen
Genre: Fantasie, Abenteuer
Beschreibung: ...
Geschichte: ...
Vorgeschichte: *Luna blickt neugierig auf und lächelt.* Oh, hallo! Ich habe dich hier noch nie gesehen.
Bild: luna.jpg
---</pre>
                <button class="bt-btn secondary" type="button" onclick="navigator.clipboard.writeText(document.querySelector('.bt-template').textContent).then(()=>this.textContent='Kopiert ✓')">Vorlage kopieren</button>
            </div>

            <div class="bt-card">
                <h3>Bereits importierte Vorschläge (<?php echo count($suggestedChars); ?>)</h3>
                <table class="bt">
                    <tr><th>Name</th><th>Typ</th><th>Bild</th><th>Von</th><th></th></tr>
                    <?php foreach ($suggestedChars as $c): ?>
                    <tr>
                        <td><?php echo e(trim($c['first_name'] . ' ' . $c['last_name'])); ?></td>
                        <td><?php echo e(implode(', ', json_decode($c['moods'] ?? '[]', true) ?: [])); ?></td>
                        <td><?php echo $c['image_file'] ? '🖼️' : '—'; ?></td>
                        <td><?php echo e($c['username']); ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Charakter löschen?');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="bt_action" value="character_delete">
                                <input type="hidden" name="character_id" value="<?php echo (int) $c['id']; ?>">
                                <button class="bt-btn danger" type="submit">Löschen</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (!$suggestedChars): ?><p class="muted">Noch keine importierten Charaktere.</p><?php endif; ?>
            </div>

            <?php elseif ($tab === 'tags'): ?>
                <?php foreach ([
                    ['kind' => 'mood',  'title' => 'Charaktertypen (Stimmungen)', 'tags' => $moodTags,  'hint' => 'Beliebig viele pro Charakter/Identität wählbar.'],
                    ['kind' => 'genre', 'title' => 'Genres',                       'tags' => $genreTags, 'hint' => 'Mindestens 1 pro Charakter, keine Obergrenze.'],
                ] as $sec): ?>
                <div class="bt-card">
                    <h3><?php echo e($sec['title']); ?></h3>
                    <p class="muted"><?php echo e($sec['hint']); ?> Deaktivierte Einträge (🚫) sind in der App ausgeblendet, bleiben aber bei bestehenden Charakteren erhalten.</p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin:14px 0;">
                        <?php foreach ($sec['tags'] as $t): ?>
                        <span class="bt-tagchip <?php echo $t['active'] ? '' : 'inactive'; ?>">
                            <?php echo e($t['name']); ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="bt_action" value="tag_toggle">
                                <input type="hidden" name="tag_id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" title="Ein-/Ausblenden"><?php echo $t['active'] ? '👁' : '🚫'; ?></button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Wirklich löschen? Bestehende Charaktere behalten den Wert.');">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="bt_action" value="tag_delete">
                                <input type="hidden" name="tag_id" value="<?php echo (int) $t['id']; ?>">
                                <button type="submit" title="Löschen">✕</button>
                            </form>
                        </span>
                        <?php endforeach; ?>
                        <?php if (!$sec['tags']): ?><span class="muted">Noch keine Einträge.</span><?php endif; ?>
                    </div>
                    <form method="post" class="bt-inline-form">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="tag_add">
                        <input type="hidden" name="kind" value="<?php echo $sec['kind']; ?>">
                        <input class="bt-input" type="text" name="name" placeholder="Neuen <?php echo $sec['kind'] === 'genre' ? 'Genre' : 'Charaktertyp'; ?> hinzufügen …" maxlength="50" required>
                        <button class="bt-btn" type="submit">Hinzufügen</button>
                    </form>
                </div>
                <?php endforeach; ?>

            <?php elseif ($tab === 'chars'): ?>
            <div class="bt-card">
                <h3>Charaktere — Markierungen</h3>
                <p class="muted" style="margin-bottom:10px;"><strong>NSFW</strong> = erscheint nur unter dem Filter „NSFW" und kann im Chat ausschließlich NSFW-Modelle nutzen. <strong>Vorgeschlagen</strong> = wird den Usern als Vorschlag angezeigt. Änderungen werden automatisch gespeichert.</p>
                <table class="bt">
                    <tr><th>Name</th><th>Besitzer</th><th>Sichtbarkeit</th><th>Vorgeschlagen</th><th>NSFW</th><th></th></tr>
                    <?php foreach ($allActiveChars as $c): ?>
                    <tr class="char-row" data-cid="<?php echo (int) $c['id']; ?>">
                        <td><?php echo e(trim($c['first_name'] . ' ' . $c['last_name'])); ?></td>
                        <td><?php echo e($c['username']); ?></td>
                        <td><?php echo $c['is_public'] ? '🌐 öffentlich' : '🔒 privat'; ?></td>
                        <td><input type="checkbox" class="char-flag" data-action="character_set_suggested" data-name="is_suggested" <?php echo $c['is_suggested'] ? 'checked' : ''; ?>></td>
                        <td><input type="checkbox" class="char-flag" data-action="character_set_nsfw" data-name="is_nsfw" <?php echo $c['is_nsfw'] ? 'checked' : ''; ?>></td>
                        <td><span class="save-ind muted" style="font-size:.72rem;"></span></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (!$allActiveChars): ?><p class="muted">Noch keine Charaktere.</p><?php endif; ?>
                <script>
                (function () {
                    var csrf = <?php echo json_encode(csrf_token()); ?>;
                    document.querySelectorAll('.char-row .char-flag').forEach(function (cb) {
                        cb.addEventListener('change', function () {
                            var row = cb.closest('.char-row');
                            var ind = row.querySelector('.save-ind');
                            var fd = new FormData();
                            fd.append('csrf_token', csrf);
                            fd.append('bt_action', cb.dataset.action);
                            fd.append('character_id', row.dataset.cid);
                            if (cb.checked) fd.append(cb.dataset.name, '1');
                            ind.textContent = 'speichere…';
                            fetch(location.href, { method: 'POST', body: fd }).then(function (r) {
                                ind.textContent = r.ok ? 'gespeichert ✓' : 'Fehler';
                                setTimeout(function () { ind.textContent = ''; }, 2000);
                            }).catch(function () { ind.textContent = 'Fehler'; });
                        });
                    });
                })();
                </script>
            </div>

            <?php elseif ($tab === 'media'): ?>
                <div class="bt-card" style="border-color:rgba(124,92,255,.55);">
                    <h3>🌐 OpenRouter für Bild, Video &amp; TTS</h3>
                    <p class="muted" style="margin-bottom:10px;">Für alle drei Generator-Arten trägst du dieselbe API-Base ein: <code>https://openrouter.ai/api/v1</code>. Beyond Telling ergänzt den jeweiligen Endpunkt automatisch.</p>
                    <div class="bt-fields">
                        <div class="bt-field md"><span>Bild</span><code>openrouter_image</code><small class="muted">→ POST /images</small></div>
                        <div class="bt-field md"><span>Video / Bild→Video</span><code>openrouter_video</code><small class="muted">→ POST /videos + Polling</small></div>
                        <div class="bt-field md"><span>TTS</span><code>openrouter_tts</code><small class="muted">→ POST /audio/speech</small></div>
                    </div>
                    <p class="muted" style="margin-top:10px;">Jeweils den vollständigen OpenRouter-Modell-Slug eintragen und als <strong>API-Key-Anbieter</strong> den zentralen OpenRouter-Eintrag auswählen. Bei Bild/Video kann „Größe/Auflösung" z. B. <code>1024x1024</code>, <code>2K 16:9</code> bzw. <code>720p 16:9 5s</code> enthalten. Bei <code>openrouter_video</code> lässt sich außerdem pro Video-Modell festlegen, ob OpenRouter Sound erzeugen soll.</p>
                </div>
                <?php
                $genSections = [
                    ['kind' => 'image', 'title' => '🖼️ Bild-Generatoren', 'gens' => $imageGens, 'assignCol' => 'image_generator_id', 'defStyle' => 'openai_image', 'defBase' => 'https://api.openai.com/v1', 'defModel' => 'gpt-image-1'],
                    ['kind' => 'video', 'title' => '🎬 Video-Generatoren', 'gens' => $videoGens, 'assignCol' => 'video_generator_id', 'defStyle' => 'openai_video', 'defBase' => 'https://api.openai.com/v1', 'defModel' => 'sora-2'],
                    ['kind' => 'tts', 'title' => '🔊 TTS / Vorlesen', 'gens' => $ttsGens, 'assignCol' => 'tts_generator_id', 'defStyle' => 'gemini_tts', 'defBase' => 'https://generativelanguage.googleapis.com/v1beta', 'defModel' => 'gemini-3.1-flash-tts-preview'],
                ];
                foreach ($genSections as $sec): ?>
                <div class="bt-card">
                    <h3><?php echo $sec['title']; ?></h3>
                    <?php foreach ($sec['gens'] as $g): ?>
                    <?php
                    $generatorKeyReady = !empty($g['has_central_key']) && !empty($g['key_provider_enabled']);
                    $generatorKeyName = trim((string) ($g['key_provider_name'] ?? '')) ?: 'nicht ausgewählt';
                    ?>
                    <div class="bt-block">
                        <div class="bt-block-head">
                            <div class="bt-block-title"><?php echo e($g['display_name']); ?> <span class="muted">· <?php echo e($g['api_style']); ?> · Key: <?php echo e($generatorKeyName); ?> <?php echo $generatorKeyReady ? '✅' : '❌'; ?><?php echo $g['enabled'] ? '' : ' · ⏸ inaktiv'; ?></span></div>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <button class="bt-btn secondary" type="button" onclick="document.getElementById('assign-<?php echo (int) $g['id']; ?>').classList.toggle('hidden')">🔗 Modelle zuordnen</button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Generator löschen?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="bt_action" value="generator_delete">
                                    <input type="hidden" name="generator_id" value="<?php echo (int) $g['id']; ?>">
                                    <button class="bt-btn danger" type="submit" title="Generator löschen">✕</button>
                                </form>
                            </div>
                        </div>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="bt_action" value="generator_save">
                            <input type="hidden" name="generator_id" value="<?php echo (int) $g['id']; ?>">
                            <input type="hidden" name="kind" value="<?php echo $sec['kind']; ?>">
                            <div class="bt-fields">
                                <label class="bt-field md"><span>Name</span><input class="bt-input" type="text" name="display_name" value="<?php echo e($g['display_name']); ?>"></label>
                                <label class="bt-field sm"><span>Stil</span><input class="bt-input" type="text" name="api_style" value="<?php echo e($g['api_style']); ?>"></label>
                                <label class="bt-field lg"><span>API-Base</span><input class="bt-input" type="text" name="api_base" value="<?php echo e($g['api_base']); ?>"></label>
                                <label class="bt-field md"><span>Modell</span><input class="bt-input" type="text" name="model_key" value="<?php echo e($g['model_key']); ?>"></label>
                                <?php if ($sec['kind'] === 'tts'): ?>
                                <label class="bt-field sm" title="Stimme, die bei „Männlich" gelesen wird (z. B. Gemini Puck/Charon, Grok leo, Venice am_liam)"><span>♂ Stimme</span><input class="bt-input" type="text" name="tts_voice_male" value="<?php echo e($g['tts_voice_male'] ?? ''); ?>"><input type="hidden" name="image_size" value="<?php echo e($g['image_size']); ?>"></label>
                                <label class="bt-field sm" title="Stimme, die bei „Weiblich" gelesen wird (z. B. Gemini Kore/Leda, Grok eve, Venice af_sky)"><span>♀ Stimme</span><input class="bt-input" type="text" name="tts_voice_female" value="<?php echo e($g['tts_voice_female'] ?? ''); ?>"></label>
                                <?php else: ?>
                                <label class="bt-field sm"><span><?php echo $sec['kind'] === 'video' ? 'Auflösung' : 'Größe'; ?></span><input class="bt-input" type="text" name="image_size" value="<?php echo e($g['image_size']); ?>"></label>
                                <?php endif; ?>
                                <label class="bt-field sm" title="<?php echo $sec['kind'] === 'tts' ? 'TTS wird pro 1000 Zeichen der vorgelesenen Nachricht berechnet.' : ''; ?>"><span><?php echo $sec['kind'] === 'tts' ? '$/1000 Zeichen' : '$/Stück'; ?></span><input class="bt-input" type="text" name="price_per_item" value="<?php echo e($g['price_per_item_eur']); ?>"></label>
                                <?php if ($sec['kind'] === 'image'): ?>
                                <label class="bt-field md" title="Optionale Text-KI, die den Bild-Prompt VOR der Generierung verbessert/anreichert (– aus – = kein Zusatzschritt, direkter Prompt). Kostet zusätzlich die Token der Text-KI. Für NSFW-Bilder ein NSFW-fähiges Modell wählen."><span>Prompt-KI (Text)</span>
                                    <select class="bt-select" name="prompt_model_id">
                                        <option value="0">– aus –</option>
                                        <?php foreach ($creatorModels as $cm): ?>
                                            <option value="<?php echo (int) $cm['id']; ?>" <?php echo (int) ($g['prompt_model_id'] ?? 0) === (int) $cm['id'] ? 'selected' : ''; ?>><?php echo e($cm['display_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <?php endif; ?>
                                <label class="bt-field md"><span>API-Key-Anbieter</span>
                                    <select class="bt-select" name="provider_id" required>
                                        <option value="">– auswählen –</option>
                                        <?php foreach ($providers as $keyProvider): ?>
                                            <?php
                                            $keyState = empty($keyProvider['enabled'])
                                                ? 'inaktiv'
                                                : (!empty($keyProvider['api_key']) ? 'Key gesetzt' : 'kein Key');
                                            ?>
                                            <option value="<?php echo (int) $keyProvider['id']; ?>" <?php echo (int) ($g['provider_id'] ?? 0) === (int) $keyProvider['id'] ? 'selected' : ''; ?>>
                                                <?php echo e($keyProvider['display_name'] . ' — ' . $keyState); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <div class="bt-actions">
                                <label class="muted" style="cursor:pointer;"><input type="checkbox" name="enabled" <?php echo $g['enabled'] ? 'checked' : ''; ?>> Aktiv</label>
                                <?php if ($sec['kind'] === 'video' && ($g['api_style'] ?? '') === 'openrouter_video'): ?>
                                <label class="muted" style="cursor:pointer;" title="OpenRouter-Parameter generate_audio. Nur aktivieren, wenn das eingetragene Video-Modell Audio unterstützt; Sound kann den Preis erhöhen."><input type="checkbox" name="generate_audio" <?php echo bt_admin_setting($pdo, 'video_generate_audio_' . (int) $g['id'], '0') === '1' ? 'checked' : ''; ?>> 🔊 Mit Sound</label>
                                <?php elseif ($sec['kind'] === 'video'): ?>
                                <span class="muted" title="Dieser API-Stil bietet in Beyond Telling keinen verlässlichen Ein/Aus-Schalter für Sound.">🔇 Sound nicht umschaltbar</span>
                                <?php endif; ?>
                                <?php if ($sec['kind'] === 'image'): ?>
                                <label class="muted" style="cursor:pointer;" title="Wenn an: Vor dem Erstellen wählt der Nutzer ein Bild (Avatar/Identität oder ein bereits im Chat erzeugtes Bild), das als Quell-/Referenzbild an die KI mitgeschickt wird — für Image-Edit-KIs."><input type="checkbox" name="needs_source_image" <?php echo !empty($g['needs_source_image']) ? 'checked' : ''; ?>> 🖼️ Bild mitschicken</label>
                                <?php endif; ?>
                                <button class="bt-btn" type="submit">💾 Speichern</button>
                            </div>
                        </form>
                        <div id="assign-<?php echo (int) $g['id']; ?>" class="hidden bt-assign">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="bt_action" value="generator_assign">
                                <input type="hidden" name="generator_id" value="<?php echo (int) $g['id']; ?>">
                                <?php $assignHint = $sec['kind'] === 'tts'
                                    ? 'Diesem TTS-Generator zugeordnete Chat-Modelle. Ordne einem Modell <strong>mehrere TTS-Stimmen</strong> zu (bei jeder Stimme dieses Modell ankreuzen) — dann bekommt der Nutzer beim 🔊-Vorlesen eine <strong>Auswahl</strong> der Stimmen.'
                                    : 'Diesem Generator zugeordnete Chat-Modelle (der ' . ($sec['kind'] === 'video' ? '🎬' : '🎨') . '-Button erscheint in Chats mit diesen Modellen). Mehrere Generatoren pro Modell sind möglich — dann wählt der Nutzer beim Erstellen aus.'; ?>
                                <p class="muted" style="margin-bottom:8px;"><?php echo $assignHint; ?></p>
                                <?php foreach ($chatModelsForAssign as $cm): ?>
                                <label class="muted" style="display:inline-block; margin:4px 14px 4px 0; cursor:pointer;">
                                    <input type="checkbox" name="model_ids[]" value="<?php echo (int) $cm['id']; ?>" <?php echo isset($modelGenMap[(int) $cm['id']][(int) $g['id']]) ? 'checked' : ''; ?>>
                                    <?php echo e($cm['display_name']); ?> <span style="opacity:.6;">(<?php echo e($cm['provider_name']); ?>)</span>
                                </label>
                                <?php endforeach; ?>
                                <div style="margin-top:10px;"><button class="bt-btn" type="submit">Zuordnung speichern</button></div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$sec['gens']): ?><p class="muted">Noch kein <?php echo $sec['kind'] === 'video' ? 'Video' : ($sec['kind'] === 'tts' ? 'TTS' : 'Bild'); ?>-Generator.</p><?php endif; ?>
                    <details style="margin-top:14px;">
                        <summary class="muted" style="cursor:pointer;">＋ <?php echo $sec['kind'] === 'video' ? 'Video' : ($sec['kind'] === 'tts' ? 'TTS' : 'Bild'); ?>-Generator hinzufügen</summary>
                        <form method="post" class="bt-block" style="margin-top:10px;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="bt_action" value="generator_save">
                            <input type="hidden" name="generator_id" value="0">
                            <input type="hidden" name="kind" value="<?php echo $sec['kind']; ?>">
                            <div class="bt-fields">
                                <label class="bt-field md"><span>Name</span><input class="bt-input" type="text" name="display_name" placeholder="z. B. Bild erstellen" required></label>
                                <label class="bt-field sm"><span>Stil</span><input class="bt-input" type="text" name="api_style" value="<?php echo $sec['defStyle']; ?>"></label>
                                <label class="bt-field lg"><span>API-Base</span><input class="bt-input" type="text" name="api_base" value="<?php echo $sec['defBase']; ?>"></label>
                                <label class="bt-field md"><span>Modell</span><input class="bt-input" type="text" name="model_key" value="<?php echo $sec['defModel']; ?>"></label>
                                <?php if ($sec['kind'] === 'image'): ?>
                                <label class="bt-field sm"><span>Größe</span><input class="bt-input" type="text" name="image_size" value="1024x1024"></label>
                                <?php elseif ($sec['kind'] === 'video'): ?>
                                <label class="bt-field sm"><span>Auflösung</span><input class="bt-input" type="text" name="image_size" value="720p"></label>
                                <?php elseif ($sec['kind'] === 'tts'): ?>
                                <label class="bt-field sm" title="Leer = Stil-Standard; bei OpenRouter sind Stimmen modellabhängig."><span>♂ Stimme</span><input class="bt-input" type="text" name="tts_voice_male" value=""></label>
                                <label class="bt-field sm" title="Leer = Stil-Standard; bei OpenRouter sind Stimmen modellabhängig."><span>♀ Stimme</span><input class="bt-input" type="text" name="tts_voice_female" value=""><input type="hidden" name="image_size" value=""></label>
                                <?php endif; ?>
                                <label class="bt-field sm" title="<?php echo $sec['kind'] === 'tts' ? 'TTS wird pro 1000 Zeichen der vorgelesenen Nachricht berechnet.' : ''; ?>"><span><?php echo $sec['kind'] === 'tts' ? '$/1000 Zeichen' : '$/Stück'; ?></span><input class="bt-input" type="text" name="price_per_item" value="0"></label>
                                <?php if ($sec['kind'] === 'image'): ?>
                                <label class="bt-field md" title="Optionale Text-KI, die den Bild-Prompt VOR der Generierung verbessert/anreichert (– aus – = kein Zusatzschritt). Für NSFW-Bilder ein NSFW-fähiges Modell wählen."><span>Prompt-KI (Text)</span>
                                    <select class="bt-select" name="prompt_model_id">
                                        <option value="0">– aus –</option>
                                        <?php foreach ($creatorModels as $cm): ?>
                                            <option value="<?php echo (int) $cm['id']; ?>"><?php echo e($cm['display_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <?php endif; ?>
                                <label class="bt-field md"><span>API-Key-Anbieter</span>
                                    <select class="bt-select" name="provider_id" required>
                                        <option value="">– auswählen –</option>
                                        <?php foreach ($providers as $keyProvider): ?>
                                            <?php
                                            $keyState = empty($keyProvider['enabled'])
                                                ? 'inaktiv'
                                                : (!empty($keyProvider['api_key']) ? 'Key gesetzt' : 'kein Key');
                                            ?>
                                            <option value="<?php echo (int) $keyProvider['id']; ?>">
                                                <?php echo e($keyProvider['display_name'] . ' — ' . $keyState); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <div class="bt-actions">
                                <label class="muted" style="cursor:pointer;"><input type="checkbox" name="enabled" checked> Aktiv</label>
                                <?php if ($sec['kind'] === 'video'): ?>
                                <label class="muted" style="cursor:pointer;" title="Wird bei API-Stil openrouter_video als generate_audio gesendet. Nur aktivieren, wenn das Modell Audio unterstützt; Sound kann den Preis erhöhen."><input type="checkbox" name="generate_audio"> 🔊 Mit Sound (OpenRouter)</label>
                                <?php endif; ?>
                                <?php if ($sec['kind'] === 'image'): ?>
                                <label class="muted" style="cursor:pointer;" title="Wenn an: Vor dem Erstellen wählt der Nutzer ein Bild (Avatar/Identität oder ein bereits im Chat erzeugtes Bild), das als Quell-/Referenzbild an die KI mitgeschickt wird — für Image-Edit-KIs."><input type="checkbox" name="needs_source_image"> 🖼️ Bild mitschicken</label>
                                <?php endif; ?>
                                <button class="bt-btn" type="submit">Anlegen</button>
                            </div>
                        </form>
                    </details>
                </div>
                <?php endforeach; ?>

                <div class="bt-card">
                    <h3>🎬 Video-Bild-Pipeline <span class="muted" style="font-weight:400;">(global — je eine eigene Pipeline für „Video erstellen" und „Bild erstellen" in ALLEN Chats)</span></h3>
                    <?php
                    $i2vGensList = array_values(array_filter($videoGens, fn($g) => in_array(($g['api_style'] ?? ''), ['venice_i2v', 'openrouter_video'], true)));
                    // Bild-Bearbeitungs-KI: nur Generatoren, die ein Quellbild wirklich bearbeiten können
                    $editGensList = array_values(array_filter($imageGens, fn($g) => in_array(($g['api_style'] ?? ''), ['openai_image', 'openrouter_image', 'gemini_image', 'venice_edit'], true)));
                    $videoReady = $videoImgGenId && $videoPromptModelId && $videoI2vGenId;
                    $editReady  = $imageeditPromptModelId && $imageeditGenId;
                    ?>

                    <!-- ===== VIDEO-PIPELINE ===== -->
                    <h4 style="margin:4px 0 6px;">🎬 Video-Pipeline <span class="muted" style="font-weight:400;">— für „Video erstellen"</span></h4>
                    <p class="muted" style="margin-bottom:10px;">
                        <strong>1.</strong> Die <strong>Bild-KI</strong> erzeugt aus dem aktuellen Chat-Moment (Charakter-Avatar als Referenz) ein Startbild. &nbsp;
                        <strong>2.</strong> Die <strong>Text-KI</strong> schreibt daraus (+ letzte Nutzer-/KI-Nachricht + Kontext) einen Video-Prompt (englisch). &nbsp;
                        <strong>3.</strong> Die <strong>Bild→Video-KI</strong> (<code>venice_i2v</code> oder <code>openrouter_video</code>) macht das fertige Video. Kosten aller drei Stufen summiert.</p>
                    <?php if (!$i2vGensList): ?><p class="muted" style="color:#e6a23c;">⚠️ Es ist noch kein Bild→Video-Generator angelegt. Lege oben einen mit Stil <code>venice_i2v</code> oder <code>openrouter_video</code> an.</p><?php endif; ?>
                    <form method="post" class="bt-block">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="video_pipeline_save">
                        <div class="bt-fields">
                            <label class="bt-field md"><span>1. Startbild-KI (Bild-Generator)</span>
                                <select class="bt-select" name="video_image_generator_id">
                                    <option value="0">– nicht gesetzt –</option>
                                    <?php foreach ($imageGens as $ig): ?>
                                        <option value="<?php echo (int) $ig['id']; ?>" <?php echo $videoImgGenId === (int) $ig['id'] ? 'selected' : ''; ?>><?php echo e($ig['display_name']); ?> (<?php echo e($ig['api_style']); ?><?php echo !empty($ig['has_central_key']) && !empty($ig['key_provider_enabled']) ? '' : ' · ❌ kein zentraler Key'; ?><?php echo $ig['enabled'] ? '' : ' · ⏸ inaktiv'; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="bt-field md" title="Muss ein bildfähiges (Vision-)Modell sein — Gemini, GPT-4o-Klasse, Grok oder Claude. Reine Text-Modelle (GLM u. a.) liefern hier einen leeren Prompt."><span>2. Text-KI für den Video-Prompt (bildfähig!)</span>
                                <select class="bt-select" name="video_prompt_model_id">
                                    <option value="0">– nicht gesetzt –</option>
                                    <?php foreach ($creatorModels as $cm): ?>
                                        <option value="<?php echo (int) $cm['id']; ?>" <?php echo $videoPromptModelId === (int) $cm['id'] ? 'selected' : ''; ?>><?php echo e($cm['display_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="bt-field md"><span>3. Bild→Video-KI</span>
                                <select class="bt-select" name="video_i2v_generator_id">
                                    <option value="0">– nicht gesetzt –</option>
                                    <?php foreach ($i2vGensList as $vg): ?>
                                        <option value="<?php echo (int) $vg['id']; ?>" <?php echo $videoI2vGenId === (int) $vg['id'] ? 'selected' : ''; ?>><?php echo e($vg['display_name']); ?><?php echo !empty($vg['has_central_key']) && !empty($vg['key_provider_enabled']) ? '' : ' · ❌ kein zentraler Key'; ?><?php echo $vg['enabled'] ? '' : ' · ⏸ inaktiv (oben aktivieren!)'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="bt-actions">
                            <span class="muted"><?php echo $videoReady ? '✅ Video-Pipeline vollständig — „Video erstellen" aktiv' : '⚠️ unvollständig — der Video-Button erscheint erst, wenn alle 3 gesetzt sind'; ?></span>
                            <button class="bt-btn" type="submit">Video-Pipeline speichern</button>
                        </div>
                    </form>

                    <hr style="border:none;border-top:1px solid rgba(255,255,255,.12);margin:20px 0;">

                    <!-- ===== BILD-PIPELINE (Bild-Bearbeitung) ===== -->
                    <h4 style="margin:4px 0 6px;">🖼️ Bild-Pipeline <span class="muted" style="font-weight:400;">— für „Bild erstellen"</span></h4>
                    <p class="muted" style="margin-bottom:10px;">Sobald Text-KI <em>und</em> Bild-Bearbeitungs-KI gesetzt sind, ersetzt dieses Verfahren die alte Bild-Erstellung:
                        <strong>1.</strong> Der Nutzer wählt ein <strong>Quellbild</strong> (Charakter-/Identitätsbild oder ein im Chat erzeugtes Bild). &nbsp;
                        <strong>2.</strong> Die <strong>Text-KI</strong> bekommt GENAU dieses Quellbild + letzte Nutzer-/KI-Nachricht + Kontext und schreibt einen Edit-Prompt (englisch). &nbsp;
                        <strong>3.</strong> Die <strong>Bild-Bearbeitungs-KI</strong> bearbeitet das Quellbild. Kosten beider Stufen summiert.</p>
                    <?php if (!$editGensList): ?><p class="muted" style="color:#e6a23c;">⚠️ Für die Bild-Bearbeitung ist noch kein passender Generator angelegt. Lege oben einen mit Stil <code>openai_image</code>, <code>openrouter_image</code>, <code>gemini_image</code> oder <code>venice_edit</code> an.</p><?php endif; ?>
                    <form method="post" class="bt-block">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="bt_action" value="imageedit_pipeline_save">
                        <div class="bt-fields">
                            <label class="bt-field md" title="Eigene Text-KI der Bild-Pipeline (unabhängig von der Video-Text-KI). Muss ein bildfähiges (Vision-)Modell sein."><span>1. Text-KI für den Edit-Prompt (bildfähig!)</span>
                                <select class="bt-select" name="imageedit_prompt_model_id">
                                    <option value="0">– nicht gesetzt –</option>
                                    <?php foreach ($creatorModels as $cm): ?>
                                        <option value="<?php echo (int) $cm['id']; ?>" <?php echo $imageeditPromptModelId === (int) $cm['id'] ? 'selected' : ''; ?>><?php echo e($cm['display_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="bt-field md" title="Bearbeitet ein Quellbild anhand des Edit-Prompts. Unterstützt werden openai_image, openrouter_image, gemini_image (Nano Banana) und venice_edit."><span>2. Bild-Bearbeitungs-KI (Edit)</span>
                                <select class="bt-select" name="imageedit_generator_id">
                                    <option value="0">– nicht gesetzt (altes Bild-Verfahren) –</option>
                                    <?php foreach ($editGensList as $eg): ?>
                                        <option value="<?php echo (int) $eg['id']; ?>" <?php echo $imageeditGenId === (int) $eg['id'] ? 'selected' : ''; ?>><?php echo e($eg['display_name']); ?> (<?php echo e($eg['api_style']); ?><?php echo !empty($eg['has_central_key']) && !empty($eg['key_provider_enabled']) ? '' : ' · ❌ kein zentraler Key'; ?><?php echo $eg['enabled'] ? '' : ' · ⏸ inaktiv'; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="bt-actions">
                            <span class="muted"><?php echo $editReady ? '✅ Bild-Pipeline aktiv — „Bild erstellen" nutzt die Bearbeitung' : 'ℹ️ aus — beide Felder setzen, sonst greift das alte Bild-Verfahren'; ?></span>
                            <button class="bt-btn" type="submit">Bild-Pipeline speichern</button>
                        </div>
                    </form>
                </div>

                <div class="bt-card">
                    <p class="muted">Bild-Stile: <code>openai_image</code> (OpenAI GPT-Image, nutzt <em>size</em> z. B. 1024x1024) &middot; <code>grok_image</code> (xAI Grok Imagine, <em>Größe</em> = <code>1k</code>/<code>2k</code>) &middot; <code>gemini_image</code> (Google Gemini / Nano Banana, <em>Größe</em> wird ignoriert) &middot; <code>venice_image</code> (Venice AI, <em>Größe</em> = <code>Breite×Höhe</code> z. B. <code>1024x1024</code>, Modell z. B. <code>venice-sd35</code>) &middot; <strong><code>venice_edit</code></strong> (Venice AI Bild-BEARBEITUNG, Endpunkt <code>/image/edit</code>, Modell z. B. <code>firered-image-edit</code> oder <code>gpt-image-2-edit</code>, <em>Größe</em> = Auflösung <code>1K</code>/<code>2K</code>/<code>4K</code>) — bearbeitet ein Quellbild und wird für die <strong>Bild-Bearbeitungs-KI</strong> der Video-Bild-Pipeline genutzt (ebenso wie <code>openai_image</code> und <code>gemini_image</code>). Nach dem Anlegen über „Modelle" einem oder mehreren Chat-Modellen zuordnen — dann erscheint in der App der 🎨-Button in Chats mit diesem Modell. Video-APIs sind anbieterspezifisch (oft asynchron); <code>openai_video</code> ist als Best-Effort angelegt. Video-Stil <code>venice_video</code> (Venice AI, Modell z. B. <code>wan-2-7-uncensored-text-to-video</code>) nutzt die Venice-Warteschlange (Queue → Retrieve); das Feld „Größe" ist die <em>Auflösung</em> (z. B. <code>720p</code>, optional mit Dauer wie <code>720p/10s</code>; Standard 5&nbsp;s). Prompt = Chat-Kontext + Charakter-/Identitätsbilder. <strong>Neu: <code>venice_i2v</code></strong> (Bild→Video, Modell <code>wan-2-7-uncensored-image-to-video</code>) animiert ein vorhandenes Bild — beim Erstellen wählt der Nutzer ein Ausgangsbild (ein im Chat erzeugtes Bild oder das Charakter-/Identitätsbild). Einfach zusätzlich zu <code>venice_video</code> demselben Modell zuordnen — dann kann der Nutzer beim „Video erstellen" zwischen Text→Video und Bild→Video wählen. <strong>Mehrere Bild-/Video-/TTS-Generatoren pro Modell</strong> sind erlaubt: bei 2+ erscheint beim Erstellen eine Auswahl mit Namen und Preis; bei genau einem wird er direkt genutzt. TTS: <code>gemini_tts</code> (Google Gemini, z. B. <code>gemini-3.1-flash-tts-preview</code>) — das Feld „Größe" ist hier die <em>Stimme</em> (z. B. Kore, Puck, Charon). Zusätzlich <code>grok_tts</code> (xAI Grok, Basis <code>https://api.x.ai/v1</code>, Stimme fest nach Geschlecht: weiblich = <code>eve</code>, männlich = <code>leo</code>) — <strong>NSFW-exklusiv</strong>: kann nur NSFW-Modellen zugeordnet werden und dient NSFW-Modellen ohne eigene TTS-Zuordnung als automatischer Fallback. <strong>Wichtig:</strong> Die im Modell gesetzte TTS-Zuordnung hat immer Vorrang — auch bei NSFW-Modellen. Ein Venice-NSFW-Modell nutzt also Venice-TTS, wenn dieser ihm zugeordnet ist (nicht automatisch Grok). Außerdem <code>venice_tts</code> (Venice AI, OpenAI-kompatibel, Modell z. B. <code>tts-kokoro</code>) — „Größe" = Standard-Stimme, Stimme automatisch nach Geschlecht (weiblich = <code>af_sky</code>, männlich = <code>am_liam</code>); wie Gemini einem Modell zuordnen. <em>Venice-Chat</em>: unter „KI &amp; API-Keys" ist bereits ein Provider „Venice" (OpenAI-kompatibel, Basis <code>https://api.venice.ai/api/v1</code>) angelegt — nur API-Key + Modelle eintragen. Venice unterstützt jetzt auch <strong>Videogenerierung</strong> (Stil <code>venice_video</code>, siehe oben). Nach dem Zuordnen an ein Chat-Modell erscheint in der App unter jeder Nachricht ein 🔊-Symbol. Ohne zugeordneten TTS-Anbieter liest die App mit der Gerätestimme vor (kostenlos).</p>
                </div>


            <?php elseif ($tab === 'betas'): ?>
            <div class="bt-card">
                <h3>🧪 Betas — Test-Modelle &amp; Feedback</h3>
                <p class="muted" style="margin-bottom:14px;">Test-Modelle erscheinen in der App unter „Testing-Modelle". User zahlen den eingestellten Rabatt und werden alle 25 Nachrichten nach Feedback gefragt. Klicke einen User an, um seine Bewertungen zu sehen.</p>
                <?php if (!$betaModels): ?>
                    <p class="muted">Noch keine Test-Modelle. Aktiviere „🧪 Test-Modell" bei einem Modell unter „Modelle &amp; API-Keys".</p>
                <?php endif; ?>
                <?php foreach ($betaModels as $bm):
                    $raters = $betaFeedback[(int) $bm['id']] ?? [];
                    // Durchschnitt über alle Bewertungen dieses Modells
                    $sumS = $sumC = $sumK = $n = 0;
                    foreach ($raters as $ru) foreach ($ru['items'] as $it) { $sumS += $it['story']; $sumC += $it['context']; $sumK += $it['continue']; $n++; }
                ?>
                <div class="bt-block" style="margin-bottom:14px;">
                    <div class="bt-block-head">
                        <div class="bt-block-title">
                            <?php echo e($bm['display_name']); ?>
                            <?php if (!empty($bm['is_test'])): ?><span class="muted">· 🧪 Test<?php echo (int) $bm['test_discount_pct'] > 0 ? ' · -' . (int) $bm['test_discount_pct'] . '%' : ''; ?></span><?php else: ?><span class="muted">· (kein Test-Modell mehr)</span><?php endif; ?>
                        </div>
                        <div class="muted" style="font-size:.8rem;">
                            <?php echo $n > 0 ? ($n . ' Bewertung' . ($n === 1 ? '' : 'en') . ' · Ø ⭐ ' . number_format(($sumS + $sumC + $sumK) / ($n * 3), 2, ',', '.')) : 'Noch keine Bewertungen'; ?>
                        </div>
                    </div>
                    <?php if ($raters): ?>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
                        <?php foreach ($raters as $ru): ?>
                        <button type="button" class="bt-models-btn beta-user-btn"
                            data-title="<?php echo e($ru['username'] . ' — ' . $bm['display_name']); ?>"
                            data-items='<?php echo e(json_encode($ru['items'], JSON_UNESCAPED_UNICODE)); ?>'>
                            <?php echo e($ru['username']); ?> (<?php echo count($ru['items']); ?>)
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <!-- Popup: Bewertungen eines Users zu einem Modell -->
                <div id="beta-modal" class="bt-modal-back hidden" onclick="if(event.target===this)this.classList.add('hidden')">
                    <div class="bt-modal">
                        <div class="bt-modal-head"><span id="beta-modal-title">Bewertungen</span>
                            <button type="button" class="bt-btn secondary" onclick="document.getElementById('beta-modal').classList.add('hidden')">✕</button></div>
                        <div id="beta-modal-body"></div>
                    </div>
                </div>
                <style>
                    .bt-modal-back { position:fixed; inset:0; background:rgba(0,0,0,.6); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; }
                    .bt-modal-back.hidden { display:none; }
                    .bt-modal { background:#1c1526; border:1px solid #3a2b4d; border-radius:14px; padding:20px 22px; max-width:520px; width:100%; max-height:80vh; overflow:auto; box-shadow:0 20px 60px rgba(0,0,0,.5); }
                    .bt-modal-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; font-weight:600; font-size:1.05rem; }
                    .bt-models-btn { padding:5px 12px; border-radius:999px; cursor:pointer; font:inherit; font-size:.8rem; font-weight:600;
                        background:rgba(145,71,255,0.14); border:1px solid rgba(145,71,255,0.4); color:#c79bef; }
                    .bt-models-btn:hover { background:rgba(145,71,255,0.28); }
                    .beta-fb { padding:12px 0; border-top:1px solid #2e2340; }
                    .beta-fb:first-child { border-top:0; }
                    .beta-fb .qline { display:flex; justify-content:space-between; gap:10px; font-size:.86rem; padding:2px 0; }
                    .beta-fb .stars { color:#f5c518; white-space:nowrap; }
                    .beta-fb .cmt { margin-top:6px; font-size:.86rem; color:var(--text-secondary,#cbb8e6); background:rgba(255,255,255,0.04); border-radius:8px; padding:8px 10px; white-space:pre-wrap; }
                    .beta-fb .date { color:var(--text-muted,#9b8bb4); font-size:.72rem; margin-top:6px; }
                </style>
                <script>
                (function () {
                    var modal = document.getElementById('beta-modal');
                    if (!modal) return;
                    var body = document.getElementById('beta-modal-body'), title = document.getElementById('beta-modal-title');
                    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
                    var stars = function (n) { n = Math.max(0, Math.min(5, Number(n) || 0)); return '★★★★★☆☆☆☆☆'.slice(5 - n, 10 - n); };
                    var Q = ['Story-Telling', 'Kontext behalten', 'Weiter nutzen?'];
                    document.querySelectorAll('.beta-user-btn').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var items = [];
                            try { items = JSON.parse(btn.getAttribute('data-items') || '[]'); } catch (e) {}
                            title.textContent = btn.getAttribute('data-title') || 'Bewertungen';
                            body.innerHTML = items.map(function (it) {
                                var rows = [['story', it.story], ['context', it.context], ['continue', it['continue']]].map(function (p, i) {
                                    return '<div class="qline"><span>' + Q[i] + '</span><span class="stars">' + stars(p[1]) + ' <span style="color:#9b8bb4">' + Number(p[1]) + '/5</span></span></div>';
                                }).join('');
                                var cmt = it.comment ? '<div class="cmt">' + esc(it.comment) + '</div>' : '';
                                return '<div class="beta-fb">' + rows + cmt + '<div class="date">' + esc(it.at) + '</div></div>';
                            }).join('') || '<p class="muted">Keine Einträge.</p>';
                            modal.classList.remove('hidden');
                        });
                    });
                })();
                </script>
            </div>

            <?php elseif ($tab === 'settings'): ?>
            <div class="bt-card">
                <h3>App-Einstellungen</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="bt_action" value="settings_save">

                    <div class="bt-subhead">🔞 NSFW &amp; Kosten</div>
                    <div class="bt-checks" style="margin: 0 0 12px;">
                        <label><input type="checkbox" name="nsfw_module_enabled" <?php echo $nsfwEnabled ? 'checked' : ''; ?>> NSFW-Modul aktiv <span class="muted">(aus = Store-konforme Variante, Toggle &amp; NSFW-Modelle verschwinden app-weit)</span></label>
                    </div>
                    <div class="bt-fields">
                        <label class="bt-field sm"><span>Tageswarnung pro User ($)</span><input class="bt-input" type="text" name="daily_warning_eur" value="<?php echo e($dailyWarn); ?>"></label>
                    </div>

                    <div class="bt-subhead">🪄 „Erstellen lassen" (KI-Charakter/Identität)</div>
                    <div class="bt-fields">
                        <label class="bt-field md" title="Erzeugt die Figur als Text (0,05 $)."><span>Text-Modell – Normal</span>
                            <select class="bt-select" name="creator_model_id">
                                <option value="0">– Automatisch (erstes verfügbares) –</option>
                                <?php foreach ($creatorModels as $cm): ?>
                                    <option value="<?php echo (int) $cm['id']; ?>" <?php echo $creatorModelId === (int) $cm['id'] ? 'selected' : ''; ?>><?php echo e($cm['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="bt-field md" title="Wird genutzt, wenn der Nutzer beim Erstellen „NSFW-Charakter" anhakt."><span>Text-Modell – NSFW</span>
                            <select class="bt-select" name="creator_model_nsfw_id">
                                <option value="0">– Automatisch (erstes NSFW-Modell) –</option>
                                <?php foreach ($creatorModels as $cm): if (empty($cm['nsfw_allowed'])) continue; ?>
                                    <option value="<?php echo (int) $cm['id']; ?>" <?php echo $creatorModelNsfwId === (int) $cm['id'] ? 'selected' : ''; ?>><?php echo e($cm['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="bt-field md" title="Bild-KI für den mitgenerierten Avatar (+0,05 $)."><span>Avatar-Bild-KI – Normal</span>
                            <select class="bt-select" name="creator_image_generator_id">
                                <option value="0">– Automatisch (erster Bild-Generator) –</option>
                                <?php foreach ($imageGens as $ig): ?>
                                    <option value="<?php echo (int) $ig['id']; ?>" <?php echo $creatorImageGenId === (int) $ig['id'] ? 'selected' : ''; ?>><?php echo e($ig['display_name']); ?> (<?php echo e($ig['api_style']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="bt-field md" title="NSFW-Avatar (nackt; nur venice_image kann Nacktheit, +0,10 $)."><span>Avatar-Bild-KI – NSFW</span>
                            <select class="bt-select" name="creator_image_generator_nsfw_id">
                                <option value="0">– Automatisch (teuerster venice-Generator) –</option>
                                <?php foreach ($imageGens as $ig): ?>
                                    <option value="<?php echo (int) $ig['id']; ?>" <?php echo $creatorImageGenNsfwId === (int) $ig['id'] ? 'selected' : ''; ?>><?php echo e($ig['display_name']); ?> (<?php echo e($ig['api_style']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="bt-subhead">🤖 AI vs AI</div>
                    <div class="bt-fields">
                        <label class="bt-field md" title="Muss sich von Seite A unterscheiden."><span>Standard-Modell für die zweite Seite</span>
                            <select class="bt-select" name="aivsai_second_model_id">
                                <option value="0">– Automatisch (erstes anderes Modell) –</option>
                                <?php foreach ($creatorModels as $cm): ?>
                                    <option value="<?php echo (int) $cm['id']; ?>" <?php echo $aivsaiSecondModelId === (int) $cm['id'] ? 'selected' : ''; ?>><?php echo e($cm['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="bt-subhead">📖 Willkommens-Tutorial (ALT — ersetzt durch die interaktive Tour unten)</div>
                    <p class="muted" style="font-size:.82rem; margin-bottom:10px;">Wird jedem User einmalig gezeigt; kann erst nach 15&nbsp;Sek. mit „ICH AKZEPTIERE" bestätigt werden. Leer lassen = kein Tutorial. Beim Text werden Zeilen, die mit <code>-</code>, <code>*</code> oder <code>•</code> beginnen, als Aufzählungspunkte dargestellt.</p>
                    <div class="bt-fields">
                        <label class="bt-field md"><span>Titel</span><input class="bt-input" type="text" name="tutorial_title" value="<?php echo e($tutTitle); ?>" placeholder="Willkommen bei BEYOND TELLING"></label>
                        <label class="bt-field md"><span>Untertitel</span><input class="bt-input" type="text" name="tutorial_subtitle" value="<?php echo e($tutSubtitle); ?>" placeholder="So funktioniert die App"></label>
                        <label class="bt-field md"><span>Logo über der Nachricht (optional)</span><input class="bt-input" type="file" name="tutorial_logo" accept="image/*"></label>
                        <?php if ($tutLogo): ?>
                        <div class="bt-field sm"><span>Aktuelles Logo</span>
                            <span style="display:flex;align-items:center;gap:10px;">
                                <img src="?media_setting=tutorial_logo" alt="Logo" style="height:44px;border-radius:8px;">
                                <label class="muted" style="cursor:pointer;"><input type="checkbox" name="tutorial_logo_clear"> entfernen</label>
                            </span>
                        </div>
                        <?php endif; ?>
                        <label class="bt-field full"><span>Text (optional als Liste mit -/*/•)</span><textarea class="bt-input" name="tutorial_text" rows="6" placeholder="- Erstelle eigene Charaktere&#10;- Chatte in verschiedenen Modi&#10;- Guthaben aufladen für mehr KI"><?php echo e($tutText); ?></textarea></label>
                    </div>

                    <div class="bt-actions"><button class="bt-btn" type="submit">Speichern</button></div>
                </form>
            </div>

            <div class="bt-card">
                <?php
                require_once '/var/www/html/beyond-telling/api/lib/core.php';
                $tourSteps = bt_tour_default_steps();
                $tourOverrides = [];
                foreach ($pdo->query("SELECT step_key, title, body FROM bt_tour_steps") as $r) $tourOverrides[$r['step_key']] = $r;
                ?>
                <h3>🧭 Interaktive Tutorial-Tour</h3>
                <p class="muted" style="font-size:.85rem; margin-bottom:12px;">Die geführte Tour ersetzt das alte Willkommens-Tutorial. Reihenfolge und welches Element markiert wird, stehen fest — hier passt du nur die <strong>Texte</strong> an. Leeres Feld = der graue Standardtext (Platzhalter) wird verwendet.<br>
                In Titel und Text kannst du <code>{free_limit}</code> (Gratis-Anfragen pro Tag, aktuell aus den Einstellungen) und <code>{free_reset}</code> (Reset-Uhrzeit) schreiben — die werden beim Anzeigen automatisch durch die echten Werte ersetzt, damit die Zahlen nicht veralten.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="bt_action" value="tour_save">
                    <?php $curPage = ''; foreach ($tourSteps as $st):
                        if ($st['page'] !== $curPage) { $curPage = $st['page']; echo '<div class="bt-subhead" style="margin-top:14px;">' . e($curPage) . '</div>'; }
                        $ov = $tourOverrides[$st['key']] ?? null;
                    ?>
                        <div class="bt-block" style="margin-bottom:8px;">
                            <div class="bt-block-title" style="font-size:.85rem;">📍 <?php echo e($st['label']); ?> <span class="muted" style="font-weight:400;">(<?php echo e($st['key']); ?>)</span></div>
                            <div class="bt-fields">
                                <label class="bt-field md"><span>Titel</span><input class="bt-input" type="text" name="tour_<?php echo e($st['key']); ?>_title" value="<?php echo e($ov['title'] ?? ''); ?>" placeholder="<?php echo e($st['title']); ?>"></label>
                                <label class="bt-field full"><span>Text</span><textarea class="bt-input" name="tour_<?php echo e($st['key']); ?>_body" rows="2" placeholder="<?php echo e($st['text']); ?>"><?php echo e($ov['body'] ?? ''); ?></textarea></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="bt-actions"><button class="bt-btn" type="submit">Tour-Texte speichern</button></div>
                </form>
                <hr style="border:none;border-top:1px solid rgba(255,255,255,.12);margin:16px 0;">
                <form method="post" onsubmit="return confirm('Die Tour wird dann bei ALLEN Usern beim nächsten App-Start automatisch (nicht überspringbar) erneut angezeigt. Fortfahren?');">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="bt_action" value="tour_reset_all">
                    <p class="muted" style="font-size:.85rem;">Setzt die Tour für alle User zurück, sodass sie beim nächsten Öffnen erneut läuft.</p>
                    <div class="bt-actions"><button class="bt-btn" type="submit">🔄 Tour allen Usern erneut zeigen</button></div>
                </form>
            </div>

            <?php elseif ($tab === 'system'): ?>
            <div class="bt-card">
                <h3>APK &amp; Links</h3>
                <p class="muted">
                    Werbeseite/Landingpage: <a href="/beyond-telling/" style="color:#9147FF;" target="_blank">likedennis.de/beyond-telling/</a><br>
                    Web-App (identisch mit App-Inhalt): <a href="/beyond-telling/app/" style="color:#9147FF;" target="_blank">likedennis.de/beyond-telling/app/</a><br>
                    APK-Datei: <?php $apk = glob('/var/www/html/beyond-telling/apk/*.apk'); echo $apk ? e(basename($apk[0])) . ' (' . number_format(filesize($apk[0]) / 1048576, 1, ',', '.') . ' MB)' : 'noch nicht gebaut'; ?>
                </p>
            </div>
            <?php endif; ?>
                </div><!-- /bt-content -->
            </div><!-- /bt-layout -->
        </div>
    </main>
</body>
</html>
