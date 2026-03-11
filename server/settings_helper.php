<?php
// Settings Helper
function get_settings($pdo) {
    $stmt = $pdo->query("SELECT * FROM settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['key']] = $r['value'];
    }
    return $settings;
}

function update_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
    $stmt->execute([$key, $value, $value]);
}
?>
