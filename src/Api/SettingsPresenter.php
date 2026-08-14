<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\BookingSettings;

/** Serializa los ajustes globales de reserva (política de acompañantes). */
final class SettingsPresenter
{
    /** @return array<string, mixed> */
    public function settings(BookingSettings $settings): array
    {
        return [
            'companionFee' => [
                'amount' => $settings->getCompanionFeeAmount(),
                'currency' => $settings->getCompanionFeeCurrency()->value,
                'display' => $settings->getCompanionFeeCurrency()->format($settings->getCompanionFeeAmount()),
            ],
            'weekdayFreePerFlyer' => $settings->getWeekdayFreePerFlyer(),
        ];
    }
}
