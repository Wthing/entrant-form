<?php
use yii\helpers\Html;

/** @var \app\models\Form $model */

$this->title = 'Успешная подпись документа';
?>

<style>
    body {
        background-color: #e0e5ec;
    }

    .morph-container {
        max-width: 800px;
        margin: 3rem auto;
        background: #f0f0f3;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 8px 8px 16px #c8c9cc, -8px -8px 16px #ffffff;
    }

    .morph-alert {
        background: #f0f0f3;
        border-radius: 15px;
        padding: 2rem;
        text-align: center;
        box-shadow: inset 4px 4px 10px #c8c9cc, inset -4px -4px 10px #ffffff;
    }

    .morph-alert h4 {
        color: #28a745;
        font-weight: bold;
        margin-bottom: 1rem;
    }

    .morph-card {
        background: #f0f0f3;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: inset 4px 4px 10px #c8c9cc, inset -4px -4px 10px #ffffff;
    }

    .morph-card h5 {
        font-weight: bold;
        color: #343a40;
        margin-bottom: 1rem;
    }

    .morph-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .morph-list li {
        background: #f0f0f3;
        padding: 1rem;
        margin-bottom: 0.75rem;
        border-radius: 10px;
        box-shadow: 4px 4px 10px #c8c9cc, -4px -4px 10px #ffffff;
        font-size: 1rem;
    }

    .morph-btn {
        background: #f0f0f3;
        color: #495057;
        border: none;
        border-radius: 30px;
        padding: 0.75rem 2rem;
        font-size: 1.1rem;
        box-shadow: 6px 6px 12px #c8c9cc, -6px -6px 12px #ffffff;
        transition: all 0.2s ease-in-out;
    }

    .morph-btn:hover {
        background: #e0e5ec;
        box-shadow: inset 4px 4px 8px #c8c9cc, inset -4px -4px 8px #ffffff;
        color: #007bff;
    }

    .morph-btn i {
        margin-right: 0.5rem;
    }
</style>

<div class="morph-container">

    <div class="morph-alert">
        <h4><i class="bi bi-check-circle-fill"></i> Документ успешно подписан!</h4>
        <p>Спасибо! Ваш документ был подписан электронной цифровой подписью и сохранён в системе.</p>
    </div>

    <div class="morph-card mt-4">
        <h5>Информация о документе</h5>
        <ul class="">
            <li><strong>ФИО:</strong> <?= Html::encode("{$model->surname} {$model->first_name} {$model->patronymic}") ?></li>
            <li><strong>Программа:</strong> <?= Html::encode($model->edu_program) ?></li>
            <li><strong>Язык обучения:</strong> <?= Html::encode($model->edu_language) ?></li>
            <li><strong>Дата подачи:</strong> <?= date('d.m.Y H:i', $model->date_filled) ?></li>
        </ul>
    </div>

    <div class="text-center mt-5">
        <a href="<?= Yii::$app->homeUrl ?>" class="morph-btn">
            <i class="bi bi-house-door-fill"></i> Вернуться на главную
        </a>
    </div>

</div>
