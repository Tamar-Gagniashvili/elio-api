<?php

namespace App\Http\Controllers;

use App\Actions\SyncPaymentStatusAction;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Where the gateway sends the client after paying (BOG redirects with GET, Flitt posts the form).
 * Public: the client is not logged in. The payment id is an unguessable UUID, and the page shows
 * only the outcome, the amount and the clinic's name.
 */
class PaymentReturnController extends Controller
{
    private const TEXT = [
        'en' => [
            'paid' => 'Payment received',
            'paid_text' => 'Thank you! Your payment has been received. You can close this page.',
            'failed' => "Payment didn't go through",
            'failed_text' => 'No money was taken. Please try the link again or contact the clinic.',
            'pending' => 'Confirming your payment…',
            'pending_text' => 'This can take a few seconds. The page will update by itself.',
            'amount' => 'Amount',
        ],
        'ka' => [
            'paid' => 'გადახდა მიღებულია',
            'paid_text' => 'გმადლობთ! თქვენი გადახდა მიღებულია. შეგიძლიათ დახუროთ ეს გვერდი.',
            'failed' => 'გადახდა ვერ შესრულდა',
            'failed_text' => 'თანხა არ ჩამოგეჭრათ. სცადეთ ბმული თავიდან ან დაუკავშირდით კლინიკას.',
            'pending' => 'გადახდა მოწმდება…',
            'pending_text' => 'ამას რამდენიმე წამი სჭირდება. გვერდი თავად განახლდება.',
            'amount' => 'თანხა',
        ],
        'ru' => [
            'paid' => 'Оплата получена',
            'paid_text' => 'Спасибо! Ваша оплата получена. Эту страницу можно закрыть.',
            'failed' => 'Оплата не прошла',
            'failed_text' => 'Деньги не списаны. Попробуйте ссылку ещё раз или свяжитесь с клиникой.',
            'pending' => 'Подтверждаем оплату…',
            'pending_text' => 'Это займёт несколько секунд. Страница обновится сама.',
            'amount' => 'Сумма',
        ],
    ];

    public function __invoke(Request $request, string $payment, SyncPaymentStatusAction $syncStatus): View
    {
        $payment = Payment::findOrFail($payment);
        $payment->tenant?->makeCurrent();

        // The callback may not have arrived yet, so ask the gateway
        if (in_array($payment->status, [PaymentStatus::Created, PaymentStatus::Processing], true)) {
            try {
                $syncStatus->execute($payment);
                $payment->refresh();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $state = match ($payment->status) {
            PaymentStatus::Completed, PaymentStatus::RefundRequested, PaymentStatus::Refunded, PaymentStatus::RefundedPartially => 'paid',
            PaymentStatus::Rejected, PaymentStatus::Expired => 'failed',
            default => 'pending',
        };

        $locale = $request->getPreferredLanguage(array_keys(self::TEXT)) ?? 'en';

        return view('payments.result', [
            'state' => $state,
            'text' => self::TEXT[$locale],
            'locale' => $locale,
            'amount' => number_format((float) $payment->amount, 2),
            'currency' => $payment->ccy ?: 'GEL',
            'clinic' => $payment->tenant?->name,
        ]);
    }
}
