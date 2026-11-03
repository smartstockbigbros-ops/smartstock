<?php
class ProductModel extends Model
{
    protected $table = 'products';
    protected $primary_key = 'id';
    protected $fillable = ['product_name', 'description', 'price', 'quantity'];
    protected $timestamps = false;

    public function __construct() {
        parent::__construct();
        $this->call->database();
    }

    public function get_all_products() {
        return $this->all();
    }

    public function get_product($id) {
        return $this->find($id);
    }

    public function create_product($data) {
        return $this->insert($data);
    }

    public function update_product($id, $data) {
        return $this->update($id, $data);
    }

    public function delete_product($id) {
        return $this->delete($id);
    }
}