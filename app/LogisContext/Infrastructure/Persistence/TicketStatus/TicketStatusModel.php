<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\TicketStatus;

use Database\Factories\TicketStatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class TicketStatusModel extends Model
{
    /** @use HasFactory<TicketStatusFactory> */
    use HasFactory;

    protected $table = 'ticket_statuses';

    /** @var list<string> */
    protected $fillable = [
        'name',
    ];

    protected static function newFactory(): TicketStatusFactory
    {
        return TicketStatusFactory::new();
    }
}
