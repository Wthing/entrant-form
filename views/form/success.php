<?php
use yii\helpers\Html;

/** @var \app\models\Form $model */

$this->title = 'Успешная подпись документа';
?>

<div class="container py-5">

    <div class="alert alert-success shadow-sm text-center" role="alert">
        <h4 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Документ успешно подписан!</h4>
        <p class="mb-0">Спасибо! Ваш документ был подписан электронной цифровой подписью и сохранён в системе.</p>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h5 class="card-title">Информация о документе</h5>
            <ul class="list-group list-group-flush mt-3">
                <li class="list-group-item"><strong>ФИО:</strong> <?= Html::encode("{$model->surname} {$model->first_name} {$model->patronymic}") ?></li>
                <li class="list-group-item"><strong>Программа:</strong> <?= Html::encode($model->edu_program) ?></li>
                <li class="list-group-item"><strong>Язык обучения:</strong> <?= Html::encode($model->edu_language) ?></li>
                <li class="list-group-item"><strong>Дата подачи:</strong> <?= date('d.m.Y H:i', $model->date_filled) ?></li>
            </ul>
        </div>
    </div>

    <div class="text-center mt-5">
        <a href="<?= Yii::$app->homeUrl ?>" class="btn btn-lg btn-secondary">
            <i class="bi bi-house-door-fill me-2"></i>Вернуться на главную
        </a>
    </div>

</div>
