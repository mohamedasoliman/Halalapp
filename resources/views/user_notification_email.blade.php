Salam,
<br><br>
@if($notificationType === 'contacted')
Thank you for your request regarding {{ $productName }} ({{ $barcode }}).
<br><br>
We have contacted the manufacturer for confirmation and will update the app as soon as we hear back.
@elseif($notificationType === 'resolved')
Halal Kiwi has completed its review of {{ $productName }} ({{ $barcode }}).
<br><br>
@if($halalStatus === '0')
Based on the evidence reviewed by Halal Kiwi, this product is classified as <strong>Halal</strong> and has been updated in the app.
@else
Based on the evidence reviewed by Halal Kiwi, this product is classified as <strong>Not Halal</strong> and has been updated in the app.
@endif
@endif
<br><br>
JazakAllah Khair,
<br>
Halal Kiwi Team
