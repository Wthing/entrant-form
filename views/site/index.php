<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Подача заявления';
?>
<div class="container mt-5">

    <div class="text-center p-5" style="max-width: 700px; margin: auto;">
        <h1 class="display-5 mb-4 text-primary">Добро пожаловать!</h1>
        <p class="lead mb-4 text-muted">Онлайн-платформа подачи заявления в университет.<br> Простой и быстрый способ заполнить все необходимые данные.</p>

        <?= Html::a('Начать оформление', ['form/create'], [
            'class' => 'btn btn-primary btn-lg px-4 py-2 shadow-sm',
            'style' => 'border-radius: 30px;'
        ]) ?>
    </div>

</div>
