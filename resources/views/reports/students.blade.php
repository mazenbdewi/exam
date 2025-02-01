<!DOCTYPE html>
<html>

<head>
    <title>تقرير الطلاب</title>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة ضريبية</title>
    <style>
    @font-face {
        font-family: 'Amiri';
        src: url('{{ storage_path('fonts/Amiri.ttf') }}');
    }


    body {
        font-family: 'DejaVuSans', sans-serif;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        border: 1px solid #000;
        padding: 8px;
        text-align: center;
    }
    </style>
</head>

<body>
    <h1>تقرير توزيع الطلاب على القاعات</h1>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>الرقم</th>
                <th>الاسم الكامل</th>
                <th>اسم الأب</th>
                <th>القاعة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $student->number }}</td>
                <td>{{ $student->full_name }}</td>
                <td>{{ $student->father_name }}</td>
                <td>{{ $student->room->room_name ?? 'غير معين' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>