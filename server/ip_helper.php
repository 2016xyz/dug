<?php
// ip_helper.php
function get_client_ip() {
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    // Fallback to REMOTE_ADDR even if private (e.g. local dev)
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

function log_server($pdo, $level, $message) {
    try {
        $ip = get_client_ip();
        $stmt = $pdo->prepare("INSERT INTO server_logs (level, message, ip) VALUES (?, ?, ?)");
        $stmt->execute([$level, $message, $ip]);
    } catch (Exception $e) {
        // ignore
    }
}
?>
