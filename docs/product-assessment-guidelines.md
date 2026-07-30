# Halal Kiwi Product Assessment Guidelines

**Document status:** Internal draft for scholar and owner review
**Version:** 1.0-draft
**Scope:** Packaged food and drink products listed by Halal Kiwi

## 1. Purpose

These guidelines define how Halal Kiwi assigns and explains product statuses. They are an
operational assessment standard, not a fatwa and not a substitute for a halal certificate.

The goals are to:

- apply the same rules to every product;
- distinguish missing information from an actual halal concern;
- preserve the evidence behind every decision;
- show users short, useful explanations without exposing internal audit details; and
- make uncertain cases easy to refer to a qualified scholar.

## 2. Accepted Authorities

Halal Kiwi follows:

1. clear rulings and halal standards from JAKIM;
2. resolutions of the International Islamic Fiqh Academy in Jeddah (IIFA);
3. guidance from Halal Kiwi's qualified New Zealand scholar; and
4. the stricter credible opinion when the approved authorities do not address an issue,
   until the scholar confirms a different approach.

JAKIM's halal certification procedure and management system are formal reference
frameworks for halal control. Halal Kiwi using these sources does not mean that Halal Kiwi
or an individual product is JAKIM certified.

References:

- [JAKIM Annual Report describing MPPHM 2020 and MHMS 2020](https://www.islam.gov.my/uploads/LAPORAN-TAHUNAN-JAKIM-2020-latest.pdf)
- [IIFA Resolution 225 on halal standards questions](https://iifa-aifi.org/en/6233.html)

## 3. Product Statuses

| Database value | Public status | Meaning |
|---|---|---|
| `0` | Halal | Available evidence satisfies every applicable Halal Kiwi rule. |
| `1` | Not Halal | Evidence establishes a prohibited ingredient, source or process under these rules. |
| `2` | Under Review | The product has not been assessed sufficiently. This is not an adverse verdict. |
| `3` | Mashbooh | The product was investigated and a credible, significant concern remains unresolved. |

### Under Review versus Mashbooh

- Use **Under Review** when research has not been completed or there is no meaningful
  evidence yet.
- Use **Mashbooh** only after investigation identifies a real concern that cannot yet be
  resolved.
- A missing halal certificate by itself remains **Under Review**, not Mashbooh and not
  Not Halal.
- Mashbooh is not a final verdict. It must not resolve a prioritisation request or send a
  final Halal/Not Halal notification.

## 4. Evidence Hierarchy

Use the strongest current evidence available:

1. A valid, product-specific halal certificate covering the exact product, manufacturing
   site and market.
2. A written manufacturer response covering the exact barcode.
3. A written manufacturer statement covering an explicitly named range, site or brand.
4. An official specification, ingredients statement and manufacturing-process response.
5. Current product packaging or an official retailer/manufacturer page for product identity.
6. Third-party barcode databases and ordinary retailer listings as identity leads only.

Rules for applying evidence:

- Product-specific evidence is stronger than a general brand statement.
- Current evidence is stronger than superseded evidence.
- Never transfer a verdict between similar products or barcodes.
- A brand-wide conclusion may be used only when the company explicitly confirms that scope.
- A retailer listing or Open Food Facts record may identify a product but cannot establish
  halal suitability by itself.
- If reliable sources conflict, keep the product Mashbooh while clarification is obtained.

## 5. Assessment Sequence

Assess each exact barcode in this order:

1. Confirm the product identity, barcode, brand and current formulation.
2. Check for a valid halal certificate and confirm its scope.
3. Review ingredients for prohibited or uncertain substances.
4. Review animal-derived ingredients and slaughter requirements.
5. Review alcohol, flavour carriers and processing aids.
6. Review manufacturing lines, cleaning and cross-contamination controls.
7. Apply the evidence hierarchy and assign one status.
8. Save the full evidence privately.
9. Write a short user-facing note only when it adds useful information.

## 6. Certification

- A valid certificate covering the exact product and relevant manufacturing site supports
  Halal status.
- Current FIANZ and NZIDT certificates are accepted directly when the certificate is
  authenticated and covers the exact product, manufacturing site and relevant scope.
- JAKIM certification is accepted when it is current, authenticated and covers the exact
  product and manufacturing site.
- Certification by an overseas body on JAKIM's current recognised list is accepted only
  after verifying that body's current recognition and the certificate's exact scope.
- Certification bodies that are not currently recognised by JAKIM, or whose recognition
  cannot be verified, require manual review.
- Record the certifier, scope and expiry in the private evidence record.
- Do not put certificate numbers, dates or proof locations in the public note.
- "Not halal certified" does not mean Not Halal for a non-meat product.
- A halal logo or certification claim that cannot be authenticated requires clarification.
- An expired certificate does not automatically prove a product is Not Halal. Re-check the
  product and keep it Under Review or Mashbooh according to the available evidence.
- Certification never overrides a stricter ingredient, slaughter or manufacturing rule in
  this document.
- HCS certification requires manual review, especially for meat and poultry. Confirm the
  actual slaughter process rather than accepting the certificate as a complete verdict.

## 7. Meat, Poultry and Animal-Derived Ingredients

- Meat and poultry require explicit evidence of halal species and halal slaughter.
- If halal slaughter cannot be confirmed, classify the meat product as Not Halal.
- Mechanically slaughtered poultry is Not Halal when each bird is not individually
  confirmed alive at the time of slaughter.
- Scholars differ on mechanical slaughter. A short public note may advise users to ask a
  trusted local scholar if they follow a different opinion.
- Pork, lard, blood and ingredients derived from prohibited animals are Not Halal.
- Gelatine, animal rennet, animal fat, stock, enzymes and similar animal-derived ingredients
  require a confirmed halal source.
- Fish and other animals that do not require slaughter are still assessed for added
  ingredients and manufacturing concerns.
- Microbial, plant or synthetic alternatives are acceptable when their source and process
  introduce no other prohibited substance.

## 8. Vegetarian and Vegan Products

- Vegetarian or vegan labelling does not automatically prove a product is halal.
- Review the exact ingredients and available manufacturer evidence. If carmine/E120, wine
  or alcohol, an uncertain animal source, or another credible concern appears, ask a
  precise follow-up rather than treating a vegetarian-only statement as a halal answer.
- The normal rules for carmine, nutmeg, alcohol, flavour carriers and shared production
  equipment still apply.

## 9. Standing Ingredient Rules

### Carmine / cochineal / E120

Halal Kiwi classifies carmine, cochineal and E120 as **Not Halal**.

### Nutmeg

- A normal seasoning amount of nutmeg used as one ingredient in a mixed food does not
  prevent Halal status when every other ingredient and process is acceptable.
- Pure nutmeg, products in which nutmeg is a main ingredient, and concentrated nutmeg
  extracts, oils or supplements are Not Halal.
- If the amount or purpose remains unclear after investigation, classify the product as
  Mashbooh. If it has not yet been investigated, keep it Under Review.

This operating rule follows the distinction between a small flavouring amount and a large
or harmful amount described by the
[General Iftaa Department of Jordan](https://aliftaa.jo/QuestionPrintEn.aspx?QuestionId=2872).

### Gelatine and animal rennet

- A confirmed halal-certified animal source supports Halal status.
- A confirmed pork or non-halal animal source is Not Halal.
- A credible but unresolved source after investigation is Mashbooh.
- If the product has not yet been investigated, keep it Under Review.

### Wine and alcoholic beverages

- Wine, beer, sake, liqueur and other intoxicating drinks used as ingredients are Not Halal.
- Cooking or evaporation does not make an intentionally added alcoholic drink acceptable.
- Vinegar is assessed as vinegar, not automatically as wine, provided it is a finished
  non-intoxicating vinegar product.

## 10. Ethanol and Alcohol Carriers

### Agreed rules

- An intoxicating alcoholic drink used as an ingredient is Not Halal.
- Naturally occurring trace ethanol that is non-intoxicating is not treated as an
  intoxicating drink.
- Ethanol used only for equipment cleaning is not automatically a product ingredient;
  cleaning and removal controls must be confirmed where relevant.

IIFA Resolution 225 distinguishes wine from ethanol and permits non-intoxicating traces
from natural fermentation. It also permits ethanol as a processor or solvent when no halal
alternative is available and the amount does not cause intoxication.

### Halal Kiwi operating rule

- **Halal:** no practical halal alternative is available and the manufacturer confirms that
  ethanol is only a processing aid, solvent or flavour carrier, is not sourced from an
  alcoholic drink, and the consumed product cannot intoxicate.
- **Not Halal:** wine or another alcoholic drink is intentionally added, or the product is
  itself intoxicating.
- **Mashbooh:** ethanol is confirmed but its source, purpose or residual amount is unclear
  after investigation.
- **Under Review:** there is only a vague flavouring listing and no investigation has yet
  established an alcohol concern.

If a manufacturer says only that a product is "not halal suitable due to ethanol", request
the ethanol's source, purpose and residual amount before deciding whether the evidence
establishes Not Halal or Mashbooh.

## 11. Flavourings, Enzymes and Processing Aids

- Do not assume that the word "flavouring" means alcohol or an animal source.
- Ask the manufacturer about animal derivatives, carmine, alcohol carriers and processing
  aids when a formulation is ambiguous.
- A confirmed prohibited source is Not Halal.
- A credible unresolved source after investigation is Mashbooh.
- No investigation or evidence remains Under Review.

## 12. Shared Lines and Cross-Contamination

Halal Kiwi accepts shared production equipment when ordinary cleaning or sanitation
between runs is confirmed:

- Manufacturer confirmation that the shared equipment is cleaned or sanitised between
  runs supports Halal status when the product and its ingredients are otherwise acceptable.
- Halal-specific, independently validated, or unusually extensive cleaning is not required.
- If shared equipment is confirmed but cleaning between runs is absent or unconfirmed, ask
  for clarification. Do not assume that sharing equipment by itself makes the product Not
  Halal.
- A separate halal line or a certified halal site is strong supporting evidence.
- Never extend one site's controls to another manufacturing site without explicit evidence.

## 13. Manufacturer Statements

- Record the sender, company, exact products/barcodes, manufacturing site and scope.
- An exact-product manufacturer statement that a product is halal suitable supports Halal
  status even if every questionnaire item was not answered separately, unless reliable
  evidence for that exact product directly establishes a conflicting prohibited ingredient
  or process.
- "Not halal certified" alone is not a Not Halal verdict.
- "We cannot confirm halal suitability" alone is inconclusive for a non-meat product.
- An explicit ingredient or process statement should be assessed under these guidelines,
  rather than copying the manufacturer's label without explanation.
- One answer about one barcode does not apply to the full brand unless the company says so.

## 14. Conflicting or Incomplete Evidence

- Use Mashbooh when a significant concern is established but unresolved after investigation.
- Use Under Review when research has not established a specific concern.
- Do not force a binary verdict merely to close a request.
- Draft a precise follow-up question identifying the missing fact.
- Refer novel or disputed religious questions to Halal Kiwi's scholar before changing the
  standing rules.

## 15. Evidence and Audit Records

For every final verdict:

- retain the original email, certificate, attachment or official source;
- identify the exact barcode and evidence scope;
- record the source, received date, expiry and assessment privately;
- preserve prior evidence when a decision changes;
- record who approved the decision; and
- never expose local proof paths, communication IDs or audit metadata to app users.

Technical audit information belongs in proof, communication and request records, not in
`products.notes`.

## 16. Public Product Notes

Product notes are optional. Use one short sentence that tells the user why the status matters.

Good examples:

- `Contains carmine (E120).`
- `The chicken is not confirmed to be halal slaughtered.`
- `Pams confirmed this product is not halal.`
- `Shared production lines with non-halal products; cleaning was not confirmed.`

Do not include:

- dates;
- proof paths or URLs;
- certificate or communication IDs;
- database operations;
- internal approval wording; or
- identity-research details.

## 17. Approval and Notification

- A human must approve each final barcode decision.
- Manufacturer evidence must be saved before applying a final verdict.
- Resolve only exact-barcode requests.
- Notify eligible users only after the database transaction succeeds.
- Do not send final-verdict notifications for Under Review or Mashbooh.

## 18. Reassessment

Reassess a product when:

- its formulation, packaging, barcode or manufacturing site changes;
- a certificate expires or its scope changes;
- the manufacturer corrects or withdraws earlier evidence;
- reliable new evidence conflicts with the current status; or
- Halal Kiwi changes an assessment rule after scholar review.

Never silently replace historical evidence. Record the reason for the new decision and retain
the prior audit trail.

## 19. Items Requiring Final Approval

Before this document becomes version 1.0:

1. Ask Halal Kiwi's New Zealand scholar to review the carmine, nutmeg, ethanol and
   shared-line rules.
2. Approve the wording of `docs/product-assessment-guidelines-public.md`.
