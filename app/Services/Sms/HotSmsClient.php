<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The provider's HTTP API v1 — `sendbulksms.php` / `getbalance.php`.
 *
 * Older and plainer than the REST v2 client next door, and preferred for OTP
 * for one reason: **it needs no token.** REST v2 issues its token through a
 * two-step login that texts a PIN to the account owner, so a human has to
 * provision it and re-provision it whenever it is revoked. This one
 * authenticates with the same username and password on every call, which means
 * SMS keeps working across a redeploy, a rotated cache, or a 3am restart with
 * nobody awake to read a PIN.
 *
 * Two details are not optional:
 *
 *   POST, never GET. The documentation offers both, and GET puts the account
 *   password in a URL — which lands in the provider's access logs, in any
 *   proxy between here and there, and in ours.
 *
 *   `type` must match the text. Arabic sent as type 0 arrives as noise,
 *   because type 0 is the GSM 03.38 alphabet and Arabic is not in it.
 *
 * Responses are bare text, not JSON: `1001`, or `1001_<messageId>` when msg_id
 * was requested, or a bare error number.
 */
class HotSmsClient
{
    private const SEND_PATH    = '/sendbulksms.php';
    private const BALANCE_PATH = '/getbalance.php';

    /** GSM 03.38, 160 chars. Latin only — Arabic here arrives mangled. */
    private const TYPE_GSM = 0;

    /** UTF-8 multilingual, 70 chars. What Arabic has to travel as. */
    private const TYPE_UTF8 = 2;

    /**
     * Bare numbers on the wire, so the meaning has to be restored here.
     *
     * The last two are the ones an operator actually meets, and both are fixed
     * by the account holder in the portal rather than by us.
     */
    private const ERRORS = [
        1000  => 'لا يوجد رصيد كافٍ لإرسال الرسالة',
        2000  => 'خطأ في التفويض — اسم المستخدم أو كلمة المرور غير صحيحة',
        3000  => 'نوع الرسالة غير صحيح',
        4000  => 'أحد المدخلات المطلوبة غير موجود',
        5000  => 'رقم المحمول غير مدعوم',
        6000  => 'اسم المرسل غير معرّف على الحساب — راجع الأسماء المعتمدة في البورتال',
        10000 => 'هذا العنوان (IP) غير مفوّض للإرسال من هذا الحساب — أضفه للقائمة المسموحة، أو اترك القائمة فارغة',
        15000 => 'خاصية الإرسال عبر API غير مفعّلة — فعّلها من أدوات الأمان في البورتال',
    ];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public function configured(): bool
    {
        return $this->baseUrl() !== ''
            && filled($this->config['username'] ?? null)
            && filled($this->config['password'] ?? null)
            && filled($this->config['sender'] ?? null);
    }

    /**
     * @return string the provider's message id when it gave one
     *
     * @throws RuntimeException
     */
    public function send(string $destination, string $message): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('إعدادات SMS غير مكتملة — راجع SMS_API_URL و SMS_API_USERNAME و SMS_API_PASSWORD و SMS_SENDER');
        }

        $body = $this->post(self::SEND_PATH, [
            'sender' => (string) $this->config['sender'],
            'mobile' => $destination,
            'type'   => $this->typeFor($message),
            'text'   => $message,
            // Asking for the id costs nothing and is the only handle we would
            // have if the provider later has to trace a message.
            'msg_id' => 'YES',
        ]);

        // "1001" alone, or "1001_5e9257a242406" when an id came back.
        [$code, $reference] = array_pad(explode('_', $body, 2), 2, null);

        if ((int) $code !== 1001) {
            throw new RuntimeException($this->explain($body));
        }

        return $reference ?? 'sent';
    }

    /** Remaining credit, or null when the provider would not say. */
    public function credits(): ?float
    {
        if (! $this->baseUrl() || blank($this->config['username'] ?? null)) {
            return null;
        }

        try {
            $body = $this->post(self::BALANCE_PATH, []);
        } catch (RuntimeException) {
            return null;
        }

        /*
         * A balance and an error are both a bare number here, and the ranges
         * overlap — an account holding exactly 1000 credits answers "1000",
         * which is also the code for "no credit". So the error table cannot
         * be applied to this endpoint without reading a healthy balance as a
         * failure.
         *
         * Measured instead: this endpoint answers "-1" when it refuses,
         * whatever the reason. Non-negative is a balance.
         */
        return is_numeric($body) && (float) $body >= 0 ? (float) $body : null;
    }

    /**
     * Arabic must not travel as GSM 03.38.
     *
     * Detected rather than configured: the OTP body is Arabic and the shop name
     * inside it is too, so a setting would only be a way to get this wrong.
     */
    private function typeFor(string $message): int
    {
        return preg_match('/^[\x20-\x7E\r\n]*$/', $message) === 1
            ? self::TYPE_GSM
            : self::TYPE_UTF8;
    }

    /** @param array<string, scalar> $payload */
    private function post(string $path, array $payload): string
    {
        $response = Http::asForm()
            ->timeout($this->timeout())
            ->post($this->baseUrl() . $path, $payload + [
                'user_name' => (string) $this->config['username'],
                'user_pass' => (string) $this->config['password'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('تعذّر الاتصال بمزوّد الرسائل (HTTP ' . $response->status() . ')');
        }

        return trim($response->body());
    }

    private function explain(string $body): string
    {
        $known = self::ERRORS[(int) $body] ?? null;

        return $known
            ? "رفض مزوّد الرسائل الإرسال: {$known} (كود {$body})"
            : "رفض مزوّد الرسائل الإرسال برد غير معروف: «{$body}»";
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 15);
    }
}
