<?php

/**
 * Lets a `users` row be created by Google sign-in instead of a local
 * username/password: password becomes nullable (a Google-only account has
 * none), and google_id/avatar are added to link and display that account.
 */
class Add_google_auth_to_users {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if (!$this->_lava->dbforge->table_exists('users')) {
            return;
        }

        if (!$this->_lava->dbforge->column_exists('users', 'google_id')) {
            $this->_lava->dbforge->add_column('users', [
                'google_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => TRUE,
                    'after'      => 'password',
                ],
            ]);
            // Partial-unique in effect: MySQL allows multiple NULLs in a UNIQUE index,
            // so local (non-Google) accounts are unaffected.
            $this->_lava->db->raw('ALTER TABLE `users` ADD UNIQUE INDEX `google_id_unique` (`google_id`)');
        }

        if (!$this->_lava->dbforge->column_exists('users', 'avatar')) {
            $this->_lava->dbforge->add_column('users', [
                'avatar' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => TRUE,
                    'after'      => 'google_id',
                ],
            ]);
        }

        // A Google-only account never gets a local password.
        $this->_lava->dbforge->modify_column('users', [
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => TRUE,
            ],
        ]);
    }

    public function down()
    {
        if (!$this->_lava->dbforge->table_exists('users')) {
            return;
        }
        if ($this->_lava->dbforge->column_exists('users', 'avatar')) {
            $this->_lava->dbforge->drop_column('users', 'avatar');
        }
        if ($this->_lava->dbforge->column_exists('users', 'google_id')) {
            $this->_lava->db->raw('ALTER TABLE `users` DROP INDEX `google_id_unique`');
            $this->_lava->dbforge->drop_column('users', 'google_id');
        }
        $this->_lava->dbforge->modify_column('users', [
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => FALSE,
            ],
        ]);
    }
}
