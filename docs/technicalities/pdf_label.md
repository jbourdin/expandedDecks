# PDF Label Card (Home Printing)

> **Audience:** Developer, AI Agent · **Scope:** Technical Reference

← Back to [Main Documentation](../docs.md) | [Feature F5.7](../features.md) | [USB HID Scanner](scanner.md) | [Camera QR Scanner](camera_scanner.md)

---

## Problem

The existing label printing system ([F5.1](../features.md) / [F5.2](../features.md)) relies on a **Zebra thermal printer** available at event venues via PrintNode. Users preparing decks **at home** before an event have no way to generate a physical label for their deck box.

Without a label, the deck cannot be scanned at the event for hand-off (F4.3) or return (F4.4). The user must either wait until they arrive at a venue with a Zebra printer, or have someone else print the label remotely — neither option is practical for pre-event preparation.

## Strategy

Generate a **downloadable PDF** containing a single TCG card-sized label that the user can print on any home printer, cut out, and slip into a card sleeve at the front of the deck box.

Key choices:

- **Dompdf** — HTML/CSS to PDF conversion via a Twig template, well integrated with Symfony
- **`endroid/qr-code`** — QR code generation as base64 PNG, embedded directly in the HTML template
- **TCG card dimensions** — The label is sized to fit a standard card sleeve, so it sits naturally in the deck box
- **Same QR encoding** — Identical deck identifier format as the ZPL label (F5.1), so existing scanners (F5.3, F5.6) work unchanged

## TCG Card Dimensions

The label is rendered at **standard poker-size TCG card dimensions**:

| Dimension | Value     | Notes                                          |
|-----------|-----------|-------------------------------------------------|
| Width     | 63.5 mm   | Standard poker card width                       |
| Height    | 88.9 mm   | Standard poker card height                      |
| Fit       | Any standard card sleeve (e.g. Ultra PRO, Dragon Shield) | Same dimensions as a Pokemon card |

## Label Layout

The card-sized label contains the same information as the ZPL label (F5.1): QR code, deck name, owner name, and deck ID.

```
+-------------------------+
|                         |
|     +-------------+     |
|     |             |     |
|     |   QR CODE   |     |
|     |  (deck ID)  |     |
|     |             |     |
|     +-------------+     |
|                         |
|   -------------------   |
|                         |
|       Deck Name         |
|    (e.g. Lugia VSTAR)   |
|                         |
|       Owner Name        |
|    (e.g. John D.)       |
|                         |
|   -------------------   |
|                         |
|       DECK-0042         |
|                         |
|  +-------------------+  |
|  |  EXPANDED DECKS   |  |
|  +-------------------+  |
|                         |
+-------------------------+
   63.5 mm x 88.9 mm
```

## Printed Page Layout

The label is **centered on an A4 (or Letter) page** with crop marks at the four corners to guide cutting:

```
+--------------------------------------------------+
|                                                  |
|                                                  |
|                  +    +                          |
|                  |    |                          |
|              ----+----+----                      |
|              |            |                      |
|              |   LABEL    |                      |
|              |  63.5 mm   |                      |
|              |  x 88.9mm  |                      |
|              |            |                      |
|              ----+----+----                      |
|                  |    |                          |
|                  +    +                          |
|                                                  |
|                                                  |
|                  A4 / Letter                     |
+--------------------------------------------------+
```

One label per page. Crop marks are thin lines extending ~5 mm outward from each corner of the label rectangle.

## Dompdf + Symfony Integration

### Package

**`dompdf/dompdf`** — a PHP library that renders HTML + CSS into PDF documents. It supports `@page` CSS rules, mm-unit positioning, and embedded base64 images (for the QR code).

### Service: `App\Service\Label\PdfLabelGenerator`

Responsibilities:

1. Accept a `Deck` entity
2. Generate a QR code (base64 PNG) encoding the deck identifier — same format as F5.1
3. Render the Twig template `label/pdf_label.html.twig` with deck data and QR image
4. Pass the rendered HTML to Dompdf and return the PDF binary string

```php
class PdfLabelGenerator
{
    public function generate(Deck $deck): string
    {
        // 1. Generate QR code as base64 PNG
        // 2. Render Twig template with deck data + QR
        // 3. Dompdf render → return PDF binary
    }
}
```

### Twig Template: `label/pdf_label.html.twig`

The template uses CSS `@page` rules and mm-unit positioning to produce the exact card dimensions:

- `@page { size: A4; margin: 0; }` — full-bleed A4 page
- The label is a `div` sized at `63.5mm x 88.9mm`, centered on the page using flexbox
- Crop marks are rendered as pseudo-elements or thin border lines at each corner
- The QR code is embedded as a `<img src="data:image/png;base64,...">` tag
- Text is sized for legibility at print resolution (deck name ~12pt, owner ~10pt, ID ~9pt)

### Route

```
GET /deck/{id}/label.pdf
```

- Controller action returns a `Response` with `Content-Type: application/pdf`
- Content-Disposition: `inline` (opens in browser's PDF viewer for direct printing)
- Filename: `deck-{id}-label.pdf`
- Access: restricted to the deck owner (voter check)

## QR Code Generation

Uses the **`endroid/qr-code`** PHP library to generate QR codes server-side.

| Parameter          | Value      | Notes                                              |
|--------------------|------------|----------------------------------------------------|
| Content            | Deck identifier (same encoding as F5.1)  | Ensures scanner compatibility |
| Error correction   | M (15%)    | Balances data density with damage tolerance         |
| Rendered size      | ~30 mm     | Large enough for reliable camera scanning           |
| Output format      | PNG (base64-encoded) | Embedded directly in the HTML template    |

The QR code uses **the same deck identifier encoding** as the ZPL label (F5.1). This means:

- USB HID scanners (F5.3) can read it
- Camera QR scanners (F5.6) can read it
- No changes needed to the existing scanner infrastructure

## Configuration Constants

| Constant                         | Value   | Purpose                                 |
|----------------------------------|---------|-----------------------------------------|
| `PDF_LABEL_CARD_WIDTH_MM`        | `63.5`  | TCG card width in millimeters           |
| `PDF_LABEL_CARD_HEIGHT_MM`       | `88.9`  | TCG card height in millimeters          |
| `PDF_LABEL_PAGE_FORMAT`          | `A4`    | Output page format                      |
| `PDF_LABEL_QR_SIZE_MM`           | `30`    | QR code rendered size in millimeters    |
| `PDF_LABEL_QR_ERROR_CORRECTION`  | `M`     | QR error correction level (15%)         |

## ZPL Label vs PDF Label Card

| Aspect              | ZPL Label (F5.1)                 | PDF Label Card (F5.7)               |
|---------------------|----------------------------------|--------------------------------------|
| Output format       | ZPL code → thermal print         | HTML → PDF → inkjet/laser print      |
| Printer             | Zebra thermal (via PrintNode)    | Any home printer                     |
| Label size          | Zebra label stock (varies)       | 63.5 x 88.9 mm (TCG card size)      |
| Use case            | At venue — instant printing      | At home — pre-event preparation      |
| QR encoding         | Deck identifier                  | Same deck identifier                 |
| Scanner compatible  | F5.3 (HID) + F5.6 (camera)      | F5.3 (HID) + F5.6 (camera)          |
| Physical form       | Adhesive label on deck box       | Card in sleeve at front of deck box  |

Both label types produce scannable QR codes with identical encoding, so the scanning infrastructure is fully shared.

## References

- [Dompdf documentation](https://github.com/dompdf/dompdf)
- [`endroid/qr-code` documentation](https://github.com/endroid/qr-code)
- [USB HID Scanner Detection](scanner.md) — Barcode scanner integration
- [Camera QR Scanner](camera_scanner.md) — Mobile camera fallback
