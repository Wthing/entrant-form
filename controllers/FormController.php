<?php

namespace app\controllers;

use app\models\Form;
use app\services\GeneratePdfService;
use Yii;
use yii\web\Controller;

class FormController extends Controller
{
    public function actionCreate()
    {
        $model = new Form();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $pdfService = new GeneratePdfService();
            $pdfService->generate($model->id);
            return $this->render('pdf',
                ['model' => $model]);
        }

        return $this->render('form', [
            'model' => $model,
        ]);
    }

}