<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Form $model */
/** @var yii\widgets\ActiveForm $form */

?>

<?php
$s3 = Yii::$app->s3;

$prefix = 'forms/1_Жамбеков_Арсен';
$result = $s3->commands()->list($prefix)->execute();
$files = $result['Contents'] ?? [];
Yii::info($files);
?>

<div class="form-form">

    <h1>Заполнение заявления</h1>

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'surname')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'first_name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'patronymic')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'address')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'education_type')->dropDownList([
        'обладателя государственного образовательного гранта' => 'обладателя государственного образовательного гранта',
        'обладателя государственного образовательного гранта по сокращённым образовательным программам' => 'обладателя гос. гранта по сокращённым программам',
        'обладателя гранта местного исполнительного органа' => 'обладателя гранта местного исполнительного органа',
        'на платной основе по конкурсу сертификатов, выданных по результатам ЕНТ' => 'на платной основе по конкурсу сертификатов',
        'на платной основе по сокращённым образовательным программам' => 'на платной основе по сокращённым программам',
        'грант «Казахстан Халкына»' => 'грант «Казахстан Халкына»',
        'образовательный грант для молодёжи из густонаселённых и западных регионов' => 'грант для молодежи из густонаселённых и западных регионов',
    ], ['prompt' => 'Выберите тип поступления']) ?>

    <?= $form->field($model, 'edu_program')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'edu_language')->dropDownList([
        'Казахский' => 'Казахский',
        'Русский' => 'Русский',
        'Английский' => 'Английский',
    ], ['prompt' => 'Выберите язык обучения']) ?>

    <?= $form->field($model, 'date_filled')->hiddenInput(['value' => time()])->label(false) ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить и сформировать PDF', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
