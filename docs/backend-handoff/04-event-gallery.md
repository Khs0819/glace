# 04 — معرض صور الفعالية `images[]`

**الأولوية:** عالية  
**Endpoints:** `GET /events` · `GET /events/{id}`  
**الحالة الحيّة (2026-08-13):** `listImage` = 10/10 ✅ · **`images` = `[]` على كل الفعاليات** · **مفيش حقل معرض في Filament**

---

## المطلوب

في Edit Event أضيفوا حقل رفع متعدد منفصل عن «صورة البطاقة»:

| حقل الأدمن | API |
|---|---|
| صورة البطاقة (موجود) | `listImage` ✅ |
| **معرض صور الفعالية (ناقص)** | `images: string[]` ❌ |

![بطاقة فقط · مفيش معرض](./images/dashboard-event.png)

```json
{
  "id": 3,
  "listImage": "http://…/storage/events/….png",
  "images": []
}
```

بعد الإصلاح (بعد رفع من الأدمن):

```json
"images": [
  "http://…/storage/events/…-a.png",
  "http://…/storage/events/…-b.png"
]
```

- URLs كاملة · بدون `null` داخل المصفوفة  
- إما روابط أو `[]` فارغة

```bash
curl -s "$API/events/3" | jq '{listImage, images}'
```

---

## معايير قبول

- [ ] حقل «معرض صور الفعالية» ظاهر في Edit Event (منفصل عن البطاقة)
- [ ] رفع أكثر من صورة لمعرض فعالية واحدة
- [ ] `GET /events/{id}` يرجع `images` بروابط حقيقية بعد الرفع
- [ ] صفحة `/events/{id}` على الفرونت تعرض المعرض من الـAPI
