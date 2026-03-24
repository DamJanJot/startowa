<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$notice = '';
$error = '';

try {
    if (!startowa_table_exists($pdo, 'startowa_roles')) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS startowa_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(50) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec("INSERT IGNORE INTO startowa_roles (`key`, `name`, is_system) VALUES
            ('owner', 'Owner', 1),
            ('admin', 'Admin', 1),
            ('user', 'User', 1)");
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add_role') {
            $roleKey = strtolower(trim((string) ($_POST['role_key'] ?? '')));
            $roleName = trim((string) ($_POST['role_name'] ?? ''));

            if (!preg_match('/^[a-z0-9_\-]{2,50}$/', $roleKey)) {
                throw new RuntimeException('Klucz roli moze zawierac tylko a-z, 0-9, -, _.');
            }
            if ($roleName === '') {
                throw new RuntimeException('Nazwa roli nie moze byc pusta.');
            }

            $stmt = $pdo->prepare('INSERT INTO startowa_roles (`key`, `name`, is_system) VALUES (?, ?, 0)');
            $stmt->execute([$roleKey, $roleName]);
            $notice = 'Dodano nowa role.';
        }

        if ($action === 'delete_role') {
            $roleKey = strtolower(trim((string) ($_POST['role_key'] ?? '')));
            if (in_array($roleKey, ['owner', 'admin', 'user'], true)) {
                throw new RuntimeException('Rola systemowa nie moze zostac usunieta.');
            }

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM startowa_role_app_assignments WHERE role_key = ?')->execute([$roleKey]);
            $pdo->prepare('DELETE FROM startowa_roles WHERE `key` = ? AND is_system = 0')->execute([$roleKey]);
            $pdo->commit();
            $notice = 'Usunieto role i jej przypisania.';
        }

        startowa_refresh_access_cache();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$rows = $pdo->query('SELECT r.`key`, r.`name`, r.is_system,
    (SELECT COUNT(*) FROM startowa_role_app_assignments a WHERE a.role_key = r.`key`) AS apps_count
    FROM startowa_roles r ORDER BY r.is_system DESC, r.`name` ASC')->fetchAll(PDO::FETCH_ASSOC);

panel_layout_start('Role', 'Dodawanie i usuwanie rol systemowych');
?>
<?php if ($notice !== ''): ?>
    <div class="alert alert-success py-2"><?php echo h($notice); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger py-2"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="card p-3 mb-3">
    <h2 class="h5 mb-3">Dodaj role</h2>
    <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add_role">
        <div class="col-md-4">
            <input class="form-control" name="role_key" placeholder="np. manager" required>
        </div>
        <div class="col-md-5">
            <input class="form-control" name="role_name" placeholder="Nazwa roli" required>
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-light w-100" type="submit">Dodaj</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Klucz</th>
                    <th>Nazwa</th>
                    <th>Typ</th>
                    <th>Przypisane apki</th>
                    <th>Akcja</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo h((string) $row['key']); ?></td>
                        <td><?php echo h((string) $row['name']); ?></td>
                        <td><?php echo ((int) $row['is_system'] === 1) ? 'systemowa' : 'niestandardowa'; ?></td>
                        <td><?php echo (int) $row['apps_count']; ?></td>
                        <td>
                            <?php if ((int) $row['is_system'] === 1): ?>
                                <span class="muted">zablokowana</span>
                            <?php else: ?>
                                <form method="post" onsubmit="return confirm('Usunac role <?php echo h((string) $row['name']); ?>?');">
                                    <input type="hidden" name="action" value="delete_role">
                                    <input type="hidden" name="role_key" value="<?php echo h((string) $row['key']); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Usun</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
panel_layout_end();
