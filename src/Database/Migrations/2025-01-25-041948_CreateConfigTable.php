<?php

namespace Esoftdream\Config\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConfigTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'config_id' => [
                'type'           => 'int',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
                'comment'        => 'ID Config',
            ],
            'config_key' => [
                'type'       => 'varchar',
                'constraint' => 255,
                'comment'    => 'Key Config',
            ],
            'config_value' => [
                'type'    => 'text',
                'null'    => true,
                'comment' => 'Value Config',
            ],
            'config_type' => [
                'type'       => 'varchar',
                'constraint' => 31,
                'default'    => 'string',
                'comment'    => 'Type Config berdasarkan value',
            ],
            'config_created_datetime' => [
                'type'    => 'datetime',
                'null'    => false,
                'comment' => 'Waktu config dibuat',
            ],
            'config_updated_datetime' => [
                'type'    => 'datetime',
                'null'    => false,
                'comment' => 'Waktu config diupdate',
            ],
        ]);
        $this->forge->addPrimaryKey('config_id');
        $this->forge->createTable('sys_config', true);
    }

    public function down()
    {
        $this->forge->dropTable('sys_config', true);
    }
}
