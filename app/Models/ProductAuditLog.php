<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAuditLog extends Model
{
    protected $table = 'product_audit_logs';

    protected $fillable = [
        'product_id',
        'file_id',
        'operation',
        'changed_ip',
        'changes',
    ];

    public $timestamps = ['changed_at'];
    const UPDATED_AT = null;

    // Cast 'changes' as array for easy access
    protected $casts = [
        'changes' => 'array',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function fileUpload()
    {
        return $this->belongsTo(FileUpload::class, 'file_id');
    }
}
