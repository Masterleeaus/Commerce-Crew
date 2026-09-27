<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'receipt_number' => $this->receipt_number,
            'version' => (int) $this->version,
            'status' => $this->status,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'receipt_hash' => $this->receipt_hash,
            'data' => $this->data ?? [],
            'issued_at' => $this->issued_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
        ];
    }
}
