# Barcode Scanner Detection (USB HID)

> **Audience:** Developer, AI Agent · **Scope:** Technical Reference

← Back to [Main Documentation](../docs.md) | [Feature F5.3](../features.md)

## Problem

USB barcode scanners operate as **HID (Human Interface Device) keyboards**. When a barcode is scanned, the scanner emits the barcode content as a rapid sequence of `keypress` events, followed by an `Enter` key. From the browser's perspective, there is no native way to distinguish scanner input from regular keyboard typing — both arrive as standard keyboard events.

We need to reliably detect scanner input in the browser to trigger deck identification (lend/return) without interfering with normal form interactions.

## Strategy: Timing-Based Heuristic

The key insight is that **scanners emit characters far faster than any human can type**. A barcode scanner sends an entire code (10-30 characters) in under 50ms, while even the fastest human typist has 30-80ms gaps between individual keystrokes.

### Detection Algorithm

1. **Buffer incoming keypresses** at the document level
2. **Track the timestamp** of each keypress
3. **Measure the inter-key delay** — time between consecutive keypress events
4. **Classify the input** based on timing thresholds

### Thresholds

| Constant | Value | Purpose |
|----------|-------|---------|
| `SCANNER_RAPID_INPUT_THRESHOLD` | 20ms | Maximum time between consecutive keypresses to consider input as scanner-originated |
| `SCANNER_BUFFER_RESET_DELAY` | 500ms | Reset the input buffer if no keypress arrives within this window |
| `SCANNER_MIN_CHARS` | 2 | Minimum characters in the buffer before evaluating as potential scanner input |
| `SCANNER_PROCESSING_DELAY` | 50ms | Debounce delay to collect all scanner characters before processing |

### Why These Values

- **20ms threshold**: Barcode scanners typically emit all characters within 50-100ms total. Even at 30 characters, that's ~3ms per character. No human can sustain < 20ms between every keystroke.
- **500ms buffer reset**: Human typing naturally has pauses > 500ms (thinking, moving fingers). This ensures stale buffer data is cleared between typing bursts.
- **50ms processing delay**: Gives the scanner time to finish emitting all characters. Longer than the slowest scanner output but imperceptible to users.

## Implementation

```javascript
const SCANNER_RAPID_INPUT_THRESHOLD = 20;
const SCANNER_BUFFER_RESET_DELAY = 500;
const SCANNER_MIN_CHARS = 2;
const SCANNER_PROCESSING_DELAY = 50;

function setupScannerDetection(onScan) {
    let buffer = '';
    let lastInputTime = 0;
    let timer = null;

    document.addEventListener('keypress', (event) => {
        const now = Date.now();

        // Reset buffer after inactivity
        if (now - lastInputTime > SCANNER_BUFFER_RESET_DELAY) {
            buffer = '';
        }

        // Skip modifier keys (scanner sends Enter at end)
        if (event.key === 'Enter' || event.key === 'Shift') {
            lastInputTime = now;
            return;
        }

        buffer += event.key;

        const isLikelyScanner =
            buffer.length > SCANNER_MIN_CHARS &&
            (now - lastInputTime) < SCANNER_RAPID_INPUT_THRESHOLD;

        lastInputTime = now;

        // Debounce to collect all scanner characters
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            if (isLikelyScanner) {
                onScan(buffer.trim());
            }
            buffer = '';
        }, SCANNER_PROCESSING_DELAY);
    });
}
```

## Flow Diagram

```
Scanner emits: D E C K - 0 0 4 2 [Enter]
               |1ms|1ms|1ms|1ms|1ms|1ms|1ms|1ms|

 t=0ms    'D' → buffer="D",       gap=0ms  (first char)
 t=1ms    'E' → buffer="DE",      gap=1ms  < 20ms → scanner candidate
 t=2ms    'C' → buffer="DEC",     gap=1ms  < 20ms → scanner candidate
 ...
 t=8ms    '2' → buffer="DECK-0042", gap=1ms < 20ms → scanner!
 t=9ms    Enter → skipped
 t=58ms   debounce fires → onScan("DECK-0042")
```

vs. human typing:

```
Human types: D . . . . E . . . . C . . . . K
             |--80ms--|--60ms--|--90ms--|

 t=0ms     'D' → buffer="D"
 t=80ms    'E' → buffer="DE",  gap=80ms > 20ms → NOT scanner
 t=140ms   'C' → buffer="DEC", gap=60ms > 20ms → NOT scanner
```

## Edge Cases

### Focused input fields
When a text input has focus, the scanner characters will be typed into it. The detection listener runs at the document level, so it catches events regardless. If the active element is a text field, the React component should decide whether to intercept or let the input pass through (e.g. skip detection when a search field is focused).

### Special characters in barcodes
Some scanner encodings produce Unicode substitutions (smart quotes, em dashes, section signs). Normalize these before processing:

```javascript
const normalized = raw
    .replace(/[\u2018\u2019]/g, "'")
    .replace(/[\u201C\u201D]/g, '"')
    .replace(/[\u2014\u2015]/g, '-')
    .replace(/\u00A0/g, ' ');
```

### Multiple rapid scans
The buffer reset delay (500ms) ensures that two consecutive scans (which always have > 500ms between them due to physical handling) are processed independently.

## Integration with React

The scanner detection should be set up as a React hook (`useScannerDetection`) that:

1. Attaches the `keypress` listener on mount
2. Cleans up on unmount
3. Calls a provided callback with the scanned value
4. Optionally filters out events when specific elements are focused

This hook is used in the lend/return views (F4.3, F4.4) to trigger deck lookup by scanned barcode ID.

## Camera Fallback (Mobile)

On smartphones and tablets where no USB HID scanner is available, the application offers a **camera-based QR code scanner** as a fallback (F5.6). The camera scanner uses the device's rear camera and the `html5-qrcode` library to decode deck label QR codes.

Both methods share the same `onScan(deckId)` callback, so downstream behavior (deck lookup, lend/return action) is identical regardless of the input method.

See [Camera QR Scanner](camera_scanner.md) for full technical details.

## References

- Inspired by the POS scanner implementation in `ecommerce-sylius` (BHV theme, `_tpeSelector.html.twig`)
- [W3C UI Events — keypress](https://www.w3.org/TR/uievents/#event-type-keypress)
