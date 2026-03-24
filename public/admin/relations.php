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
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS startowa_user_relations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_user_id INT NOT NULL,
            target_user_id INT NOT NULL,
            relation_type VARCHAR(60) NOT NULL DEFAULT "manager",
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_relation (source_user_id, target_user_id, relation_type),
            INDEX idx_source (source_user_id),
            INDEX idx_target (target_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add_relation') {
            $sourceId = (int) ($_POST['source_user_id'] ?? 0);
            $targetId = (int) ($_POST['target_user_id'] ?? 0);
            $relationType = strtolower(trim((string) ($_POST['relation_type'] ?? 'manager')));
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
                throw new RuntimeException('Wybierz dwa rozne konta.');
            }

            if (!preg_match('/^[a-z0-9_\-]{2,60}$/', $relationType)) {
                throw new RuntimeException('Typ relacji ma niepoprawny format.');
            }

            $stmt = $pdo->prepare('INSERT INTO startowa_user_relations (source_user_id, target_user_id, relation_type, notes) VALUES (?, ?, ?, ?)');
            $stmt->execute([$sourceId, $targetId, $relationType, $notes !== '' ? $notes : null]);
            $notice = 'Dodano relacje miedzy kontami.';
        }

        if ($action === 'delete_relation') {
            $relationId = (int) ($_POST['relation_id'] ?? 0);
            if ($relationId <= 0) {
                throw new RuntimeException('Nieprawidlowe ID relacji.');
            }
            $stmt = $pdo->prepare('DELETE FROM startowa_user_relations WHERE id = ?');
            $stmt->execute([$relationId]);
            $notice = 'Relacja zostala usunieta.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$users = $pdo->query('SELECT id, imie, nazwisko, email FROM uzytkownicy ORDER BY imie ASC, nazwisko ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
$relations = $pdo->query('SELECT r.id, r.relation_type, r.notes, r.created_at,
    s.id AS source_id, s.imie AS source_name, s.nazwisko AS source_last,
    t.id AS target_id, t.imie AS target_name, t.nazwisko AS target_last
    FROM startowa_user_relations r
    JOIN uzytkownicy s ON s.id = r.source_user_id
    JOIN uzytkownicy t ON t.id = r.target_user_id
    ORDER BY r.id DESC')->fetchAll(PDO::FETCH_ASSOC);

panel_layout_start('Relacje', 'Powiazania miedzy uzytkownikami i zespolami');
?>
<?php if ($notice !== ''): ?>
    <div class="alert alert-success py-2"><?php echo h($notice); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger py-2"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="card p-3 mb-3">
    <h2 class="h5 mb-3">Dodaj relacje</h2>
    <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add_relation">
        <div class="col-md-4">
            <label class="form-label mb-1">Zrodlo</label>
            <select class="form-select" name="source_user_id" required>
                <option value="">Wybierz uzytkownika</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo (int) $user['id']; ?>"><?php echo h(trim($user['imie'] . ' ' . $user['nazwisko']) . ' (#' . $user['id'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Cel</label>
            <select class="form-select" name="target_user_id" required>
                <option value="">Wybierz uzytkownika</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo (int) $user['id']; ?>"><?php echo h(trim($user['imie'] . ' ' . $user['nazwisko']) . ' (#' . $user['id'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label mb-1">Typ relacji</label>
            <input class="form-control" name="relation_type" value="manager" required>
        </div>
        <div class="col-12">
            <label class="form-label mb-1">Notatka</label>
            <input class="form-control" name="notes" placeholder="opcjonalnie">
        </div>
        <div class="col-12">
            <button class="btn btn-outline-light" type="submit">Dodaj relacje</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Zrodlo</th>
                    <th>Relacja</th>
                    <th>Cel</th>
                    <th>Utworzono</th>
                    <th>Akcja</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($relations as $relation): ?>
                    <tr>
                        <td><?php echo (int) $relation['id']; ?></td>
                        <td><?php echo h(trim($relation['source_name'] . ' ' . $relation['source_last']) . ' (#' . $relation['source_id'] . ')'); ?></td>
                        <td>
                            <strong><?php echo h((string) $relation['relation_type']); ?></strong>
                            <?php if ((string) $relation['notes'] !== ''): ?>
                                <div class="muted" style="font-size:12px;"><?php echo h((string) $relation['notes']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h(trim($relation['target_name'] . ' ' . $relation['target_last']) . ' (#' . $relation['target_id'] . ')'); ?></td>
                        <td><?php echo h((string) $relation['created_at']); ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Usunac relacje?');">
                                <input type="hidden" name="action" value="delete_relation">
                                <input type="hidden" name="relation_id" value="<?php echo (int) $relation['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Usun</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($relations)): ?>
                    <tr>
                        <td colspan="6" class="text-center muted py-3">Brak relacji.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
panel_layout_end();
