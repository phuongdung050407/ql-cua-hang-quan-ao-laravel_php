<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

#[Fillable(['name', 'email', 'username', 'role', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

	protected $table = 'users';

	protected $fillable = [
		'name',
        'email',
        'password',
        'phone',
        'gender',
        'address',
        'role',
	 ];

	 protected $hidden = [
		 'password',
		 'remember_token',
	 ];

	 protected function casts(): array
	 {
		 return [
			 'email_verified_at' => 'datetime',
			 'password' => 'hashed',
		 ];
	 }

	 public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function searchHistories(): HasMany
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function searchProductClicks(): HasMany
    {
        return $this->hasMany(SearchProductClick::class);
    }


}	
class CustomResetPasswordNotification extends ResetPassword
{
	public function toMail($notifiable)
	{
		return (new MailMessage)
		 ->subject('Khôi phục mật khẩu')
		 ->line('Bạn vừa yêu cầu ' . config('app.name') . ' khôi phục mật khẩu của mình.')
		 ->line('Liên kết đặt lại mật khẩu này sẽ hết hạn sau 60 phút.')
		 ->line('Xin vui lòng nhấn vào nút "Khôi phục mật khẩu" bên dưới để tiến hành cấp mật khẩu mới.')
		 ->action('Khôi phục mật khẩu', url(config('app.url') . route('password.reset', $this->token, false)))
		 ->line('Nếu bạn không yêu cầu đặt lại mật khẩu, xin vui lòng không làm gì thêm và báo lại cho quản trị hệ thống về vấn đề này.');
	}

}