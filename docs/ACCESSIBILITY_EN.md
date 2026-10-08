# Accessibility

Attendance Journeys builds on Moodle components and conventions and aims to remain fully usable with a keyboard and assistive technologies.

- Fields have explicit labels, including labels that are visually hidden.
- Status groups use radio buttons inside a `fieldset` with a legend.
- Calculation and progress updates are announced through `aria-live` regions.
- Minute validation errors are linked to the affected field with `aria-describedby` and `aria-invalid`.
- Wide tables can receive focus and be scrolled horizontally with a keyboard.
- A visible focus indicator is preserved on links, buttons and fields.
- Colour is not the only way an interface state is communicated: badges are accompanied by text or labels.

Final compliance must also be checked with the Moodle theme used in production because the theme can change colours, spacing and focus styles.
