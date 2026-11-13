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

/**
 * Wiersz z bazy w kształcie, który wypisuje szablon.
 *
 * Osobna klasa, bo rysują z tego dwa miejsca: pierwsza porcja składana przy
 * wydaniu strony i każda kolejna z kontrolera doładowania. Dwie kopie tej samej
 * zamiany rozjechałyby się przy pierwszej poprawce.
 */
final class QaPresenter
{
    /**
     * @param array<int,array<string,mixed>> $wiersze
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(array $wiersze): array
    {
        return array_map([self::class, 'one'], $wiersze);
    }

    /**
     * @param array<string,mixed> $w
     *
     * @return array<string,mixed>
     */
    public static function one(array $w): array
    {
        return [
            'id' => (int) $w['id_quince_qa'],
            'author' => self::podpis((string) $w['customer_name']),
            'initials' => self::inicjaly((string) $w['customer_name']),
            'date' => date('d.m.Y', (int) strtotime((string) $w['date_add'])),
            'dateIso' => substr((string) $w['date_add'], 0, 10),
            'question' => (string) $w['question'],
            'answer' => (string) ($w['answer'] ?? ''),
            'answerAuthor' => (string) ($w['answer_author'] ?? ''),
        ];
    }

    /**
     * Inicjały do kółka przy nagłówku.
     *
     * Dwie litery, bo jedna nie odróżnia dwóch Marków, a trzy przestają być
     * skrótem. Bez nazwiska zostaje sama pierwsza litera imienia.
     */
    private static function inicjaly(string $nazwa): string
    {
        $czesci = array_values(array_filter(preg_split('/\s+/', trim($nazwa)) ?: []));

        if ($czesci === []) {
            return '';
        }

        $pierwsza = mb_strtoupper(mb_substr($czesci[0], 0, 1));

        if (count($czesci) < 2) {
            return $pierwsza;
        }

        return $pierwsza . mb_strtoupper(mb_substr(end($czesci), 0, 1));
    }

    /**
     * Podpis pod pytaniem: imię i pierwsza litera nazwiska.
     *
     * Pełne nazwisko pod publicznym pytaniem to dana osobowa wystawiona bez
     * potrzeby. Do rozróżnienia dwóch Marków wystarczy inicjał.
     */
    private static function podpis(string $nazwa): string
    {
        $czesci = preg_split('/\s+/', trim($nazwa)) ?: [];

        if (count($czesci) < 2) {
            return trim($nazwa);
        }

        $ostatnia = array_pop($czesci);

        return implode(' ', $czesci) . ' ' . mb_substr($ostatnia, 0, 1) . '.';
    }
}
