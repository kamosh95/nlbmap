<?php
// Shared helper: Fetch dealers filtered by TM's assigned districts
function get_dealers_for_user(PDO $pdo): array {
    $role    = $_SESSION['role'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;

    $assigned = '';
    if ($role === 'tm') {
        $u = $pdo->prepare("SELECT assigned_districts FROM users WHERE id = ?");
        $u->execute([$user_id]);
        $assigned = $u->fetchColumn() ?? '';
    }

    if ($role === 'tm' && !empty($assigned)) {
        $districts = array_filter(array_map('trim', explode(',', $assigned)));
        if (!empty($districts)) {
            $placeholders = implode(',', array_fill(0, count($districts), '?'));
            $stmt = $pdo->prepare("SELECT dealer_code, name, district FROM dealers WHERE district IN ($placeholders) ORDER BY dealer_code ASC");
            $stmt->execute($districts);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    // Admin / no restriction
    return $pdo->query("SELECT dealer_code, name, district FROM dealers ORDER BY dealer_code ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
