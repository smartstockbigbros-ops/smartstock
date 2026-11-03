<?php

class Create_inventory_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('inventory')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'id' => [
                    'type'           => 'INT',
                    'unsigned'       => TRUE,
                    'auto_increment' => TRUE,
                    'null'           => FALSE,
                ],
                'sku' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => FALSE,
                ],
                'item_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => FALSE,
                ],
                'category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => FALSE,
                    'default'    => 'General',
                ],
                'material' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => TRUE,
                ],
                'quantity' => [
                    'type'       => 'INT',
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                    'default'    => 0,
                ],
                'unit' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => FALSE,
                    'default'    => 'pcs',
                ],
                'reorder_level' => [
                    'type'       => 'INT',
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                    'default'    => 10,
                ],
                'unit_price' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => FALSE,
                    'default'    => 0,
                ],
                'location' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 80,
                    'null'       => TRUE,
                ],
                'supplier' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 120,
                    'null'       => TRUE,
                ],
                'corrosion_sensitive' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                    'default'    => 0,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => TRUE,
                ],
                'created_at' => [
                    'type'    => 'DATETIME',
                    'null'    => FALSE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'updated_at' => [
                    'type'    => 'DATETIME',
                    'null'    => TRUE,
                    'default' => NULL,
                ],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key('sku', unique: TRUE, name: 'sku_unique')
            ->add_key('category', name: 'category_idx')
            ->add_key('item_name', name: 'item_name_idx')
            ->create_table('inventory');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('inventory');
    }
}
