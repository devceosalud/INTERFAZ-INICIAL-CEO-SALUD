<?php

namespace App\Support\Scheduling;

final class SchedulingCapability
{
    public const MVP_ACCESS = 'appointment.mvp.access';

    public const VIEW = 'appointment.view';

    public const CREATE = 'appointment.create';

    public const UPDATE = 'appointment.update';

    public const WITHDRAW = 'appointment.withdraw';

    public const RESCHEDULE = 'appointment.reschedule';

    public const ASSIGN_RESPONSIBLE = 'appointment.responsible.assign';

    public const CREATE_HOLD = 'appointment.hold.create';

    public const EXTEND_HOLD = 'appointment.hold.extend';

    public const SUBMIT_PAYMENT = 'appointment.payment.submit';

    public const VERIFY_PAYMENT = 'appointment.payment.verify';

    public const OVERRIDE_DOWN_PAYMENT = 'appointment.down_payment.override';

    public const REQUEST_ZERO_COST = 'appointment.zero_cost.request';

    public const APPROVE_ZERO_COST = 'appointment.zero_cost.approve';

    public const CREATE_ADDITIONAL = 'appointment.additional.create';

    public const OVERBOOK = 'appointment.overbook';

    public const VIEW_AUDIT = 'appointment.audit.view';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::MVP_ACCESS,
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::WITHDRAW,
            self::RESCHEDULE,
            self::ASSIGN_RESPONSIBLE,
            self::CREATE_HOLD,
            self::EXTEND_HOLD,
            self::SUBMIT_PAYMENT,
            self::VERIFY_PAYMENT,
            self::OVERRIDE_DOWN_PAYMENT,
            self::REQUEST_ZERO_COST,
            self::APPROVE_ZERO_COST,
            self::CREATE_ADDITIONAL,
            self::OVERBOOK,
            self::VIEW_AUDIT,
        ];
    }
}
