# Quince Questions

**Product questions and answers for PrestaShop 9.2.**

Customers ask about a product right on its page, the shop answers in the back office,
and the question with its answer stays on the product page. The next customer reads
the answer instead of asking again.

> Quince Questions is a free, open-source module (OSL-3.0). There is nothing to buy.
> If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

---

## Features

- **Ask from the product page.** Signed-in customers ask with the name and address
  from their account. Guests can ask too when the shop switches that on; it is off by
  default, because a guest's address is not confirmed.
- **Only answered questions are shown.** A question appears on the product page only
  once the shop answers it, so the page never shows a customer who seems ignored.
- **A queue for the shop.** All questions in one list, filtered by status (waiting
  for an answer, answered, rejected) and searchable by question, customer or product.
  From there the shop publishes an answer, rejects a question or deletes it.
- **Notifications by email.** The shop gets an email about each new question, with
  copies to the shop contacts it picks in **Shop Parameters → Contact**. The customer
  gets an email when the answer is published. The question always lands in the queue,
  whatever the email settings.
- **Batches on the product page.** The shop sets how many questions show before the
  button that loads more.
- **Wording and signature.** The text of the product page section and of the customer
  email is set per language. Answers are signed with a name of the shop's choice, or
  the shop name by default.
- **Works with Quince Events.** When
  [Quince Events](https://github.com/quinceproject/quince_events) is installed, each
  new question is also sent as a `qa.question` event to the shop's webhook.
- **Form protection.** The question form goes through the Quince Engine form gate,
  and through Quince reCAPTCHA when that module is installed.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | required |
| Quince theme | required for the product page section |

Quince Engine hosts the module's back office screens. The module declares it as a
dependency, so PrestaShop refuses to install Quince Questions without it.

The module provides the data; the Quince theme draws the questions section and the
question form on the product page. With another theme the back office works, but the
product page shows nothing.

The back office screens and the email templates are in English only for now.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_qa.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_qa/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_qa` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince questions and click **Install**.

**From Quince Engine**

With Quince Engine installed, **Quince project → Quince modules** installs and updates
this module from the Quince release list, without downloading anything by hand.

**With Composer**

A PrestaShop shop ships without a `composer.json` of its own, so Quince packages are
installed from a separate folder next to the shop. The one-time setup of that folder is
described in the [Quince Engine README](https://github.com/quinceproject/quince_engine#installation).
Then, from that folder:

```sh
composer require "quinceproject/qa:*"
php ../bin/console prestashop:module install quince_qa
```

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_qa/releases).

Uninstalling the module removes all questions and answers.

## Configuration

Open **Quince project → Questions** in the back office menu, or click **Configure**
next to the module in the Module Manager.

1. **Who can ask.** Signed-in customers only, or guests as well.
2. **Questions per batch** on the product page.
3. **Notifications.** Email to the shop, copies to shop contacts, email to the
   customer about the answer.
4. **Answers are signed** with the name you enter, or the shop name.
5. **Wording** of the product page section and of the customer email, per language.

Answer questions in the queue on the same screen.

## Architecture

- **`QaStore`** keeps questions and answers in the module's own table.
- **`ask` and `list` front controllers** receive a new question and load further
  answered questions for the product page, as JSON.
- **`actionFrontControllerSetVariables`** hands the product page data to the theme
  under `$modules.quince_qa`.
- **Symfony controllers and Twig** for the back office queue and settings, inside the
  Quince Engine shell.
- **Settings** are stored in PrestaShop configuration under the `QUINCE_QA_` prefix.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_qa.git
cd quince_qa
composer install
```

The folder must be called `quince_qa`, the same as the module. A clone of this
repository already has that name.

To build an installable archive, zip the `quince_qa/` folder **with its `vendor/`
directory included**, leaving out `.git/` and `.gitignore`.

## License

Code: [Open Software License 3.0 (OSL-3.0)](LICENSE). It is a reciprocal licence:
whoever modifies and distributes this code has to publish the changes under the same
licence and keep the original copyright and author notices.

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
