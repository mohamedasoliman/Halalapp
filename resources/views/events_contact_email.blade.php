<!DOCTYPE html>
<html>
<head>
    <title>Contact Message</title>
</head>
<body>
    <p><strong>Subject:</strong> {{ $request->subject }}</p>
    <p><strong>Event Name:</strong> {{ $request->eventName }}</p>
    <p><strong>Email:</strong> {{ $request->email }}</p>
    <p><strong>Contact:</strong> {{ $request->contact }}</p>
    <p><strong>Date:</strong> {{ $request->date }}</p>
    <p><strong>time:</strong> {{ $request->time }}</p>
    <p><strong>address:</strong> {{ $request->address }}</p>
    <p><strong>link:</strong> {{ $request->link }}</p>
    <p><strong>Description:</strong></p>
    <div>{!! nl2br(e($request->description ?? '')) !!}</div>
    <p><strong>Submission UUID:</strong> {{ $request->submission_uuid ?? '' }}</p>
    <p><strong>Submission Context:</strong> {{ $request->context_type ?? '' }} {{ $request->context_id ?? '' }}</p>
    <p><strong>App:</strong> {{ $request->platform ?? '' }} {{ $request->app_version ?? '' }} ({{ $request->app_build ?? '' }})</p>
</body>
</html>
