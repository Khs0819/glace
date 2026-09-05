<?php

namespace App\Services\JawwalPay;

/**
 * Service Bus error codes (merchant guide §4) with the Arabic wording we show.
 *
 * Two audiences, deliberately kept apart:
 *  - label()   — the vendor's own English name, for the admin dashboard and logs.
 *  - message() — Arabic for whoever is reading.
 *
 * Codes that describe *our* merchant account, the device registry or the wire
 * format tell a customer nothing, so customerMessage() collapses them into one
 * safe sentence; only what the customer can actually act on (a wrong OTP, no
 * balance, a limit) is surfaced verbatim.
 */
class ErrorCode
{
    public const SUCCESS           = '00';
    public const REQUEST_TIMED_OUT = '19';
    public const DUPLICATE_MESSAGE = '46';
    public const EXPIRED_OTP       = '88';
    public const INVALID_OTP       = '89';
    public const SIGN_IN_EXPIRED   = '91';

    /** Codes the customer can do something about — shown to them as-is. */
    private const ACTIONABLE = [
        '05', '12', '19', '20', '21', '32', '46', '56', '58', '60', '61', '62',
        '63', '64', '65', '73', '88', '89', '95', '99', '106', '126', '127',
        '128', '151', '152', '153', '154', '155', '156', '157', '158', '171',
    ];

    /** @var array<string, array{0: string, 1: string}> code => [english label, arabic message] */
    private const CODES = [
        '00'  => ['Success', 'تمت العملية بنجاح'],
        '02'  => ['Internal System Error', 'خطأ داخلي في نظام الدفع'],
        '03'  => ['Unsupported Request', 'العملية غير مدعومة'],
        '04'  => ['Sender Mobile Not Registered', 'رقم المرسل غير مسجّل في جوال باي'],
        '05'  => ['Receiver Mobile Not Registered', 'الرقم غير مسجّل في محفظة جوال باي'],
        '06'  => ['Agent Not Registered', 'الوكيل غير مسجّل'],
        '11'  => ['Invalid PIN', 'الرقم السري غير صحيح'],
        '12'  => ['Insufficient Funds', 'الرصيد غير كافٍ في المحفظة'],
        '13'  => ['Agent Inactive', 'حساب الوكيل غير مفعّل'],
        '17'  => ['Transaction Not Found', 'لم يتم العثور على العملية'],
        '19'  => ['Request Timed Out', 'انتهت مهلة العملية، حاول مرة أخرى'],
        '20'  => ['Exceeded Daily Count Limit', 'تم تجاوز عدد العمليات المسموح يومياً'],
        '21'  => ['Exceeded Daily Amount Limit', 'تم تجاوز المبلغ المسموح يومياً'],
        '25'  => ['Sender Service Not Active', 'خدمة المرسل غير مفعّلة'],
        '26'  => ['Sender Corporate Not Active', 'حساب الشركة غير مفعّل'],
        '29'  => ['Sender Account Suspended', 'حساب المرسل موقوف'],
        '32'  => ['Receiver Account Suspended', 'المحفظة موقوفة'],
        '37'  => ['Failed to parse message', 'تعذّرت قراءة بيانات الطلب'],
        '38'  => ['Service currently unavailable', 'خدمة الدفع غير متاحة حالياً'],
        '39'  => ['Sender Service Not Registered', 'خدمة المرسل غير مسجّلة'],
        '42'  => ['Invalid Device ID', 'معرّف الجهاز غير صالح'],
        '43'  => ['Invalid Activation Code', 'رمز التفعيل غير صالح'],
        '44'  => ['Invalid Password', 'كلمة المرور غير صحيحة'],
        '45'  => ['Device Not Found', 'تعذّر التحقق من بيانات الدخول'],
        '46'  => ['Message Is Duplicated', 'تم إرسال هذه العملية مسبقاً'],
        '50'  => ['Device Not Active', 'الجهاز غير مفعّل'],
        '51'  => ['Device Is Stolen', 'الجهاز مُبلّغ عنه كمسروق'],
        '54'  => ['Device is already in use', 'الجهاز مستخدم بالفعل'],
        '56'  => ['Invalid Mobile Number', 'رقم الجوال غير صالح'],
        '58'  => ['Transaction Amount Not Allowed', 'المبلغ غير مسموح به'],
        '60'  => ['Exceeded Weekly Count Limit', 'تم تجاوز عدد العمليات المسموح أسبوعياً'],
        '61'  => ['Exceeded Weekly Amount Limit', 'تم تجاوز المبلغ المسموح أسبوعياً'],
        '62'  => ['Exceeded Monthly Count Limit', 'تم تجاوز عدد العمليات المسموح شهرياً'],
        '63'  => ['Exceeded Monthly Amount Limit', 'تم تجاوز المبلغ المسموح شهرياً'],
        '64'  => ['Exceeded Yearly Count Limit', 'تم تجاوز عدد العمليات المسموح سنوياً'],
        '65'  => ['Exceeded Yearly Amount Limit', 'تم تجاوز المبلغ المسموح سنوياً'],
        '67'  => ['Payment Type Not Supported', 'نوع الدفع غير مدعوم'],
        '70'  => ['Service Integration Failure', 'فشل الاتصال بخدمة الدفع'],
        '73'  => ['Invalid Amount', 'المبلغ غير صالح'],
        '85'  => ['Message Not Found', 'لم يتم العثور على الرسالة'],
        '86'  => ['Invalid Operation', 'عملية غير صالحة'],
        '88'  => ['Expired OTP', 'انتهت صلاحية رمز التحقق، اطلب رمزاً جديداً'],
        '89'  => ['Invalid OTP', 'رمز التحقق غير صحيح'],
        '90'  => ['Device Blocked', 'الجهاز محظور'],
        '91'  => ['Sign In Expired', 'انتهت جلسة الاتصال بخدمة الدفع'],
        '92'  => ['Sender Account Not Defined', 'حساب المرسل غير معرّف'],
        '93'  => ['Receiver Account Not Defined', 'حساب المستلم غير معرّف'],
        '94'  => ['Sender Account In Active', 'حساب المرسل غير نشط'],
        '95'  => ['Receiver Account In Active', 'المحفظة غير نشطة'],
        '97'  => ['Invalid Request', 'طلب غير صالح'],
        '99'  => ['Cash Cap Exceeded', 'تم تجاوز الحد النقدي المسموح'],
        '100' => ['Password Expired', 'انتهت صلاحية كلمة المرور'],
        '101' => ['Pin Expired', 'انتهت صلاحية الرقم السري'],
        '106' => ['Wallet Cap Reached', 'تم بلوغ الحد الأقصى لرصيد المحفظة'],
        '126' => ['Exceed Number Of Attempts', 'تم تجاوز عدد المحاولات المسموح'],
        '127' => ['Max Number Of Requested Otp', 'تم تجاوز عدد رموز التحقق المسموح طلبها'],
        '128' => ['Max Number Of Retrying Otp', 'تم تجاوز عدد محاولات إدخال رمز التحقق'],
        '129' => ['Max Number of Devices Reached', 'تم بلوغ الحد الأقصى لعدد الأجهزة'],
        '131' => ['Activation Code Expired', 'انتهت صلاحية رمز التفعيل'],
        '151' => ['Exceeded Total Daily Count Limit', 'تم تجاوز إجمالي عدد العمليات اليومي'],
        '152' => ['Exceeded Total Daily Amount Limit', 'تم تجاوز إجمالي المبلغ اليومي'],
        '153' => ['Exceeded Total Weekly Count Limit', 'تم تجاوز إجمالي عدد العمليات الأسبوعي'],
        '154' => ['Exceeded Total Weekly Amount Limit', 'تم تجاوز إجمالي المبلغ الأسبوعي'],
        '155' => ['Exceeded Total Monthly Count Limit', 'تم تجاوز إجمالي عدد العمليات الشهري'],
        '156' => ['Exceeded Total Monthly Amount Limit', 'تم تجاوز إجمالي المبلغ الشهري'],
        '157' => ['Exceeded Total Yearly Count Limit', 'تم تجاوز إجمالي عدد العمليات السنوي'],
        '158' => ['Exceeded Total Yearly Amount Limit', 'تم تجاوز إجمالي المبلغ السنوي'],
        '167' => ['Invalid Account', 'الحساب غير صالح'],
        '171' => ['Exceeded Total Credit Limit', 'تم تجاوز الحد الائتماني'],
        '180' => ['Service Blocked', 'الخدمة محظورة'],
        '181' => ['Max Number Of Activation Code Reached', 'تم بلوغ الحد الأقصى لرموز التفعيل'],
        '182' => ['Activation Code Already Requested', 'تم طلب رمز التفعيل مسبقاً'],
    ];

    /** The vendor's English name for a code — for the dashboard and logs. */
    public static function label(?string $code): string
    {
        return self::CODES[(string) $code][0] ?? 'Unknown Error';
    }

    /** Arabic wording for a code, whoever is reading. */
    public static function message(?string $code): string
    {
        return self::CODES[(string) $code][1] ?? 'حدث خطأ غير متوقع في خدمة الدفع';
    }

    /**
     * Arabic wording safe to hand the customer. Anything describing our merchant
     * account or the wire protocol is a bug on our side, not something the
     * customer can fix — so it becomes one generic sentence.
     */
    public static function customerMessage(?string $code): string
    {
        return in_array((string) $code, self::ACTIONABLE, true)
            ? self::message($code)
            : 'تعذّر إتمام عملية الدفع، يرجى المحاولة لاحقاً أو التواصل معنا';
    }

    public static function known(?string $code): bool
    {
        return isset(self::CODES[(string) $code]);
    }

    /** @return array<string, string> code => "code — english label" */
    public static function options(): array
    {
        $options = [];

        foreach (self::CODES as $code => [$label, $message]) {
            $options[$code] = $code . ' — ' . $label;
        }

        return $options;
    }
}
