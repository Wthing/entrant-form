<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%form}}`.
 */
class m250708_085818_create_form_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('form', [
            'id' => $this->primaryKey(),
            'surname' => $this->string()->notNull(),
            'first_name' => $this->string()->notNull(),
            'patronymic' => $this->string()->null(),
            'address' => $this->string()->notNull(),
            'education_type' => $this->string()->notNull(),
            'edu_program' => $this->string()->notNull(),
            'edu_language' => $this->string()->notNull(),
            'date_filled' => $this->integer()->notNull(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%form}}');
    }
}
