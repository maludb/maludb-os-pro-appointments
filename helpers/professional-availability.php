<?php
/**
 * Professional Scheduling Availability Engine
 *
 * Server-side slot generation and slot validation for the
 * single-provider professional scheduling product.
 */

require_once __DIR__ . '/db.php';

/**
 * Load the professional profile configuration for a restaurant.
 * Falls back to restaurant timezone and schema defaults when a
 * professional profile row has not been created yet.
 */
function getProfessionalProfile($restaurantId) {
    static $cache = [];

    $restaurantId = (int)$restaurantId;
    if ($restaurantId <= 0) {
        return null;
    }

    if (isset($cache[$restaurantId])) {
        return $cache[$restaurantId];
    }

    $stmt = db()->prepare(
        "SELECT
            r.id AS restaurant_id,
            r.name AS restaurant_name,
            r.slug AS restaurant_slug,
            r.phone AS restaurant_phone,
            r.email AS restaurant_email,
            r.timezone AS restaurant_timezone,
            pp.id,
            pp.owner_user_id,
            pp.business_name,
            pp.display_name,
            pp.business_phone,
            pp.business_email,
            pp.timezone,
            pp.booking_slug,
            pp.slot_interval_minutes,
            pp.default_buffer_before_minutes,
            pp.default_buffer_after_minutes,
            pp.minimum_booking_notice_hours,
            pp.maximum_booking_horizon_days,
            pp.default_location_type,
            pp.default_location_label,
            pp.booking_instructions,
            pp.cancellation_policy,
            pp.cancellation_notice_hours,
            pp.is_public_booking_enabled
         FROM restaurants r
         LEFT JOIN professional_profiles pp ON pp.restaurant_id = r.id
         WHERE r.id = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $timezone = professionalResolveTimezone($row['timezone'] ?? '');
    if ($timezone === null) {
        $timezone = professionalResolveTimezone($row['restaurant_timezone'] ?? '') ?: new DateTimeZone('America/New_York');
    }

    $profile = [
        'id' => isset($row['id']) ? (int)$row['id'] : 0,
        'restaurant_id' => (int)$row['restaurant_id'],
        'owner_user_id' => isset($row['owner_user_id']) ? (int)$row['owner_user_id'] : 0,
        'business_name' => $row['business_name'] ?: ($row['restaurant_name'] ?? ''),
        'display_name' => $row['display_name'] ?: ($row['restaurant_name'] ?? ''),
        'business_phone' => $row['business_phone'] ?: ($row['restaurant_phone'] ?? null),
        'business_email' => $row['business_email'] ?: ($row['restaurant_email'] ?? null),
        'timezone' => $timezone->getName(),
        'booking_slug' => $row['booking_slug'] ?: ($row['restaurant_slug'] ?? ''),
        'slot_interval_minutes' => max(5, (int)($row['slot_interval_minutes'] ?? 30)),
        'default_buffer_before_minutes' => max(0, (int)($row['default_buffer_before_minutes'] ?? 0)),
        'default_buffer_after_minutes' => max(0, (int)($row['default_buffer_after_minutes'] ?? 0)),
        'minimum_booking_notice_hours' => max(0, (int)($row['minimum_booking_notice_hours'] ?? 2)),
        'maximum_booking_horizon_days' => max(0, (int)($row['maximum_booking_horizon_days'] ?? 90)),
        'default_location_type' => $row['default_location_type'] ?: 'in_person',
        'default_location_label' => $row['default_location_label'] ?? null,
        'booking_instructions' => $row['booking_instructions'] ?? null,
        'cancellation_policy' => $row['cancellation_policy'] ?? null,
        'cancellation_notice_hours' => max(0, (int)($row['cancellation_notice_hours'] ?? 24)),
        'is_public_booking_enabled' => isset($row['is_public_booking_enabled']) ? (int)$row['is_public_booking_enabled'] : 1,
    ];

    $cache[$restaurantId] = $profile;
    return $profile;
}

/**
 * Load a professional profile by its public booking slug.
 */
function getProfessionalProfileByBookingSlug($bookingSlug) {
    $bookingSlug = strtolower(trim((string)$bookingSlug));
    if ($bookingSlug === '') {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT r.id
         FROM restaurants r
         LEFT JOIN professional_profiles pp ON pp.restaurant_id = r.id
         WHERE r.is_active = 1
           AND (
               pp.booking_slug = ?
               OR (COALESCE(pp.booking_slug, '') = '' AND r.slug = ?)
           )
         ORDER BY CASE WHEN pp.booking_slug = ? THEN 0 ELSE 1 END, r.id ASC
         LIMIT 1"
    );
    $stmt->execute([$bookingSlug, $bookingSlug, $bookingSlug]);
    $restaurantId = (int)$stmt->fetchColumn();

    if ($restaurantId <= 0) {
        return null;
    }

    return getProfessionalProfile($restaurantId);
}

/**
 * Load one professional service for a restaurant.
 *
 * Options:
 * - public_booking => bool
 * - allow_inactive => bool
 */
function getProfessionalService($restaurantId, $serviceId, array $options = []) {
    $restaurantId = (int)$restaurantId;
    $serviceId = (int)$serviceId;

    if ($restaurantId <= 0 || $serviceId <= 0) {
        return null;
    }

    $publicBooking = !empty($options['public_booking']);
    $allowInactive = !empty($options['allow_inactive']);

    $query = "SELECT * FROM professional_services WHERE restaurant_id = ? AND id = ?";
    $params = [$restaurantId, $serviceId];

    if (!$allowInactive) {
        $query .= " AND is_active = 1";
    }

    if ($publicBooking) {
        $query .= " AND is_public_bookable = 1";
    }

    $query .= " LIMIT 1";

    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $profile = getProfessionalProfile($restaurantId);
    if (!$profile) {
        return null;
    }

    $serviceBufferBefore = max(0, (int)$row['buffer_before_minutes']);
    $serviceBufferAfter = max(0, (int)$row['buffer_after_minutes']);

    $effectiveBufferBefore = $serviceBufferBefore > 0
        ? $serviceBufferBefore
        : (int)$profile['default_buffer_before_minutes'];
    $effectiveBufferAfter = $serviceBufferAfter > 0
        ? $serviceBufferAfter
        : (int)$profile['default_buffer_after_minutes'];

    return [
        'id' => (int)$row['id'],
        'restaurant_id' => (int)$row['restaurant_id'],
        'name' => $row['name'],
        'description' => $row['description'] ?? null,
        'duration_minutes' => max(1, (int)$row['duration_minutes']),
        'buffer_before_minutes' => $serviceBufferBefore,
        'buffer_after_minutes' => $serviceBufferAfter,
        'effective_buffer_before_minutes' => $effectiveBufferBefore,
        'effective_buffer_after_minutes' => $effectiveBufferAfter,
        'price' => $row['price'],
        'currency_code' => $row['currency_code'] ?: 'USD',
        'location_type' => $row['location_type'] ?: null,
        'location_label' => $row['location_label'] ?: null,
        'color' => $row['color'] ?: null,
        'sort_order' => (int)$row['sort_order'],
        'is_active' => (int)$row['is_active'],
        'is_public_bookable' => (int)$row['is_public_bookable'],
    ];
}

/**
 * Load recurring availability windows for a local business date.
 */
function getProfessionalAvailabilityWindowsForDate($restaurantId, $date) {
    $profile = getProfessionalProfile($restaurantId);
    if (!$profile) {
        return [];
    }

    $timezone = new DateTimeZone($profile['timezone']);
    $dateObject = professionalNormalizeDate($date, $timezone);
    if ($dateObject === null) {
        return [];
    }

    $weekday = (int)$dateObject->format('w');

    $stmt = db()->prepare(
        "SELECT id, weekday, start_time, end_time, location_type, location_label
         FROM professional_availability_rules
         WHERE restaurant_id = ? AND weekday = ? AND is_active = 1
         ORDER BY start_time ASC, end_time ASC"
    );
    $stmt->execute([(int)$restaurantId, $weekday]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $windows = [];

    foreach ($rows as $row) {
        $windowStart = professionalCreateDateTimeFromDateAndTime($dateObject->format('Y-m-d'), $row['start_time'], $timezone);
        $windowEnd = professionalCreateDateTimeFromDateAndTime($dateObject->format('Y-m-d'), $row['end_time'], $timezone);

        if ($windowStart === null || $windowEnd === null || $windowStart >= $windowEnd) {
            continue;
        }

        $windows[] = [
            'id' => (int)$row['id'],
            'weekday' => (int)$row['weekday'],
            'date' => $dateObject->format('Y-m-d'),
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'location_type' => $row['location_type'] ?: null,
            'location_label' => $row['location_label'] ?: null,
        ];
    }

    return $windows;
}

/**
 * Load blocked time ranges that overlap a date/time window.
 */
function getProfessionalTimeOffBlocks($restaurantId, $rangeStart, $rangeEnd) {
    $profile = getProfessionalProfile($restaurantId);
    if (!$profile) {
        return [];
    }

    $timezone = new DateTimeZone($profile['timezone']);
    $rangeStartObject = professionalNormalizeDateTime($rangeStart, $timezone);
    $rangeEndObject = professionalNormalizeDateTime($rangeEnd, $timezone);

    if ($rangeStartObject === null || $rangeEndObject === null || $rangeStartObject >= $rangeEndObject) {
        return [];
    }

    $stmt = db()->prepare(
        "SELECT id, starts_at, ends_at, reason, notes, is_all_day
         FROM professional_time_off
         WHERE restaurant_id = ?
           AND starts_at < ?
           AND ends_at > ?
         ORDER BY starts_at ASC, ends_at ASC"
    );
    $stmt->execute([
        (int)$restaurantId,
        $rangeEndObject->format('Y-m-d H:i:s'),
        $rangeStartObject->format('Y-m-d H:i:s'),
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $blocks = [];

    foreach ($rows as $row) {
        $startsAt = professionalNormalizeDateTime($row['starts_at'], $timezone);
        $endsAt = professionalNormalizeDateTime($row['ends_at'], $timezone);

        if ($startsAt === null || $endsAt === null || $startsAt >= $endsAt) {
            continue;
        }

        $blocks[] = [
            'id' => (int)$row['id'],
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'start_at_dt' => $startsAt,
            'end_at_dt' => $endsAt,
            'reason' => $row['reason'] ?: null,
            'notes' => $row['notes'] ?: null,
            'is_all_day' => (int)$row['is_all_day'],
        ];
    }

    return $blocks;
}

/**
 * Load appointment occupied ranges that overlap a date/time window.
 *
 * Options:
 * - exclude_appointment_id => int
 */
function getProfessionalAppointmentBlocks($restaurantId, $rangeStart, $rangeEnd, array $options = []) {
    $profile = getProfessionalProfile($restaurantId);
    if (!$profile) {
        return [];
    }

    $timezone = new DateTimeZone($profile['timezone']);
    $rangeStartObject = professionalNormalizeDateTime($rangeStart, $timezone);
    $rangeEndObject = professionalNormalizeDateTime($rangeEnd, $timezone);

    if ($rangeStartObject === null || $rangeEndObject === null || $rangeStartObject >= $rangeEndObject) {
        return [];
    }

    $excludeAppointmentId = (int)($options['exclude_appointment_id'] ?? 0);

    $query = "
        SELECT
            id,
            professional_user_id,
            client_id,
            service_id,
            status,
            source,
            appointment_date,
            start_at,
            end_at,
            service_name,
            duration_minutes,
            buffer_before_minutes,
            buffer_after_minutes,
            price,
            currency_code,
            location_type,
            location_label,
            confirmation_code,
            (start_at - buffer_before_minutes * INTERVAL '1 minute') AS occupied_start_at,
            (end_at + buffer_after_minutes * INTERVAL '1 minute') AS occupied_end_at
        FROM professional_appointments
        WHERE restaurant_id = ?
          AND status NOT IN ('cancelled', 'no_show')
          AND (start_at - buffer_before_minutes * INTERVAL '1 minute') < ?
          AND (end_at + buffer_after_minutes * INTERVAL '1 minute') > ?
    ";
    $params = [
        (int)$restaurantId,
        $rangeEndObject->format('Y-m-d H:i:s'),
        $rangeStartObject->format('Y-m-d H:i:s'),
    ];

    if ($excludeAppointmentId > 0) {
        $query .= " AND id != ?";
        $params[] = $excludeAppointmentId;
    }

    $query .= " ORDER BY start_at ASC, end_at ASC";

    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $blocks = [];

    foreach ($rows as $row) {
        $startAt = professionalNormalizeDateTime($row['start_at'], $timezone);
        $endAt = professionalNormalizeDateTime($row['end_at'], $timezone);
        $occupiedStart = professionalNormalizeDateTime($row['occupied_start_at'], $timezone);
        $occupiedEnd = professionalNormalizeDateTime($row['occupied_end_at'], $timezone);

        if ($startAt === null || $endAt === null || $occupiedStart === null || $occupiedEnd === null) {
            continue;
        }

        $blocks[] = [
            'id' => (int)$row['id'],
            'professional_user_id' => (int)$row['professional_user_id'],
            'client_id' => (int)$row['client_id'],
            'service_id' => $row['service_id'] !== null ? (int)$row['service_id'] : null,
            'status' => $row['status'],
            'source' => $row['source'],
            'appointment_date' => $row['appointment_date'],
            'start_at' => $startAt->format('Y-m-d H:i:s'),
            'end_at' => $endAt->format('Y-m-d H:i:s'),
            'start_at_dt' => $startAt,
            'end_at_dt' => $endAt,
            'occupied_start_at' => $occupiedStart->format('Y-m-d H:i:s'),
            'occupied_end_at' => $occupiedEnd->format('Y-m-d H:i:s'),
            'occupied_start_at_dt' => $occupiedStart,
            'occupied_end_at_dt' => $occupiedEnd,
            'service_name' => $row['service_name'],
            'duration_minutes' => (int)$row['duration_minutes'],
            'buffer_before_minutes' => (int)$row['buffer_before_minutes'],
            'buffer_after_minutes' => (int)$row['buffer_after_minutes'],
            'price' => $row['price'],
            'currency_code' => $row['currency_code'],
            'location_type' => $row['location_type'] ?: null,
            'location_label' => $row['location_label'] ?: null,
            'confirmation_code' => $row['confirmation_code'] ?: null,
        ];
    }

    return $blocks;
}

/**
 * Generate professional slots for one service on one date.
 *
 * Options:
 * - include_unavailable => bool
 * - public_booking => bool
 * - allow_inactive => bool
 * - ignore_notice => bool
 * - ignore_horizon => bool
 * - exclude_appointment_id => int
 * - now => string|DateTimeInterface
 */
function getProfessionalAvailableSlots($restaurantId, $serviceId, $date, array $options = []) {
    $profile = getProfessionalProfile($restaurantId);
    if (!$profile) {
        return [];
    }

    if (!empty($options['public_booking']) && (int)$profile['is_public_booking_enabled'] !== 1) {
        return [];
    }

    $service = getProfessionalService($restaurantId, $serviceId, $options);
    if (!$service) {
        return [];
    }

    $timezone = new DateTimeZone($profile['timezone']);
    $dateObject = professionalNormalizeDate($date, $timezone);
    if ($dateObject === null) {
        return [];
    }

    $windows = getProfessionalAvailabilityWindowsForDate($restaurantId, $dateObject->format('Y-m-d'));
    if (empty($windows)) {
        return [];
    }

    $earliestWindowStart = null;
    $latestWindowEnd = null;

    foreach ($windows as $window) {
        if ($earliestWindowStart === null || $window['window_start'] < $earliestWindowStart) {
            $earliestWindowStart = $window['window_start'];
        }
        if ($latestWindowEnd === null || $window['window_end'] > $latestWindowEnd) {
            $latestWindowEnd = $window['window_end'];
        }
    }

    if ($earliestWindowStart === null || $latestWindowEnd === null) {
        return [];
    }

    $timeOffBlocks = getProfessionalTimeOffBlocks($restaurantId, $earliestWindowStart, $latestWindowEnd);
    $appointmentBlocks = getProfessionalAppointmentBlocks($restaurantId, $earliestWindowStart, $latestWindowEnd, [
        'exclude_appointment_id' => (int)($options['exclude_appointment_id'] ?? 0),
    ]);

    $includeUnavailable = !empty($options['include_unavailable']);
    $slotIntervalMinutes = max(5, (int)$profile['slot_interval_minutes']);
    $durationMinutes = (int)$service['duration_minutes'];
    $bufferBeforeMinutes = (int)$service['effective_buffer_before_minutes'];
    $bufferAfterMinutes = (int)$service['effective_buffer_after_minutes'];
    $now = professionalResolveCurrentTime($profile, $options);

    $slots = [];
    $seenStartTimes = [];

    foreach ($windows as $window) {
        $firstCandidateStart = $window['window_start']->modify('+' . $bufferBeforeMinutes . ' minutes');
        $lastCandidateStart = $window['window_end']
            ->modify('-' . $durationMinutes . ' minutes')
            ->modify('-' . $bufferAfterMinutes . ' minutes');

        if ($firstCandidateStart > $lastCandidateStart) {
            continue;
        }

        for ($candidateStart = $firstCandidateStart; $candidateStart <= $lastCandidateStart; $candidateStart = $candidateStart->modify('+' . $slotIntervalMinutes . ' minutes')) {
            $slotKey = $candidateStart->format('Y-m-d H:i:s');
            if (isset($seenStartTimes[$slotKey])) {
                continue;
            }

            $seenStartTimes[$slotKey] = true;

            $slot = professionalEvaluateSlot(
                $candidateStart,
                $window,
                $service,
                $profile,
                $timeOffBlocks,
                $appointmentBlocks,
                $now,
                $options
            );

            if ($slot['is_available'] || $includeUnavailable) {
                $slots[] = $slot;
            }
        }
    }

    usort($slots, function ($a, $b) {
        return strcmp($a['start_at'], $b['start_at']);
    });

    return $slots;
}

/**
 * Validate one selected professional slot.
 *
 * Options:
 * - public_booking => bool
 * - allow_inactive => bool
 * - ignore_notice => bool
 * - ignore_horizon => bool
 * - exclude_appointment_id => int
 * - now => string|DateTimeInterface
 */
function validateProfessionalSlot($restaurantId, $serviceId, $startAt, array $options = []) {
    $profile = getProfessionalProfile($restaurantId);
    if (!$profile) {
        return [
            'is_available' => false,
            'reason' => 'profile_not_found',
            'message' => professionalGetSlotReasonMessage('profile_not_found'),
            'slot' => null,
            'service' => null,
            'profile' => null,
        ];
    }

    if (!empty($options['public_booking']) && (int)$profile['is_public_booking_enabled'] !== 1) {
        return [
            'is_available' => false,
            'reason' => 'public_booking_disabled',
            'message' => professionalGetSlotReasonMessage('public_booking_disabled'),
            'slot' => null,
            'service' => null,
            'profile' => $profile,
        ];
    }

    $service = getProfessionalService($restaurantId, $serviceId, $options);
    if (!$service) {
        return [
            'is_available' => false,
            'reason' => 'service_not_found',
            'message' => professionalGetSlotReasonMessage('service_not_found'),
            'slot' => null,
            'service' => null,
            'profile' => $profile,
        ];
    }

    $timezone = new DateTimeZone($profile['timezone']);
    $startAtObject = professionalNormalizeDateTime($startAt, $timezone);
    if ($startAtObject === null) {
        return [
            'is_available' => false,
            'reason' => 'invalid_datetime',
            'message' => professionalGetSlotReasonMessage('invalid_datetime'),
            'slot' => null,
            'service' => $service,
            'profile' => $profile,
        ];
    }

    $slots = getProfessionalAvailableSlots($restaurantId, $serviceId, $startAtObject->format('Y-m-d'), [
        'include_unavailable' => true,
        'public_booking' => !empty($options['public_booking']),
        'allow_inactive' => !empty($options['allow_inactive']),
        'ignore_notice' => !empty($options['ignore_notice']),
        'ignore_horizon' => !empty($options['ignore_horizon']),
        'exclude_appointment_id' => (int)($options['exclude_appointment_id'] ?? 0),
        'now' => $options['now'] ?? null,
    ]);

    foreach ($slots as $slot) {
        if ($slot['start_at'] === $startAtObject->format('Y-m-d H:i:s')) {
            return [
                'is_available' => (bool)$slot['is_available'],
                'reason' => $slot['unavailable_reason'],
                'message' => $slot['availability_message'],
                'slot' => $slot,
                'service' => $service,
                'profile' => $profile,
            ];
        }
    }

    return [
        'is_available' => false,
        'reason' => 'outside_availability',
        'message' => professionalGetSlotReasonMessage('outside_availability'),
        'slot' => null,
        'service' => $service,
        'profile' => $profile,
    ];
}

/**
 * Convert an unavailable reason into a reusable human message.
 */
function professionalGetSlotReasonMessage($reason) {
    switch ($reason) {
        case null:
            return 'This slot is available.';
        case 'profile_not_found':
            return 'Professional scheduling settings are not configured yet.';
        case 'service_not_found':
            return 'The selected service is not available.';
        case 'invalid_datetime':
            return 'The selected appointment time is invalid.';
        case 'public_booking_disabled':
            return 'Public booking is not enabled for this professional profile.';
        case 'booking_notice':
            return 'This time is inside the minimum booking notice window.';
        case 'booking_horizon':
            return 'This time is beyond the current booking horizon.';
        case 'outside_availability':
            return 'This time is outside the provider availability window.';
        case 'time_off':
            return 'This time overlaps blocked time or time off.';
        case 'appointment_conflict':
            return 'This time overlaps another appointment.';
        default:
            return 'This time is not available.';
    }
}

/**
 * Evaluate one candidate slot against the full professional scheduling rules.
 */
function professionalEvaluateSlot(
    DateTimeImmutable $candidateStart,
    array $window,
    array $service,
    array $profile,
    array $timeOffBlocks,
    array $appointmentBlocks,
    DateTimeImmutable $now,
    array $options = []
) {
    $durationMinutes = (int)$service['duration_minutes'];
    $bufferBeforeMinutes = (int)$service['effective_buffer_before_minutes'];
    $bufferAfterMinutes = (int)$service['effective_buffer_after_minutes'];

    $startAt = $candidateStart;
    $endAt = $startAt->modify('+' . $durationMinutes . ' minutes');
    $occupiedStartAt = $startAt->modify('-' . $bufferBeforeMinutes . ' minutes');
    $occupiedEndAt = $endAt->modify('+' . $bufferAfterMinutes . ' minutes');

    $reason = null;
    $blockedBy = null;
    $blockedRecordId = null;

    if ($occupiedStartAt < $window['window_start'] || $occupiedEndAt > $window['window_end']) {
        $reason = 'outside_availability';
    }

    if ($reason === null && empty($options['ignore_notice'])) {
        $minimumBookableTime = $now->modify('+' . (int)$profile['minimum_booking_notice_hours'] . ' hours');
        if ($startAt < $minimumBookableTime) {
            $reason = 'booking_notice';
        }
    }

    if ($reason === null && empty($options['ignore_horizon'])) {
        $latestBookableStart = $now
            ->setTime(0, 0, 0)
            ->modify('+' . (int)$profile['maximum_booking_horizon_days'] . ' days')
            ->setTime(23, 59, 59);

        if ($startAt > $latestBookableStart) {
            $reason = 'booking_horizon';
        }
    }

    if ($reason === null) {
        foreach ($timeOffBlocks as $block) {
            if (professionalRangesOverlap($occupiedStartAt, $occupiedEndAt, $block['start_at_dt'], $block['end_at_dt'])) {
                $reason = 'time_off';
                $blockedBy = 'time_off';
                $blockedRecordId = (int)$block['id'];
                break;
            }
        }
    }

    if ($reason === null) {
        foreach ($appointmentBlocks as $block) {
            if (professionalRangesOverlap($occupiedStartAt, $occupiedEndAt, $block['occupied_start_at_dt'], $block['occupied_end_at_dt'])) {
                $reason = 'appointment_conflict';
                $blockedBy = 'appointment';
                $blockedRecordId = (int)$block['id'];
                break;
            }
        }
    }

    $locationType = $window['location_type']
        ?: ($service['location_type'] ?: $profile['default_location_type']);
    $locationLabel = $window['location_label']
        ?: ($service['location_label'] ?: $profile['default_location_label']);

    return [
        'service_id' => (int)$service['id'],
        'service_name' => $service['name'],
        'date' => $startAt->format('Y-m-d'),
        'time' => $startAt->format('H:i:s'),
        'time_display' => $startAt->format('g:ia'),
        'start_at' => $startAt->format('Y-m-d H:i:s'),
        'end_at' => $endAt->format('Y-m-d H:i:s'),
        'occupied_start_at' => $occupiedStartAt->format('Y-m-d H:i:s'),
        'occupied_end_at' => $occupiedEndAt->format('Y-m-d H:i:s'),
        'duration_minutes' => $durationMinutes,
        'buffer_before_minutes' => $bufferBeforeMinutes,
        'buffer_after_minutes' => $bufferAfterMinutes,
        'slot_interval_minutes' => (int)$profile['slot_interval_minutes'],
        'window_id' => (int)$window['id'],
        'window_start' => $window['window_start']->format('Y-m-d H:i:s'),
        'window_end' => $window['window_end']->format('Y-m-d H:i:s'),
        'location_type' => $locationType,
        'location_label' => $locationLabel,
        'is_available' => ($reason === null),
        'unavailable_reason' => $reason,
        'availability_message' => professionalGetSlotReasonMessage($reason),
        'blocked_by' => $blockedBy,
        'blocked_record_id' => $blockedRecordId,
    ];
}

/**
 * Resolve the "now" reference time used for notice and horizon checks.
 */
function professionalResolveCurrentTime(array $profile, array $options = []) {
    $timezone = new DateTimeZone($profile['timezone']);

    if (array_key_exists('now', $options) && $options['now']) {
        $resolved = professionalNormalizeDateTime($options['now'], $timezone);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    return new DateTimeImmutable('now', $timezone);
}

/**
 * Check whether two date/time ranges overlap.
 */
function professionalRangesOverlap(DateTimeImmutable $startA, DateTimeImmutable $endA, DateTimeImmutable $startB, DateTimeImmutable $endB) {
    return $startA < $endB && $endA > $startB;
}

/**
 * Normalize a business-local date.
 */
function professionalNormalizeDate($date, DateTimeZone $timezone) {
    if ($date instanceof DateTimeInterface) {
        return (new DateTimeImmutable($date->format('Y-m-d'), $timezone))->setTime(0, 0, 0);
    }

    $date = trim((string)$date);
    if ($date === '') {
        return null;
    }

    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
    if ($dateObject === false) {
        return null;
    }

    return $dateObject->setTime(0, 0, 0);
}

/**
 * Normalize a business-local date/time.
 */
function professionalNormalizeDateTime($value, DateTimeZone $timezone) {
    if ($value instanceof DateTimeInterface) {
        return new DateTimeImmutable($value->format('Y-m-d H:i:s'), $timezone);
    }

    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
    ];

    foreach ($formats as $format) {
        $dateTime = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        if ($dateTime !== false) {
            return $dateTime;
        }
    }

    try {
        return new DateTimeImmutable($value, $timezone);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Build a local date/time from separate date and time strings.
 */
function professionalCreateDateTimeFromDateAndTime($date, $time, DateTimeZone $timezone) {
    $time = trim((string)$time);
    if ($time === '') {
        return null;
    }

    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time .= ':00';
    }

    return professionalNormalizeDateTime(trim((string)$date) . ' ' . $time, $timezone);
}

/**
 * Resolve a timezone string safely.
 */
function professionalResolveTimezone($timezoneName) {
    $timezoneName = trim((string)$timezoneName);
    if ($timezoneName === '') {
        return null;
    }

    try {
        return new DateTimeZone($timezoneName);
    } catch (Exception $e) {
        return null;
    }
}
