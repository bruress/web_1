<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Log;
use App\Metrics;
use App\Models\Note;

// Обработка формы добавления записи. Редиректим после POST (Post/Redirect/Get),
// чтобы повторная отправка формы не происходила при обновлении страницы
$formError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note = $_POST['note'] ?? null;
    if (!is_string($note) || trim($note) === '' || mb_strlen($note) > 500) {
        $formError = 'Введите заметку длиной от 1 до 500 символов.';
        http_response_code(422);
    } else {
        try {
            Note::create(['note' => trim($note)]);
            Metrics::noteCreated();
            header('Location: /', true, 303);
            exit;
        } catch (\Throwable $e) {
            Log::error('note_create_failed', ['exception' => $e]);
            $formError = 'Не удалось сохранить заметку. Попробуйте позже.';
            http_response_code(503);
        }
    }
}

$phpVersion = PHP_VERSION;
$hasPdoPgsql = extension_loaded('pdo_pgsql');
$hostname = gethostname();

$dbVersion = null;
$dbError = null;
$rows = [];

try {
    $dbVersion = (string) Database::boot()->getConnection()->selectOne('SELECT version() AS version')->version;
    $rows = Note::orderByDesc('id')->get();
} catch (\Throwable $e) {
    Log::error('notes_read_failed', ['exception' => $e]);
    $dbError = 'База данных временно недоступна.';
    http_response_code(503);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>nginx + php-fpm + PostgreSQL</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; color: #1c1c1c; }
        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.1rem; margin-top: 2rem; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 0.3rem 1rem; background: #f6f6f6; padding: 1rem; border-radius: 6px; }
        dt { font-weight: 600; }
        dd { margin: 0; }
        .ok { color: #0a7a2f; }
        .fail { color: #b00020; }
        table { border-collapse: collapse; width: 100%; margin: 0.5rem 0 1rem; }
        th, td { border: 1px solid #ddd; padding: 0.4rem 0.6rem; text-align: left; font-size: 0.9rem; }
        th { background: #f0f0f0; }
        form { display: flex; gap: 0.5rem; }
        input[type=text] { flex: 1; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 0.5rem 1.2rem; border: 0; border-radius: 4px; background: #2563eb; color: #fff; cursor: pointer; }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <h1>nginx + php-fpm + PostgreSQL</h1>

    <dl>
        <dt>Версия PHP</dt>
        <dd><?= h($phpVersion) ?></dd>

        <dt>Расширение pdo_pgsql</dt>
        <dd class="<?= $hasPdoPgsql ? 'ok' : 'fail' ?>"><?= $hasPdoPgsql ? 'подключено' : 'отсутствует' ?></dd>

        <dt>Хост контейнера</dt>
        <dd><?= h((string) $hostname) ?></dd>

        <dt>Подключение к БД</dt>
        <dd class="<?= $dbError === null ? 'ok' : 'fail' ?>">
            <?= $dbError === null ? h($dbVersion ?? '') : 'ошибка: ' . h($dbError) ?>
        </dd>
    </dl>

    <h2>Таблица demo</h2>
    <?php if (count($rows) > 0): ?>
        <table>
            <tr><th>id</th><th>создано</th><th>note</th></tr>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= h((string) $row['id']) ?></td>
                    <td><?= h((string) $row['created_at']) ?></td>
                    <td><?= h((string) $row['note']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Нет данных (таблица пуста или БД недоступна).</p>
    <?php endif; ?>

    <?php if ($formError !== null): ?>
        <p class="fail" role="alert"><?= h($formError) ?></p>
    <?php endif; ?>
    <form method="post" action="/">
        <input type="text" name="note" placeholder="Новая запись" required maxlength="500">
        <button type="submit">Добавить</button>
    </form>
</body>
</html>
