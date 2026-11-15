# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.4] - 2026-11-15

### Security

- Every back-office screen of the module checks the employee's permissions. Before,
  any logged-in employee, whatever their profile, could open the question queue,
  answer, publish and delete questions. The screens now belong to the module's own
  menu tab (`AdminQuinceQa`): grant it to a profile under **Advanced Parameters → Team →
  Permissions**. SuperAdmin needs nothing. A profile that had access through the hidden
  `AdminQuince` tab needs access to this tab now.

## [0.1.3] - 2026-11-15

### Security

- The visitor's name and e-mail address are escaped in the e-mail about a new
  question and in the answer e-mail. Before, a name with HTML put a link of the
  visitor's choosing into an e-mail sent from the shop's address. Use with Quince
  Engine 0.8.1, which makes the form guard mandatory for this form.

## [0.1.2] - 2026-11-10

### Added

- Polish translation of every text the module shows, in the back office and in the shop.
- Polish versions of the e-mails.

### Changed

- Texts no longer use long dashes.

## [0.1.1] - 2026-11-10

### Fixed

- The questions screen now shows up in the Modules group of the Quince Engine
  sidebar. The module registered the engine hook on install but never answered it,
  so the engine did not know about the screen.

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
