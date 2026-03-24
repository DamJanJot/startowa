<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function safe_identifier(string $name): bool
{
    return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name);
}

$notice = '';
$error = '';
$queryRows = [];
$queryColumns = [];
$queryInfo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_table') {
            $tableName = trim((string) ($_POST['table_name'] ?? ''));
            $columnsSql = trim((string) ($_POST['columns_sql'] ?? ''));

            if (!safe_identifier($tableName)) {
                throw new RuntimeException('Niepoprawna nazwa tabeli.');
            }
            if ($columnsSql === '') {
                throw new RuntimeException('Podaj definicje kolumn.');
            }

            $pdo->exec("CREATE TABLE `$tableName` ($columnsSql) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $notice = 'Tabela zostala utworzona.';
        }

        if ($action === 'drop_table') {
            $tableName = trim((string) ($_POST['table_name'] ?? ''));
            if (!safe_identifier($tableName)) {
                throw new RuntimeException('Niepoprawna nazwa tabeli.');
            }
            $pdo->exec("DROP TABLE `$tableName`");
            $notice = 'Tabela zostala usunieta.';
        }

        if ($action === 'add_column') {
            $tableName = trim((string) ($_POST['table_name'] ?? ''));
            $columnName = trim((string) ($_POST['column_name'] ?? ''));
            $columnType = trim((string) ($_POST['column_type'] ?? 'VARCHAR(255)'));

            if (!safe_identifier($tableName) || !safe_identifier($columnName)) {
                throw new RuntimeException('Niepoprawna nazwa tabeli lub kolumny.');
            }

            $pdo->exec("ALTER TABLE `$tableName` ADD COLUMN `$columnName` $columnType");
            $notice = 'Kolumna zostala dodana.';
        }

        if ($action === 'run_query') {
            $sql = trim((string) ($_POST['sql'] ?? ''));
            if ($sql === '') {
                throw new RuntimeException('Zapytanie nie moze byc puste.');
            }

            $stmt = $pdo->query($sql);
            if ($stmt instanceof PDOStatement) {
                if ($stmt->columnCount() > 0) {
                    $queryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($queryRows)) {
                        $queryColumns = array_keys($queryRows[0]);
                    }
                    $queryInfo = 'Wynik zapytania: ' . count($queryRows) . ' wierszy.';
                } else {
                    $queryInfo = 'Zapytanie wykonane. Zmienione wiersze: ' . $stmt->rowCount();
                }
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$selectedTable = trim((string) ($_GET['table'] ?? $_POST['table_name'] ?? ''));
$columns = [];
if ($selectedTable !== '' && safe_identifier($selectedTable)) {
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM `$selectedTable`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

panel_layout_start('Baza danych', 'SQL runner, tabele, kolumny i operacje administracyjne');
?>
<?php if ($notice !== ''): ?>
    <div class="alert alert-success py-2"><?php echo h($notice); ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger py-2"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card p-3 h-100">
            <h2 class="h5 mb-2">Tabele</h2>
            <div class="table-responsive" style="max-height:260px;">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Nazwa</th><th>Akcje</th></tr></thead>
                    <tbody>
                    <?php foreach ($tables as $table): ?>
                        <tr>
                            <td><a class="text-info" href="?embed=<?php echo panel_is_embedded() ? '1' : '0'; ?>&table=<?php echo urlencode((string) $table); ?>"><?php echo h((string) $table); ?></a></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Usunac tabele <?php echo h((string) $table); ?>?');" style="display:inline;">
                                    <input type="hidden" name="action" value="drop_table">
                                    <input type="hidden" name="table_name" value="<?php echo h((string) $table); ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Drop</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tables)): ?>
                        <tr><td colspan="2" class="text-center muted py-3">Brak tabel.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card p-3 mb-3">
            <h2 class="h5 mb-2">Utworz tabele</h2>
            <form method="post" class="row g-2">
                <input type="hidden" name="action" value="create_table">
                <div class="col-12"><input class="form-control" name="table_name" placeholder="nazwa_tabeli" required></div>
                <div class="col-12"><textarea class="form-control" name="columns_sql" rows="4" placeholder="id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL" required></textarea></div>
                <div class="col-12"><button class="btn btn-outline-light" type="submit">Utworz</button></div>
            </form>
        </div>

        <div class="card p-3">
            <h2 class="h5 mb-2">Dodaj kolumne</h2>
            <form method="post" class="row g-2">
                <input type="hidden" name="action" value="add_column">
                <div class="col-md-4">
                    <select class="form-select" name="table_name" required>
                        <option value="">Wybierz tabele</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?php echo h((string) $table); ?>" <?php echo (string) $table === $selectedTable ? 'selected' : ''; ?>><?php echo h((string) $table); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><input class="form-control" name="column_name" placeholder="nowa_kolumna" required></div>
                <div class="col-md-4"><input class="form-control" name="column_type" value="VARCHAR(255)" required></div>
                <div class="col-12"><button class="btn btn-outline-light" type="submit">Dodaj kolumne</button></div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($columns)): ?>
<div class="card p-3 mb-3">
    <h2 class="h5 mb-2">Struktura: <?php echo h($selectedTable); ?></h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr></thead>
            <tbody>
            <?php foreach ($columns as $column): ?>
                <tr>
                    <td><?php echo h((string) ($column['Field'] ?? '')); ?></td>
                    <td><?php echo h((string) ($column['Type'] ?? '')); ?></td>
                    <td><?php echo h((string) ($column['Null'] ?? '')); ?></td>
                    <td><?php echo h((string) ($column['Key'] ?? '')); ?></td>
                    <td><?php echo h((string) ($column['Default'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card p-3">
    <h2 class="h5 mb-2">SQL runner</h2>
    <form method="post" class="mb-2">
        <input type="hidden" name="action" value="run_query">
        <textarea class="form-control" name="sql" rows="5" placeholder="SELECT * FROM uzytkownicy LIMIT 50;"><?php echo isset($_POST['sql']) ? h((string) $_POST['sql']) : ''; ?></textarea>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="muted">Uwaga: to pole wykonuje zapytania SQL bez ograniczen.</span>
            <button class="btn btn-outline-light" type="submit">Wykonaj</button>
        </div>
    </form>

    <?php if ($queryInfo !== ''): ?>
        <div class="muted mb-2"><?php echo h($queryInfo); ?></div>
    <?php endif; ?>

    <?php if (!empty($queryColumns)): ?>
        <div class="table-responsive" style="max-height:360px;">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <?php foreach ($queryColumns as $column): ?>
                            <th><?php echo h((string) $column); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queryRows as $row): ?>
                        <tr>
                            <?php foreach ($queryColumns as $column): ?>
                                <td><?php echo h((string) ($row[$column] ?? '')); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php
panel_layout_end();
