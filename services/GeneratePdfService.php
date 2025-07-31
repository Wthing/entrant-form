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
        $s3 = Yii::$app->s3;

        if (!$model) {
            throw new \yii\web\NotFoundHttpException("Форма не найдена.");
        }

        $html = Yii::$app->controller->renderPartial('/form/pdf', [
            'model' => $model,
            'pdfData' => null,
        ]);

        $pdf = new Mpdf([
            'format' => 'A4',
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 20,
            'margin_right' => 20,
        ]);
        $pdf->WriteHTML($html);

        $relativeDir = 'forms/' . $model->id . '_' . $model->surname . '_' . $model->first_name;
        $fileName = $model->surname . '_' . $model->first_name . '_' . $model->id . '_' . time() . '.pdf';
        $localPath = Yii::getAlias('@runtime/tmp/' . $fileName);
        $s3Path = $relativeDir . '/' . $fileName;

        $dir = dirname($localPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdf->Output($localPath, Destination::FILE);

        $this->signDoc($localPath);

        $s3->commands()
            ->upload($s3Path, $localPath)
            ->withAcl('private')
            ->execute();

        @unlink($localPath);

        return $s3Path;
    }

    public function signDoc($pdf){
        $fileErr = Yii::getAlias('@runtime').'/test/documents/1.txt';

        include Yii::getAlias('@webroot')."/utils/kalkanFlags&constants.php";
        KalkanCrypt_Init();

        KalkanCrypt_TSASetURL("http://tsp.pki.gov.kz");
        $container = Yii::getAlias('@webroot')."/utils/GOST512_fe3c3d8372520e7f91a6a69052eb8188225ac3f5.p12";
        $password = $_ENV['EDS_PASS'];

        $alias = "";
        $storage = $KCST_PKCS12;
        $err = KalkanCrypt_LoadKeyStore($storage, $password,$container,$alias);
        if ($err > 0){
            echo "Error:\tKalkanCrypt_LoadKeyStore".$err."\n";
        }else{
            echo "Ok1\tKalkanCrypt_LoadKeyStore\n";
        }

        $outSign = "";
        $inData = $pdf;
        $flags_sign = $KC_SIGN_CMS + $KC_IN_FILE + $KC_OUT_BASE64 + $KC_WITH_TIMESTAMP;
        $err = KalkanCrypt_SignData("", $flags_sign, $inData, $outSign);

        if ($err > 0){
            file_put_contents($fileErr,KalkanCrypt_GetLastErrorString());
            die;
            $err_sign = 1;
        }
        $data = base64_decode($outSign);

        file_put_contents($pdf,$data);


        KalkanCrypt_Finalize();
        return true;

    }

}