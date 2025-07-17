<?php

namespace App\Jobs;

use App\Models\FileUpload;
use App\Models\Product;
use App\Models\ProductAuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\FileStatusUpdated;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ProcessUploadedFile implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, Dispatchable;

    protected $fileUploadId;

    public function __construct($fileUploadId)
    {
        $this->fileUploadId = $fileUploadId;
    }

    // CLEANER: Ensures only valid UTF-8, trims, and removes control characters/BOM
    private function clean_string($value)
    {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = str_replace("\xEF\xBB\xBF", '', $value); // Remove BOM
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        return trim($value);
    }

    public function handle()
    {
        $fileUpload = FileUpload::find($this->fileUploadId);

        try {

            if (!$fileUpload) {
                Log::error('FileUpload not found', ['id' => $this->fileUploadId]);
                return;
            }

            $fileUpload->update(['status' => 'processing']);
            
            broadcast(new FileStatusUpdated($fileUpload));

            $filePath = storage_path('app/private/uploads/' . $fileUpload->filename);
            Log::debug('Open file at', ['filePath' => $filePath]);

            if (!file_exists($filePath)) {
                $fileUpload->update(['status' => 'failed']);
                
                broadcast(new FileStatusUpdated($fileUpload));
                Log::error('File does not exist', ['filePath' => $filePath]);
                return;
            }

            if (($handle = fopen($filePath, "r")) === false) {
                $fileUpload->update(['status' => 'failed']);
                
                broadcast(new FileStatusUpdated($fileUpload));
                Log::error('Failed to open CSV file', ['filePath' => $filePath]);
                return;
            }

            // Read and clean headers
            $header = fgetcsv($handle, 0, ",");
            if (!$header || count($header) === 0) {
                $fileUpload->update(['status' => 'failed']);
                
                broadcast(new FileStatusUpdated($fileUpload));
                Log::error('CSV header missing or empty', ['filePath' => $filePath]);
                fclose($handle);
                return;
            }

            // Clean all header fields (BOM, spaces, control chars)
            $header = array_map([$this, 'clean_string'], $header);

            // Map from CSV headers to DB columns
            $fieldMap = [
                'UNIQUE_KEY'             => 'unique_key',
                'PRODUCT_TITLE'          => 'product_title',
                'PRODUCT_DESCRIPTION'    => 'product_description',
                'STYLE#'                 => 'style_number',
                'SANMAR_MAINFRAME_COLOR' => 'sanmar_mainframe_color',
                'SIZE'                   => 'size',
                'COLOR_NAME'             => 'color_name',
                'PIECE_PRICE'            => 'piece_price',
            ];

            $requiredFields = array_keys($fieldMap);

            Log::debug('CSV Headers found', [
                'headers' => $header,
                'header_count' => count($header),
                'expected_fields' => $requiredFields
            ]);

            // Check if all required fields exist in headers
            $missingHeaders = [];
            foreach ($requiredFields as $field) {
                if (!in_array($field, $header)) {
                    $missingHeaders[] = $field;
                }
            }

            if (!empty($missingHeaders)) {
                $fileUpload->update(['status' => 'failed']);
                
                broadcast(new FileStatusUpdated($fileUpload));
                Log::error('Required CSV columns missing', [
                    'filePath' => $filePath,
                    'missing_headers' => $missingHeaders,
                    'available_headers' => $header
                ]);
                fclose($handle);
                return;
            }

            $lineNum = 2;
            $successCount = 0;
            $failCount = 0;

            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                if (count($header) !== count($data)) {
                    Log::warning('CSV row column count mismatch', [
                        'file' => $filePath,
                        'line' => $lineNum,
                        'header_count' => count($header),
                        'data_count' => count($data),
                        'data' => $data
                    ]);
                    $failCount++;
                    $lineNum++;
                    continue;
                }

                $row = array_combine($header, $data);

                // Clean every value in row (remove control chars, decode html, etc)
                foreach ($row as $k => &$value) {
                    $value = $this->clean_string(html_entity_decode($value, ENT_QUOTES | ENT_HTML401, 'UTF-8'));
                }
                unset($value);

                // Validate required fields are present and not empty
                $skip = false;
                foreach ($requiredFields as $field) {
                    if (!isset($row[$field]) || $row[$field] === '') {
                        Log::error('Row skipped due to missing/empty required field', [
                            'file' => $filePath,
                            'line' => $lineNum,
                            'field' => $field,
                            'value' => $row[$field] ?? 'NOT_SET',
                            'unique_key' => $row['UNIQUE_KEY'] ?? 'unknown'
                        ]);
                        $failCount++;
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    $lineNum++;
                    continue;
                }

                // Build DB insert array using mapping
                $dbRow = [];
                foreach ($fieldMap as $csvField => $dbField) {
                    $dbRow[$dbField] = $row[$csvField];
                }

                $dbRow['updated_ip'] = $fileUpload->created_ip;


                try {
                    $product = Product::updateOrCreate(
                        ['unique_key' => $dbRow['unique_key']],
                        $dbRow
                    );

                    ProductAuditLog::create([
                        'product_id'   => $product->id,
                        'file_id'      => $fileUpload->id,
                        'operation'    => $product->wasRecentlyCreated ? 'insert' : 'update',
                        'changed_ip'   => $fileUpload->created_ip,
                        'changes'      => json_encode($dbRow),
                        'changed_at'   => now(),
                    ]);

                    $successCount++;
                } catch (\Throwable $e) {
                    Log::error('Failed to upsert product', [
                        'file' => $filePath,
                        'line' => $lineNum,
                        'exception' => $e->getMessage(),
                        'db_row' => $dbRow
                    ]);
                    $failCount++;
                }

                $lineNum++;
            }
            fclose($handle);

            $fileUpload->update(['status' => 'completed']);
            broadcast(new FileStatusUpdated($fileUpload));

            Log::info('CSV import finished', [
                'file' => $filePath,
                'success' => $successCount,
                'failed' => $failCount
            ]);
        } catch (\Throwable $e) {
            Log::error('Error processing file upload job', [
                'fileUploadId' => $this->fileUploadId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            if (isset($fileUpload) && $fileUpload) {
                $fileUpload->update(['status' => 'failed']);
                broadcast(new FileStatusUpdated($fileUpload));
            }
        }
    }
}
 