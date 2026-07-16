Hi,

@if($kind === 'follow_up')
I am following up on our earlier halal suitability enquiry for the products below.
@else
I am writing to enquire about the halal suitability of the following product(s):
@endif

Reference: {{ $reference }}

@foreach($products as $product)
- {{ $product['name'] }} ({{ $product['barcode'] }})
@endforeach

For each product, I would appreciate confirmation of the following:

1. Any animal-derived ingredients or processing aids, including gelatin, animal rennet, lard, meat derivatives, or E120 (carmine), and their source.
2. Any alcohol, ethanol, alcohol-derived ingredient, extraction solvent, or alcohol used as a flavour carrier.
3. Whether the product is halal certified. If so, please provide the certifying body, certificate number, expiry date, and the products or facilities covered.
4. Whether it is made on equipment shared with non-halal products and, if so, what cleaning controls are used between runs.
5. Whether your response applies only to the products listed above or to a wider product range.

Ingredient specifications, certificates, or other supporting documents would be very helpful. This information helps us provide accurate guidance to consumers using Halal Kiwi.

Thank you for your time and assistance. Please let me know if you require any further information.

Kind regards,
Soliman
{{ config('outreach.reply_to') }}
halalkiwi.com
