<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Promo;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class VoucherService
{
    public function importFromCsv(UploadedFile $file, Promo $promo): int
    {
        $imported = 0;
        $handle = fopen($file->getPathname(), 'r');

        // Skip header row
        fgetcsv($handle);

        DB::transaction(function () use ($handle, $promo, &$imported) {
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 2) {
                    $code = trim($data[1]);

                    // Validate promo_id matches
                    if (! empty($code)) {
                        Voucher::create([
                            'promo_id' => $promo->id,
                            'code' => $code,
                            'is_used' => false,
                            'expired_at' => $promo->end_date ?? now()->addMonths(3),
                        ]);
                        $imported++;
                    }
                }
            }
        });

        fclose($handle);

        return $imported;
    }

    public function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
