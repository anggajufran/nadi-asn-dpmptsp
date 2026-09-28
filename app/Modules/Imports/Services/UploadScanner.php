<?php
namespace App\Modules\Imports\Services;

class UploadScanner
{
    public function assertClean(string $path): void
    {
        if (!config('uploads.scan_enabled')) {
            abort_if(app()->environment('production'), 503, 'Pemindai unggahan belum tersedia.');
            return;
        }
        $socket = @stream_socket_client(
            'tcp://' . config('uploads.clamav_host') . ':' . config('uploads.clamav_port'),
            $errno,
            $error,
            5,
        );
        abort_unless(
            $socket,
            503,
            'Pemindai unggahan sementara tidak tersedia. Coba kembali nanti.',
        );
        $file = null;
        try {
            stream_set_timeout($socket, config('uploads.scan_timeout'));
            $this->write($socket, "zINSTREAM\0");
            $file = @fopen($path, 'rb');
            abort_unless($file, 422, 'Berkas unggahan tidak dapat dibaca.');
            while (!feof($file)) {
                $chunk = fread($file, 65536);
                abort_if($chunk === false, 422, 'Berkas unggahan tidak dapat dibaca.');
                if ($chunk !== '') {
                    $this->write($socket, pack('N', strlen($chunk)) . $chunk);
                }
            }
            $this->write($socket, pack('N', 0));
            $response = stream_get_line($socket, 4096, "\0");
            $state = stream_get_meta_data($socket);
            abort_if(
                $state['timed_out'] || $response === false,
                503,
                'Pemindaian unggahan gagal. Coba kembali nanti.',
            );
            abort_if(
                str_ends_with($response, ' FOUND'),
                422,
                'Berkas ditolak oleh pemeriksaan keamanan.',
            );
            abort_unless(
                $response === 'stream: OK',
                503,
                'Pemindaian unggahan gagal. Coba kembali nanti.',
            );
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
            fclose($socket);
        }
    }

    private function write($socket, string $bytes): void
    {
        while ($bytes !== '') {
            $written = @fwrite($socket, $bytes);
            abort_unless($written > 0, 503, 'Pemindaian unggahan gagal. Coba kembali nanti.');
            $bytes = substr($bytes, $written);
        }
    }
}
