<?php

declare(strict_types=1);

namespace CrmStages\Domain;

/**
 * Enum стадий CRM-воронки.
 * Код → название → порядковый номер для определения направления переходов.
 */
enum Stage: string
{
    case Ice          = 'C0';
    case Touched      = 'C1';
    case Aware        = 'C2';
    case Interested   = 'W1';
    case DemoPlanned  = 'W2';
    case DemoDone     = 'W3';
    case Committed    = 'H1';
    case Customer     = 'H2';
    case Activated    = 'A1';
    case Null         = 'N0';

    public function label(): string
    {
        return match ($this) {
            self::Ice         => 'Ice',
            self::Touched     => 'Touched',
            self::Aware       => 'Aware',
            self::Interested  => 'Interested',
            self::DemoPlanned => 'Demo Planned',
            self::DemoDone    => 'Demo Done',
            self::Committed   => 'Committed',
            self::Customer    => 'Customer',
            self::Activated   => 'Activated',
            self::Null        => 'Null',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Ice         => 0,
            self::Touched     => 1,
            self::Aware       => 2,
            self::Interested  => 3,
            self::DemoPlanned => 4,
            self::DemoDone    => 5,
            self::Committed   => 6,
            self::Customer    => 7,
            self::Activated   => 8,
            self::Null        => -1,
        };
    }

    /**
     * Следующая стадия в воронке (null если последняя).
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Ice         => self::Touched,
            self::Touched     => self::Aware,
            self::Aware       => self::Interested,
            self::Interested  => self::DemoPlanned,
            self::DemoPlanned => self::DemoDone,
            self::DemoDone    => self::Committed,
            self::Committed   => self::Customer,
            self::Customer    => self::Activated,
            self::Activated   => null,
            self::Null        => null,
        };
    }
}
