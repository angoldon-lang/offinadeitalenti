<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\{Auth, Request, Response, View};
use App\Repository\NotificationRepository;

final class HomeController
{
    public function index(Request $r): void
    {
        $user = Auth::user();
        if ($user) {
            Response::redirect(Auth::homeFor((string) $user['platform_role']));
        }
        echo View::page('home', ['title' => 'Competenze tecniche, senza intermediari lenti'], 'layout_public');
    }

    public function notifications(Request $r): void
    {
        $userId = (string) Auth::id();
        $items  = NotificationRepository::forUser($userId);
        NotificationRepository::markAllRead($userId);

        echo View::page('notifications', ['title' => 'Notifiche', 'items' => $items]);
    }
}
