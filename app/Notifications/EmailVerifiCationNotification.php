<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;
use Cache;

// ShouldQueue 这个接口本身没有定义任何方法，对于继承了 ShouldQueue 的邮件类 Laravel 会用将发邮件的操作放进队列里来实现异步发送；
class EmailVerifiCationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // 开启邮件通知
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     * 发送邮件时会调用此方法来构建邮件内容
     * 参数就是 App\Models\User
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // 使用 Laravel 内置的 Str 类生成随机字符串的函数，参数就是要生成的字符串长度 
        $token = Str::random(16); // str_random(16)
        //写入缓存，有效时间 30 分钟
        $key = 'email_verification_' . $notifiable->email;
        Cache::set($key, $token, 30);
        // 验证 url 
        $url = route('email_verification.verify', ['email' => $notifiable->email, 'token' => $token]);

        return (new MailMessage)
                    // 设置欢迎词
                    ->greeting($notifiable->name . '您好：')
                    // 定义邮件标题
                    ->subject('注册成功，请验证您的邮箱')
                    // 邮件内容添加一行文字
                    ->line('请点击下方链接验证您的邮箱')
                    // 邮件内容添加一个链接按钮
                    ->action('验证', $url);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
