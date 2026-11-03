<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class UsersModel extends Model
{
    // The table this model represents
    protected $table = 'users';

    public function __construct()
    {
        parent::__construct();
        $this->call->database();
    }

    public function all()
    {
        return $this->db->table($this->table)->get_all();
    }
}