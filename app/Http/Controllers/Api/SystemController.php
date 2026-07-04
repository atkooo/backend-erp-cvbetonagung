<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class SystemController extends Controller
{
    public function exportBackup()
    {
        try {
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host', '127.0.0.1');
            $port = config('database.connections.mysql.port', '3306');

            $filename = 'cvba-backup-'.date('Y-m-d-H-i-s').'.sql';
            $path = storage_path('app/'.$filename);

            // Deteksi path mysqldump (khususnya untuk Windows/Laragon/XAMPP yang tidak masuk PATH)
            $dumpBinary = 'mysqldump';
            exec('mysqldump --version 2> nul', $out, $ret);
            if ($ret !== 0 && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Cari manual di beberapa lokasi umum Windows (tanpa hardcode versi MySQL)
                $searchDirs = [
                    'C:\\laragon\\bin\\mysql',
                    'C:\\xampp\\mysql\\bin',
                ];
                foreach ($searchDirs as $dir) {
                    if (! is_dir($dir)) {
                        continue;
                    }
                    // Cari mysqldump.exe di subfolder manapun (misal: mysql-8.x.x-winx64/bin)
                    $found = glob($dir.'\\*\\bin\\mysqldump.exe') ?: glob($dir.'\\mysqldump.exe');
                    if (! empty($found)) {
                        $dumpBinary = '"'.$found[0].'"';
                        break;
                    }
                }
            }

            $passwordOption = $password ? "--password={$password}" : '';
            $command = "{$dumpBinary} --user={$username} {$passwordOption} --host={$host} --port={$port} {$database} > {$path}";

            // Execute the mysqldump command
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                Log::error('MySQL Dump failed', ['command' => $command, 'output' => $output, 'code' => $returnVar]);

                return response()->json([
                    'message' => 'Gagal mengekspor database. Pastikan mysqldump tersedia di environment server.',
                ], 500);
            }

            if (! file_exists($path)) {
                return response()->json([
                    'message' => 'File backup gagal dibuat.',
                ], 500);
            }

            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Backup exception: '.$e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan sistem saat membuat backup.',
            ], 500);
        }
    }
}
