<?php
$out = [];
$out[] = '=== LEGACY DIAG ' . date('c') . ' ===';

$root = '/var/www/vhosts/bahman.ir/httpdocs';
$myconf = $root . '/libs/myconf.php';
$legacy = $root . '/templates/bahman/legacy_redirects.php';
$index = $root . '/index.php';

$out[] = 'myconf exists=' . (is_file($myconf) ? 'yes' : 'no');
$out[] = 'legacy exists=' . (is_file($legacy) ? 'yes' : 'no');
$out[] = 'legacy size=' . (is_file($legacy) ? filesize($legacy) : 0);

if (is_readable($myconf)) {
    $raw = file_get_contents($myconf);
    if (preg_match('/define\s*\(\s*"TEMPLATE_NAME"\s*,\s*"([^"]+)"/', $raw, $m)) {
        $out[] = 'TEMPLATE_NAME=' . $m[1];
    }
    // show related lines
    foreach (explode("\n", $raw) as $i => $line) {
        if (stripos($line, 'TEMPLATE_NAME') !== false || stripos($line, 'SITE_URL') !== false) {
            $out[] = 'myconf L' . ($i + 1) . ': ' . trim($line);
        }
    }
}

if (is_readable($index)) {
    $raw = file_get_contents($index);
    $out[] = 'index has legacy include=' . (strpos($raw, 'legacy_redirects') !== false ? 'yes' : 'no');
    if (preg_match('/legacyApplyFn.*?_apply_legacy_redirects/s', $raw, $m)) {
        $out[] = 'index apply snippet: ' . preg_replace('/\s+/', ' ', $m[0]);
    }
}

if (is_readable($legacy)) {
    $raw = file_get_contents($legacy);
    $out[] = 'legacy has 231=>' . (preg_match('/\b231\s*=>\s*(\d+)/', $raw, $m) ? $m[1] : 'NO');
    $out[] = 'legacy fn=' . (strpos($raw, 'function bahman_apply_legacy_redirects') !== false ? 'yes' : 'no');
    // Check namespace
    if (preg_match('/^namespace\s+([^;]+);/m', $raw, $m)) {
        $out[] = 'legacy namespace=' . $m[1];
    }
}

// DB: status of destination product 716 and old 231
$cfg = $root . '/ewcfg.php';
$raw = file_get_contents($cfg);
$host = 'localhost';
$user = $pass = $db = null;
if (preg_match_all('/"host"\s*=>\s*"([^"]+)"\s*,\s*"port"\s*=>\s*"([^"]+)"\s*,\s*"user"\s*=>\s*"([^"]+)"\s*,\s*"pass"\s*=>\s*([\'"])(.*?)\4\s*,\s*"db"\s*=>\s*"([^"]+)"/s', $raw, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $m) {
        if ($m[6] === 'bmcars_cms') {
            $host = $m[1];
            $user = $m[3];
            $pass = $m[5];
            $db = $m[6];
            break;
        }
    }
}
$mysqli = @new mysqli($host, $user, $pass, $db);
if (!$mysqli->connect_errno) {
    $mysqli->set_charset('utf8mb4');
    foreach ([231, 716, 217, 714] as $id) {
        $r = $mysqli->query("SELECT id, status, content_type_id FROM cms_contents WHERE id=$id");
        $row = $r ? $r->fetch_assoc() : null;
        $title = '';
        if ($row) {
            $t = $mysqli->query("SELECT v.value FROM cms_content_field_values v JOIN cms_content_type_fields f ON f.id=v.content_type_field_id AND f.`key`='title' WHERE v.content_id=$id LIMIT 1");
            $title = ($x = $t->fetch_assoc()) ? $x['value'] : '';
        }
        $out[] = "content $id: " . json_encode($row, JSON_UNESCAPED_UNICODE) . " title=[$title]";
    }
} else {
    $out[] = 'db fail: ' . $mysqli->connect_error;
}

// Live HTTP checks from the server itself
$out[] = '';
$out[] = '=== CURL LOCAL ===';
foreach ([
    'https://127.0.0.1/product/231',
    'https://127.0.0.1/product/231/FORCE-F38B',
    'http://127.0.0.1/product/231',
    'https://bahman.ir/product/231',
] as $url) {
    $cmd = 'curl -skI --resolve bahman.ir:443:127.0.0.1 --resolve bahman.ir:80:127.0.0.1 --connect-timeout 5 --max-time 15 -H "Host: bahman.ir" ' . escapeshellarg($url) . ' 2>&1 | head -20';
    $out[] = 'URL ' . $url;
    $out[] = trim((string) shell_exec($cmd));
    $out[] = '---';
}

// Also via nginx public IP if different
$cmd = 'curl -skI --connect-timeout 5 --max-time 15 -H "Host: bahman.ir" https://172.20.14.76/product/231 2>&1 | head -20';
$out[] = 'via 172.20.14.76:';
$out[] = trim((string) shell_exec($cmd));

$text = implode("\n", $out) . "\n";
file_put_contents('/tmp/legacydiag_report.txt', $text);
@mkdir('/opt/psa/var/modules/legacydiag', 0755, true);
@file_put_contents('/opt/psa/var/modules/legacydiag/report.txt', $text);
echo $text;
