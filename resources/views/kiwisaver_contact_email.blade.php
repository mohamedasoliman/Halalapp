<!DOCTYPE html>
<html>
<head>
    <title>KiwiSaver Enquiry</title>
</head>
<body>
    <p><strong>First Name:</strong> {{ $request->first_name }}</p>
    <p><strong>Last Name:</strong> {{ $request->last_name }}</p>
    <p><strong>Email:</strong> {{ $request->email }}</p>
    <p><strong>How did you hear about us?</strong> {{ $request->heard_about }}</p>
</body>
</html>


