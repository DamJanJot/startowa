<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$notice = '';
$error = '';

$roles = [];
if (startowa_table_exists($pdo, 'startowa_roles')) {
    $stmt = $pdo->query('SELECT `key`, `name` FROM startowa_roles ORDER BY is_system DESC, name ASC');
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($roles)) {
    $stmt = $pdo->query('SELECT DISTINCT LOWER(TRIM(COALESCE(rola, "user"))) AS role_key FROM uzytkownicy ORDER BY role_key ASC');
    $roleRows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($roleRows as $roleKey) {
        $normalized = startowa_normalize_role((string) $roleKey);
        $roles[] = ['key' => $normalized, 'name' => ucfirst($normalized)];
    }
}

if (empty($roles)) {
    $roles = [
        ['key' => 'user', 'name' => 'User'],
        ['key' => 'admin', 'name' => 'Admin'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_user_role') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $newRole = startowa_normalize_role((string) ($_POST['role'] ?? 'user'));

            if ($userId <= 0) {
                throw new RuntimeException('Nieprawidlowy ID uzytkownika.');
            }

            $stmt = $pdo->prepare('UPDATE uzytkownicy SET rola = ? WHERE id = ?');
            $stmt->execute([$newRole, $userId]);
            startowa_refresh_access_cache();
            $notice = 'Rola uzytkownika zostala zaktualizowana.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$usersStmt = $pdo->query('SELECT id, imie, nazwisko, email, COALESCE(rola, "user") AS rola FROM uzytkownicy ORDER BY id DESC');
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

panel_layout_start('Uzytkownicy', 'Zarzadzanie rolami kont uzytkownikow');
?>
<?php if ($notice !== ''): ?>
    <div class="alert alert-success py-2"><?php echo h($notice); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger py-2"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Imie i nazwisko</th>
                    <th>Email</th>
                    <th>Rola</th>
                    <th>Akcja</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo (int) $user['id']; ?></td>
                        <td><?php echo h(trim(($user['imie'] ?? '') . ' ' . ($user['nazwisko'] ?? ''))); ?></td>
                        <td><?php echo h((string) ($user['email'] ?? '')); ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="action" value="update_user_role">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                <select name="role" class="form-select form-select-sm" style="min-width:180px;">
                                    <?php foreach ($roles as $role): ?>
                                        <?php $roleKey = startowa_normalize_role((string) $role['key']); ?>
                                        <option value="<?php echo h($roleKey); ?>" <?php echo $roleKey === startowa_normalize_role((string) $user['rola']) ? 'selected' : ''; ?>>
                                            <?php echo h((string) $role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                        </td>
                        <td>
                                <button type="submit" class="btn btn-sm btn-outline-light">Zapisz</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="text-center muted py-3">Brak uzytkownikow.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
panel_layout_end();
