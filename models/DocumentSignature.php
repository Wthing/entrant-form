<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "document_signature".
 *
 * @property int $id
 * @property int $document_id
 * @property string $pdf_path Путь к PDF файлу
 * @property string $signature_path Путь к .sig файлу
 * @property string $subject_dn Distinguished Name из сертификата
 * @property string $serial_number
 * @property int $valid_from
 * @property int $valid_until
 * @property int $signed_at
 * @property string $iin
 * @property string $signer_role
 */
class DocumentSignature extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'document_signature';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['document_id', 'pdf_path', 'signature_path', 'subject_dn', 'serial_number', 'valid_from', 'valid_until', 'signed_at'], 'required'],
            [['document_id', 'valid_from', 'valid_until', 'signed_at'], 'integer'],
            [['iin', 'signer_role'], 'string', 'max' => 12],
            [['pdf_path', 'signature_path', 'subject_dn', 'serial_number'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'document_id' => Yii::t('app', 'Document ID'),
            'pdf_path' => Yii::t('app', 'Pdf Path'),
            'signature_path' => Yii::t('app', 'Signature Path'),
            'subject_dn' => Yii::t('app', 'Subject Dn'),
            'serial_number' => Yii::t('app', 'Serial Number'),
            'valid_from' => Yii::t('app', 'Valid From'),
            'valid_until' => Yii::t('app', 'Valid Until'),
            'signed_at' => Yii::t('app', 'Signed At'),
            'iin' => Yii::t('app', 'Iin'),
            'signer_role' => Yii::t('app', 'Signer Role'),
        ];
    }

    public function getDocument()
    {
        return $this->hasOne(Document::class, ['id' => 'document_id']);
    }


}
