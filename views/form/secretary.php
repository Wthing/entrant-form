<?php
/** @var $forms */
?>

<h2>Заявки, ожидающие подписи секретаря</h2>

<table border="1" cellpadding="8">
    <tr>
        <th>ID</th>
        <th>ФИО</th>
        <th>Действие</th>
    </tr>

    <?php foreach ($forms as $form): ?>
        <tr>
            <td><?= $form->id ?></td>
            <td><?= $form->surname . ' ' . $form->first_name ?></td>
            <td>
                <a href="/form/sign-secretary?id=<?= $form->id ?>">Подписать</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
