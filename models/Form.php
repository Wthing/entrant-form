<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "form".
 *
 * @property int $id
 * @property string $surname
 * @property string $first_name
 * @property string|null $patronymic
 * @property string $address
 * @property string $education_type
 * @property string $edu_program
 * @property string $edu_language
 * @property int $date_filled
 *
 * @property Document[] $documents
 */
class Form extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'form';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['patronymic'], 'default', 'value' => null],
            [['surname', 'first_name', 'address', 'education_type', 'edu_program', 'edu_language', 'date_filled'], 'required'],
            [['date_filled'], 'integer'],
            [['surname', 'first_name', 'patronymic', 'address', 'education_type', 'edu_program', 'edu_language'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'surname' => Yii::t('app', 'Surname'),
            'first_name' => Yii::t('app', 'First Name'),
            'patronymic' => Yii::t('app', 'Patronymic'),
            'address' => Yii::t('app', 'Address'),
            'education_type' => Yii::t('app', 'Education Type'),
            'edu_program' => Yii::t('app', 'Edu Program'),
            'edu_language' => Yii::t('app', 'Edu Language'),
            'date_filled' => Yii::t('app', 'Date Filled'),
        ];
    }

    /**
     * Gets query for [[Documents]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDocuments()
    {
        return $this->hasMany(Document::class, ['form_id' => 'id']);
    }

}
