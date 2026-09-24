<?php
namespace App\Modules\IdentityAccess\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class User extends Authenticatable
{
    use HasUuids;
    protected $guarded = ['id', 'role', 'security_version', 'is_active', 'totp_secret'];
    protected $hidden = ['password', 'remember_token', 'totp_secret', 'last_totp_step'];
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'security_version' => 'integer',
            'last_totp_step' => 'integer',
        ];
    }
    public function canWrite(): bool
    {
        return in_array($this->role, ['ADMIN', 'SUPER_ADMIN'], true);
    }
}
