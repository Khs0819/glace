<x-mail::message>
# ملخص طلبك

رقم الطلب: **{{ $order->reference }}**

الحالة: **{{ $order->status }}**

<x-mail::table>
| الصنف | الكمية | السعر |
| :---- | :----: | ----: |
@foreach ($order->items as $item)
| {{ $item->product_name }}{{ $item->description ? ' — ' . $item->description : '' }} | {{ $item->quantity }} | {{ number_format($item->line_total, 2) }} ₪ |
@endforeach
</x-mail::table>

**المجموع الفرعي:** {{ number_format($order->subtotal, 2) }} ₪
@if ($order->discount > 0)

**الخصم:** −{{ number_format($order->discount, 2) }} ₪
@endif
@if ($order->delivery_fee > 0)

**رسوم التوصيل:** {{ number_format($order->delivery_fee, 2) }} ₪
@endif

**الإجمالي:** {{ number_format($order->total, 2) }} ₪

@if ($order->address)
### عنوان التوصيل
{{ $order->address['name'] ?? '' }} — {{ $order->address['phone'] ?? '' }}
{{ collect([$order->address['city'] ?? null, $order->address['area'] ?? null, $order->address['street'] ?? null])->filter()->implode('، ') }}
@endif

شكراً لك،<br>
{{ config('app.name') }}
</x-mail::message>
