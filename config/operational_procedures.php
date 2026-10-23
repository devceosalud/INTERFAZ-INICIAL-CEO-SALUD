<?php
return [
    // Add only clinically approved catalog entries: key => ['label'=>..., 'family'=>...].
    'approved' => [],
    // Explicit longitudinal monitoring; never a scheduling, billing or completion order.
    'followup_families' => ['mri', 'surgery'],
    'followup_keys' => [], // Individual approved options, without enabling an entire family.
];
