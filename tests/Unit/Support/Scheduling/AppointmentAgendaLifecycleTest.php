<?php

namespace Tests\Unit\Support\Scheduling;

use App\Support\Scheduling\AppointmentAgendaLifecycle as Lifecycle;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\AppointmentVisibility;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AppointmentAgendaLifecycleTest extends TestCase
{
    public function test_legacy_preserves_every_existing_care_state_policy(): void
    {
        foreach (array_merge(AppointmentOccupancy::BLOCKING_STATES, AppointmentOccupancy::RELEASING_STATES, [null, 'UNKNOWN']) as $careState) {
            foreach ([null, Lifecycle::REGULAR, Lifecycle::ADDITIONAL] as $bookingType) {
                $this->assertSame(
                    AppointmentOccupancy::blocks($careState),
                    Lifecycle::consumesRegularSlot(Lifecycle::LEGACY, $bookingType, $careState)
                );
            }
        }
    }

    public function test_only_explicit_new_states_use_the_new_lifecycle(): void
    {
        $this->assertFalse(Lifecycle::usesNewLifecycle(Lifecycle::LEGACY));
        $this->assertTrue(Lifecycle::usesNewLifecycle(Lifecycle::PENDING_CONFIRMATION));
        $this->assertTrue(Lifecycle::usesNewLifecycle(Lifecycle::CONFIRMED));
        $this->assertFalse(Lifecycle::usesNewLifecycle('UNKNOWN'));
    }

    public function test_pending_and_additional_never_consume_regular_capacity(): void
    {
        foreach (AppointmentOccupancy::BLOCKING_STATES as $careState) {
            foreach ([null, Lifecycle::REGULAR, Lifecycle::ADDITIONAL] as $bookingType) {
                $this->assertFalse(Lifecycle::consumesRegularSlot(Lifecycle::PENDING_CONFIRMATION, $bookingType, $careState));
            }

            $this->assertFalse(Lifecycle::consumesRegularSlot(Lifecycle::CONFIRMED, Lifecycle::ADDITIONAL, $careState));
            $this->assertTrue(Lifecycle::consumesRegularSlot(Lifecycle::CONFIRMED, Lifecycle::REGULAR, $careState));
        }

        foreach (AppointmentOccupancy::RELEASING_STATES as $careState) {
            $this->assertFalse(Lifecycle::consumesRegularSlot(Lifecycle::CONFIRMED, Lifecycle::REGULAR, $careState));
        }
    }

    public function test_unclassified_confirmed_data_does_not_accidentally_release_a_slot(): void
    {
        $this->assertTrue(Lifecycle::consumesRegularSlot(Lifecycle::CONFIRMED, null, 'PROGRAMADO'));
    }

    public function test_visibility_uses_creator_not_booking_type_or_care_state(): void
    {
        $this->assertTrue(AppointmentVisibility::allows(Lifecycle::PENDING_CONFIRMATION, 10, 10));
        $this->assertFalse(AppointmentVisibility::allows(Lifecycle::PENDING_CONFIRMATION, 10, 20));
        $this->assertTrue(AppointmentVisibility::allows(Lifecycle::LEGACY, 10, 20));
        $this->assertTrue(AppointmentVisibility::allows(Lifecycle::CONFIRMED, 10, 20));
        $this->assertFalse(AppointmentVisibility::allows('UNKNOWN', 10, 10));
    }

    public function test_visibility_requires_an_authenticated_actor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AppointmentVisibility::allows(Lifecycle::LEGACY, 10, 0);
    }

    public function test_effective_private_owner_is_responsible_with_creator_fallback_and_no_admin_bypass(): void
    {
        $this->assertFalse(AppointmentVisibility::allows(Lifecycle::PENDING_CONFIRMATION, 10, 10, 20));
        $this->assertTrue(AppointmentVisibility::allows(Lifecycle::PENDING_CONFIRMATION, 10, 20, 20));
        $this->assertTrue(AppointmentVisibility::allows(Lifecycle::PENDING_CONFIRMATION, 10, 10, null));
    }
}
