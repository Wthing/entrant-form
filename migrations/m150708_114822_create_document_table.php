<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%document}}`.
 */
class m150708_114822_create_document_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('document', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'form_id' => $this->integer()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('draft'),
            'created_at' => $this->integer()->notNull(),
            'signed_at' => $this->integer()->null(),
        ]);

        $this->createIndex(
            'idx-document-user_id',
            'document',
            'user_id'
        );

        $this->createIndex(
            'idx-document-form_id',
            'document',
            'form_id'
        );

        // Внешние ключи
        $this->addForeignKey(
            'fk-document-user_id',
            'document',
            'user_id',
            'user',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-document-form_id',
            'document',
            'form_id',
            'form',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-document-user_id', 'document');
        $this->dropIndex('idx-document-user_id', 'document');

        $this->dropForeignKey('fk-document-form_id', 'document');
        $this->dropIndex('idx-document-form_id', 'document');
        $this->dropTable('document');
    }
}
