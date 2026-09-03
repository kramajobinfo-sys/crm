<?php
namespace App\Events;
use Illuminate\Foundation\Events\Dispatchable;
class UserLoginFailed
{
    use Dispatchable;
    public function __construct(public string $email, public string $ipAddress, public string $reason, public ?string $userAgent = null) {}
}
