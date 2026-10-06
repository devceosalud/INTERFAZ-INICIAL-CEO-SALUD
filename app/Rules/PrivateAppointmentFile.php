<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;

class PrivateAppointmentFile implements Rule
{
    public static function mime(UploadedFile $file): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
    }
    public function passes($attribute, $value): bool
    {
        if (!$value instanceof UploadedFile || !$value->isValid() || $value->getSize() < 1) { return false; }
        $mime = self::mime($value);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) { return false; }
        if ($mime === 'application/pdf') { return str_starts_with(file_get_contents($value->getRealPath(), false, null, 0, 5), '%PDF-'); }
        $size = @getimagesize($value->getRealPath());
        return $size !== false && $size[0] > 0 && $size[1] > 0 && $size[0] * $size[1] <= 40000000;
    }
    public function message(): string { return 'Adjunta una imagen JPG/PNG real o PDF válido, hasta 8 MB.'; }
}
