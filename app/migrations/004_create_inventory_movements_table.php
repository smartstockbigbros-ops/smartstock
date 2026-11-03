<?php

/**
 * Audit trail for every stock change (manual +/- adjustments, edits that
 * change quantity, new items, and deletions). Deliberately NOT a foreign
 * key to `inventory` — a row here must survive the item it refers to being
 * deleted, so sku/item_name are copied at the time of the movement instead
 * of being looked up later.
 */
class Create_inventory_movements_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('inventory_movements')) {
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
                'inventory_id' => [
                    'type'     => 'INT',
                    'unsigned' => TRUE,
                    'null'     => FALSE,
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
                'delta' => [
                    'type' => 'INT',
                    'null' => FALSE,
                ],
                'quantity_after' => [
                    'type'     => 'INT',
                    'unsigned' => TRUE,
                    'null'     => FALSE,
                ],
                'reason' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => FALSE,
                ],
                'note' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => TRUE,
                ],
                'actor' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => TRUE,
                ],
                'created_at' => [
                    'type'    => 'DATETIME',
                    'null'    => FALSE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ])
            ->add_key('id', primary: TRUE)
            ->add_key('inventory_id', name: 'inventory_id_idx')
            ->add_key('created_at', name: 'created_at_idx')
            ->create_table('inventory_movements');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('inventory_movements');
    }
}
