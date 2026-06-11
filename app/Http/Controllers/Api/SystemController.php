<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SystemController extends Controller
{
    public function exportBackup()
    {
        try {
            $database = env('DB_DATABASE');
            $username = env('DB_USERNAME');
            $password = env('DB_PASSWORD');
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            
            $filename = "cvba-backup-" . date('Y-m-d-H-i-s') . ".sql";
            $path = storage_path("app/" . $filename);
            
            // Deteksi path mysqldump (khususnya untuk Windows/Laragon/XAMPP yang tidak masuk PATH)
            $dumpBinary = 'mysqldump';
            exec('mysqldump --version 2> nul', $out, $ret);
            if ($ret !== 0 && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Cari manual di beberapa lokasi umum Windows
                $possiblePaths = [
                    'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe',
                    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                ];
                foreach ($possiblePaths as $p) {
                    if (file_exists($p)) {
                        $dumpBinary = '"' . $p . '"';
                        break;
                    }
                }
            }
            
            $passwordOption = $password ? "--password={$password}" : "";
            $command = "{$dumpBinary} --user={$username} {$passwordOption} --host={$host} --port={$port} {$database} > {$path}";
            
            // Execute the mysqldump command
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                Log::error('MySQL Dump failed', ['command' => $command, 'output' => $output, 'code' => $returnVar]);
                return response()->json([
                    'message' => 'Gagal mengekspor database. Pastikan mysqldump tersedia di environment server.'
                ], 500);
            }
            
            if (!file_exists($path)) {
                return response()->json([
                    'message' => 'File backup gagal dibuat.'
                ], 500);
            }
            
            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Backup exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan sistem saat membuat backup.'
            ], 500);
        }
    }
}
