<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use App\Models\FileUpload;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class FileStatusUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public $fileUpload;

    public function __construct(FileUpload $fileUpload)
    {
        $this->fileUpload = $fileUpload;
    }

    public function broadcastOn()
    {
        return new Channel('file-uploads');
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->fileUpload->id,
            'status' => $this->fileUpload->status,
            'file_name' => $this->fileUpload->display_name,
            'time' => $this->fileUpload->updated_at->toJSON(),
        ];
    }
}