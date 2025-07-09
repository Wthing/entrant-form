<?php

namespace services;

use app\models\Form;
use Yii;

class FormService
{
    public function saveForm(array $data)
    {
        $form = new Form();
        $form->attributes = $data;
        $form->date_filled = time();

        if ($form->validate() && $form->save()) {
            return $form;
        }

        Yii::error("Ошибка сохранения формы: " . json_encode($form->errors));
        return null;
    }
}