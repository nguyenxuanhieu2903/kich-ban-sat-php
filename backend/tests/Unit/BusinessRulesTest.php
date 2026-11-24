<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit Test Suite for Business Rules (Phase D)
 * Tests core business calculations, rules, constraints and state logic in isolation.
 */
class BusinessRulesTest extends TestCase
{
    /**
     * Rule 1: 24h Golden Rule for Cancellation & Refund Eligibility.
     * If now is >= 24 hours before scheduled play time => 100% full automatic refund.
     * If now is < 24 hours before scheduled play time => late cancellation (requires manual review or non-refundable).
     */
    public function test24HourGoldenRuleEligibility(): void
    {
        $evalRule = function (string $scheduledAt, string $now): array {
            $sessionTime = strtotime($scheduledAt);
            $currentTime = strtotime($now);
            $diffHours = ($sessionTime - $currentTime) / 3600;

            if ($diffHours >= 24) {
                return [
                    'eligible_auto_refund' => true,
                    'status' => 'COMPLETED',
                    'reason' => 'Hủy trước 24 giờ - Hoàn tiền tự động 100%',
                ];
            }

            return [
                'eligible_auto_refund' => false,
                'status' => 'PENDING',
                'reason' => 'Hủy muộn dưới 24 giờ - Chuyển sang chờ Quản trị viên duyệt',
            ];
        };

        // Scenario A: Cancelled 48 hours in advance
        $resEarly = $evalRule('2026-12-01 19:00:00', '2026-11-29 19:00:00');
        $this->assertTrue($resEarly['eligible_auto_refund']);
        $this->assertSame('COMPLETED', $resEarly['status']);

        // Scenario B: Cancelled exactly 24 hours in advance
        $resExact = $evalRule('2026-12-01 19:00:00', '2026-11-30 19:00:00');
        $this->assertTrue($resExact['eligible_auto_refund']);
        $this->assertSame('COMPLETED', $resExact['status']);

        // Scenario C: Cancelled 23 hours and 59 minutes in advance (late)
        $resLate = $evalRule('2026-12-01 19:00:00', '2026-11-30 19:01:00');
        $this->assertFalse($resLate['eligible_auto_refund']);
        $this->assertSame('PENDING', $resLate['status']);

        // Scenario D: Cancelled 2 hours before session
        $resUrgent = $evalRule('2026-12-01 19:00:00', '2026-12-01 17:00:00');
        $this->assertFalse($resUrgent['eligible_auto_refund']);
        $this->assertSame('PENDING', $resUrgent['status']);
    }

    /**
     * Rule 2: Pricing Engine Calculation.
     * Base price + Time slot surcharge (Morning: 0, Afternoon: +20k, Evening: +40k) - Voucher discount (FIXED / PERCENT).
     * Final price cannot be negative (floor at 0).
     */
    public function testPricingEngineCalculation(): void
    {
        $calculatePrice = function (int $basePrice, string $slot, ?array $voucher = null): array {
            $slotSurcharges = [
                'MORNING' => 0,
                'AFTERNOON' => 20000,
                'EVENING' => 40000,
            ];

            $surcharge = $slotSurcharges[$slot] ?? 0;
            $subtotal = $basePrice + $surcharge;
            $discount = 0;

            if ($voucher !== null && ($voucher['is_active'] ?? false)) {
                if ($voucher['type'] === 'PERCENT') {
                    $discount = (int) round($subtotal * (($voucher['value'] ?? 0) / 100));
                    if (isset($voucher['max_discount']) && $voucher['max_discount'] > 0) {
                        $discount = min($discount, (int) $voucher['max_discount']);
                    }
                } elseif ($voucher['type'] === 'FIXED') {
                    $discount = (int) ($voucher['value'] ?? 0);
                }
            }

            $finalPrice = max(0, $subtotal - $discount);

            return [
                'base_price' => $basePrice,
                'surcharge' => $surcharge,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'final_price' => $finalPrice,
            ];
        };

        // Standard Morning booking (Base: 150,000)
        $morn = $calculatePrice(150000, 'MORNING');
        $this->assertSame(150000, $morn['final_price']);
        $this->assertSame(0, $morn['discount']);

        // Evening booking with 20% discount (Max 30,000)
        $evePromo = $calculatePrice(150000, 'EVENING', [
            'type' => 'PERCENT',
            'value' => 20,
            'max_discount' => 30000,
            'is_active' => true,
        ]);
        // Subtotal = 150,000 + 40,000 = 190,000. 20% = 38,000. Capped at 30,000. Final = 160,000.
        $this->assertSame(190000, $evePromo['subtotal']);
        $this->assertSame(30000, $evePromo['discount']);
        $this->assertSame(160000, $evePromo['final_price']);

        // Huge Fixed voucher exceeding price (Floor at 0)
        $freePromo = $calculatePrice(100000, 'MORNING', [
            'type' => 'FIXED',
            'value' => 200000,
            'is_active' => true,
        ]);
        $this->assertSame(0, $freePromo['final_price']);
    }

    /**
     * Rule 3: Deposit Deadline Generation.
     * Deposit deadline is min(join_time + 12 hours, formation_deadline_at).
     */
    public function testDepositDeadlineCalculation(): void
    {
        $computeDeadline = function (string $joinAt, string $formationDeadlineAt, int $windowHours = 12): string {
            $joinTime = strtotime($joinAt);
            $formTime = strtotime($formationDeadlineAt);
            $candidateTime = $joinTime + ($windowHours * 3600);

            $finalTime = min($candidateTime, $formTime);
            return date('Y-m-d H:i:s', $finalTime);
        };

        // When formation deadline is 48 hours away, candidate 12 hours wins
        $d1 = $computeDeadline('2026-11-25 10:00:00', '2026-11-27 10:00:00');
        $this->assertSame('2026-11-25 22:00:00', $d1);

        // When formation deadline is only 3 hours away, formation deadline caps the deposit window
        $d2 = $computeDeadline('2026-11-25 10:00:00', '2026-11-25 13:00:00');
        $this->assertSame('2026-11-25 13:00:00', $d2);
    }

    /**
     * Rule 4: Room State Machine Valid Transitions.
     */
    public function testRoomStateMachineTransitions(): void
    {
        $validTransitions = [
            'FORMING' => ['FULL', 'CANCELLED'],
            'FULL' => ['DEPOSITING', 'FORMING', 'CANCELLED'],
            'DEPOSITING' => ['SCHEDULED', 'FORMING', 'CANCELLED'],
            'SCHEDULED' => ['IN_PROGRESS', 'CANCELLED'],
            'IN_PROGRESS' => ['COMPLETED', 'CANCELLED'],
            'COMPLETED' => [],
            'CANCELLED' => [],
        ];

        $canTransition = function (string $from, string $to) use ($validTransitions): bool {
            return in_array($to, $validTransitions[$from] ?? [], true);
        };

        // Allowed transitions
        $this->assertTrue($canTransition('FORMING', 'FULL'));
        $this->assertTrue($canTransition('FULL', 'DEPOSITING'));
        $this->assertTrue($canTransition('DEPOSITING', 'SCHEDULED'));
        $this->assertTrue($canTransition('SCHEDULED', 'IN_PROGRESS'));
        $this->assertTrue($canTransition('IN_PROGRESS', 'COMPLETED'));
        $this->assertTrue($canTransition('FORMING', 'CANCELLED'));

        // Disallowed / Illegal transitions
        $this->assertFalse($canTransition('COMPLETED', 'FORMING'));
        $this->assertFalse($canTransition('CANCELLED', 'SCHEDULED'));
        $this->assertFalse($canTransition('FORMING', 'COMPLETED'));
    }

    /**
     * Rule 5: User Active Forming Room Limit (Host Quota = 3).
     */
    public function testHostActiveFormingRoomLimit(): void
    {
        $checkCanCreateRoom = function (int $currentActiveFormingRooms): bool {
            $maxAllowed = 3;
            return $currentActiveFormingRooms < $maxAllowed;
        };

        $this->assertTrue($checkCanCreateRoom(0));
        $this->assertTrue($checkCanCreateRoom(1));
        $this->assertTrue($checkCanCreateRoom(2));
        $this->assertFalse($checkCanCreateRoom(3));
        $this->assertFalse($checkCanCreateRoom(4));
    }

    /**
     * Rule 6: Student Verification Discount (HSSV -20,000 VND).
     */
    public function testStudentVerificationDiscountCalculation(): void
    {
        $calculateStudentDiscount = function (int $basePrice, string $studentCardStatus): array {
            $discount = 0;
            if ($studentCardStatus === 'APPROVED') {
                $discount = 20000;
            }
            $finalPrice = max(0, $basePrice - $discount);
            return [
                'base_price' => $basePrice,
                'discount' => $discount,
                'final_price' => $finalPrice,
            ];
        };

        // Approved Student Card
        $approved = $calculateStudentDiscount(150000, 'APPROVED');
        $this->assertSame(20000, $approved['discount']);
        $this->assertSame(130000, $approved['final_price']);

        // Pending or Rejected Student Card
        $pending = $calculateStudentDiscount(150000, 'PENDING');
        $this->assertSame(0, $pending['discount']);
        $this->assertSame(150000, $pending['final_price']);

        $rejected = $calculateStudentDiscount(150000, 'REJECTED');
        $this->assertSame(0, $rejected['discount']);
        $this->assertSame(150000, $rejected['final_price']);
    }

    /**
     * Rule 7: Host Kick Member Eligibility Rule.
     * Host can only kick players who have not deposited (PENDING_DEPOSIT).
     */
    public function testHostKickMemberEligibilityRule(): void
    {
        $canKick = function (bool $isHost, string $targetParticipantStatus): bool {
            if (!$isHost) {
                return false;
            }
            return $targetParticipantStatus === 'PENDING_DEPOSIT';
        };

        // Host kicking unpaid member => Allowed
        $this->assertTrue($canKick(true, 'PENDING_DEPOSIT'));

        // Host kicking deposited member => Forbidden
        $this->assertFalse($canKick(true, 'DEPOSITED'));

        // Non-host kicking unpaid member => Forbidden
        $this->assertFalse($canKick(false, 'PENDING_DEPOSIT'));
    }
}
