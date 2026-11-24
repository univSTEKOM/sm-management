<?php

return [
    // PRD 27.3 A-01 (asumsi): mentors can read all schools in Jawa Tengah.
    // Set to false to restrict mentors to their assigned schools.
    'mentor_can_view_all_schools' => env('ACCESS_MENTOR_VIEW_ALL_SCHOOLS', true),

    // TD-04 (diputuskan): idle timeout in minutes, enforced server-side.
    'idle_timeout_minutes' => (int) env('IDLE_TIMEOUT_MINUTES', 60),
];
