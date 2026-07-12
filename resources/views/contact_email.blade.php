<!DOCTYPE html>
<html>
<head>
    <title>Contact Message</title>
</head>
<body>
    <p><strong>Subject:</strong> {{ $request->subject }}</p>
    <p><strong>Name:</strong> {{ $request->name }}</p>
    <p><strong>Email:</strong> {{ $request->email }}</p>
    <p><strong>Message:</strong></p>
    <div>{!! nl2br(e($request->body)) !!}</div>
</body>
</html>
