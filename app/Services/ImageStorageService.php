<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 画像の保存と削除をまとめて扱う。
 *
 * 保存先は public ディスク。表示URLは Storage::url() で組み立てる。
 * （`php artisan storage:link` が必要）
 */
class ImageStorageService
{
    /**
     * 画像を保存し、保存先のパスを返す。
     */
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    /**
     * 保存済みの画像を削除する。
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * 差し替え。新しいファイルがあれば古いものを消して保存し、
     * 削除フラグが立っていれば消すだけ。変更がなければ null を返す。
     *
     * @return array{changed: bool, path: ?string}
     */
    public function sync(?UploadedFile $file, ?string $currentPath, string $directory, bool $remove = false): array
    {
        // 「削除する」と「新しい画像」が同時に送られた場合は、新しい画像を優先する
        // （チェックの外し忘れで、選んだ画像まで消えてしまうのを防ぐ）
        if ($remove && $file === null) {
            $this->delete($currentPath);

            return ['changed' => true, 'path' => null];
        }

        if ($file === null) {
            return ['changed' => false, 'path' => $currentPath];
        }

        $this->delete($currentPath);

        return ['changed' => true, 'path' => $this->store($file, $directory)];
    }

    /**
     * 保存済みの画像を複製し、新しいパスを返す。元が無ければ null。
     */
    public function duplicate(?string $path, string $directory): ?string
    {
        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $target = $directory.'/'.bin2hex(random_bytes(16)).($extension !== '' ? '.'.$extension : '');

        Storage::disk('public')->copy($path, $target);

        return $target;
    }

    /**
     * 表示用URL。
     */
    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
