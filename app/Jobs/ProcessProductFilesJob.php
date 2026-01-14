<?php

namespace App\Jobs;

use App\Models\Product;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProcessProductFilesJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 400;

    public function __construct(protected int $productId, protected array $files) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);

        if (!$product) {
            Log::error("Product not found for processing: $this->productId");
            return;
        }

        $attachments = [];
        foreach ($this->files as $file) {
            try {
                $oldPath = $file['path'];
                $newPath = str_replace('temp', 'active', $oldPath);

                if (!Storage::disk('public')->exists($oldPath)) continue;

                $fullOldPath = Storage::disk('public')->path($oldPath);
                $fullNewPath = Storage::disk('public')->path($newPath);

                if (str_contains($file['mime'], 'image')) {
                    Image::read($fullOldPath)
                        ->scaleDown(width: 1200)
                        ->toJpeg(quality: 85)
                        ->save($fullNewPath);
                    Storage::disk('public')->delete($oldPath);
                } else {
                    Storage::disk('public')->move($oldPath, $newPath);
                }

                $attachments[] = [
                    'product_id' => $product->id,
                    'file_path' => $newPath,
                    'file_type' => strtok($file['mime'], '/'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } catch (Exception $e) {
                Log::error("Failed to process product file: " . $e->getMessage());
            }
        }

        if (!empty($attachments)) {
            $product->files()->insert($attachments);
        }
    }
}
