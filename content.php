<?php
$pluginDirectory = __DIR__;
$configFile = $pluginDirectory . '/config/devices.json';
if (!file_exists($configFile)) { @file_put_contents($configFile, json_encode(['devices'=>[]], JSON_PRETTY_PRINT)); }
$config = json_decode(@file_get_contents($configFile), true);
if (!is_array($config)) $config = ['devices'=>[]];
$message = '';
function goveeSend($ip, $payload) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return 'Ungültige IPv4-Adresse.';
    $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$sock) return 'UDP-Socket konnte nicht erstellt werden.';
    $json = json_encode(['msg'=>$payload], JSON_UNESCAPED_SLASHES);
    $sent = @socket_sendto($sock, $json, strlen($json), 0, $ip, 4003);
    @socket_close($sock);
    return ($sent === false) ? 'UDP-Senden fehlgeschlagen.' : 'Befehl gesendet an ' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? ''); $ip = trim($_POST['ip'] ?? '');
        if ($name !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $config['devices'][$name] = $ip;
            @file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
            $message = 'Gerät gespeichert.';
        } else $message = 'Bitte Gerätename und gültige IPv4-Adresse angeben.';
    } elseif ($action === 'send') {
        $ip = trim($_POST['ip'] ?? ''); $cmd = $_POST['cmd'] ?? '';
        if ($cmd === 'on' || $cmd === 'off') $message = goveeSend($ip, ['cmd'=>'turn','data'=>['value'=>$cmd==='on'?1:0]]);
        elseif ($cmd === 'brightness') {
            $v = filter_var($_POST['brightness'] ?? null, FILTER_VALIDATE_INT);
            $message = ($v !== false && $v >= 1 && $v <= 100) ? goveeSend($ip, ['cmd'=>'brightness','data'=>['value'=>$v]]) : 'Helligkeit muss 1–100 sein.';
        } elseif ($cmd === 'color') {
            $r = filter_var($_POST['r'] ?? null, FILTER_VALIDATE_INT); $g = filter_var($_POST['g'] ?? null, FILTER_VALIDATE_INT); $b = filter_var($_POST['b'] ?? null, FILTER_VALIDATE_INT);
            if ($r!==false && $g!==false && $b!==false && $r>=0 && $r<=255 && $g>=0 && $g<=255 && $b>=0 && $b<=255) $message = goveeSend($ip, ['cmd'=>'colorwc','data'=>['color'=>['r'=>$r,'g'=>$g,'b'=>$b],'colorTemInKelvin'=>0]]);
            else $message = 'RGB-Werte müssen jeweils 0–255 sein.';
        } elseif ($cmd === 'effect') {
            $effect = $_POST['effect'] ?? 'rainbow'; $seconds = max(1, min(60, intval($_POST['seconds'] ?? 5)));
            $cmdline = escapeshellarg($pluginDirectory . '/scripts/govee_lan.py') . ' ' . escapeshellarg($ip) . ' effect ' . escapeshellarg($effect) . ' ' . $seconds . ' >/dev/null 2>&1 &';
            exec('/usr/bin/python3 ' . $cmdline);
            $message = 'Effekt gestartet (falls der Prozess auf diesem FPP-System gestartet werden darf).';
        } elseif ($cmd === 'stop') {
            exec('/usr/bin/python3 ' . escapeshellarg($pluginDirectory . '/scripts/govee_lan.py') . ' ' . escapeshellarg($ip) . ' stop-effect 2>&1', $out, $rc);
            $message = $rc === 0 ? 'Stop-Befehl verarbeitet.' : 'Effekt konnte nicht gestoppt werden.';
        }
    }
}
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Govee LAN Control</title>
<style>body{font-family:Arial,sans-serif;max-width:900px;margin:1.5rem auto;padding:0 1rem;color:#222}fieldset{margin:1rem 0;padding:1rem;border:1px solid #bbb;border-radius:8px}label{display:inline-block;margin:.35rem .5rem .35rem 0}input,select,button{padding:.45rem;margin:.2rem}button{cursor:pointer}.msg{background:#eef7ee;padding:.7rem;border-radius:6px}.muted{color:#555}</style></head><body>
<h1>Govee LAN Control</h1><p class="muted">Lokale UDP-Steuerung über Port 4003. LAN Control muss in der Govee-App aktiviert sein.</p>
<?php if ($message!==''): ?><p class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<fieldset><legend>Gerät speichern</legend><form method="post"><input type="hidden" name="action" value="save"><label>Name <input name="name" required placeholder="Wohnzimmer"></label><label>IPv4 <input name="ip" required placeholder="192.168.1.50" pattern="[0-9.]+"></label><button>Speichern</button></form>
<?php if (!empty($config['devices'])): ?><p>Gespeicherte Geräte:</p><ul><?php foreach($config['devices'] as $n=>$ip): ?><li><?php echo htmlspecialchars($n,ENT_QUOTES,'UTF-8').' — '.htmlspecialchars($ip,ENT_QUOTES,'UTF-8'); ?></li><?php endforeach; ?></ul><?php endif; ?></fieldset>
<fieldset><legend>Manuelle Steuerung</legend><form method="post"><input type="hidden" name="action" value="send"><label>IP <input name="ip" required placeholder="192.168.1.50"></label><label>Aktion <select name="cmd"><option value="on">Ein</option><option value="off">Aus</option><option value="brightness">Helligkeit</option><option value="color">RGB-Farbe</option><option value="effect">Farbeffekt</option><option value="stop">Effekt stoppen</option></select></label><br><label>Helligkeit <input type="number" name="brightness" min="1" max="100" value="60"></label><label>R <input type="number" name="r" min="0" max="255" value="255"></label><label>G <input type="number" name="g" min="0" max="255" value="0"></label><label>B <input type="number" name="b" min="0" max="255" value="80"></label><br><label>Effekt <select name="effect"><option value="rainbow">Regenbogen</option><option value="party">Party</option><option value="fire">Feuer</option><option value="candle">Kerzenlicht</option></select></label><label>Effektdauer (Minuten) <input type="number" name="seconds" min="1" max="60" value="5"></label><button>Senden</button></form></fieldset>
<p class="muted">Hinweis: Der Effektgenerator sendet fortlaufend Farben; er ersetzt keine in der Govee-App gespeicherten Szenen. UDP-Senden bestätigt nicht, dass die Lampe den Befehl ausgeführt hat.</p></body></html>
