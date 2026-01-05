<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject ?? 'Email' }}</title>
</head>
<body>
    <h2>Hello, {{ $name ?? 'User' }}</h2>
    <p>{{ $message ?? '' }}</p>
    <p>Regards,<br>CPCL Team</p>
</body>
</html>
