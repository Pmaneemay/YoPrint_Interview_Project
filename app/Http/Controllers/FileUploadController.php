<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\FileUpload;
use App\Jobs\ProcessUploadedFile;
use Illuminate\Support\Facades\Log;

class FileUploadController extends Controller
{ 

    public function list() {
        return FileUpload::orderBy('updated_at', 'desc')->limit(10)->get();
    }

    public function init(Request $request)
    {
        $fileName = $request->input('file_name');
        $fileUpload = FileUpload::create([
            'filename' => '',
            'display_name' => $fileName,
            'status' => 'pending',
            'created_ip' => $request->ip(),
        ]);

        return response()->json([
            'id' => $fileUpload->id
        ]);
    }

    public function finish(Request $request, $id)
    {
        $file = $request->file('file');
        $fileUpload = FileUpload::findOrFail($id);
        $filename = uniqid() . '_' . $file->getClientOriginalName();

        $file->storeAs('uploads', $filename);
        $fileUpload->update([
            'filename' => $filename,
        ]);
        // Dispatch job
        ProcessUploadedFile::dispatch($fileUpload->id);

       return response()->json([
            'id' => $fileUpload->id,
            'file_name' => $fileUpload->display_name,
            'status' => $fileUpload->status,
            'created_at' => $fileUpload->created_at,
        ]);

    }

}
