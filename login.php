<?php
require __DIR__ . '/lib.php';

if (is_admin()) {
    redirect('admin.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = post_str('username');
    $p = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    if (!csrf_ok()) {
        $error = 'Сессия устарела. Обновите страницу и повторите вход.';
    } elseif ($u === '' || $p === '') {
        $error = 'Введите логин и пароль';
    } elseif (hash_equals(ADMIN_LOGIN, $u) && hash_equals(ADMIN_PASS, $p)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        redirect('admin.php');
    } else {
        $error = 'Неверный логин или пароль';
    }
}

page_header('Вход');
?>
<main>
    <div class="d-flex justify-content-between flex-wrap align-items-center">
        <h1>Вход</h1>
    </div>
    <?php if ($error): ?>
        <div class="alert alert-danger my-3"><?= h($error) ?></div>
    <?php endif; ?>
    <form class="my-2" novalidate method="post" action="login.php">
        <?= csrf_field() ?>
        <div class="my-2">
            <label for="username" class="form-label">Логин</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="my-2">
            <label for="password" class="form-label">Пароль</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit">Войти</button>
        </div>
    </form>
</main>
<?php page_footer(); ?>
