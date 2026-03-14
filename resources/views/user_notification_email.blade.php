Salam,

@if($notificationType === 'contacted')
Thank you for your request regarding {{ $productName }} ({{ $barcode }}).

We have contacted the manufacturer for confirmation and will update the app as soon as we hear back.
@elseif($notificationType === 'resolved')
We have received confirmation regarding {{ $productName }} ({{ $barcode }}).

@if($halalStatus === '0')
This product has been confirmed as Halal and has been updated in the app.
@else
This product has been confirmed as Not Halal and has been updated in the app.
@endif
@endif

JazakAllah Khair,
Halal Kiwi Team
