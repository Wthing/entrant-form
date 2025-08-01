<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Заполнение заявления';
?>

<div class="container-fluid d-flex flex-column justify-content-center align-items-center">

    <div class="p-4 w-100" style="max-width: 850px; overflow-y: auto;">

        <h1 class="text-center text-primary mb-4"><?= Html::encode($this->title) ?></h1>

        <?php $form = ActiveForm::begin(['options' => ['class' => 'row g-3']]); ?>

        <!-- Личные данные -->
        <div class="col-md-4">
            <?= $form->field($model, 'surname')->textInput(['maxlength' => true, 'placeholder' => 'Иванов', 'class' => 'form-control shadow-sm']) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'first_name')->textInput(['maxlength' => true, 'placeholder' => 'Иван', 'class' => 'form-control shadow-sm']) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'patronymic')->textInput(['maxlength' => true, 'placeholder' => 'Иванович', 'class' => 'form-control shadow-sm']) ?>
        </div>

        <!-- Адрес -->
        <div class="col-12">
            <?= $form->field($model, 'address')->textarea(['rows' => 2, 'placeholder' => 'Адрес прописки', 'class' => 'form-control shadow-sm']) ?>
        </div>

        <!-- Тип поступления -->
        <div class="col-md-6">
            <?= $form->field($model, 'education_type')->dropDownList([
                'обладателя государственного образовательного гранта' => 'Гос. образовательный грант',
                'обладателя государственного образовательного гранта по сокращённым образовательным программам' => 'Гос. грант (сокращённые программы)',
                'обладателя гранта местного исполнительного органа' => 'Грант местного органа',
                'на платной основе по конкурсу сертификатов, выданных по результатам ЕНТ' => 'Платно по ЕНТ',
                'на платной основе по сокращённым образовательным программам' => 'Платно (сокращённые программы)',
                'грант «Казахстан Халкына»' => 'Грант «Казахстан Халкына»',
                'образовательный грант для молодёжи из густонаселённых и западных регионов' => 'Грант для отдалённых регионов',
            ], ['prompt' => 'Выберите тип', 'class' => 'form-select shadow-sm']) ?>
        </div>

        <!-- Программа -->
        <div class="col-md-6">
            <?= $form->field($model, 'edu_program')->dropDownList([
                '6В02101 — Дизайн (доучивание)' => '6В02101 — Дизайн (доучивание)',
                '6В04107 — Экономика промышленности' => '6В04107 — Экономика промышленности',
                // ... укороченный список для краткости ...
                '6В11302 — Логистика (Транспорт)' => '6В11302 — Логистика (Транспорт)',
            ], ['prompt' => 'Выберите программу', 'class' => 'form-select shadow-sm']) ?>
        </div>

        <!-- Язык обучения -->
        <div class="col-12 text-center">
            <label class="form-label fw-bold mb-2">Язык обучения</label><br>
            <div class="w-100 justify-content-center" role="group">
                <?php
                echo $form->field($model, 'edu_language')->radioList([
                    'Казахский' => 'Казахский',
                    'Русский' => 'Русский',
                    'Английский' => 'Английский',
                ], [
                    'item' => function ($index, $label, $name, $checked, $value) {
                        $id = 'btnradio' . $index;
                        $checkedAttr = $checked ? 'checked' : '';
                        return <<<HTML
<input type="radio" class="btn-check" name="{$name}" id="{$id}" value="{$value}" autocomplete="off" {$checkedAttr}>
<label class="btn btn-outline-primary mx-2 px-3 py-2 shadow-sm" for="{$id}">{$label}</label>
HTML;
                    },
                    'class' => 'd-inline-block',
                    'style' => 'margin: 0 auto;',
                ])->label(false);
                ?>
            </div>
        </div>

        <!-- Скрытая дата -->
        <?= $form->field($model, 'date_filled')->hiddenInput(['value' => time()])->label(false) ?>

        <!-- Кнопка -->
        <div class="col-12 mt-3">
            <?= Html::submitButton('Сохранить и сформировать PDF', [
                'class' => 'btn btn-success w-100 py-2 shadow-sm',
                'style' => 'border-radius: 30px; font-size: 1.1rem;'
            ]) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
