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

use Quince\Qa\Settings\QaPresenter;
use Quince\Qa\Settings\QaSettings;
use Quince\Qa\Settings\QaStore;

/**
 * Kolejna porcja pytań z odpowiedziami.
 *
 * Pierwsza porcja stoi w kodzie strony, tę dokłada przycisk pod listą.
 * Odpowiedź niesie gotowe znaczniki, a nie dane: pojedyncze pytanie rysuje ten
 * sam szablon motywu, co przy pierwszej porcji, więc doładowane wygląda tak samo
 * jak wypisane przez serwer. Rysowanie ich drugi raz w JavaScripcie
 * rozjechałoby się przy pierwszej zmianie wyglądu.
 */
class Quince_QaListModuleFrontController extends ModuleFrontController
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
        echo json_encode($this->porcja());
        exit;
    }

    /** @return array<string,mixed> */
    private function porcja(): array
    {
        $idProduct = (int) Tools::getValue('id_product');
        $strona = max(1, (int) Tools::getValue('page'));

        if ($idProduct < 1) {
            return ['ok' => false, 'html' => '', 'hasMore' => false];
        }

        $idShop = (int) $this->context->shop->id;
        $naStrone = (new QaSettings())->perPage();
        $magazyn = new QaStore();

        $pytania = $magazyn->forProduct($idProduct, $idShop, $naStrone, $strona);
        $ile = $magazyn->countForProduct($idProduct, $idShop);

        return [
            'ok' => true,
            'html' => $this->znaczniki($pytania),
            'hasMore' => $strona * $naStrone < $ile,
            'page' => $strona,
        ];
    }

    /**
     * Pytania zamienione na znaczniki szablonem motywu.
     *
     * @param array<int,array<string,mixed>> $pytania
     */
    private function znaczniki(array $pytania): string
    {
        $szablon = _PS_THEME_DIR_ . 'templates/catalog/_partials/product-qa-item.tpl';

        if (!is_file($szablon)) {
            return '';
        }

        $wynik = '';

        foreach (QaPresenter::all($pytania) as $pytanie) {
            $this->context->smarty->assign('qPytanie', $pytanie);
            $wynik .= $this->context->smarty->fetch($szablon);
        }

        return $wynik;
    }
}
