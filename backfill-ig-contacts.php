<?php
/**
 * Backfill de nombres de contacto de Instagram (T8).
 * Recorre conversaciones IG con contact_name vacío / "Sin nombre" / "Usuario Instagram XXXX",
 * consulta el perfil real vía get_instagram_profile() y actualiza contact_name + contact_avatar_url.
 *
 * Uso (con el PHP del web server):
 *   C:/wamp64/bin/php/php8.2.26/php.exe backfill-ig-contacts.php dry   # simula, no escribe
 *   C:/wamp64/bin/php/php8.2.26/php.exe backfill-ig-contacts.php       # aplica
 *
 * En PROD: subir y correr por CLI/SSH. Requiere bot_token del canal IG cargado.
 * NO subir a prod como endpoint web público (es script de mantenimiento).
 */
require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/omnichannel-controller.php';

$controller = new OmnichannelController();
global $wpdb;
$p = $wpdb->prefix . 'omnichannel_';
$dry = (($argv[1] ?? '') === 'dry');

$channels = $wpdb->get_results("SELECT * FROM {$p}channels WHERE channel_type = 'instagram'");
if (!$channels) { exit("No hay canales Instagram.\n"); }

$total = 0; $updated = 0; $skipped = 0;
foreach ($channels as $ch) {
    if (empty($ch->bot_token)) { echo "Canal {$ch->id} sin bot_token — saltado.\n"; continue; }
    $convs = $wpdb->get_results($wpdb->prepare(
        "SELECT id, external_contact_id, contact_name FROM {$p}conversations
         WHERE channel_id = %d AND external_contact_id <> ''
           AND (contact_name IS NULL OR contact_name = '' OR contact_name = 'Sin nombre'
                OR contact_name LIKE 'Usuario Instagram %')",
        $ch->id
    ));
    echo "Canal {$ch->id} ({$ch->channel_name}): " . count($convs) . " conversaciones a revisar.\n";
    foreach ($convs as $c) {
        $total++;
        $profile = $controller->get_instagram_profile($ch, $c->external_contact_id);
        if (!$profile) { echo "  conv {$c->id}: sin perfil (igsid eco/propia cuenta o error) — saltada.\n"; $skipped++; continue; }
        $name = trim((string)($profile['name'] ?? ''));
        $user = trim((string)($profile['username'] ?? ''));
        $display = ($name && $user) ? "{$name} (@{$user})" : ($name ?: ($user ? "@{$user}" : $c->contact_name));
        echo "  conv {$c->id}: '{$c->contact_name}' -> '{$display}'" . ($dry ? " [dry]" : "") . "\n";
        if (!$dry) {
            $upd = ['contact_name' => $display];
            if (!empty($profile['profile_pic'])) { $upd['contact_avatar_url'] = $profile['profile_pic']; }
            $wpdb->update($p . 'conversations', $upd, ['id' => (int)$c->id]);
            $updated++;
        }
    }
}
echo "\n" . ($dry ? "DRY RUN — nada escrito. " : "Aplicado. ") . "Revisadas: {$total}, actualizadas: {$updated}, saltadas: {$skipped}.\n";
