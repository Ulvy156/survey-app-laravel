<?php

namespace App\Enums;

enum SurveyInvitationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
