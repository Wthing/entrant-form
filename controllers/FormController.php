<?php

namespace app\controllers;

use app\models\Document;
use app\models\DocumentSignature;
use app\models\Form;
use app\services\GeneratePdfService;
use DateTime;
use Exception;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class FormController extends Controller
{
    public const APPLICANT = 'applicant';
    public const PARENT = 'parent';
    public const SECRETARY = 'secretary';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['secretary', 'add-secretary', 'sign-secretary'], // Названия действий, а НЕ маршруты
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['admin'], // Только авторизованные пользователи
                    ],
                ],
            ],
        ];
    }

    public function actionCreate()
    {

//        Yii::info(phpinfo());

//        $userId = Yii::$app->user->id;
        $document = new Document();
        $s3 = Yii::$app->s3;

        $prefix = 'forms/';
        $localPath = Yii::getAlias('@runtime/tmp/' . basename('forms/21_Жамбеков_Арсен/form_21_1754307868.zip'));
        $result = $s3->commands()->list($prefix)->execute();
        $s3->commands()
            ->get('forms/21_Жамбеков_Арсен/form_21_1754307868.zip')
            ->saveAs($localPath)
            ->execute();
        $files = $result['Contents'] ?? [];
        Yii::info($files);
//        $s3->commands()->delete('forms/50_Жамбеков_Арсен/form_50_1754281763.zip')->execute();
        $model = new Form();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $pdfService = new GeneratePdfService();
//            $pdfService = $this->generatePdfService;
            $s3Path = $pdfService->generate($model->id);

            $prefix = 'forms/' . $model->id . '_' . $model->surname . '_' . $model->first_name . '/';
            $result = $s3->commands()->list($prefix)->execute();
            $files = $result['Contents'] ?? [];
            Yii::info($files);

            $tmpLocalPath = Yii::getAlias('@runtime/tmp/' . basename($s3Path));
            $s3->commands()
                ->get($s3Path)
                ->saveAs($tmpLocalPath)
                ->execute();

            if (!file_exists($tmpLocalPath)) {
                throw new NotFoundHttpException('Не удалось скачать PDF из S3.');
            }

            $doc = file_get_contents($tmpLocalPath);
            $base64 = base64_encode($doc);

            $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
            $xml->addChild('document', $base64);

            // 5. Сохраняем Document
            $document->form_id = $model->id;
            $document->created_at = time();
            $document->save();

            @unlink($tmpLocalPath);

            return $this->render('pdf', [
                'model' => $model,
                'pdfData' => $xml->asXML(),
            ]);
        }

        return $this->render('form', [
            'model' => $model,
        ]);
    }

    public function actionAdd()
    {
//        $userId = Yii::$app->user->id;
        $signData = Yii::$app->request->post('signData');
        $formId = Yii::$app->request->post('formId');
        $s3 = Yii::$app->s3;

        $model = Form::findOne($formId);
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена");
        }

        $pdfFileName = $model->surname . '_' . $model->first_name . '_' . $model->id;
        $prefix = 'forms/' . $model->id . '_' . $model->surname . '_' . $model->first_name . '/';
        $result = $s3->commands()->list($prefix)->execute();
        $files = $result['Contents'] ?? [];
        Yii::info($files);

        $pdfS3Path = null;
        foreach ($files as $file) {
            $key = $file['Key'];
            if (str_starts_with($key, $prefix . $pdfFileName) && str_ends_with($key, '.pdf')) {
                $pdfS3Path = $key;
                break;
            }
        }

        if (!$pdfS3Path) {
            throw new NotFoundHttpException('PDF файл не найден в S3.');
        }

        $tmpPdf = Yii::getAlias('@runtime/tmp/' . basename($pdfS3Path));
        $s3->commands()->get($pdfS3Path)->saveAs($tmpPdf)->execute();

        $relativeDir = 'forms/' . $model->id . '_' . $model->surname . '_' . $model->first_name;
        $sigFileName = 'signature_' . $model->id . '_' . time() . '.sig';
        $s3Path = $relativeDir . '/' . $sigFileName;
        $tmpSig = Yii::getAlias('@runtime/tmp/' . $sigFileName);
        file_put_contents($tmpSig, $signData);

        $tmpSigNew = Yii::getAlias('@runtime/tmp/' . $sigFileName);

        $s3->commands()->upload($s3Path, $tmpSigNew)->execute();

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
        $signature->pdf_path = $pdfS3Path;
        $signature->signature_path = $prefix . $sigFileName;
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
            $docData = file_get_contents($tmpPdf);
            $base64 = base64_encode($docData);
            $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
            $xml->addChild('document', $base64);

            @unlink($tmpPdf);
            @unlink($tmpSig);

            return $this->render('sign-pdf', [
                'model' => $model,
                'pdfData' => $xml->asXML()
            ]);
        }

        @unlink($tmpPdf);
        @unlink($tmpSig);

        return $this->render('success', [
            'model' => $model,
        ]);
    }

    public function actionSecretary()
    {
        $s3 = Yii::$app->s3;
        $search = Yii::$app->request->get('search');
        $status = Yii::$app->request->get('status');

        $query = Form::find()
            ->alias('f')
            ->joinWith(['documents d', 'documents.signatures s'])
            ->groupBy('f.id');

        if ($search) {
            $query->andWhere([
                'or',
                ['like', 'f.surname', $search],
                ['like', 'f.first_name', $search],
                ['like', 'f.patronymic', $search],
                ['like', 's.iin', $search],
                ['f.id' => $search],
            ]);
        }

        // Фильтрация по статусу подписи секретаря
        if ($status === 'signed') {
            $query->andWhere(['exists',
                DocumentSignature::find()
                    ->alias('ds')
                    ->join('INNER JOIN', 'document d2', 'd2.id = ds.document_id')
                    ->where('d2.form_id = f.id')
                    ->andWhere(['ds.signer_role' => 'secretary'])
            ]);
        } elseif ($status === 'unsigned') {
            $query->andWhere(['not exists',
                DocumentSignature::find()
                    ->alias('ds')
                    ->join('INNER JOIN', 'document d2', 'd2.id = ds.document_id')
                    ->where('d2.form_id = f.id')
                    ->andWhere(['ds.signer_role' => 'secretary'])
            ]);
        }

        $forms = $query->all();
        $pdfMap = [];
        $filesMap = [];
        $signedMap = [];

        foreach ($forms as $form) {
            $doc = $form->documents[0] ?? null;
            $signed = false;

            if ($doc) {
                foreach ($doc->signatures as $sig) {
                    if ($sig->signer_role === 'secretary') {
                        $signed = true;
                        break;
                    }
                }
            }

            $signedMap[$form->id] = $signed;

            // Загрузка PDF
            $pdfMap[$form->id] = null;
            $filesMap[$form->id] = null;

            if ($doc) {
//                $userId = $doc->user_id;
                $prefix = 'forms/' . $form->id . '_' . $form->surname . '_' . $form->first_name . '/';

                try {
                    $result = $s3->commands()->list($prefix)->execute();
                    $files = $result['Contents'] ?? [];

                    foreach ($files as $file) {
                        if (preg_match('/' . preg_quote($form->surname . '_' . $form->first_name . '_' . $form->id, '/') . '_.*\.pdf$/', $file['Key'])) {
                            $filesMap[$form->id] = $file['Key'];
                            $pdfMap[$form->id] = $s3->getPresignedUrl($file['Key'], '+30 minutes');
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Yii::error("Ошибка S3: " . $e->getMessage(), __METHOD__);
                }
            }
        }

        return $this->render('secretary', [
            'forms' => $forms,
            'pdfMap' => $pdfMap,
            'filesMap' => $filesMap,
            'signedMap' => $signedMap,
        ]);
    }

    public function actionAddSecretary()
    {
        $signData = Yii::$app->request->post('signData');
        $formId = Yii::$app->request->post('formId');
        $s3 = Yii::$app->s3;

        $model = Form::findOne($formId);
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена");
        }

        $relativeDir = 'forms/' . $model->id . '_' . $model->surname . '_' . $model->first_name;
        $filePrefix = $model->surname . '_' . $model->first_name . '_' . $model->id;

        $result = $s3->commands()->list($relativeDir . '/' . $filePrefix)->execute();
        if (empty($result['Contents'])) {
            throw new NotFoundHttpException('PDF-файл не найден в S3');
        }

        $pdfKey = $result['Contents'][0]['Key'];

        $sigFileName = 'signature_' . $model->id . '_' . time() . '.sig';
        $tmpSig = Yii::getAlias('@runtime/tmp/' . $sigFileName);
        if (!is_dir(dirname($tmpSig))) {
            mkdir(dirname($tmpSig), 0777, true);
        }
        file_put_contents($tmpSig, $signData);

        $sigS3Path = $relativeDir . '/' . $sigFileName;
        $s3->commands()->upload($sigS3Path, $tmpSig)->execute();

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
        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($certRaw), 64, "\n") . "-----END CERTIFICATE-----\n";
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
        $signature->pdf_path = $pdfKey;
        $signature->signature_path = $sigS3Path;
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

        $tmpFolder = Yii::getAlias('@runtime/tmp/' . uniqid('form_', true));
        mkdir($tmpFolder, 0777, true);

        $s3Files = $s3->commands()->list($relativeDir)->execute();
        $s3Keys = [];

        foreach ($s3Files['Contents'] as $item) {
            $key = $item['Key'];
            $filename = basename($key);
            $localPath = $tmpFolder . '/' . $filename;

            $stream = $s3->commands()->get($key)->execute()->get('Body');
            file_put_contents($localPath, $stream->getContents());

            $s3Keys[] = $key;
        }

        $zipName = 'form_' . $model->id . '_' . time() . '.zip';
        $zipPath = $tmpFolder . '/' . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Не удалось создать ZIP архив');
        }

        foreach (glob($tmpFolder . '/*') as $file) {
            if (is_file($file) && basename($file) !== $zipName) {
                $zip->addFile($file, basename($file));
            }
        }
        $zip->close();

        $zipS3Key = $relativeDir . '/' . $zipName;
        $s3->commands()->upload($zipS3Key, $zipPath)->execute();

        foreach ($s3Keys as $oldKey) {
            $s3->commands()->delete($oldKey)->execute();
        }

        array_map('unlink', glob($tmpFolder . '/*'));
        rmdir($tmpFolder);

        return $this->render('success', [
            'model' => $model,
        ]);
    }

    public function actionSignSecretary($id, $doc)
    {
        $s3 = Yii::$app->s3;

        $model = Form::findOne($id);
//        $userId = Document::find()->select('user_id')->where(['form_id' => $id])->scalar();
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена");
        }

        try {
            $stream = $s3->commands()->get($doc)->execute()->get('Body');
            $pdfContent = $stream->getContents(); // ✅ Правильное использование
        } catch (\Exception $e) {
            Yii::error("Ошибка при получении PDF из S3: " . $e->getMessage(), 's3');
            throw new NotFoundHttpException("Файл PDF не найден на S3");
        }

        $base64 = base64_encode($pdfContent);
        $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
        $xml->addChild('document', $base64);

        return $this->render('sign-secretary', [
            'model' => $model,
            'pdfData' => $xml->asXML(),
        ]);
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