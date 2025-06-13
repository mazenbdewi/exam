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

        // تعيين كلمة مرور مشفرة (من رقم الهاتف)
        $user = User::create([
            'name' => $filteredRow['name'],
            'email' => $filteredRow['email'], // سيحتوي على رقم الهاتف
            'password' => Hash::make($filteredRow['email']), // استخدام رقم الهاتف ككلمة سر
            'max_observers' => $filteredRow['max_observers'],
            'month_part' => $filteredRow['month_part'] ?? 'both', // القيمة الافتراضية 'both'
        ]);

        $user->syncRoles([]);

        // ثم أعطه الدور المحدد من الإكسل فقط
        if ($role = Role::where('name', $filteredRow['role'])->first()) {
            $user->assignRole($role);

            return $user;
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|regex:/^\+?[0-9]{7,15}$/', // تحقق من صيغة رقم الهاتف
            'role' => 'required|string|exists:roles,name',
            'max_observers' => 'required|integer',
            'month_part' => 'sometimes|in:first_half,second_half,both,any', // تحقق من القيم المسموحة
        ];
    }

    public function customValidationMessages()
    {
        return [
            'name.required' => 'حقل الاسم مطلوب',
            'email.required' => 'حقل رقم الهاتف مطلوب',
            'email.regex' => 'يجب إدخال رقم هاتف صحيح (7-15 رقم)',
            'role.required' => 'حقل الدور مطلوب',
            'role.exists' => 'الدور المحدد غير موجود',
            'max_observers.required' => 'حقل عدد المراقبين مطلوب',
            'max_observers.integer' => 'يجب أن يكون عدداً صحيحاً',
            'month_part.in' => 'قيمة نصف الشهر غير صالحة (يجب أن تكون: first_half, second_half, both, any)',
        ];
    }
}
