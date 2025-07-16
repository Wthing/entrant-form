<?php

namespace app\controllers;

use app\models\Document;
use app\models\DocumentSignature;
use app\models\Form;
use app\services\GeneratePdfService;
use DateTime;
use Exception;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class FormController extends Controller
{
    public const APPLICANT = 'applicant';
    public const PARENT = 'parent';
    public const SECRETARY = 'secretary';

    public function actionCreate()
    {
        $userId = Yii::$app->user->id;
        $document = new Document();

        $model = new Form();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $pdfService = new GeneratePdfService();

            $pdfService->generate($model->id);

            $pattern = 'uploads/' . $userId . '_' . $model->surname . '_' . $model->first_name . '/' . $model->surname . '_' . $model->first_name . '_' . $model->id . '_*.pdf';
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
        $userId = Yii::$app->user->id;
        $signData = Yii::$app->request->post('signData');
        $formId = Yii::$app->request->post('formId');

        $model = Form::findOne($formId);
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена");
        }

        $dirName = $userId . '_' . $model->surname . '_' . $model->first_name;
        $pdfDir = Yii::getAlias('@webroot/uploads/' . $dirName);
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0777, true);
        }

        $pattern = $pdfDir . '/' . $model->surname . '_' . $model->first_name . '_' . $model->id . '_*.pdf';
        $matches = glob($pattern);
        if (empty($matches)) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        $docFullPath = $matches[0];
        $docWebPath = str_replace(Yii::getAlias('@webroot'), '', $docFullPath);

        $sigFileName = 'signature_' . $model->id . '_' . time() . '.sig';
        $sigPath = $pdfDir . '/' . $sigFileName;
        file_put_contents($sigPath, $signData);

        $certXml = simplexml_load_string($signData);
        if ($certXml === false) {
            throw new \RuntimeException('Ошибка разбора XML-подписи');
        }

        $certXml->registerXPathNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $certData = $certXml->xpath('//ds:X509Certificate');
        if (empty($certData[0])) {
            throw new \RuntimeException('Не удалось извлечь сертификат из подписи');
        }

        $certRaw = base64_decode((string)$certData[0]);
        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($certRaw), 64, "\n") . "-----END CERTIFICATE-----\n";
        $x509 = openssl_x509_parse($pem);
        if (!$x509) {
            throw new \RuntimeException('Ошибка парсинга X.509 PEM сертификата');
        }

        $subject = $x509['subject']['CN'] ?? null;
        $serialRaw = $x509['subject']['serialNumber'] ?? null;
        if (!$serialRaw || !preg_match('/^IIN(\d{12})$/', $serialRaw, $matches)) {
            throw new \RuntimeException('Некорректный или отсутствующий ИИН');
        }

        $iin = $matches[1];

        $fullName = mb_strtoupper(trim($model->surname . ' ' . $model->first_name), 'UTF-8');
        $role = ($subject === $fullName) ? 'applicant' : 'parent';

        $duplicate = DocumentSignature::find()
            ->where([
                'subject_dn' => $subject,
                'serial_number' => $x509['serialNumberHex'] ?? null
            ])
            ->exists();

        if ($duplicate) {
            throw new \RuntimeException("Подпись от '{$subject}' уже существует");
        }

        $birthDate = $this->getBirthDateFromIIN($iin);
        if (!$birthDate) {
            throw new \RuntimeException('Не удалось определить дату рождения');
        }

        $age = $birthDate->diff(new DateTime())->y;

        $document = Document::find()->where(['form_id' => $model->id])->one();
        if (!$document) {
            throw new \RuntimeException("Документ не найден для формы ID {$model->id}");
        }

        $signature = new DocumentSignature();
        $signature->document_id = $document->id;
        $signature->pdf_path = $docWebPath;
        $signature->signature_path = str_replace(Yii::getAlias('@webroot'), '', $sigPath);
        $signature->subject_dn = $subject;
        $signature->serial_number = $x509['serialNumberHex'] ?? '(нет серийного номера)';
        $signature->valid_from = $x509['validFrom_time_t'] ?? time();
        $signature->valid_until = $x509['validTo_time_t'] ?? time();
        $signature->signed_at = time();
        $signature->iin = $iin;
        $signature->signer_role = $role;

        if (!$signature->save()) {
            Yii::error($signature->getErrors(), 'signature');
            throw new \RuntimeException('Ошибка сохранения подписи');
        }

        if ($role === 'applicant' && $age < 18) {
            $docData = file_get_contents($docFullPath);
            $base64 = base64_encode($docData);
            $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
            $xml->addChild('document', $base64);

            return $this->render('sign-pdf', [
                'model' => $model,
                'pdfData' => $xml->asXML()
            ]);
        }

        return $this->render('success', [
            'model' => $model,
        ]);
    }


    public function actionSecretary()
    {
        $forms = Form::find()
            ->joinWith('documents.signatures s1')
            ->where(['s1.signer_role' => ['applicant', 'parent']])
            ->andWhere(['not exists',
                DocumentSignature::find()
                    ->alias('s2')
                    ->join('INNER JOIN', 'document d', 'd.id = s2.document_id')
                    ->where('d.form_id = form.id')
                    ->andWhere(['s2.signer_role' => 'secretary'])
            ])
            ->groupBy('form.id')
            ->all();

        $pdfMap = [];

        foreach ($forms as $form) {
            $doc = $form->documents[0] ?? null;
            if (!$doc) {
                $pdfMap[$form->id] = null;
                continue;
            }

            $userId = $doc->user_id;
            $pattern = 'uploads/' . $userId . '_' . $form->surname . '_' . $form->first_name . '/' . $form->surname . '_' . $form->first_name . '_' . $form->id . '_*.pdf';
            $matches = glob($pattern);

            if (!empty($matches)) {
                $pdfMap[$form->id] = '/' . ltrim($matches[0], '/'); // относительный путь для ссылки
            } else {
                $pdfMap[$form->id] = null;
            }
        }

        return $this->render('secretary', [
            'forms' => $forms,
            'pdfMap' => $pdfMap,
        ]);
    }

    public function actionAddSecretary()
    {
        $userId = Yii::$app->user->id;
        $signData = Yii::$app->request->post('signData');
        $formId = Yii::$app->request->post('formId');
        Yii::info($formId);

        $model = Form::findOne($formId);
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена");
        }

        $pdfDir = Yii::getAlias('@webroot/uploads/' . $userId . '_' . $model->surname . '_' . $model->first_name);
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0777, true);
        }

        $pattern = 'uploads/' . $userId . '_' . $model->surname . '_' . $model->first_name . '/' . $model->surname . '_' . $model->first_name . '_' . $model->id . '_*.pdf';
        $matches = glob($pattern);

        if (!empty($matches)) {
            $doc = basename($matches[0]);
        } else {
            throw new NotFoundHttpException('Файл не найден.');
        }

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
        $signature->signer_role = self::SECRETARY;

        if (!$signature->save()) {
            Yii::error($signature->getErrors(), 'signature');
            throw new \RuntimeException('Ошибка сохранения подписи: ' . print_r($signature->getErrors(), true));
        }

        return $this->render('success', [
            'model' => $model,
        ]);
    }

    public function actionSignSecretary($id, $doc)
    {
        $model = Form::findOne($id);
        $document = file_get_contents(Yii::getAlias('@webroot') . $doc);

        $bas64 = base64_encode($document);
        $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
        $xml->addChild('document', "$bas64");
        return $this->render('sign-secretary',
            ['model' => $model,
                'pdfData' => $xml->asXML()]);
    }

    function getBirthDateFromIIN(string $iin): ?DateTime {
        if (!preg_match('/^(\d{2})(\d{2})(\d{2})(\d)/', $iin, $m)) {
            return null;
        }

        [$_, $yy, $mm, $dd, $centuryDigit] = $m;

        $century = match ((int)$centuryDigit) {
            1, 2 => 1800,
            3, 4 => 1900,
            5, 6 => 2000,
            default => null
        };

        if ($century === null) return null;

        $fullYear = $century + (int)$yy;

        $dateStr = sprintf('%04d-%02d-%02d', $fullYear, $mm, $dd);

        try {
            return new DateTime($dateStr);
        } catch (Exception) {
            return null;
        }
    }
}