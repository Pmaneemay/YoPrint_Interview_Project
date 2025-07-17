<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class fileUpload extends Model
{
    protected $table = 'file_uploads';

    protected $fillable = [
        'filename',
        'display_name',
        'created_ip',
        'status',
    ];


    // Relationship: A file upload may have many audit logs
    public function auditLogs()
    {
        return $this->hasMany(ProductAuditLog::class, 'file_id');
    }
}
