<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Подача заявления';
?>
<div class="site-index">

    <div class="jumbotron text-center mt-5 py-5 rounded">
        <h1 class="display-5 mb-3">Добро пожаловать!</h1>
        <p class="lead mb-4">Это платформа для онлайн-заполнения заявления в университет.</p>

        <?= Html::a('Начать заполнение заявления', ['form/create'], ['class' => 'btn btn-success btn-lg']) ?>
    </div>
</div>
