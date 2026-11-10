<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Qa\Settings;

use Configuration;
use Language;
use Shop;

/**
 * Ustawienia modułu pytań i odpowiedzi.
 *
 * Dwie rzeczy dołożone względem modułu źródłowego `productqa`.
 *
 * Wielojęzyczność obejmuje wszystkie teksty czytane przez klienta, nie trzy
 * wybrane. Sklep niemiecki nie dostaje polskiego zdania dlatego, że ktoś kiedyś
 * wpisał je w jedyne pole, jakie było.
 *
 * Wielosklepowość: zapis idzie do każdego sklepu z bieżącego kontekstu, a nie
 * do wiersza globalnego. Powód jest ten sam, dla którego silnik ma `ConfigStore`:
 * sklep czyta najpierw swój wiersz, więc wartość zapisana „dla wszystkich"
 * nie docierała do sklepu, który miał już własny.
 */
final class QaSettings
{
    private const PREFIX = 'QUINCE_QA_';

    /** Ustawienia jednakowe dla wszystkich języków: nazwa => wartość domyślna. */
    private const PLAIN = [
        // Pytania od gości. Domyślnie wyłączone, bo pytanie bez konta trafia do
        // moderacji razem z adresem, którego nikt nie potwierdził.
        'ALLOW_GUESTS' => '0',
        // Moderacja. Domyślnie włączona: pytanie klienta pokazuje się dopiero
        // z odpowiedzią sklepu, a nie samo.
        'MODERATE' => '1',
        'PER_PAGE' => '5',
        // Powiadomienia: do sklepu o nowym pytaniu, do klienta o odpowiedzi.
        'NOTIFY_SHOP' => '1',
        'NOTIFY_CUSTOMER' => '1',
        // Podpis pod odpowiedzią. Puste znaczy nazwę sklepu.
        'ANSWER_AUTHOR' => '',
        // Kopie powiadomień: identyfikatory kontaktów sklepu po przecinku.
        // Adresy wpisywane ręcznie odpadają, bo każdy z nich i tak musi gdzieś
        // żyć, a żyje w Sklep → Kontakty.
        'COPY_CONTACTS' => '',
    ];

    /** Teksty czytane przez klienta: wartość na każdy język sklepu. */
    private const TRANSLATED = [
        'DRAWER_INTRO',
        'EMAIL_SUBJECT',
        'EMAIL_BODY',
    ];

    /**
     * Ustawienia jednakowe dla wszystkich języków.
     *
     * @return array<string,string>
     */
    public function plain(): array
    {
        $out = [];

        foreach (self::PLAIN as $klucz => $domyslna) {
            $wartosc = Configuration::get(self::PREFIX . $klucz);
            $out[$klucz] = ($wartosc === false || $wartosc === null) ? $domyslna : (string) $wartosc;
        }

        return $out;
    }

    public function get(string $klucz): string
    {
        return $this->plain()[$klucz] ?? '';
    }

    public function flag(string $klucz): bool
    {
        return $this->get($klucz) === '1';
    }

    public function perPage(): int
    {
        $ile = (int) $this->get('PER_PAGE');

        return $ile > 0 ? $ile : 5;
    }

    /**
     * Teksty w rozbiciu na języki, gotowe pod pola wielojęzyczne panelu.
     *
     * @return array<string,array<int,string>>
     */
    public function translated(): array
    {
        $out = [];

        foreach (self::TRANSLATED as $klucz) {
            $out[$klucz] = [];

            foreach (Language::getLanguages(true) as $jezyk) {
                $idLang = (int) $jezyk['id_lang'];
                $wartosc = Configuration::get(self::PREFIX . $klucz, $idLang);
                $out[$klucz][$idLang] = $wartosc === false ? '' : (string) $wartosc;
            }
        }

        return $out;
    }

    /** Tekst w jednym języku, gotowy do wypisania. */
    public function text(string $klucz, int $idLang): string
    {
        $wartosc = Configuration::get(self::PREFIX . $klucz, $idLang);

        return $wartosc === false ? '' : (string) $wartosc;
    }

    /**
     * Zapis całego ekranu.
     *
     * @param array<string,string>            $plain
     * @param array<string,array<int,string>> $translated
     */
    public function save(array $plain, array $translated): void
    {
        foreach (self::PLAIN as $klucz => $_) {
            if (array_key_exists($klucz, $plain)) {
                $this->zapisz(self::PREFIX . $klucz, (string) $plain[$klucz]);
            }
        }

        foreach (self::TRANSLATED as $klucz) {
            if (!array_key_exists($klucz, $translated)) {
                continue;
            }

            $wartosci = [];

            foreach (Language::getLanguages(true) as $jezyk) {
                $idLang = (int) $jezyk['id_lang'];
                $wartosci[$idLang] = (string) ($translated[$klucz][$idLang] ?? '');
            }

            $this->zapisz(self::PREFIX . $klucz, $wartosci);
        }
    }

    /**
     * Wartości domyślne przy instalacji: tylko tam, gdzie klucza jeszcze nie ma.
     *
     * Piszemy do wiersza globalnego, a nie do sklepów z kontekstu. Instalacja
     * idzie z konsoli albo z jednego sklepu, a wartość startowa ma dotyczyć
     * wszystkich — także tych założonych później.
     */
    public function installDefaults(): void
    {
        foreach (self::PLAIN as $klucz => $domyslna) {
            if (!Configuration::hasKey(self::PREFIX . $klucz)) {
                Configuration::updateGlobalValue(self::PREFIX . $klucz, $domyslna);
            }
        }

        // Teksty zostają puste. Wpisane tu zdanie po angielsku wyglądałoby
        // w polskim sklepie na usterkę, a przetłumaczone „na wszelki wypadek"
        // trafiłoby do każdego języka jako ten sam napis.
    }

    public function uninstall(): void
    {
        foreach (array_keys(self::PLAIN) as $klucz) {
            Configuration::deleteByName(self::PREFIX . $klucz);
        }

        foreach (self::TRANSLATED as $klucz) {
            Configuration::deleteByName(self::PREFIX . $klucz);
        }
    }

    /**
     * Zapis do każdego sklepu z bieżącego kontekstu.
     *
     * To samo, co robi `ConfigStore` silnika. Powielone tutaj, a nie wzięte
     * stamtąd, bo moduł ustawień nie ma powodu zależeć od klas silnika poza
     * ekranem panelu.
     *
     * @param string|array<int,string> $wartosc
     */
    private function zapisz(string $klucz, $wartosc): void
    {
        foreach (Shop::getContextListShopID() as $idShop) {
            Configuration::updateValue($klucz, $wartosc, false, null, (int) $idShop);
        }

        if (Shop::getContext() === Shop::CONTEXT_ALL) {
            Configuration::updateGlobalValue($klucz, $wartosc);
        }
    }
}
