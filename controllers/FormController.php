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
use ZipArchive;

class FormController extends Controller
{
    public const APPLICANT = 'applicant';
    public const PARENT = 'parent';
    public const SECRETARY = 'secretary';

    public function actionCreate()
    {
        $userId = Yii::$app->user->id;
        $document = new Document();
        $s3 = Yii::$app->s3;
//        $s3->commands()->delete('forms/1_Жамбеков_Арсен/signature_254_1752815264.sig')->execute();
//        $s3->commands()->delete('forms/1_Жамбеков_Арсен/signature_254_1752815339.sig')->execute();
//        $s3->commands()->delete('forms/1_Жамбеков_Арсен/Жамбеков_Арсен_254_1752815242.pdf')->execute();

        $model = new Form();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // 1. Генерация PDF и загрузка в S3
            $pdfService = new GeneratePdfService();
            $s3Path = $pdfService->generate($model->id); // ← возвращает путь в S3
            Yii::info($s3Path, 'ssssssssss');

            $prefix = 'forms/' . $userId . '_' . $model->surname . '_' . $model->first_name . '/';
            $result = $s3->commands()->list($prefix)->execute();
            $files = $result['Contents'] ?? [];
            Yii::info($files);

            // 2. Проверка и скачивание PDF с S3
            $tmpLocalPath = Yii::getAlias('@runtime/tmp/' . basename($s3Path));
            $s3->commands()
                ->get($s3Path)
                ->saveAs($tmpLocalPath)
                ->execute();

            if (!file_exists($tmpLocalPath)) {
                throw new \yii\web\NotFoundHttpException('Не удалось скачать PDF из S3.');
            }

            // 3. Получаем содержимое и кодируем
            $doc = file_get_contents($tmpLocalPath);
            $base64 = base64_encode($doc);

            // 4. XML обёртка
            $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
            $xml->addChild('document', $base64);

            // 5. Сохраняем Document
            $document->user_id = $userId;
            $document->form_id = $model->id;
            $document->created_at = time();
            $document->save();

            // 6. Удаляем локальный временный файл
            @unlink($tmpLocalPath);

            // 7. Отображаем PDF
            return $this->render('pdf', [
                'model' => $model,
                'pdfData' => $xml->asXML(),
            ]);
        }

        // Форма по умолчанию
        return $this->render('form', [
            'model' => $model,
        ]);
    }


    public function actionAdd()
    {
        $userId = Yii::$app->user->id;
        $signData = Yii::$app->request->post('signData');
        $formId = Yii::$app->request->post('formId');
        $s3 = Yii::$app->s3;

        $model = Form::findOne($formId);
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена");
        }

        // Имя PDF файла в S3
        $pdfFileName = $model->surname . '_' . $model->first_name . '_' . $model->id;
        $prefix = 'forms/' . $userId . '_' . $model->surname . '_' . $model->first_name . '/';
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

        // Загружаем PDF во временный путь
        $tmpPdf = Yii::getAlias('@runtime/tmp/' . basename($pdfS3Path));
        $s3->commands()->get($pdfS3Path)->saveAs($tmpPdf)->execute();

        // Сохраняем XML-подпись во временный файл
        $relativeDir = 'forms/' . $userId . '_' . $model->surname . '_' . $model->first_name;
        $sigFileName = 'signature_' . $model->id . '_' . time() . '.sig';
        $s3Path = $relativeDir . '/' . $sigFileName;
        $tmpSig = Yii::getAlias('@runtime/tmp/' . $sigFileName);
        file_put_contents($tmpSig, $signData);

        $tmpSigNew = Yii::getAlias('@runtime/tmp/' . $sigFileName);

        $s3->commands()->upload($s3Path, $tmpSigNew)->execute();

        // Парсим XML-подпись
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

        // Загружаем файл обратно и шлём в pdf-view, если applicant < 18
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
        $filesMap = [];

        foreach ($forms as $form) {
            $doc = $form->documents[0] ?? null;
            if (!$doc) {
                $pdfMap[$form->id] = null;
                continue;
            }

            $userId = $doc->user_id;
            $prefix = 'forms/' . $userId . '_' . $form->surname . '_' . $form->first_name . '/';
            try {
                $result = $s3->commands()->list($prefix)->execute();
                $files = $result['Contents'] ?? [];

                // фильтруем только нужные PDF
                $pdfFile = null;
                foreach ($files as $file) {
                    if (preg_match('/' . preg_quote($form->surname . '_' . $form->first_name . '_' . $form->id, '/') . '_.*\.pdf$/', $file['Key'])) {
                        $pdfFile = $file['Key'];
                        break;
                    }
                }

                if ($pdfFile) {
                    Yii::info($pdfFile, 'pdf');
                    $filesMap[$form->id] = $pdfFile;
                    // путь на клиент — через route или прямую ссылку (если публично доступен)
                    $pdfMap[$form->id] = $s3->getPresignedUrl($pdfFile, '+30 minutes'); // или: '/s3/proxy?key=' . urlencode($pdfFile)
                } else {
                    $pdfMap[$form->id] = null;
                }

            } catch (\Exception $e) {
                Yii::error("Ошибка получения списка файлов из S3: " . $e->getMessage(), __METHOD__);
                $pdfMap[$form->id] = null;
            }
        }

        Yii::info($pdfMap);

        return $this->render('secretary', [
            'forms' => $forms,
            'pdfMap' => $pdfMap,
            'filesMap' => $filesMap,
        ]);
    }


    public function actionAddSecretary()
    {
        $userId = Yii::$app->user->id;
        $signData = Yii::$app->request->post('signData');
        $formId = Yii::$app->request->post('formId');
        $s3 = Yii::$app->s3;

        $model = Form::findOne($formId);
        if (!$model) {
            throw new NotFoundHttpException("Форма не найдена");
        }

        $relativeDir = 'forms/' . $userId . '_' . $model->surname . '_' . $model->first_name;
        $filePrefix = $model->surname . '_' . $model->first_name . '_' . $model->id;

        // Получаем список файлов по префиксу
        $result = $s3->commands()->list($relativeDir . '/' . $filePrefix)->execute();

        if (empty($result['Contents'])) {
            throw new NotFoundHttpException('PDF-файл не найден в S3');
        }

        $pdfKey = $result['Contents'][0]['Key'];
        $doc = basename($pdfKey);

        // Сохраняем подпись локально во временную папку
        $sigFileName = 'signature_' . $model->id . '_' . time() . '.sig';
        $tmpSig = Yii::getAlias('@runtime/tmp/' . $sigFileName);
        if (!is_dir(dirname($tmpSig))) {
            mkdir(dirname($tmpSig), 0777, true);
        }
        file_put_contents($tmpSig, $signData);

        // Загружаем подпись в S3
        $sigS3Path = $relativeDir . '/' . $sigFileName;
        $s3->commands()->upload($sigS3Path, $tmpSig)->execute();

        // Обработка подписи
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

        // Архив в S3 больше не создаём — если нужно, делается через отдельную команду.

        return $this->render('success', [
            'model' => $model,
        ]);
    }


    public function actionSignSecretary($id, $doc)
    {
        $s3 = Yii::$app->s3;

        $model = Form::findOne($id);
        if (!$model) {
            throw new \yii\web\NotFoundHttpException("Форма не найдена");
        }

        try {
            $stream = $s3->commands()->get($doc)->execute()->get('Body');
            $pdfContent = $stream->getContents(); // ✅ Правильное использование
        } catch (\Exception $e) {
            Yii::error("Ошибка при получении PDF из S3: " . $e->getMessage(), 's3');
            throw new \yii\web\NotFoundHttpException("Файл PDF не найден на S3");
        }

        $base64 = base64_encode($pdfContent);
        $xml = new \SimpleXMLElement("<?xml version='1.0' standalone='yes'?><data></data>");
        $xml->addChild('document', $base64);

        return $this->render('sign-secretary', [
            'model' => $model,
            'pdfData' => $xml->asXML()
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