<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%document_signature}}`.
 */
class m250708_113920_create_document_signature_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('document_signature', [
            'id' => $this->primaryKey(),
            'document_id' => $this->integer()->notNull(),
            'pdf_path' => $this->string()->notNull()->comment('Путь к PDF файлу'),
            'signature_path' => $this->string()->notNull()->comment('Путь к .sig файлу'),
            'subject_dn' => $this->string()->notNull()->comment('Distinguished Name из сертификата'),
            'serial_number' => $this->string()->notNull(),
            'valid_from' => $this->integer()->notNull(),
            'valid_until' => $this->integer()->notNull(),
            'iin' => $this->string()->notNull(),
            'signed_at' => $this->integer()->notNull(),
            'signer_role' => $this->string()->notNull(),
        ]);

        $this->createIndex(
            'idx-document-signature-document_id',
            'document_signature',
            'document_id'
        );

        $this->addForeignKey(
            'fk-document_signature-document_id',
            'document_signature',
            'document_id',
            'document',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-document_signature-document_id', 'document_signature');
        $this->dropIndex('idx-document-signature-document_id', 'document_signature');
        $this->dropTable('document_signature');
    }
}
