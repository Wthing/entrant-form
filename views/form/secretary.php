<?php
/** @var yii\web\View $this */
/** @var app\models\Form[] $forms */
/** @var array $filesMap */

use yii\helpers\Html;

$this->title = 'Заявки, ожидающие подписи секретаря';
?>

<head>
    <title><?= Html::encode($this->title) ?></title>
</head>

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
                            <?php Yii::info($filesMap[$form->id], 'info') ?>
                            <?= Html::a('Подписать', ['form/sign-secretary', 'id' => $form->id, 'doc' => $filesMap[$form->id]], [
                                'class' => 'btn btn-outline-primary btn-sm',
                            ]) ?>
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
