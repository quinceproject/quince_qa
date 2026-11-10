# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-11-10

First public release.

### Added

- Questions asked from the product page, by signed-in customers or, when the shop
  allows it, by guests.
- A queue in the back office with filters by status, where the shop answers, rejects
  or deletes questions.
- Only answered questions appear on the product page, in batches with a button that
  loads more.
- Email to the shop about a new question, with copies to chosen shop contacts, and
  email to the customer when the answer is published.
- Wording of the product page section and of the customer email per language, and a
  signature under answers.
- A `qa.question` event sent through Quince Events when that module is installed.
