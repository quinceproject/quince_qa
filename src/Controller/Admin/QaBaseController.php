<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Qa\Controller\Admin;

use Quince\Engine\Controller\Admin\QuinceController;

/**
 * Wspólna podstawa ekranów modułu.
 *
 * Jest tu jedna rzecz: spis zakładek. Moduł ma dwa widoki, więc gdyby każdy
 * kontroler wypisywał listę u siebie, dołożenie trzeciego wymagałoby poprawki
 * w dwóch miejscach — a pierwszy pominięty dawałby ekran bez zakładki, do
 * którego nie da się wrócić.
 */
abstract class QaBaseController extends QuinceController
{
    protected const DOMENA = 'Modules.Quinceqa.Admin';

    /**
     * @return array<int,array{label:string,route:string}>
     */
    protected function tabs(): array
    {
        return [
            ['label' => 'Questions', 'route' => 'quince_qa'],
            ['label' => 'Settings', 'route' => 'quince_qa_settings'],
        ];
    }

    /**
     * Parametry wspólne dla obu widoków: nagłówek, zakładki i podświetlenie
     * pozycji w bocznym pasku.
     *
     * @return array<string,mixed>
     */
    protected function chrome(string $route): array
    {
        return [
            'layoutTitle' => 'Questions',
            'layoutTitleDomain' => self::DOMENA,
            'quinceTabs' => $this->tabs(),
            'quinceTabsDomain' => self::DOMENA,
            // Boczny pasek podświetla pozycję modułu, która wskazuje kolejkę —
            // ustawienia są jej widokiem, a nie osobną pozycją w menu.
            'quinceRoute' => $route,
            'quinceNavRoute' => 'quince_qa',
        ];
    }
}
