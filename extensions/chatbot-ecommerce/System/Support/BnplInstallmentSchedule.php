<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class BnplInstallmentSchedule
{
    /**
     * @return list<array{number:int,amount:int,due_on:string}>
     */
    public static function build(
        int $amount,
        int $installmentCount,
        int $intervalDays,
        string $firstDueOn,
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('The BNPL amount must be positive.');
        }
        if ($installmentCount < 2 || $installmentCount > 60) {
            throw new InvalidArgumentException('The BNPL installment count must be between 2 and 60.');
        }
        if ($intervalDays < 1 || $intervalDays > 365) {
            throw new InvalidArgumentException('The BNPL installment interval must be between 1 and 365 days.');
        }

        $firstDate = new DateTimeImmutable($firstDueOn);
        $base = intdiv($amount, $installmentCount);
        $remainder = $amount % $installmentCount;
        $schedule = [];

        for ($index = 0; $index < $installmentCount; $index++) {
            $schedule[] = [
                'number' => $index + 1,
                'amount' => $base + ($index < $remainder ? 1 : 0),
                'due_on' => $firstDate
                    ->add(new DateInterval('P' . ($index * $intervalDays) . 'D'))
                    ->format('Y-m-d'),
            ];
        }

        return $schedule;
    }
}
