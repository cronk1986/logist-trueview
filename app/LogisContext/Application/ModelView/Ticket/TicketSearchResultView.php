<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\Ticket;

final readonly class TicketSearchResultView
{
    private function __construct(
        public int $id,
        public string $repairId,
        public string $incidentNumber,
        public string $subject,
        public string $status,
    ) {
    }

    public static function from(int $id, string $repairId, string $incidentNumber, string $subject, string $status): self
    {
        return new self(id: $id, repairId: $repairId, incidentNumber: $incidentNumber, subject: $subject, status: $status);
    }

    /**
     * @return array{id: int, repair_id: string, incident_number: string, subject: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'repair_id' => $this->repairId,
            'incident_number' => $this->incidentNumber,
            'subject' => $this->subject,
            'status' => $this->status,
        ];
    }
}
