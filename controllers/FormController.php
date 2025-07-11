<?php

namespace app\controllers;

use app\models\Document;
use app\models\Form;
use app\services\GeneratePdfService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class FormController extends Controller
{
    public function actionCreate()
    {
        $model = new Form();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $pdfService = new GeneratePdfService();

            $pdfService->generate($model->id);

            $pattern = 'uploads/pdf/' . $model->surname . '_' . $model->first_name . '_' . $model->patronymic . '_' . $model->id . '_*.pdf';
            $matches = glob($pattern);

            if (!empty($matches)) {
                $doc = file_get_contents($matches[0]);
            } else {
                throw new NotFoundHttpException('Файл не найден.');
            }

            $bas64 = base64_encode($doc);
            $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
            $xml->addChild('document', "$bas64");
            return $this->render('pdf',
                ['model' => $model,
                    'pdfData' => $xml->asXML()]);
        }

        return $this->render('form', [
            'model' => $model,
        ]);
    }

    public function actionView(int $formId)
    {
        $form = Form::findOne($formId);
        $pattern = 'uploads/pdf/' . $form->surname . '_' . $form->first_name . '_' . $form->patronymic . '_' . $form->id . '_*.pdf';
        $matches = glob($pattern);

        if (!empty($matches)) {
            $doc = file_get_contents($matches[0]);
        } else {
            throw new NotFoundHttpException('Файл не найден.');
        }

        $bas64 = base64_encode($doc);
        $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
        $xml->addChild('document', "$bas64");

        return $this->render('view', [
            'form' => $form,
            'pdfData' => $xml->asXML(),
        ]);
    }

}