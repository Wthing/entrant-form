<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "document".
 *
 * @property int $id
 * @property int $user_id
 * @property int $form_id
 * @property string $status
 * @property int $created_at
 * @property int|null $signed_at
 *
 * @property Form $form
 * @property User $user
 */
class Document extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'document';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['signed_at'], 'default', 'value' => null],
            [['status'], 'default', 'value' => 'draft'],
            [['user_id', 'form_id', 'created_at'], 'required'],
            [['user_id', 'form_id', 'created_at', 'signed_at'], 'integer'],
            [['status'], 'string', 'max' => 20],
            [['form_id'], 'exist', 'skipOnError' => true, 'targetClass' => Form::class, 'targetAttribute' => ['form_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'form_id' => Yii::t('app', 'Form ID'),
            'status' => Yii::t('app', 'Status'),
            'created_at' => Yii::t('app', 'Created At'),
            'signed_at' => Yii::t('app', 'Signed At'),
        ];
    }

    /**
     * Gets query for [[Form]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getForm()
    {
        return $this->hasOne(Form::class, ['id' => 'form_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

}
