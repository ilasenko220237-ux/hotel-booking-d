<?php
require __DIR__ . '/lib.php';

$cats = db()->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();
$catIds = array_map('intval', array_column($cats, 'id'));

// Фильтр обрабатывается на сервере; неизвестные значения игнорируются
$cat = isset($_GET['category']) && is_string($_GET['category']) ? (int)$_GET['category'] : 0;
if ($cat && !in_array($cat, $catIds, true)) {
    $cat = 0;
}

$sql = 'SELECT r.*, c.name AS category FROM rooms r JOIN categories c ON c.id = r.category_id'
     . ($cat ? ' WHERE c.id = :c' : '') . ' ORDER BY RAND() LIMIT 6';
$st = db()->prepare($sql);
$st->execute($cat ? [':c' => $cat] : []);
$rooms = $st->fetchAll();

page_header('Каталог номеров');
?>
<main>
    <div class="d-flex justify-content-between flex-wrap align-items-center">
        <h1>Каталог номеров</h1>
        <form method="get" action="index.php" class="d-flex gap-2 align-items-center filter-form">
            <select name="category" class="form-select" aria-label="Категория">
                <option value="0">Все категории</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Применить</button>
            <a class="btn btn-danger" href="index.php">Сбросить фильтр</a>
        </form>
    </div>

    <?php if (!$rooms): ?>
        <div class="alert alert-warning my-3">По выбранным условиям номера не найдены. Нажмите «Сбросить фильтр».</div>
    <?php endif; ?>

    <div class="d-flex justify-content-around flex-wrap align-items-center">
        <?php foreach ($rooms as $r): ?>
            <div class="card">
                <img src="img/<?= h($r['image']) ?>" class="card-img-top" alt="<?= h($r['category']) ?>">
                <div class="card-body">
                    <h3>Категория: <?= h($r['category']) ?></h3>
                    <h5>Цена: <?= number_format((int)$r['price'], 0, '', ' ') ?> ₽ / сутки</h5>
                    <h5>Характеристики:</h5>
                    <ul class="list-group">
                        <li class="list-group-item">Мест: <?= (int)$r['capacity'] ?>, площадь: <?= (int)$r['area'] ?> м²</li>
                        <?php foreach (explode(';', $r['features']) as $f): ?>
                            <li class="list-group-item"><?= h($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="d-grid gap-2">
                    <a href="order.php?room=<?= (int)$r['id'] ?>" class="btn btn-success">Забронировать</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>
<?php page_footer(); ?>
