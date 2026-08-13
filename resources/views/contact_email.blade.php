<!DOCTYPE html>
<html>
<head>
    <title>Contact Message</title>
</head>
<body>
    <p><strong>Reference:</strong> {{ $ticket->reference }}</p>
    <p><strong>Category:</strong> {{ $ticket->category }}</p>
    <p><strong>Subject:</strong> {{ $ticket->subject }}</p>
    <p><strong>Name:</strong> {{ $ticket->requester_name }}</p>
    <p><strong>Email:</strong> {{ $ticket->requester_email }}</p>
    <p><strong>Message:</strong></p>
    <div>{!! nl2br(e($supportMessage->body)) !!}</div>
    <p><strong>Private attachments awaiting admin safety review:</strong> {{ $supportMessage->attachments()->where('security_status', '!=', 'safe')->count() }}</p>
</body>
</html>
