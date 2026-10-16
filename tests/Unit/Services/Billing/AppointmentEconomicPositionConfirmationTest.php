<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\AppointmentEconomicPosition;
use PHPUnit\Framework\TestCase;

class AppointmentEconomicPositionConfirmationTest extends TestCase
{
    private function position(array $overrides = []): array
    {
        return array_replace([
            'secured' => false,
            'allocation_required' => false,
            'authority' => 'PAYMENTS',
            'paid_cents' => 0,
            'effective_cents' => 0,
        ], $overrides);
    }

    public function test_any_attributed_positive_payment_still_available_allows_confirmation(): void
    {
        foreach ([1, 3000, 4999] as $cents) {
            $this->assertTrue(AppointmentEconomicPosition::allowsConfirmation(
                $this->position(['paid_cents' => $cents, 'effective_cents' => $cents])
            ));
        }
    }

    public function test_zero_or_non_attributed_or_reversed_payment_does_not_allow_confirmation(): void
    {
        $cases = [
            [],
            ['paid_cents' => 3000, 'effective_cents' => 0],
            ['paid_cents' => 3000, 'effective_cents' => -100],
            ['paid_cents' => 3000, 'effective_cents' => 3000, 'allocation_required' => true],
            ['paid_cents' => 3000, 'effective_cents' => 3000, 'authority' => 'LEGACY_SNAPSHOT'],
            ['paid_cents' => 3000, 'effective_cents' => 3000, 'authority' => 'UNALLOCATED'],
            ['paid_cents' => 0, 'effective_cents' => 3000],
        ];
        foreach ($cases as $fields) {
            $this->assertFalse(AppointmentEconomicPosition::allowsConfirmation($this->position($fields)));
        }
    }

    public function test_existing_half_paid_secured_policy_is_preserved(): void
    {
        $this->assertTrue(AppointmentEconomicPosition::allowsConfirmation(
            $this->position(['secured' => true, 'authority' => 'LEGACY_SNAPSHOT', 'paid_cents' => 5000, 'effective_cents' => 5000])
        ));
    }
}
