<?php
if (is_file(__DIR__ . '/../core/access_control.php')) {
    require_once __DIR__ . '/../core/access_control.php';

    startowa_require_login();

    if (!startowa_has_app_access('admin_panel') && !startowa_has_app_access('server_hub')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
}

header('Content-Type: application/json; charset=UTF-8');

$configuredRoot = getenv('DASHBOARD_ROOT') ?: '';
$serverRoot = $configuredRoot !== '' ? realpath($configuredRoot) : realpath(dirname(__DIR__, 2));
if ($serverRoot === false) {
    $serverRoot = realpath(dirname(__DIR__));
}

function normalizePath(string $path): string
{
    $realPath = realpath($path);
    return $realPath === false ? '' : $realPath;
}

function isPathAllowed(string $path): bool
{
    global $serverRoot;
    $realPath = normalizePath($path);
    if ($realPath === '') {
        return false;
    }

    return strncmp($realPath, $serverRoot, strlen($serverRoot)) === 0;
}

function relativePath(string $absPath): string
{
    global $serverRoot;
    if ($absPath === $serverRoot) {
        return DIRECTORY_SEPARATOR;
    }

    return substr($absPath, strlen($serverRoot)) ?: DIRECTORY_SEPARATOR;
}

function sanitizeName(string $name): string
{
    $name = trim($name);
    $name = str_replace(["\0", '/', '\\'], '', $name);
    return $name;
}

function allowedNewName(string $name): bool
{
    if ($name === '' || $name === '.' || $name === '..') {
        return false;
    }

    return preg_match('/^[a-zA-Z0-9._\- ]+$/', $name) === 1;
}

function ensureAllowedParent(string $parentPath): string
{
    $parentReal = normalizePath($parentPath);
    if ($parentReal === '' || !isPathAllowed($parentReal) || !is_dir($parentReal)) {
        return '';
    }

    return $parentReal;
}

function buildChildPath(string $parentPath, string $name): string
{
    return rtrim($parentPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
}

function getFileIcon(string $filename, bool $isDir): string
{
    if ($isDir) {
        return 'folder';
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'php' => 'php',
        'js' => 'code',
        'ts' => 'code',
        'tsx' => 'code',
        'jsx' => 'code',
        'css' => 'paint',
        'html' => 'html',
        'json' => 'json',
        'sql' => 'db',
        'md' => 'markdown',
        'txt' => 'text',
        'yml' => 'settings',
        'yaml' => 'settings',
        'xml' => 'xml',
        'svg' => 'image',
        'png' => 'image',
        'jpg' => 'image',
        'jpeg' => 'image',
        'gif' => 'image',
        'webp' => 'image',
        'pdf' => 'pdf',
        'zip' => 'archive',
    ];

    return $map[$ext] ?? 'file';
}

function hasIndexFile(string $dirPath): bool
{
    $candidates = ['index.php', 'index.html', 'index.htm'];
    foreach ($candidates as $candidate) {
        if (is_file($dirPath . DIRECTORY_SEPARATOR . $candidate)) {
            return true;
        }
    }
    return false;
}

function listDirectory(string $path): array
{
    global $serverRoot;

    if (!isPathAllowed($path)) {
        return ['error' => 'Path not allowed'];
    }

    if (!is_dir($path)) {
        return ['error' => 'Not a directory'];
    }

    $items = [];
    try {
        $entries = scandir($path);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $entry;
            $isDir = is_dir($fullPath);
            $normalized = normalizePath($fullPath);
            if ($normalized === '' || !isPathAllowed($normalized)) {
                continue;
            }

            $items[] = [
                'name' => $entry,
                'path' => $normalized,
                'type' => $isDir ? 'directory' : 'file',
                'size' => $isDir ? 0 : @filesize($fullPath),
                'modified' => @filemtime($fullPath),
                'icon' => getFileIcon($entry, $isDir),
                'hasIndex' => $isDir ? hasIndexFile($fullPath) : false,
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }

    $current = normalizePath($path);
    $parent = dirname($current);
    $canGoUp = $current !== $serverRoot && isPathAllowed($parent);

    return [
        'current' => $current,
        'relative' => relativePath($current),
        'parent' => $canGoUp ? normalizePath($parent) : null,
        'items' => $items,
    ];
}

function readFileContent(string $path): array
{
    if (!isPathAllowed($path)) {
        return ['error' => 'Path not allowed'];
    }

    if (!is_file($path)) {
        return ['error' => 'Not a file'];
    }

    $size = @filesize($path);
    if ($size === false || $size > 2 * 1024 * 1024) {
        return ['error' => 'File too large (max 2MB)'];
    }

    $content = @file_get_contents($path);
    if ($content === false) {
        return ['error' => 'Cannot read file'];
    }

    return [
        'content' => $content,
        'size' => $size,
        'modified' => @filemtime($path),
    ];
}

function writeFileContent(string $path, string $content): array
{
    if (!isPathAllowed($path) || !is_file($path)) {
        return ['error' => 'Path not allowed'];
    }

    $ok = @file_put_contents($path, $content);
    if ($ok === false) {
        return ['error' => 'Cannot write file'];
    }

    return ['success' => true, 'modified' => @filemtime($path)];
}

function createDirectory(string $parentPath, string $folderName): array
{
    $parentReal = ensureAllowedParent($parentPath);
    if ($parentReal === '') {
        return ['error' => 'Invalid parent path'];
    }

    $folderName = sanitizeName($folderName);
    if (!allowedNewName($folderName)) {
        return ['error' => 'Invalid folder name'];
    }

    $target = buildChildPath($parentReal, $folderName);
    if (file_exists($target)) {
        return ['error' => 'Folder already exists'];
    }

    if (!@mkdir($target, 0775, true)) {
        return ['error' => 'Cannot create folder'];
    }

    $targetReal = normalizePath($target);
    if ($targetReal === '' || !isPathAllowed($targetReal)) {
        return ['error' => 'Created path out of root'];
    }

    return ['success' => true, 'path' => $targetReal];
}

function createFile(string $parentPath, string $fileName, string $content): array
{
    $parentReal = ensureAllowedParent($parentPath);
    if ($parentReal === '') {
        return ['error' => 'Invalid parent path'];
    }

    $fileName = sanitizeName($fileName);
    if (!allowedNewName($fileName)) {
        return ['error' => 'Invalid file name'];
    }

    $target = buildChildPath($parentReal, $fileName);
    if (file_exists($target)) {
        return ['error' => 'File already exists'];
    }

    if (@file_put_contents($target, $content) === false) {
        return ['error' => 'Cannot create file'];
    }

    $targetReal = normalizePath($target);
    if ($targetReal === '' || !isPathAllowed($targetReal)) {
        return ['error' => 'Created file out of root'];
    }

    return ['success' => true, 'path' => $targetReal];
}

function deletePath(string $path): array
{
    if (!isPathAllowed($path)) {
        return ['error' => 'Path not allowed'];
    }

    if (is_file($path)) {
        if (!@unlink($path)) {
            return ['error' => 'Cannot delete file'];
        }
        return ['success' => true];
    }

    if (!is_dir($path)) {
        return ['error' => 'Path not found'];
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $entry) {
        $entryPath = $entry->getPathname();
        if ($entry->isDir()) {
            if (!@rmdir($entryPath)) {
                return ['error' => 'Cannot remove directory'];
            }
        } else {
            if (!@unlink($entryPath)) {
                return ['error' => 'Cannot remove file'];
            }
        }
    }

    if (!@rmdir($path)) {
        return ['error' => 'Cannot remove directory'];
    }

    return ['success' => true];
}

function renamePath(string $path, string $newName): array
{
    if (!isPathAllowed($path) || !file_exists($path)) {
        return ['error' => 'Path not allowed'];
    }

    $newName = sanitizeName($newName);
    if (!allowedNewName($newName)) {
        return ['error' => 'Invalid new name'];
    }

    $parent = dirname($path);
    $target = buildChildPath($parent, $newName);
    if (file_exists($target)) {
        return ['error' => 'Target already exists'];
    }

    if (!@rename($path, $target)) {
        return ['error' => 'Cannot rename'];
    }

    $targetReal = normalizePath($target);
    if ($targetReal === '' || !isPathAllowed($targetReal)) {
        return ['error' => 'Renamed path out of root'];
    }

    return ['success' => true, 'path' => $targetReal];
}

function movePath(string $path, string $targetDir): array
{
    if (!isPathAllowed($path) || !file_exists($path)) {
        return ['error' => 'Source path not allowed'];
    }

    $targetDirReal = ensureAllowedParent($targetDir);
    if ($targetDirReal === '') {
        return ['error' => 'Target directory not allowed'];
    }

    $name = basename($path);
    $target = buildChildPath($targetDirReal, $name);
    if (file_exists($target)) {
        return ['error' => 'Target already exists'];
    }

    if (!@rename($path, $target)) {
        return ['error' => 'Cannot move'];
    }

    $targetReal = normalizePath($target);
    if ($targetReal === '' || !isPathAllowed($targetReal)) {
        return ['error' => 'Moved path out of root'];
    }

    return ['success' => true, 'path' => $targetReal];
}

function uniqueFolderName(string $parentDir, string $baseName): string
{
    $candidate = $baseName;
    $idx = 1;
    while (file_exists(buildChildPath($parentDir, $candidate))) {
        $candidate = $baseName . '_' . $idx;
        $idx++;
    }
    return $candidate;
}

function extractZipToFolder(string $zipPath, string $parentDir): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return ['error' => 'Cannot open ZIP'];
    }

    $base = pathinfo($zipPath, PATHINFO_FILENAME);
    $folder = uniqueFolderName($parentDir, $base !== '' ? $base : 'unzipped');
    $targetDir = buildChildPath($parentDir, $folder);

    if (!@mkdir($targetDir, 0775, true)) {
        $zip->close();
        return ['error' => 'Cannot create unzip directory'];
    }

    if (!$zip->extractTo($targetDir)) {
        $zip->close();
        return ['error' => 'Cannot extract ZIP'];
    }

    $zip->close();
    return ['success' => true, 'path' => normalizePath($targetDir)];
}

function uploadFiles(string $parentPath, bool $extractZip): array
{
    $parentReal = ensureAllowedParent($parentPath);
    if ($parentReal === '') {
        return ['error' => 'Invalid parent path'];
    }

    if (!isset($_FILES['files'])) {
        return ['error' => 'No files uploaded'];
    }

    $names = $_FILES['files']['name'];
    $tmpNames = $_FILES['files']['tmp_name'];
    $errors = $_FILES['files']['error'];
    $relativePaths = $_POST['relativePaths'] ?? [];

    if (!is_array($names) || !is_array($tmpNames) || !is_array($errors)) {
        return ['error' => 'Upload format not supported'];
    }

    $created = [];

    for ($i = 0; $i < count($names); $i++) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $originalName = sanitizeName((string)$names[$i]);
        if ($originalName === '') {
            continue;
        }

        $relative = isset($relativePaths[$i]) ? str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$relativePaths[$i]) : '';
        $relative = trim($relative);

        $targetDir = $parentReal;
        if ($relative !== '') {
            $parts = array_filter(explode(DIRECTORY_SEPARATOR, dirname($relative)), function ($p) {
                return $p !== '' && $p !== '.' && $p !== '..';
            });

            foreach ($parts as $part) {
                $clean = sanitizeName($part);
                if (!allowedNewName($clean)) {
                    continue 2;
                }
                $targetDir = buildChildPath($targetDir, $clean);
            }

            if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
                return ['error' => 'Cannot create upload subfolder'];
            }
        }

        if (!isPathAllowed($targetDir)) {
            return ['error' => 'Upload target out of root'];
        }

        $targetFile = buildChildPath($targetDir, $originalName);
        if (!@move_uploaded_file($tmpNames[$i], $targetFile)) {
            return ['error' => 'Cannot store uploaded file'];
        }

        $storedPath = normalizePath($targetFile);
        if ($storedPath === '') {
            continue;
        }

        $created[] = $storedPath;

        $ext = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        if ($extractZip && $ext === 'zip' && class_exists('ZipArchive')) {
            $unzipped = extractZipToFolder($storedPath, dirname($storedPath));
            if (!isset($unzipped['error'])) {
                $created[] = $unzipped['path'];
            }
        }
    }

    return ['success' => true, 'created' => $created];
}

function markdownToHtml(string $markdown): string
{
    $text = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $text = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

    $parts = preg_split('/\n\n+/', $text);
    $parts = array_map(function ($p) {
        if (preg_match('/^<h[1-3]>/', $p)) {
            return $p;
        }
        return '<p>' . nl2br($p) . '</p>';
    }, $parts);

    return implode("\n", $parts);
}

function filePreview(string $path): array
{
    if (!isPathAllowed($path) || !is_file($path)) {
        return ['error' => 'Path not allowed'];
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $size = @filesize($path);

    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
        if ($size === false || $size > 5 * 1024 * 1024) {
            return ['error' => 'Image too large'];
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return ['error' => 'Cannot read image'];
        }

        $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
        return [
            'kind' => 'image',
            'name' => basename($path),
            'dataUrl' => 'data:' . $mime . ';base64,' . base64_encode($raw),
        ];
    }

    if ($ext === 'pdf') {
        if ($size === false || $size > 8 * 1024 * 1024) {
            return ['error' => 'PDF too large'];
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return ['error' => 'Cannot read PDF'];
        }
        return [
            'kind' => 'pdf',
            'name' => basename($path),
            'dataUrl' => 'data:application/pdf;base64,' . base64_encode($raw),
        ];
    }

    if ($ext === 'md') {
        $content = @file_get_contents($path);
        if ($content === false) {
            return ['error' => 'Cannot read markdown'];
        }
        return [
            'kind' => 'markdown',
            'name' => basename($path),
            'html' => markdownToHtml($content),
        ];
    }

    return ['error' => 'Preview not available for this extension'];
}

function folderIndexPreview(string $path): array
{
    if (!isPathAllowed($path) || !is_dir($path)) {
        return ['error' => 'Path not allowed'];
    }

    $candidates = ['index.php', 'index.html', 'index.htm'];
    $indexPath = '';
    foreach ($candidates as $f) {
        $candidate = $path . DIRECTORY_SEPARATOR . $f;
        if (is_file($candidate)) {
            $indexPath = $candidate;
            break;
        }
    }

    if ($indexPath === '') {
        return ['exists' => false];
    }

    $content = @file_get_contents($indexPath);
    if ($content === false) {
        return ['error' => 'Cannot read index file'];
    }

    $title = '';
    if (preg_match('/<title>(.*?)<\/title>/is', $content, $m)) {
        $title = trim(strip_tags($m[1]));
    }

    $text = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
    $snippet = mb_substr($text, 0, 220);

    return [
        'exists' => true,
        'file' => basename($indexPath),
        'title' => $title,
        'snippet' => $snippet,
    ];
}

function webPreviewUrl(string $path): array
{
    if (!isPathAllowed($path)) {
        return ['error' => 'Path not allowed'];
    }

    $docRoot = normalizePath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRoot === '') {
        return ['error' => 'Document root unavailable'];
    }

    $target = normalizePath($path);
    if ($target === '' || strncmp($target, $docRoot, strlen($docRoot)) !== 0) {
        return ['error' => 'Path outside public web root'];
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    $relative = substr($target, strlen($docRoot));
    $relative = str_replace('\\', '/', $relative);
    if ($relative === '') {
        $relative = '/';
    }

    if (is_dir($target)) {
        if (substr($relative, -1) !== '/') {
            $relative .= '/';
        }
    }

    if ($host === '') {
        return ['url' => $relative];
    }

    return ['url' => $scheme . '://' . $host . $relative];
}

function isSafeTerminalCommand(string $cmd): bool
{
    if ($cmd === '') {
        return false;
    }

    if (preg_match('/[;&|><`\n\r]/', $cmd)) {
        return false;
    }

    return true;
}

function executeBuiltinCommand(string $cmd, string $workDir): ?array
{
    $trimmed = trim($cmd);
    if ($trimmed === '') {
        return null;
    }

    $parts = preg_split('/\s+/', $trimmed, 2);
    $name = strtolower((string)($parts[0] ?? ''));
    $argText = (string)($parts[1] ?? '');

    if ($name === 'pwd') {
        return ['success' => true, 'output' => $workDir !== '' ? $workDir : '/'];
    }

    if ($name === 'date') {
        return ['success' => true, 'output' => date('Y-m-d H:i:s')];
    }

    if ($name === 'whoami') {
        $user = trim((string)get_current_user());
        if ($user === '') {
            $user = trim((string)($_SERVER['USER'] ?? $_SERVER['USERNAME'] ?? 'web-user'));
        }
        return ['success' => true, 'output' => $user !== '' ? $user : 'web-user'];
    }

    if ($name === 'echo') {
        return ['success' => true, 'output' => $argText];
    }

    if ($name === 'ls' || $name === 'dir') {
        $target = $workDir !== '' ? $workDir : getcwd();
        if (!is_string($target) || $target === '' || !is_dir($target)) {
            return ['error' => 'Directory unavailable'];
        }

        $items = @scandir($target);
        if (!is_array($items)) {
            return ['error' => 'Cannot read directory'];
        }

        $lines = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $target . DIRECTORY_SEPARATOR . $item;
            $lines[] = is_dir($full) ? ($item . DIRECTORY_SEPARATOR) : $item;
            if (count($lines) >= 300) {
                break;
            }
        }

        return ['success' => true, 'output' => count($lines) > 0 ? implode("\n", $lines) : '(empty directory)'];
    }

    if ($name === 'php' && preg_match('/^\s*-v\s*$/i', $argText)) {
        return ['success' => true, 'output' => 'PHP ' . PHP_VERSION];
    }

    return null;
}

function executeCommand(string $cmd, string $cwd = ''): array
{
    if (!isSafeTerminalCommand($cmd)) {
        return ['error' => 'Unsafe command syntax'];
    }

    $allowed = ['npm', 'composer', 'php', 'git', 'ls', 'dir', 'pwd', 'echo', 'whoami', 'date'];
    $cmdName = strtok($cmd, ' ');
    if ($cmdName === false || !in_array($cmdName, $allowed, true)) {
        return ['error' => 'Command not allowed'];
    }

    $workDir = '';
    if ($cwd !== '') {
        $normCwd = normalizePath($cwd);
        if ($normCwd !== '' && isPathAllowed($normCwd) && is_dir($normCwd)) {
            $workDir = $normCwd;
        }
    }

    $builtin = executeBuiltinCommand($cmd, $workDir);
    if (is_array($builtin)) {
        return $builtin;
    }

    if (function_exists('proc_open')) {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $descriptors, $pipes, $workDir !== '' ? $workDir : null);
        if (is_resource($proc)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);

            $output = trim((string)$stdout . (string)$stderr);
            return ['success' => true, 'output' => $output !== '' ? $output : 'Command finished (no output)'];
        }
    }

    if (function_exists('shell_exec')) {
        $prefix = $workDir !== '' ? ('cd ' . escapeshellarg($workDir) . ' && ') : '';
        $output = shell_exec($prefix . $cmd . ' 2>&1');
        return ['success' => true, 'output' => $output ?: 'Command finished (no output)'];
    }

    return ['error' => 'Terminal execution unavailable on this server (hosting restriction). Built-in commands: pwd, ls, dir, date, whoami, echo, php -v'];
}

function searchTextInFiles(string $basePath, string $query, int $maxResults = 120): array
{
    if ($query === '') {
        return ['error' => 'Empty query'];
    }

    $base = normalizePath($basePath);
    if ($base === '' || !isPathAllowed($base) || !is_dir($base)) {
        return ['error' => 'Invalid base path'];
    }

    $maxResults = max(1, min($maxResults, 300));
    $results = [];
    $extensions = ['php', 'js', 'ts', 'tsx', 'jsx', 'css', 'html', 'md', 'txt', 'json', 'xml', 'yml', 'yaml', 'sql'];

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $entry) {
        if (count($results) >= $maxResults) {
            break;
        }

        if (!$entry->isFile()) {
            continue;
        }

        $path = $entry->getPathname();
        $norm = normalizePath($path);
        if ($norm === '' || !isPathAllowed($norm)) {
            continue;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) {
            continue;
        }

        $size = @filesize($path);
        if ($size === false || $size > 1024 * 1024) {
            continue;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            continue;
        }

        $lines = preg_split('/\R/', $content);
        foreach ($lines as $idx => $line) {
            if (stripos($line, $query) !== false) {
                $results[] = [
                    'path' => $norm,
                    'name' => basename($norm),
                    'line' => $idx + 1,
                    'preview' => trim($line),
                ];
                break;
            }
        }
    }

    return ['success' => true, 'results' => $results, 'count' => count($results)];
}

function findAndReplaceInFiles(string $basePath, string $find, string $replace, array $extensions = [], int $maxFiles = 150, bool $dryRun = false): array
{
    if ($find === '') {
        return ['error' => 'Find phrase cannot be empty'];
    }

    $base = normalizePath($basePath);
    if ($base === '' || !isPathAllowed($base) || !is_dir($base)) {
        return ['error' => 'Invalid base path'];
    }

    $maxFiles = max(1, min($maxFiles, 400));
    $defaultExtensions = ['php', 'js', 'ts', 'tsx', 'jsx', 'css', 'html', 'md', 'txt', 'json', 'xml', 'yml', 'yaml', 'sql'];
    $extensions = array_values(array_filter(array_map('strtolower', $extensions), function ($x) {
        return preg_match('/^[a-z0-9]+$/', $x) === 1;
    }));
    $extensions = count($extensions) > 0 ? $extensions : $defaultExtensions;

    $changed = [];
    $totalReplacements = 0;
    $processed = 0;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $entry) {
        if ($processed >= $maxFiles) {
            break;
        }

        if (!$entry->isFile()) {
            continue;
        }

        $path = $entry->getPathname();
        $norm = normalizePath($path);
        if ($norm === '' || !isPathAllowed($norm)) {
            continue;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) {
            continue;
        }

        $size = @filesize($path);
        if ($size === false || $size > 2 * 1024 * 1024) {
            continue;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            continue;
        }

        $count = substr_count($content, $find);
        if ($count <= 0) {
            continue;
        }

        if (!$dryRun) {
            $newContent = str_replace($find, $replace, $content);
            if (@file_put_contents($path, $newContent) === false) {
                continue;
            }
        }

        $processed++;
        $totalReplacements += $count;
        $changed[] = [
            'path' => $norm,
            'replacements' => $count,
        ];
    }

    return [
        'success' => true,
        'changed' => $changed,
        'filesChanged' => count($changed),
        'totalReplacements' => $totalReplacements,
        'extensions' => $extensions,
        'dryRun' => $dryRun,
    ];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$path = $_GET['path'] ?? $_POST['path'] ?? '';
$response = [];

switch ($action) {
    case 'root':
        $response = ['root' => $serverRoot, 'name' => basename($serverRoot)];
        break;

    case 'list':
        $target = $path !== '' ? $path : $serverRoot;
        $response = listDirectory($target);
        break;

    case 'read':
        $response = $path !== '' ? readFileContent($path) : ['error' => 'Missing path'];
        break;

    case 'write':
        $response = $path !== '' ? writeFileContent($path, (string)($_POST['content'] ?? '')) : ['error' => 'Missing path'];
        break;

    case 'mkdir':
        $response = createDirectory((string)($_POST['parentPath'] ?? $serverRoot), (string)($_POST['folderName'] ?? ''));
        break;

    case 'create_file':
        $response = createFile(
            (string)($_POST['parentPath'] ?? $serverRoot),
            (string)($_POST['fileName'] ?? ''),
            (string)($_POST['content'] ?? '')
        );
        break;

    case 'upload':
        $extract = (string)($_POST['extractZip'] ?? '0') === '1';
        $response = uploadFiles((string)($_POST['parentPath'] ?? $serverRoot), $extract);
        break;

    case 'delete':
        $response = $path !== '' ? deletePath($path) : ['error' => 'Missing path'];
        break;

    case 'rename':
        $response = $path !== '' ? renamePath($path, (string)($_POST['newName'] ?? '')) : ['error' => 'Missing path'];
        break;

    case 'move':
        $response = $path !== '' ? movePath($path, (string)($_POST['targetDir'] ?? '')) : ['error' => 'Missing path'];
        break;

    case 'preview':
        $response = $path !== '' ? filePreview($path) : ['error' => 'Missing path'];
        break;

    case 'folder_index_preview':
        $response = $path !== '' ? folderIndexPreview($path) : ['error' => 'Missing path'];
        break;

    case 'web_preview_url':
        $response = $path !== '' ? webPreviewUrl($path) : ['error' => 'Missing path'];
        break;

    case 'execute':
        $response = executeCommand(trim((string)($_POST['cmd'] ?? '')), trim((string)($_POST['cwd'] ?? '')));
        break;

    case 'search_text':
        $response = searchTextInFiles(
            (string)($_POST['basePath'] ?? $serverRoot),
            trim((string)($_POST['query'] ?? '')),
            (int)($_POST['maxResults'] ?? 120)
        );
        break;

    case 'find_replace_text':
        $extRaw = trim((string)($_POST['extensions'] ?? ''));
        $extList = $extRaw !== '' ? array_filter(array_map('trim', explode(',', $extRaw))) : [];
        $dryRun = in_array(strtolower((string)($_POST['dryRun'] ?? '0')), ['1', 'true', 'yes'], true);
        $response = findAndReplaceInFiles(
            (string)($_POST['basePath'] ?? $serverRoot),
            (string)($_POST['find'] ?? ''),
            (string)($_POST['replace'] ?? ''),
            $extList,
            (int)($_POST['maxFiles'] ?? 150),
            $dryRun
        );
        break;

    default:
        $response = ['error' => 'Unknown action'];
        break;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
