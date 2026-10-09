# Public display preferences

Self-hosted, optional presentation helpers. The UI is in
`resources/views/public/partials/display-panel.blade.php`, the palettes and reflow
rules in `resources/css/public/display.css`, and the behavior in
`resources/js/display-preferences.js` (imported by the existing Vite entry).
No package, remote asset, cookie or server endpoint was added. At the user's
explicit request, manually chosen settings are saved in one local browser entry.
The CMS keeps its existing presentation.

A fixed 52 px eye-icon button stays at the viewport's bottom-right corner with
an accessible name and native title. The compact overlay is anchored above it,
at most 28 rem wide and 36 rem tall (smaller when the viewport requires it), with
its own scrolling and sticky close/header controls. It keeps a margin on mobile,
does not dim the screen, and closes on an outside click. The reveal animation
expands from the bottom-right only when motion is allowed. Final footer padding
keeps its last links clear of the fixed button.

| Control | Behavior |
|---|---|
| Schriftgröße | Root rem scale: 100, 112.5, 125, 137.5, 150%. Smaller/larger buttons stop at the limits; Standard resets only text size. Browser zoom remains independent. Larger text allows the wide navigation to wrap into a second row and narrow actions to wrap. |
| Kontrast erhöhen | Stronger text, borders, surface distinctions and link underlines through existing tokens. Light/dark variants; overrides warm surface colours where stronger contrast is needed. Never disables forced colours. |
| Dunkler Modus | Manual dark palette and native dark control scheme. Brighter link/status tokens with a separate action-text token preserve button contrast. Default remains the approved light design. |
| Bewegungen reduzieren | Eliminates non-essential animations, transitions, hover motion and smooth scrolling; search dismissal and alert removal honour the manual flag. OS reduced motion remains active independently and after reset. |
| Blaufilter | Warmer surface and accent tokens, with a separate warm dark palette. No filter on text, focus rings or dialog layers; no medical claims. Contrast mode takes precedence over warming. |
| Farbwahrnehmung / Farbschwäche | Optional red/green helper uses blue/yellow success/error colours; blue/yellow helper uses teal/magenta. Status outlines strengthen distinctions. Existing words/icons carry meaning independently. It does not simulate or claim to correct colour blindness or recolour photographs. |
| Bilder ausblenden | Hides optional raster pixels while keeping original image geometry, aspect ratio and crop, with a neutral surface and overlaid safe alternative text. Image slots, captions, links and surrounding layout stay intact. Decorative slots keep the neutral surface without invented descriptions. Overflowing descriptions become keyboard-scrollable. Added main-content images are also handled; reset unwraps the originals. The decorative footer silhouette is hidden. |
| Webseite vorlesen | User-initiated local German speech synthesis of visible main content in chunks of up to 220 characters, excluding all forms/values/navigation/hidden content. Only one chunk is queued at a time. Stop, reset and page departure cancel playback; stale callbacks cannot restart it. Missing API/local voice disables start and explains why. |
| Alles zurücksetzen | Removes all manual presentation flags and the saved browser entry, returns text size to normal, restores images and stops this page's reading; other tabs receive the default presentation. Retains OS/browser preferences. |

Images with essential visual information can opt out with
`data-display-essential`; the existing image partial accepts `imageEssential => true`.
This does not relax the content's existing image-alternative requirements.
Hiding images is a display aid, not a download/data-saver feature.

Settings persist across page loads and tabs in the same browser profile and
origin, using `localStorage` key `merching.display-preferences.v1`. Only the font
step, five boolean presentation flags and colour-helper choice are serialized:
no identifiers, timestamps, content, voice details or reading state. No entry is
written until a visitor changes a setting. Returning all controls to defaults or
using reset removes it. Changes sync to other open public tabs through native
storage events; restored history pages refresh their settings on `pageshow`.
Speech never automatically starts or syncs. Storage is never sent to the server.

Strict validation ignores malformed/out-of-range saved data. Denied/full storage
falls back to page-local memory and explains the limitation in the panel. Private
windows, different browser profiles/devices and different origins do not share
preferences; private browsing or clearing site data may discard them. With no
JavaScript the base presentation remains usable and no preferences are applied.

The privacy regression test spies on all local/session-storage mutations before
application startup. Ordinary page visits, opening/closing the panel, OS motion
changes and restoring saved preferences must perform no writes (including
temporary writes followed by deletion). Only an explicit preference change
creates the saved entry. Ordinary browsing also sends no `Set-Cookie` header.

The native `<dialog>` supplies modal semantics, background inertness, focus
containment and Escape behavior. Opening focuses the close button and closing
returns focus to the invoking control. A browser lacking `showModal` does not
expose a non-working trigger. No-JavaScript content and native forms remain usable.

Speech support depends on browser/OS and installed language voices. The Web
Speech specification defines `localService` as local versus remote synthesis:
[SpeechSynthesisVoice attributes](https://webaudio.github.io/web-speech-api/#speechsynthesisvoice-attributes).
The application refuses unknown/nonlocal voices; it cannot inspect the operating
system's own speech implementation or certify its behavior. Native audible speech,
Safari/iOS, screen readers, browser zoom and text spacing still need the existing
manual go-live checks. Automated tests cover API behavior with mocks and Chromium
rendering/keyboard/axe, not those native platform behaviors.
