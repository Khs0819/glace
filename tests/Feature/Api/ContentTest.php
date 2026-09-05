<?php

use App\Models\Faq;
use App\Models\PaymentAccount;
use App\Models\SiteContent;
use App\Support\HtmlSanitizer;

/**
 * Dashboard-owned content: payment accounts (13), FAQs (15), terms (16),
 * privacy (17).
 */

beforeEach(fn () => fakePublicDisk());

// ─── payment accounts ───────────────────────────────────────────────────────

it('serves the shop payment accounts in the storefront shape', function () {
    PaymentAccount::create([
        'method'          => 'bop',
        'qr_image'        => 'payment-accounts/bop-qr.png',
        'holder_name'     => 'شركة جلاسيه الأمير',
        'bank_name'       => 'بنك فلسطين',
        'primary_label'   => 'رقم الحساب',
        'primary_value'   => '123456789',
        'secondary_label' => 'IBAN',
        'secondary_value' => 'PS00PALS000000000000123456789',
        'sort_order'      => 0,
    ]);

    $response = test()->getJson('/api/payment-accounts')->assertOk()->assertJsonCount(1);

    $response->assertJsonPath('0.method', 'bop')
        ->assertJsonPath('0.holderName', 'شركة جلاسيه الأمير')
        ->assertJsonPath('0.bankName', 'بنك فلسطين')
        ->assertJsonPath('0.primaryValue', '123456789')
        ->assertJsonPath('0.secondaryLabel', 'IBAN');

    // Absolute URL, per the media contract in swagger.yaml.
    expect($response->json('0.qrImage'))->toStartWith('http');
});

it('omits bankName for a wallet rather than sending it empty', function () {
    PaymentAccount::create([
        'method'        => 'jawwal-manual',
        'holder_name'   => 'جلاسيه الأمير',
        'primary_label' => 'رقم جوال باي',
        'primary_value' => '0599123456',
    ]);

    // handoff 13: bankName is for banks; a wallet leaves it out entirely.
    expect(test()->getJson('/api/payment-accounts')->json('0'))->not->toHaveKey('bankName');
});

it('hides an account the dashboard has switched off', function () {
    PaymentAccount::create([
        'method' => 'paypal', 'holder_name' => 'x',
        'primary_label' => 'y', 'primary_value' => 'z', 'active' => false,
    ]);

    test()->getJson('/api/payment-accounts')->assertOk()->assertJsonCount(0);
});

// ─── FAQs ───────────────────────────────────────────────────────────────────

it('serves faqs in dashboard order', function () {
    Faq::create(['id' => 'second', 'question' => 'س٢', 'answer' => 'ج٢', 'sort_order' => 2]);
    Faq::create(['id' => 'first', 'question' => 'س١', 'answer' => 'ج١', 'sort_order' => 1]);

    // Array order is display order (handoff 15).
    test()->getJson('/api/help/faqs')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', 'first')
        ->assertJsonPath('1.id', 'second');
});

it('includes an faq link only when one is set', function () {
    Faq::create([
        'id' => 'contact', 'question' => 'س', 'answer' => 'ج',
        'link_href' => '/contact', 'link_label' => 'تواصل معنا',
    ]);
    Faq::create(['id' => 'plain', 'question' => 'س', 'answer' => 'ج', 'sort_order' => 2]);

    $faqs = test()->getJson('/api/help/faqs')->assertOk()->json();

    expect($faqs[0]['link'])->toBe(['href' => '/contact', 'label' => 'تواصل معنا'])
        ->and($faqs[1])->not->toHaveKey('link');
});

it('hides an faq that is switched off', function () {
    Faq::create(['id' => 'hidden', 'question' => 'س', 'answer' => 'ج', 'active' => false]);

    test()->getJson('/api/help/faqs')->assertOk()->assertJsonCount(0);
});

// ─── terms & privacy ────────────────────────────────────────────────────────

it('serves terms as one html string', function () {
    SiteContent::create([
        'key'   => 'terms',
        'title' => 'الشروط والأحكام',
        'body'  => '<h3>1. قبول الشروط</h3><p>استخدامك للتطبيق...</p>',
    ]);

    $response = test()->getJson('/api/terms')->assertOk();

    // A single string, not an array of sections (handoff 16).
    expect($response->json())->toBeString()
        ->toContain('<h3>1. قبول الشروط</h3>');
});

it('serves privacy the same way', function () {
    SiteContent::create([
        'key' => 'privacy', 'title' => 'سياسة الخصوصية',
        'body' => '<h3>1. المعلومات التي نجمعها</h3><p>نجمع...</p>',
    ]);

    expect(test()->getJson('/api/privacy')->assertOk()->json())
        ->toBeString()->toContain('المعلومات التي نجمعها');
});

it('returns an empty string rather than null when a page is unwritten', function () {
    expect(test()->getJson('/api/terms')->assertOk()->json())->toBe('')
        ->and(test()->getJson('/api/privacy')->assertOk()->json())->toBe('');
});

// ─── sanitisation ───────────────────────────────────────────────────────────

it('strips script out of dashboard html on the way in', function () {
    $content = SiteContent::create([
        'key' => 'terms', 'title' => 'الشروط',
        'body' => '<h3>عنوان</h3><script>steal()</script><p>نص</p>',
    ]);

    // Cleaned before it is stored, so a payload that got past the editor is
    // never persisted (handoff 16).
    expect($content->fresh()->body)->not->toContain('<script')
        ->toContain('<h3>عنوان</h3>')
        ->toContain('<p>نص</p>');
});

it('sanitises again on the way out, for rows written around the model', function () {
    SiteContent::create(['key' => 'terms', 'title' => 'الشروط', 'body' => '<p>ok</p>']);

    // A direct SQL edit never passes through the setter.
    DB::table('site_contents')->where('key', 'terms')
        ->update(['body' => '<p onclick="x()">نص</p><script>bad()</script>']);

    $body = test()->getJson('/api/terms')->assertOk()->json();

    expect($body)->not->toContain('script')
        ->and($body)->not->toContain('onclick');
});

it('keeps the tags the storefront actually renders', function () {
    $html = '<h3>عنوان</h3><p>فقرة</p><ul><li>عنصر</li></ul><a href="/contact">تواصل</a>';

    expect(HtmlSanitizer::clean($html))->toBe($html);
});

it('removes every event handler attribute', function () {
    foreach (['onclick', 'onerror', 'onload', 'onmouseover'] as $handler) {
        expect(HtmlSanitizer::clean("<p {$handler}=\"bad()\">نص</p>"))->toBe('<p>نص</p>');
    }
});

it('refuses a javascript href, however it is disguised', function () {
    foreach ([
        'javascript:alert(1)',
        'JaVaScRiPt:alert(1)',
        "java\tscript:alert(1)",
        'java&#09;script:alert(1)',
        ' javascript:alert(1)',
        'data:text/html,<script>alert(1)</script>',
    ] as $href) {
        $clean = HtmlSanitizer::clean('<a href="' . $href . '">اضغط</a>');

        expect($clean)->not->toContain('javascript')
            ->and($clean)->not->toContain('data:text/html');
    }
});

it('keeps the text inside a disallowed tag but drops the tag', function () {
    // Losing a paragraph of terms because it sat inside a <div> would be worse
    // than the div itself.
    expect(HtmlSanitizer::clean('<div class="x"><p>مهم</p></div>'))->toBe('<p>مهم</p>');
});

it('drops script and style contents entirely', function () {
    expect(HtmlSanitizer::clean('<script>alert(1)</script><p>بعد</p>'))->toBe('<p>بعد</p>')
        ->and(HtmlSanitizer::clean('<style>body{display:none}</style><p>بعد</p>'))->toBe('<p>بعد</p>');
});

it('protects the opener on a link that opens a new tab', function () {
    expect(HtmlSanitizer::clean('<a href="https://x.test" target="_blank">س</a>'))
        ->toContain('rel="noopener noreferrer"');
});

it('leaves arabic text intact', function () {
    // DOMDocument assumes ISO-8859-1 without an explicit encoding, which would
    // mangle every one of these.
    $html = '<p>مرحباً بك في جلاسيه الأمير — نتمنى لك يوماً سعيداً</p>';

    expect(HtmlSanitizer::clean($html))->toBe($html);
});

it('handles an empty or blank body without falling over', function () {
    expect(HtmlSanitizer::clean(''))->toBe('')
        ->and(HtmlSanitizer::clean('   '))->toBe('');
});
