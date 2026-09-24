<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired', ['--hours' => (int) config('access_tokens.prune_hours')])->hourly();
