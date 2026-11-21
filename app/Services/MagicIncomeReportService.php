<?php

namespace App\Services;

final class MagicIncomeReportService
{
    public static function summary(int $memberId, ?string $date = null): array
    {
        return MemberIncomeReportService::summary($memberId, $date, true);
    }
}
