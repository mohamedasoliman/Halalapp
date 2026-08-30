Salam,
<br><br>
@if($notificationType === 'contacted')
Thank you for your request regarding {{ $productName }} ({{ $barcode }}).
<br><br>
We have contacted the manufacturer for confirmation and will update the app as soon as we hear back.
@elseif(in_array($notificationType, ['information_request', 'photo_request'], true))
Thank you for your request regarding {{ $productName }} ({{ $barcode }}).
@if($replyReference)
<br><br>
Please keep this reference in your reply: [{{ $replyReference }}]
@endif
<br><br>
We need a little more information before we can identify and review the exact product. Please reply to this email with clear photos of:
<br><br>
1. The front of the product packaging, showing the product name, brand, flavour and size.<br>
2. The complete ingredients, allergen and manufacturer information on the back or side of the packaging.<br>
3. The barcode, with all digits clearly visible.
<br><br>
If the information appears across several panels, please include a clear photo of each panel. Once received, we can continue reviewing your request.
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
