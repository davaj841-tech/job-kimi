<?php

namespace App\Http\Controllers\Api;

use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Services\MailConfigService;
use App\Support\OperatorPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends BaseController
{
    public function __construct(
        protected MailConfigService $mail
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return $this->successResponse([
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'in:support,pre_sale,bug,suggestion'],
            'priority' => ['nullable', 'in:low,medium,high'],
        ]);

        $ticket = Ticket::query()->create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'category' => $data['category'] ?? 'support',
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        $admins = User::query()->whereIn('role', ['admin', 'operator'])->get();
        foreach ($admins as $admin) {
            $admin->notify(new GenericDatabaseNotification(
                'new_ticket',
                'تیکت جدید',
                'تیکت «'.$ticket->subject.'» ثبت شد.',
                '/admin/tickets'
            ));
        }

        return $this->successResponse($ticket, 'تیکت ثبت شد.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()
            ->with(['replies.user:id,name,role', 'user:id,name,mobile'])
            ->findOrFail($id);

        if ($ticket->user_id !== $request->user()->id && ! OperatorPermissions::allows($request->user(), 'tickets')) {
            return $this->errorResponse('تیکت یافت نشد.', 404);
        }

        return $this->successResponse($ticket);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);
        $user = $request->user();
        $canStaff = OperatorPermissions::allows($user, 'tickets');

        if ($ticket->user_id !== $user->id && ! $canStaff) {
            return $this->errorResponse('تیکت یافت نشد.', 404);
        }

        $isAdmin = $ticket->user_id !== $user->id && $canStaff;

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $reply = TicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $data['message'],
            'is_admin' => $isAdmin,
        ]);

        if ($isAdmin) {
            $ticketUser = $ticket->user;
            if ($ticketUser instanceof User) {
                $ticketUser->notify(new GenericDatabaseNotification(
                    'admin_reply',
                    'پاسخ پشتیبانی',
                    'پاسخ جدید برای تیکت «'.$ticket->subject.'»',
                    '/support/'.$ticket->id
                ));
                if ($ticketUser->email) {
                    try {
                        $this->mail->queueTo($ticketUser->email, new TicketReplyMail(
                            ticketSubject: (string) $ticket->subject,
                            replyMessage: (string) $data['message'],
                            ticketUrl: rtrim(config('app.url'), '/').'/support/'.$ticket->id,
                            name: $ticketUser->name,
                        ));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        return $this->successResponse($reply->load('user:id,name,role'), 'پاسخ ثبت شد.', 201);
    }
}
