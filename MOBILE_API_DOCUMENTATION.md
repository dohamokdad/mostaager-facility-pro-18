# دليل مطور تطبيقات الموبايل - Mostaager Facility Pro API

## نظرة عامة

هذا الدليل يشرح كيفية استخدام REST APIs المتاحة في إضافة Mostaager Facility Pro لربط تطبيقات الموبايل مع الموقع.

---

## 🔐 المصادقة (Authentication)

جميع الطلبات تتطلب مصادقة باستخدام JWT Token.

### الحصول على Token

**Endpoint:** `POST /wp-json/mfp/v1/auth/token`

**طلب:**
```json
{
  "username": "user@example.com",
  "password": "password123"
}
```

**رد ناجح:**
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user_id": 123,
    "role": "owner"
  }
}
```

**رد فاشل:**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

### استخدام Token

أضف الـ Token في Header لكل طلب:

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**مدة صلاحية الـ Token:** أسبوع واحد (7 أيام)

---

## 📱 API Version 1 (mfp/v1)

### المباني والوحدات

#### 1. جلب المباني
**Endpoint:** `GET /wp-json/mfp/v1/buildings`

**الصلاحيات:** Admin, Building Manager

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "مبنى الأفق",
      "manager_id": 5,
      "address": "شارع الرئيسي، القاهرة"
    }
  ]
}
```

#### 2. جلب وحدات المبنى
**Endpoint:** `GET /wp-json/mfp/v1/buildings/{id}/units`

**الصلاحيات:** Admin, Building Manager

**المعاملات:**
- `id` (required): معرف المبنى

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "building_id": 1,
      "unit_number": "101",
      "floor": "1",
      "status": "available"
    }
  ]
}
```

---

### الفواتير

#### 3. جلب الفواتير
**Endpoint:** `GET /wp-json/mfp/v1/invoices`

**الصلاحيات:** جميع المستخدمين

**المعاملات:**
- `building_id` (optional): تصفية حسب المبنى

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 100,
      "user_id": 123,
      "amount": 5000.00,
      "status": "pending",
      "due_date": "2024-01-15",
      "invoice_type": "rent"
    }
  ]
}
```

#### 4. تحديث حالة الفاتورة
**Endpoint:** `PATCH /wp-json/mfp/v1/invoices/{id}`

**الصلاحيات:** Admin, Building Manager

**المعاملات:**
- `id` (required): معرف الفاتورة

**رد:**
```json
{
  "success": true,
  "data": {
    "invoice_id": 100,
    "status": "paid"
  }
}
```

---

### الصيانة

#### 5. جلب طلبات الصيانة
**Endpoint:** `GET /wp-json/mfp/v1/maintenance`

**الصلاحيات:** جميع المستخدمين

**المعاملات:**
- `building_id` (optional): تصفية حسب المبنى

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 50,
      "building_id": 1,
      "unit_id": 10,
      "title": "تسرب مياه",
      "description": "تسرب في الحمام",
      "status": "open",
      "priority": "high",
      "cost": 500.00
    }
  ]
}
```

#### 6. إنشاء طلب صيانة
**Endpoint:** `POST /wp-json/mfp/v1/maintenance`

**الصلاحيات:** Building Manager

**طلب:**
```json
{
  "building_id": 1,
  "unit_id": 10,
  "title": "تسرب مياه",
  "description": "تسرب في الحمام",
  "cost": 500.00,
  "status": "open",
  "priority": "high",
  "payer_type": "owner"
}
```

**رد:**
```json
{
  "success": true,
  "data": {
    "maintenance_id": 50
  }
}
```

#### 7. تحديث طلب الصيانة
**Endpoint:** `PATCH /wp-json/mfp/v1/maintenance/{id}`

**الصلاحيات:** Building Manager

**طلب:**
```json
{
  "status": "in_progress"
}
```

**الحالات المسموحة:** `open`, `in_progress`, `completed`, `closed`

**رد:**
```json
{
  "success": true,
  "data": {
    "maintenance_id": 50,
    "status": "in_progress"
  }
}
```

#### 8. جلب جدول زمني لطلب الصيانة
**Endpoint:** `GET /wp-json/mfp/v1/maintenance/{id}/timeline`

**الصلاحيات:** جميع المستخدمين

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "request_id": 50,
      "status": "open",
      "title": "إنشاء الطلب",
      "description": "تم إنشاء الطلب",
      "changed_by_name": "أحمد محمد",
      "created_at": "2024-01-10 10:00:00"
    }
  ]
}
```

---

### الإشعارات

#### 9. جلب الإشعارات
**Endpoint:** `GET /wp-json/mfp/v1/notifications`

**الصلاحيات:** جميع المستخدمين

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "type": "maintenance_status_changed",
      "message": "تم تحديث حالة طلب الصيانة #50",
      "is_read": 0,
      "created_at": "2024-01-10 10:00:00"
    }
  ]
}
```

#### 10. تحديد الإشعار كمقروء
**Endpoint:** `PATCH /wp-json/mfp/v1/notifications/{id}/read`

**الصلاحيات:** جميع المستخدمين

**رد:**
```json
{
  "success": true,
  "data": {
    "notification_id": 1,
    "read": true
  }
}
```

#### 11. إعدادات الإشعارات
**Endpoint:** `GET /wp-json/mfp/v1/notification-preferences`

**الصلاحيات:** جميع المستخدمين

**رد:**
```json
{
  "success": true,
  "data": {
    "in_app": 1,
    "email": 1,
    "whatsapp": 0,
    "push": 1
  }
}
```

**Endpoint:** `POST /wp-json/mfp/v1/notification-preferences`

**طلب:**
```json
{
  "in_app": 1,
  "email": 1,
  "whatsapp": 0,
  "push": 1
}
```

---

### Push Notifications

#### 12. تسجيل Push Token
**Endpoint:** `POST /wp-json/mfp/v1/push-tokens`

**طلب:**
```json
{
  "token": "firebase_push_token_here",
  "platform": "ios"
}
```

**المنصات:** `ios`, `android`, `web`

#### 13. حذف Push Token
**Endpoint:** `DELETE /wp-json/mfp/v1/push-tokens`

**طلب:**
```json
{
  "token": "firebase_push_token_here"
}
```

---

### المناقشات

#### 14. جلب المناقشات
**Endpoint:** `GET /wp-json/mfp/v1/discussions`

**المعاملات:**
- `building_id` (required): معرف المبنى

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "building_id": 1,
      "title": "مشكلة في المصعد",
      "created_at": "2024-01-10 10:00:00"
    }
  ]
}
```

#### 15. إضافة رد على مناقشة
**Endpoint:** `POST /wp-json/mfp/v1/discussions/{id}/replies`

**طلب:**
```json
{
  "message": "شكراً على الإبلاغ"
}
```

**رد:**
```json
{
  "success": true,
  "data": {
    "reply_id": 10
  }
}
```

---

### المحفظة

#### 16. جلب المحفظة
**Endpoint:** `GET /wp-json/mfp/v1/wallet`

**الصلاحيات:** جميع المستخدمين

**المعاملات:**
- `building_id` (optional): للمديرين لجلب محفظة المبنى

**رد للمستخدم العادي:**
```json
{
  "success": true,
  "data": {
    "balance": 5000.00
  }
}
```

**رد للمدير:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "building_id": 1,
    "balance": 50000.00,
    "frozen_balance": 10000.00,
    "target_amount": 200000.00,
    "status": "active"
  }
}
```

---

## 📱 API Version 2 (mostager/v2)

### الفواتير المحسّنة

#### 17. دفع فاتورة
**Endpoint:** `POST /wp-json/mostaager/v2/invoices/{id}/pay`

**الصلاحيات:** صاحب الفاتورة أو Admin

**رد:**
```json
{
  "success": true,
  "data": {
    "payment_url": "https://example.com/checkout/order-pay/123/?key=abc"
  }
}
```

---

### فواتير الخدمات التشغيلية

#### 18. جلب فواتير الخدمات
**Endpoint:** `GET /wp-json/mostaager/v2/utility-bills`

**المعاملات:**
- `building_id` (required): معرف المبنى

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "building_id": 1,
      "title": "فاتورة الكهرباء - يناير 2024",
      "bill_type": "electricity",
      "total_amount": 15000.00,
      "status": "draft",
      "billing_period_start": "2024-01-01",
      "billing_period_end": "2024-01-31"
    }
  ]
}
```

#### 19. إنشاء فاتورة خدمات
**Endpoint:** `POST /wp-json/mostaager/v2/utility-bills`

**طلب:**
```json
{
  "building_id": 1,
  "title": "فاتورة الكهرباء - فبراير 2024",
  "bill_type": "electricity",
  "total_amount": 15000.00,
  "billing_period_start": "2024-02-01",
  "billing_period_end": "2024-02-29",
  "distribution_method": "equal",
  "notes": "فاتورة شهرية",
  "auto_distribute": true
}
```

**رد:**
```json
{
  "success": true,
  "data": {
    "bill_id": 2,
    "message": "تم إنشاء الفاتورة بنجاح"
  }
}
```

#### 20. توزيع فاتورة الخدمات
**Endpoint:** `POST /wp-json/mostaager/v2/utility-bills/{id}/distribute`

**رد:**
```json
{
  "success": true,
  "data": {
    "distributed_count": 10,
    "message": "تم توزيع الفاتورة على 10 وحدات"
  }
}
```

---

## 🔧 API الصيانة (mostager/v1/maintenance)

### إدارة تذاكر الصيانة

#### 21. جلب قائمة التذاكر
**Endpoint:** `GET /wp-json/mostaager/v1/maintenance`

**المعاملات:**
- `building_id` (optional): تصفية حسب المبنى
- `status` (optional): تصفية حسب الحالة
- `priority` (optional): تصفية حسب الأولوية
- `page` (optional): رقم الصفحة (default: 1)
- `per_page` (optional): عدد النتائج (default: 20, max: 100)

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "building_id": 1,
      "unit_id": 10,
      "facility_id": 5,
      "tenant_name": "أحمد محمد",
      "title": "تسرب مياه",
      "description": "تسرب في الحمام",
      "status": "new",
      "priority": "high",
      "created_at": "2024-01-10 10:00:00",
      "building_name": "مبنى الأفق",
      "unit_number": "101",
      "facility_name": "سباكة"
    }
  ],
  "total": 50,
  "pages": 3,
  "page": 1
}
```

#### 22. إنشاء تذكرة صيانة
**Endpoint:** `POST /wp-json/mostaager/v1/maintenance`

**طلب:**
```json
{
  "building_id": 1,
  "unit_id": 10,
  "facility_id": 5,
  "tenant_name": "أحمد محمد",
  "tenant_phone": "0123456789",
  "title": "تسرب مياه",
  "description": "تسرب في الحمام",
  "category": "plumbing",
  "priority": "high"
}
```

**رد:**
```json
{
  "success": true,
  "ticket_id": 1,
  "message": "تم إنشاء طلب الصيانة بنجاح، سنتواصل معك قريباً"
}
```

#### 23. جلب تذكرة مفصلة
**Endpoint:** `GET /wp-json/mostaager/v1/maintenance/{id}`

**رد:**
```json
{
  "success": true,
  "ticket": {
    "id": 1,
    "building_id": 1,
    "unit_id": 10,
    "title": "تسرب مياه",
    "status": "in_progress",
    "priority": "high",
    "cost_estimate": 500.00,
    "actual_cost": 450.00,
    "assigned_to": 5,
    "building_name": "مبنى الأفق",
    "unit_number": "101",
    "floor": "1"
  },
  "comments": [
    {
      "id": 1,
      "ticket_id": 1,
      "user_id": 5,
      "comment": "جاري العمل على المشكلة",
      "user_name": "محمد علي",
      "created_at": "2024-01-10 11:00:00"
    }
  ],
  "attachments": [
    {
      "id": 1,
      "ticket_id": 1,
      "file_url": "https://example.com/uploads/photo.jpg",
      "file_type": "image/jpeg",
      "uploaded_by": 5,
      "created_at": "2024-01-10 10:30:00"
    }
  ]
}
```

#### 24. تحديث التذكرة
**Endpoint:** `PUT /wp-json/mostaager/v1/maintenance/{id}`

**طلب:**
```json
{
  "status": "in_progress",
  "priority": "medium",
  "assigned_to": 5,
  "cost_estimate": 500.00,
  "actual_cost": 450.00
}
```

**رد:**
```json
{
  "success": true,
  "message": "تم التحديث بنجاح"
}
```

#### 25. تحديث الحالة مع تعليق
**Endpoint:** `PUT /wp-json/mostaager/v1/maintenance/{id}/status`

**طلب:**
```json
{
  "status": "completed",
  "comment": "تم إصلاح المشكلة بنجاح"
}
```

**الحالات المسموحة:** `new`, `assigned`, `in_progress`, `waiting_parts`, `completed`, `cancelled`

**رد:**
```json
{
  "success": true,
  "message": "تم تحديث الحالة إلى: مكتمل"
}
```

#### 26. جلب التعليقات
**Endpoint:** `GET /wp-json/mostaager/v1/maintenance/{id}/comments`

**رد:**
```json
{
  "success": true,
  "comments": [
    {
      "id": 1,
      "ticket_id": 1,
      "user_id": 5,
      "comment": "جاري العمل على المشكلة",
      "user_name": "محمد علي",
      "created_at": "2024-01-10 11:00:00"
    }
  ]
}
```

#### 27. إضافة تعليق
**Endpoint:** `POST /wp-json/mostaager/v1/maintenance/{id}/comments`

**طلب:**
```json
{
  "comment": "متى سيتم إصلاح المشكلة؟"
}
```

**رد:**
```json
{
  "success": true,
  "comment_id": 2,
  "message": "تم إضافة التعليق بنجاح"
}
```

#### 28. رفع مرفق
**Endpoint:** `POST /wp-json/mostaager/v1/maintenance/{id}/attachments`

**نوع المحتوى:** `multipart/form-data`

**المعاملات:**
- `attachment` (required): الملف (JPG, PNG, GIF, PDF, MP4)
- الحد الأقصى: 10MB

**رد:**
```json
{
  "success": true,
  "attachment_id": 1,
  "file_url": "https://example.com/uploads/photo.jpg",
  "file_type": "image/jpeg"
}
```

#### 29. إحصائيات لوحة التحكم
**Endpoint:** `GET /wp-json/mostaager/v1/maintenance/stats`

**المعاملات:**
- `building_id` (optional): تصفية حسب المبنى

**رد:**
```json
{
  "success": true,
  "stats": {
    "total": 50,
    "new": 10,
    "in_progress": 15,
    "completed": 20,
    "high_priority": 5
  }
}
```

---

## 🏢 API المرافق (mostager/v1/facilities)

### إدارة المرافق

#### 30. جلب مرافق المبنى
**Endpoint:** `GET /wp-json/mostaager/v1/facilities`

**المعاملات:**
- `building_id` (required): معرف المبنى

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "building_id": 1,
      "facility_type_id": 2,
      "name": "مصعد المبنى",
      "status": "working",
      "facility_type_name": "مصاعد",
      "maintenance_status": "working",
      "latest_maintenance_status": "completed",
      "latest_maintenance_updated_at": "2024-01-10 10:00:00"
    }
  ]
}
``#### 31. جلب حالة المرافق
**Endpoint:** `GET /wp-json/mostaager/v1/facilities/status`

**المعاملات:**
- `building_id` (required): معرف المبنى

**رد:**
```json
{
  "success": true,
  "data": [
    {
      "facility_id": 1,
      "facility_name": "مصعد المبنى",
      "facility_type_name": "مصاعد",
      "base_status": "working",
      "derived_status": "working",
      "color": "#10b981",
      "latest_request_title": "صيانة دورية",
      "latest_request_status": "completed",
      "latest_request_updated_at": "2024-01-10 10:00:00"
    }
  ]
}
```

**الحالات المشتقة:**
- `working` - يعمل بشكل طبيعي (أخضر)
- `under_maintenance` - تحت الصيانة (برتقالي)
- `critical` - حالة حرجة (أحمر)

#### 32. جلب مرفق مفصل
**Endpoint:** `GET /wp-json/mostaager/v1/facilities/{id}`

**رد:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "building_id": 1,
    "facility_type_id": 2,
    "name": "مصعد المبنى",
    "status": "working",
    "description": "مصعد رئيسي للمبنى",
    "facility_type_name": "مصاعد",
    "maintenance_status": "working",
    "latest_maintenance_status": "completed",
    "latest_maintenance_updated_at": "2024-01-10 10:00:00"
  }
}
```

---

## 👥 الأدوار والصلاحيات

### الأدوار المتاحة:
1. **Admin** - صلاحيات كاملة على كل شيء
2. **Building Manager** - إدارة المباني والوحدات والصيانة
3. **Owner** - إدارة العقارات والفواتير
4. **Tenant** - عرض الفواتير والإشعارات وإنشاء طلبات الصيانة
5. **Agent** - إدارة العقارات والموافقات

### الصلاحيات حسب الدور:

| Endpoint | Admin | Manager | Owner | Tenant | Agent |
|----------|-------|---------|-------|--------|-------|
| /auth/token | ✅ | ✅ | ✅ | ✅ | ✅ |
| /buildings | ✅ | ✅ | ❌ | ❌ | ❌ |
| /buildings/{id}/units | ✅ | ✅ | ❌ | ❌ | ❌ |
| /invoices | ✅ | ✅ | ✅ | ✅ | ❌ |
| /invoices/{id}/pay | ✅ | ✅ | ✅ | ✅ | ❌ |
| /maintenance (GET) | ✅ | ✅ | ✅ | ✅ | ✅ |
| /maintenance (POST) | ✅ | ✅ | ❌ | ❌ | ❌ |
| /maintenance/{id} (PATCH) | ✅ | ✅ | ❌ | ❌ | ❌ |
| /notifications | ✅ | ✅ | ✅ | ✅ | ✅ |
| /wallet | ✅ | ✅ | ✅ | ✅ | ✅ |
| /utility-bills | ✅ | ✅ | ❌ | ❌ | ❌ |
| /facilities | ✅ | ✅ | ❌ | ❌ | ❌ |

---

## 📨 صيغة الردود

### الرد الناجح:
```json
{
  "success": true,
  "data": { ... }
}
```

### الرد الفاشل:
```json
{
  "success": false,
  "message": "وصف الخطأ"
}
```

### أكواد الحالة HTTP:
- `200` - نجاح
- `201` - تم الإنشاء
- `400` - طلب غير صالح
- `401` - غير مصرح (Token غير صالح أو منتهي)
- `403` - ممنوع (صلاحيات غير كافية)
- `404` - غير موجود
- `422` - بيانات غير صالحة
- `500` - خطأ في الخادم

---

## 🔗 أمثلة على الاستخدام

### مثال 1: تسجيل الدخول وجلب الفواتير

```javascript
// 1. تسجيل الدخول
const loginResponse = await fetch('https://example.com/wp-json/mfp/v1/auth/token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    username: 'user@example.com',
    password: 'password123'
  })
});

const { token } = (await loginResponse.json()).data;

// 2. جلب الفواتير
const invoicesResponse = await fetch('https://example.com/wp-json/mfp/v1/invoices', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const invoices = (await invoicesResponse.json()).data;
```

### مثال 2: إنشاء طلب صيانة

```javascript
const createMaintenance = async (token, buildingId, unitId, title, description) => {
  const response = await fetch('https://example.com/wp-json/mfp/v1/maintenance', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      building_id: buildingId,
      unit_id: unitId,
      title: title,
      description: description,
      cost: 500.00,
      status: 'open',
      priority: 'high',
      payer_type: 'owner'
    })
  });

  return await response.json();
};
```

### مثال 3: رفع صورة لطلب صيانة

```javascript
const uploadAttachment = async (token, ticketId, file) => {
  const formData = new FormData();
  formData.append('attachment', file);

  const response = await fetch(`https://example.com/wp-json/mostaager/v1/maintenance/${ticketId}/attachments`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    },
    body: formData
  });

  return await response.json();
};
```

---

## ⚠️ ملاحظات مهمة

1. **الـ Token ينتهي بعد أسبوع** - يجب تجديده قبل انتهائه
2. **جميع الطلبات يجب أن تكون HTTPS** في الإنتاج
3. **الملفات المرفوعة محدودة بـ 10MB**
4. **الأنواع المسموحة للملفات:** JPG, PNG, GIF, PDF, MP4
5. **التواريخ بتنسيق:** `YYYY-MM-DD HH:MM:SS`
6. **المبالغ بتنسيق:** رقم عشري (مثال: 5000.00)
7. **اللغة الافتراضية:** العربية

---

## 🐞 معالجة الأخطاء

### Token منتهي:
```json
{
  "success": false,
  "message": "Token expired"
}
```
**الحل:** طلب token جديد من endpoint `/auth/token`

### صلاحيات غير كافية:
```json
{
  "success": false,
  "message": "غير مصرح"
}
```
**الحل:** التأكد من أن المستخدم لديه الدور المناسب

### بيانات مفقودة:
```json
{
  "success": false,
  "message": "Missing required fields"
}
```
**الحل:** التأكد من إرسال جميع الحقول المطلوبة

---

## 📞 الدعم الفني

للدعم الفني والاستفسارات:
- البريد الإلكتروني: support@mostaager.com
- التوثيق: https://docs.mostaager.com

---

## 🔄 التحديثات

### الإصدار 2.0.0 (يناير 2024)
- إضافة API v2 للفواتير المحسّنة
- إضافة API لفواتير الخدمات التشغيلية
- تحديث API الصيانة مع المرفقات
- إضافة API المرافق مع الحالة المشتقة

---

**آخر تحديث:** يناير 2024
**الإصدار:** 2.0.0
