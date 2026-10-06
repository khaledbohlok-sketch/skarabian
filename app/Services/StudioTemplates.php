<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Default wording for SK Arabian Studio documents (EN/AR). The Owner edits them in Studio > Templates.
 * {{placeholders}} are filled from the linked record; {{table}} is replaced by the document's data table.
 * NOTE: replace these with the exact wording of the Owner's existing Studio templates before go-live.
 */
final class StudioTemplates
{
    public static function defaults(): array
    {
        return [
            'financial_report' => [
                'en' => ['Financial Report', '<p>Financial report for the period <strong>{{period_from}}</strong> to <strong>{{period_to}}</strong>. All amounts in Qatari Riyals (QAR). Approved/paid and pending amounts are shown separately.</p>{{table}}'],
                'ar' => ['تقرير مالي', '<p>تقرير مالي للفترة من <strong>{{period_from}}</strong> إلى <strong>{{period_to}}</strong>. جميع المبالغ بالريال القطري. تظهر المبالغ المعتمدة/المدفوعة والمعلقة بشكل منفصل.</p>{{table}}'],
            ],
            'diet_log' => [
                'en' => ['Horse Diet Log', '<p>Horse: <strong>{{horse.name}}</strong> &nbsp; Reg. No.: {{horse.registration_no}} &nbsp; Location: {{horse.location}}</p>{{table}}<p>Prepared by: {{issued_by}}</p>'],
                'ar' => ['سجل تغذية الخيل', '<p>الخيل: <strong>{{horse.name}}</strong> &nbsp; رقم التسجيل: {{horse.registration_no}} &nbsp; الموقع: {{horse.location}}</p>{{table}}<p>أعدّه: {{issued_by}}</p>'],
            ],
            'purchase_order' => [
                'en' => ['Purchase Order', '<p><strong>PO No.:</strong> {{po.number}} &nbsp; <strong>Date:</strong> {{po.date}}</p><p><strong>Supplier:</strong> {{supplier.name}}<br>{{supplier.contact}}</p>{{table}}<p>Please deliver the above items to SK Arabians, Doha. Payment as agreed upon delivery and receipt of a valid invoice.</p><p class="sign">Authorised signature</p>'],
                'ar' => ['أمر شراء', '<p><strong>رقم الأمر:</strong> {{po.number}} &nbsp; <strong>التاريخ:</strong> {{po.date}}</p><p><strong>المورد:</strong> {{supplier.name}}<br>{{supplier.contact}}</p>{{table}}<p>يرجى توريد المواد أعلاه إلى اس كي ارابيان، الدوحة. يتم الدفع حسب الاتفاق عند الاستلام وتقديم فاتورة صحيحة.</p><p class="sign">التوقيع المعتمد</p>'],
            ],
            'salary_certificate' => [
                'en' => ['Salary Certificate', '<p><strong>To Whom It May Concern</strong></p><p>This is to certify that <strong>{{employee.name}}</strong>, {{employee.nationality}} national, holder of Qatar ID No. <strong>{{employee.qid}}</strong> and Passport No. <strong>{{employee.passport}}</strong>, is employed with SK Arabians as <strong>{{employee.position}}</strong> since <strong>{{employee.hire_date}}</strong>.</p><p>The employee receives a monthly salary as follows:</p>{{table}}<p>This certificate is issued at the request of the employee without any liability on the company.</p><p class="sign">For SK Arabians<br><br>Authorised signature</p>'],
                'ar' => ['شهادة راتب', '<p><strong>إلى من يهمه الأمر</strong></p><p>نشهد بأن السيد/ة <strong>{{employee.name}}</strong>، الجنسية {{employee.nationality}}، حامل البطاقة الشخصية القطرية رقم <strong>{{employee.qid}}</strong> وجواز السفر رقم <strong>{{employee.passport}}</strong>، يعمل لدى اس كي ارابيان بوظيفة <strong>{{employee.position}}</strong> منذ تاريخ <strong>{{employee.hire_date}}</strong>.</p><p>ويتقاضى راتباً شهرياً على النحو التالي:</p>{{table}}<p>أعطيت هذه الشهادة بناءً على طلب الموظف دون أدنى مسؤولية على الشركة.</p><p class="sign">عن اس كي ارابيان<br><br>التوقيع المعتمد</p>'],
            ],
            'offer_letter' => [
                'en' => ['Employment Offer Letter', '<p>Dear <strong>{{employee.name}}</strong>,</p><p>We are pleased to offer you the position of <strong>{{employee.position}}</strong> in the {{employee.department}} department at SK Arabians, starting on <strong>{{start_date}}</strong>.</p>{{table}}<p>Working hours, leave and other benefits are in accordance with the Qatar Labour Law and company policy. This offer is subject to a probation period of {{probation_months}} months and to obtaining the required residency and work permits.</p><p>Please sign below to confirm your acceptance.</p><p class="sign">For SK Arabians &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Accepted by employee</p>'],
                'ar' => ['خطاب عرض عمل', '<p>السيد/ة <strong>{{employee.name}}</strong> المحترم/ة،</p><p>يسرنا أن نعرض عليكم وظيفة <strong>{{employee.position}}</strong> في قسم {{employee.department}} لدى اس كي ارابيان، اعتباراً من تاريخ <strong>{{start_date}}</strong>.</p>{{table}}<p>تكون ساعات العمل والإجازات وسائر المزايا وفقاً لقانون العمل القطري وسياسة الشركة. يخضع هذا العرض لفترة تجربة مدتها {{probation_months}} أشهر وللحصول على تصاريح الإقامة والعمل اللازمة.</p><p>يرجى التوقيع أدناه لتأكيد القبول.</p><p class="sign">عن اس كي ارابيان &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; توقيع الموظف بالقبول</p>'],
            ],
            'experience_letter' => [
                'en' => ['Experience Letter', '<p><strong>To Whom It May Concern</strong></p><p>This is to certify that <strong>{{employee.name}}</strong>, {{employee.nationality}} national, Passport No. <strong>{{employee.passport}}</strong>, worked with SK Arabians as <strong>{{employee.position}}</strong> from <strong>{{employee.hire_date}}</strong> to <strong>{{employee.end_date}}</strong>.</p><p>During this period the employee showed dedication, good conduct and professional care of our horses. We wish them every success in the future.</p><p class="sign">For SK Arabians<br><br>Authorised signature</p>'],
                'ar' => ['شهادة خبرة', '<p><strong>إلى من يهمه الأمر</strong></p><p>نشهد بأن السيد/ة <strong>{{employee.name}}</strong>، الجنسية {{employee.nationality}}، جواز سفر رقم <strong>{{employee.passport}}</strong>، قد عمل لدى اس كي ارابيان بوظيفة <strong>{{employee.position}}</strong> من تاريخ <strong>{{employee.hire_date}}</strong> حتى تاريخ <strong>{{employee.end_date}}</strong>.</p><p>وقد أبدى خلال فترة عمله تفانياً وحسن سلوك ورعاية مهنية لخيولنا، ونتمنى له التوفيق.</p><p class="sign">عن اس كي ارابيان<br><br>التوقيع المعتمد</p>'],
            ],
            'payslip' => [
                'en' => ['Payslip', '<p><strong>Employee:</strong> {{employee.name}} &nbsp; <strong>Position:</strong> {{employee.position}} &nbsp; <strong>Period:</strong> {{period}}</p>{{table}}<p>Paid to: {{employee.bank}}</p>'],
                'ar' => ['قسيمة راتب', '<p><strong>الموظف:</strong> {{employee.name}} &nbsp; <strong>الوظيفة:</strong> {{employee.position}} &nbsp; <strong>الفترة:</strong> {{period}}</p>{{table}}<p>يُدفع إلى: {{employee.bank}}</p>'],
            ],
            'invoice' => [
                'en' => ['Invoice', '<p><strong>Invoice No.:</strong> {{invoice.number}} &nbsp; <strong>Date:</strong> {{invoice.date}} &nbsp; <strong>Due:</strong> {{invoice.due_date}}</p><p><strong>Bill to:</strong> {{client.name}}<br>{{client.contact}}</p>{{table}}<p>Please make payment to SK Arabians quoting the invoice number.</p>'],
                'ar' => ['فاتورة', '<p><strong>رقم الفاتورة:</strong> {{invoice.number}} &nbsp; <strong>التاريخ:</strong> {{invoice.date}} &nbsp; <strong>الاستحقاق:</strong> {{invoice.due_date}}</p><p><strong>إلى:</strong> {{client.name}}<br>{{client.contact}}</p>{{table}}<p>يرجى السداد إلى اس كي ارابيان مع ذكر رقم الفاتورة.</p>'],
            ],
            'receipt' => [
                'en' => ['Payment Receipt', '<p>Received with thanks from <strong>{{party.name}}</strong> the sum of <strong>{{payment.amount}}</strong> against {{bill.number}} {{bill.description}}.</p>{{table}}<p class="sign">Received by</p>'],
                'ar' => ['سند قبض', '<p>استلمنا من <strong>{{party.name}}</strong> مبلغ <strong>{{payment.amount}}</strong> مقابل {{bill.number}} {{bill.description}}.</p>{{table}}<p class="sign">المستلم</p>'],
            ],
            'ownership_transfer' => [
                'en' => ['Horse Ownership Transfer Certificate', '<p>This certifies that ownership of the horse described below has been transferred from <strong>{{from.name}}</strong> to <strong>{{to.name}}</strong> on <strong>{{transfer.date}}</strong>.</p>{{table}}<p>All rights to the horse pass to the new owner from the transfer date.</p><p class="sign">For SK Arabians &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; New owner</p>'],
                'ar' => ['شهادة نقل ملكية خيل', '<p>نشهد بأن ملكية الخيل الموضح أدناه قد انتقلت من <strong>{{from.name}}</strong> إلى <strong>{{to.name}}</strong> بتاريخ <strong>{{transfer.date}}</strong>.</p>{{table}}<p>تنتقل جميع الحقوق في الخيل إلى المالك الجديد اعتباراً من تاريخ النقل.</p><p class="sign">عن اس كي ارابيان &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; المالك الجديد</p>'],
            ],
            'embryo_transfer' => [
                'en' => ['Embryo Transfer Record', '<p>Embryo <strong>{{embryo.code}}</strong> {{embryo.name}}</p>{{table}}<p class="sign">Veterinarian</p>'],
                'ar' => ['سجل نقل الأجنة', '<p>الجنين <strong>{{embryo.code}}</strong> {{embryo.name}}</p>{{table}}<p class="sign">الطبيب البيطري</p>'],
            ],
            'leave_approval' => [
                'en' => ['Leave Approval', '<p>Dear <strong>{{employee.name}}</strong>,</p><p>Your request for <strong>{{leave.type}}</strong> leave of <strong>{{leave.days}}</strong> day(s) from <strong>{{leave.start}}</strong> to <strong>{{leave.end}}</strong> has been approved. Your remaining leave balance is {{leave.balance}} day(s).</p><p class="sign">For SK Arabians</p>'],
                'ar' => ['الموافقة على إجازة', '<p>السيد/ة <strong>{{employee.name}}</strong>،</p><p>تمت الموافقة على طلبكم لإجازة <strong>{{leave.type}}</strong> لمدة <strong>{{leave.days}}</strong> يوم من تاريخ <strong>{{leave.start}}</strong> إلى <strong>{{leave.end}}</strong>. رصيد الإجازات المتبقي {{leave.balance}} يوم.</p><p class="sign">عن اس كي ارابيان</p>'],
            ],
            'custom_letter' => [
                'en' => ['Letter', '<p>{{to}}</p><p><strong>Subject: {{subject}}</strong></p>{{body}}<p class="sign">For SK Arabians</p>'],
                'ar' => ['خطاب', '<p>{{to}}</p><p><strong>الموضوع: {{subject}}</strong></p>{{body}}<p class="sign">عن اس كي ارابيان</p>'],
            ],
        ];
    }

    public static function seed(): void
    {
        foreach (self::defaults() as $type => $langs) {
            foreach ($langs as $lang => [$title, $body]) {
                DB::run(
                    'INSERT IGNORE INTO studio_templates (doc_type, lang, title, body, letterhead_version) VALUES (?, ?, ?, ?, ?)',
                    [$type, $lang, $title, $body, $lang === 'ar' ? 2 : 1]
                );
            }
        }
    }

    public static function get(string $type, string $lang): array
    {
        $t = DB::row('SELECT * FROM studio_templates WHERE doc_type = ? AND lang = ?', [$type, $lang]);
        if (!$t) {
            [$title, $body] = self::defaults()[$type][$lang] ?? ['Document', '{{body}}'];
            $t = ['doc_type' => $type, 'lang' => $lang, 'title' => $title, 'body' => $body, 'letterhead_version' => 1];
        }
        return $t;
    }
}
