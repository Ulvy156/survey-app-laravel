<?php

namespace App\Models;

use App\Enums\SurveyType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'type',
        'created_by',
        'is_active',
        'is_closed',
        'share_token',
        'is_public',
        'expires_at',
        'available_from_time',
        'available_until_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SurveyType::class,
            'is_active' => 'boolean',
            'is_closed' => 'boolean',
            'is_public' => 'boolean',
            'expires_at' => 'datetime',
            'available_from_time' => 'datetime',
            'available_until_time' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(SurveyInvitation::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired(Builder $query, Carbon $now): Builder
    {
        return $query->where(function (Builder $scope) use ($now): void {
            $scope->whereNull('expires_at')
                ->orWhere('expires_at', '>', $now);
        });
    }

    public function scopeAvailableAt(Builder $query, Carbon $time): Builder
    {
        return $query->where(function (Builder $scope) use ($time): void {
            $scope->whereNull('available_from_time')
                ->orWhereNull('available_until_time')
                ->orWhere(function (Builder $window) use ($time): void {
                    $window->where('available_from_time', '<=', $time)
                        ->where('available_until_time', '>=', $time);
                });
        });
    }

    public function isAvailableNow(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->trashed() || $this->is_closed) {
            return false;
        }

        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->available_from_time && $this->available_until_time) {
            if ($now->lt($this->available_from_time) || $now->gt($this->available_until_time)) {
                return false;
            }
        }

        return true;
    }
}
