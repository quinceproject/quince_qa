<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use Quince\Qa\Settings\QaSettings;
use Quince\Qa\Settings\QaStore;

/**
 * Pytanie o produkt zadane z karty.
 *
 * Formularza nie sprawdzamy tutaj poza tym, co dotyczy samej treści. Znacznik
 * strony, pułapkę, czas wypełnienia i limit powtórzeń bierze na siebie strażnik
 * silnika — odpala się na `actionFrontControllerInitAfter` dla każdego żądania
 * niosącego jego pola i przerywa, zanim ten kod się zacznie.
 *
 * Pytanie idzie dwiema drogami naraz: do tabeli, bo sklep ma na nie odpowiedzieć
 * w panelu, oraz zdarzeniem `qa.question` do odbiorcy ustawionego w silniku,
 * żeby trafiło tam, gdzie sklep prowadzi obsługę. Mail do sklepu jest trzecią
 * drogą i można go wyłączyć — tabela zostaje zawsze.
 */
class Quince_QaAskModuleFrontController extends ModuleFrontController
{
    /** @var bool nie renderujemy strony, tylko JSON */
    public $ajax = true;

    public function postProcess(): void
    {
        $this->ajax = true;
    }

    public function displayAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->przyjmij());
        exit;
    }

    /** @return array<string,mixed> */
    private function przyjmij(): array
    {
        $ustawienia = new QaSettings();
        $zalogowany = $this->context->customer !== null && (bool) $this->context->customer->isLogged();

        if (!$ustawienia->flag('ALLOW_GUESTS') && !$zalogowany) {
            return ['ok' => false, 'error' => 'login'];
        }

        $idProduct = (int) Tools::getValue('id_product');
        $tresc = trim((string) Tools::getValue('question'));

        // Zalogowanemu bierzemy dane z konta: pytanie ma wrócić na adres, który
        // sklep zna, a nie na wpisany w locie.
        $imie = $zalogowany
            ? (string) $this->context->customer->firstname . ' ' . (string) $this->context->customer->lastname
            : trim((string) Tools::getValue('name'));
        $email = $zalogowany
            ? (string) $this->context->customer->email
            : trim((string) Tools::getValue('email'));

        if ($tresc === '' || $imie === '' || $email === '' || !Validate::isEmail($email)) {
            return ['ok' => false, 'error' => 'form'];
        }

        if (!Validate::isCleanHtml($tresc) || !Validate::isCleanHtml($imie)) {
            return ['ok' => false, 'error' => 'form'];
        }

        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $produkt = new Product($idProduct, false, $idLang, $idShop);

        if (!$produkt->id) {
            return ['ok' => false, 'error' => 'product'];
        }

        // Bez moderacji pytanie i tak nie pokaże się od razu: na karcie stoją
        // wyłącznie pytania z odpowiedzią. Stan mówi tylko, czy sklep ma je
        // jeszcze przejrzeć, zanim odpowie.
        $id = (new QaStore())->add([
            'id_product' => $idProduct,
            'id_shop' => $idShop,
            'id_customer' => $zalogowany ? (int) $this->context->customer->id : 0,
            'customer_name' => pSQL($imie),
            'customer_email' => pSQL($email),
            'question' => pSQL($tresc),
            'status' => QaStore::CZEKA,
            'date_add' => date('Y-m-d H:i:s'),
        ]);

        if ($id === 0) {
            return ['ok' => false, 'error' => 'save'];
        }

        if ($ustawienia->flag('NOTIFY_SHOP')) {
            $this->mailDoSklepu($imie, $email, $tresc, $produkt);
        }

        $this->zdarzenie($id, $imie, $email, $tresc, $produkt);

        return ['ok' => true];
    }

    /**
     * Powiadomienie dla sklepu o nowym pytaniu.
     *
     * Nadawcą jest adres sklepu, bo tylko z niego wolno wysyłać; adres klienta
     * idzie jako zwrotny, więc da się odpowiedzieć wprost, nie szukając go
     * w treści. Odpowiedź publikowana na karcie powstaje jednak w panelu, a nie
     * z tej wiadomości.
     */
    private function mailDoSklepu(string $imie, string $email, string $tresc, Product $produkt): void
    {
        $nazwaSklepu = (string) Configuration::get('PS_SHOP_NAME');
        $idLang = (int) $this->context->language->id;

        Mail::Send(
            $this->jezykSzablonu($idLang, 'quince_qa_question'),
            'quince_qa_question',
            $nazwaSklepu . ' — ' . $produkt->name,
            [
                '{shop_name}' => $nazwaSklepu,
                '{customer_name}' => $imie,
                '{customer_email}' => $email,
                '{question}' => nl2br(htmlspecialchars($tresc, ENT_QUOTES, 'UTF-8')),
                '{product_name}' => (string) $produkt->name,
                '{product_url}' => $this->context->link->getProductLink($produkt),
            ],
            (string) Configuration::get('PS_SHOP_EMAIL'),
            $nazwaSklepu,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'quince_qa/mails/',
            false,
            (int) $this->context->shop->id,
            $this->kopie(),
            $email,
            $imie
        );
    }

    /**
     * Adresy kopii: kontakty sklepu wskazane w ustawieniach.
     *
     * Identyfikatory, nie adresy — adres kontaktu zmienia się w jednym miejscu,
     * w Sklep → Kontakty, i zmiana dochodzi tutaj sama.
     *
     * @return string[]
     */
    private function kopie(): array
    {
        $wybrane = array_filter(array_map(
            'intval',
            explode(',', (new QaSettings())->get('COPY_CONTACTS'))
        ));

        if ($wybrane === []) {
            return [];
        }

        $adresy = [];

        foreach (Contact::getContacts((int) $this->context->language->id) as $kontakt) {
            if (in_array((int) $kontakt['id_contact'], $wybrane, true)
                && Validate::isEmail($kontakt['email'])
            ) {
                $adresy[] = (string) $kontakt['email'];
            }
        }

        return array_values(array_unique($adresy));
    }

    /**
     * Język, w którym mamy szablon wiadomości.
     *
     * PrestaShop szuka pliku po skrócie języka, a brakującego szablonu nie
     * zastępuje niczym: wiadomość po prostu nie wychodzi, a w dzienniku sklepu
     * zostaje „Brakuje szablonu e-mail". Sklep w języku, dla którego szablonu
     * jeszcze nie ma, dostaje więc wiadomość w tym, który jest.
     */
    private function jezykSzablonu(int $idLang, string $szablon): int
    {
        $katalog = _PS_MODULE_DIR_ . 'quince_qa/mails/';
        $iso = (string) Language::getIsoById($idLang);

        if ($iso !== '' && is_file($katalog . $iso . '/' . $szablon . '.html')) {
            return $idLang;
        }

        foreach (Language::getLanguages(false) as $jezyk) {
            if (is_file($katalog . $jezyk['iso_code'] . '/' . $szablon . '.html')) {
                return (int) $jezyk['id_lang'];
            }
        }

        return $idLang;
    }

    /**
     * Zdarzenie wychodzące ze sklepu.
     *
     * Odbiorcę wskazuje sklep w module `quince_events`, w jego ekranie
     * ustawień — moduł nie zna ani adresu, ani tego, co stoi po drugiej
     * stronie. Bez ustawionego odbiorcy nie dzieje się nic, a pytanie i tak
     * jest w tabeli.
     */
    private function zdarzenie(int $id, string $imie, string $email, string $tresc, Product $produkt): void
    {
        $zdarzenia = Module::getInstanceByName('quince_events');

        if (!$zdarzenia || !class_exists(\Quince\Events\Event\EventDispatcher::class)) {
            return;
        }

        (new \Quince\Events\Event\EventDispatcher(new \Quince\Events\Event\EventOutbox()))->dispatch(
            new \Quince\Events\Event\ShopEvent(
                type: 'qa.question',
                data: [
                    'id_question' => $id,
                    'sender' => [
                        // Zero znaczy gość. Sklep nie zgaduje, kto to jest —
                        // dopasowaniem do kontaktu zajmuje się odbiorca.
                        'id_customer' => $this->context->customer !== null
                            && $this->context->customer->isLogged()
                                ? (int) $this->context->customer->id
                                : 0,
                        'email' => $email,
                        'name' => $imie,
                    ],
                    'question' => $tresc,
                ],
                context: [
                    'product' => [
                        'id_product' => (int) $produkt->id,
                        'name' => (string) $produkt->name,
                        'reference' => (string) $produkt->reference,
                        'url' => $this->context->link->getProductLink($produkt),
                    ],
                ],
                source: 'quince_qa'
            )
        );
    }
}
