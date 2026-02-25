# Camera QR Scanner (Mobile Fallback)

> **Audience:** Developer, AI Agent · **Scope:** Technical Reference

← Back to [Main Documentation](../docs.md) | [Feature F5.6](../features.md) | [USB HID Scanner](scanner.md)

---

## Problem

The existing scanner system ([F5.3 — USB HID scanner](scanner.md)) relies on a physical barcode reader that emits keypresses. This works at event venues with desktop or laptop setups but **fails on smartphones and tablets** where no USB HID scanner is available.

Event participants commonly use their phones to confirm deck hand-offs (F4.3) and returns (F4.4). A camera-based QR code scanning fallback is needed so that any device with a rear camera can scan deck labels.

## Strategy

Use the **`html5-qrcode`** library's low-level `Html5Qrcode` API to access the device camera, decode QR codes and Code128 barcodes, and feed the result into the same `onScan` callback used by the HID scanner.

Key choices:

- **Low-level API (`Html5Qrcode`)** — not the UI wrapper (`Html5QrcodeScanner`) — so we can build our own React/Mantine UI around it
- **Rear camera preferred** — `facingMode: "environment"` targets the back camera on mobile devices
- **QR + Code128** — supports both encoding formats used on deck labels (F5.1)

### Why `html5-qrcode`

| Criterion             | Detail                                                      |
|-----------------------|-------------------------------------------------------------|
| Cross-platform        | iOS Safari 14.5+, Android Chrome, desktop browsers          |
| Format support        | QR Code, Code128, and many other barcode formats            |
| Bundle size           | Reasonable (~90 KB gzipped)                                 |
| Maintenance           | ~600k weekly npm downloads, actively maintained             |
| API flexibility       | Low-level API allows custom UI integration                  |

## Browser Compatibility

| Platform        | Browser          | Min Version | Notes                              |
|-----------------|------------------|-------------|------------------------------------|
| iOS             | Safari           | 14.5+       | `getUserMedia` support required    |
| iOS             | Chrome / Firefox | 14.5+       | Uses Safari's WebKit engine (WKWebView) |
| Android         | Chrome           | 63+         | Full support                       |
| Android         | Firefox          | 36+         | Full support                       |
| Desktop         | Chrome           | 53+         | Webcam access                      |
| Desktop         | Firefox          | 36+         | Webcam access                      |
| Desktop         | Safari           | 11+         | Webcam access                      |

**HTTPS required** — `getUserMedia` (camera access) is blocked on insecure origins. `localhost` is exempt during development.

## UX Flow

```
User taps scan button (header)
        │
        ▼
  Mantine Modal opens
  (camera viewfinder)
        │
        ├── Camera permission denied?
        │       → Show permission error + instructions
        │
        ├── No camera found?
        │       → Show "no camera" message
        │
        ├── Camera active, scanning...
        │       │
        │       ├── QR/barcode decoded
        │       │       → Close modal → onScan(deckId)
        │       │
        │       └── 60s inactivity timeout
        │               → Auto-stop camera → Show "timed out" message
        │
        └── User closes modal manually
                → Stop camera → Clean up
```

### Step-by-step

1. **Header scan button** — A scan icon button in the `AppShell` header (visible on all pages) opens the camera scanner modal.
2. **Mantine Modal** — Displays the camera viewfinder with a scanning overlay. On mobile, the modal should be fullscreen for easier aiming.
3. **Camera permission** — The browser prompts for camera access on first use. The modal shows contextual messages for each permission state.
4. **Scanning** — The camera feed is displayed with a target box overlay. When a QR code or barcode is recognized, the modal closes automatically.
5. **Result** — The decoded value is passed to `onScan(deckId)`, triggering the same deck lookup/action as a USB HID scan.

## React Integration

### `useCameraScanner` Hook

Manages the `Html5Qrcode` lifecycle.

```typescript
interface UseCameraScannerReturn {
    start: (elementId: string) => Promise<void>;
    stop: () => Promise<void>;
    isScanning: boolean;
    error: CameraScannerError | null;
}

function useCameraScanner(onDecode: (decodedText: string) => void): UseCameraScannerReturn;
```

Responsibilities:
- Create and destroy the `Html5Qrcode` instance
- Start scanning with `facingMode: "environment"` preference
- Call `onDecode` on successful scan
- Auto-stop after `CAMERA_SCANNER_INACTIVITY_TIMEOUT` (60s)
- Expose scanning state and errors
- Clean up on unmount

### `CameraScannerModal` Component

A Mantine `Modal` wrapping the camera viewfinder.

```typescript
interface CameraScannerModalProps {
    opened: boolean;
    onClose: () => void;
    onScan: (deckId: string) => void;
}
```

Responsibilities:
- Render the `Html5Qrcode` target element
- Use `useCameraScanner` to manage scanning
- Display permission states: requesting, denied, no camera found
- Show scanning overlay / target box
- Fullscreen on mobile viewports (`useMediaQuery`)
- Close and call `onScan` on successful decode

### `useDeckScanner` Unified Hook

Combines both scanner methods behind a single callback.

```typescript
interface UseDeckScannerReturn {
    openCameraScanner: () => void;
    closeCameraScanner: () => void;
    isCameraOpen: boolean;
}

function useDeckScanner(onScan: (deckId: string) => void): UseDeckScannerReturn;
```

Responsibilities:
- Set up the existing HID scanner listener (passive, always active)
- Provide controls to open/close the camera scanner modal
- Both methods feed into the same `onScan(deckId)` callback
- Prevents duplicate scans (debounce across both methods)

## Configuration Constants

| Constant                          | Value           | Purpose                                      |
|-----------------------------------|-----------------|----------------------------------------------|
| `CAMERA_SCANNER_FPS`              | `10`            | Camera frame processing rate (frames/second)  |
| `CAMERA_SCANNER_BOX_SIZE`         | `250`           | QR scanning box size in pixels                |
| `CAMERA_FACING_MODE`              | `"environment"` | Preferred camera (rear on mobile)             |
| `CAMERA_SCANNER_INACTIVITY_TIMEOUT` | `60000`       | Auto-stop after 60s of no successful scan (ms)|

## Error Handling

Camera access can fail for several reasons. Each error type should display a user-friendly message.

| Error Name          | Cause                                     | User Message                                             |
|---------------------|-------------------------------------------|----------------------------------------------------------|
| `NotAllowedError`   | User denied camera permission             | "Camera access was denied. Please allow camera access in your browser settings to scan QR codes." |
| `NotFoundError`     | No camera available on the device         | "No camera found on this device. Use a USB barcode scanner instead." |
| `NotReadableError`  | Camera is in use by another application   | "Camera is in use by another app. Close other camera apps and try again." |
| `OverconstrainedError` | Requested camera (rear) not available  | Falls back to any available camera silently.             |
| Decode timeout      | 60s with no successful scan               | "Scanner timed out. Tap to try again."                   |

### Auto-Stop (Battery Saving)

The camera is automatically stopped after **60 seconds** of inactivity (no successful decode) to conserve battery on mobile devices. The user can restart scanning by tapping a "Try again" button without closing the modal.

## Relationship to HID Scanner

Both scanning methods are complementary:

| Aspect             | USB HID Scanner (F5.3)                     | Camera QR Scanner (F5.6)                |
|--------------------|--------------------------------------------|-----------------------------------------|
| Activation         | Passive — always listening                 | On-demand — user taps scan button       |
| Hardware           | USB barcode reader                         | Device camera                           |
| Detection method   | Timing heuristic on `keypress` events      | `getUserMedia` + `html5-qrcode` decode  |
| Best for           | Desktop/laptop at venue                    | Smartphones and tablets                 |
| Output             | `onScan(deckId)`                           | `onScan(deckId)`                        |

Both methods produce the same output and trigger the same downstream behavior (deck lookup, lend/return action).

## References

- [`html5-qrcode` documentation](https://github.com/mebjas/html5-qrcode)
- [MDN — `MediaDevices.getUserMedia()`](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia)
- [USB HID Scanner Detection](scanner.md) — Existing scanner implementation
