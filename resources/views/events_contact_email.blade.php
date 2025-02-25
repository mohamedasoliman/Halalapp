<!DOCTYPE html>
<html>
<head>
    <title>Contact Message</title>
</head>
<body>
    <p><strong>Subject:</strong> {{ $request->subject }}</p>
    <p><strong>Event Name:</strong> {{ $request->eventName }}</p>
    <p><strong>Email:</strong> {{ $request->email }}</p>
    <p><strong>Contact:</strong> {{ $request->email }}</p>
    <p><strong>Date:</strong> {{ $request->date }}</p>
    <p><strong>time:</strong> {{ $request->time }}</p>
    <p><strong>address:</strong> {{ $request->address }}</p>
    <p><strong>link:</strong> {{ $request->link }}</p>
</body>
</html>
