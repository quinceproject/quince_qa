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

require_once __DIR__ . '/vendor/autoload.php';

use Quince\Qa\Settings\QaPresenter;
use Quince\Qa\Settings\QaSettings;
use Quince\Qa\Settings\QaStore;

/**
 * Quince QA — pytania klientów o produkt i odpowiedzi sklepu.
 *
 * Klient pyta przy produkcie, sklep odpowiada w panelu, a pytanie z odpowiedzią
 * staje się treścią karty. To nie jest kanał kontaktu, tylko materiał, który
 * zostaje: następny kupujący czyta odpowiedź zamiast pytać drugi raz.
 *
 * Dlatego na karcie widać wyłącznie pytania Z ODPOWIEDZIĄ. Pytanie bez niej
 * wygląda jak zignorowany klient, a nie jak sekcja w budowie.
 *
 * Moduł podaje dane, rysuje je motyw — tak samo jak przy opiniach. Znaczniki
 * należą do motywu, więc sekcja wygląda jak reszta akordeonu, a nie jak wstawka.
 */
class Quince_Qa extends Module
{
    public function __construct()
    {
        $this->name = 'quince_qa';
        $this->tab = 'front_office_features';
        $this->version = '0.1.1';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.2.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        // Ekran modułu dziedziczy po kontrolerze silnika, a szablon po jego
        // layoucie. Bez tej deklaracji PrestaShop pozwoli wyłączyć silnik,
        // a moduł przestanie otwierać swój ekran bez żadnego ostrzeżenia.
        $this->dependencies = ['quince_engine'];

        // Jeden wpis w gałęzi „Quince project". Kolejność wpisów ustala silnik.
        $this->tabs = [
            [
                'class_name' => 'AdminQuinceQa',
                'visible' => true,
                'name' => 'Questions',
                'parent_class_name' => 'AdminQuinceProject',
                'route_name' => 'quince_qa',
                'wording' => 'Questions',
                'wording_domain' => 'Modules.Quinceqa.Admin',
            ],
        ];

        parent::__construct();

        $this->displayName = $this->trans('Quince questions', [], 'Modules.Quinceqa.Admin');
        $this->description = $this->trans(
            'Customers ask about a product, the shop answers, and the answer stays on the product page.',
            [],
            'Modules.Quinceqa.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Uninstall the module? All questions and answers will be removed.',
            [],
            'Modules.Quinceqa.Admin'
        );
    }

    public function install(): bool
    {
        if (!parent::install() || !QaStore::createTable()) {
            return false;
        }

        (new QaSettings())->installDefaults();

        return $this->registerHook(['actionQuinceRegisterFeature', 'actionFrontControllerSetVariables']);
    }

    public function uninstall(): bool
    {
        (new QaSettings())->uninstall();

        return QaStore::dropTable() && parent::uninstall();
    }

    /**
     * Zgłoszenie do silnika: ekran pytań w grupie „Moduły" jego paska bocznego.
     *
     * Silnik buduje tę grupę wyłącznie ze zgłoszeń. Hook był zarejestrowany od
     * instalacji, ale bez tej metody moduł nic nie zwracał i ekran pytań nie
     * pojawiał się w pasku silnika. Bez `type`: silnik z niczego tu nie korzysta,
     * dostaje tylko adres ekranu.
     *
     * @return array<int,array{id:string,label:string,route:string}>
     */
    public function hookActionQuinceRegisterFeature(): array
    {
        return [[
            'id' => 'quince_qa',
            'label' => $this->trans('Questions', [], 'Modules.Quinceqa.Admin'),
            'route' => 'quince_qa',
        ]];
    }

    /**
     * Dane sekcji pytań dla karty produktu.
     *
     * PrestaShop wkłada je pod `$modules.quince_qa`, więc motyw rysuje z nich
     * sekcję akordeonu. Poza stroną produktu nie zwracamy nic: hook wypisuje
     * zmienne na każdej stronie sklepu, a pytania dotyczą jednego produktu.
     *
     * @return array<string,mixed>
     */
    public function hookActionFrontControllerSetVariables(): array
    {
        if (!($this->context->controller instanceof ProductController)) {
            return [];
        }

        $produkt = $this->context->controller->getProduct();

        if (!$produkt) {
            return [];
        }

        $idProduct = (int) $produkt->id;
        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        $ustawienia = new QaSettings();
        $magazyn = new QaStore();
        $naStrone = $ustawienia->perPage();

        $ile = $magazyn->countForProduct($idProduct, $idShop);

        return [
            'items' => QaPresenter::all($magazyn->forProduct($idProduct, $idShop, $naStrone)),
            'count' => $ile,
            'perPage' => $naStrone,
            'hasMore' => $naStrone < $ile,
            'canAsk' => $this->wolnoPytac($ustawienia),
            'moderated' => $ustawienia->flag('MODERATE'),
            'intro' => $ustawienia->text('DRAWER_INTRO', $idLang),
            'url' => $this->context->link->getModuleLink($this->name, 'ask', [], true),
            'urlMore' => $this->context->link->getModuleLink($this->name, 'list', [], true),
            'product' => $idProduct,
        ];
    }

    /**
     * Czy ten odwiedzający może zadać pytanie.
     *
     * Pytamy przed pokazaniem przycisku, a nie dopiero przy zapisie: przycisk,
     * który kończy się odmową, jest gorszy niż jego brak.
     */
    private function wolnoPytac(QaSettings $ustawienia): bool
    {
        if ($ustawienia->flag('ALLOW_GUESTS')) {
            return true;
        }

        return $this->context->customer !== null && (bool) $this->context->customer->isLogged();
    }

    /**
     * Menedżer modułów PrestaShopa pokazuje „Konfiguruj" tylko modułom, które
     * mają tę metodę (`ModuleRepository::__construct`, `method_exists`),
     * a my przenosimy sprzedawcę na ekran modułu w panelu.
     */
    public function getContent(): void
    {
        \Tools::redirectAdmin($this->get('router')->generate('quince_qa'));
    }
}
