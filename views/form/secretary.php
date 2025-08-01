<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$this->title = 'Заявки, ожидающие подписи секретаря';
?>

<div class="container py-4 mt-4">
    <h1 class="text-center text-primary fw-bold mb-4"><?= Html::encode($this->title) ?></h1>

    <div class="p-4 mb-4">
        <?php $searchForm = ActiveForm::begin([
            'method' => 'get',
            'action' => ['form/secretary'],
            'options' => ['class' => 'row g-3 justify-content-center align-items-end']
        ]); ?>

        <div class="col-md-4">
            <?= Html::input('text', 'search', Yii::$app->request->get('search'), [
                'class' => 'form-control shadow-sm',
                'placeholder' => 'Поиск по ФИО или ID'
            ]) ?>
        </div>

        <div class="col-md-3">
            <?= Html::dropDownList('status', Yii::$app->request->get('status'), [
                '' => 'Все',
                'signed' => 'Подписано секретарём',
                'unsigned' => 'Без подписи',
            ], ['class' => 'form-select shadow-sm']) ?>
        </div>

        <div class="col-md-2 d-grid">
            <?= Html::submitButton('Поиск', ['class' => 'btn btn-primary shadow-sm']) ?>
        </div>

        <div class="col-md-2 d-grid">
            <?= Html::a('Сброс', ['form/secretary'], ['class' => 'btn btn-outline-secondary shadow-sm']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>

    <?php if (empty($forms)): ?>
        <div class="alert alert-info shadow-sm d-flex align-items-center justify-content-center">
            <i class="bi bi-info-circle me-2"></i> Нет заявок, ожидающих подписи секретаря.
        </div>
    <?php else: ?>
        <div class="card shadow-sm p-3" style="border-radius: 20px;">
            <div class="table-responsive">
                <table class="table table-hover align-middle text-center mb-0">
                    <thead class="table-light">
                    <tr class="fw-semibold">
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
                            <td><?= $form->id ?></td>
                            <td><?= Html::encode("{$form->surname} {$form->first_name}") ?></td>
                            <td>
                                <?php
                                $sig = $form->documents[0]->signatures ?? [];
                                $firstSig = $sig[0] ?? null;
                                echo $firstSig ? Html::encode($firstSig->signer_role . ': ' . $firstSig->iin) : '—';
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($pdfMap[$form->id])): ?>
                                    <?= Html::a('<i class="bi bi-file-earmark-text"></i> PDF', $pdfMap[$form->id], [
                                        'target' => '_blank',
                                        'class' => 'btn btn-outline-secondary btn-sm shadow-sm'
                                    ]) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($signedMap[$form->id]): ?>
                                    <span class="badge bg-success">Подписано</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Без подписи</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$signedMap[$form->id] && !empty($filesMap[$form->id])): ?>
                                    <?= Html::a('Подписать', ['form/sign-secretary', 'id' => $form->id, 'doc' => $filesMap[$form->id]], [
                                        'class' => 'btn btn-outline-primary btn-sm shadow-sm',
                                    ]) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$this->registerJs(<<<JS
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
JS);
?>
