# حزمة تعديلات للباك اند — Glace API

**تاريخ الحزمة:** 2026-08-11  
**آخر مراجعة QA:** 2026-08-12 → [`08-qa-remaining.md`](./08-qa-remaining.md)  
**API الحالي:** `http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/api`  
**داشبورد:** `http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/admin`  
**العقد:** [`swagger.yaml`](./swagger.yaml) (نسخة كاملة جوّا المجلد) · [`MENU_CATALOG-1.md`](../../MENU_CATALOG-1.md) (جذر الريبو)

هذه المجلدات مخصّصة لإرسالها لفريق الباك. كل ملف = تذكرة تنفيذ مستقلة مع معايير قبول.

---

## 👈 ابدأ من هنا — ترتيب القراءة

| # | افتح | ليه |
|---|---|---|
| 1 | **[`08-qa-remaining.md`](./08-qa-remaining.md)** | **قائمة المتبقي بعد مراجعة 2026-08-12 — ابدأ من هون** |
| 2 | [`README.md`](./README.md) (هذا الملف) | جدول الحالات + التحقق السريع |
| 3 | [`01-items-image.md`](./01-items-image.md) | أصناف flat-list (سعر + صورة) |
| 4 | [`05-builder-sizes.md`](./05-builder-sizes.md) | أنواع/أحجام/أسعار builder (`cup` · `family` · `brad`) |
| 5 | [`07-flat-list-mixes.md`](./07-flat-list-mixes.md) | المكسات |
| 6 | [`04-home-and-events.md`](./04-home-and-events.md) | الفعاليات — `images[]` مفيش مكان في الداشبورد |
| 7 | [`images/`](./images/) | لقطات الدليل |

التذاكر [`02`](./02-builder-flavors.md) · [`03`](./03-media-and-cleanup.md) · [`06`](./06-brad-boza-media.md) مقفولة أو شبه مقفولة — للمرجع فقط.

**لقطات دليل النواقص:** [`images/`](./images/)  
- [`loqaimat.png`](./images/loqaimat.png) — ستورفرونت flat-list: سعر ظاهر · مكان الصورة فاضي  
- [`brad-storefront.png`](./images/brad-storefront.png) — براد: أحجام وأسعار ظاهرة · **مفيش تحكم أدمن**  
- [`family-storefront.png`](./images/family-storefront.png) — بوظة عائلي: أماكن صور الأحجام فاضية  
- [`family-dashboard.png`](./images/family-dashboard.png) — داشبورد builder: **مفيش** تحكم نوع/حجم/سعر  
- [`dashboard-settings.png`](./images/dashboard-settings.png) — منتج flat-list · معلومات أساسية (**بدون** أصناف)  
- [`dashboard-product-presentaion-settings.png`](./images/dashboard-product-presentaion-settings.png) — إعدادات العرض فقط  
- [`dashboard-event.png`](./images/dashboard-event.png) — فعالية · صورة بطاقة فقط · **مفيش حقل لـ`images[]`**  

---

## الأولوية

| # | الملف | الأثر | حالة QA 2026-08-12 |
|---|---|---|---|
| 1 | [`01-items-image.md`](./01-items-image.md) | صور + أسعار أصناف flat-list من الداشبورد | ❌ `image` 0/69 · ✅ `price` في API فقط · ❌ مفيش حقول أدمن |
| 2 | [`02-builder-flavors.md`](./02-builder-flavors.md) | قفل طلب `cup` / `brad-boza` / `family` | ✅ مغلق |
| 3 | [`05-builder-sizes.md`](./05-builder-sizes.md) | CRUD أنواع+أحجام+أسعار · صور الأحجام | ❌ `cup`+`family`+`brad`: بيانات API موجودة · **مفيش داشبورد** |
| 4 | [`07-flat-list-mixes.md`](./07-flat-list-mixes.md) | CRUD مكس/سوبر مكس من الداشبورد (flat-list) | ⚠️ بيانات API موجودة · ❌ مفيش CRUD أدمن |
| 5 | [`06-brad-boza-media.md`](./06-brad-boza-media.md) | عنوان أسعار البراد بدون تكرار · بدون صورة كاسة | ✅ API · ⚠️ ناقص تعديل `iceCreamAddonPrices` من الأدمن |
| 6 | [`04-home-and-events.md`](./04-home-and-events.md) | كسر/ضعف الهوم والفعاليات | ⚠️ URL كامل ✅ · **مفيش مكان لـ`images[]` في الداشبورد** · `images: []` دايمًا |
| 7 | [`03-media-and-cleanup.md`](./03-media-and-cleanup.md) | صور نكهات + تنظيفات صغيرة | ✅ صور نكهات · ❌ addons مكررة |
| 8 | [`08-qa-remaining.md`](./08-qa-remaining.md) | **قائمة المتبقي بعد مراجعة الرفع** (API + داشبورد) | 🔴 القبول مرفوض — راجع الملف |

---

## تحقق سريع بعد التنفيذ

```bash
# شكل العقد + البنود المتفق عليها
node scripts/audit-api.mjs http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/api

# عيّنة يدوية لصور الأصناف
curl -s .../api/menu/products/corn | jq '.items[] | {id, label, image}'

# عيّنة يدوية للنكهات
curl -s .../api/menu/products/cup | jq '.flavors[] | select(.id=="mango" or .id=="flora") | {id, available}'
curl -s .../api/menu/products/family | jq '.flavors | length'

# عيّنة يدوية للهوم / الفعاليات
curl -s .../api/home | jq '.about.paragraphs | map(type) | unique'
curl -s .../api/home | jq '.hero.slides[0].manImg'
curl -s .../api/home | jq '.events.items | length'
curl -s .../api/events/3 | jq '{listImage, images}'

# عيّنة يدوية لأحجام العائلي
curl -s .../api/menu/products/family | jq '.sizes[] | {id, image, available}'

# عيّنة يدوية للمكسات
curl -s .../api/menu/products/pancake | jq '.mixes[] | {id, available, pick, basePrice, itemIds}'
curl -s .../api/menu/products/kunafa | jq '.mixes'
```

القبول النهائي: `audit-api.mjs` يخرج `0` فشل، وكل بنود [`08-qa-remaining.md`](./08-qa-remaining.md) متعلّمة، وكل الـ19 منتج يوصلوا لـ«أضف للسلة» بدون شاشة خطأ، والهوم/الفعاليات بدون placeholders مكسورة.

---

## ملاحظات للباك

- الفرونت **ما عندوش fallback** لداتا المنيو. رد ناقص/غلط = شاشة خطأ للزبون.
- **المطلوب للصور:** URL كامل عبر `Storage::disk('public')->url(...)`.  
  الفرونت عنده تطبيع مؤقت للمسارات النسبية — **مش بديل عن العقد** (شوف [`04-home-and-events.md`](./04-home-and-events.md) بند 1).
- روابط `cdn.example.com` **مرفوضة** (دومين ميت) وتُعامل كـ«مفيش صورة».
- بعد أي رفع جديد: نفّذ بنود [`08-qa-remaining.md`](./08-qa-remaining.md) قبل ما تعتبر الحزمة مكتملة.
