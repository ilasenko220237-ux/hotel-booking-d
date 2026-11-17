<?php
require_once __DIR__ . '/config.php';

// Понятное сообщение вместо «белого экрана» при сбое БД
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo '<!doctype html><meta charset="UTF-8"><body style="font-family:sans-serif;padding:2rem">'
       . '<h1>Сервис временно недоступен</h1>'
       . '<p>Не удалось выполнить операцию. Проверьте подключение к базе данных (config.php) и попробуйте ещё раз.</p></body>';
});

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_str(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

// ---------- flash-сообщения ----------
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_html(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert alert-' . h($f['type']) . ' text-center" role="alert">' . h($f['msg']) . '</div>';
}

// ---------- администратор ----------
function is_admin(): bool
{
    return !empty($_SESSION['admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('login.php');
    }
}

// ---------- даты ----------
// Строгий разбор ДД.ММ.ГГГГ (31.02.2026 и т.п. отклоняются)
function parse_date(string $s): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!d.m.Y', $s);
    $err = DateTimeImmutable::getLastErrors();
    if (!$d || ($err && ($err['warning_count'] || $err['error_count'])) || $d->format('d.m.Y') !== $s) {
        return null;
    }
    return $d;
}

function fmt_date(string $ymd): string
{
    return (new DateTimeImmutable($ymd))->format('d.m.Y');
}

// Пересекается ли период [in, out) с одобренными бронями номера
function has_overlap(int $roomId, string $in, string $out, ?int $excludeId = null): bool
{
    $sql = "SELECT 1 FROM bookings WHERE room_id = :r AND status = 'approved'
            AND check_in < :out AND check_out > :in" . ($excludeId ? ' AND id <> :ex' : '') . ' LIMIT 1';
    $st = db()->prepare($sql);
    $params = [':r' => $roomId, ':in' => $in, ':out' => $out];
    if ($excludeId) {
        $params[':ex'] = $excludeId;
    }
    $st->execute($params);
    return (bool)$st->fetchColumn();
}

// Занятые ночи номера: ['2026-11-20' => true, ...]
function busy_days(int $roomId): array
{
    $st = db()->prepare("SELECT check_in, check_out FROM bookings
                         WHERE room_id = ? AND status = 'approved' AND check_out > ?");
    $st->execute([$roomId, (new DateTimeImmutable('today'))->format('Y-m-d')]);
    $days = [];
    foreach ($st as $r) {
        $end = new DateTimeImmutable($r['check_out']);
        for ($d = new DateTimeImmutable($r['check_in']); $d < $end; $d = $d->modify('+1 day')) {
            $days[$d->format('Y-m-d')] = true;
        }
    }
    return $days;
}

function render_calendar(DateTimeImmutable $first, array $busy, DateTimeImmutable $today): string
{
    $months = ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    $html = '<table class="calendar"><caption>' . $months[(int)$first->format('n')] . ' ' . $first->format('Y') . '</caption>'
          . '<tr><th>Пн</th><th>Вт</th><th>Ср</th><th>Чт</th><th>Пт</th><th>Сб</th><th>Вс</th></tr><tr>';
    $offset = (int)$first->format('N') - 1;
    $html .= str_repeat('<td></td>', $offset);
    $days = (int)$first->format('t');
    for ($i = 1; $i <= $days; $i++) {
        $d = $first->setDate((int)$first->format('Y'), (int)$first->format('n'), $i);
        $cls = isset($busy[$d->format('Y-m-d')]) ? 'busy' : ($d < $today ? 'past' : 'free');
        $html .= '<td class="' . $cls . '">' . $i . '</td>';
        if (($offset + $i) % 7 === 0 && $i < $days) {
            $html .= '</tr><tr>';
        }
    }
    $rest = (7 - ($offset + $days) % 7) % 7;
    return $html . str_repeat('<td></td>', $rest) . '</tr></table>';
}

// ---------- общий каркас страницы ----------
function page_header(string $title): void
{
    ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/index.css">
    <title><?= h($title) ?> — Сладкие Сны</title>
</head>
<body class="container">
<header class="d-flex flex-wrap justify-content-center py-3">
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-body-emphasis text-decoration-none">
        <span class="fs-4 mx-2 fw-medium">Сладкие Сны</span>
    </a>
    <ul class="nav">
        <li class="nav-item"><a href="index.php" class="nav-link">Каталог</a></li>
        <?php if (is_admin()): ?>
            <li class="nav-item"><a href="admin.php" class="nav-link">Панель администратора</a></li>
            <li class="nav-item"><a href="logout.php" class="nav-link">Выйти</a></li>
        <?php else: ?>
            <li class="nav-item"><a href="login.php" class="nav-link">Вход</a></li>
        <?php endif; ?>
        <li class="nav-item"><span class="nav-link">Приезжайте как гости, уезжайте как друзья!</span></li>
    </ul>
</header>
<?= flash_html() ?>
<?php
}

function page_footer(array $scripts = []): void
{
    ?>
<footer class="py-2 my-2">
    <ul class="nav justify-content-between align-items-center">
        <li class="nav-item"><span class="nav-link text-body-secondary">ул. г.Москва, ул. Ивовая, 48</span></li>
        <li class="nav-item"><span class="nav-link text-body-secondary">Время работы: Пн-Пт, с 8:00-17:00</span></li>
        <li class="nav-item"><a href="tel:88005553535" class="nav-link text-body-secondary">тел. 8 (800) 555 - 35 - 35</a></li>
        <li class="nav-item"><a href="mailto:обращения@СветлыеСны.рф" class="nav-link text-body-secondary">Email: обращения@СветлыеСны.рф</a></li>
    </ul>
</footer>
<script src="js/bootstrap.bundle.min.js"></script>
<?php foreach ($scripts as $s): ?>
<script src="js/<?= h($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
