<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use App\Models\Map;
use App\Models\Building;
use App\Models\Room;
use App\Models\Employee;

class ImportMapCommand extends Command
{
    protected $signature = 'map:import {zipPath} {statusId}';
    protected $description = 'Import a map ZIP in background and update status file';

    public function handle(): int
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');
        @ini_set('max_input_time', '600');
        @ini_set('memory_limit', '1024M');
        @ignore_user_abort(true);

        $relativeZip = $this->argument('zipPath');
        $statusId = $this->argument('statusId');
        $statusPath = storage_path('app/import_status/' . $statusId . '.json');

        $this->writeStatus($statusPath, 'running', 'Extracting ZIP', 5);
        try {
            $fullZipPath = storage_path('app/' . ltrim($relativeZip, '/'));
            if (!file_exists($fullZipPath)) {
                throw new \Exception('ZIP not found: ' . $fullZipPath);
            }

            $tempDir = storage_path('app/temp/import_' . time());
            if (!file_exists($tempDir)) mkdir($tempDir, 0755, true);

            $zip = new ZipArchive();
            if ($zip->open($fullZipPath) !== true) {
                throw new \Exception('Cannot open ZIP');
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $layoutPath = $tempDir . '/layout.json';
            if (!file_exists($layoutPath)) throw new \Exception('layout.json missing');
            $json = file_get_contents($layoutPath);
            $data = json_decode($json, true);
            if (!$data) throw new \Exception('Invalid layout.json');

            $importId = time() . '_' . rand(10000, 99999);
            $map = new Map();
            $map->name = ($data['map']['name'] ?? 'Imported Map') . ' (Imported ' . date('H:i:s') . ')';
            $map->width = $data['map']['width'] ?? 0;
            $map->height = $data['map']['height'] ?? 0;
            $map->is_active = false;
            $map->is_published = false;

            if (!empty($data['map']['image_filename'])) {
                $map->image_path = $this->copyImageFromImport($tempDir . '/images/' . $data['map']['image_filename'], 'maps', 'map_' . $importId);
            }
            $map->save();

            $buildings = $data['buildings'] ?? [];
            $count = max(1, count($buildings));
            $index = 0;
            
            // Process buildings in batches to avoid memory issues
            $batchSize = 10; // Process 10 buildings at a time
            $batches = array_chunk($buildings, $batchSize);
            
            foreach ($batches as $batchIndex => $buildingBatch) {
                $this->writeStatus($statusPath, 'running', 'Processing building batch ' . ($batchIndex + 1) . ' of ' . count($batches), min(10 + intval(($batchIndex / count($batches)) * 70), 80));
                
                foreach ($buildingBatch as $b) {
                    $index++;
                    if ($index % 5 === 0) { // Update status every 5 buildings
                        $this->writeStatus($statusPath, 'running', 'Importing buildings (' . $index . '/' . $count . ')', min(10 + intval(($index / $count) * 70), 80));
                    }

                    $building = new Building();
                    $building->map_id = $map->id;
                    $building->building_name = $b['building_name'] ?? 'Building';
                    $building->description = $b['description'] ?? '';
                    $building->services = $b['services'] ?? [];
                    $building->x_coordinate = $b['x_coordinate'] ?? 0;
                    $building->y_coordinate = $b['y_coordinate'] ?? 0;
                    $building->width = $b['width'] ?? 30;
                    $building->height = $b['height'] ?? 30;
                    $building->is_published = false;
                    if (!empty($b['image_filename'])) {
                        $building->image_path = $this->copyImageFromImport($tempDir . '/images/' . $b['image_filename'], 'buildings', 'building_' . $importId . '_' . rand(1000, 9999));
                    }
                    if (!empty($b['modal_image_filename'])) {
                        $building->modal_image_path = $this->copyImageFromImport($tempDir . '/images/' . $b['modal_image_filename'], 'buildings', 'modal_' . $importId . '_' . rand(1000, 9999));
                    }
                    $building->save();

                    foreach (($b['employees'] ?? []) as $e) {
                        if (empty($e['name'])) continue;
                        $emp = new Employee();
                        $emp->building_id = $building->id;
                        $emp->employee_name = $e['name'];
                        $emp->is_published = false;
                        if (!empty($e['image_filename'])) {
                            $emp->employee_image = $this->copyImageFromImport($tempDir . '/images/' . $e['image_filename'], 'employees', 'employee_' . $importId . '_' . rand(1000, 9999));
                        }
                        $emp->save();
                    }

                    foreach (($b['rooms'] ?? []) as $r) {
                        $room = new Room();
                        $room->building_id = $building->id;
                        $room->name = $r['name'] ?? 'Room';
                        $room->is_published = false;
                        if (!empty($r['panorama_image_filename'])) {
                            $room->panorama_image_path = $this->copyImageFromImport($tempDir . '/images/' . $r['panorama_image_filename'], 'rooms/360', 'panorama_' . $importId . '_' . rand(1000, 9999));
                        }
                        if (!empty($r['thumbnail_filename'])) {
                            $room->thumbnail_path = $this->copyImageFromImport($tempDir . '/images/' . $r['thumbnail_filename'], 'rooms/thumbnails', 'thumb_' . $importId . '_' . rand(1000, 9999));
                        }
                        $room->save();
                    }
                }
                
                // Memory cleanup after each batch
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            // cleanup
            $this->deleteDirectory($tempDir);
            $this->writeStatus($statusPath, 'success', 'Import complete', 100);
            return 0;
        } catch (\Throwable $e) {
            Log::error('ImportMapCommand failed', ['error' => $e->getMessage()]);
            $this->writeStatus($statusPath ?? storage_path('app/import_status/unknown.json'), 'error', $e->getMessage(), 100);
            return 1;
        }
    }

    private function writeStatus(string $path, string $status, string $message, int $progress): void
    {
        if (!file_exists(dirname($path))) @mkdir(dirname($path), 0755, true);
        @file_put_contents($path, json_encode(compact('status','message','progress')));
    }

    private function copyImageFromImport(string $sourcePath, string $directory, string $base): ?string
    {
        if (!file_exists($sourcePath)) return null;
        $fullDir = storage_path('app/public/' . $directory);
        if (!file_exists($fullDir)) @mkdir($fullDir, 0755, true);
        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = $base . '.' . $ext;
        $dest = $fullDir . '/' . $filename;
        if (@copy($sourcePath, $dest)) return $directory . '/' . $filename;
        return null;
    }

    private function deleteDirectory($dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir), ['.','..']) as $f) {
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->deleteDirectory($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}


