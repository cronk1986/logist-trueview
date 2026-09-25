<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Ticket;

use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Logis\SharedContext\Infrastructure\Persistence\LikePattern;

/**
 * @property int $id
 * @property int $customer_id
 * @property ?int $asset_id
 * @property string $repair_id
 * @property string $incident_number
 * @property string $subject
 * @property int $status_id
 * @property string $issue_description
 * @property int $created_by
 * @property Carbon $created_at
 * @property-read CustomerModel $customer
 * @property-read ?AssetModel $asset
 * @property-read TicketStatusModel $status
 * @property-read UserModel $creator
 */
final class TicketModel extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tickets';

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'asset_id',
        'repair_id',
        'incident_number',
        'subject',
        'status_id',
        'issue_description',
        'created_by',
    ];

    /** @return BelongsTo<CustomerModel, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(related: CustomerModel::class, foreignKey: 'customer_id');
    }

    /** @return BelongsTo<AssetModel, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(related: AssetModel::class, foreignKey: 'asset_id')->withTrashed();
    }

    /** @return BelongsTo<TicketStatusModel, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(related: TicketStatusModel::class, foreignKey: 'status_id');
    }

    /** @return BelongsTo<UserModel, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(related: UserModel::class, foreignKey: 'created_by');
    }

    /** @param Builder<TicketModel> $query */
    public function scopeVisibleTo(Builder $query, VisibleCustomers $customers): void
    {
        $query->whereIn('customer_id', $customers->values());
    }

    /** @param Builder<TicketModel> $query */
    public function scopeMatching(Builder $query, SearchTerm $term): void
    {
        $pattern = LikePattern::contains(value: $term->value());

        $query->where(column: function (Builder $query) use ($pattern): void {
            $query->where(column: 'subject', operator: 'like', value: $pattern)
                ->orWhere(column: 'repair_id', operator: 'like', value: $pattern)
                ->orWhere(column: 'incident_number', operator: 'like', value: $pattern);
        });
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }
}
