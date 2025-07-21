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
        $userId = Yii::$app->user->id;
        $model = Form::findOne($formId);
        $s3 = Yii::$app->s3;

        if (!$model) {
            throw new \yii\web\NotFoundHttpException("Форма не найдена.");
        }

        $html = Yii::$app->controller->renderPartial('/form/pdf', [
            'model' => $model,
            'pdfData' => null,
        ]);

        $pdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 20,
            'margin_right' => 20,
        ]);
        $pdf->WriteHTML($html);

        $relativeDir = 'forms/' . $userId . '_' . $model->surname . '_' . $model->first_name;
        $fileName = $model->surname . '_' . $model->first_name . '_' . $model->id . '_' . time() . '.pdf';
        $localPath = Yii::getAlias('@runtime/tmp/' . $fileName); // ⬅️ временный путь
        $s3Path = $relativeDir . '/' . $fileName;

        $dir = dirname($localPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdf->Output($localPath, Destination::FILE);

        $s3->commands()
            ->upload($s3Path, $localPath)
            ->withAcl('private')
            ->execute();

        @unlink($localPath);

        return $s3Path;
    }

}