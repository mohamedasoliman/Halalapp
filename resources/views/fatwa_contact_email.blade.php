<!DOCTYPE html>
<html>
<head>
    <title>Contact Message</title>
</head>
<body>
    <p><strong>Subject:</strong> {{ $request->subject }}</p>
    <p><strong>Name:</strong> {{ $request->name }}</p>
    <p><strong>Email:</strong> {{ $request->email }}</p>
    <p><strong>Category:</strong> {{ $request->category }}</p>
    <p><strong>Date:</strong> {{ $request->date }}</p>
    <p><strong>Message:</strong> {{ $request->body }}</p>
</body>
</html>
