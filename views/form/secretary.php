<?php
/** @var yii\web\View $this */
/** @var app\models\Form[] $forms */
/** @var array $filesMap */
/** @var array $signedMap */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$this->title = 'Заявки, ожидающие подписи секретаря';
?>

<head>
    <title><?= Html::encode($this->title) ?></title>
</head>

<div class="mb-4">
    <?php $searchForm = ActiveForm::begin([
        'method' => 'get',
        'action' => ['form/secretary'],
        'options' => ['class' => 'row g-2']
    ]); ?>

    <div class="col-md-3">
        <?= Html::input('text', 'search', Yii::$app->request->get('search'), [
            'class' => 'form-control',
            'placeholder' => 'Поиск по ФИО или ID'
        ]) ?>
    </div>

    <div class="col-md-3">
        <?= Html::dropDownList('status', Yii::$app->request->get('status'), [
            '' => 'Все',
            'signed' => 'Подписано секретарём',
            'unsigned' => 'Без подписи',
        ], ['class' => 'form-select']) ?>
    </div>

    <div class="col-md-2">
        <?= Html::submitButton('Поиск', ['class' => 'btn btn-primary w-100']) ?>
    </div>

    <div class="col-md-2">
        <?= Html::a('Сброс', ['form/secretary'], ['class' => 'btn btn-outline-secondary w-100']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold"><?= Html::encode($this->title) ?></h1>
    </div>

    <?php if (empty($forms)): ?>
        <div class="alert alert-info d-flex align-items-center">
            <i class="ti ti-info-circle me-2"></i> Нет заявок, ожидающих подписи секретаря.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle shadow-sm rounded">
                <thead class="table-primary text-center">
                <tr>
                    <th>ID</th>
                    <th>ФИО абитуриента</th>
                    <th>Подписант</th>
                    <th>Заявление</th>
                    <th>Статус</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($forms as $form): ?>
                    <tr>
                        <td class="text-center"><?= $form->id ?></td>
                        <td><?= Html::encode("{$form->surname} {$form->first_name}") ?></td>
                        <td>
                            <?php
                            $sig = $form->documents[0]->signatures ?? [];
                            $firstSig = $sig[0] ?? null;
                            echo $firstSig ? Html::encode($firstSig->signer_role . ': ' . $firstSig->iin) : '—';
                            ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($pdfMap[$form->id])): ?>
                                <?= Html::a('<i class="ti ti-file-text"></i> PDF', $pdfMap[$form->id], [
                                    'target' => '_blank',
                                    'class' => 'btn btn-outline-secondary btn-sm'
                                ]) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if ($signedMap[$form->id]): ?>
                                <span class="badge bg-success">✔ Подписано</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">✖ Без подписи</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if (!$signedMap[$form->id] && !empty($filesMap[$form->id])): ?>
                                <?= Html::a('Подписать', ['form/sign-secretary', 'id' => $form->id, 'doc' => $filesMap[$form->id]], [
                                    'class' => 'btn btn-outline-primary btn-sm',
                                ]) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$this->registerJs(<<<JS
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el);
    });
JS);
?>
