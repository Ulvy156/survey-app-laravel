<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dateTime('available_from_datetime')->nullable()->after('expires_at');
            $table->dateTime('available_until_datetime')->nullable()->after('available_from_datetime');
        });

        DB::table('surveys')
            ->select([
                'id',
                'created_at',
                'expires_at',
                'available_from_time',
                'available_until_time',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($surveys): void {
                foreach ($surveys as $survey) {
                    DB::table('surveys')
                        ->where('id', $survey->id)
                        ->update([
                            'available_from_datetime' => $this->combineDateAndTime(
                                $survey->created_at,
                                $survey->available_from_time
                            ),
                            'available_until_datetime' => $this->combineDateAndTime(
                                $survey->expires_at ?? $survey->created_at,
                                $survey->available_until_time
                            ),
                        ]);
                }
            });

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['available_from_time', 'available_until_time']);
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->renameColumn('available_from_datetime', 'available_from_time');
            $table->renameColumn('available_until_datetime', 'available_until_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->time('available_from_only_time')->nullable()->after('expires_at');
            $table->time('available_until_only_time')->nullable()->after('available_from_only_time');
        });

        DB::table('surveys')
            ->select([
                'id',
                'available_from_time',
                'available_until_time',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($surveys): void {
                foreach ($surveys as $survey) {
                    DB::table('surveys')
                        ->where('id', $survey->id)
                        ->update([
                            'available_from_only_time' => $this->extractTime($survey->available_from_time),
                            'available_until_only_time' => $this->extractTime($survey->available_until_time),
                        ]);
                }
            });

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['available_from_time', 'available_until_time']);
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->renameColumn('available_from_only_time', 'available_from_time');
            $table->renameColumn('available_until_only_time', 'available_until_time');
        });
    }

    protected function combineDateAndTime(?string $baseDateTime, ?string $time): ?string
    {
        if (! $baseDateTime || ! $time) {
            return null;
        }

        $base = Carbon::parse($baseDateTime);
        $parsedTime = $this->parseTimeValue($time);

        if (! $parsedTime) {
            return null;
        }

        return $base
            ->copy()
            ->setTimeFromTimeString($parsedTime)
            ->format('Y-m-d H:i:s');
    }

    protected function parseTimeValue(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, $time)->format('H:i:s');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function extractTime(?string $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::parse($dateTime)->format('H:i:s');
    }
};
