<?php

namespace Tests\Unit;

use App\Models\Log;
use App\Models\User;
use App\Policies\LogPolicy;
use Tests\TestCase;

class LogPolicyTest extends TestCase
{
    public function test_los_logs_no_pueden_crearse_modificarse_ni_eliminarse_desde_la_aplicacion(): void
    {
        $policy = new LogPolicy();
        $user = new User();
        $log = new Log();

        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $log));
        $this->assertFalse($policy->delete($user, $log));
        $this->assertFalse($policy->deleteAny($user));
        $this->assertFalse($policy->forceDelete($user, $log));
        $this->assertFalse($policy->forceDeleteAny($user));
    }
}
