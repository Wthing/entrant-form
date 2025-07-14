<?php

namespace app\controllers;

use app\models\Document;
use app\models\DocumentSignature;
use app\models\Form;
use app\services\GeneratePdfService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class FormController extends Controller
{
    public function actionCreate()
    {
        $userId = Yii::$app->user->id;
        $document = new Document();

        $model = new Form();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $pdfService = new GeneratePdfService();

            $pdfService->generate($model->id);

            $pattern = 'uploads/pdf/' . $model->surname . '_' . $model->first_name . '_' . $model->id . '_*.pdf';
            $matches = glob($pattern);

            if (!empty($matches)) {
                $doc = file_get_contents($matches[0]);
            } else {
                throw new NotFoundHttpException('Файл не найден.');
            }

            $document->user_id = $userId;
            $document->form_id = $model->id;
            $document->created_at = time();
            $document->save();

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

    public function actionAdd()
    {
        $signData = Yii::$app->request->post('signData');
        $formId = Yii::$app->request->post('formId');
        Yii::info($formId);

        $model = Form::findOne($formId);
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена");
        }

        $pdfDir = Yii::getAlias('@webroot/uploads/pdf');
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0777, true);
        }

        // 🔍 Ищем PDF-файл
        $pattern = 'uploads/pdf/' . $model->surname . '_' . $model->first_name . '_' . $model->id . '_*.pdf';
        $matches = glob($pattern);

        if (!empty($matches)) {
            $doc = basename($matches[0]);
        } else {
            throw new NotFoundHttpException('Файл не найден.');
        }

        // 🖊️ Сохраняем SIG-файл
        $sigFileName = 'signature_' . $model->id . '_' . time() . '.sig';
        $sigPath = $pdfDir . '/' . $sigFileName;
        file_put_contents($sigPath, $signData);

        $certXml = simplexml_load_string($signData);
        if ($certXml === false) {
            throw new \RuntimeException('Ошибка разбора XML-подписи');
        }

        $certXml->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $certData = $certXml->xpath('//ds:X509Certificate');

        if (!$certData || empty($certData[0])) {
            throw new \RuntimeException('Не удалось извлечь X509 сертификат из подписи');
        }

        $certRaw = base64_decode((string)$certData[0]);
        $pem = "-----BEGIN CERTIFICATE-----\n" .
            chunk_split(base64_encode($certRaw), 64, "\n") .
            "-----END CERTIFICATE-----\n";

        $x509 = openssl_x509_parse($pem);
        if (!$x509) {
            throw new \RuntimeException('Ошибка при парсинге X.509 PEM-сертификата');
        }

        $subject = $x509['subject']['CN'] ?? null;

        $serialRaw = $x509['subject']['serialNumber'] ?? null;

        if (!$serialRaw || !preg_match('/^IIN(\d{12})$/', $serialRaw, $matches)) {
            throw new \RuntimeException('Некорректный или отсутствующий ИИН в сертификате');
        }

        $iin = $matches[1];

        $document = Document::find()->where(['form_id' => $model->id])->one();
        if (!$document) {
            throw new \RuntimeException("Документ не найден для формы ID {$model->id}");
        }

        $signature = new DocumentSignature();
        $signature->document_id = $document->id;
        $signature->pdf_path = str_replace(Yii::getAlias('@webroot'), '', $doc);
        $signature->signature_path = str_replace(Yii::getAlias('@webroot'), '', $sigPath);
        $signature->subject_dn = $subject;
        $signature->serial_number = $x509['serialNumberHex'] ?? '(нет серийного номера)';
        $signature->valid_from = $x509['validFrom_time_t'] ?? time();
        $signature->valid_until = $x509['validTo_time_t'] ?? time();
        $signature->signed_at = time();
        $signature->iin = $iin ?? null;

        if (!$signature->save()) {
            Yii::error($signature->getErrors(), 'signature');
            throw new \RuntimeException('Ошибка сохранения подписи: ' . print_r($signature->getErrors(), true));
        }

        return $this->render('success', [
            'model' => $model,
        ]);
    }


}