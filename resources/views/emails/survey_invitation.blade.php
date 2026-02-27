<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Survey Invitation</title>
</head>
<body>
    <p>Hello,</p>
    <p>You have been invited to participate in the survey titled <strong>{{ $survey->title }}</strong>.</p>
    <p>Click the link below to start:</p>
    <p><a href="{{ $frontendUrl }}">{{ $frontendUrl }}</a></p>
    <p>If the button above does not work, copy and paste the URL into your browser.</p>
    <p>Thank you!</p>
</body>
</html>
