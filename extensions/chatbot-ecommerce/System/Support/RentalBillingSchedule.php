<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class RentalBillingSchedule
{
    public const FREQUENCIES = ['one_time', 'daily', 'weekly', 'fortnightly', 'monthly', 'custom'];

    /**
     * @return list<array{period_start:string,period_end:string,due_date:string}>
     */
    public static function periods(
        string $startsOn,
        string $throughDate,
        string $frequency,
        int $interval = 1,
        int $dueOffsetDays = 0,
        ?string $endsOn = null,
        int $limit = 500,
        ?int $customIntervalDays = null,
    ): array {
        $frequency = strtolower(trim($frequency));
        if (! in_array($frequency, self::FREQUENCIES, true)) {
            throw new InvalidArgumentException('Unsupported rental billing frequency.');
        }

        $interval = max(1, $interval);
        $dueOffsetDays = max(-365, min(365, $dueOffsetDays));
        $limit = max(1, min(5000, $limit));
        $start = self::date($startsOn);
        $through = self::date($throughDate);
        $agreementEnd = $endsOn ? self::date($endsOn) : null;

        if ($agreementEnd && $agreementEnd < $start) {
            throw new InvalidArgumentException('Agreement end date cannot be before its start date.');
        }
        if ($frequency === 'custom' && ($customIntervalDays ?? 0) < 1) {
            throw new InvalidArgumentException('A custom billing interval requires at least one day.');
        }

        if ($frequency === 'one_time') {
            if ($start > $through) {
                return [];
            }
            $end = $agreementEnd ?: $start;
            return [[
                'period_start' => $start->format('Y-m-d'),
                'period_end' => $end->format('Y-m-d'),
                'due_date' => $start->modify(sprintf('%+d days', $dueOffsetDays))->format('Y-m-d'),
            ]];
        }

        $periods = [];
        $cursor = $start;
        while ($cursor <= $through && count($periods) < $limit) {
            if ($agreementEnd && $cursor > $agreementEnd) {
                break;
            }

            $next = self::advance($cursor, $frequency, $interval, $customIntervalDays);
            $periodEnd = $next->modify('-1 day');
            if ($agreementEnd && $periodEnd > $agreementEnd) {
                $periodEnd = $agreementEnd;
            }

            $periods[] = [
                'period_start' => $cursor->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'due_date' => $cursor->modify(sprintf('%+d days', $dueOffsetDays))->format('Y-m-d'),
            ];

            if ($agreementEnd && $periodEnd >= $agreementEnd) {
                break;
            }
            $cursor = $next;
        }

        return $periods;
    }

    private static function advance(DateTimeImmutable $date, string $frequency, int $interval, ?int $customDays): DateTimeImmutable
    {
        return match ($frequency) {
            'daily' => $date->modify('+' . $interval . ' days'),
            'weekly' => $date->modify('+' . (7 * $interval) . ' days'),
            'fortnightly' => $date->modify('+' . (14 * $interval) . ' days'),
            'monthly' => self::addMonthsClamped($date, $interval),
            'custom' => $date->modify('+' . ((int) $customDays * $interval) . ' days'),
            default => throw new InvalidArgumentException('Unsupported rental billing frequency.'),
        };
    }

    private static function addMonthsClamped(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $zeroBased = ($year * 12 + ($month - 1)) + $months;
        $targetYear = intdiv($zeroBased, 12);
        $targetMonth = ($zeroBased % 12) + 1;
        $first = $date->setDate($targetYear, $targetMonth, 1);
        $targetDay = min($day, (int) $first->format('t'));

        return $first->setDate($targetYear, $targetMonth, $targetDay);
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), new DateTimeZone('UTC'));
        if (! $date || $date->format('Y-m-d') !== trim($value)) {
            throw new InvalidArgumentException('Rental billing dates must use YYYY-MM-DD.');
        }
        return $date;
    }
}
