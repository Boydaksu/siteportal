<?php

declare(strict_types=1);

namespace SitePortal\Core\Queue;

final class BackoffCalculator
{
    /**
     * @param int $attempt Kaçıncı deneme olduğu (1, 2, 3...)
     * @param int $baseSeconds İlk deneme bekleme tabanı (örn. 5s)
     * @param int $multiplier Üstel çarpan (örn. 2)
     * @param int $maxSeconds Üst sınır tavanı (örn. 300s)
     * @param bool $jitter Rastgele dalgalanma eklensin mi
     * @return int Bekleme süresi (saniye)
     */
    public static function calculate(
        int $attempt,
        int $baseSeconds = 5,
        int $multiplier = 2,
        int $maxSeconds = 300,
        bool $jitter = true
    ): int {
        $safeAttempt = max(1, $attempt);
        $exponent = $safeAttempt - 1;

        // Üstel hesaplama: base * (multiplier ^ exponent)
        $delay = $baseSeconds * ($multiplier ** $exponent);

        if ($delay > $maxSeconds) {
            $delay = $maxSeconds;
        }

        if ($jitter && $delay > 0) {
            // Sürü senkronizasyonunu (thundering herd) engellemek için %0-20 arası rastgele gecikme ekle
            $jitterMax = (int) max(1, round($delay * 0.2));
            $delay += random_int(0, $jitterMax);
        }

        return (int) min($delay, $maxSeconds);
    }
}
