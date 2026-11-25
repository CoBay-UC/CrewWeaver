<?php

declare(strict_types=1);

namespace CrewWeaver\Controllers;

use CrewWeaver\Authentication\AuthenticationService;
use CrewWeaver\Core\Request;
use CrewWeaver\Core\Response;
use CrewWeaver\Models\NotificationRepository;

class NotificationApiController
{
    /* ============================================================
     * PROTECTED VARIABLES
     * ============================================================ */
    protected NotificationRepository $_notifications;
    protected AuthenticationService $_authentication;

    /* ============================================================
     * PUBLIC VARIABLES
     * ============================================================ */

    /* ============================================================
     * MAGIC METHODS
     * ============================================================ */
    public function __construct(NotificationRepository $notifications, AuthenticationService $authentication)
    {
        $this->_notifications=$notifications;$this->_authentication=$authentication;
    }

    /* ============================================================
     * PROTECTED METHODS
     * ============================================================ */

    /* ============================================================
     * PUBLIC METHODS
     * ============================================================ */
    public function index(Request $request): Response
    {
        $id=$this->_authentication->currentUser()?->id()??0;return Response::success('Notifications retrieved.',['items'=>$this->_notifications->forUser($id),'unread'=>$this->_notifications->unreadCount($id)]);
    }

    public function read(Request $request): Response
    {
        return $this->_notifications->markRead((int)$request->parameter('id'),$this->_authentication->currentUser()?->id()??0)?Response::success('Notification marked read.'):Response::error('Notification not found.',404);
    }

    public function readAll(Request $request): Response
    {
        $count=$this->_notifications->markAllRead($this->_authentication->currentUser()?->id()??0);return Response::success('Notifications marked read.',['updated'=>$count]);
    }
}
