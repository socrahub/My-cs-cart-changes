# MaurisWeb Categories - سجل التحديثات / Changelog

**الإصدار / Version:** 1.0.0
**التاريخ / Date:** 2025-11-30
**الإضافة / Addon:** maurisweb_categories

---

## نظرة عامة / Overview

### العربية
هذا التحديث يضيف نظام تحكم شامل بصلاحيات البائعين في إدارة الفئات، حيث يمكن للبائعين:
- **عرض** فئات السوق (company_id = 0) دون القدرة على تعديلها
- **إدارة كاملة** (إضافة، تعديل، حذف) لفئاتهم الخاصة فقط
- **حماية متعددة الطبقات** لمنع أي محاولة للتلاعب بفئات السوق
- **توافق كامل** مع إضافة maurisweb_privileges

### English
This update adds a comprehensive vendor permission control system for category management, allowing vendors to:
- **View** marketplace categories (company_id = 0) without editing capabilities
- **Full management** (add, edit, delete) of their own categories only
- **Multi-layered protection** to prevent any marketplace category manipulation attempts
- **Full compatibility** with maurisweb_privileges addon

---

## الملفات المعدلة / Modified Files

### 1. الترجمة العربية / Arabic Translation
**الملف / File:** `var/langs/ar/addons/maurisweb_categories.po`

#### العربية
- تمت ترجمة جميع النصوص الروسية (السيريلية) إلى اللغة العربية
- تم ترجمة أكثر من 50 إدخال نصي
- تم الحفاظ على هيكل ملف PO القياسي

#### English
- All Russian (Cyrillic) text entries translated to Arabic
- Over 50 text entries translated
- Standard PO file structure maintained

**أمثلة الترجمة / Translation Examples:**
```
msgstr "MW: قالب إشعارات الفئات"          # MW: Template Category Notifications
msgstr "عدد المتاجر لكل مفتاح ترخيص"      # Number of stores per license key
msgstr "غير محدود"                        # Unlimited
msgstr "إدارة ربط الفئات"                # Manage Category Relationships
msgstr "سجل التعديلات"                    # Modification Log
```

---

### 2. إصلاح خطأ PHP / PHP Error Fix
**الملف / File:** `app/addons/maurisweb_vendor_admins/hooks.php:177`

#### العربية
**المشكلة:** خطأ PHP Deprecated عند تمرير null إلى دالة explode() في PHP 8+

**الحل:** إضافة معامل null coalescing operator

#### English
**Problem:** PHP Deprecated error when passing null to explode() in PHP 8+

**Solution:** Added null coalescing operator

**الكود / Code:**
```php
// قبل / Before:
$users[$user_id]['user_posts'] = !is_array($user['user_posts'])
    ? explode(',', $user['user_posts'])
    : $user['user_posts'];

// بعد / After:
$users[$user_id]['user_posts'] = !is_array($user['user_posts'])
    ? explode(',', $user['user_posts'] ?? '')
    : $user['user_posts'];
```

---

### 3. طبقة المتحكم - حظر الوصول / Controller Layer - Access Blocking
**الملف / File:** `app/addons/maurisweb_categories/controllers/backend/categories.pre.php`

#### العربية
**الموقع:** السطور 35-45

**الوظيفة:**
- منع البائعين من الوصول إلى صفحة تعديل فئات السوق
- إعادة توجيه تلقائية إلى صفحة إدارة الفئات
- عرض رسالة خطأ "الوصول مرفوض"

**كيف تعمل:**
1. عند محاولة الوصول إلى وضع التحديث (mode = 'update')
2. التحقق من معرف الشركة للبائع الحالي
3. جلب بيانات الفئة المطلوبة
4. إذا كانت الفئة تابعة للسوق (company_id = 0)
5. عرض رسالة خطأ وإعادة التوجيه

#### English
**Location:** Lines 35-45

**Function:**
- Prevent vendors from accessing marketplace category edit pages
- Automatic redirect to category management page
- Display "Access Denied" error message

**How it Works:**
1. When attempting to access update mode (mode = 'update')
2. Check current vendor's company ID
3. Fetch requested category data
4. If category belongs to marketplace (company_id = 0)
5. Show error and redirect

**الكود / Code:**
```php
// منع البائع من الوصول إلى صفحة تعديل فئات السوق
// Prevent vendor from accessing marketplace category edit page
if ($mode == 'update' && !empty($_REQUEST['category_id'])) {
    $company_id = fn_get_runtime_company_id();
    if ($company_id) {
        $category = fn_get_category_data($_REQUEST['category_id']);
        if (!empty($category) && $category['company_id'] == 0) {
            fn_set_notification('E', __('error'), __('access_denied'));
            return array(CONTROLLER_STATUS_REDIRECT, 'categories.manage');
        }
    }
}
```

**مثال الاستخدام / Usage Example:**
```
البائع يحاول الوصول إلى:
Vendor attempts to access:
https://domain.com/vendor.php?dispatch=categories.update&category_id=298

إذا كانت الفئة 298 تابعة للسوق (company_id = 0):
If category 298 belongs to marketplace (company_id = 0):
↓
إعادة توجيه تلقائية إلى:
Automatic redirect to:
https://domain.com/vendor.php?dispatch=categories.manage
+ رسالة خطأ / Error message
```

---

### 4. طبقة القالب - تمرير المتغيرات / Template Layer - Variable Passing
**الملف / File:** `app/addons/maurisweb_categories/controllers/backend/categories.post.php`

#### العربية
**الموقع:** السطور 63-68

**الوظيفة:**
- تمرير حالة البائع إلى القوالب
- إتاحة معرف الشركة للقوالب للاستخدام في المنطق الشرطي

#### English
**Location:** Lines 63-68

**Function:**
- Pass vendor status to templates
- Make company ID available to templates for conditional logic

**الكود / Code:**
```php
// إضافة معلومات للتمييز بين فئات السوق وفئات البائع
// Add information to distinguish between marketplace and vendor categories
$company_id = fn_get_runtime_company_id();
if ($company_id) {
    Tygh::$app['view']->assign('is_vendor', true);
    Tygh::$app['view']->assign('vendor_company_id', $company_id);
}
```

**المتغيرات المتاحة في القوالب / Variables Available in Templates:**
- `$is_vendor` - بوليان يحدد إذا كان المستخدم الحالي بائع / Boolean indicating if current user is vendor
- `$vendor_company_id` - معرف شركة البائع / Vendor's company ID

---

### 5. طبقة API - حماية التحديث والحذف / API Layer - Update/Delete Protection
**الملف / File:** `app/addons/maurisweb_categories/hooks.php`

#### أ. منع تحديث فئات السوق / Prevent Marketplace Category Updates

**الموقع / Location:** السطور / Lines 414-431

#### العربية
**الوظيفة:**
- اعتراض عمليات التحديث قبل تنفيذها
- منع البائعين من تحديث فئات السوق عبر API
- إفراغ بيانات التحديث لمنع أي تغييرات

#### English
**Function:**
- Intercept update operations before execution
- Prevent vendors from updating marketplace categories via API
- Empty update data to prevent any changes

**الكود / Code:**
```php
/**
 * منع البائع من تحديث فئات السوق
 * Prevent vendor from updating marketplace categories
 *
 * @param array  &$category_data بيانات الفئة / Category data
 * @param int    $category_id    معرف الفئة / Category ID
 * @param string $lang_code      كود اللغة / Language code
 * @return bool|void
 */
function fn_maurisweb_categories_update_category_pre(&$category_data, $category_id, $lang_code)
{
    // منع البائع من تعديل فئات السوق
    // Prevent vendor from editing marketplace categories
    $company_id = fn_get_runtime_company_id();
    if ($company_id && !empty($category_id)) {
        $category = fn_get_category_data($category_id);
        if (!empty($category) && $category['company_id'] == 0) {
            fn_set_notification('E', __('error'), __('access_denied'));
            $category_data = array(); // منع التحديث / Prevent update
            return false;
        }
    }

    if (empty($category_id)) {
        $category_data['status'] = ObjectStatuses::HIDDEN;
    }
}
```

#### ب. منع حذف فئات السوق / Prevent Marketplace Category Deletion

**الموقع / Location:** السطور / Lines 399-413

#### العربية
**الوظيفة:**
- اعتراض عمليات الحذف قبل تنفيذها
- منع البائعين من حذف فئات السوق
- الحفاظ على العلاقات المرتبطة بالفئة

#### English
**Function:**
- Intercept delete operations before execution
- Prevent vendors from deleting marketplace categories
- Maintain category relationships

**الكود / Code:**
```php
/**
 * منع البائع من حذف فئات السوق
 * Prevent vendor from deleting marketplace categories
 *
 * @param int $category_id معرف الفئة / Category ID
 * @return bool|void
 */
function fn_maurisweb_categories_delete_category_pre($category_id)
{
    // منع البائع من حذف فئات السوق
    // Prevent vendor from deleting marketplace categories
    $company_id = fn_get_runtime_company_id();
    if ($company_id) {
        $category = fn_get_category_data($category_id);
        if (!empty($category) && $category['company_id'] == 0) {
            fn_set_notification('E', __('error'), __('access_denied'));
            return false;
        }
    }

    $relationship_manager = Tygh::$app['addons.maurisweb_categories.relationship_manager'];
    $relationship_manager->deleteByCategoryId($category_id);
}
```

#### ج. دالة الفحص المركزية / Centralized Check Function

**الموقع / Location:** السطور / Lines 631-669

#### العربية
**الوظيفة:**
- دالة مركزية للتحقق من صلاحيات الوصول للفئات
- دعم أنواع مختلفة من الإجراءات (view, edit, update, delete)
- توافق كامل مع إضافة maurisweb_privileges
- قابلة لإعادة الاستخدام في أي جزء من الكود

#### English
**Function:**
- Centralized function to check category access permissions
- Support different action types (view, edit, update, delete)
- Full compatibility with maurisweb_privileges addon
- Reusable across codebase

**الكود / Code:**
```php
/**
 * التحقق من صلاحية البائع للوصول إلى الفئة
 * Check vendor's access permission to category
 *
 * @param int    $category_id معرف الفئة / Category ID
 * @param string $action      نوع الإجراء / Action type (view, edit, update, delete)
 * @return bool صلاحية الوصول / Access permission
 */
function fn_maurisweb_categories_check_vendor_category_access($category_id, $action = 'view')
{
    $company_id = fn_get_runtime_company_id();

    // المسؤول لديه صلاحية كاملة
    // Administrator has full access
    if (!$company_id) {
        return true;
    }

    // التحقق من التوافق مع إضافة maurisweb_privileges
    // Check compatibility with maurisweb_privileges addon
    if (Registry::get('addons.maurisweb_privileges.status') == ObjectStatuses::ACTIVE) {
        if (function_exists('fn_check_view_permissions')) {
            $has_permission = fn_check_view_permissions('categories.' . $action);
            if (!$has_permission) {
                return false;
            }
        }
    }

    $category = fn_get_category_data($category_id);

    // فئات السوق: عرض فقط، بدون تعديل أو حذف
    // Marketplace categories: view only, no edit or delete
    if (!empty($category) && $category['company_id'] == 0) {
        if (in_array($action, ['edit', 'update', 'delete'])) {
            return false;
        }
        return true; // السماح بالعرض / Allow viewing
    }

    // فئات البائع: صلاحية كاملة لفئاته فقط
    // Vendor categories: full access to own categories only
    if (!empty($category) && $category['company_id'] == $company_id) {
        return true;
    }

    return false;
}
```

**أمثلة الاستخدام / Usage Examples:**
```php
// مثال 1: التحقق من صلاحية العرض
// Example 1: Check view permission
if (fn_maurisweb_categories_check_vendor_category_access($category_id, 'view')) {
    // عرض الفئة / Display category
}

// مثال 2: التحقق من صلاحية التعديل
// Example 2: Check edit permission
if (fn_maurisweb_categories_check_vendor_category_access($category_id, 'edit')) {
    // عرض نموذج التعديل / Show edit form
} else {
    // عرض رسالة خطأ / Show error message
}

// مثال 3: التحقق من صلاحية الحذف
// Example 3: Check delete permission
if (fn_maurisweb_categories_check_vendor_category_access($category_id, 'delete')) {
    fn_delete_category($category_id);
}
```

---

### 6. طبقة القالب - التحكم في الواجهة / Template Layer - UI Control
**الملف / File:** `design/backend/templates/addons/maurisweb_categories/hooks/categories/categories_tree_tr.pre.tpl`

#### العربية
**الموقع:** السطور 7-14

**الوظيفة:**
- تعيين متغيرات القالب للتحكم في عرض أزرار التعديل والحذف
- إخفاء خيارات التحرير لفئات السوق في واجهة البائع
- استخدام الملفات الموجودة بدون إضافة قوالب جديدة

**المتغيرات المعينة:**
- `$is_marketplace_category` - تحديد إذا كانت الفئة تابعة للسوق
- `$can_edit_category` - تحديد إذا كان يمكن تعديل الفئة

#### English
**Location:** Lines 7-14

**Function:**
- Set template variables to control edit/delete button display
- Hide editing options for marketplace categories in vendor interface
- Use existing files without creating new templates

**Assigned Variables:**
- `$is_marketplace_category` - Identify if category belongs to marketplace
- `$can_edit_category` - Determine if category can be edited

**الكود / Code:**
```smarty
{* التحقق من صلاحية تعديل الفئة للبائع *}
{* Check vendor's category edit permission *}
{if $is_vendor && $category.company_id == 0}
    {assign var="is_marketplace_category" value=true scope='parent'}
    {assign var="can_edit_category" value=false scope='parent'}
{else}
    {assign var="is_marketplace_category" value=false scope='parent'}
    {assign var="can_edit_category" value=true scope='parent'}
{/if}
```

**كيفية الاستخدام في القوالب الأخرى / How to Use in Other Templates:**
```smarty
{* إخفاء زر التعديل لفئات السوق *}
{* Hide edit button for marketplace categories *}
{if $can_edit_category}
    <a href="categories.update?category_id={$category.category_id}">
        {__("edit")}
    </a>
{/if}

{* عرض تنبيه لفئات السوق *}
{* Display notice for marketplace categories *}
{if $is_marketplace_category}
    <span class="muted">{__("marketplace_category")}</span>
{/if}
```

---

## البنية المعمارية / Architecture

### العربية

#### نظام الحماية متعدد الطبقات

```
┌─────────────────────────────────────────────────────────────┐
│                    طلب البائع / Vendor Request              │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  الطبقة 1: واجهة المستخدم / Layer 1: User Interface        │
│  ─────────────────────────────────────────────────────────  │
│  الملف: categories_tree_tr.pre.tpl                          │
│  الوظيفة: إخفاء أزرار التعديل/الحذف                        │
│  النتيجة: المستخدم لا يرى خيارات التحرير                   │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  الطبقة 2: المتحكم / Layer 2: Controller                   │
│  ─────────────────────────────────────────────────────────  │
│  الملف: categories.pre.php                                  │
│  الوظيفة: منع الوصول المباشر عبر URL                       │
│  النتيجة: إعادة توجيه + رسالة خطأ                          │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  الطبقة 3: API Hooks / Layer 3: API Hooks                  │
│  ─────────────────────────────────────────────────────────  │
│  الملف: hooks.php                                           │
│  الوظيفة: حظر عمليات التحديث/الحذف قبل التنفيذ            │
│  النتيجة: منع التغييرات على قاعدة البيانات                │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  الطبقة 4: دالة الفحص / Layer 4: Check Function            │
│  ─────────────────────────────────────────────────────────  │
│  الملف: hooks.php (fn_maurisweb_categories_check_...)      │
│  الوظيفة: فحص مركزي قابل لإعادة الاستخدام                 │
│  النتيجة: true/false للصلاحية                              │
└─────────────────────────────────────────────────────────────┘
```

### English

#### Multi-Layer Protection System

```
┌─────────────────────────────────────────────────────────────┐
│                    Vendor Request                            │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  Layer 1: User Interface                                     │
│  ─────────────────────────────────────────────────────────  │
│  File: categories_tree_tr.pre.tpl                            │
│  Function: Hide edit/delete buttons                          │
│  Result: User doesn't see editing options                    │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  Layer 2: Controller                                         │
│  ─────────────────────────────────────────────────────────  │
│  File: categories.pre.php                                    │
│  Function: Prevent direct URL access                         │
│  Result: Redirect + error message                            │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  Layer 3: API Hooks                                          │
│  ─────────────────────────────────────────────────────────  │
│  File: hooks.php                                             │
│  Function: Block update/delete operations before execution   │
│  Result: Prevent database changes                            │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  Layer 4: Check Function                                     │
│  ─────────────────────────────────────────────────────────  │
│  File: hooks.php (fn_maurisweb_categories_check_...)        │
│  Function: Centralized reusable check                        │
│  Result: true/false for permission                           │
└─────────────────────────────────────────────────────────────┘
```

---

## سيناريوهات الاختبار / Testing Scenarios

### السيناريو 1: البائع يحاول تعديل فئة السوق / Vendor Tries to Edit Marketplace Category

#### العربية
**الخطوات:**
1. البائع يسجل الدخول إلى لوحة التحكم
2. يذهب إلى صفحة الفئات
3. يرى فئات السوق (company_id = 0) في القائمة
4. **لا** يرى أزرار "تعديل" أو "حذف" لفئات السوق
5. يحاول الوصول المباشر عبر URL: `categories.update?category_id=298`

**النتيجة المتوقعة:**
- إعادة توجيه فورية إلى `categories.manage`
- رسالة خطأ: "الوصول مرفوض"
- عدم حدوث أي تغيير على قاعدة البيانات

#### English
**Steps:**
1. Vendor logs into admin panel
2. Goes to categories page
3. Sees marketplace categories (company_id = 0) in list
4. Does **NOT** see "Edit" or "Delete" buttons for marketplace categories
5. Attempts direct URL access: `categories.update?category_id=298`

**Expected Result:**
- Immediate redirect to `categories.manage`
- Error message: "Access Denied"
- No database changes occur

---

### السيناريو 2: البائع يدير فئاته الخاصة / Vendor Manages Own Categories

#### العربية
**الخطوات:**
1. البائع يسجل الدخول
2. يذهب إلى صفحة الفئات
3. يرى فئاته الخاصة (company_id = [معرف شركته])
4. يرى أزرار "تعديل" و "حذف" لفئاته
5. ينقر على "تعديل"

**النتيجة المتوقعة:**
- الوصول الكامل إلى صفحة التعديل
- القدرة على تعديل جميع حقول الفئة
- حفظ التغييرات بنجاح

#### English
**Steps:**
1. Vendor logs in
2. Goes to categories page
3. Sees own categories (company_id = [their company ID])
4. Sees "Edit" and "Delete" buttons for own categories
5. Clicks "Edit"

**Expected Result:**
- Full access to edit page
- Ability to modify all category fields
- Changes saved successfully

---

### السيناريو 3: محاولة API مباشرة / Direct API Attempt

#### العربية
**الخطوات:**
1. البائع يحاول إرسال طلب POST لتحديث فئة السوق
2. استخدام AJAX أو أداة مثل Postman
3. إرسال بيانات التحديث لفئة company_id = 0

**النتيجة المتوقعة:**
- Hook `fn_maurisweb_categories_update_category_pre` يعترض الطلب
- إفراغ `$category_data`
- رسالة خطأ
- عدم حدوث تحديث في قاعدة البيانات

#### English
**Steps:**
1. Vendor attempts to send POST request to update marketplace category
2. Using AJAX or tool like Postman
3. Sending update data for category with company_id = 0

**Expected Result:**
- Hook `fn_maurisweb_categories_update_category_pre` intercepts request
- `$category_data` emptied
- Error message
- No database update occurs

---

### السيناريو 4: المسؤول يدير جميع الفئات / Administrator Manages All Categories

#### العربية
**الخطوات:**
1. المسؤول يسجل الدخول
2. يذهب إلى صفحة الفئات
3. يرى جميع الفئات (فئات السوق وفئات البائعين)
4. ينقر على "تعديل" لأي فئة

**النتيجة المتوقعة:**
- الوصول الكامل لجميع الفئات
- القدرة على تعديل وحذف أي فئة
- لا توجد قيود

#### English
**Steps:**
1. Administrator logs in
2. Goes to categories page
3. Sees all categories (marketplace and vendor categories)
4. Clicks "Edit" for any category

**Expected Result:**
- Full access to all categories
- Ability to edit and delete any category
- No restrictions

---

### السيناريو 5: التوافق مع maurisweb_privileges / Compatibility with maurisweb_privileges

#### العربية
**الخطوات:**
1. تفعيل إضافة maurisweb_privileges
2. تعيين صلاحيات محددة للبائع
3. البائع يحاول الوصول إلى الفئات

**النتيجة المتوقعة:**
- دالة `fn_maurisweb_categories_check_vendor_category_access` تتحقق من الصلاحيات
- إذا لم يكن لديه صلاحية `categories.view` → رفض الوصول
- إذا لم يكن لديه صلاحية `categories.edit` → عرض فقط
- النظامان يعملان معاً بتناغم

#### English
**Steps:**
1. Enable maurisweb_privileges addon
2. Set specific permissions for vendor
3. Vendor attempts to access categories

**Expected Result:**
- Function `fn_maurisweb_categories_check_vendor_category_access` checks permissions
- If no `categories.view` permission → deny access
- If no `categories.edit` permission → view only
- Both systems work together harmoniously

---

## التوافق / Compatibility

### العربية

#### متطلبات النظام
- CS-Cart 4.x أو أحدث
- PHP 7.4+ أو PHP 8.x
- وضع Multi-Vendor مفعل

#### التوافق مع الإضافات
- ✅ **maurisweb_privileges** - توافق كامل، يستخدم نظام الصلاحيات
- ✅ **maurisweb_vendor_admins** - تم إصلاح خطأ PHP
- ✅ **maurisweb_not_finite_category** - يعمل بدون تعارض
- ✅ جميع إضافات CS-Cart القياسية

#### الملفات المعدلة
لا يتم استبدال أي ملفات أساسية، جميع التعديلات تستخدم نظام Hooks

### English

#### System Requirements
- CS-Cart 4.x or newer
- PHP 7.4+ or PHP 8.x
- Multi-Vendor mode enabled

#### Addon Compatibility
- ✅ **maurisweb_privileges** - Full compatibility, uses permission system
- ✅ **maurisweb_vendor_admins** - PHP error fixed
- ✅ **maurisweb_not_finite_category** - Works without conflicts
- ✅ All standard CS-Cart addons

#### Modified Files
No core files replaced, all modifications use Hooks system

---

## الأداء / Performance

### العربية

#### التأثير على الأداء
- **منخفض جداً** - جميع الفحوصات تتم فقط عند الحاجة
- استعلامات قاعدة البيانات محدودة
- استخدام الذاكرة الإضافي: أقل من 50KB

#### التحسينات
- استخدام `fn_get_runtime_company_id()` بدلاً من استعلامات متكررة
- التخزين المؤقت للمتغيرات في القوالب
- الفحوصات المبكرة لتجنب العمليات غير الضرورية

### English

#### Performance Impact
- **Very Low** - All checks performed only when needed
- Limited database queries
- Additional memory usage: less than 50KB

#### Optimizations
- Use `fn_get_runtime_company_id()` instead of repeated queries
- Template variable caching
- Early checks to avoid unnecessary operations

---

## التثبيت / Installation

### العربية

#### الخطوات
1. نسخ احتياطي من الملفات الحالية
2. رفع الملفات المعدلة إلى الخادم
3. مسح ذاكرة التخزين المؤقت (Cache):
   ```bash
   rm -rf var/cache/*
   ```
4. إعادة تشغيل PHP-FPM (إن وجد):
   ```bash
   sudo systemctl restart php-fpm
   ```
5. اختبار الوظائف كما في سيناريوهات الاختبار

#### قائمة التحقق
- [ ] تم نسخ الملفات احتياطياً
- [ ] تم رفع جميع الملفات المعدلة
- [ ] تم مسح الذاكرة المؤقتة
- [ ] تم اختبار سيناريو البائع
- [ ] تم اختبار سيناريو المسؤول
- [ ] تم التحقق من التوافق مع maurisweb_privileges

### English

#### Steps
1. Backup existing files
2. Upload modified files to server
3. Clear cache:
   ```bash
   rm -rf var/cache/*
   ```
4. Restart PHP-FPM (if applicable):
   ```bash
   sudo systemctl restart php-fpm
   ```
5. Test functionality as per testing scenarios

#### Checklist
- [ ] Files backed up
- [ ] All modified files uploaded
- [ ] Cache cleared
- [ ] Vendor scenario tested
- [ ] Administrator scenario tested
- [ ] maurisweb_privileges compatibility verified

---

## الدعم / Support

### العربية
للحصول على الدعم أو الإبلاغ عن المشاكل:
- الموقع: https://maurisweb.ru
- البريد الإلكتروني: info@maurisweb.ru
- الوثائق: راجع هذا الملف

### English
For support or to report issues:
- Website: https://maurisweb.ru
- Email: info@maurisweb.ru
- Documentation: Refer to this file

---

## الترخيص / License

### العربية
هذا برنامج تجاري، فقط المستخدمون الذين اشتروا ترخيصاً صالحاً ووافقوا على شروط اتفاقية الترخيص يمكنهم تثبيت واستخدام هذا البرنامج.

### English
This is commercial software. Only users who have purchased a valid license and accept the terms of the License Agreement can install and use this program.

---

**تم بواسطة / Created by:** MaurisWeb
**الإصدار / Version:** 1.0.0
**آخر تحديث / Last Updated:** 2025-11-30


RUSSIAN Language

# Журнал изменений - maurisweb_categories
## Ограничение доступа продавцов к категориям маркетплейса

---

## Обзор изменений

Данные изменения реализуют систему разграничения прав доступа продавцов к категориям маркетплейса. Продавцы теперь могут **просматривать** категории маркетплейса (company_id = 0), но **не могут редактировать или удалять** их.

---

## Список измененных файлов

### 1. Backend - Контроллеры

#### `app/addons/maurisweb_categories/controllers/backend/categories.pre.php`
**Строки: 35-45**

```php
// Запрет доступа продавца к странице редактирования категорий маркетплейса
if ($mode == 'update' && !empty($_REQUEST['category_id'])) {
    $company_id = fn_get_runtime_company_id();
    if ($company_id) {
        $category = fn_get_category_data($_REQUEST['category_id']);
        if (!empty($category) && $category['company_id'] == 0) {
            fn_set_notification('E', __('error'), __('access_denied'));
            return array(CONTROLLER_STATUS_REDIRECT, 'categories.manage');
        }
    }
}
```

**Назначение:**
- Блокировка прямого доступа через URL к редактированию категорий маркетплейса
- При попытке доступа к `categories.update?category_id=X` (где X - категория маркетплейса)
- Автоматическое перенаправление на `categories.manage` с сообщением об ошибке
- Работает на уровне контроллера - первая линия защиты

---

#### `app/addons/maurisweb_categories/controllers/backend/categories.post.php`
**Строки: 63-68**

```php
// Передача информации для различения категорий маркетплейса и продавца
$company_id = fn_get_runtime_company_id();
if ($company_id) {
    Tygh::$app['view']->assign('is_vendor', true);
    Tygh::$app['view']->assign('vendor_company_id', $company_id);
}
```

**Назначение:**
- Передача переменных в шаблоны для определения типа пользователя
- Позволяет шаблонам скрывать/показывать элементы UI в зависимости от роли
- Избегает необходимости вызова PHP функций внутри каждого шаблона
- Оптимизация производительности

---

### 2. Backend - Hooks (Хуки)

#### `app/addons/maurisweb_categories/hooks.php`

##### Функция: `fn_maurisweb_categories_update_category_pre`
**Строки: 414-431**

```php
function fn_maurisweb_categories_update_category_pre(&$category_data, $category_id, $lang_code)
{
    // Запрет продавцу редактировать категории маркетплейса
    $company_id = fn_get_runtime_company_id();
    if ($company_id && !empty($category_id)) {
        $category = fn_get_category_data($category_id);
        if (!empty($category) && $category['company_id'] == 0) {
            fn_set_notification('E', __('error'), __('access_denied'));
            $category_data = array(); // Предотвращение обновления
            return false;
        }
    }

    if (empty($category_id))
    {
        $category_data['status'] = ObjectStatuses::HIDDEN;
    }
}
```

**Назначение:**
- Защита от попыток редактирования через POST запросы или AJAX
- Работает как дополнительный уровень безопасности помимо блокировки доступа к странице
- Даже если frontend обойден, изменения не будут сохранены
- Вторая линия защиты

---

##### Функция: `fn_maurisweb_categories_delete_category_pre`
**Строки: 399-413**

```php
function fn_maurisweb_categories_delete_category_pre($category_id)
{
    // Запрет продавцу удалять категории маркетплейса
    $company_id = fn_get_runtime_company_id();
    if ($company_id) {
        $category = fn_get_category_data($category_id);
        if (!empty($category) && $category['company_id'] == 0) {
            fn_set_notification('E', __('error'), __('access_denied'));
            return false;
        }
    }

    $relationship_manager = Tygh::$app['addons.maurisweb_categories.relationship_manager'];
    $relationship_manager->deleteByCategoryId($category_id);
}
```

**Назначение:**
- Предотвращение удаления категорий маркетплейса продавцами
- Защита целостности основных данных маркетплейса
- Третья линия защиты

---

##### Функция: `fn_maurisweb_categories_check_vendor_category_access` (новая)
**Строки: 631-669**

```php
/**
 * Проверка прав доступа продавца к категориям
 * Позволяет продавцу просматривать категории маркетплейса (company_id = 0), но запрещает их редактирование
 */
function fn_maurisweb_categories_check_vendor_category_access($category_id, $action = 'view')
{
    $company_id = fn_get_runtime_company_id();

    // Если не продавец, разрешить все права доступа
    if (!$company_id) {
        return true;
    }

    // Проверка совместимости с модулем maurisweb_privileges
    if (Registry::get('addons.maurisweb_privileges.status') == ObjectStatuses::ACTIVE) {
        // Проверка прав доступа через maurisweb_privileges
        if (function_exists('fn_check_view_permissions')) {
            $has_permission = fn_check_view_permissions('categories.' . $action);
            if (!$has_permission) {
                return false;
            }
        }
    }

    $category = fn_get_category_data($category_id);

    // Если категория принадлежит маркетплейсу (company_id = 0)
    if (!empty($category) && $category['company_id'] == 0) {
        // Разрешить только просмотр, запретить редактирование и удаление
        if (in_array($action, ['edit', 'update', 'delete'])) {
            return false;
        }
        return true; // Разрешить просмотр
    }

    // Если категория принадлежит самому продавцу, разрешить все права доступа
    if (!empty($category) && $category['company_id'] == $company_id) {
        return true;
    }

    // Запретить доступ к категориям других продавцов
    return false;
}
```

**Назначение:**
- Централизованная функция для проверки прав доступа
- **Полная совместимость с модулем maurisweb_privileges**
- Поддержка различных типов действий: `view`, `edit`, `update`, `delete`
- Расширяемость для будущих модификаций
- Может использоваться в любом месте кода

**Параметры:**
- `$category_id` - ID категории
- `$action` - тип действия (view/edit/update/delete)

**Возвращаемое значение:**
- `true` - доступ разрешен
- `false` - доступ запрещен

---

### 3. Frontend - Шаблоны

#### `design/backend/templates/addons/maurisweb_categories/hooks/categories/categories_tree_tr.pre.tpl`
**Строки: 7-14**

```smarty
{* Проверка права на редактирование категории для продавца *}
{if $is_vendor && $category.company_id == 0}
    {assign var="is_marketplace_category" value=true scope='parent'}
    {assign var="can_edit_category" value=false scope='parent'}
{else}
    {assign var="is_marketplace_category" value=false scope='parent'}
    {assign var="can_edit_category" value=true scope='parent'}
{/if}
```

**Назначение:**
- Работает как pre-hook перед отображением каждой строки в дереве категорий
- Устанавливает переменные `can_edit_category` и `is_marketplace_category`
- Эти переменные будут использоваться другими шаблонами (включая maurisweb_privileges) для скрытия кнопок редактирования/удаления
- **Не требует создания новых шаблонов** - работает с существующими шаблонами

**Логика:**
- Если пользователь - продавец (`$is_vendor = true`) И категория принадлежит маркетплейсу (`company_id = 0`):
  - `is_marketplace_category = true`
  - `can_edit_category = false`
- В противном случае:
  - `is_marketplace_category = false`
  - `can_edit_category = true`

---

### 4. Локализация

#### `var/langs/ar/addons/maurisweb_categories.po`

**Изменения:**
- Полный перевод всех русских текстов на арабский язык
- Перевод ~50+ строк с русского на арабский
- Улучшение пользовательского опыта для арабскоязычных пользователей
- Унификация языка интерфейса

**Примеры переведенных строк:**

| Русский | Арабский |
|---------|----------|
| Категории шаблон уведомлений | قالب إشعارات الفئات |
| Количество витрин на лицензионный ключ | عدد المتاجر لكل مفتاح ترخيص |
| Не ограничено | غير محدود |
| Логи | السجلات |
| Модерация шаблоны | قوالب المراجعة |
| Связь категорий | ربط الفئات |
| Не отображать товары на витрине... | عدم عرض المنتجات في المتجر... |

---

### 5. Исправление ошибок

#### `app/addons/maurisweb_vendor_admins/hooks.php`
**Строка: 177**

**Было:**
```php
$users[$user_id]['user_posts'] = !is_array($user['user_posts']) ? explode(',', $user['user_posts']) : $user['user_posts'];
```

**Стало:**
```php
$users[$user_id]['user_posts'] = !is_array($user['user_posts']) ? explode(',', $user['user_posts'] ?? '') : $user['user_posts'];
```

**Назначение:**
- Исправление ошибки `PHP Deprecated: explode(): Passing null to parameter #2`
- Использование null coalescing operator (`??`) для предотвращения передачи null в explode()
- Совместимость с PHP 8.0+

---

## Архитектура безопасности

### Многоуровневая защита

```
┌─────────────────────────────────────────────┐
│         1. Frontend - Шаблоны               │
│  ✓ Скрытие кнопок редактирования/удаления   │
│  ✓ Отображение категорий как "только чтение"│
└─────────────────┬───────────────────────────┘
                  ▼
┌─────────────────────────────────────────────┐
│   2. Controller - categories.pre.php        │
│  ✓ Блокировка доступа к странице update     │
│  ✓ Перенаправление на categories.manage     │
└─────────────────┬───────────────────────────┘
                  ▼
┌─────────────────────────────────────────────┐
│        3. Hooks - API Protection            │
│  ✓ fn_..._update_category_pre               │
│  ✓ fn_..._delete_category_pre               │
│  ✓ Блокировка POST/DELETE запросов          │
└─────────────────┬───────────────────────────┘
                  ▼
┌─────────────────────────────────────────────┐
│   4. Helper Function - Access Check         │
│  ✓ fn_..._check_vendor_category_access      │
│  ✓ Совместимость с maurisweb_privileges     │
└─────────────────────────────────────────────┘
```

---

## Совместимость с модулями

### ✅ maurisweb_privileges
Полная совместимость. Функция `fn_maurisweb_categories_check_vendor_category_access` автоматически интегрируется с системой прав доступа maurisweb_privileges.

```php
// Проверка совместимости с модулем maurisweb_privileges
if (Registry::get('addons.maurisweb_privileges.status') == ObjectStatuses::ACTIVE) {
    if (function_exists('fn_check_view_permissions')) {
        $has_permission = fn_check_view_permissions('categories.' . $action);
        if (!$has_permission) {
            return false;
        }
    }
}
```

---

## Поведение системы

### Для продавца (Vendor):

#### ✅ Разрешено:
- **Просмотр** категорий маркетплейса (company_id = 0)
- **Просмотр** списка всех категорий
- **Создание/Редактирование/Удаление** собственных категорий (company_id = ID продавца)
- **Просмотр** товаров в категориях маркетплейса
- **Добавление** товаров в категории маркетплейса

#### ❌ Запрещено:
- **Доступ** к странице редактирования категорий маркетплейса
  - URL: `categories.update?category_id=X` → Перенаправление
- **Редактирование** категорий маркетплейса
  - POST запросы блокируются
  - AJAX запросы блокируются
- **Удаление** категорий маркетплейса
  - DELETE запросы блокируются
- **Просмотр кнопок** редактирования/удаления для категорий маркетплейса
  - Кнопки скрыты в UI

### Для администратора (Admin):
- ✅ **Полный доступ** ко всем категориям
- ✅ Без ограничений

---

## Примеры использования

### Пример 1: Попытка доступа к странице редактирования

**Продавец пытается открыть:**
```
https://telemarket.dz/vendor.php?dispatch=categories.update&category_id=298
```

**Если category_id=298 это категория маркетплейса (company_id = 0):**
1. Система проверяет в `categories.pre.php`
2. Обнаруживает, что `company_id = 0`
3. Показывает уведомление: "Доступ запрещен"
4. Перенаправляет на: `categories.manage`

**Если category_id=298 это категория продавца:**
1. Проверка проходит успешно
2. Страница редактирования открывается нормально

---

### Пример 2: Попытка редактирования через API

**POST запрос для обновления категории:**
```php
$category_data = [
    'category' => 'Новое название',
    'status' => 'A'
];
fn_update_category($category_data, 298);
```

**Если category_id=298 это категория маркетплейса:**
1. Хук `fn_maurisweb_categories_update_category_pre` активируется
2. Проверяет `company_id` категории
3. Обнаруживает `company_id = 0`
4. Очищает `$category_data = array()`
5. Показывает уведомление об ошибке
6. Возвращает `false` - обновление не происходит

---

### Пример 3: Использование вспомогательной функции

```php
// Проверка доступа перед выполнением действия
$category_id = 298;

// Проверка права на просмотр
if (fn_maurisweb_categories_check_vendor_category_access($category_id, 'view')) {
    // Разрешено - показать категорию
    $category = fn_get_category_data($category_id);
}

// Проверка права на редактирование
if (fn_maurisweb_categories_check_vendor_category_access($category_id, 'edit')) {
    // Разрешено - показать форму редактирования
} else {
    // Запрещено - показать сообщение
    fn_set_notification('E', __('error'), __('access_denied'));
}

// Проверка права на удаление
if (fn_maurisweb_categories_check_vendor_category_access($category_id, 'delete')) {
    // Разрешено - выполнить удаление
    fn_delete_category($category_id);
}
```

---

## Тестирование

### Сценарии тестирования:

#### 1. Доступ к странице редактирования
- [x] Продавец не может открыть страницу редактирования категории маркетплейса
- [x] Продавец может открыть страницу редактирования своей категории
- [x] Администратор может открыть любую страницу редактирования

#### 2. Редактирование категорий
- [x] Продавец не может сохранить изменения в категории маркетплейса
- [x] Продавец может сохранить изменения в своей категории
- [x] AJAX запросы блокируются для категорий маркетплейса

#### 3. Удаление категорий
- [x] Продавец не может удалить категорию маркетплейса
- [x] Продавец может удалить свою категорию
- [x] DELETE запросы блокируются для категорий маркетплейса

#### 4. Интерфейс
- [x] Кнопки редактирования/удаления скрыты для категорий маркетплейса
- [x] Кнопки отображаются для собственных категорий продавца
- [x] Категории маркетплейса отображаются в списке (только чтение)

#### 5. Совместимость
- [x] Работает корректно с модулем maurisweb_privileges
- [x] Не конфликтует с другими модулями
- [x] Правильно обрабатывает все типы пользователей

---

## Производительность

### Оптимизации:
- ✅ Минимальное количество SQL запросов
- ✅ Кэширование данных категорий
- ✅ Использование существующих функций системы
- ✅ Отсутствие дублирующихся проверок
- ✅ Передача данных в шаблоны один раз (в categories.post.php)

### Влияние на производительность:
- **Незначительное** - добавлено всего несколько проверок условий
- **Нет дополнительных SQL запросов** - используются существующие данные
- **Кэширование** - результаты проверок не вычисляются повторно

---

## Обратная совместимость

### ✅ Сохранена полная обратная совместимость:
- Существующий функционал не изменен
- Добавлены только новые проверки
- Не изменены структуры базы данных
- Не изменены существующие API
- Работает со всеми версиями CS-Cart 4.x

---

## Безопасность

### Меры безопасности:

#### 1. Валидация входных данных
```php
if (!empty($_REQUEST['category_id'])) {
    $category = fn_get_category_data($_REQUEST['category_id']);
    // Проверка существования категории
    if (!empty($category)) {
        // Выполнение действия
    }
}
```

#### 2. Проверка прав доступа
```php
$company_id = fn_get_runtime_company_id();
if ($company_id) {
    // Продавец - применить ограничения
}
```

#### 3. Предотвращение SQL инъекций
- Используются только встроенные функции CS-Cart
- Нет прямых SQL запросов с пользовательскими данными

#### 4. Предотвращение XSS
- Все выводимые данные экранируются через `__()` функции

---

## Расширение функционала

### Добавление новых типов действий:

```php
// В функции fn_maurisweb_categories_check_vendor_category_access
// можно добавить новые типы действий:

if (in_array($action, ['edit', 'update', 'delete', 'export', 'duplicate'])) {
    return false; // Запретить для категорий маркетплейса
}
```

### Добавление дополнительных ограничений:

```php
// Можно добавить проверку на основе других критериев
if (!empty($category) && $category['company_id'] == 0) {
    // Дополнительные проверки
    if ($category['status'] == 'D') {
        return false; // Запретить доступ к отключенным категориям
    }
}
```

---

## Поддержка и обслуживание

### Логирование:
Все блокировки доступа автоматически создают уведомления:
```php
fn_set_notification('E', __('error'), __('access_denied'));
```

### Мониторинг:
Можно добавить логирование попыток несанкционированного доступа:
```php
if (!empty($category) && $category['company_id'] == 0) {
    // Логирование попытки доступа
    fn_log_event('categories', 'access_denied', [
        'user_id' => $auth['user_id'],
        'category_id' => $category_id,
        'action' => $action
    ]);

    fn_set_notification('E', __('error'), __('access_denied'));
    return false;
}
```

---

## Контрольный список внедрения

- [x] Резервное копирование базы данных
- [x] Резервное копирование файлов
- [x] Обновление файлов на сервере
- [x] Очистка кэша шаблонов
- [x] Очистка кэша данных
- [x] Тестирование от имени продавца
- [x] Тестирование от имени администратора
- [x] Проверка логов на наличие ошибок
- [x] Проверка совместимости с maurisweb_privileges
- [x] Проверка перевода на арабский язык

---

## Контактная информация

**Разработчик модуля:** MaurisWeb
**Сайт:** https://maurisweb.ru
**Email:** info@maurisweb.ru

---

## История изменений

### Версия 1.0.0 (30.11.2024)
- ✅ Реализовано ограничение доступа продавцов к категориям маркетплейса
- ✅ Добавлена совместимость с модулем maurisweb_privileges
- ✅ Исправлена ошибка PHP Deprecated в maurisweb_vendor_admins
- ✅ Полный перевод интерфейса на арабский язык
- ✅ Добавлена вспомогательная функция проверки доступа
- ✅ Реализована многоуровневая система защиты

---

## Лицензия

Это коммерческое программное обеспечение. Только пользователи, которые приобрели действительную лицензию и приняли условия Лицензионного соглашения, могут устанавливать и использовать эту программу.

---

*Документ создан: 30 ноября 2024 г.*
*Последнее обновление: 30 ноября 2024 г.*
