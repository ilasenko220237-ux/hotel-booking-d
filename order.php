<?php
require __DIR__ . '/lib.php';

$roomId = (int)($_GET['room'] ?? 0);
$st = db()->prepare('SELECT r.*, c.name AS category FROM rooms r JOIN categories c ON c.id = r.category_id WHERE r.id = ?');
$st->execute([$roomId]);
$room = $st->fetch();

$old = ['first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'check_in' => '', 'check_out' => ''];
$errors = [];
$posted = $room && $_SERVER['REQUEST_METHOD'] === 'POST';

if ($posted) {
    foreach ($old as $k => $_) {
        $old[$k] = post_str($k);
    }
    $today = new DateTimeImmutable('today');
    $nameRe = '/^[А-Яа-яЁё .\-]+$/u';

    if (!csrf_ok()) {
        $errors['form'] = 'Сессия устарела. Обновите страницу и отправьте форму ещё раз.';
    }

    foreach (['first_name' => 'имя', 'last_name' => 'фамилию'] as $k => $label) {
        if ($old[$k] === '') {
            $errors[$k] = "Пожалуйста, введите $label";
        } elseif (!preg_match($nameRe, $old[$k]) || !preg_match('/\p{L}/u', $old[$k])) {
            $errors[$k] = 'Допустимы только символы кириллицы, пробелы, точки и тире';
        } elseif (mb_strlen($old[$k]) > 50) {
            $errors[$k] = 'Не более 50 символов';
        }
    }

    if ($old['phone'] === '') {
        $errors['phone'] = 'Пожалуйста, введите номер телефона';
    } elseif (!preg_match('/^\+7\(\d{3}\)\d{3}-\d{2}-\d{2}$/', $old['phone'])) {
        $errors['phone'] = 'Телефон должен быть в формате +7(XXX)XXX-XX-XX';
    }

    if ($old['email'] === '') {
        $errors['email'] = 'Пожалуйста, введите email';
    } elseif (mb_strlen($old['email']) > 254 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email, например name@example.com';
    }

    $dates = [];
    foreach (['check_in' => 'заезда', 'check_out' => 'выезда'] as $k => $label) {
        if ($old[$k] === '') {
            $errors[$k] = "Пожалуйста, введите дату $label";
        } elseif (!($dates[$k] = parse_date($old[$k]))) {
            $errors[$k] = "Дата $label должна существовать и быть в формате ДД.ММ.ГГГГ";
        }
    }
    if (isset($dates['check_in']) && $dates['check_in'] < $today) {
        $errors['check_in'] = 'Дата заезда не может быть раньше текущей даты';
    }
    if (!isset($errors['check_in']) && !isset($errors['check_out']) && isset($dates['check_in'], $dates['check_out'])
        && $dates['check_out'] <= $dates['check_in']) {
        $errors['check_out'] = 'Дата выезда должна быть позже даты заезда';
    }

    if (!$errors) {
        $in = $dates['check_in']->format('Y-m-d');
        $out = $dates['check_out']->format('Y-m-d');
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('SELECT id FROM rooms WHERE id = ? FOR UPDATE')->execute([$roomId]); // защита от гонки
        if (has_overlap($roomId, $in, $out)) {
            $pdo->rollBack();
            $errors['check_in'] = $errors['check_out'] = 'На выбранный период номер уже забронирован. Выберите другие даты (занятые дни отмечены в календаре).';
        } else {
            $pdo->prepare("INSERT INTO bookings (room_id, first_name, last_name, phone, email, check_in, check_out, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'new')")
                ->execute([$roomId, $old['first_name'], $old['last_name'], $old['phone'], $old['email'], $in, $out]);
            $pdo->commit();
            flash_set('success', 'Заявка успешно отправлена!');
            redirect('order.php?room=' . $roomId);
        }
    }
}

$cls = function (string $k) use ($errors, $posted): string {
    return isset($errors[$k]) ? ' is-invalid' : ($posted ? ' is-valid' : '');
};
$err = fn(string $k): string => isset($errors[$k]) ? h($errors[$k]) : '';

page_header('Бронирование номера');
?>
<main>
    <div class="d-flex justify-content-between flex-wrap align-items-center">
        <h1>Бронирование номера</h1>
    </div>

    <?php if (!$room): ?>
        <div class="alert alert-danger my-3">Номер не найден. Выберите номер в каталоге.</div>
        <a href="index.php" class="btn btn-primary">Перейти в каталог</a>
    <?php else: ?>
        <p class="lead mb-0">Номер <?= h($room['number']) ?> · <?= h($room['category']) ?> · <?= number_format((int)$room['price'], 0, '', ' ') ?> ₽ / сутки</p>

        <?php if (isset($errors['form'])): ?>
            <div class="alert alert-danger my-3"><?= h($errors['form']) ?></div>
        <?php endif; ?>

        <form class="row g-3 my-2" novalidate method="post" action="order.php?room=<?= (int)$room['id'] ?>">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label for="first_name" class="form-label">Имя</label>
                <input type="text" class="form-control<?= $cls('first_name') ?>" id="first_name" name="first_name" maxlength="50" value="<?= h($old['first_name']) ?>" required>
                <div class="invalid-feedback"><?= $err('first_name') ?></div>
            </div>
            <div class="col-md-4">
                <label for="last_name" class="form-label">Фамилия</label>
                <input type="text" class="form-control<?= $cls('last_name') ?>" id="last_name" name="last_name" maxlength="50" value="<?= h($old['last_name']) ?>" required>
                <div class="invalid-feedback"><?= $err('last_name') ?></div>
            </div>
            <div class="col-md-4">
                <label for="validationCustomPhone" class="form-label">Телефон</label>
                <input type="text" class="form-control<?= $cls('phone') ?>" id="validationCustomPhone" name="phone" placeholder="+7(___)___-__-__" value="<?= h($old['phone']) ?>" required>
                <div class="invalid-feedback"><?= $err('phone') ?></div>
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">Почта</label>
                <input type="text" class="form-control<?= $cls('email') ?>" id="email" name="email" maxlength="254" value="<?= h($old['email']) ?>" required>
                <div class="invalid-feedback"><?= $err('email') ?></div>
            </div>
            <div class="col-md-3">
                <label for="check_in" class="form-label">Дата заезда</label>
                <input type="text" class="form-control date-mask<?= $cls('check_in') ?>" id="check_in" name="check_in" placeholder="ДД.ММ.ГГГГ" value="<?= h($old['check_in']) ?>" required>
                <div class="invalid-feedback"><?= $err('check_in') ?></div>
            </div>
            <div class="col-md-3">
                <label for="check_out" class="form-label">Дата выезда</label>
                <input type="text" class="form-control date-mask<?= $cls('check_out') ?>" id="check_out" name="check_out" placeholder="ДД.ММ.ГГГГ" value="<?= h($old['check_out']) ?>" required>
                <div class="invalid-feedback"><?= $err('check_out') ?></div>
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-primary" type="submit">Отправить заявку</button>
            </div>
        </form>

        <h4 class="mt-4">Занятость номера</h4>
        <?php
        $busy = busy_days((int)$room['id']);
        $today = new DateTimeImmutable('today');
        $first = $today->modify('first day of this month');
        ?>
        <div class="d-flex flex-wrap gap-4">
            <?php for ($i = 0; $i < 3; $i++) {
                echo render_calendar($first->modify("+$i month"), $busy, $today);
            } ?>
        </div>
        <p class="legend"><span class="busy-dot"></span> занято (одобренная бронь) &nbsp; <span class="past-dot"></span> прошедшие дни</p>
    <?php endif; ?>
</main>
<?php page_footer(['jquery-3.7.1.slim.min.js', 'jquery.inputmask.min.js', 'index.js']); ?>
