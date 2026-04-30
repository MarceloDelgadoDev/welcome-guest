<?php

// --- Config ---
define('LOG_FILE', __DIR__ . '/ips.log');
define('MAX_ENTRIES', 50);
define('DISPLAY_LIMIT', 20);

// --- Captura o IP do visitante ---
function get_visitor_ip(): string {
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return 'unknown';
}

// --- Lê entradas do log ---
function read_log(): array {
    if (!file_exists(LOG_FILE)) {
        return [];
    }

    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return [];
    }

    $entries = [];
    foreach (array_reverse($lines) as $line) {
        // datetime | ip | …UA pode conter " | "; só os dois primeiros delimitadores fecham ip/datetime
        $p1 = strpos($line, ' | ');
        if ($p1 === false) {
            continue;
        }
        $p2 = strpos($line, ' | ', $p1 + 3);
        if ($p2 === false) {
            continue;
        }
        $entries[] = [
            'datetime' => substr($line, 0, $p1),
            'ip'       => substr($line, $p1 + 3, $p2 - $p1 - 3),
            'ua'       => substr($line, $p2 + 3),
        ];
    }

    return $entries;
}

/** macOS 10_15_7 → 10.15 (só o que costuma importar na lista) */
function macos_version_short(string $underscore): string {
    $parts = explode('_', $underscore);

    return ($parts[0] ?? '0') . '.' . ($parts[1] ?? '0');
}

// --- Uma linha: só navegador + SO (tooltip mantém o UA completo) ---
function client_one_line(string $ua): string {
    $ua = trim($ua);
    if ($ua === '' || strcasecmp($ua, 'unknown') === 0) {
        return 'unknown';
    }

    $browser = '';
    $os      = '';

    if (preg_match('/Android (\d+(?:\.\d+)?)/', $ua, $m)) {
        $os = 'Android ' . $m[1];
    } elseif (preg_match('/iPhone OS ([\d_]+)/', $ua, $m)) {
        $os = 'iOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/CPU iPad OS ([\d_]+)/', $ua, $m) || preg_match('/iPad.*OS ([\d_]+)/', $ua, $m)) {
        $os = 'iPadOS ' . str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Mac OS X ([\d_]+)/', $ua, $m)) {
        $os = 'macOS ' . macos_version_short($m[1]);
    } elseif (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
        $map = [
            '10.0' => 'Win 10/11',
            '6.3'  => 'Win 8.1',
            '6.2'  => 'Win 8',
            '6.1'  => 'Win 7',
        ];
        $os = $map[$m[1]] ?? 'Windows';
    } elseif (stripos($ua, 'CrOS') !== false) {
        $os = 'Chrome OS';
    } elseif (stripos($ua, 'Linux') !== false && stripos($ua, 'Android') === false) {
        $os = 'Linux';
    }

    $major = static function (string $v): string {
        return explode('.', $v)[0];
    };

    if (preg_match('/Edg(?:e|iOS|A)?\/([\d.]+)/', $ua, $m)) {
        $browser = 'Edge ' . $major($m[1]);
    } elseif (preg_match('/OPR\/([\d.]+)/', $ua, $m)) {
        $browser = 'Opera ' . $major($m[1]);
    } elseif (preg_match('/Chrome\/([\d.]+)/', $ua, $m)) {
        $browser = 'Chrome ' . $major($m[1]);
    } elseif (preg_match('/Firefox\/([\d.]+)/', $ua, $m)) {
        $browser = 'Firefox ' . $major($m[1]);
    } elseif (preg_match('/Version\/([\d.]+).*Safari\//', $ua, $m) && stripos($ua, 'Chrome') === false) {
        $browser = 'Safari ' . $major($m[1]);
    } elseif (preg_match('/Safari\/[\d.]+/', $ua) && stripos($ua, 'Chrome') === false && stripos($ua, 'Firefox') === false) {
        $browser = 'Safari';
    } elseif (preg_match('/^curl\/([\d.]+)/i', $ua, $m)) {
        $browser = 'curl ' . $m[1];
        if ($os === '') {
            $os = 'CLI';
        }
    } elseif (preg_match('/wget/i', $ua)) {
        $browser = 'wget';
    }

    $bits = array_values(array_filter([$browser, $os]));
    if ($bits !== []) {
        return implode(' · ', $bits);
    }

    $max = 52;

    return (mb_strlen($ua) > $max ? mb_substr($ua, 0, $max) . '…' : $ua);
}

// --- Grava o IP atual no log ---
function write_log(string $ip): void {
    $ua       = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $datetime = date('Y-m-d H:i:s');
    $entry    = "{$datetime} | {$ip} | {$ua}" . PHP_EOL;

    // Adiciona ao log
    file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);

    // Mantém apenas os últimos MAX_ENTRIES registros
    if (file_exists(LOG_FILE)) {
        $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines && count($lines) > MAX_ENTRIES) {
            $lines = array_slice($lines, -MAX_ENTRIES);
            file_put_contents(LOG_FILE, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
        }
    }
}

// --- Execução ---
$visitor_ip = get_visitor_ip();
write_log($visitor_ip);
$all_entries = read_log();
$entries = array_slice($all_entries, 0, DISPLAY_LIMIT);
$visit_count = count($all_entries);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome, Guest</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg:       #0d0d0d;
            --surface:  #141414;
            --border:   #222;
            --text:     #e2e2e2;
            --muted:    #666;
            --accent:   #7c6af7;
            --accent2:  #56cfb2;
            --font:     'Courier New', Courier, monospace;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 48px 16px;
        }

        header {
            text-align: center;
            margin-bottom: 48px;
        }

        header h1 {
            font-size: clamp(1.8rem, 5vw, 2.8rem);
            font-weight: normal;
            letter-spacing: 0.12em;
            color: var(--accent);
        }

        header p {
            margin-top: 12px;
            color: var(--muted);
            font-size: 0.85rem;
            letter-spacing: 0.05em;
        }

        .your-ip {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 3px solid var(--accent2);
            border-radius: 6px;
            padding: 16px 24px;
            margin-bottom: 40px;
            font-size: 0.9rem;
            max-width: 640px;
            width: 100%;
        }

        .your-ip span {
            color: var(--accent2);
        }

        .log-wrapper {
            width: 100%;
            max-width: 640px;
        }

        .log-wrapper h2 {
            font-size: 0.75rem;
            letter-spacing: 0.15em;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        thead th {
            text-align: left;
            padding: 8px 12px;
            color: var(--muted);
            font-weight: normal;
            border-bottom: 1px solid var(--border);
            font-size: 0.75rem;
            letter-spacing: 0.08em;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }

        tbody tr:hover {
            background: var(--surface);
        }

        tbody tr:first-child td {
            color: var(--accent);
        }

        td {
            padding: 10px 12px;
            vertical-align: top;
        }

        td.ua {
            font-size: 0.8rem;
            max-width: min(340px, 38vw);
            vertical-align: middle;
            line-height: 1.25;
        }

        .ua-line {
            display: block;
            font-weight: 600;
            letter-spacing: 0.02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        tbody tr:first-child td.ua .ua-line {
            color: var(--accent);
        }

        tbody tr:not(:first-child) td.ua .ua-line {
            color: var(--muted);
            font-weight: normal;
        }

        .empty {
            color: var(--muted);
            font-size: 0.85rem;
            padding: 24px 0;
        }

        footer {
            margin-top: 64px;
            color: var(--muted);
            font-size: 0.75rem;
            letter-spacing: 0.06em;
        }

        footer a {
            color: var(--muted);
            text-decoration: none;
        }

        footer a:hover {
            color: var(--text);
        }
    </style>
</head>
<body>

    <header>
        <h1>welcome, guest</h1>
        <p>this server has been visited <?= $visit_count ?> time<?= $visit_count !== 1 ? 's' : '' ?></p>
    </header>

    <div class="your-ip">
        your ip &rarr; <span><?= htmlspecialchars($visitor_ip) ?></span>
        &nbsp;&mdash;&nbsp;
        <span style="color: var(--muted); font-size: 0.82rem;"><?= date('Y-m-d H:i:s') ?></span>
    </div>

    <div class="log-wrapper">
        <h2>recent visitors</h2>

        <?php if (empty($entries)): ?>
            <p class="empty">no entries yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>datetime</th>
                        <th>ip</th>
                        <th>client</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $i => $entry): ?>
                        <tr>
                            <td><?= $visit_count - $i ?></td>
                            <td><?= htmlspecialchars($entry['datetime']) ?></td>
                            <td><?= htmlspecialchars($entry['ip']) ?></td>
                            <?php
                                $rawUa       = $entry['ua'];
                                $clientLabel = client_one_line($rawUa);
                            ?>
                            <td class="ua" title="<?= htmlspecialchars($rawUa) ?>">
                                <span class="ua-line"><?= htmlspecialchars($clientLabel) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer>
        <a href="https://github.com/MarceloDelgadoDev/welcome-guest" target="_blank">github</a>
        &nbsp;&mdash;&nbsp;
        welcome-guest
    </footer>

</body>
</html>
