<?php

namespace App\Enums;

enum SurveyType: string
{
    case Poll = 'poll';
    case Survey = 'survey';

    /**
     * Retrieve all case values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }
}
