# دليل API — منصة مستأجر العقاري
**لمطوّر تطبيق الموبايل** · الإصدار 18.10.0 · آخر تحديث: 23 سبتمبر 2026

---

## 1. الأساسيات

| | |
|---|---|
| **Base URL** | `https://ejar-egy.com/wp-json/mfp/v1` |
| **المصادقة** | JWT عبر ترويسة `Authorization: Bearer <token>` |
| **الصيغة** | JSON فقط (UTF-8) |
| **HTTPS** | إلزامي — لا ترسل التوكن على HTTP |

> **مسار قديم:** `/wp-json/mostager/v1` ما زال يعمل لكنه **deprecated**. ردوده تحمل الترويسات
> `X-MS-API-Deprecated: true` و `X-MS-API-Successor: mfp/v1` و `Sunset: 2027-01-01`.
> لا تبنِ عليه شيئاً جديداً؛ استخدم `mfp/v1` فقط.

---

## 2. شكل الاستجابة (ثابت لكل المسارات)

**نجاح:**
```json
{ "success": true, "data": { } }
```

**نجاح مع قائمة مرقّمة:**
```json
{
  "success": true,
  "data": [ ],
  "meta": { "page": 1, "per_page": 20, "total": 124, "total_pages": 7 }
}
```

**خطأ:**
```json
{
  "success": false,
  "code": "forbidden_building",
  "message": "لا تملك صلاحية الوصول إلى هذا المبنى",
  "details": {}
}
```

اعتمد على **`code`** في منطق التطبيق (ثابت بالإنجليزية)، واعرض **`message`** للمستخدم (عربي).

### أكواد HTTP

| الكود | المعنى | ماذا يفعل التطبيق |
|---|---|---|
| 200 | نجاح | — |
| 201 | تم الإنشاء | اقرأ `data` للحصول على الرقم الجديد |
| 400 | طلب ناقص | راجع المعاملات |
| 401 | توكن مفقود أو منتهٍ | أعد تسجيل الدخول |
| 403 | لا صلاحية على هذا المورد | اعرض رسالة ولا تعد المحاولة |
| 404 | غير موجود | — |
| 422 | بيانات غير صالحة | اقرأ `details.allowed` لعرض الخيارات الصحيحة |
| 429 | محاولات دخول كثيرة | انتظر 15 دقيقة |
| 500 | خطأ داخلي | أعد المحاولة لاحقاً |

### أكواد الأخطاء الشائعة

`missing_credentials` · `invalid_credentials` · `too_many_attempts` · `forbidden` ·
`forbidden_building` · `missing_building_id` · `invalid_id` · `not_found` ·
`invalid_status` · `invalid_priority` · `invalid_payer_type` · `unit_building_mismatch`

---

## 3. تسجيل الدخول

```bash
curl -X POST 'https://ejar-egy.com/wp-json/mfp/v1/auth/token' \
  -H 'Content-Type: application/json' \
  --data '{"username":"USER","password":"PASS"}'
```

```json
{ "success": true, "data": { "token": "eyJ...", "user_id": 123, "role": "tenant" } }
```

- **صلاحية التوكن: 7 أيام.** بعدها ترجع كل الطلبات 401 ← أعد تسجيل الدخول.
- **حد المحاولات:** 5 محاولات فاشلة لكل IP خلال 15 دقيقة، ثم 429.
- **التخزين:** Keychain على iOS و EncryptedSharedPreferences على Android. **لا تستخدم تخزيناً عادياً.**
- `role` يأتي بإحدى القيم: `admin` · `building_manager` · `owner` · `agent` · `tenant`.
  استخدمه لبناء الواجهة فقط — الخادم يتحقق من الصلاحية في كل طلب بغض النظر عن التطبيق.

---

## 4. قاعدة الصلاحيات

كل مسار يأخذ `building_id` يتحقق من علاقة **هذا المستخدم** بهذا المبنى:

| الدور | ما يراه |
|---|---|
| مدير موقع | كل شيء |
| مدير مبنى | المباني التي يديرها |
| وسيط | المباني المعيّن عليها |
| مالك | المباني التي يملك فيها وحدات |
| مستأجر | مبنى وحدته فقط |

تمرير رقم مبنى آخر يرجع **403 `forbidden_building`**. لا تحاول التحايل — الفحص في الخادم.

---

## 5. المسارات

### 5.1 المباني والوحدات

**`GET /buildings`** — مباني المستخدم (مدير المبنى أو مدير الموقع)
```bash
curl -H "Authorization: Bearer $TOKEN" \
  'https://ejar-egy.com/wp-json/mfp/v1/buildings'
```

**`GET /buildings/{id}/units`** — وحدات مبنى · يدعم الترقيم · متاح أيضاً للمالك والمستأجر
```bash
curl -H "Authorization: Bearer $TOKEN" \
  'https://ejar-egy.com/wp-json/mfp/v1/buildings/3/units?page=1&per_page=20'
```
```json
{ "success": true,
  "data": [ { "id": "12", "building_id": "3", "owner_id": "8",
              "tenant_id": "21", "agent_id": "6", "status": "occupied" } ],
  "meta": { "page": 1, "per_page": 20, "total": 34, "total_pages": 2 } }
```
`status` للوحدة: `available` · `occupied` · `maintenance` · `reserved`

---

### 5.2 الفواتير

**`GET /invoices`** — تختلف النتيجة حسب الدور تلقائياً (مستأجر: فواتيره، مالك: فواتير وحداته، مدير: فواتير مبانيه)

| المعامل | النوع | ملاحظة |
|---|---|---|
| `building_id` | int | اختياري — للمدير والأدمن |

**`PATCH /invoices/{id}`** — تعليم الفاتورة مدفوعة · **مدير مبنى أو أدمن فقط**
```bash
curl -X PATCH "https://ejar-egy.com/wp-json/mfp/v1/invoices/55" \
  -H "Authorization: Bearer $MANAGER_TOKEN"
```
```json
{ "success": true, "data": { "invoice_id": 55, "status": "paid" } }
```
حالات الفاتورة: `pending` · `paid` · `overdue` · `cancelled`

---

### 5.3 الصيانة

**`GET /maintenance`** — يدعم الترقيم

| المعامل | النوع | ملاحظة |
|---|---|---|
| `building_id` | int | **إلزامي** للمالك والمستأجر، اختياري للمدير |
| `status` | string | اختياري |
| `page` / `per_page` | int | الافتراضي 20، الحد 100 |

**`POST /maintenance`** — **مدير مبنى أو أدمن فقط** · يرجع **201**
```bash
curl -X POST 'https://ejar-egy.com/wp-json/mfp/v1/maintenance' \
  -H "Authorization: Bearer $MANAGER_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{
    "building_id": 3,
    "unit_id": 12,
    "title": "عطل في مضخة المياه",
    "description": "انخفاض ضغط المياه منذ صباح اليوم",
    "cost": 850,
    "status": "open",
    "priority": "high",
    "payer_type": "owner",
    "maintenance_type": "emergency"
  }'
```

| الحقل | إلزامي | القيم المسموحة |
|---|---|---|
| `building_id` | ✅ | مبنى يملك المستخدم صلاحية عليه |
| `unit_id` | ❌ | **يجب أن تتبع نفس المبنى** وإلا 422 `unit_building_mismatch` |
| `title` | ✅ | نص |
| `status` | ❌ | `open` · `in_progress` · `completed` · `closed` |
| `priority` | ❌ | `low` · `medium` · `high` |
| `payer_type` | ❌ | `tenant` · `owner` · `building` |
| `cost` | ❌ | رقم — يُستخدم لتوزيع الفواتير تلقائياً |

**`PATCH /maintenance/{id}`** — تغيير الحالة فقط
```bash
curl -X PATCH 'https://ejar-egy.com/wp-json/mfp/v1/maintenance/55' \
  -H "Authorization: Bearer $MANAGER_TOKEN" \
  -H 'Content-Type: application/json' \
  --data '{"status":"in_progress"}'
```
تغيير الحالة يرسل إشعاراً للمستأجر تلقائياً.

**`GET /maintenance/{id}/timeline`** — سجل تغيّر حالات الطلب

---

### 5.4 الإشعارات

**`GET /notifications`** — إشعارات صاحب التوكن

**`PATCH /notifications/{id}/read`** — تعليم كمقروء

**`GET /notification-preferences`** · **`POST /notification-preferences`** — تفضيلات الإشعارات

---

### 5.5 الإشعارات الفورية (Push)

**`POST /push-tokens`**
```json
{ "token": "FCM_DEVICE_TOKEN", "platform": "android" }
```
`platform`: `android` · `ios` · `web`

**`DELETE /push-tokens`** — أرسل نفس الحقل `token` عند تسجيل الخروج أو حذف التطبيق.

> سجّل التوكن بعد تسجيل الدخول مباشرة، وأعد تسجيله عند تجديد FCM للتوكن.

---

### 5.6 المناقشات

**`GET /discussions?building_id=3`** — يدعم الترقيم · يتطلب صلاحية على المبنى

**`POST /discussions/{id}/replies`**
```json
{ "message": "تم إصلاح المصعد اليوم" }
```

---

### 5.7 المحفظة

**`GET /wallet`** — رصيد المستخدم

**`GET /wallet?building_id=3`** — محفظة المبنى (مدير المبنى فقط، ولمبانيه فقط)

---

## 6. أمثلة كود

### Dart / Flutter
```dart
class MostaagerApi {
  static const base = 'https://ejar-egy.com/wp-json/mfp/v1';
  String? _token;

  Future<Map<String, dynamic>> _get(String path) async {
    final res = await http.get(Uri.parse('$base$path'), headers: {
      'Authorization': 'Bearer $_token',
      'Accept': 'application/json',
    });
    final body = jsonDecode(utf8.decode(res.bodyBytes));

    if (res.statusCode == 401) throw SessionExpired();          // أعد تسجيل الدخول
    if (body['success'] != true) throw ApiException(body['code'], body['message']);

    return body;
  }

  Future<List> units(int buildingId, {int page = 1}) async {
    final body = await _get('/buildings/$buildingId/units?page=$page&per_page=20');
    return body['data'] as List;   // body['meta'] فيها بيانات الترقيم
  }
}
```

### Kotlin
```kotlin
val request = Request.Builder()
    .url("$BASE/maintenance?building_id=3&page=1")
    .addHeader("Authorization", "Bearer $token")
    .addHeader("Accept", "application/json")
    .build()
```

### Swift
```swift
var request = URLRequest(url: URL(string: "\(base)/invoices")!)
request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
request.setValue("application/json", forHTTPHeaderField: "Accept")
```

---

## 7. قواعد مهمة للتطبيق

1. **401 = انتهت الجلسة.** اعرض شاشة الدخول فوراً، ولا تكرّر الطلب.
2. **403 لا يُعاد.** يعني المستخدم فعلاً لا يملك صلاحية على هذا المورد.
3. **اقرأ `code` لا `message`.** الرسائل قد تتغير صياغتها.
4. **الترقيم:** استخدم `meta.total_pages` لإيقاف التحميل اللانهائي.
5. **التاريخ والوقت:** كل القيم بصيغة `YYYY-MM-DD HH:MM:SS` بتوقيت الموقع (القاهرة).
6. **المبالغ:** أرقام بالجنيه المصري (EGP). لا تفترض خانتين عشريتين في العرض؛ نسّقها محلياً.
7. **الأرقام كنصوص:** بعض الحقول القادمة من قاعدة البيانات تصل كنص (`"12"`) — استخدم تحويلاً متسامحاً.
8. **لا تخزّن بيانات حساسة** (فواتير، عقود) في تخزين غير مشفّر على الجهاز.

---

## 8. قائمة اختبار قبل الإطلاق

- [ ] تسجيل دخول بكل دور: مستأجر، مالك، وسيط، مدير مبنى
- [ ] توكن منتهٍ (عدّل ساعة الجهاز أو انتظر) → التطبيق يعيد لشاشة الدخول
- [ ] تمرير `building_id` لمبنى غير تابع للمستخدم → 403 وعرض رسالة مناسبة
- [ ] إنشاء طلب صيانة بحالة غير صالحة → 422 وقراءة `details.allowed`
- [ ] إنشاء طلب صيانة بوحدة من مبنى آخر → 422 `unit_building_mismatch`
- [ ] قائمة طويلة (أكثر من 20 عنصراً) → الترقيم يعمل ولا يتكرر عنصر
- [ ] تسجيل وحذف توكن FCM عند الدخول والخروج
- [ ] وضع الطيران / انقطاع الشبكة → رسالة خطأ واضحة لا تعطّل الشاشة

---

## 9. للتواصل

أي مسار ناقص أو سلوك غير متوقع: أرسل **المسار الكامل + الترويسات (بدون التوكن) + الرد الكامل +
دور المستخدم**. هذا يختصر التشخيص كثيراً.
