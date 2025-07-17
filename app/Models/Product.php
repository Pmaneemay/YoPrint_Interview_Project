<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'unique_key',
        'product_title',
        'product_description',
        'style_number',
        'sanmar_mainframe_color',
        'size',
        'color_name',
        'piece_price',
        'created_ip',
        'updated_ip',
    ];

    // Relationship: Product has many audit logs
    public function auditLogs()
    {
        return $this->hasMany(ProductAuditLog::class, 'product_id');
    }
}
