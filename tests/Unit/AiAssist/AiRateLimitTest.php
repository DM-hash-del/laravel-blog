<?php

use App\Models\User;
use App\Services\AiAssist\AiRateLimit;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-07-01 12:00:00', 'UTC'));
    $this->user = new User;
    $this->user->id = 7;
    $this->limit = new AiRateLimit;
});

test('there is no wait until the limit is hit', function () {
    expect($this->limit->secondsRemaining($this->user))->toBe(0)
        ->and($this->limit->secondsRemaining(null))->toBe(0);
});

test('the wait counts down with the clock and ends at zero', function () {
    $this->limit->blockFor($this->user, 40);

    expect($this->limit->secondsRemaining($this->user))->toBe(40);

    $this->travel(15)->seconds();
    expect($this->limit->secondsRemaining($this->user))->toBe(25);

    $this->travel(26)->seconds();
    expect($this->limit->secondsRemaining($this->user))->toBe(0);
});

test('each admin has their own wait', function () {
    $other = new User;
    $other->id = 8;

    $this->limit->blockFor($this->user, 30);

    expect($this->limit->secondsRemaining($other))->toBe(0);
});
