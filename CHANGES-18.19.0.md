# Mostaager Facility PRO — 18.19.0

## Self-defence: the section stays open
If another script on the page hides the section right after we open it (each dashboard ships its
own inline tab code), the handler now notices within ~120 ms and re-applies the section, up to six
times. This makes section switching independent of what the page's own script decides to do.

## Debug mode from the console
`MSUX.debug()` turns on verbose logging, then every click on a `#section` link, every hash change,
every open, and every "another script hid it again" event is printed with the current URL and the
visible sections. Turn it off with `MSUX.debug(false)`.

Still no behaviour change for normal users; the logging is off by default.
