# GAS deployment

GitHub Actions deploys this directory to Apps Script with `clasp push --force`.

Required repository secret:

- `CLASPRC_JSON`: contents of a valid local `~/.clasprc.json`

Target Apps Script project:

- `1CdriYl4LWNudAkRhQiPnLuROWdtJiBG6fUwBpojWx4bePfUQykC_VXWx`

Mail mode:

- By default, career center emails are sent to the test address `eduard@hsc.ac.jp`.
- After manual testing passes, set Apps Script property `USE_PRODUCTION_CAREER_CENTER_MAIL` to `true` to send to `syushoku@ucs-hiroshima.ac.jp`.
- Remove the property or set it to any value other than `true` to return to test mode.

Last deploy trigger: 2026-06-25 retry after Apps Script API enable
