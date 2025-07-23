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
            'surname' => Yii::t('app', 'Фамилия'),
            'first_name' => Yii::t('app', 'Имя'),
            'patronymic' => Yii::t('app', 'Отчество'),
            'address' => Yii::t('app', 'Адрес'),
            'education_type' => Yii::t('app', 'Тип поступления'),
            'edu_program' => Yii::t('app', 'Образовательная программа'),
            'edu_language' => Yii::t('app', 'Язык обучения'),
            'date_filled' => Yii::t('app', 'Дата подписания'),
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
