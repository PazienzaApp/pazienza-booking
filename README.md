# Pazienza Booking

Online booking form for studios and professionals using [Pazienza](https://www.pazienza.app). Gutenberg block, real-time availability, confirmation emails, and more.

**WordPress.org:** [pazienza-booking](https://wordpress.org/plugins/pazienza-booking/) &nbsp;|&nbsp; **License:** GPL-2.0+

---

## Features

- **Gutenberg Block** — Insert the booking form on any page with a single click in the block editor.
- **Real-time availability** — Time slots are always in sync with the Pazienza calendar. Double bookings are automatically prevented.
- **Service and operator selection** — Clients can choose the service and, if configured, their preferred operator or resource.
- **Custom fields** — Add custom questions to the booking form (e.g. "Briefly describe your issue").
- **Automatic confirmation email** — The client receives an email with a booking summary and, optionally, a cancellation link.
- **Online cancellation** — Enable a time-limited cancellation link so clients can cancel within the window you configure.
- **My Account integration** — When WooCommerce is active, logged-in customers find their bookings in the "My Account" area.

## Requirements

- WordPress 6.5 or later, PHP 8.1 or later
- Active [Pazienza](https://www.pazienza.app) account

## Installation

1. Install **Pazienza Booking** via **Plugins › Add New**, or upload the folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **Prenotazioni › Impostazioni** and click **Connetti a Pazienza** to authorise the site via OAuth.
4. Add the **Pazienza Booking** block to any page from the Gutenberg editor.

## Development

The block is built with `@wordpress/scripts`. To rebuild the compiled assets:

```bash
cd blocks/booking-form
npm install
npm run build
```

Source files are in `blocks/booking-form/src/`. Compiled output lands in `blocks/booking-form/build/` and is committed to the repository so the plugin works without a build step on production.

## External Service

This plugin communicates with the Pazienza Cloud API to retrieve availability slots, create bookings, and manage customer records. An active Pazienza account is required.

- [Terms of Service](https://www.pazienza.app/termini)
- [Privacy Policy](https://www.pazienza.app/privacy)

Data transmitted includes: client name, email, and phone number; the selected service and time slot; any custom fields completed by the client. No data is shared with third parties.

## License

[GPL-2.0+](license.txt)
