<?php

namespace app\services;

use app\models\Form;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Yii;
use yii\web\NotFoundHttpException;

class GeneratePdfService
{
    /**
     * Генерирует PDF по ID формы и сохраняет в файл
     *
     * @param int $formId
     * @return string путь к PDF
     * @throws NotFoundHttpException
     */
    public function generate(int $formId): string
    {
        $model = Form::findOne($formId);
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена.");
        }

        $html = Yii::$app->controller->renderPartial('/form/pdf', ['model' => $model, 'pdfData' => null]);

        $pdf = new Mpdf([
            'format' => 'A4',
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 20,
            'margin_right' => 20,
        ]);

        $pdf->WriteHTML($html);

        $pdfDir = Yii::getAlias('@webroot/uploads/pdf');
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0777, true);
        }

        $fileName = $model->surname . '_' . $model->first_name . '_' . $model->id . '_' . time() . '.pdf';
        $pdfPath = $pdfDir . '/' . $fileName;

        $pdf->Output($pdfPath, Destination::FILE);

        return $pdfPath;
    }
}