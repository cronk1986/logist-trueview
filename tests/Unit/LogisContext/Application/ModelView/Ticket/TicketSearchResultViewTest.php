<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\Ticket;

use Logis\LogisContext\Application\ModelView\Ticket\TicketSearchResultView;
use PHPUnit\Framework\TestCase;

final class TicketSearchResultViewTest extends TestCase
{
    public function test_it_renders_the_search_fields_of_a_ticket(): void
    {
        $view = TicketSearchResultView::from(id: 812, repairId: 'R-10231', incidentNumber: 'INC-5521', subject: 'Zebra printer jams', status: 'Open');

        $this->assertSame([
            'id' => 812,
            'repair_id' => 'R-10231',
            'incident_number' => 'INC-5521',
            'subject' => 'Zebra printer jams',
            'status' => 'Open',
        ], $view->toArray());
    }
}
