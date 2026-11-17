<?php
require __DIR__ . '/lib.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = post_str('action');

    if (!csrf_ok()) {
        flash_set('danger', 'Сессия устарела. Обновите страницу и повторите действие.');
    } elseif ($action === 'approve') {
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $b = $st->fetch();
        if (!$b) {
            $pdo->rollBack();
            flash_set('danger', 'Заявка не найдена (возможно, уже удалена).');
        } elseif ($b['status'] === 'approved') {
            $pdo->rollBack();
            flash_set('warning', 'Заявка уже одобрена.');
        } elseif (has_overlap((int)$b['room_id'], $b['check_in'], $b['check_out'], $id)) {
            $pdo->rollBack();
            flash_set('danger', 'Нельзя одобрить: период пересекается с уже одобренной бронью этого номера.');
        } else {
            $pdo->prepare("UPDATE bookings SET status = 'approved' WHERE id = ?")->execute([$id]);
            $pdo->commit();
            flash_set('success', 'Заявка одобрена.');
        }
    } elseif ($action === 'delete') {
        $st = db()->prepare('DELETE FROM bookings WHERE id = ?');
        $st->execute([$id]);
        flash_set($st->rowCount() ? 'success' : 'warning', $st->rowCount() ? 'Заявка удалена.' : 'Заявка не найдена.');
    }
    redirect('admin.php');
}

$rows = db()->query("SELECT b.*, r.number, c.name AS category
                     FROM bookings b
                     JOIN rooms r ON r.id = b.room_id
                     JOIN categories c ON c.id = r.category_id
                     ORDER BY FIELD(b.status, 'new', 'approved', 'rejected'), b.created_at DESC")->fetchAll();

$labels = ['new' => ['Новая', 'secondary'], 'approved' => ['Одобрена', 'success'], 'rejected' => ['Отклонена', 'danger']];

page_header('Панель администратора');
?>
<main>
    <div class="d-flex justify-content-between flex-wrap align-items-center">
        <h1>Панель администратора</h1>
    </div>

    <?php if (!$rows): ?>
        <div class="alert alert-info my-3">Заявок на бронирование пока нет.</div>
    <?php endif; ?>

    <div class="d-flex justify-content-around flex-wrap align-items-center">
        <?php foreach ($rows as $b): [$label, $color] = $labels[$b['status']]; ?>
            <div class="card">
                <div class="card-body">
                    <span class="badge text-bg-<?= $color ?> mb-2"><?= $label ?></span>
                    <h5>Фамилия: <?= h($b['last_name']) ?></h5>
                    <h5>Имя: <?= h($b['first_name']) ?></h5>
                    <h5>Телефон: <?= h($b['phone']) ?></h5>
                    <h5>Email: <?= h($b['email']) ?></h5>
                    <ul class="list-group">
                        <li class="list-group-item">Номер: <?= h($b['number']) ?> (<?= h($b['category']) ?>)</li>
                        <li class="list-group-item">Дата заезда: <?= h(fmt_date($b['check_in'])) ?></li>
                        <li class="list-group-item">Дата выезда: <?= h(fmt_date($b['check_out'])) ?></li>
                    </ul>
                </div>
                <div class="d-grid gap-2">
                    <?php if ($b['status'] !== 'approved'): ?>
                        <form method="post" action="admin.php" class="plain-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-success w-100" type="submit">Одобрить</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="admin.php" class="plain-form" onsubmit="return confirm('Удалить заявку?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-danger w-100" type="submit">Удалить</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>
<?php page_footer(); ?>
