<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Spatie\Permission\Models\Role;

class UsersImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // تنقية البيانات من القيم الفارغة
        $filteredRow = array_filter($row, function ($value) {
            return $value !== null;
        });

        // إذا لم يكن هناك بيانات كافية، تخطى الصف
        if (count($filteredRow) < 4) {
            return null;
        }

        // تحويل نوع المراقب من العربية إلى الإنجليزية
        $observerType = $this->translateObserverType($filteredRow['observer_type'] ?? 'أساسي');

        // تحويل مستوى المراقبة إلى رقم
        $monitoringLevel = $this->translateMonitoringLevel($filteredRow['monitoring_level'] ?? 'مراقبة كاملة');

        // إنشاء المستخدم مع الحقول الجديدة
        $user = User::create([
            'name' => $filteredRow['name'],
            'email' => $filteredRow['email'], // رقم الهاتف
            'password' => Hash::make($filteredRow['email']), // استخدام رقم الهاتف ككلمة سر
            'month_part' => $filteredRow['month_part'] ?? 'any', // القيمة الافتراضية 'any'
            'observer_type' => $observerType,
            'monitoring_level' => $monitoringLevel,
        ]);

        $user->syncRoles([]);

        // تعيين الدور المحدد من الإكسل
        if ($role = Role::where('name', $filteredRow['role'])->first()) {
            $user->assignRole($role);
        }

        return $user;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required', // تحقق من صيغة رقم الهاتف
            'role' => 'required|string|exists:roles,name',
            'month_part' => 'sometimes|in:first_half,second_half,any',
            'observer_type' => 'required|in:أساسي,ثانوي,احتياط,primary,secondary,reserve',
            'monitoring_level' => 'required|in:0,1,2,3,لا يراقب,مراقبة كاملة,نصف مراقبة,ربع مراقبة',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => 'حقل الاسم مطلوب',
            'email.required' => 'حقل رقم الهاتف مطلوب',
            'email.regex' => 'يجب إدخال الرقم الوظني الصحيح',
            'role.required' => 'حقل الدور مطلوب',
            'role.exists' => 'الدور المحدد غير موجود',
            'month_part.in' => 'قيمة نصف الشهر غير صالحة (يجب أن تكون: first_half, second_half, any)',
            'observer_type.required' => 'نوع المراقب مطلوب',
            'observer_type.in' => 'نوع المراقب غير صالح (القيم المسموحة: أساسي، ثانوي، احتياط)',
            'monitoring_level.required' => 'مستوى المراقبة مطلوب',
            'monitoring_level.in' => 'مستوى المراقبة غير صالح (القيم المسموحة: 0,1,2,3 أو: لا يراقب، مراقبة كاملة، نصف مراقبة، ربع مراقبة)',
        ];
    }

    /**
     * تحويل نوع المراقب من العربية إلى الإنجليزية
     */
    private function translateObserverType(string $type): string
    {
        return match (trim($type)) {
            'أساسي', 'primary' => 'primary',
            'ثانوي', 'secondary' => 'secondary',
            'احتياط', 'reserve' => 'reserve',
            default => 'primary'
        };
    }

    /**
     * تحويل مستوى المراقبة إلى رقم
     */
    private function translateMonitoringLevel($level): int
    {
        if (is_numeric($level)) {
            return (int) $level;
        }

        return match (trim($level)) {
            'مراقبة كاملة', 'كاملة' => 1,
            'نصف مراقبة', 'نصف' => 2,
            'ربع مراقبة', 'ربع' => 3,
            'لا يراقب', 'لا' => 0,
            default => 1
        };
    }
}
